<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Recipe extends Model {
    protected $fillable = ['product_id'];

    public function product() {
        return $this->belongsTo(Product::class);
    }

    public function items() {
        return $this->hasMany(RecipeItem::class);
    }
}