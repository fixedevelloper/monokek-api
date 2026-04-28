<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecipeResource extends JsonResource {
    public function toArray($request) {
        return [
            'id' => $this->id,
            'product_name' => $this->product->name,
            'ingredients' => $this->items->map(fn($item) => [
                'name' => $item->ingredient->name,
                'qty' => (float) $item->qty,
                'unit' => $item->ingredient->unit->name
            ]),
        ];
    }
}