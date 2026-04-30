<?php
// On suppose que les helpers (runArtisan, updateEnv, etc.) sont chargés
$cronStatus = null;
$artisanPath = realpath(__DIR__ . '/../../artisan');
$phpPath = PHP_BINARY;

// 1. TENTATIVE D'AUTOMATISATION DU CRON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_services'])) {
    
    // Commande exacte à insérer
    $cronJob = "* * * * * $phpPath $artisanPath schedule:run >> /dev/null 2>&1";
    
    // Tentative de lecture du crontab actuel
    $currentCron = shell_exec('crontab -l 2>/dev/null');
    
    if (strpos($currentCron, $artisanPath) === false) {
        $newCron = $currentCron . PHP_EOL . $cronJob . PHP_EOL;
        $tempFile = tempnam(sys_get_temp_dir(), 'cron');
        file_put_contents($tempFile, $newCron);
        
        exec("crontab $tempFile", $output, $returnVar);
        unlink($tempFile);
        
        $cronStatus = ($returnVar === 0) ? 'success' : 'manual';
    } else {
        $cronStatus = 'already_exists';
    }

    // 2. CONFIGURATION AUTOMATIQUE WEBSOCKET (REVERB)
    // On génère des clés uniques pour le client
    updateEnv([
        'BROADCAST_CONNECTION' => 'reverb',
        'REVERB_APP_ID' => rand(100000, 999999),
        'REVERB_APP_KEY' => bin2hex(random_bytes(10)),
        'REVERB_APP_SECRET' => bin2hex(random_bytes(10)),
        'REVERB_HOST' => '0.0.0.0',
        'REVERB_PORT' => 8080,
        'REVERB_SCHEME' => 'http'
    ]);

    // Si le cron est géré ou déjà existant, on peut finaliser
    if ($cronStatus !== 'manual') {
        header('Location: ?step=finish');
        exit;
    }
}
?>

<style>
    :root {
        --primary: #4f46e5;
        --primary-hover: #4338ca;
        --bg: #f8fafc;
        --card-bg: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --success: #10b981;
        --warning-bg: #fffbeb;
        --warning-text: #92400e;
        --warning-border: #fde68a;
        --code-bg: #1e293b;
    }

    body {
        font-family: 'Inter', system-ui, sans-serif;
        background-color: var(--bg);
        color: var(--text-main);
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        margin: 0;
    }

    /* Conteneur principal */
    .card {
        background: var(--card-bg);
        padding: 2.5rem;
        border-radius: 1.25rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 550px;
        border: 1px solid var(--border);
    }

    h2 {
        margin: 0 0 0.5rem 0;
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
    }

    p {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.95rem;
        margin-bottom: 2rem;
    }

    /* Barre de progression */
    .progress-container {
        width: 100%;
        height: 8px;
        background-color: #f1f5f9;
        border-radius: 999px;
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), #818cf8);
        border-radius: 999px;
        transition: width 0.5s ease-in-out;
    }

    /* Message d'alerte/warning */
    .status-msg.warning {
        background-color: var(--warning-bg);
        color: var(--warning-text);
        border: 1px solid var(--warning-border);
        padding: 1rem;
        border-radius: 0.75rem;
        margin-bottom: 1.5rem;
        font-size: 0.9rem;
        line-height: 1.5;
    }

    /* Cartes de services */
    .service-card {
        border: 1px solid var(--border);
        border-radius: 0.75rem;
        padding: 1.25rem;
        margin-bottom: 1rem;
        background: #fff;
        transition: border-color 0.2s;
    }

    .service-card:hover {
        border-color: #cbd5e1;
    }

    .service-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 0.5rem;
    }

    .service-icon {
        width: 32px;
        height: 32px;
        background: #f1f5f9;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    /* Bloc de code (Style Terminal) */
    .config-code {
        background: var(--code-bg);
        color: #e2e8f0;
        padding: 1rem;
        border-radius: 0.5rem;
        font-family: 'Fira Code', 'Courier New', monospace;
        font-size: 0.8rem;
        margin-top: 12px;
        word-break: break-all;
        border: 1px solid #334155;
        line-height: 1.4;
    }

    /* Badges */
    .badge.success {
        display: inline-block;
        background-color: #ecfdf5;
        color: var(--success);
        border: 1px solid #d1fae5;
        padding: 0.25rem 0.75rem;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 700;
    }

    /* Boutons */
    .actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
    }

    .btn {
        flex: 1;
        padding: 0.85rem;
        border-radius: 0.6rem;
        font-size: 0.95rem;
        font-weight: 600;
        text-decoration: none;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
    }

    .btn.primary {
        background-color: var(--primary);
        color: white;
    }

    .btn.primary:hover {
        background-color: var(--primary-hover);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
    }

    .btn.secondary {
        background-color: white;
        color: var(--text-main);
        border: 1px solid var(--border);
    }

    .btn.secondary:hover {
        background-color: #f1f5f9;
    }

    small {
        display: block;
        margin-top: 8px;
        font-size: 0.75rem;
        line-height: 1.4;
    }
</style>

<div class="card">
    <div class='progress-container'>
        <div class='progress-bar' style='width:95%'></div>
    </div>

    <h2>Services & Automatisation</h2>
    <p>Configuration du moteur de tâches et du temps réel.</p>

    <?php if ($cronStatus === 'manual'): ?>
        <div class="status-msg warning">
            <strong>⚠️ Configuration manuelle requise</strong><br>
            Votre serveur ne permet pas l'auto-configuration du Crontab. Veuillez suivre l'étape 1 ci-dessous.
        </div>
    <?php endif; ?>

    <!-- Section Cron -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-icon">⌛</div>
            <strong>1. Planificateur (Crontab)</strong>
        </div>
        <p style="font-size: 0.85rem; margin: 0;">Nécessaire pour les notifications et le nettoyage automatique.</p>
        <?php if ($cronStatus === 'manual'): ?>
            <div class="config-code">
                * * * * * <?php echo $phpPath; ?> <?php echo $artisanPath; ?> schedule:run >> /dev/null 2>&1
            </div>
            <small style="color:var(--text-muted)">Tapez <code>crontab -e</code> en SSH et collez cette ligne.</small>
        <?php else: ?>
            <span class="badge success" style="margin-top:10px;">✓ Prêt à être configuré</span>
        <?php endif; ?>
    </div>

    <!-- Section WebSockets -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-icon">⚡</div>
            <strong>2. Temps Réel (Websockets)</strong>
        </div>
        <p style="font-size: 0.85rem; margin: 0;">Laravel Reverb sera configuré automatiquement pour les mises à jour en direct.</p>
        <span class="badge success" style="margin-top:10px;">✓ Auto-configuration active</span>
    </div>

    <form method="POST">
        <input type="hidden" name="setup_services" value="1">
        <div class="actions">
            <a href="?step=admin" class="btn secondary">Précédent</a>
            <button type="submit" class="btn primary">
                <?php echo ($cronStatus === 'manual') ? 'J\'ai configuré, Terminer' : 'Confirmer & Finaliser'; ?>
            </button>
        </div>
    </form>
</div>