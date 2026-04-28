<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PaymentMethodSeeder::class,
            RoleSeeder::class,
            PermissionSeeder::class,
            CompanySeeder::class,
            ModifierSeeder::class,
            ProductSeeder::class,
            AdminUserSeeder::class,
            FloorSeeder::class,        // 4. Les zones/étages
            TableSeeder::class,
            KitchenStationSeeder::class,
            UnitSeeder::class,
            IngredientSeeder::class,
            SupplierSeeder::class,
            CashRegisterSeeder::class
        ]);

      
        
    }
}