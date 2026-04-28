<?php

namespace App\Events;

use App\Models\KitchenTicket;
use App\Http\Resources\KitchenTicketResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // <--- NE PAS OUBLIER
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCreated implements ShouldBroadcast // <--- AJOUTER ICI
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $ticket;

    public function __construct(KitchenTicket $ticket)
    {
        // On hydrate le ticket avec toutes les relations pour la cuisine
        $this->ticket = $ticket->load([
            'order.table',
            'order.items.product.category',
            'order.items.modifiers.modifierItem'
        ]);
    }

    /**
     * Canal spécifique à la station (ex: kitchen.station.1)
     */
    public function broadcastOn(): array
    {
        return [new Channel('kitchen.station.' . $this->ticket->station_id)];
    }

    /**
     * Nom de l'événement côté Frontend (Echo)
     */
    public function broadcastAs(): string
    {
        return 'ticket.created';
    }

    /**
     * Données formatées via la Resource
     */
    public function broadcastWith(): array
    {
        return (new KitchenTicketResource($this->ticket))->resolve();
    }
}