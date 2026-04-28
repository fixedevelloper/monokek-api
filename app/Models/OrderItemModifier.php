<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemModifier extends Model
{
    // Pas de timestamps dans ta migration, donc on désactive
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function modifierItem(): BelongsTo
    {
        return $this->belongsTo(ModifierItem::class);
    }
}
