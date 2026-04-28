<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CashRegisterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // database/seeders/CashRegisterSeeder.php
    public function run(): void
    {
        \App\Models\CashRegister::create([
            'branch_id' => 1, // L'ID de ta succursale au Cameroun
            'name' => 'Caisse Principale - Terminal 01',
        ]);

        \App\Models\CashRegister::create([
            'branch_id' => 1,
            'name' => 'Caisse Bar',
        ]);
    }
}
