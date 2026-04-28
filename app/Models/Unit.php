<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Unit extends Model {
    protected $fillable = ['name'];
    public $timestamps = false; // Pas de timestamps dans ta migration

    public function ingredients() {
        return $this->hasMany(Ingredient::class);
    }
}
