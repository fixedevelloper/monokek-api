<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['name' => 'Grossiste Alimentaire Express', 'phone' => '0123456789'],
            ['name' => 'La Ferme du Village', 'phone' => '0987654321'],
            ['name' => 'DistriBoissons SARL', 'phone' => '0544332211'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::create($supplier);
        }
    }
}