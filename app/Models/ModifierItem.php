<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModifierItem extends Model
{
    protected $fillable = ['modifier_id', 'name', 'price'];

    /**
     * Récupère le modificateur parent.
     */
    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}