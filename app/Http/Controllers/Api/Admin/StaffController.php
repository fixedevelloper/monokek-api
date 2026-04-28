<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Helpers\Helpers;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    /**
     * Liste tout le staff de la succursale actuelle
     */
    public function index(Request $request)
    {
        logger($request->user()->permissions);
        $branchId = $request->user()->branch_id;

       $staff = User::query()
    ->with([
        'roles:id,name',
        'permissions:id,name',
    ])
    ->latest()
    ->get();

        return Helpers::success($staff);
    }

    /**
     * Créer un nouveau membre du staff
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|exists:roles,name',
        ]);

        return DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
               // 'branch_id' => $request->user()->branch_id, // Auto-assignation à la branche de l'admin
            ]);

            $user->assignRole($request->role);

            return response()->json([
                'message' => 'Membre du staff créé avec succès',
                'user' => $user->load('roles')
            ], 201);
        });
    }

    /**
     * Mettre à jour un membre (Profil + Rôle)
     */
    public function update(Request $request, User $staff)
    {
        // Sécurité : Vérifier que le staff appartient à la même branche
        if ($staff->branch_id !== $request->user()->branch_id) {
            return response()->json(['message' => 'Action non autorisée'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $staff->id,
            'role' => 'sometimes|exists:roles,name',
        ]);

        if ($request->has('role')) {
            $staff->syncRoles([$request->role]);
        }

        $staff->update($request->only('name', 'email', 'phone', 'is_active'));

        return response()->json([
            'message' => 'Profil mis à jour',
            'user' => $staff->load('roles')
        ]);
    }

    /**
     * Supprimer (ou révoquer) un accès
     */
    public function destroy(Request $request, User $staff)
    {
        if ($staff->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous supprimer vous-même'], 422);
        }

        // Pour Mono-Kek, on peut soit supprimer, soit désactiver
        $staff->delete(); 

        return response()->json(['message' => 'Accès révoqué avec succès']);
    }

    /**
     * Récupérer les rôles disponibles pour le formulaire d'invitation
     */
    public function roles()
    {
        // On ne retourne pas le rôle 'super-admin' pour des raisons de sécurité
        $roles = Role::where('name', '!=', 'super-admin')->get(['id', 'name']);
        return response()->json(['data' => $roles]);
    }
public function updatePermissions(Request $request, $uuid)
{
    $request->validate([
        'permissions' => 'present|array',
        'permissions.*' => 'string|exists:permissions,name',
    ]);

    $user = User::where('uuid', $uuid)->firstOrFail();

    try {
        // ✅ sécurité + nettoyage
        $permissions = collect($request->permissions)
            ->filter()
            ->values();

        // ✅ transformation en modèles Spatie (plus robuste)
        $permissionModels = Permission::whereIn('name', $permissions)->get();

        // ✅ sync propre Spatie
        $user->syncPermissions($permissionModels);
$user->refresh(); // Recharge l'instance depuis la DB

logger([
    'direct_permissions' => $user->getPermissionNames(), // Uniquement celles du modal
    'all_permissions' => $user->getAllPermissions()->pluck('name') // Modal + Rôle
]);
        return response()->json([
            'message' => 'Permissions mises à jour avec succès',
            'count' => $permissionModels->count(),
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'message' => 'Erreur technique lors de la synchronisation',
            'error' => config('app.debug') ? $e->getMessage() : null,
        ], 500);
    }
}
// app/Http/Controllers/Api/Admin/StaffController.php

public function getAllPermissions()
{
    // On récupère toutes les permissions du guard sanctum
    $permissions = \Spatie\Permission\Models\Permission::where('guard_name', 'sanctum')->get();

    // On les groupe par préfixe (ex: 'orders_create' -> groupe 'orders')
    // Ou tu peux définir un tableau de mapping fixe ici
    return response()->json([
        'data' => $permissions->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'label' => str_replace(['_', 'create', 'view', 'manage'], [' ', 'Créer', 'Voir', 'Gérer'], $p->name)
        ])
    ]);
}
}