<?php

namespace App\Console\Commands;

use App\Models\RestaurantTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseTables extends Command
{
    /**
     * Statuts de commande considérés comme "actifs" :
     * tant qu'une commande dinein dans un de ces statuts existe
     * pour une table, la table reste occupée.
     *
     * Ajuste cette liste selon ton workflow réel
     * (ex: retire 'paid' si le paiement libère déjà la table).
     */
    private const ACTIVE_ORDER_STATUSES = [
/*        'draft',
        'pending_payment',
        'pending',
        'billing',
        'reserved',
        'ready',
        'preparing',*/
        'completed',
         'paid', // décommente si "payé" ne doit PAS libérer la table tout de suite
    ];

    protected $signature = 'tables:release
                            {--dry-run : Simule sans modifier la base}
                            {--table= : Ne traiter qu\'une table spécifique (ID)}';

    protected $description = 'Libère les tables dinein dont toutes les commandes sont terminées (paid/completed/cancelled)';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tableId  = $this->option('table');

        $query = RestaurantTable::query()
            ->where('status', '!=', 'free')
            ->with(['orders' => function ($q) {
                $q->where('type', 'dinein')
                    ->whereIn('status', self::ACTIVE_ORDER_STATUSES);
            }]);

        if ($tableId) {
            $query->where('id', $tableId);
        }

        $tables = $query->get();

        if ($tables->isEmpty()) {
            $this->info('Aucune table à traiter.');
            return self::SUCCESS;
        }

        $released = 0;
        $skipped  = 0;

        foreach ($tables as $table) {
            // Si la table a encore des commandes dinein actives, on la laisse occupée.
            if ($table->orders->isNotEmpty()) {
                $skipped++;
                $this->line("Table #{$table->id} ({$table->name}) — toujours occupée ({$table->orders->count()} commande(s) active(s)).");
                continue;
            }

            if ($isDryRun) {
                $this->info("[DRY-RUN] Table #{$table->id} ({$table->name}) serait libérée.");
                $released++;
                continue;
            }

            DB::transaction(function () use ($table) {
                $table->update(['status' => 'free']);
            });

            Log::info('Table libérée automatiquement', [
                'table_id'   => $table->id,
                'table_name' => $table->name,
            ]);

            $this->info("✅ Table #{$table->id} ({$table->name}) libérée.");
            $released++;
        }

        $this->newLine();
        $this->info("Terminé : {$released} table(s) libérée(s), {$skipped} laissée(s) occupée(s).");

        return self::SUCCESS;
    }
}
