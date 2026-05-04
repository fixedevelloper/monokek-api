<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modifier;
use App\Models\ModifierItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ModifierController extends Controller
{
    // Liste tous les groupes avec leurs items (Utile pour le SupplementSheet)
    public function index()
    {
        $modifiers = Modifier::with('items')->get();
        return response()->json($modifiers);
    }

    // Créer un nouveau groupe avec ses options
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'items' => 'nullable|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request) {
            // 1. Création du groupe
            $modifier = Modifier::create([
                'name' => $request->name
            ]);

            // 2. Création des items associés
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    $modifier->items()->create($item);
                }
            }

            return response()->json($modifier->load('items'), 201);
        });
    }

    // Mettre à jour un groupe et synchroniser les items
    public function update(Request $request, Modifier $modifier)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $modifier->update(['name' => $request->name]);

        return response()->json($modifier->load('items'));
    }

    // Supprimer un groupe (les items seront supprimés par cascadeOnDelete)
    public function destroy(Modifier $modifier)
    {
        $modifier->delete();
        return response()->json(['message' => 'Groupe supprimé avec succès']);
    }

    // --- Gestion spécifique des Items ---

    // Ajouter une option à un groupe existant
    public function addItem(Request $request, Modifier $modifier)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $item = $modifier->items()->create($request->all());

        return response()->json($item, 201);
    }

    // Supprimer une option spécifique
    public function destroyItem(ModifierItem $item)
    {
        $item->delete();
        return response()->json(['message' => 'Option supprimée']);
    }
}
