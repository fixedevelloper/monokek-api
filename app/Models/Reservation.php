<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    /**
     * Les attributs qui peuvent être assignés en masse.
     */
    protected $fillable = [
        'order_id',
        'customer_id',
        'pickup_date',
        'guests_count',
        'manager_notes',
        'reservation_status',
    ];

    /**
     * Conversion automatique des types (Casting).
     * Très important pour manipuler pickup_date comme un objet Carbon/DateTime.
     */
    protected $casts = [
        'pickup_date' => 'datetime',
        'guests_count' => 'integer',
    ];

    /**
     * Relation : Une réservation appartient à une commande.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relation : Une réservation appartient à un client.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Scope pour filtrer les réservations du jour (Utile pour le dashboard du Manager).
     */

    public function scopeToday($query)
    {
        return $query->whereDate('pickup_date', now()->today());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('pickup_date', '>', now());
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('reservation_status', $status);
    }
}
