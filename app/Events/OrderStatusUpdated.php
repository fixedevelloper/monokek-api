<?php

namespace App\Events;

use App\Models\Order;
use App\Http\Resources\OrderResource;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
       $this->order = $order->load(['items.product', 'items.modifiers.modifierItem', 'table']);
    }

    public function broadcastOn(): array
    {
        // On diffuse sur le même canal que les créations pour simplifier
        return [new Channel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    public function broadcastWith(): array
    {
        // On renvoie la ressource complète ou juste les champs modifiés
        return (new OrderResource($this->order))->resolve();
    }
}