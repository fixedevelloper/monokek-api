<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model {
    protected $fillable = ['unit_id', 'name', 'stock', 'alert_qty'];

    public function unit() {
        return $this->belongsTo(Unit::class);
    }

    public function stockMovements() {
        return $this->hasMany(StockMovement::class);
    }
}
