<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    /**
     * Les attributs qui sont assignables en masse.
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'order_item_id',
        'amount',
        'percentage',
        'type',
        'status',
    ];

    /**
     * Les attributs qui doivent être castés.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'float',
        'status' => 'string',
        'type' => 'string',
    ];

    /**
     * Relation avec le Serveur (User)
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation avec la Commande parente
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Relation avec la ligne de commande spécifique (utile pour l'Option C)
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Scope pour filtrer les commissions non payées
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope pour filtrer par type (Global ou Incentive)
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }
}
