<?php

namespace App\Events;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // <-- IMPORTANT
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusé quand un round est ajouté à une commande DÉJÀ existante
 * (contrairement à OrderCreated qui signale la création d'une nouvelle commande).
 *
 * Permet aux listeners front (KDS, dashboard, autres postes POS) de distinguer
 * "nouvelle commande" de "round ajouté à une commande existante" et d'éviter
 * de la traiter comme une nouvelle entrée dans une liste de commandes.
 */
class RoundAddedToOrder implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $order;
    public $round;

    public function __construct(Order $order, OrderRound $round)
    {
        // Mêmes relations que OrderCreated pour que le front reçoive une structure identique
        $this->order = $order->load(['items.product', 'items.modifiers.modifierItem', 'table']);
        $this->round = $round->load(['items.product', 'items.modifiers.modifierItem']);
    }

    /**
     * Même canal que OrderCreated pour que les terminaux déjà abonnés à "orders"
     * reçoivent aussi les rounds ajoutés sans re-souscription.
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
        return 'round.added';
    }

    /**
     * Les données envoyées au terminal : la commande complète (même format que
     * order.created, via OrderResource) + le round spécifique qui vient d'être ajouté,
     * pour que le front puisse soit tout re-render, soit juste ajouter le round.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => (new OrderResource($this->order))->resolve(request()),
            'round' => [
                'id' => $this->round->id,
                'round_number' => $this->round->round_number,
                'items' => $this->round->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product->name ?? null,
                        'qty' => $item->qty,
                        'price' => $item->price,
                        'total' => $item->total,
                        'status' => $item->status,
                    ];
                }),
            ],
        ];
    }
}
