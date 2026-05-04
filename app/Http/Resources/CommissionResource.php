<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'percentage' => $this->percentage,
            'type' => $this->type, // 'global' ou 'incentive'
            'status' => $this->status,
            'created_at' => $this->created_at->format('d/m/Y H:i'),

            // On simplifie les relations pour le Frontend
            'waiter_name' => $this->waiter->name ?? 'Inconnu',
            'waiter_id' => $this->waiter->id,
            'order_reference' => $this->order->reference ?? "#{$this->order_id}",

            // Détail du produit si c'est une option C
            'product_name' => $this->orderItem->product->name ?? 'Vente Globale',

            // Meta-data pour faciliter le styling CSS au front
            'is_incentive' => $this->type === 'incentive',
            'status_label' => $this->status === 'paid' ? 'Payé' : 'En attente',
        ];
    }
}
