<?php

namespace App\Http\Services;

use App\Models\Order;
use App\Models\StockMovement;

class StockService {
    public static function deductFromOrder(Order $order) {
        foreach ($order->items as $orderItem) {
            $product = $orderItem->product;
            
            // Si le produit a une recette
            if ($product->recipe) {
                foreach ($product->recipe->items as $recipeItem) {
                    $totalQtyNeeded = $recipeItem->qty * $orderItem->qty;

                    // 1. Sortie de stock
                    $recipeItem->ingredient->decrement('stock', $totalQtyNeeded);

                    // 2. Historique du mouvement
                    StockMovement::create([
                        'ingredient_id' => $recipeItem->ingredient_id,
                        'type' => 'out',
                        'qty' => $totalQtyNeeded,
                        'reason' => "Vente #{$order->id}"
                    ]);
                }
            }
        }
    }
}