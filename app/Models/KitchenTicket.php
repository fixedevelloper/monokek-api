<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitchenTicket extends Model
{
    protected $fillable = ['order_id', 'station_id', 'status','order_round_id'];

    /**
     * Statuts possibles pour un ticket.
     */
    const STATUS_PENDING = 'pending';     // En attente
    const STATUS_PREPARING = 'preparing'; // En cours
    const STATUS_READY = 'ready';         // Prêt à servir
    const STATUS_SERVED = 'served';       // Servi

    /**
     * Le ticket appartient à une commande spécifique.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Le ticket est assigné à une station de travail.
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(KitchenStation::class, 'station_id');
    }
    public function round()
    {
        return $this->belongsTo(OrderRound::class, 'order_round_id');
    }

    public function items()
    {
        // Récupère les items du round qui appartiennent à la même station que le ticket
        return $this->hasMany(OrderItem::class, 'order_round_id', 'order_round_id')
            ->where('station_id', $this->station_id);
    }
}
