<?php

namespace Database\Seeders;

use App\Models\Floor;
use App\Models\RestaurantTable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TableSeeder extends Seeder
{
    public function run(): void
    {
        // 1. On récupère tous les floors créés par le FloorSeeder
        $floors = Floor::all();

        if ($floors->isEmpty()) {
            $this->command->warn("Aucun étage trouvé. Assure-toi de lancer FloorSeeder avant.");
            return;
        }

        foreach ($floors as $floor) {
            // On définit un préfixe basé sur le nom du floor (ex: Terrasse -> T)
            $prefix = strtoupper(substr($floor->name, 0, 1));
            
            // Nombre de tables à créer par étage (tu peux ajuster)
            $numberOfTables = 8;

            for ($i = 1; $i <= $numberOfTables; $i++) {
                // Formatage du nom : T-01, T-02, etc.
                $tableName = sprintf("%s-%02d", $prefix, $i);

                RestaurantTable::updateOrCreate(
                    [
                        'name' => $tableName,
                        'floor_id' => $floor->id,
                    ],
                    [
                        // On varie les capacités pour le test
                        'seats' => ($i % 2 == 0) ? 4 : 2, 
                        'status' => 'free',
                    ]
                );
            }
        }

        $this->command->info('Tables générées avec succès pour tous les étages !');
    }
}