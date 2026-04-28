<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Http\Resources\StockMovementResource;
use App\Http\Resources\UnitResource;
use App\Models\Ingredient;
use App\Http\Resources\IngredientResource;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IngredientController extends Controller
{
    public function index()
    {
        $ingredients = Ingredient::with('unit')->get();
        return IngredientResource::collection($ingredients);
    }

    public function units()
    {
        $ingredients = Unit::query()->get();
        return UnitResource::collection($ingredients);
    }
    public function mouvements()
{
    // On charge la relation ingredient et son unité
    $movements = StockMovement::with('ingredient.unit')
        ->latest()
        ->paginate(50);

    return StockMovementResource::collection($movements);
}
    public function store(Request $request)
    {
        // 1. Validation stricte des données
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:ingredients,name',
            'unit_id' => 'required|exists:units,id',
            'stock' => 'required|numeric|min:0',
            'alert_qty' => 'required|numeric|min:0',
        ]);

        // 2. Utilisation d'une transaction pour garantir l'intégrité
        return DB::transaction(function () use ($validated) {

            // Création de l'ingrédient
            $ingredient = Ingredient::create($validated);

            // 3. Création automatique du mouvement de stock initial
            // C'est crucial pour l'historique (Stock History)
            if ($ingredient->stock > 0) {
                $ingredient->stockMovements()->create([
                    'type' => 'in',
                    'qty' => $ingredient->stock,
                    'reason' => 'Stock initial à la création',
                ]);
            }

            // 4. Retourner la ressource formatée pour le Frontend
            return new IngredientResource($ingredient->load('unit'));
        });
    }

    public function updateAlert(Request $request, Ingredient $ingredient)
    {
        $request->validate(['alert_qty' => 'required|numeric|min:0']);
        $ingredient->update(['alert_qty' => $request->alert_qty]);
        return response()->json(['message' => 'Seuil d alerte mis à jour']);
    }

    // Ajustement manuel (ex: perte, vol, erreur comptage)
    public function adjustStock(Request $request, Ingredient $ingredient)
    {
        $request->validate([
            'qty' => 'required|numeric|min:0.001',
            'type' => 'required|in:in,out',
            'reason' => 'nullable|string'
        ]);

        $qty = $request->qty;

        // Calcul du nouveau stock
        $newStock = ($request->type === 'in')
            ? $ingredient->stock + $qty
            : $ingredient->stock - $qty;

        DB::transaction(function () use ($ingredient, $request, $qty, $newStock) {
            // 1. Mettre à jour l'ingrédient
            $ingredient->update(['stock' => $newStock]);

            // 2. Créer le mouvement pour l'historique
            $ingredient->stockMovements()->create([
                'type' => $request->type,
                'qty' => $qty,
                'reason' => $request->reason ?? 'Ajustement manuel'
            ]);
        });

        return response()->json([
            'message' => 'Stock mis à jour',
            'new_stock' => $newStock
        ]);
    }
}