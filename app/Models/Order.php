<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $guarded = [];
    /**
     * Statuts représentant une commande encore "vivante" — sur laquelle on peut
     * légitimement ajouter un round, afficher le total en cours, etc.
     * Tout statut absent de cette liste (paid, completed, cancelled, ...) signifie
     * que la commande est close : il faut en créer une nouvelle pour la table.
     *
     * Utilisée par :
     * - RestaurantTable::currentOrder()
     * - PosOrderController::sendRound() (remplace l'ancien private const OPEN_ORDER_STATUSES)
     *
     * Confirme la liste exacte avec Rodrigue avant de merger — notamment le sort
     * de 'completed' (servi mais pas payé ? ou déjà clos ?) et 'draft'.
     */
    public const OPEN_STATUSES = [
        'pending',
        'preparing',
        'ready',
        'billing',
        'pending_payment',
    ];
    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax'      => 'decimal:2',
        'discount' => 'decimal:2',
        'total'    => 'decimal:2',
        'paid_at'  => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /* -------------------------------------------------------------------------- */
    /*                                 RELATIONS                                  */
    /* -------------------------------------------------------------------------- */

    /**
     * Les vagues d'envoi (Rounds) de la commande.
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(OrderRound::class)->orderBy('round_number');
    }

    /**
     * Récupère tous les articles de la commande à travers les différents rounds.
     * C'est magique : ça permet de garder $order->items fonctionnel !
     */
    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            OrderItem::class,
            OrderRound::class,
            'order_id',       // Clé étrangère sur OrderRound
            'order_round_id', // Clé étrangère sur OrderItem
            'id',             // Clé locale sur Order
            'id'              // Clé locale sur OrderRound
        );
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }
// Dans App\Models\Order.php
    public function kitchenTickets()
    {
        // Order -> hasMany -> OrderRound -> hasMany -> KitchenTicket
        return $this->hasManyThrough(
            KitchenTicket::class,
            OrderRound::class,
            'order_id',       // Clé étrangère sur OrderRound
            'order_round_id', // Clé étrangère sur KitchenTicket
            'id',             // Clé locale sur Order
            'id'              // Clé locale sur OrderRound
        );
    }
    /* -------------------------------------------------------------------------- */
    /*                                  HELPERS                                   */
    /* -------------------------------------------------------------------------- */

    /**
     * Calcule et met à jour les totaux de la commande en fonction de tous les rounds.
     */
    public function refreshTotals(): void
    {
        $subtotal = $this->items()->sum('total');

        $this->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + $this->tax - $this->discount
        ]);
    }

    /**
     * Récupère le dernier paiement validé.
     */
    public function lastPayment()
    {
        return $this->payments()->latest()->first();
    }
}
