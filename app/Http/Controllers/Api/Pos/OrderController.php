<?php

namespace App\Http\Controllers\Api\Pos;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Events\TicketCreated;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Http\Services\StockService;
use App\Models\CashSession;
use App\Models\Order;
use App\Models\PaymentMethod;
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
            'table_id' => 'required|exists:restaurant_tables,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            // Validation des modificateurs
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*.modifier_item_id' => 'required|exists:modifier_items,id',
            'items.*.modifiers.*.price' => 'required|numeric',
            'subtotal' => 'required|numeric',
            'total' => 'required|numeric',
        ]);

        return DB::transaction(function () use ($request) {
            $table = RestaurantTable::with('floor')->findOrFail($request->table_id);

            // 1. Créer la commande principale
            $order = Order::create([
                'uuid' => (string) Str::uuid(),
                'reference' => 'CMD-' . strtoupper(Str::random(8)),
                'branch_id' => $table->floor->branch_id,
                'table_id' => $table->id,
                'user_id' => auth()->id(),
                'status' => 'pending_payment',
                'subtotal' => $request->subtotal,
                'tax' => $request->tax ?? 0,
                'total' => $request->total,
                'type' => 'dinein',
            ]);

            // 2. Enregistrer les articles et leurs modificateurs
            foreach ($request->items as $itemData) {
                // On crée d'abord l'article de la commande
                $orderItem = $order->items()->create([
                    'product_id' => $itemData['product_id'],
                    'qty' => $itemData['qty'],
                    'price' => $itemData['price'],
                    'total' => $itemData['price'] * $itemData['qty'],
                    'status' => 'pending',
                ]);

                // 3. On enregistre les modificateurs pour cet article précis
                if (!empty($itemData['modifiers'])) {
                    foreach ($itemData['modifiers'] as $mod) {
                        $orderItem->modifiers()->create([
                            'modifier_item_id' => $mod['modifier_item_id'],
                            'price' => $mod['price'],
                        ]);
                    }
                }
            }

            // 4. Mettre à jour le statut de la table
            $table->update(['status' => 'billing']);
            broadcast(new OrderCreated($order))->toOthers();
            // On charge les relations pour la ressource de retour
            return new OrderResource($order->load(['items.product', 'items.modifiers.modifierItem', 'table']));
        });
    }

    /**
     * Étape 2 : Finaliser le paiement (La table est libérée)
     */
    public function finalizePayment(Request $request, $uuid)
    {

        // 1. Validation stricte des moyens de paiement
        $request->validate([
            'payment_method' => 'required|in:cash,momo,orange,card',
            'note' => 'nullable|string',
            'change_due' => 'required|numeric',
            'amount_received' => 'nullable|numeric'
        ]);


        return DB::transaction(function () use ($request, $uuid) {
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

            // 4. CRÉATION DES TICKETS DE CUISINE VIA LES CATÉGORIES
            $itemsByStation = $order->items->groupBy(function ($item) {
                // On récupère la station définie au niveau de la catégorie
                return $item->product->category->kitchen_station_id;
            });

            foreach ($itemsByStation as $stationId => $items) {
                // On ne crée le ticket que si la catégorie est liée à une station
                if ($stationId) {
                    // 1. On crée le ticket et on le stocke dans une variable
                    $ticket = $order->kitchenTickets()->create([
                        'station_id' => $stationId,
                        'status' => 'pending',
                    ]);

                    // 2. On charge les relations nécessaires (order et items) avant de diffuser
                    // Cela évite que le frontend reçoive un ticket vide
                    $ticket->load(['order', 'station']);

                    // 3. On diffuse l'événement
                    broadcast(new TicketCreated($ticket))->toOthers();

                    Log::info("Ticket cuisine diffusé pour la station : " . $stationId);
                }
            }

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
            broadcast(new OrderStatusUpdated($order))->toOthers();

            //StockService::deductFromOrder($order);
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
        $orders = Order::with(['table', 'user', 'items', 'items.product', 'items.modifiers.modifierItem'])
            ->whereIn('status', ['paid', 'completed', 'pending_payment'])
            ->latest()
            ->paginate(20);


        return Helpers::success(OrderResource::collection($orders));
    }
    public function historyAdmin(Request $request)
    {


        $query = Order::with(['table', 'user', 'items', 'items.product', 'items.modifiers.modifierItem'])
            ->whereIn('status', ['paid', 'cancelled']); // On ne montre que ce qui est fini

        // Filtre par date (Y-m-d)
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
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
}