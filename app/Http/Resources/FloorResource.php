<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FloorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            
            // On charge les tables uniquement si la relation a été appelée (with('tables'))
            'tables' => RestaurantTableResource::collection($this->whenLoaded('tables')),
            
            // Optionnel : un petit compteur pour le badge sur le frontend
            'tables_count' => $this->whenCounted('tables'),
            
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
