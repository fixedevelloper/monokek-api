<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion initiale (Email + Password)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
           // 'device_name' => 'required', // Utile pour identifier le terminal (ex: "Caisse-01")
        ]);

        $user = User::where('email', $request->email)->first();

        
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        // On réinitialise les tokens existants pour ce terminal si nécessaire
        $user->tokens()->where('name', $request->device_name)->delete();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
               'role' => $user->roles->first()?->name ?? 'waiter',
            ],
            'token' => $user->createToken($request->device_name)->plainTextToken,
        ]);
    }

    /**
     * Déverrouillage rapide par PIN (LockScreen)
     */
public function verifyPin(Request $request)
{
    $request->validate([
        'pin' => 'required|digits:4'
    ]);

    $user = $request->user();

    // On vérifie si le PIN haché en base correspond au PIN saisi
    if (!$user || !Hash::check($request->pin, $user->pin_code)) {
        return response()->json([
            'message' => 'Code PIN invalide',
            'status' => 'error'
        ], 400);
    }

    return response()->json([
        'message' => 'Accès autorisé',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role
        ]
    ]);
}

    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }
}