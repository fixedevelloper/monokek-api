<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'qty' => (int) $this->qty,
            'price' => (float) $this->price,
            'total' => (float) $this->total,
            'status' => $this->status,

            // ✔ Produit (safe même si null)
            'product' => $this->whenLoaded('product', function () {
                return [
                    'id' => $this->product?->id,
                    'name' => $this->product?->name,
                ];
            }),

            // ✔ Variant (optionnel)
            'variant' => $this->whenLoaded('variant', function () {
                return $this->variant ? [
                    'id' => $this->variant->id,
                    'name' => $this->variant->name,
                ] : null;
            }),

            // ✔ Modifiers (toujours tableau propre)
            'modifiers' => OrderItemModifierResource::collection(
                $this->whenLoaded('modifiers')
            ),

            // ✔ bonus utile frontend
            'formatted_total' => number_format($this->total, 0, ',', ' ') . ' FCFA',
        ];
    }
}