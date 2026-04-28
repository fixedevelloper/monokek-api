<?php
// 1. Générer une APP_KEY si elle n'existe pas encore (essentiel pour Laravel)
$currentConfig = file_get_contents(envPath());
if (strpos($currentConfig, 'base64:') === false) {
    $key = 'base64:' . base64_encode(random_bytes(32));
    updateEnv(['APP_KEY' => $key]);
}

// 2. Créer le fichier de verrouillage pour désactiver l'installateur
$lockFile = __DIR__ . '/../installed.lock';
if (!file_exists($lockFile)) {
    file_put_contents($lockFile, 'Installation terminée le : ' . date('Y-m-d H:i:s'));
}

// 3. Nettoyage de la session (optionnel)
session_destroy();
?>

<div class="card text-center">
    <div class="success-icon">
        <span style="font-size: 48px;">🎉</span>
    </div>
    
    <h2>Installation terminée !</h2>
    <p>L'application Laravel a été configurée avec succès.</p>
    
    <div class="alert success">
        <strong>Sécurité :</strong> Le fichier <code>installed.lock</code> a été créé. L'installateur est désormais désactivé.
    </div>

    <hr>

    <div class="info-box">
        <p>Vous pouvez maintenant vous connecter à votre interface d'administration avec l'email configuré à l'étape précédente.</p>
    </div>

    <div class="actions">
        <a href="../" class="btn primary">Accéder au site</a>
    </div>
</div>

<style>
    .text-center { text-align: center; }
    .success-icon { margin-bottom: 20px; }
    .info-box { 
        background: #f9fafb; 
        padding: 15px; 
        border-radius: 8px; 
        margin: 20px 0; 
        color: #4b5563;
        font-size: 0.9em;
    }
</style>