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
    /**
     * Table de caractères imprimante utilisée (doit correspondre à la commande ESC t envoyée).
     * Table 2 = CP850 chez la plupart des Xprinter (dont la XP-80TS), bon choix pour le français.
     * Si les accents restent faux, teste 'CP1252' avec chr(19) à la place de chr(2).
     */
    private const PRINTER_CHARSET = 'CP850';

    /**
     * Convertit une chaîne UTF-8 (venant de Laravel/JSON) vers l'encodage mono-octet
     * attendu par l'imprimante thermique.
     *
     * IMPORTANT : ce texte converti doit être envoyé avec $printer->textRaw(),
     * jamais avec $printer->text(). textRaw() envoie les octets tels quels, sans
     * qu'escpos-php ne tente sa propre conversion automatique UTF-8 -> code page
     * (basée sur le CapabilityProfile par défaut, qui pointe vers CP437 et n'a
     * aucune idée qu'on a basculé l'imprimante en table 2 via write() en brut).
     * C'est ce conflit entre les deux conversions qui causait les caractères
     * illisibles, indépendamment du bon choix de code page.
     */
    private function enc(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $converted = @iconv('UTF-8', self::PRINTER_CHARSET . '//TRANSLIT//IGNORE', $text);
        return $converted !== false ? $converted : $text;
    }

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

        $printer = null;

        try {
            $connector = new NetworkPrintConnector($ip, $port, 3);

            $printer = new Printer($connector);
            $printer->initialize();

            // ESC t 2 = sélectionne la table CP850 sur l'imprimante.
            // Doit correspondre à self::PRINTER_CHARSET ci-dessus.
            $printer->getPrintConnector()->write(chr(27) . chr(116) . chr(2));

            // 2. Lancement de l'impression
            if ($jobType === 'kitchen' || $jobType === 'bar') {
                $this->printKitchenReceipt($printer, $jobType, $order);
            } else {
                $this->printClientReceipt($printer, $order, $store);
            }

            // 3. Impulsion du tiroir de caisse
            if ($jobType !== 'kitchen' && $jobType !== 'bar') {
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

        // ── CHARGEMENT ET IMPRESSION DU LOGO DEPUIS LE DOSSIER PUBLIC ──
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

        // Nom Enseigne
        $storeName = strtoupper($store['store_name'] ?? "RESTO");
        $printer->setTextSize(2, 2);
        $printer->textRaw($this->enc($storeName) . "\n");

        $printer->setTextSize(1, 1);
        if (!empty($store['store_address'])) $printer->textRaw($this->enc($store['store_address']) . "\n");
        if (!empty($store['store_phone']))   $printer->textRaw($this->enc("Tel : " . $store['store_phone']) . "\n");
        $printer->textRaw(date('d/m/Y H:i:s') . "\n");
        $printer->textRaw("------------------------------------------\n");

        // Infos Commande
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->textRaw($this->enc("Table    : " . ($order['table']['name'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Facture  : " . ($order['reference'] ?? 'N/A')) . "\n");
        $printer->textRaw($this->enc("Serveur  : " . ($order['user']['name'] ?? $order['waiter']['name'] ?? 'N/A')) . "\n");
        $printer->textRaw("------------------------------------------\n");

        // Liste des articles par Services (Rounds)
        $rounds = $order['rounds'] ?? [];
        foreach ($rounds as $index => $round) {
            $roundItems = $round['items'] ?? [];
            if (empty($roundItems)) continue;

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->textRaw($this->enc("--- SERVICE #" . ($index + 1) . " ---") . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            foreach ($roundItems as $i) {
                $qty = $i['qty'] ?? 1;
                // Tronquer puis convertir AVANT le sprintf pour garder un alignement
                // correct des colonnes (CP850 = 1 octet par caractère, contrairement à l'UTF-8).
                $name = $this->enc(mb_strimwidth($i['product']['name'] ?? 'Article', 0, 22, ".."));
                $totalPrice = $i['total'] ?? 0;

                $line = sprintf("%-2dx %-24s %6s FCFA\n", $qty, $name, $totalPrice);
                $printer->textRaw($line);
            }
        }

        // Totaux
        $printer->textRaw("==========================================\n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(2, 1);
        $printer->textRaw($this->enc("TOTAL : " . ($order['total'] ?? 0) . " FCFA") . "\n");
        $printer->setTextSize(1, 1);
        $printer->textRaw("------------------------------------------\n");

        // Règlements
        $printer->setJustification(Printer::JUSTIFY_LEFT);
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

        // ── QR CODE ──
        $qrContent = $order['qr_content'] ?? null;

        if (empty($qrContent) && !empty($order['reference'])) {
            // 💡 FORMAT PRO : Un condensé des données de la facture (Idéal pour contrôle rapide)
            $qrData = [
                'REF'  => $order['reference'],
                'DATE' => date('d-m-Y_H:i'),
                'MNT'  => ($order['total'] ?? 0) . ' FCFA',
                'SYS'  => $store['store_name'] ?? "RESTO"
            ];

            // Convertit en JSON compact pour le QR Code
            $qrContent = json_encode($qrData);
        }

        if (!empty($qrContent)) {
            $printer->feed(1);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            try {
                $printer->qrCode($qrContent, Printer::QR_ECLEVEL_L, 6, Printer::QR_MODEL_2);
            } catch (Exception $e) {
                // Si l'imprimante ne supporte pas le QR natif, on log et on continue
                // sans bloquer le reste du ticket.
                Log::error("Impossible d'imprimer le QR code : " . $e->getMessage());
            }
            $printer->feed(1);
           // $printer->textRaw($this->enc("Scannez pour suivre votre facture") . "\n");
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->textRaw($this->enc("\nMerci de votre visite !") . "\n");
        $printer->feed(2);
    }
}
