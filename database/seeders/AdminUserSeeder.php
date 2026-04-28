<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::all()->keyBy('name');

        $users = [
            [
                'email' => 'admin@resto.com',
                'name' => 'Super Admin',
                'phone' => '000000000',
                'role' => 'admin',
            ],
            [
                'email' => 'cashier@resto.com',
                'name' => 'Cashier User',
                'phone' => '700000000',
                'role' => 'cashier',
            ],
            [
                'email' => 'kitchen@resto.com',
                'name' => 'Kitchen User',
                'phone' => '600000000',
                'role' => 'kitchen',
            ],
            [
                'email' => 'waiter@resto.com',
                'name' => 'Waiter User',
                'phone' => '500000000',
                'role' => 'waiter',
            ],
        ];

        foreach ($users as $data) {

            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ]
            );

            // ✅ assign role proprement Spatie
            if ($role = $roles[$data['role']] ?? null) {
                $user->syncRoles($role); // ✔ cleaner
            }
        }
    }
}