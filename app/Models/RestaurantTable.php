<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RestaurantTable extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    public function floor() {
        return $this->belongsTo(Floor::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    /**
     * La commande actuellement "vivante" sur cette table (celle sur laquelle
     * on doit ajouter un round, afficher le total en cours, etc.), s'il y en a une.
     *
     * Utilise la même liste de statuts "ouverts" que sendRound() (Order::OPEN_STATUSES)
     * pour éviter que les deux logiques divergent silencieusement.
     *
     * hasOne + latestOfMany() : s'il y a plusieurs commandes ouvertes sur la table
     * (ne devrait normalement pas arriver grâce au verrou dans sendRound), on prend
     * la plus récente plutôt que de planter.
     */
    public function currentOrder()
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereIn('status', Order::OPEN_STATUSES)
            ->latestOfMany();
    }
}
