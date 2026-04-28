<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentMethod extends Model
{
    protected $fillable = ['name'];

    /**
     * Récupérer tous les paiements effectués avec ce mode de paiement
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}