<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // On vide la table pour éviter les doublons si on relance le seeder
        // On utilise DB::statement pour ignorer les contraintes de clés étrangères pendant le truncate
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        PaymentMethod::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $methods = [
            ['id' => 1, 'name' => 'cash'],
            ['id' => 2, 'name' => 'momo'],
            ['id' => 3, 'name' => 'card'],
        ];

        foreach ($methods as $method) {
            PaymentMethod::create($method);
        }
    }
}