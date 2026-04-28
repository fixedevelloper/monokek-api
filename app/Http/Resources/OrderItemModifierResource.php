<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemModifierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'modifier_item_id' => $this->modifier_item_id,
            // On récupère le nom depuis la table des définitions (modifier_items)
            'name' => $this->whenLoaded('modifierItem', fn() => $this->modifierItem->name),
            'price' => (float) $this->price,
        ];
    }
}
