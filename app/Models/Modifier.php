<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modifier extends Model
{
    protected $fillable = ['name'];

    /**
     * Récupère les items associés à ce modificateur.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ModifierItem::class);
    }
    public function products(): BelongsToMany
{
    return $this->belongsToMany(Product::class);
}
}
