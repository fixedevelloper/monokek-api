<?php

namespace App\Http\Controllers\Api\Pos;

use App\Events\KitchenStationUpdated;
use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Events\TicketCreated;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Services\CommissionService;
use App\Http\Services\StockService;
use App\Models\CashSession;
use App\Models\Customer;
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
     * Étape 1 : Demande d'addition (La table passe en orange)
     */
    public function requestBill(Request $request)
    {
        $request->validate([
            'order_id' => 'nullable|exists:orders,id', // Important pour la modification
            'table_id' => 'required|exists:restaurant_tables,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*.modifier_item_id' => 'required|exists:modifier_items,id',
            'items.*.modifiers.*.price' => 'required|numeric',
            'subtotal' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            $table = RestaurantTable::with('floor')->findOrFail($request->table_id);

            // 1. TROUVER OU CRÉER LA COMMANDE
            // Si order_id est fourni, on update. Sinon, on crée.
            $order = Order::updateOrCreate(
                ['id' => $request->order_id],
                [
                    'uuid' => $request->order_id ? Order::find($request->order_id)->uuid : (string)Str::uuid(),
                    'reference' => $request->order_id ? Order::find($request->order_id)->reference : 'CMD-' . strtoupper(Str::random(8)),
                    'branch_id' => $table->floor->branch_id,
                    'table_id' => $table->id,
                    'user_id' => auth()->id(),
                    'cashier_id' => auth()->id(),
                    'status' => 'pending_payment',
                    'subtotal' => $request->subtotal,
                    'tax' => $request->tax ?? 0,
                    'total' => $request->total,
                    'type' => 'dinein',
                ]
            );

            // 2. GÉRER LES ARTICLES (SYNC)
            // Stratégie : On supprime les anciens items pour remettre les nouveaux
            // (Note: Dans un système avancé, on ne supprimerait que ceux qui n'ont pas encore été envoyés en cuisine)
            $order->items()->delete();

            foreach ($request->items as $itemData) {
                $orderItem = $order->items()->create([
                    'product_id' => $itemData['product_id'],
                    'qty' => $itemData['qty'],
                    'price' => $itemData['price'],
                    'total' => $itemData['price'] * $itemData['qty'],
                    'status' => 'pending',
                ]);

                // Dans OrderController.php
                if (!empty($itemData['modifiers'])) {
                    foreach ($itemData['modifiers'] as $mod) {
                        $orderItem->modifiers()->create([
                            'modifier_item_id' => $mod['modifier_item_id'],
                            'price' => $mod['price'],
                            'quantity' => $mod['quantity'] ?? 1, // On récupère la qté du front
                        ]);
                    }
                }
            }

            // 3. RE-GÉNÉRER LES TICKETS DE CUISINE
            // Pour éviter les doublons en cuisine, on peut supprimer les tickets 'pending' existants
            // avant de générer les nouveaux pour cette commande modifiée
            $order->kitchenTickets()->where('status', 'pending')->delete();

            $itemsByStation = $order->items()->with('product.category')->get()->groupBy(function ($item) {
                return $item->product->category->kitchen_station_id;
            });

            foreach ($itemsByStation as $stationId => $items) {
                if ($stationId) {
                    $ticket = $order->kitchenTickets()->create([
                        'station_id' => $stationId,
                        'status' => 'pending',
                    ]);

                    $ticket->load(['order', 'station']);

                    // Diffusion des événements
                    broadcast(new KitchenStationUpdated($ticket->station))->toOthers();
                    broadcast(new TicketCreated($ticket))->toOthers();
                }
            }
            $order->statusHistories()->create([
                'status' => 'created',
                'user_id' => auth()->id(),
            ]);
            // 4. MISE À JOUR DE LA TABLE
            $table->update(['status' => 'billing']);

            broadcast(new OrderCreated($order))->toOthers();

            return new OrderResource($order->load(['items.product', 'items.modifiers.modifierItem', 'table']));
        });
    }

    /**
     * Étape 2 : Finaliser le paiement (La table est libérée)
     * @param Request $request
     * @param $uuid
     * @param CommissionService $commissionService
     * @return mixed
     */
    public function finalizePayment(Request $request, $uuid,CommissionService $commissionService)
    {

        // 1. Validation stricte des moyens de paiement
        $request->validate([
            'payment_method' => 'required|in:cash,momo,orange,card',
            'note' => 'nullable|string',
            'change_due' => 'required|numeric',
            'amount_received' => 'nullable|numeric'
        ]);


        return DB::transaction(function () use ($commissionService, $request, $uuid) {
            // 1. Vérifier si l'utilisateur a une session de caisse ouverte
            $session = CashSession::where('user_id', auth()->id())
                ->whereNull('closed_at')
                ->first();

            if (!$session) {
                return response()->json(['error' => 'Veuillez ouvrir une session de caisse'], 403);
            }
            // 2. Récupération de la commande
            $order = Order::where('uuid', $uuid)->firstOrFail();

            if ($order->status === 'paid') {
                return response()->json(['message' => 'Cette commande est déjà payée'], 422);
            }

            // 3. Mise à jour de la commande
            $order->update([
                'status' => 'paid',
                'note' => $request->note ?? $order->note,
                'paid_at' => now(),
                'cashier_id' => auth()->id(),
            ]);
            $payementMethod = PaymentMethod::query()->where('name', $request->payment_method)->first();

            // 1. Créer l'enregistrement du paiement
            $order->payments()->create([
                'cash_session_id' => $session->id,
                'payment_method_id' => $payementMethod->id,
                'amount' => $order->total,
                'change_due' => $request->change_due,
                'amount_received' => $request->amount_received,
                'reference' => $request->reference ?? null,
            ]);

            // 4. Historique
            $order->statusHistories()->create([
                'status' => 'paid',
                'user_id' => auth()->id(),
            ]);

            // 5. Libération de la table
            if ($order->table_id) {
                $order->table()->update(['status' => 'free']);
                // Note: Utiliser la relation $order->table() est plus propre
            }
            // On génère l'argent pour le serveur !
            $commissionService->calculateCommissions($order);
            broadcast(new OrderStatusUpdated($order))->toOthers();

            StockService::deductFromOrder($order);
            // 6. Retourner la référence pour le ticket
            return response()->json([
                'message' => 'Paiement validé, table libérée',
                'reference' => $order->reference, // Assure-toi que ce champ existe
                'status' => 'success'
            ]);
        });
    }

    /**
     * Historique des commandes payées
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $session = CashSession::where('user_id', $user->id)
            ->whereNull('closed_at')
            ->first();

        if (!$session) {
            return Helpers::error("Session de caisse introuvable.");
        }

        $orders = Order::with(['table', 'user', 'items', 'items.product', 'items.modifiers.modifierItem'])
            ->where(function ($query) use ($session) {
                // 1. Les commandes PAYÉES dans MA session
                $query->whereHas('payments', function ($q) use ($session) {
                    $q->where('cash_session_id', $session->id);
                })
                    // 2. OU les commandes qui n'ont PAS ENCORE de paiement (En attente)
                    // On vérifie le statut pour ne pas voir les commandes annulées ou trop vieilles
                    ->orWhereNotIn('status', ['cancelled', 'pay',]);
            })
            // Optionnel : Limiter les "En attente" à aujourd'hui pour éviter de polluer la liste
            ->where('created_at', '>=', now()->startOfDay())
            ->latest()
            ->paginate(20);

        return Helpers::success(OrderResource::collection($orders));
    }

    public function historyAdmin(Request $request)
    {


        $query = Order::with(['table', 'user','cashier', 'items', 'items.product', 'items.modifiers.modifierItem']); // On ne montre que ce qui est fini

        // Filtre par date (Y-m-d)
        if($request->start_date && $request->end_date) {
            $query->whereBetween('created_at', [$request->start_date.' 00:00:00', $request->end_date.' 23:59:59']);
        }
        // Filtre par recherche (Référence ou Table)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('table', function ($t) use ($search) {
                        $t->where('name', 'like', "%{$search}%");
                    });
            });
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
        // On cherche une commande liée à cette table qui n'est pas encore 'completed' ou 'cancelled'
        $order = Order::where('table_id', $table->id)
            ->whereIn('status', ['pending', 'processing', 'partially_paid','pending_payment']) // Statuts considérés comme "actifs"
            ->with([
                'items.product',
                'items.variant',
                'items.modifiers.modifierItem'
            ])
            ->latest() // Au cas où il y en aurait plusieurs, on prend la plus récente
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Aucune commande active pour cette table',
                'data' => null
            ], 200); // On renvoie 200 avec null pour que le front sache qu'il doit créer une nouvelle commande
        }

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
}
