<?php

namespace App\Http\Services;

use App\Models\Setting;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\EscposImage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class PrintManagerService
{
    /**
     * Table de caractères de l'imprimante (CP850 est idéal pour l'Afrique francophone / Xprinter)
     */
    private const PRINTER_CHARSET = 'CP850';

    /**
     * Convertit une chaîne UTF-8 vers l'encodage de l'imprimante (gestion parfaite des accents)
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
     * Initialise le connecteur réseau LAN et l'instance de l'imprimante
     * @param string $ip
     * @param int $port
     * @return Printer
     * @throws Exception
     */
    private function getPrinter(string $ip, int $port): Printer
    {
        Log::debug("[Printer] Tentative de connexion réseau LAN...", ['ip' => $ip, 'port' => $port]);

        try {
            $connector = new NetworkPrintConnector($ip, $port, 3); // Timeout à 3 secondes
            $printer = new Printer($connector);
            $printer->initialize();

            // Commande brute ESC t 2 pour activer la table de caractères CP850 matérielle
            $printer->getPrintConnector()->write(chr(27) . chr(116) . chr(2));

            Log::debug("[Printer] Connexion établie et table CP850 initialisée.", ['ip' => $ip]);
            return $printer;
        } catch (Exception $e) {
            Log::error("[Printer] Échec d'initialisation de l'imprimante réseau.", [
                'ip' => $ip,
                'port' => $port,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Formatage sécurisé des dates reçues du Job (gère Carbon et string)
     */
    private function formatDate($date): string
    {
        if (empty($date)) {
            return 'N/A';
        }
        if ($date instanceof Carbon) {
            return $date->format('d/m/Y H:i');
        }
        try {
            return Carbon::parse($date)->format('d/m/Y H:i');
        } catch (Exception $e) {
            return (string)$date;
        }
    }

    /**
     * IMPRESSION TEMPS RÉEL : Récapitulatif / Clôture de Caisse (Rapport de Session X-Report)
     */
    public function printRegisterSessionSummary(string $ip, int $port, array $session): void
    {
        $sessionId = $session['id'] ?? 'N/A';
        Log::info("[PrintQueue] Début impression Rapport de Session.", ['session_id' => $sessionId, 'target_ip' => $ip]);

        $printer = $this->getPrinter($ip, $port);

        try {
            $store = Setting::getStoreInfos();
            $storeName = strtoupper($store['store_name'] ?? "MON RESTAURANT");

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2, 2);
            $printer->textRaw($this->enc($storeName) . "\n");

            $printer->setTextSize(1, 1);
            $printer->textRaw($this->enc("CLOSURE / RECAPITULATIF DE CAISSE") . "\n");
            $printer->textRaw("------------------------------------------\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->textRaw($this->enc("Session  : #" . $sessionId) . "\n");
            $printer->textRaw($this->enc("Caissier : " . ($session['cashier_name'] ?? 'N/A')) . "\n");
            $printer->textRaw($this->enc("Ouverture: " . $this->formatDate($session['opened_at'] ?? null)) . "\n");
            $printer->textRaw($this->enc("Fermeture: " . $this->formatDate($session['closed_at'] ?? now())) . "\n");
            $printer->textRaw("------------------------------------------\n");

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->textRaw($this->enc("--- FLUX DE CAISSE ---") . "\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            $openingBalance = $session['opening_balance'] ?? 0;
            $totalSales = $session['total_sales'] ?? 0;
            $expectedBalance = $session['expected_balance'] ?? 0;

            $printer->textRaw(sprintf("%-24s %12s FCFA\n", $this->enc("Fond de caisse init."), number_format($openingBalance, 0, '.', ' ')));
            $printer->textRaw(sprintf("%-24s %12s FCFA\n", $this->enc("+ Ventes de la session"), number_format($totalSales, 0, '.', ' ')));
            $printer->textRaw("..........................................\n");
            $printer->textRaw(sprintf("%-24s %12s FCFA\n", $this->enc("Theorique en caisse"), number_format($expectedBalance, 0, '.', ' ')));
            $printer->textRaw("------------------------------------------\n");

            $payments = $session['payment_methods_totals'] ?? [];
            if (!empty($payments)) {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->textRaw($this->enc("--- MODES DE REGLEMENTS ---") . "\n");
                $printer->setJustification(Printer::JUSTIFY_LEFT);

                foreach ($payments as $payment) {
                    $methodName = $this->enc($payment['name'] ?? 'Autre');
                    $methodTotal = $payment['total'] ?? 0;
                    $printer->textRaw(sprintf("- %-21s : %12s FCFA\n", $methodName, number_format($methodTotal, 0, '.', ' ')));
                }
                $printer->textRaw("------------------------------------------\n");
            }

            $items = $session['sold_items_summary'] ?? [];
            if (!empty($items)) {
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->textRaw($this->enc("--- ARTICLES VENDUS ---") . "\n");
                $printer->setJustification(Printer::JUSTIFY_LEFT);

                foreach ($items as $item) {
                    $qty = $item['qty'] ?? 0;
                    $name = $this->enc(mb_strimwidth($item['product_name'] ?? 'Article', 0, 21, ".."));
                    $totalPrice = $item['total'] ?? 0;

                    $line = sprintf("%-3dx %-22s %11s FCFA\n", $qty, $name, number_format($totalPrice, 0, '.', ' '));
                    $printer->textRaw($line);
                }
                $printer->textRaw("------------------------------------------\n");
            }

            try {
                Log::debug("[Printer] Envoi de l'impulsion d'ouverture du tiroir-caisse.");
                $printer->pulse(0, 120, 240);
            } catch (Exception $e) {
                Log::warning("[Printer] Impossible de déclencher le tiroir-caisse.", ['error' => $e->getMessage()]);
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->textRaw(now()->format('d/m/Y H:i:s') . "\n");
            $printer->textRaw($this->enc("Rapport genere avec succes.") . "\n");
            $printer->feed(3);
            $printer->cut();

            Log::info("[PrintQueue] Rapport de Session #{$sessionId} imprimé avec succès.", ['ip' => $ip]);

        } catch (Exception $e) {
            Log::error("[PrintQueue] Échec durant le traitement du flux d'impression de session.", [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $printer->close();
            Log::debug("[Printer] Connexion fermée.", ['ip' => $ip]);
        }
    }

    /**
     * IMPRESSION TEMPS RÉEL : Bon Cuisine / Bar (Envoi en préparation)
     * @param string $ip
     * @param int $port
     * @param string $type
     * @param array $order
     * @throws Exception
     */
    public function printKitchenReceipt(string $ip, int $port, string $type, array $order): void
    {
        $reference = $order['reference'] ?? 'N/A';
        Log::info("[PrintQueue] Début impression Bon Préparation [{$type}].", ['reference' => $reference, 'target_ip' => $ip]);

        $printer = $this->getPrinter($ip, $port);

        try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2, 2);
            $printer->textRaw($this->enc("BON " . strtoupper($type)) . "\n\n");

            $printer->setTextSize(1, 1);
            $printer->textRaw($this->enc("TABLE : " . ($order['table']['name'] ?? 'N/A')) . "\n");
            $printer->textRaw($this->enc("Serveur : " . ($order['cashier']['name'] ?? $order['user']['name'] ?? 'N/A')) . "\n");
            $printer->textRaw(now()->format('d/m/Y H:i:s') . "\n");
            $printer->textRaw("==========================================\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $items = $order['items'] ?? [];

            Log::debug("[PrintQueue] Traitement des articles cuisine.", ['count' => count($items)]);

            foreach ($items as $item) {
                $qty = $item['qty'] ?? 1;
                $productName = $item['product']['name'] ?? 'Inconnu';
                $label = $qty . "x " . $productName;
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
            $printer->textRaw($this->enc("Ref : " . $reference) . "\n");

            $printer->feed(3);
            $printer->cut();

            Log::info("[PrintQueue] Bon Préparation [{$type}] imprimé avec succès pour Ref: {$reference}.", ['ip' => $ip]);

        } catch (Exception $e) {
            Log::error("[PrintQueue] Échec durant l'impression cuisine.", [
                'reference' => $reference,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $printer->close();
            Log::debug("[Printer] Connexion fermée.", ['ip' => $ip]);
        }
    }

    /**
     * IMPRESSION TEMPS RÉEL : Ticket Client Standard / Facture de table
     */
    public function printClientReceipt(string $ip, int $port, array $order): void
    {
        $reference = $order['reference'] ?? 'N/A';
        Log::info("[PrintQueue] Début impression Ticket Client.", ['reference' => $reference, 'target_ip' => $ip]);

        $printer = $this->getPrinter($ip, $port);

        try {
            $store = Setting::getStoreInfos();

            // Impression du Logo
            try {
                $logoPath = public_path('images/logo.png');
                if (file_exists($logoPath)) {
                    Log::debug("[Printer] Fichier logo trouvé. Chargement...", ['path' => $logoPath]);
                    $logo = EscposImage::load($logoPath);
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $printer->bitImage($logo, Printer::IMG_DOUBLE_WIDTH);
                    $printer->feed(1);
                } else {
                    Log::debug("[Printer] Aucun logo trouvé au chemin spécifié.", ['path' => $logoPath]);
                }
            } catch (Exception $e) {
                Log::error("[Printer] Erreur lors du chargement/rendu du logo graphique.", ['error' => $e->getMessage()]);
            }

            $storeName = strtoupper($store['store_name'] ?? "RESTO");
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setTextSize(2, 2);
            $printer->textRaw($this->enc($storeName) . "\n");

            $printer->setTextSize(1, 1);
            if (!empty($store['store_address'])) $printer->textRaw($this->enc($store['store_address']) . "\n");
            if (!empty($store['store_phone']))   $printer->textRaw($this->enc("Tel : " . $store['store_phone']) . "\n");
            $printer->textRaw(now()->format('d/m/Y H:i:s') . "\n");
            $printer->textRaw("------------------------------------------\n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->textRaw($this->enc("Table    : " . ($order['table']['name'] ?? 'N/A')) . "\n");
            $printer->textRaw($this->enc("Facture  : " . $reference) . "\n");
            $printer->textRaw($this->enc("Serveur  : " . ($order['user']['name'] ?? $order['waiter']['name'] ?? 'N/A')) . "\n");
            $printer->textRaw("------------------------------------------\n");

            $rounds = $order['rounds'] ?? [];
            Log::debug("[PrintQueue] Traitement des rounds de service clients.", ['rounds_count' => count($rounds)]);

            foreach ($rounds as $index => $round) {
                $roundItems = $round['items'] ?? [];
                if (empty($roundItems)) continue;

                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->textRaw($this->enc("--- SERVICE #" . ($index + 1) . " ---") . "\n");
                $printer->setJustification(Printer::JUSTIFY_LEFT);

                foreach ($roundItems as $i) {
                    $qty = $i['qty'] ?? 1;
                    $name = $this->enc(mb_strimwidth($i['product']['name'] ?? 'Article', 0, 21, ".."));
                    $totalPrice = $i['total'] ?? 0;

                    $line = sprintf("%-2dx %-22s %9s FCFA\n", $qty, $name, number_format($totalPrice, 0, '.', ' '));
                    $printer->textRaw($line);
                }
            }

            $printer->textRaw("==========================================\n");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->setTextSize(2, 1);
            $totalAmount = $order['total'] ?? 0;
            $printer->textRaw($this->enc("TOTAL : " . number_format($totalAmount, 0, '.', ' ') . " FCFA") . "\n");
            $printer->setTextSize(1, 1);
            $printer->textRaw("------------------------------------------\n");

            $payments = $order['payments'] ?? [];
            if (!empty($payments)) {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->textRaw($this->enc("REGLEMENTS :") . "\n");
                foreach ($payments as $payment) {
                    $method = $this->enc($payment['payment_method']['name'] ?? $payment['payment_method_name'] ?? "Especes");
                    $amount = $payment['amount'] ?? 0;
                    $printer->textRaw(sprintf("- %-21s : %12s FCFA\n", $method, number_format($amount, 0, '.', ' ')));
                }
            } else {
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->textRaw($this->enc("REGLEMENT : En attente") . "\n");
            }

            $qrContent = $order['qr_content'] ?? null;
            if (empty($qrContent) && !empty($order['reference'])) {
                $qrData = [
                    'REF'  => $order['reference'],
                    'DATE' => now()->format('d-m-Y_H:i'),
                    'MNT'  => number_format($totalAmount, 0, '.', ' ') . ' FCFA',
                    'SYS'  => $store['store_name'] ?? "RESTO"
                ];
                $qrContent = json_encode($qrData);
            }

            if (!empty($qrContent)) {
                $printer->feed(1);
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                try {
                    Log::debug("[Printer] Génération du QR Code natif.");
                    $printer->qrCode($qrContent, Printer::QR_ECLEVEL_L, 6, Printer::QR_MODEL_2);
                } catch (Exception $e) {
                    Log::error("[Printer] Erreur lors de la génération du QR Code.", ['error' => $e->getMessage()]);
                }
                $printer->feed(1);
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->textRaw($this->enc("\nMerci de votre visite !") . "\n");
            $printer->feed(3);
            $printer->cut();

            Log::info("[PrintQueue] Ticket Client imprimé avec succès pour Ref: {$reference}.", ['ip' => $ip]);

        } catch (Exception $e) {
            Log::error("[PrintQueue] Échec durant l'impression du ticket client.", [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            throw $e;
        } finally {
            $printer->close();
            Log::debug("[Printer] Connexion fermée.", ['ip' => $ip]);
        }
    }
}
