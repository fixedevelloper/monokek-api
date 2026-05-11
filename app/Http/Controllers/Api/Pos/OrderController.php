<?php

namespace App\Http\Controllers\Api\Pos;

use App\Events\KitchenStationUpdated;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Events\TicketCreated;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Services\CommissionService;
use App\Http\Services\PrintService;
use App\Http\Services\StockService;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Product;
use App\Http\Resources\OrderResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * ENVOI D'UN ROUND (Remplace ou complète requestBill)
     * Cette méthode crée la commande si elle n'existe pas,
     * sinon elle ajoute un nouveau Round à la commande active.
     * @param Request $request
     * @param PrintService $printService
     * @return mixed
     */
    public function sendRound(Request $request, PrintService $printService)
    {
        $request->validate([
            'order_id' => 'nullable|exists:orders,id',
            'table_id' => 'required|exists:restaurant_tables,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.modifiers' => 'nullable|array',
            'note' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($printService, $request) {
            $table = RestaurantTable::with('floor')->findOrFail($request->table_id);

            // 1. RÉCUPÉRER OU CRÉER LA COMMANDE
            $order = Order::firstOrCreate(
                ['id' => $request->order_id, 'status' => 'pending'],
                [
                    'uuid' => (string)Str::uuid(),
                    'reference' => $order->reference ?? 'CMD-' . strtoupper(Str::random(8)),
                    'branch_id' => $table->floor->branch_id,
                    'table_id' => $table->id,
                    'user_id' => auth()->id(),
                    'cashier_id' => auth()->id(),
                    'status' => 'pending',
                    'type' => 'dinein',
                ]
            );

            // 2. CRÉER LE ROUND
            $roundNumber = $order->rounds()->count() + 1;
            $round = $order->rounds()->create([
                'round_number' => $roundNumber,
                'status' => 'sent',
                'note' => $request->note,
                'sent_at' => now(),
            ]);

            // 3. CRÉER LES ITEMS DANS LE ROUND
            foreach ($request->items as $itemData) {
                // On récupère le produit pour connaître sa station_id
                $product = Product::findOrFail($itemData['product_id']);

                $orderItem = $round->items()->create([
                    //'order_id' => $order->id, // Important pour la cohérence
                    'product_id' => $itemData['product_id'],
                    //'station_id' => $product->category->kitchen_station_id, // On lie l'item à sa station
                    'qty' => $itemData['qty'],
                    'price' => $itemData['price'],
                    'total' => $itemData['price'] * $itemData['qty'],
                    'status' => 'pending',
                ]);

                if (!empty($itemData['modifiers'])) {
                    foreach ($itemData['modifiers'] as $mod) {
                        $orderItem->modifiers()->create([
                            'modifier_item_id' => $mod['modifier_item_id'],
                            'price' => $mod['price'],
                            'quantity' => $mod['quantity'] ?? 1,
                        ]);
                    }
                }
            }

            // --- NOUVELLE ÉTAPE 3.5 : CRÉER LES TICKETS DE CUISINE ---
            // On groupe les items de ce round par station_id pour créer un ticket par station
         //   $itemsByStation = $round->items()->get()->groupBy('station_id');
            $itemsByStation = $order->items()->with('product.category')->get()->groupBy(function ($item) {
                return $item->product->category->kitchen_station_id;
            });
            foreach ($itemsByStation as $stationId => $items) {
                if ($stationId) { // On ne crée de ticket que si une station est définie
                    $ticket = KitchenTicket::create([
                        'order_id' => $order->id,
                        'order_round_id' => $round->id,
                        'station_id' => $stationId,
                        'status' => 'pending',
                    ]);

                    // Optionnel : Déclencher l'événement pour le temps réel du KDS
                    broadcast(new KitchenStationUpdated($ticket->station))->toOthers();
                    broadcast(new TicketCreated($ticket))->toOthers();
                }
            }

            // 4. MISE À JOUR DES TOTAUX
            $order->refreshTotals();

            // 5. MISE À JOUR DE LA TABLE
            $table->update(['status' => 'occupied']); // 'occupied' est plus logique que 'billing' ici

            // 6. IMPRESSION & NOTIFICATIONS
            $printService->queueRoundTickets($round);

            broadcast(new OrderCreated($order->load('rounds.items')))->toOthers();

            return new OrderResource($order->load(['rounds.items.product', 'table', 'rounds.kitchenTickets']));
        });
    }

    /**
     * FINALISER LE PAIEMENT (Inchangé sur la logique, mais refresh sur les relations)
     */
    public function finalizePayment(Request $request, $uuid, CommissionService $commissionService, PrintService $printService)
    {
        $request->validate([
            'payment_method' => 'required|in:cash,momo,orange,card',
            'amount_received' => 'required|numeric'
        ]);

        return DB::transaction(function () use ($printService, $commissionService, $request, $uuid) {
            $session = CashSession::where('user_id', auth()->id())->whereNull('closed_at')->first();
            if (!$session) return response()->json(['error' => 'Caisse fermée'], 403);

            $order = Order::where('uuid', $uuid)->firstOrFail();

            // Paiement
            $payMethod = PaymentMethod::where('name', $request->payment_method)->first();
            $order->payments()->create([
                'cash_session_id' => $session->id,
                'payment_method_id' => $payMethod->id,
                'amount' => $order->total,
                'amount_received' => $request->amount_received,
                'change_due' => $request->amount_received - $order->total,
            ]);

            $order->update(['status' => 'paid', 'paid_at' => now()]);
            $order->table()->update(['status' => 'free']);

            $commissionService->calculateCommissions($order);

            // Impression du ticket final (Reçu client complet)
            $printService->queueFinalReceipt($order);

            return response()->json(['message' => 'Payé', 'status' => 'success']);
        });
    }
    /**
     * Historique des commandes payées
     */
    public function history(Request $request)
    {
        $user = $request->user();

        // 1. Récupérer la session active de l'utilisateur
        $session = CashSession::where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();

        if (!$session) {
            return Helpers::error("Session de caisse introuvable.");
        }

        // 2. Récupérer les commandes avec les Rounds et leurs Items
        $orders = Order::with([
            'table',
            'user',
            'rounds' => function($query) {
                $query->orderBy('round_number', 'asc');
            },
            'rounds.items.product',
            'rounds.items.modifiers.modifierItem',
            // On garde items à la racine au cas où (pour les totaux rapides),
            // mais le front utilisera surtout rounds.items
            'items.product'
        ])
            ->where(function ($query) use ($session) {
                // Commandes payées dans cette session
                $query->whereHas('payments', function ($q) use ($session) {
                    $q->where('cash_session_id', $session->id);
                })
                    // OU commandes actives (non annulées et non payées) du jour
                    ->orWhere(function($q) {
                        $q->whereNotIn('status', ['cancelled', 'paid'])
                            ->where('created_at', '>=', now()->startOfDay());
                    });
            })
            ->latest()
            ->paginate(50); // Augmenté à 50 pour une meilleure visibilité sur le shift

        return Helpers::success(OrderResource::collection($orders));
    }

    public function historyAdmin(Request $request)
    {
        // On charge les rounds et les caissiers/serveurs pour une traçabilité totale
        $query = Order::with([
            'table',
            'user',
            'cashier',
            'rounds' => function($q) {
                $q->orderBy('round_number', 'asc');
            },
            'rounds.items.product',
            'rounds.items.modifiers.modifierItem',
            // On garde aussi les items à plat si besoin de calculs directs
            'items.product'
        ]);

        // Filtre par date (Y-m-d)
        if($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [
                $request->start_date.' 00:00:00',
                $request->end_date.' 23:59:59'
            ]);
        }

        // Filtre par recherche (Référence ou Table)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('table', function ($t) use ($search) {
                        $t->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('user', function ($u) use ($search) {
                        $u->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filtre par statut (Optionnel mais utile pour un Admin)
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(50);

        return Helpers::success(OrderResource::collection($orders));
    }

    public function index(Request $request)
    {
        $query = Order::query()->with(['table', 'user']);

        // Filtre par recherche (Référence ou Table)
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('reference', 'like', "%{$request->search}%")
                    ->orWhereHas('table', function ($sq) use ($request) {
                        $sq->where('name', 'like', "%{$request->search}%");
                    });
            });
        }

        // Filtre par Date Spécifique
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // Filtre par Statut
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Pagination pour éviter de surcharger Tauri
        $orders = $query->latest()->paginate(15);

        return OrderResource::collection($orders);
    }

    public function getActiveOrder(RestaurantTable $table)
    {
        // On cherche la commande active
        $order = Order::where('table_id', $table->id)
            ->whereIn('status', ['pending', 'processing', 'partially_paid', 'pending_payment', 'billing'])
            ->with([
                // On charge les rounds triés par ordre de création
                'rounds' => function($query) {
                    $query->orderBy('round_number', 'asc');
                },
                // On charge les détails à l'intérieur de chaque round
                'rounds.items.product',
                'rounds.items.modifiers.modifierItem',
                // On peut aussi garder les relations de base de la commande
                'table',
                'user'
            ])
            ->latest()
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Aucune commande active pour cette table',
                'data' => null
            ], 200);
        }

        // Important : Assure-toi que ton OrderResource traite bien la collection "rounds"
        return new OrderResource($order);
    }

    public function waiterOrders(Request $request)
    {
        $query = Order::with([
            'table',
            'user',
            'items.product',
            'items.variant',            // Ajouté
            'items.modifiers.modifierItem' // Ajouté (charge les modifiers ET leur définition/nom)
        ])
            ->where('user_id', auth()->id());

        // Filtre par date
        $date = $request->input('date', now()->today());
        $query->whereDate('created_at', $date);

        // Filtre par statut
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return OrderResource::collection($query->latest()->get());
    }

    public function markAsServed(Order $order)
    {
        // 1. On met à jour le statut de l'ordre
        $order->update(['status' => 'completed']);

        // 2. On met à jour tous les tickets de cuisine liés à 'served'
        $order->kitchenTickets()->update(['status' => 'served']);

        // 3. On broadcast l'info pour que la Caisse et le Serveur voient le changement
        broadcast(new OrderStatusUpdated($order->load(['table', 'items'])))->toOthers();

        return response()->json(['message' => 'Commande servie !']);
    }
    public function reprint(Order $order, PrintService $printService)
    {
        // Impression manuelle (via votre bouton React)
        $printService->queueTicket($order, 'receipt', $order->branch_id);

        return response()->json(['message' => 'Impression lancée']);
    }
}
