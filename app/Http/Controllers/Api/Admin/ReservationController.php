<?php


namespace App\Http\Controllers\Api\Admin;


use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReservationResource;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class ReservationController extends Controller
{
    /**
     * Liste des réservations avec relations
     */
    public function index(Request $request)
    {
        $query = Reservation::with(['customer', 'order.items.product']);

        // Application des filtres
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'today':
                    $query->today();
                    break;
                case 'upcoming':
                    $query->upcoming();
                    break;
                case 'past':
                    $query->where('pickup_date', '<', now());
                    break;
            }
        }

        // Recherche par nom ou téléphone via la relation customer
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('customer', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $reservations = $query->orderBy('pickup_date', 'asc')->paginate(20);

        return ReservationResource::collection($reservations);
    }

    /**
     * Création d'une réservation "Manager"
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'nullable|exists:orders,id', // Important pour la modification
            // Infos Client
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string',
            // Infos Réservation
            'pickup_date' => 'required|date|after:now',
            'guests_count' => 'nullable|integer|min:1',
            'manager_notes' => 'nullable|string',
            // Infos Commande (Items)
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'total_amount' => 'required|numeric',
        ]);

        try {
            return DB::transaction(function () use ($validated) {

                // 1. Gérer le Client (Récupère ou Crée)
                $customer = Customer::firstOrCreate(
                    ['phone' => $validated['customer_phone']],
                    ['name' => $validated['customer_name']]
                );

                // 2. Créer la Commande (Statut 'reserved')
                $order = Order::create([
                    'user_id' => auth()->id(), // Le manager qui crée
                    'cashier_id' => auth()->id(),
                    'branch_id' => 1,
                    'reference' => $validated['order_id']? Order::find($validated['order_id'])->reference : 'CMD-' . strtoupper(Str::random(8)),
                    'customer_id' => $customer->id,
                    'total' => $validated['total_amount'],
                    'status' => 'reserved', // Statut spécifique pour bloquer sans encaisser
                    'source' => 'manager_booking'
                ]);

                // 3. Ajouter les produits à la commande
                foreach ($validated['items'] as $item) {
                    $orderItem = $order->items()->create([
                        'product_id' => $item['id'],
                        'qty' => $item['quantity'],
                        'price' => $item['price'],
                        'total' => $item['price'] * $item['quantity'],
                        'status' => 'pending',
                    ]);

                    // Si des modificateurs (suppléments) sont présents
                    if (!empty($item['selectedModifiers'])) {
                        foreach ($item['selectedModifiers'] as $mod) {
                            $orderItem->modifiers()->create([
                                 'modifier_item_id' => $mod['modifier_item_id'],
                            'price' => $mod['price'],
                            'quantity' => $mod['quantity'] ?? 1, // On récupère la qté du front
                            ]);
                        }
                    }
                }

                // 4. Créer la Réservation liée
                $reservation = Reservation::create([
                    'order_id' => $order->id,
                    'customer_id' => $customer->id,
                    'pickup_date' => Carbon::parse($validated['pickup_date']),
                    'guests_count' => $validated['guests_count'] ?? 1,
                    'manager_notes' => $validated['manager_notes'],
                    'reservation_status' => 'confirmed'
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => $reservation->load('customer', 'order.items')
                ], 210);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la réservation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pay(Request $request, $id)
    {
        $order=Order::find($id);
        // Sécurité : Vérifier si déjà payé
        if ($order->status === 'paid') {
            return response()->json(['message' => 'Cette commande est déjà réglée'], 422);
        }

        return DB::transaction(function () use ($order, $request) {
            // 1. Mise à jour de la commande
            $order->update([
                'status' => 'pending_payment',
            ]);
            $order->statusHistories()->create([
                'status' => 'pending_payment',
                'user_id' => auth()->id(),
            ]);
            broadcast(new OrderStatusUpdated($order))->toOthers();
            return response()->json(['message' => 'Paiement validé']);
        });
    }
    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string',
            'pickup_date' => 'required|date',
            'guests_count' => 'nullable|integer|min:1',
            'manager_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'total_amount' => 'required|numeric',
        ]);

        try {
            return DB::transaction(function () use ($validated, $reservation) {

                // 1. Mise à jour du Client
                $reservation->customer->update([
                    'name' => $validated['customer_name'],
                    'phone' => $validated['customer_phone'],
                ]);

                // 2. Mise à jour de la Commande liée
                $order = $reservation->order;
                $order->update([
                    'total_amount' => $validated['total_amount'],
                ]);

                // 3. Rafraîchir les articles de la commande
                // On supprime les anciens items et on insère les nouveaux
                $order->items()->delete();

                foreach ($validated['items'] as $item) {
                    // Gestion de la provenance ID (product_id ou id selon le format front)
                    $productId = $item['product_id'] ?? $item['id'];

                    $orderItem = $order->items()->create([
                        'product_id' => $productId,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'] ?? $item['unit_price'],
                    ]);

                    // Optionnel : Gestion des modificateurs si modifiés
                    if (!empty($item['modifiers'])) {
                        foreach ($item['modifiers'] as $mod) {
                            $orderItem->modifiers()->create([
                                'modifier_name' => $mod['modifier_name'] ?? $mod['name'],
                                'price' => $mod['price']
                            ]);
                        }
                    }
                }

                // 4. Mise à jour de la Réservation
                $reservation->update([
                    'pickup_date' => Carbon::parse($validated['pickup_date']),
                    'guests_count' => $validated['guests_count'],
                    'manager_notes' => $validated['manager_notes'],
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Réservation mise à jour avec succès'
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Annuler une réservation
     */
    public function destroy(Reservation $reservation)
    {
        return DB::transaction(function () use ($reservation) {
            // On passe la commande en annulé pour libérer le stock si nécessaire
            if ($reservation->order) {
                $reservation->order->update(['status' => 'cancelled']);
            }

            $reservation->delete();
            return response()->json(['message' => 'Réservation supprimée']);
        });
    }
}
