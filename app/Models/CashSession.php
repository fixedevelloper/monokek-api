<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CashSession extends Model {
      protected $fillable = ['register_id','user_id','opening_amount','closing_amount','opened_at',
      'closed_at','expected_amount','note'];
    
    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function register() {
        return $this->belongsTo(CashRegister::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function payments() {
        return $this->hasMany(Payment::class);
    }
}
