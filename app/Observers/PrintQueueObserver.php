<?php

namespace App\Observers;

use App\Jobs\ProcessNetworkPrintJob;
use App\Models\PrintQueue;

class PrintQueueObserver
{
    /**
     * Handle the PrintQueue "created" event.
     * @param PrintQueue $printQueue
     */
    public function created(PrintQueue $printQueue): void
    {
        // Si le ticket est inséré en attente, on le traite instantanément
        if ($printQueue->status === 'pending') {
            ProcessNetworkPrintJob::dispatch($printQueue->id);
        }
    }

    /**
     * Handle the PrintQueue "updated" event.
     * @param PrintQueue $printQueue
     */
    public function updated(PrintQueue $printQueue): void
    {
        //
    }

    /**
     * Handle the PrintQueue "deleted" event.
     */
    public function deleted(PrintQueue $printQueue): void
    {
        //
    }

    /**
     * Handle the PrintQueue "restored" event.
     */
    public function restored(PrintQueue $printQueue): void
    {
        //
    }

    /**
     * Handle the PrintQueue "force deleted" event.
     */
    public function forceDeleted(PrintQueue $printQueue): void
    {
        //
    }
}
