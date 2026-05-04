<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitchenTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->order->reference,
            'table' => $this->order->table ? $this->order->table->name : 'Emporté',
            'status' => $this->status,
            'createdAt' => $this->created_at->format('H:i'),

            'items' => $this->order->items
                ->where('product.category.kitchen_station_id', $this->station_id)
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->product->name,
                        'qty' => $item->qty,
                        // Correction ici : on prend la quantité depuis $m (le pivot)
                        'modifiers' => $item->modifiers->map(fn($m) => [
                            'name' => $m->modifierItem->name,
                            'quantity' => $m->quantity // <--- On utilise le champ de la table pivot
                        ]),
                    ];
                })->values(),
        ];
    }
}
