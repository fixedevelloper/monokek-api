<?php

namespace App\Http\Controllers\Api\Kitchen;

use App\Events\KitchenStationUpdated;
use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\KitchenStation;
use App\Models\KitchenTicket;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Http\Resources\KitchenTicketResource;

class TicketController extends Controller
{
    public function getStationTickets($stationId)
    {
        $tickets = KitchenTicket::with([
            'order.table',
            'order.items.product.category',
            'order.items.modifiers.modifierItem'
        ])
            ->where('station_id', $stationId)
            ->whereIn('status', ['pending', 'preparing'])
            ->orderBy('created_at', 'asc')
            ->get();

        return KitchenTicketResource::collection($tickets);
    }
    public function getStations()
    {
        //$branchId = auth()->user()->branch_id; // Ou via un paramètre

        $stations = KitchenStation::query()
            ->withCount([
                'tickets' => function ($query) {
                    $query->whereIn('status', ['pending', 'preparing']);
                }
            ])
            ->get();

        return response()->json([
            'data' => $stations->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'pending_tickets_count' => $s->tickets_count
            ])
        ]);
    }

    public function index(Request $request)
    {
        $request->validate([
            'station_id' => 'required|exists:kitchen_stations,id'
        ]);

        $tickets = KitchenTicket::with([
            'order.table',
            'order.items.product.category',
            'order.items.modifiers.modifierItem'
        ])
            ->where('station_id', $request->station_id)
            ->whereIn('status', ['pending', 'preparing'])
            ->orderBy('created_at', 'asc')
            ->get();

        return KitchenTicketResource::collection($tickets);
    }
    public function updateStatus(Request $request, KitchenTicket $ticket)
    {
        $request->validate([
            'status' => 'required|in:pending,preparing,ready,served'
        ]);

        // 1. Mise à jour du ticket de cuisine spécifique
        $ticket->update(['status' => $request->status]);

        // 2. Récupération de la commande parente avec ses tickets
        $order = $ticket->order;
        $allTickets = $order->kitchenTickets;

        // 3. Logique de mise à jour automatique du statut de l'Order
        $newOrderStatus = $order->status;


        if ($allTickets->every(fn($t) => $t->status === 'ready')) {
            // Si TOUTES les stations ont fini (ex: Pizza prête ET Boissons prêtes)
            $newOrderStatus = 'ready';

        } elseif ($allTickets->contains(fn($t) => $t->status === 'preparing')) {
            // Si au moins une station a commencé
            $newOrderStatus = 'preparing';
        }

        if ($newOrderStatus !== $order->status) {
            $order->update(['status' => $newOrderStatus]);
        }
        // Déclencher la mise à jour pour la station concernée
        broadcast(new KitchenStationUpdated($ticket->station))->toOthers();
        // 4. Déclenchement de l'événement WebSocket pour l'Order
        // On charge les relations nécessaires pour que le serveur sache quelle table est prête
        broadcast(new OrderStatusUpdated($order->load(['table', 'items.product'])))->toOthers();

        return response()->json([
            'message' => "Ticket {$ticket->id} mis à jour, Order {$order->reference} est désormais {$newOrderStatus}",
            'ticket_status' => $ticket->status,
            'order_status' => $order->status
        ]);
    }
}