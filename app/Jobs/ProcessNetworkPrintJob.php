<?php

namespace App\Jobs;

use App\Http\Services\PrintManagerService;
use App\Models\PrintQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessNetworkPrintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobId;

    /**
     * Le nombre de fois que le job peut être retenté en cas de coupure micro-réseau.
     */
    public $tries = 3;

    /**
     * Le nombre de secondes à attendre avant de retenter le job.
     */
    public $backoff = 5;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;
    }

    public function handle(PrintManagerService $printManager)
    {
        // 1. Récupérer le job de la table et verrouiller son statut
        $printQueue = PrintQueue::with('printer')->find($this->jobId);

        if (!$printQueue || in_array($printQueue->status, ['completed', 'printing'])) {
            return;
        }

        $printQueue->update([
            'status' => 'printing',
            'attempts' => $printQueue->attempts + 1
        ]);

        $printer = $printQueue->printer;

        if (!$printer || $printer->connection !== 'network') {
            $printQueue->update([
                'status' => 'failed',
                'error_message' => 'Imprimante introuvable, inactive ou type de connexion non géré par le serveur LAN.'
            ]);
            return;
        }

        try {
            // Décodage sécurisé du contenu (gère si c'est déjà un array ou du JSON brut string)
            $content = is_string($printQueue->content) ? json_decode($printQueue->content, true) : $printQueue->content;
            $orderData = $content['order'] ?? null;

            if (!$orderData) {
                throw new \Exception("Données de la commande ou de la session manquantes dans le payload.");
            }

            $ip = $printer->ip;
            $port = $printer->port ?? 9100;

            // 2. Routage dynamique selon le type de job d'impression
            switch ($printQueue->job_type) {
                case 'session_summary':
                    // Rapport de fermeture de caisse / X-Report
                    $printManager->printRegisterSessionSummary($ip, $port, $orderData);
                    break;

                case 'receipt':
                case 'order':
                    // Ticket client standard / Facture de table
                    $printManager->printClientReceipt($ip, $port, $orderData);
                    break;

                case 'kitchen':
                case 'bar':
                    // Bon de préparation pour la cuisine ou le bar
                    // On passe le job_type comme $type à la méthode ("kitchen", "bar" etc.)
                    $printManager->printKitchenReceipt($ip, $port, $printQueue->job_type, $orderData);
                    break;

                default:
                    throw new \Exception("Type de job d'impression [{$printQueue->job_type}] non supporté.");
            }

            // 3. Marquer le succès en base de données
            $printQueue->update([
                'status' => 'completed',
                'printed_at' => now()
            ]);

            Log::info("[PrintQueue] Job #{$this->jobId} exécuté avec succès (Type: {$printQueue->job_type}).");

        } catch (\Exception $e) {
            Log::error("[PrintQueue] Échec du job #{$this->jobId} : " . $e->getMessage());

            $printQueue->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            // Remet le job dans la file d'attente (release) s'il reste des essais disponibles
            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff);
            }
        }
    }
}
