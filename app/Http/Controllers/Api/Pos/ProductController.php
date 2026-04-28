<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
        public function categories(Request $request)
    {
        $query = Category::query()
            ->where('is_active', true);

        $categories = $query->orderBy('name')->get();

        return CategoryResource::collection($categories);
    }
    /**
     * Liste des produits pour le POS
     */
    public function index(Request $request)
    {
        $query = Product::with(['category','modifiers.items'])
            ->where('is_active', true);

        // Filtrage par catégorie
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Recherche textuelle (Nom ou SKU)
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderBy('name')->get();

        return ProductResource::collection($products);
    }

    /**
     * Détails d'un produit (ex: pour voir les options/modifiers)
     */
    public function show(Product $product)
    {
        return new ProductResource($product->load(['category', 'modifiers']));
    }

    /**
     * Mise à jour rapide du stock (via l'admin ou inventaire)
     */
    public function updateStock(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|numeric',
            'type' => 'required|in:add,set,remove'
        ]);

        $currentStock = $product->stock->quantity ?? 0;

        switch ($request->type) {
            case 'add':
                $newQuantity = $currentStock + $request->quantity;
                break;
            case 'remove':
                $newQuantity = max(0, $currentStock - $request->quantity);
                break;
            case 'set':
            default:
                $newQuantity = $request->quantity;
                break;
        }

        $product->stock()->updateOrCreate(
            ['product_id' => $product->id],
            ['quantity' => $newQuantity]
        );

        return response()->json([
            'message' => 'Stock mis à jour',
            'new_quantity' => $newQuantity
        ]);
    }

    /**
     * Toggle d'activation (Rupture immédiate signalée par le chef)
     */
    public function toggleStatus(Product $product)
    {
        $product->update(['is_active' => !$product->is_active]);
        
        return response()->json([
            'message' => $product->is_active ? 'Produit activé' : 'Produit désactivé',
            'status' => $product->is_active
        ]);
    }
    // Créer un produit
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'track_stock' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::create($request->all());

        return new ProductResource($product->load('category'));
    }

    // Mettre à jour un produit
    public function update(Request $request, Product $product)
    {
        $product->update($request->all());
        return new ProductResource($product->load('category'));
    }

    // Supprimer (Soft Delete)
    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'Produit supprimé'], 200);
    }
}