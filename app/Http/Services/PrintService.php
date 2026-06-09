<?php


namespace App\Http\Services;


use App\Models\Printer;
use App\Models\PrintQueue;
use App\Events\PrintJobCreated;
use App\Models\Setting;

class PrintService
{
    /**
     * Gère l'impression d'un Round spécifique
     * Distribue les articles aux imprimantes concernées (Cuisine, Bar, etc.)
     */
    public function queueRoundTickets($round)
    {
        // 1. Charger les données nécessaires avec les relations
        $round->load([
            'order.table',
            'order.user',
            'items.product.category'
        ]);

        // 2. Grouper les articles par leur destination d'impression
        // On assume que chaque produit ou sa catégorie pointe vers une 'location' (ex: kitchen, bar)
        $itemsByLocation = $round->items->groupBy(function($item) {
            return $item->product->printing_location ?? 'kitchen';
        });

        $jobs = [];

        foreach ($itemsByLocation as $location => $items) {
            // 3. Trouver l'imprimante dédiée à cette zone
            $printer = Printer::where('branch_id', $round->order->branch_id)
                ->where('location', $location)
                ->where('is_active', true)
                ->first();

            // Fallback sur l'imprimante de reçu si la destination n'a pas d'imprimante
            if (!$printer) {
                $printer = Printer::where('branch_id', $round->order->branch_id)
                    ->where('location', 'receipt')
                    ->first();
            }

            if (!$printer) continue;

            // 4. Créer le job d'impression pour ce groupe d'articles
            $job = PrintQueue::create([
                'printer_id' => $printer->id,
                'job_type'   => $location,
                'content'    => [
                    'round_info' => [
                        'number' => $round->round_number,
                        'note'   => $round->note,
                    ],
                    'order' => [
                        'reference' => $round->order->reference,
                        'table'     => $round->order->table->name ?? 'N/A',
                        'waiter'    => $round->order->user->name ?? 'Système',
                        'items'     => $items->load('modifiers.modifierItem'), // Détails importants
                    ],
                    'timestamp' => now()->toDateTimeString(),
                ],
                'status' => 'pending',
            ]);

            broadcast(new PrintJobCreated($job))->toOthers();
            $jobs[] = $job;
        }

        return $jobs;
    }

    /**
     * Garder la méthode simple pour l'impression de la FACTURE finale (Receipt)
     * @param $order
     * @return |null
     */
    public function queueFinalReceipt($order)
    {
        $printer = Printer::where('branch_id', $order->branch_id)
            ->where('location', 'receipt')
            ->first();

        if (!$printer) return null;

        $job = PrintQueue::create([
            'printer_id' => $printer->id,
            'job_type'   => 'receipt',
            'content'    => [
                // Nettoyage et optimisation des chargements de relations
                'order' => $order->load([
                    'rounds.items.product',
                    'rounds.items.modifiers',
                    'payments.paymentMethod', // Charge les paiements ET la méthode associée d'un coup
                    'customer',
                    'table',
                    'user',                   // Le serveur (créateur de la commande)
                    'cashier'                 // Le caissier
                ]),
                'store'     => Setting::getStoreInfos(),
                'is_final'  => true,
                'timestamp' => now()->toDateTimeString(),
            ],
            'status' => 'pending',
        ]);

        broadcast(new PrintJobCreated($job))->toOthers();
        return $job;
    }
}
