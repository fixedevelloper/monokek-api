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
            // On récupère la référence de la commande parente
            'reference' => $this->order->reference, 
            // On récupère le nom ou le numéro de la table
            'table' => $this->order->table ? $this->order->table->name : 'Emporté',
            'status' => $this->status,
            'createdAt' => $this->created_at->format('H:i'),
            
            // On filtre les items de la commande pour ne renvoyer que ceux 
            // qui appartiennent à la station de ce ticket
            'items' => $this->order->items
                ->where('product.category.kitchen_station_id', $this->station_id)
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->product->name,
                        'qty' => $item->qty,
                        // On inclut les modificateurs (ex: cuisson, suppléments)
                        'modifiers' => $item->modifiers->map(fn($m) => [
                            'name' => $m->modifierItem->name
                        ]),
                    ];
                })->values(), // values() pour réinitialiser les clés du tableau après le filtre
        ];
    }
}
