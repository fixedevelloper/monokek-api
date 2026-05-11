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

        $stationId = $request->station_id;

        $tickets = KitchenTicket::where('station_id', $stationId)
            ->whereIn('status', ['pending', 'preparing'])
            ->with([
                'order.table',
                'round',
                'round.items' => function($query) use ($stationId) {
                    // Use whereHas to filter items by their product's category station
                    $query->whereHas('product.category', function($q) use ($stationId) {
                        $q->where('kitchen_station_id', $stationId);
                    })->with(['product', 'modifiers.modifierItem']);
                }
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        return KitchenTicketResource::collection($tickets);
    }
    public function updateStatus(Request $request, KitchenTicket $ticket)
    {
        $request->validate([
            'status' => 'required|in:pending,preparing,ready,served'
        ]);

        // 1. Mise à jour du ticket spécifique
        $ticket->update(['status' => $request->status]);

        // 2. On récupère le Round associé
        $round = $ticket->round; // S'assurer que la relation 'round' est définie dans KitchenTicket

        if (!$round) {
            return response()->json(['error' => 'Round non trouvé pour ce ticket'], 404);
        }

        // 3. Mise à jour du statut du ROUND
        // On utilise ->kitchenTickets()->get() pour éviter le null et forcer la collection
        $allRoundTickets = $round->kitchenTickets()->get();

        $newRoundStatus = $round->status;

        if ($allRoundTickets->every(fn($t) => $t->status === 'ready')) {
            $newRoundStatus = 'served';
        } elseif ($allRoundTickets->contains(fn($t) => $t->status === 'preparing')) {
            $newRoundStatus = 'preparing';
        }

        if ($newRoundStatus !== $round->status) {
            $round->update(['status' => $newRoundStatus]);
        }

        // 4. Mise à jour optionnelle de l'ORDER global
        $order = $round->order;
        if ($order) {
            // Si tous les rounds de la commande sont 'ready', l'order passe à 'ready'
            $allRounds = $order->rounds()->get();
            if ($allRounds->every(fn($r) => $r->status === 'served')) {
                $order->update(['status' => 'ready']);
            }
        }

        // 5. Broadcasts
        broadcast(new KitchenStationUpdated($ticket->station))->toOthers();
        broadcast(new OrderStatusUpdated($order->load(['table'])))->toOthers();

        return response()->json([
            'message' => "Statut mis à jour",
            'round_status' => $newRoundStatus
        ]);
    }
}
