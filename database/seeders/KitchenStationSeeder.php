<?php
namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\KitchenStation;
use Illuminate\Database\Seeder;

class KitchenStationSeeder extends Seeder
{
    public function run(): void
    {
        // On récupère la branche (Mono-Kek Douala)
        $branch = Branch::first();

        if (!$branch) return;

        // 1. Création des Stations
        $stations = [
            ['name' => 'Cuisine Chaude'],
            ['name' => 'Grillade & Braises'],
            ['name' => 'Bar & Boissons'],
        ];

        foreach ($stations as $s) {
            $station = KitchenStation::updateOrCreate(
                ['name' => $s['name'], 'branch_id' => $branch->id],
                ['name' => $s['name']]
            );

            // 2. Liaison automatique avec les catégories existantes
            if ($s['name'] === 'Cuisine Chaude') {
                Category::whereIn('name', ['Cuisine', 'Entrées'])
                    ->update(['kitchen_station_id' => $station->id]);
            }

            if ($s['name'] === 'Grillade & Braises') {
                Category::where('name', 'Grillades')
                    ->update(['kitchen_station_id' => $station->id]);
            }

            if ($s['name'] === 'Bar & Boissons') {
                Category::where('name', 'Boissons')
                    ->update(['kitchen_station_id' => $station->id]);
            }
        }
    }
}