<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = ['purchase_order_id', 'ingredient_id', 'qty', 'price'];
    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    public function purchase_order() {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function ingredient() {
        return $this->belongsTo(Ingredient::class);
    }

}
