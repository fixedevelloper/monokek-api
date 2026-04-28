<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $kg = Unit::where('name', 'kg')->first()->id;
        $l = Unit::where('name', 'l')->first()->id;
        $pcs = Unit::where('name', 'pcs')->first()->id;

        $ingredients = [
            [
                'name' => 'Farine de blé',
                'unit_id' => $kg,
                'stock' => 50.000,
                'alert_qty' => 10.000,
            ],
            [
                'name' => 'Huile de tournesol',
                'unit_id' => $l,
                'stock' => 20.000,
                'alert_qty' => 5.000,
            ],
            [
                'name' => 'Œufs',
                'unit_id' => $pcs,
                'stock' => 120.000,
                'alert_qty' => 30.000,
            ],
            [
                'name' => 'Sel fin',
                'unit_id' => $kg,
                'stock' => 5.000,
                'alert_qty' => 1.000,
            ],
            [
                'name' => 'Mozzarella',
                'unit_id' => $kg,
                'stock' => 15.500,
                'alert_qty' => 5.000,
            ],
        ];

        foreach ($ingredients as $ingredient) {
            Ingredient::create($ingredient);
        }
    }
}