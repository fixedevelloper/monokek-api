<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Exception;

class PrintQueueController extends Controller
{
    /**
     * Traite un job d'impression réseau en direct via Socket TCP
     * @param Request $request
     * @param $id
     * @return
     */
    public function dispatchNetwork(Request $request, $id)
    {
        $request->validate([
            'ip' => 'required|ip',
            'port' => 'integer',
            'job_type' => 'required|string',
            'order' => 'required|array'
        ]);

        $ip = $request->input('ip');
        $port = $request->input('port', 9100);
        $jobType = $request->input('job_type');
        $order = $request->input('order');
        $store = $request->input('store', []);

        try {
            // 1. Connexion directe à l'Xprinter via le réseau local
            $connector = new NetworkPrintConnector($ip, $port, 3); // 3 secondes de timeout
            $printer = new Printer($connector);

            // Sélection de la table de caractères pour les accents (Français/Cameroun)
            $printer->selectCharacterTable(6); // ISO-8859-1 / Windows-1252

            if ($jobType === 'kitchen' || $jobType === 'bar') {
                $this->printKitchenReceipt($printer, $jobType, $order);
            } else {
                $this->printClientReceipt($printer, $order, $store);
            }

            // 2. Fermeture et impulsion de découpe automatique
            $printer->cut();
            $printer->close();

            // Optionnel : Mettre à jour le statut dans votre BDD locale
            PrintQueue::where('id', $id)->update(['status' => 'completed']);

            return response()->json(['success' => true, 'message' => 'Imprimé avec succès']);

        } catch (Exception $e) {
            // PrintJob::where('id', $id)->update(['status' => 'failed', 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => "Échec de connexion à l'Xprinter ($ip) : " . $e->getMessage()
            ], 500);
        }
    }

    private function printKitchenReceipt(Printer $printer, $type, $order)
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2, 2);
        $printer->text("BON " . strtoupper($type) . "\n\n");

        $printer->setTextSize(1, 1);
        $printer->text("TABLE : " . ($order['table']['name'] ?? 'N/A') . "\n");
        $printer->text("Serveur : " . ($order['cashier']['name'] ?? 'N/A') . "\n");
        $printer->text(date('d/m/Y H:i:s') . "\n");
        $printer->text("==========================================\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $items = $order['items'] ?? [];
        foreach ($items as $item) {
            $printer->text(strtoupper($item['qty'] . "x " . ($item['product']['name'] ?? 'Inconnu')) . "\n");

            if (!empty($item['modifiers'])) {
                foreach ($item['modifiers'] as $m) {
                    if (isset($m['modifier_item']['name'])) {
                        $printer->text("  + " . $m['modifier_item']['name'] . "\n");
                    }
                }
            }
            if (!empty($item['note'])) {
                $printer->text("  NOTE : " . $item['note'] . "\n");
            }
        }
        $printer->text("------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Ref : " . ($order['reference'] ?? '') . "\n");
        $printer->feed(2);
    }

    private function printClientReceipt(Printer $printer, $order, $store)
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // ── CHARGEMENT ET IMPRESSION DU LOGO DEPUIS LE DOSSIER PUBLIC ──
        try {
            $logoPath = public_path('images/logo.png');

            if (file_exists($logoPath)) {
                $logo = EscposImage::load($logoPath);

                /*
                 * 🛑 FIX ICI : Remplacement de ->graphics() par ->bitImage()
                 * L'argument Printer::IMG_DOUBLE_WIDTH ou Printer::IMG_DEFAULT
                 * force l'envoi sous forme de matrice de points brute, universelle sur 100% des Xprinter.
                 */
                $printer->bitImage($logo, Printer::IMG_DOUBLE_WIDTH);
                $printer->feed(1);
            }
        } catch (Exception $e) {
            Log::error("Impossible d'imprimer le logo : " . $e->getMessage());
        }

        // Nom Enseigne
        $storeName = strtoupper($store['store_name'] ?? "RESTO");
        $printer->setTextSize(2, 2);
        $printer->text($storeName . "\n");

        $printer->setTextSize(1, 1);
        if (!empty($store['store_address'])) $printer->text($store['store_address'] . "\n");
        if (!empty($store['store_phone']))   $printer->text("Tel : " . $store['store_phone'] . "\n");
        $printer->text(date('d/m/Y H:i:s') . "\n");
        $printer->text("------------------------------------------\n");

        // Infos Commande
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("Table    : " . ($order['table']['name'] ?? 'N/A') . "\n");
        $printer->text("Facture  : " . ($order['reference'] ?? 'N/A') . "\n");
        $printer->text("Serveur  : " . ($order['user']['name'] ?? $order['waiter']['name'] ?? 'N/A') . "\n");
        $printer->text("------------------------------------------\n");

        // Liste des articles par Services (Rounds)
        $rounds = $order['rounds'] ?? [];
        foreach ($rounds as $index => $round) {
            $roundItems = $round['items'] ?? [];
            if (empty($roundItems)) continue;

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("--- SERVICE #" . ($index + 1) . " ---\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            foreach ($roundItems as $i) {
                $qty = $i['qty'] ?? 1;
                $name = mb_strimwidth($i['product']['name'] ?? 'Article', 0, 22, "..");
                $totalPrice = $i['total'] ?? 0;

                // Alignement propre des colonnes
                $line = sprintf("%-2dx %-24s %6s XAF\n", $qty, $name, $totalPrice);
                $printer->text($line);
            }
        }

        // Totaux
        $printer->text("==========================================\n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(2, 1);
        $printer->text("TOTAL : " . ($order['total'] ?? 0) . " XAF\n");
        $printer->setTextSize(1, 1);
        $printer->text("------------------------------------------\n");

        // Règlements
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $payments = $order['payments'] ?? [];
        if (!empty($payments)) {
            $printer->text("RÈGLEMENTS :\n");
            foreach ($payments as $payment) {
                $method = $payment['payment_method']['name'] ?? $payment['payment_method_name'] ?? "Espèces";
                $printer->text(sprintf("- %-20s : %s XAF\n", $method, $payment['amount'] ?? 0));
            }
        } else {
            $printer->text("RÈGLEMENT : En attente\n");
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("\nMerci de votre visite !\n");
        $printer->feed(2);
    }
}
