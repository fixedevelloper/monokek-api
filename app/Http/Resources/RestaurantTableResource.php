<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestaurantTableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'seats' => $this->seats,
            'status' => $this->status ?? 'free',
            'floor_name' => $this->whenLoaded('floor', fn() => $this->floor->name),
            // whenLoaded() (au lieu de isset()) : n'inclut 'total' que si la
            // relation currentOrder a été eager-loadée en amont, sans jamais
            // déclencher de requête N+1. Le ?-> gère le cas table libre
            // (currentOrder chargée mais null).
            'total' => $this->whenLoaded('currentOrder', fn() => $this->currentOrder?->total),
        ];
    }
}
