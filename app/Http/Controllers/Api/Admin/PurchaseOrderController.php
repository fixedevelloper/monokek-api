<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.qty' => 'required|numeric|min:0.001',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        return \DB::transaction(function () use ($request) {
            $total = collect($request->items)->sum(fn($i) => $i['qty'] * $i['price']);

            $po = PurchaseOrder::create([
                'supplier_id' => $request->supplier_id,
                'total' => $total,
                'status' => 'received' // On considère ici réception immédiate
            ]);

            foreach ($request->items as $item) {
                $po->items()->create($item);

                // On augmente le stock de l'ingrédient
                $ingredient = \App\Models\Ingredient::find($item['ingredient_id']);
                $ingredient->increment('stock', $item['qty']);

                // On trace le mouvement
                $ingredient->stockMovements()->create([
                    'type' => 'in',
                    'qty' => $item['qty'],
                    'reason' => "Achat #{$po->id}"
                ]);
            }

            return response()->json(['message' => 'Commande fournisseur enregistrée et stock mis à jour']);
        });
    }
}