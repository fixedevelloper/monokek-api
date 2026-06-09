<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method_id',
        'amount',
        'reference','change_due','amount_received','cash_session_id'
    ];

    /**
     * La commande associée à ce paiement
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Le mode de paiement utilisé
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
    /**
     * Le mode de paiement utilisé
     */
    public function paymentMethod(): BelongsTo // <-- Change "method" to "paymentMethod"
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
