<?php

/**
 * Redirige vers une étape spécifique
 */
function nextStep($name) {
    header('Location: ?step=' . $name);
    exit;
}

/**
 * Retourne le chemin racine du projet (où se trouve le .env)
 */
function rootPath() {
    return realpath(__DIR__ . '/..');
}

/**
 * Retourne le chemin complet du fichier .env
 */
function envPath() {
    return rootPath() . '/.env';
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

/**
 * Met à jour ou crée les variables dans le fichier .env
 */
function updateEnv(array $data) {
    $path = envPath();
    
    // Si le fichier n'existe pas, on tente de copier .env.example
    if (!file_exists($path) && file_exists(rootPath() . '/.env.example')) {
        copy(rootPath() . '/.env.example', $path);
    }

    $content = file_exists($path) ? file_get_contents($path) : '';

    foreach ($data as $key => $value) {
        // Gère les valeurs avec des espaces
        if (preg_match('/\s/', $value)) {
            $value = '"' . $value . '"';
        }

        if (preg_match("/^{$key}=/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            $content .= "\n{$key}={$value}";
        }
    }

    return file_put_contents($path, trim($content) . "\n");
}

/**
 * Teste la connexion à la base de données via PDO
 */
function testDatabaseConnection($host, $db, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];
        new PDO($dsn, $user, $pass, $options);
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}
/**
 * Nettoie les données pour éviter les failles XSS lors de l'affichage
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}