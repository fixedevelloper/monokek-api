<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KitchenStation extends Model
{
    protected $fillable = ['branch_id', 'name'];

    /**
     * Une station appartient à une branche (établissement).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Une station reçoit plusieurs tickets de cuisine.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class, 'station_id');
    }
}
