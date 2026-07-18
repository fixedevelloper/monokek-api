<?php

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusé quand un round DÉJÀ envoyé est modifié (qty changée, item ajouté/retiré)
 * tant qu'il n'est pas encore servi. Distinct de RoundAddedToOrder (qui signale
 * la création d'un nouveau round) pour que le front sache qu'il doit juste
 * re-render le round existant, pas en ajouter un nouveau à la liste.
 */
class RoundUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $round;

    public function __construct(Order $order, OrderRound $round)
    {
        $this->order = $order;
        $this->round = $round;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'round.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order' => (new OrderResource($this->order))->resolve(request()),
            'round_id' => $this->round->id,
        ];
    }
}
