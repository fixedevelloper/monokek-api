<?php

namespace App\Http\Controllers\Api\Kitchen;

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
        ->withCount(['tickets' => function ($query) {
            $query->whereIn('status', ['pending', 'preparing']);
        }])
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

        $ticket->update(['status' => $request->status]);

        return response()->json([
            'message' => "Statut du ticket mis à jour : {$request->status}",
            'status' => $ticket->status
        ]);
    }
}