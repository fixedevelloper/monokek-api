<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtenir la branche à laquelle appartient cette caisse.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Obtenir toutes les sessions associées à cette caisse.
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(CashSession::class, 'register_id');
    }
}
