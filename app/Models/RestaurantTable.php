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
}
