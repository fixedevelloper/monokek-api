<?php


namespace App\Http\Services;

use App\Models\Order;
use App\Models\Commission;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Calcule et enregistre les commissions d'une commande.
     * @param Order $order
     * @return mixed|void
     */
    public function calculateCommissions(Order $order)
    {
        // 1. Sécurité : On évite de recalculer si des commissions existent déjà pour cette commande
        if ($order->commissions()->exists()) {
            return;
        }

        $globalRate = 0.02; // Option A : 2%

        return DB::transaction(function () use ($order, $globalRate) {
            foreach ($order->items as $item) {
                $product = $item->product;

                // Option C : Montant fixe prioritaire
                if ($product && $product->incentive_amount > 0.0) {
                    $this->createCommission($order, $item, $product->incentive_amount * $item->qty, 'incentive');
                }
                // Option A : Pourcentage global
                else {
                    $amount = ($item->price * $item->qty) * $globalRate;
                    $this->createCommission($order, $item, $amount, 'global', $globalRate * 100);
                }
            }
        });
    }

    /**
     * Marquer toutes les commissions d'un serveur comme payées.
     * @param int $userId
     * @return
     */
    public function settleWaiterCommissions(int $userId)
    {
        return Commission::where('user_id', $userId)
            ->where('status', 'pending')
            ->update([
                'status' => 'paid',
                'paid_at' => now(), // Pense à ajouter cette colonne dans ta migration si besoin
            ]);
    }

    /**
     * Helper privé pour la création
     * @param Order $order
     * @param $item
     * @param $amount
     * @param $type
     * @param null $percentage
     * @return
     */
    private function createCommission(Order $order, $item, $amount, $type, $percentage = null)
    {
        return Commission::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'amount' => $amount,
            'percentage' => $percentage,
            'type' => $type,
            'status' => 'pending'
        ]);
    }
}
