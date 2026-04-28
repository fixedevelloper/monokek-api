<?php

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // <-- IMPORTANT
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        // On charge les relations nécessaires (ex: les plats commandés)
        $this->order = $order->load(['items.product', 'items.modifiers.modifierItem', 'table']);
        logger($this->order);
    }

    /**
     * Le nom du canal sur lequel diffuser.
     * Pour une cuisine, un canal public "orders" est souvent suffisant en local.
     */
    public function broadcastOn(): array
    {
       
        return [
            new Channel('orders'),
        ];
    }

    /**
     * Le nom de l'événement reçu par JavaScript (Echo).
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }

    /**
     * Les données spécifiques que tu veux envoyer au terminal.
     */
    public function broadcastWith(): array
    {
        return (new OrderResource($this->order))->resolve(request());
    }
}