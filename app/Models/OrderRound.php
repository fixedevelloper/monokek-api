<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Casts des attributs.
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'round_number' => 'integer',
    ];

    /**
     * La commande parente.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Les articles inclus dans ce round spécifique.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Helper pour savoir si le round a déjà été envoyé en cuisine.
     */
    public function isSent(): bool
    {
        return $this->status !== 'pending' && $this->sent_at !== null;
    }
}
