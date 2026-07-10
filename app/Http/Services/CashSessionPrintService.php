<?php

namespace App\Http\Services;

use App\Models\CashSession;
use Illuminate\Support\Collection;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Illuminate\Support\Facades\Log;
use Exception;

class CashSessionPrintService
{
    /**
     * Table de caractères imprimante (ISO-8859-1 se mappe parfaitement sur la table 2 / CP850).
     */
    private const PRINTER_CHARSET = 'ISO-8859-1';

    /**
     * Imprime le rapport de fermeture de caisse sur une imprimante réseau.
     */
    public function printClosingReport(CashSession $session, Collection $paymentsDetail): void
    {
        $printerInstance = null;

        try {
            // 💡 FIX : On utilise une variable distincte pour le modèle Eloquent
            $printerModel = \App\Models\Printer::where('location', 'receipt')->first();

            if (!$printerModel) {
                throw new Exception("Imprimante de reçu non configurée dans la base de données.");
            }

            $connector = new NetworkPrintConnector($printerModel->ip, $printerModel->port, 3);
            $printerInstance = new Printer($connector);
            $printerInstance->initialize();

            // Bascule l'imprimante sur la table PC850 (accents français).
            $printerInstance->getPrintConnector()->write(chr(27) . chr(116) . chr(2));

            // Récupération sécurisée du nom du shop
            $storeName = config('app.name', 'RESTO');
            $this->renderReport($printerInstance, $session, $paymentsDetail, $storeName);

            $printerInstance->cut();
        }catch (Exception $exception){
            logger($exception->getMessage());
        } finally {
            // 💡 FIX : On ferme uniquement si c'est bien l'instance du driver ESC/POS
            if ($printerInstance instanceof Printer) {
                $printerInstance->close();
            }
        }
    }

    /**
     * Construit le contenu du ticket.
     */
    public function renderReport(Printer $printer, CashSession $session, Collection $paymentsDetail, $store = 'Store'): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);

        $storeName = strtoupper($store ?? "RESTO");
        $printer->setTextSize(2, 2);
        $printer->textRaw($this->enc($storeName) . "\n");

        $printer->setTextSize(1, 1);
        $printer->textRaw($this->enc("RAPPORT DE CAISSE (X-REPORT)") . "\n");
        $printer->textRaw("==========================================\n");

        // Infos session
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $cashierName = $session->user->name ?? 'N/A';
        $printer->textRaw($this->enc("Caissier   : " . $cashierName) . "\n");
        $printer->textRaw($this->enc("Ouverture  : " . $this->formatDate($session->opened_at)) . "\n");
        $printer->textRaw($this->enc("Fermeture  : " . $this->formatDate($session->closed_at)) . "\n");

        if ($session->opened_at && $session->closed_at) {
            $duration = date_diff(date_create($session->opened_at), date_create($session->closed_at));
            $printer->textRaw($this->enc("Duree      : " . $duration->format('%hh%I')) . "\n");
        }

        $printer->textRaw("------------------------------------------\n");

        // Ventilation par mode de paiement
        $printer->textRaw($this->enc("VENTILATION DES ENCAISSEMENTS") . "\n");
        $printer->textRaw("------------------------------------------\n");

        foreach ($paymentsDetail as $p) {
            $name = $this->enc(mb_strimwidth($p->name ?? 'Inconnu', 0, 26, ".."));
            $amount = $p->total ?? 0;
            // 💡 Utilisation d'un espace normal dans le formatage pour éviter les soucis d'affichage des espaces insécables
            $printer->textRaw(sprintf("%-26s %10s XAF\n", $name, number_format($amount, 0, ',', ' ')));
        }

        $totalPayments = $paymentsDetail->sum('total');

        $printer->textRaw("------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(2, 1);
        $printer->textRaw($this->enc("TOTAL VENTES : " . number_format($totalPayments, 0, ',', ' ') . " XAF") . "\n");
        $printer->setTextSize(1, 1);

        // Contrôle de caisse
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->textRaw("==========================================\n");
        $printer->textRaw($this->enc("CONTROLE DE CAISSE") . "\n");
        $printer->textRaw("------------------------------------------\n");

        $printer->textRaw(sprintf("%-24s %10s XAF\n", "Fond de caisse", number_format($session->opening_amount, 0, ',', ' ')));
        $printer->textRaw(sprintf("%-24s %10s XAF\n", "Total attendu", number_format($session->expected_amount, 0, ',', ' ')));
        $printer->textRaw(sprintf("%-24s %10s XAF\n", "Montant compte", number_format($session->closing_amount, 0, ',', ' ')));

        $difference = $session->closing_amount - $session->expected_amount;
        $diffLabel = $difference == 0 ? "Ecart" : ($difference > 0 ? "Excedent" : "Manquant");

        $printer->textRaw("------------------------------------------\n");
        $printer->setJustification(Printer::JUSTIFY_RIGHT);
        $printer->setTextSize(2, 1);
        $printer->textRaw($this->enc($diffLabel . " : " . number_format(abs($difference), 0, ',', ' ') . " XAF") . "\n");
        $printer->setTextSize(1, 1);
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        if (!empty($session->note)) {
            $printer->textRaw("------------------------------------------\n");
            $printer->textRaw($this->enc("Note : " . $session->note) . "\n");
        }

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->textRaw("==========================================\n");
        $printer->textRaw($this->enc(date('d/m/Y H:i:s')) . "\n");
        $printer->feed(2);

        $printer->textRaw($this->enc("Signature caissier : ____________________") . "\n");
        $printer->feed(3);
    }

    private function formatDate(?string $date): string
    {
        return $date ? date('d/m/Y H:i', strtotime($date)) : 'N/A';
    }

    /**
     * 💡 FIX : Utilisation de mb_convert_encoding pour une meilleure compatibilité d'environnement.
     */
    private function enc(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        return mb_convert_encoding($text, self::PRINTER_CHARSET, 'UTF-8');
    }
}
