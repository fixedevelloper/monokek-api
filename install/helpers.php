<?php
session_start();

function rootPath() { 
    return realpath(__DIR__.'/..'); 
}

// Exécute une commande artisan et retourne le résultat
function runArtisan($command) {
    $artisan = rootPath() . '/artisan';
    // On force le chemin vers PHP pour éviter les conflits
    $cmd = "php $artisan $command 2>&1"; 
    return shell_exec($cmd);
}

function updateEnv(array $data) {
    $path = rootPath() . '/.env';
    $content = file_get_contents($path);
    foreach ($data as $key => $value) {
        // Gère les valeurs avec des espaces
        $val = (strpos($value, ' ') !== false) ? '"' . $value . '"' : $value;
        $content = preg_replace("/^{$key}=.*/m", "{$key}={$val}", $content);
    }
    return file_put_contents($path, $content);
}

function sanitize($data) {
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}
/**
 * Vérifie les prérequis PHP nécessaires à Laravel
 */
function checkRequirements() {
    return [
        'PHP >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
        'BCMath'    => extension_loaded('bcmath'),
        'Ctype'     => extension_loaded('ctype'),
        'JSON'      => extension_loaded('json'),
        'Mbstring'  => extension_loaded('mbstring'),
        'OpenSSL'   => extension_loaded('openssl'),
        'PDO'       => extension_loaded('pdo'),
        'Tokenizer' => extension_loaded('tokenizer'),
        'XML'       => extension_loaded('xml'),
    ];
}

/**
 * Vérifie si les dossiers sont scriptibles (nécessaire pour Laravel)
 */
function checkPermissions() {
    $paths = [
        'Root'    => rootPath(),
        'Storage' => rootPath() . '/storage',
        'Cache'   => rootPath() . '/bootstrap/cache',
    ];

    $results = [];
    foreach ($paths as $name => $path) {
        $results[$name] = is_dir($path) && is_writable($path);
    }
    return $results;
}
function nextStep($name) {
    header('Location: ?step=' . $name);
    exit;
}
