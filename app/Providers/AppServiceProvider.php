<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\PrintQueue;
use App\Observers\PrintQueueObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Enregistrement de l'observer pour l'impression en temps réel
        PrintQueue::observe(PrintQueueObserver::class);
    }
}
