<?php


namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderRoundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'round_number' => $this->round_number,
            'status' => $this->status, // 'sent', 'preparing', 'served', etc.
            'note' => $this->note,
            'sent_at' => $this->sent_at ? $this->sent_at->format('Y-m-d H:i:s') : null,

            // On charge les items de ce round via une collection d'OrderItems
            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            // Petit helper pour le front : total du round uniquement
            'total_round' => $this->items->sum('total'),
        ];
    }
}
