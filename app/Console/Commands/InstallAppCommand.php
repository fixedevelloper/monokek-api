<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InstallAppCommand extends Command
{
    // Le nom de la commande à taper dans le terminal
    protected $signature = 'app:install {--email=} {--password=} {--name=Admin}';

    protected $description = 'Installe et configure l\'application (DB, Admin, .env)';

public function handle()
{
    $this->info('🚀 Lancement de l\'installation...');

    // 1. Migrations
    $this->call('migrate:fresh', ['--force' => true]);

    // 2. Injection des données du formulaire dans la config temporaire
    config([
        'install.admin_email' => $this->option('email'),
        'install.admin_password' => $this->option('password'),
        'install.admin_name' => $this->option('name'),
    ]);

    // 3. Seeders (qui inclut AdminUserSeeder)
    $this->info('Initialisation des données (Seeders)...');
    $this->call('db:seed', ['--force' => true]);

    // 4. Clé d'application
    $this->call('key:generate', ['--force' => true]);

    $this->info('Installation terminée !');
    return 0;
}

    protected function updateEnv(array $data)
    {
        $path = base_path('.env');
        $content = File::get($path);

        foreach ($data as $key => $value) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        }

        File::put($path, $content);
    }
}