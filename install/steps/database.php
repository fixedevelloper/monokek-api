<?php

$error = '';
$host = '127.0.0.1';
$name = '';
$user = 'root';
$pass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $host = $_POST['DB_HOST'] ?? '';
    $name = $_POST['DB_DATABASE'] ?? '';
    $user = $_POST['DB_USERNAME'] ?? '';
    $pass = $_POST['DB_PASSWORD'] ?? '';

    try {

        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";

        new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $_SESSION['db_config'] = [
            'host' => $host,
            'name' => $name,
            'user' => $user,
            'pass' => $pass
        ];

        updateEnv([
            'DB_HOST' => $host,
            'DB_DATABASE' => $name,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => $pass,
        ]);

        header('Location: ?step=license');
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
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
        --danger: #ef4444;
        --success: #10b981;
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

    .card {
        background: var(--card-bg);
        padding: 2.5rem;
        border-radius: 1rem;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        width: 100%;
        max-width: 450px;
        border: 1px solid var(--border);
    }

    h2 {
        margin: 0 0 0.5rem 0;
        font-size: 1.5rem;
        font-weight: 700;
        text-align: center;
        color: var(--text-main);
    }

    p.subtitle {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.9rem;
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
        box-sizing: border-box; /* Important pour le padding */
    }

    input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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

    button:hover {
        background-color: var(--primary-hover);
    }

    .alert {
        padding: 1rem;
        border-radius: 0.5rem;
        margin-bottom: 1.5rem;
        font-size: 0.875rem;
        line-height: 1.4;
    }

    .alert.danger {
        background-color: #fef2f2;
        color: var(--danger);
        border: 1px solid #fecaca;
    }

    .db-icon {
        width: 48px;
        height: 48px;
        background: rgba(79, 70, 229, 0.1);
        color: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
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
        border-color: #cbd5e1;
    }

    .btn.disabled {
        background-color: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
        border: 1px solid var(--border);
    }
        .actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }
</style>

<div class="card">
    <div class="db-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.5 8d0-3 5.5-3 5.5 3s-5.5 3-5.5 3"/><path d="M5.5 21a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M15 3v5h5"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
    </div>

    <h2>Base de données</h2>
    <p class="subtitle">Veuillez renseigner vos identifiants MySQL.</p>

    <?php if (!empty($error)): ?>
        <div class="alert danger">
            <strong>Erreur de connexion :</strong><br>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Hôte (Host)</label>
            <input type="text" name="DB_HOST" value="<?= htmlspecialchars($host) ?>" placeholder="ex: 127.0.0.1 ou localhost" required>
        </div>

        <div class="form-group">
            <label>Nom de la base</label>
            <input type="text" name="DB_DATABASE" value="<?= htmlspecialchars($name) ?>" placeholder="ex: ma_base_de_donnees" required>
        </div>

        <div class="form-group">
            <label>Utilisateur (Username)</label>
            <input type="text" name="DB_USERNAME" value="<?= htmlspecialchars($user) ?>" placeholder="ex: root" required>
        </div>

        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="DB_PASSWORD" value="<?= htmlspecialchars($pass) ?>" placeholder="••••••••">
        </div>

        <div class="actions">
            <a href="?step=server" class="btn secondary">Précédent</a>
            <button type="submit" class="btn btn-primary">Vérifier & Continuer</button>
        </div>
    </form>
</div>