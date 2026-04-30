<?php
$error = null;
$name = $_POST['admin_name'] ?? '';
$email = $_POST['admin_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass  = $_POST['admin_password'] ?? '';

    // 1. Sécurisation des arguments pour le shell
    $emailArg = escapeshellarg($email);
    $passArg  = escapeshellarg($pass);
    $nameArg  = escapeshellarg($name);

    $artisanPath = realpath(__DIR__ . '/../../artisan');

    // 2. ASTUCE : On force le driver de log pour éviter l'erreur Pusher/Reverb 
    // qui crash si les clés ne sont pas encore chargées.
    putenv("BROADCAST_DRIVER=log");

    // 3. Exécution de la commande Artisan
    // On utilise les variables Arg (qui incluent déjà les quotes de sécurité)
    $command = "php $artisanPath app:install --email=$emailArg --password=$passArg --name=$nameArg 2>&1";
    
    exec($command, $outputArray, $returnCode);

    // On fusionne le tableau de sortie
    $output = implode("\n", $outputArray);

    if ($returnCode === 0) {
        // Stockage en session pour le récap final
        session_start();
        $_SESSION['admin_email'] = $email;

        // Créer le fichier de verrouillage
        file_put_contents(__DIR__ . '/../../installed.lock', date('Y-m-d H:i:s'));
        
        header('Location: ?step=services');
        exit;
    } else {
        // Nettoyage de l'output pour éviter d'afficher des chemins serveurs sensibles si besoin
        $error = "L'installation a échoué (Code $returnCode). <br> <strong>Détails :</strong> <pre style='text-align:left; background:#fff5f5; padding:10px; border:1px solid #feb2b2; font-size:12px;'>$output</pre>";
    }
}
?>

<!-- Ton HTML Card ici... -->

<style>
    :root {
        --primary: #4f46e5;
        --primary-hover: #4338ca;
        --bg: #f8fafc;
        --card-bg: #ffffff;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --border: #e2e8f0;
        --danger: #ef4444;
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

    .card {
        background: var(--card-bg);
        padding: 2.5rem;
        border-radius: 1.25rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 500px;
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

    .form-group {
        margin-bottom: 1.25rem;
    }

    label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-main);
    }

    input {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid var(--border);
        background-color: #fff;
        font-size: 1rem;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    hr {
        border: 0;
        border-top: 1px solid var(--border);
        margin: 2rem 0;
    }

    .alert.danger {
        background-color: #fef2f2;
        color: var(--danger);
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
        border: 1px solid #fecaca;
    }

    .actions {
        display: flex;
        gap: 1rem;
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
</style>

<div class="card">
    <div class='progress-container'>
        <div class='progress-bar' style='width:85%'></div>
    </div>

    <h2>Compte Administrateur</h2>
    <p>Dernière étape ! Créez votre compte pour accéder au tableau de bord.</p>

    <?php if (!empty($error)): ?>
        <div class="alert danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nom complet</label>
            <input type="text" name="admin_name" value="<?php echo isset($name) ? $name : ''; ?>" placeholder="Ex: Jean Dupont" required>
        </div>

        <div class="form-group">
            <label>Adresse Email</label>
            <input type="email" name="admin_email" value="<?php echo isset($email) ? $email : ''; ?>" placeholder="admin@votre-site.com" required>
        </div>

        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="admin_password" placeholder="8 caractères minimum" required minlength="8">
        </div>

        <hr>

        <div class="actions">
            <a href="?step=license" class="btn secondary">Précédent</a>
            <button type="submit" class="btn primary">Créer le compte et Terminer</button>
        </div>
    </form>
</div>