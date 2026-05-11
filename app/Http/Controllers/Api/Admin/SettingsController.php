<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Printer;
use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    /**
     * Récupérer tous les réglages sous forme de dictionnaire (Key-Value)
     */
    public function index()
    {
        // Retourne un objet propre : { "site_name": "Mon Resto", "tva": 20 }
        return response()->json(Setting::pluck('value', 'key'));
    }

    /**
     * Mettre à jour plusieurs réglages à la fois
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        // Le format attendu : { "settings": { "key1": "val1", "key2": "val2" } }
        $settings = $request->input('settings');

        if (!is_array($settings)) {
            return response()->json(['message' => 'Format invalide'], 400);
        }

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json([
            'message' => 'Configurations enregistrées',
            'data' => Setting::pluck('value', 'key')
        ]);
    }
    public function printers()
    {
        // Retourne un objet propre : { "site_name": "Mon Resto", "tva": 20 }
        return response()->json(Printer::all());
    }
    public function storePrint(Request $request)
    {
        $validated = $request->validate([
            'branch_id'  => 'required|exists:branches,id',
            'name'       => 'required|string|max:255',
            'type'       => 'required|in:escpos,label',
            'connection' => 'required|in:usb,network',
            'ip'         => 'required_if:connection,network|nullable|ip',
            'port'       => 'nullable|integer',
        ]);

        $printer = Printer::create($validated);

        return response()->json([
            'message' => 'Imprimante enregistrée avec succès',
            'printer' => $printer
        ]);
    }
    public function edtPrint(Request $request)
    {
        $validated = $request->validate([
            'branch_id'  => 'required|exists:branches,id',
            'name'       => 'required|string|max:255',
            'type'       => 'required|in:escpos,label',
            'connection' => 'required|in:usb,network',
            'ip'         => 'required_if:connection,network|nullable|ip',
            'port'       => 'nullable|integer',
        ]);

        $printer = Printer::create($validated);

        return response()->json([
            'message' => 'Imprimante enregistrée avec succès',
            'printer' => $printer
        ]);
    }
}
