<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class LicenseController extends Controller
{
    // Cette clé ne doit jamais sortir du code PHP (ou mettre dans .env)
    private $security_salt = "VOTRE_PHRASE_SECRETE_ULTRA_CONFIDENTIELLE_2026";

    /**
     * ACTIVER LE SYSTÈME
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate(Request $request)
    {
        $request->validate(['key' => 'required|string']);
        $licenseKey = $request->key;

        try {
            logger(env('SERVER_LICENSE'));
            // 1. Appel vers ton serveur Master
            $response = Http::timeout(10)->post(env('SERVER_LICENSE').'api/verify-license', [
                'key' => $licenseKey
            ]);

            // Si le serveur Master répond 404 (Clé inexistante)
            if ($response->status() === 404) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette clé de licence n\'existe pas.'
                ], 404);
            }

            $data = $response->json();
            $expiryDate = $data['expiry_date'];
            $isExpired = $data['expired'] ?? false;

            // 2. Signature locale (Sécurité)
            $signature = hash_hmac('sha256', $licenseKey . $expiryDate, $this->security_salt);

            // 3. Sauvegarde locale
            Setting::updateOrCreate(['key' => 'license_key'], ['value' => $licenseKey]);
            Setting::updateOrCreate(['key' => 'license_expiry'], ['value' => $expiryDate]);
            Setting::updateOrCreate(['key' => 'license_signature'], ['value' => $signature]);

            // 4. Réponse structurée pour le Frontend
            return response()->json([
                'success' => !$isExpired, // Succès seulement si non expiré
                'active' => true,
                'expired' => $isExpired,
                'license_key' => $data['license_key'],
                'expiry_date' => Carbon::parse($expiryDate)->format('d/m/Y'),
                'days_left' => $data['days_left'],
                'message' => $isExpired ? 'Licence expirée' : 'Activation réussie !'
            ]);

        } catch (\Exception $e) {
            logger($e);
            return response()->json([
                'success' => false,
                'message' => 'Serveur d\'activation injoignable.'
            ], 503);
        }
    }

    /**
     * VÉRIFIER LE STATUT (Appelé par le Frontend)
     */
    public function status()
    {
        $key = Setting::where('key', 'license_key')->value('value');
        $expiry = Setting::where('key', 'license_expiry')->value('value');
        $storedSignature = Setting::where('key', 'license_signature')->value('value');

        if (!$key || !$expiry || !$storedSignature) {
            return response()->json(['active' => false, 'message' => 'Aucune licence trouvée'], 200);
        }

        // VÉRIFICATION D'INTÉGRITÉ (Anti-fraude base de données)
        $expectedSignature = hash_hmac('sha256', $key . $expiry, $this->security_salt);

        if ($storedSignature !== $expectedSignature) {
            return response()->json([
                'active' => false,
                'error' => 'FRAUD_DETECTED',
                'message' => 'L\'intégrité de la licence a été compromise (modification manuelle détectée).'
            ], 403);
        }

        // VÉRIFICATION DE LA DATE
        $expiryDate = Carbon::parse($expiry);
        $isExpired = Carbon::now()->greaterThan($expiryDate);
        $daysLeft = (int) Carbon::now()->diffInDays($expiryDate, false);

        return response()->json([
            'active' => true,
            'license_key' => $this->maskKey($key), // On masque la clé pour le front
            'expiry_date' => $expiryDate->format('d/m/Y'),
            'expired' => $isExpired,
            'days_left' => $daysLeft > 0 ? $daysLeft : 0
        ]);
    }

    /**
     * Fonction privée pour masquer la clé
     */
    private function maskKey($key) {
        $parts = explode('-', $key);
        if(count($parts) < 2) return "****-****";
        return $parts[0] . "-****-****-" . end($parts);
    }
}
