<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecipeItem extends Model {
    protected $fillable = ['recipe_id', 'ingredient_id', 'qty'];
    public $timestamps = false;

    public function ingredient() {
        return $this->belongsTo(Ingredient::class);
    }
}
