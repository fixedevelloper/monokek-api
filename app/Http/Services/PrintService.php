<?php


namespace App\Http\Services;

use App\Models\PrintQueue;
use App\Models\Printer;
use App\Events\PrintJobCreated;

class PrintService
{
    /**
     * Envoie un ticket dans la file d'attente selon sa destination
     *
     * @param $order    L'objet commande
     * @param $location La destination (receipt, kitchen, bar, pizza, etc.)
     * @param $branchId L'ID de la branche
     */
    public function queueTicket($order, $location, $branchId)
    {
        // 1. Trouver l'imprimante configurée pour cette destination précise
        // On cherche une imprimante active qui correspond à la "location"
        $printer = Printer::where('branch_id', $branchId)
            ->where('location', $location) // "kitchen", "bar", etc.
            ->where('is_active', true)
            ->first();

        // Fallback : Si aucune imprimante spécifique n'est trouvée pour la cuisine,
        // on peut chercher l'imprimante par défaut (receipt)
        if (!$printer) {
            $printer = Printer::where('branch_id', $branchId)
                ->where('location', 'receipt')
                ->first();
        }

        if (!$printer) return null;

        // 2. Créer le job dans la queue
        $job = PrintQueue::create([
            'printer_id' => $printer->id,
            'job_type'   => $location, // On garde la destination comme type de job
            'content'    => [
                'order' => $order->load([
                    'items.product',
                    'items.modifiers.modifierItem',
                    'customer',
                    'cashier',
                    'table' // Ne pas oublier la table pour la cuisine !
                ]),
                'timestamp' => now()->toDateTimeString(),
            ],
            'status' => 'pending',
        ]);

        // 3. Déclencher l'événement via WebSockets (Pusher/Soketi)
        // Le frontend Tauri écoute et lancera l'impression dès réception
        broadcast(new PrintJobCreated($job))->toOthers();

        return $job;
    }
}
