<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. OBLIGATOIRE : Nettoyer le cache des permissions avant de commencer
        // Cela évite les erreurs de "Permission non trouvée" après un changement de guard
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Structure par groupes pour une maintenance facile
        $permissionsByGroup = [
            'users'    => ['manage_users', 'view_staff', 'edit_permissions'],
            'orders'   => ['create_orders', 'edit_orders', 'cancel_orders', 'view_history'],
            'products' => ['manage_products', 'manage_stock', 'manage_categories'],
            'reports'  => ['view_reports', 'view_analytics'],
            'finance'  => ['close_cashier', 'manage_discounts'],
            'settings' => ['manage_settings', 'manage_branch'],
        ];

        // 3. Insertion optimisée
        foreach ($permissionsByGroup as $group => $permissions) {
            foreach ($permissions as $name) {
                Permission::updateOrCreate(
                    [
                        'name'       => $name,
                        'guard_name' => 'sanctum'
                    ],
                    [
                        // Optionnel: tu peux ajouter un champ 'group' si tu as étendu la table
                        // 'group' => $group 
                    ]
                );
            }
        }
        
        $this->command->info('Permissions pour le guard "sanctum" synchronisées avec succès !');
    }
}