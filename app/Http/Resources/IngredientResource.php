<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IngredientResource extends JsonResource {
    public function toArray($request) {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'stock' => (float) $this->stock,
            'alert_qty' => (float) $this->alert_qty,
            'unit' => $this->unit->name, // Retourne "kg" au lieu de unit_id: 1
            'is_low_stock' => $this->stock <= $this->alert_qty,
        ];
    }
}
