<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function categories(Request $request)
    {
        $query = Category::with(['products' => function($query) {
            $query->where('is_active', true); // Optionnel : seulement les plats dispos
        }])
            ->where('is_active', true);

        $categories = $query->orderBy('name')->get();

        return CategoryResource::collection($categories);
    }

    /**
     * Liste des produits pour le POS
     * @param Request $request
     * @return AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'modifiers.items'])
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
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'sku' => 'nullable|string|unique:products,sku',
            'stock_count' => 'nullable|integer|min:0',
            'incentive_amount' => 'nullable|integer|min:0',
            'alert_stock' => 'nullable|integer|min:0',
            'type' => 'required|in:storable,consumable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // Validation image
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // On prépare les données (FormData envoie des strings, on cast si besoin)
        $data = $request->only(['name', 'category_id', 'price', 'sku', 'stock_count', 'alert_stock','incentive_amount','type']);

        // Forcer le track_stock à true si on gère du stock
        $data['track_stock'] = $request->has('stock');

        // Gestion de l'image
        if ($request->hasFile('image')) {
            // Stocke dans storage/app/public/products
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = 'storage/'.$path;
        }

        $product = Product::create($data);

        return new ProductResource($product->load('category'));
    }

    // Mettre à jour un produit

    public function update(Request $request,  $id)
    {
     $product=Product::query()->find($id);

        // 1. Validation stricte
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'price' => 'sometimes|required|numeric|min:0',
            'incentive_amount' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|unique:products,sku,' . $product->id, // Ignore l'ID actuel pour l'unique
            'stock' => 'nullable|integer',
            'alert_stock' => 'nullable|integer',
            'is_active' => 'sometimes|boolean',
            'type' => 'required|in:storable,consumable',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Récupération des données sauf l'image pour l'instant
        $data = $request->except(['image', '_method']);

        // 3. Gestion de l'image (si un nouveau fichier est envoyé)
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image du disque si elle existe
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            // Stocker la nouvelle image
            $data['image'] = 'storage/'.$request->file('image')->store('products', 'public');
        }

        // 4. Mise à jour en base de données
        $product->update($data);

        // 5. Retourner la ressource avec la relation chargée
        return new ProductResource($product->load('category'));
    }
    public function syncModifiers(Request $request, Product $product)
    {
        $request->validate([
            'modifier_ids' => 'array',
            'modifier_ids.*' => 'exists:modifiers,id'
        ]);

        // sync() va ajouter les nouveaux groupes et retirer ceux qui ne sont plus dans le tableau
        $product->modifiers()->sync($request->modifier_ids);

        return response()->json(['message' => 'Modificateurs synchronisés']);
    }

    public function bulkImport(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.name' => 'required|string',
            'items.*.price' => 'required',
            'items.*.category' => 'required|string',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $importedCount = 0;
               // $branch_id = auth()->user()->branch_id;
                $branch = Branch::first();
                $branch_id = $branch->id;
                foreach ($request->items as $item) {
                    // 1. Gérer la catégorie (la trouver ou la créer dynamiquement)
                    $category = Category::firstOrCreate(
                        ['name' => trim($item['category'])],
                        ['slug' => Str::slug($item['category']), 'branch_id' => $branch_id]
                    );

                    // 2. Créer le produit
                    Product::create([
                        'name' => $item['name'],
                        'price' => (float) $item['price'],
                        'category_id' => $category->id,
                        'is_active' => true,
                    ]);

                    $importedCount++;
                }

                return response()->json([
                    'message' => "Succès ! $importedCount produits ont été importés.",
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'message' => "Erreur lors de l'importation : " . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $product=Product::find($id);
        $product->delete();
        return response()->json(['message' => 'Produit supprimé'], 200);
    }

}
