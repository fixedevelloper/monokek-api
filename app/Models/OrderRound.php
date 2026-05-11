<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class OrderRound extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'round_number',
        'status',
        'note',
        'sent_at'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'round_number' => 'integer',
    ];

    /* -------------------------------------------------------------------------- */
    /*                                 RELATIONS                                  */
    /* -------------------------------------------------------------------------- */

    /**
     * La commande globale associée à ce service.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
    /**
     * Un round peut générer plusieurs tickets de cuisine
     * (ex: un pour le Bar, un pour la Cuisine)
     */
    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class, 'order_round_id');
    }
    /**
     * Les articles (plats/boissons) ajoutés lors de ce round.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_round_id');
    }

    /**
     * Relation pour accéder directement aux modificateurs de tous les items du round.
     * Très utile pour l'impression du ticket de cuisine.
     */
    public function itemModifiers(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItemModifier::class,
            OrderItem::class,
            'order_round_id', // Clé étrangère sur OrderItem
            'order_item_id',  // Clé étrangère sur OrderItemModifier
            'id',             // Clé locale sur OrderRound
            'id'              // Clé locale sur OrderItem
        );
    }

    /* -------------------------------------------------------------------------- */
    /*                                  HELPERS                                   */
    /* -------------------------------------------------------------------------- */

    /**
     * Calcule le total financier de ce round spécifique.
     */
    public function getTotalAttribute(): float
    {
        return (float) $this->items()->sum('total');
    }

    /**
     * Vérifie si le round est en attente d'envoi.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Vérifie si le round a été envoyé en préparation.
     */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'preparing', 'served']) && $this->sent_at !== null;
    }

    /**
     * Formate le numéro du round pour l'affichage (ex: "Suite #2").
     */
    public function getLabelAttribute(): string
    {
        return $this->round_number === 1 ? 'Premier Service' : "Suite #{$this->round_number}";
    }
}
