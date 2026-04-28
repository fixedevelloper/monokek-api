<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'kg'],
            ['name' => 'g'],
            ['name' => 'l'],
            ['name' => 'ml'],
            ['name' => 'pcs'], // Pièces
            ['name' => 'btl'], // Bouteille
            ['name' => 'pack'],
        ];

        foreach ($units as $unit) {
            Unit::create($unit);
        }
    }
}