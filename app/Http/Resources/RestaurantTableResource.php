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
            // On peut ajouter ici le total de la commande en cours si besoin
            'current_bill' => $this->when(isset($this->active_order_total), $this->active_order_total),
        ];
    }
}
