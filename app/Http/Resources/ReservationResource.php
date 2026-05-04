<?php


namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pickup_date' => $this->pickup_date, // DateTime casté dans le modèle
            'guests_count' => $this->guests_count,
            'manager_notes' => $this->manager_notes,
            'reservation_status' => $this->reservation_status,

            // Inclusion du Client (Nom, Phone, etc.)
            'customer' => new CustomerResource($this->whenLoaded('customer')),

            // Inclusion de la Commande et de ses détails
            'order' => new OrderResource($this->whenLoaded('order')),

            'created_at' => $this->created_at->format('d/m/Y H:i'),
            'updated_at' => $this->updated_at->format('d/m/Y H:i'),
        ];
    }
}
