<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ingredient_id' => $this->ingredient_id,
            
            // On inclut l'ingrédient si la relation est chargée (Eager Loading)
            'ingredient' => new IngredientResource($this->whenLoaded('ingredient')),
            
            'type' => $this->type, // 'in', 'out', 'adjust'
            
            // Conversion en float pour le frontend (JS)
            'qty' => (float) $this->qty,
            
            'reason' => $this->reason ?? 'Aucune note',
            
            // Formatage de la date pour un affichage propre sans JS complexe côté client
            'created_at' => $this->created_at->toISOString(),
            'formatted_date' => $this->created_at->translatedFormat('d M Y, H:i'),
        ];
    }
}