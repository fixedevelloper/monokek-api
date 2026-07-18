<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintQueue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Exception;

class PrintQueueController extends Controller
{
    private const PRINTER_CHARSET = 'CP850';

    private function enc(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $converted = @iconv('UTF-8', self::PRINTER_CHARSET . '//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : $text;
    }

    public function dispatchNetwork(Request $request, $id)
    {
        $request->validate([
            'ip' => 'required|ip',
            'port' => 'integer',
            'job_type' => 'required|string',
            // 'order' n'est requis que si ce n'est pas un récap de caisse
            'order' => 'required_unless:job_type,register_summary|array'
        ]);

        $ip = $request->input('ip');
        $port = $request->input('port', 9100);
        $jobType = $request->input('job_type');
        $order = $request->input('order', []);
        $store = $request->input('store', []);
        $sessionData = $request->input('session_data', []); // Contiendra les ventes de la session

        $printer = null;

        try {
            $connector = new NetworkPrintConnector($ip, $port, 3);
            $printer = new Printer($connector);
            $printer->initialize();

            // Sélection de la table CP850
            $printer->getPrintConnector()->write(chr(27) . chr(116) . chr(2));

            // Routage des impressions
            if ($jobType === 'kitchen' || $jobType === 'bar') {
                $this->printKitchenReceipt($printer, $jobType, $order);
            } elseif ($jobType === 'register_summary') {
                // Nouvelle méthode pour le récapitulatif de caisse
                $this->printRegisterSessionSummary($printer, $sessionData, $store);
            } else {
                $this->printClientReceipt($printer, $order, $store);
            }

            // Impulsion du tiroir de caisse (Seulement pour client et récap de fin de caisse)
            if (in_array($jobType, ['client', 'register_summary'])) {
                try {
                    $printer->pulse(0, 120, 240);
                } catch (Exception $e) {
                    Log::error("Impossible d'ouvrir le tiroir de caisse : " . $e->getMessage());
                }
            }

            $printer->cut();

            PrintQueue::where('id', $id)->update(['status' => 'completed']);
            return response()->json(['success' => true, 'message' => 'Imprimé avec succès']);

        } catch (Exception $e) {
            Log::error("Échec impression réseau ID $id : " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => "Échec de connexion à l'Xprinter ($ip) : " . $e->getMessage()
            ], 500);
        } finally {
            if ($printer) {
                $printer->close();
            }
        }
    }

    /**
     * Imprime le récapitulatif des ventes d'une session de caisse
     */
    private function printRegisterSessionSummary(Printer $printer, array $session, array $store)
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        // En-tête du magasin
        $storeName = strtoupper($store['store_name'] ?? "RESTO");
        $printer->setTextSize(2, 2);
        $printer->textRaw($this->enc($storeName) . "\n");

        $printer->setTextSize(1, 1);
        $printer->textRaw($this->enc("CLOSURE / RECAPITULATIF DE CAISSE") . "\n");
        $printer->textRaw("------------------------------------------\n");

        // Informations Session
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->textRaw($this->enc("Session  : #" . ($session['id'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Caissier : " . ($session['cashier_name'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Ouverture: " . ($session['opened_at'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Fermeture: " . ($session['closed_at'] ?? date('d/m/Y H:i:s'))) . "\n");
        $printer->textRaw("------------------------------------------\n");

        // 1. FINANCES / FLUX DE TRÉSORERIE
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->textRaw($this->enc("--- FLUX DE CAISSE ---") . "\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $openingBalance = $session['opening_balance'] ?? 0;
        $expectedBalance = $session['expected_balance'] ?? 0;
        $totalSales = $session['total_sales'] ?? 0;

        $printer->textRaw(sprintf("%-25s %12s FCFA\n", $this->enc("Fond de caisse init."), $openingBalance));
        $printer->textRaw(sprintf("%-25s %12s FCFA\n", $this->enc("+ Ventes de la session"), $totalSales));
        $printer->textRaw("..........................................\n");
        $printer->textRaw(sprintf("%-25s %12s FCFA\n", $this->enc("Théorique en caisse"), $expectedBalance));
        $printer->textRaw("------------------------------------------\n");

        // 2. REPARTITION PAR MODES DE PAIEMENT
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->textRaw($this->enc("--- MODES DE REGLEMENTS ---") . "\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $payments = $session['payment_methods_totals'] ?? [];
        foreach ($payments as $payment) {
            $methodName = $this->enc($payment['name'] ?? 'Autre');
            $methodTotal = $payment['total'] ?? 0;
            $printer->textRaw(sprintf("- %-22s : %12s FCFA\n", $methodName, $methodTotal));
        }
        $printer->textRaw("------------------------------------------\n");

        // 3. RECAPITULATIF DES ARTICLES VENDUS (Le top des ventes de la session)
        $items = $session['sold_items_summary'] ?? [];
        if (!empty($items)) {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->textRaw($this->enc("--- ARTICLES VENDUS ---") . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            foreach ($items as $item) {
                $qty = $item['qty'] ?? 0;
                $name = $this->enc(mb_strimwidth($item['product_name'] ?? 'Article', 0, 22, ".."));
                $totalPrice = $item['total'] ?? 0;

                // Formatage aligné : Qte x NomArticle -> Prix cumulé
                $line = sprintf("%-3dx %-23s %9s FCFA\n", $qty, $name, $totalPrice);
                $printer->textRaw($line);
            }
            $printer->textRaw("------------------------------------------\n");
        }

        // Pied de page du rapport
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->textRaw(date('d/m/Y H:i:s') . "\n");
        $printer->textRaw($this->enc("Rapport généré avec succès.") . "\n");
        $printer->feed(3);
    }

    private function printKitchenReceipt(Printer $printer, $type, $order)
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setTextSize(2, 2);
        $printer->textRaw($this->enc("BON " . strtoupper($type)) . "\n\n");

        $printer->setTextSize(1, 1);
        $printer->textRaw($this->enc("TABLE : " . ($order['table']['name'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Serveur : " . ($order['cashier']['name'] ?? 'N/A')) . "\n");
        $printer->textRaw(date('d/m/Y H:i:s') . "\n");
        $printer->textRaw("==========================================\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $items = $order['items'] ?? [];
        foreach ($items as $item) {
            $label = $item['qty'] . "x " . ($item['product']['name'] ?? 'Inconnu');
            $printer->textRaw($this->enc(strtoupper($label)) . "\n");

            if (!empty($item['modifiers'])) {
                foreach ($item['modifiers'] as $m) {
                    if (isset($m['modifier_item']['name'])) {
                        $printer->textRaw($this->enc("  + " . $m['modifier_item']['name']) . "\n");
                    }
                }
            }
            if (!empty($item['note'])) {
                $printer->textRaw($this->enc("  NOTE : " . $item['note']) . "\n");
            }
        }
        $printer->textRaw("------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->textRaw($this->enc("Ref : " . ($order['reference'] ?? '')) . "\n");
        $printer->feed(2);
    }

    private function printClientReceipt(Printer $printer, $order, $store)
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        try {
            $logoPath = public_path('images/logo.png');
            if (file_exists($logoPath)) {
                $logo = EscposImage::load($logoPath);
                $printer->bitImage($logo, Printer::IMG_DOUBLE_WIDTH);
                $printer->feed(1);
            }
        } catch (Exception $e) {
            Log::error("Impossible d'imprimer le logo : " . $e->getMessage());
        }

        $storeName = strtoupper($store['store_name'] ?? "RESTO");
        $printer->setTextSize(2, 2);
        $printer->textRaw($this->enc($storeName) . "\n");

        $printer->setTextSize(1, 1);
        if (!empty($store['store_address'])) $printer->textRaw($this->enc($store['store_address']) . "\n");
        if (!empty($store['store_phone']))   $printer->textRaw($this->enc("Tel : " . $store['store_phone']) . "\n");
        $printer->textRaw(date('d/m/Y H:i:s') . "\n");
        $printer->textRaw("------------------------------------------\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->textRaw($this->enc("Table    : " . ($order['table']['name'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Facture  : " . ($order['reference'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Serveur  : " . ($order['user']['name'] ?? $order['waiter']['name'] ?? 'N/A')) . "\n");
        $printer->textRaw("------------------------------------------\n");

        $rounds = $order['rounds'] ?? [];
        foreach ($rounds as $index => $round) {
            $roundItems = $round['items'] ?? [];
            if (empty($roundItems)) continue;

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->textRaw($this->enc("--- SERVICE #" . ($index + 1) . " ---") . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            foreach ($roundItems as $i) {
                $qty = $i['qty'] ?? 1;
                $name = $this->enc(mb_strimwidth($i['product']['name'] ?? 'Article', 0, 22, ".."));
                $totalPrice = $i['total'] ?? 0;

                $line = sprintf("%-2dx %-24s %6s FCFA\n", $qty, $name, $totalPrice);
                $printer->textRaw($line);
            }
        }

        $printer->textRaw("==========================================\n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(2, 1);
        $printer->textRaw($this->enc("TOTAL : " . ($order['total'] ?? 0) . " FCFA") . "\n");
        $printer->setTextSize(1, 1);
        $printer->textRaw("------------------------------------------\n");

        $payments = $order['payments'] ?? [];
        if (!empty($payments)) {
            $printer->textRaw($this->enc("RÈGLEMENTS :") . "\n");
            foreach ($payments as $payment) {
                $method = $this->enc($payment['payment_method']['name'] ?? $payment['payment_method_name'] ?? "Espèces");
                $printer->textRaw(sprintf("- %-20s : %s FCFA\n", $method, $payment['amount'] ?? 0));
            }
        } else {
            $printer->textRaw($this->enc("REGLEMENT : En attente") . "\n");
        }

        $qrContent = $order['qr_content'] ?? null;
        if (empty($qrContent) && !empty($order['reference'])) {
            $qrData = [
                'REF'  => $order['reference'],
                'DATE' => date('d-m-Y_H:i'),
                'MNT'  => ($order['total'] ?? 0) . ' FCFA',
                'SYS'  => $store['store_name'] ?? "RESTO"
            ];
            $qrContent = json_encode($qrData);
        }

        if (!empty($qrContent)) {
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            try {
                $printer->qrCode($qrContent, Printer::QR_ECLEVEL_L, 6, Printer::QR_MODEL_2);
            } catch (Exception $e) {
                Log::error("Impossible d'imprimer le QR code : " . $e->getMessage());
            }
            $printer->feed(1);
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->textRaw($this->enc("\nMerci de votre visite !") . "\n");
        $printer->feed(2);
    }
}
