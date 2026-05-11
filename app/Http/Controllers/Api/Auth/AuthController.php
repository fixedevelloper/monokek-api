<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion initiale (Email + Password)
     * @param Request $request
     * @return string
     * @throws ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            // 'device_name' => 'required', // Utile pour identifier le terminal (ex: "Caisse-01")
        ]);

        $user = User::where('email', $request->email)->first();


        if (!$user || !Hash::check($request->password, $user->password)) {
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
                    'role' => $user->roles->first() ?->name ?? 'waiter',
            ],
            'token' => $user->createToken($request->device_name)->plainTextToken,
        ]);
    }

    /**
     * Déverrouillage rapide par PIN (LockScreen)
     * @param Request $request
     * @return JsonResponse
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



    public function updatePin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pin' => 'required|digits:4', // Ou le nombre de chiffres que tu souhaites
        ], [
            'pin.digits' => 'Le code PIN doit contenir exactement 4 chiffres.',
            'pin.required' => 'Le code PIN est obligatoire.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = auth()->user();

            // Optionnel : On peut hasher le PIN pour plus de sécurité
            // $user->pin = Hash::make($request->pin);

            $user->pin_code =Hash::make($request->pin);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Code PIN mis à jour avec succès.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Une erreur est survenue lors de la mise à jour.'
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        // 1. Vérifier si l'ancien mot de passe est correct
        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'L\'ancien mot de passe est incorrect.'
            ], 422);
        }

        // 2. Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return response()->json(['message' => 'Mot de passe mis à jour.']);
    }
    /**
     * Déconnexion
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie']);
    }
}
