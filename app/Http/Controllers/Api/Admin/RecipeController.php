<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;

use App\Models\Recipe;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{/**
     * Enregistrer ou mettre à jour la recette d'un produit
     */
    public function store(Request $request, $productId)
    {
        $product = Product::findOrFail($productId);

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.qty' => 'required|numeric|min:0.0001',
        ]);

        return DB::transaction(function () use ($request, $product) {
            // 1. On récupère ou on crée la recette pour ce produit
            $recipe = Recipe::firstOrCreate(['product_id' => $product->id]);

            // 2. On supprime les anciens ingrédients pour repartir à zéro (Mise à jour propre)
            $recipe->items()->delete();

            // 3. On insère les nouveaux composants
            foreach ($request->items as $item) {
                $recipe->items()->create([
                    'ingredient_id' => $item['ingredient_id'],
                    'qty' => $item['qty'],
                ]);
            }

            return response()->json([
                'message' => 'Fiche technique mise à jour avec succès',
                'recipe' => $recipe->load('items.ingredient.unit')
            ]);
        });
    }

    /**
     * Voir la recette d'un produit spécifique
     */
    public function show($productId)
    {
        $recipe = Recipe::with(['items.ingredient.unit'])
            ->where('product_id', $productId)
            ->first();

        if (!$recipe) {
            return response()->json(['message' => 'Aucune recette définie', 'items' => []]);
        }

        return response()->json($recipe);
    }
}