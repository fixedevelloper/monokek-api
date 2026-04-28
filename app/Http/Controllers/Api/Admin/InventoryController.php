<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Liste complète du stock avec alertes
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'stock']);

        // Filtre pour voir uniquement les articles en stock bas
        if ($request->has('low_stock')) {
            $query->whereHas('stock', function($q) {
                $q->whereColumn('quantity', '<=', 'min_threshold');
            });
        }

        $inventory = $query->get()->map(function($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category->name ?? 'N/A',
                'current_stock' => $product->stock->quantity ?? 0,
                'min_threshold' => $product->stock->min_threshold ?? 0,
                'status' => $this->getStockStatus($product->stock),
            ];
        });

        return response()->json($inventory);
    }

    /**
     * Ajustement manuel du stock (Arrivage ou Perte)
     */
    public function adjust(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|numeric',
            'type' => 'required|in:addition,subtraction,correction',
            'reason' => 'required|string|max:255', // ex: "Nouvel arrivage", "Casse", "Inventaire"
        ]);

        return DB::transaction(function () use ($request, $product) {
            $stock = $product->stock()->firstOrCreate(
                ['product_id' => $product->id],
                ['quantity' => 0, 'min_threshold' => 5]
            );

            $oldQuantity = $stock->quantity;

            if ($request->type === 'addition') {
                $stock->increment('quantity', $request->quantity);
            } elseif ($request->type === 'subtraction') {
                $stock->decrement('quantity', max(0, $request->quantity));
            } else {
                $stock->update(['quantity' => $request->quantity]);
            }

            // 1. Historisation du mouvement (Crucial pour l'audit)
            StockLog::create([
                'product_id' => $product->id,
                'user_id' => auth()->id(),
                'type' => $request->type,
                'quantity' => $request->quantity,
                'old_quantity' => $oldQuantity,
                'new_quantity' => $stock->fresh()->quantity,
                'reason' => $request->reason,
            ]);

            return response()->json([
                'message' => 'Stock mis à jour avec succès',
                'current_stock' => $stock->fresh()->quantity
            ]);
        });
    }

    /**
     * Définir le seuil d'alerte
     */
    public function updateThreshold(Request $request, Product $product)
    {
        $request->validate(['min_threshold' => 'required|numeric|min:0']);

        $product->stock()->updateOrCreate(
            ['product_id' => $product->id],
            ['min_threshold' => $request->min_threshold]
        );

        return response()->json(['message' => 'Seuil d\'alerte mis à jour']);
    }

    private function getStockStatus($stock)
    {
        if (!$stock || $stock->quantity <= 0) return 'out_of_stock';
        if ($stock->quantity <= $stock->min_threshold) return 'low_stock';
        return 'good';
    }
}