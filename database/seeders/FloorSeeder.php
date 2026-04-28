<?php
namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Floor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FloorSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Récupérer la branche principale (ou la créer si le BranchSeeder n'a pas tourné)
    $branch = Branch::first();

        // 2. Définition des zones (Floors)
        $floors = [
            ['name' => 'Terrasse', 'slug' => 'terrasse'],
            ['name' => 'Salle VIP', 'slug' => 'salle-vip'],
            ['name' => 'Bar Central', 'slug' => 'bar-central'],
            ['name' => 'Mezzanine', 'slug' => 'mezzanine'],
        ];

        foreach ($floors as $floorData) {
            Floor::updateOrCreate(
                [
                    'name' => $floorData['name'],
                    'branch_id' => $branch->id
                ],
                [
                    // Ici tu peux ajouter d'autres champs si nécessaire
                ]
            );
        }

        $this->command->info('Zones (Floors) créées pour la branche : ' . $branch->name);
    }
}