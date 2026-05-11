<?php


namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Printer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrinterController extends Controller
{
    /**
     * Liste toutes les imprimantes d'une branche.
     */
    public function index(Request $request)
    {
        // On peut filtrer par branch_id si nécessaire
        $printers = Printer::query()
            ->when($request->branch_id, function ($q) use ($request) {
                return $q->where('branch_id', $request->branch_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($printers, Response::HTTP_OK);
    }

    /**
     * Enregistre une nouvelle imprimante.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        logger($request->all());
        $validated = $request->validate([
            'branch_id'  => 'required|exists:branches,id',
            'name'       => 'required|string|max:255',
            'type'       => 'required|in:escpos,label',
            'connection' => 'required|in:usb,network',
            'ip'         => 'required_if:connection,network|nullable|ip',
            'port'       => 'nullable|integer',
            'char_per_line' => 'nullable|integer',
            'use_beep'      => 'boolean', // Faire sonner l'imprimante en cuisine
            'paper_width'   => 'nullable|in:58,80', // Largeur en mm
            'location' => 'required|in:receipt,kitchen,bar,pizza,delivery',
        ]);

        try {
            $printer = Printer::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Imprimante configurée avec succès',
                'data' => $printer
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Affiche les détails d'une imprimante spécifique.
     */
    public function show(Printer $printer)
    {
        return response()->json($printer, Response::HTTP_OK);
    }

    /**
     * Met à jour une imprimante.
     * @param Request $request
     * @param Printer $printer
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Printer $printer)
    {
        $validated = $request->validate([
            'branch_id'  => 'sometimes|required|exists:branches,id',
            'name'       => 'sometimes|required|string|max:255',
            'type'       => 'sometimes|required|in:escpos,label',
            'connection' => 'sometimes|required|in:usb,network',
            'ip'         => 'required_if:connection,network|nullable|ip',
            'port'       => 'nullable|integer',
            'is_active'  => 'boolean',
            'char_per_line' => 'nullable|integer|min:20|max:64', // Très important pour le rendu
            'use_beep'      => 'boolean', // Faire sonner l'imprimante en cuisine
            'paper_width'   => 'nullable|in:58,80', // Largeur en mm
            'location' => 'required|in:receipt,kitchen,bar,pizza,delivery',
        ]);

        try {
            // Nettoyage des données si passage de Network à USB
            if (isset($validated['connection']) && $validated['connection'] === 'usb') {
                $validated['ip'] = null;
            }

            $printer->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Configuration mise à jour',
                'data' => $printer
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Échec de la mise à jour',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime une imprimante.
     */
    public function destroy(Printer $printer)
    {
        try {
            $printer->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Imprimante supprimée'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Suppression impossible',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Route utilitaire pour tester la connexion (Ping)
     * Utile pour ton bouton "Tester" sur le frontend.
     */
    public function testConnection(Printer $printer)
    {
        if ($printer->connection !== 'network') {
            return response()->json(['message' => 'Test non disponible pour USB via API'], 400);
        }

        $waitTimeoutInSeconds = 2;
        if($fp = @fsockopen($printer->ip, $printer->port ?? 9100, $errCode, $errStr, $waitTimeoutInSeconds)){
            fclose($fp);
            return response()->json(['status' => 'online', 'message' => 'Imprimante jointe avec succès']);
        } else {
            return response()->json(['status' => 'offline', 'message' => 'Impossible de joindre l\'imprimante'], 504);
        }
    }
}
