<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Printer extends Model
{
    protected $fillable = [
        'branch_id', 
        'name', 
        'type', 
        'connection', 
        'ip', 
        'port'
    ];

    /**
     * Relation avec la succursale (Branch)
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Helper pour obtenir l'adresse de connexion complète
     */
    public function getFullAddressAttribute(): string
    {
        return $this->connection === 'lan' ? "{$this->ip}:{$this->port}" : "USB";
    }
}
