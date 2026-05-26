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
            'reference' => $this->order->reference ?? 'N/A',
            // Utilisation de l'opérateur null-safe pour plus de sécurité
            'table' => $this->order->table->name ?? 'Emporté',
            'status' => $this->status,
            'round_number' => $this->round->round_number ?? 1,
            'createdAt' => $this->created_at->format('H:i'),

            /*
               On transforme la collection d'items.
               Si tu as déjà filtré dans le contrôleur via eager loading,
               $this->round->items ne contiendra déjà que les bons items.
            */
            'items' => $this->round->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->product->name ?? 'Produit inconnu',
                    'qty' => $item->qty,
                    'modifiers' => $item->modifiers->map(fn($m) => [
                        'name' => $m->modifierItem->name ?? 'N/A',
                        'quantity' => $m->quantity
                    ]),
                    'notes' => $item->notes ?? '', // Toujours utile en cuisine
                ];
            })->values(),
        ];
    }
}
