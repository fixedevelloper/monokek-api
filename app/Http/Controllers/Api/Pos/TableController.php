<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\FloorResource;
use App\Http\Resources\RestaurantTableResource;
use App\Models\Floor;
use App\Models\RestaurantTable;
use App\Models\Table;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TableController extends Controller
{

    public function floors()
    {
        $floors = Floor::with(['tables'])
            ->withCount('tables')
            ->get();

        return FloorResource::collection($floors);
    }

public function storeFloor(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:50|unique:floors,name',
    ]);

    $floor = Floor::create($validated);

    return response()->json([
        'message' => 'Zone créée avec succès',
        'data' => $floor
    ], 201);
}
public function updateFloor(Request $request, Floor $floor) {
    $data = $request->validate(['name' => 'required|string|unique:floors,name,'.$floor->id]);
    $floor->update($data);
    return response()->json(['message' => 'Zone mise à jour']);
}
    /**
     * Liste toutes les tables groupées par zone
     */
    public function index()
    {
        // On récupère les zones avec leurs tables pour construire le plan de salle
        return Floor::with([
            'tables' => function ($query) {
                $query->orderBy('name');
            }
        ])->get();
    }
    // Créer une table (Depuis ton Modal AddTable)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'floor_id' => 'required|exists:floors,id',
            'name' => 'required|unique:restaurant_tables,name',
            'seats' => 'required|integer|min:1',

        ]);

        $table = RestaurantTable::create($validated);

        return response()->json([
            'message' => 'Table créée avec succès',
            'table' => $table
        ], 201);
    }
    public function update(Request $request, RestaurantTable $table)
    {
        $table->update($request->all());
        return new RestaurantTableResource($table);
    }
    /**
     * Changer le statut d'une table (ex: réservation ou nettoyage)
     */
    public function updateStatus(Request $request, RestaurantTable $table)
    {
        $request->validate([
            'status' => 'required|in:available,occupied,billing,maintenance'
        ]);

        $table->update(['status' => $request->status]);

        return response()->json([
            'message' => "Table {$table->name} mise à jour",
            'status' => $table->status
        ]);
    }

    /**
     * Transférer une commande d'une table à une autre
     */
    public function transfer(Request $request)
    {
        $request->validate([
            'from_table_id' => 'required|exists:tables,id',
            'to_table_id' => 'required|exists:tables,id',
        ]);

        return DB::transaction(function () use ($request) {
            $fromTable = RestaurantTable::findOrFail($request->from_table_id);
            $toTable = RestaurantTable::findOrFail($request->to_table_id);

            // 1. Vérifier si la table de destination est libre
            if ($toTable->status !== 'available') {
                return response()->json(['message' => 'La table de destination est déjà occupée'], 422);
            }

            // 2. Trouver la commande active (pending) de la table source
            $order = $fromTable->activeOrder(); // Scope ou relation personnalisée

            if (!$order) {
                return response()->json(['message' => 'Aucune commande active sur cette table'], 404);
            }

            // 3. Basculer la commande
            $order->update(['table_id' => $toTable->id]);

            // 4. Mettre à jour les statuts physiques
            $fromTable->update(['status' => 'available']);
            $toTable->update(['status' => 'occupied']);

            return response()->json([
                'message' => "Commande transférée de {$fromTable->name} vers {$toTable->name}"
            ]);
        });
    }
}
