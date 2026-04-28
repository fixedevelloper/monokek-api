<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Modifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
         $branch = Branch::first();

        // Récupération des modificateurs pour les lier plus tard
        $supplements = Modifier::where('name', 'Suppléments')->first();
        $cuisson = Modifier::where('name', 'Cuisson')->first();
        $accompagnements = Modifier::where('name', 'Accompagnements')->first();

        $categories = [
            ['name' => 'Cuisine', 'icon' => 'Utensils'],
            ['name' => 'Boissons', 'icon' => 'Beer'],
            ['name' => 'Grillades', 'icon' => 'Flame'],
            ['name' => 'Entrées', 'icon' => 'Salad'],
        ];

        foreach ($categories as $cat) {
            $category = Category::create([
                'branch_id' => $branch->id,
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'icon' => $cat['icon'],
            ]);

            if ($cat['name'] === 'Cuisine') {
                $p1 = Product::create([
                    'category_id' => $category->id,
                    'name' => 'Ndolé Viande & Crevettes',
                    'price' => 3500,
                    'is_active' => true,
                ]);
                // Le Ndolé peut avoir des suppléments (ex: plus de crevettes)
                if ($supplements) $p1->modifiers()->attach($supplements->id);

                $p2 = Product::create([
                    'category_id' => $category->id,
                    'name' => 'Poulet DG',
                    'price' => 4500,
                    'is_active' => true,
                ]);
            }

            if ($cat['name'] === 'Grillades') {
                $p3 = Product::create([
                    'category_id' => $category->id,
                    'name' => 'Poisson Braisé (Bar)',
                    'price' => 5000,
                    'is_active' => true,
                ]);
                // Le poisson a besoin d'un accompagnement (Alloco, Frites)
                if ($accompagnements) $p3->modifiers()->attach($accompagnements->id);

                $p4 = Product::create([
                    'category_id' => $category->id,
                    'name' => 'Soya de Boeuf (Portion)',
                    'price' => 2000,
                    'is_active' => true,
                ]);
                // Le Soya peut avoir des niveaux de cuisson ou des suppléments piment/oignons
                if ($cuisson) $p4->modifiers()->attach($cuisson->id);
            }

            if ($cat['name'] === 'Boissons') {
                Product::create([
                    'category_id' => $category->id,
                    'name' => 'Kadji Beer 65cl',
                    'price' => 1000,
                    'is_active' => true,
                ]);
                // En général, pas de modificateurs pour les boissons embouteillées
            }
        }
    }
}