<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'reference' => $this->reference,
            'type' => $this->type,
            'status' => $this->status,

            // Montants formatés pour le front
            'amounts' => [
                'subtotal' => (float) $this->subtotal,
                'tax' => (float) $this->tax,
                'discount' => (float) $this->discount,
                'total' => (float) $this->total,
                'formatted_total' => number_format($this->total, 0, '.', ' ') . ' FCFA',
            ],

            // Relations chargées conditionnellement
            'table' => new RestaurantTableResource($this->whenLoaded('table')),
            'waiter' => [
                'id' => $this->user_id,
                'name' => $this->whenLoaded('user', fn() => $this->user->name),
            ],
            'cashier' => [
                'id' => $this->cashier_id,
                'name' => $this->whenLoaded('cashier', fn() => $this->cashier->name),
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            'note' => $this->note,
            'created_at' => $this->created_at->format('H:i'), // Utile pour le temps d'attente
            'date' => $this->created_at->format('d/m/Y'),
        ];
    }
}
