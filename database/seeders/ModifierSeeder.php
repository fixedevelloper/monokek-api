<?php

namespace Database\Seeders;

use App\Models\Modifier;
use App\Models\ModifierItem;
use Illuminate\Database\Seeder;

class ModifierSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Extras pour Pizza / Burger
        $extras = Modifier::create(['name' => 'Suppléments']);
        
        $extras->items()->createMany([
            ['name' => 'Fromage', 'price' => 500],
            ['name' => 'Bacon', 'price' => 700],
            ['name' => 'Avocat', 'price' => 400],
            ['name' => 'Champignons', 'price' => 300],
        ]);

        // 2. Niveaux de cuisson
        $cuisson = Modifier::create(['name' => 'Cuisson']);
        
        $cuisson->items()->createMany([
            ['name' => 'Bleu', 'price' => 0],
            ['name' => 'Saignant', 'price' => 0],
            ['name' => 'A point', 'price' => 0],
            ['name' => 'Bien cuit', 'price' => 0],
        ]);

        // 3. Accompagnements payants
        $sides = Modifier::create(['name' => 'Accompagnements']);
        
        $sides->items()->createMany([
            ['name' => 'Frites', 'price' => 1000],
            ['name' => 'Salade verte', 'price' => 800],
            ['name' => 'Alloco', 'price' => 1200],
        ]);
    }
}