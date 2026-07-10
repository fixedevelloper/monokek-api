<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Floor extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Si aucune branch_id n'est fournie à la création d'un Floor, on assigne
     * automatiquement la première Branch existante — évite les Floor orphelins
     * tant qu'un vrai sélecteur de branche n'est pas branché côté front.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Floor $floor) {
            if (empty($floor->branch_id)) {
                $floor->branch_id = Branch::query()->value('id');
            }
        });
    }

    /**
     * Relation avec la branche (Etablissement)
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Relation avec les tables du restaurant
     * C'est cette relation qui est appelée dans ton FloorResource
     */
    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class);
    }
}
