<?php
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $license_key = $_POST['license_key'] ?? '';
    $accept_terms = isset($_POST['accept_terms']);

    if (!$accept_terms) {
        $error = "Vous devez accepter les conditions d'utilisation pour continuer.";
    } elseif (empty($license_key)) {
        $error = "Veuillez saisir votre clé de licence.";
    } else {
        // Optionnel : Ici vous pouvez ajouter une vérification d'API pour la clé
        // if (verifyLicense($license_key)) { ... }
        
        // On sauvegarde la licence dans le .env pour référence future
        updateEnv(['APP_LICENSE' => $license_key]);
        
        nextStep('admin');
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
        margin-bottom: 1.5rem;
    }

    label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-main);
    }

    input[type="text"] {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid var(--border);
        background-color: #fff;
        font-size: 1rem;
        font-family: monospace; /* Pour que la clé soit bien lisible */
        transition: all 0.2s ease;
        box-sizing: border-box;
    }

    input[type="text"]:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    small {
        display: block;
        margin-top: 0.5rem;
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    /* Style spécifique pour la Checkbox */
    .checkbox-group {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: #f8fafc;
        padding: 1rem;
        border-radius: 0.5rem;
        border: 1px solid var(--border);
    }

    .checkbox-group input[type="checkbox"] {
        margin-top: 3px;
        cursor: pointer;
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
    }

    .checkbox-group label {
        margin-bottom: 0;
        font-weight: 500;
        cursor: pointer;
        line-height: 1.4;
        font-size: 0.9rem;
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
        text-align: center;
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
    <h2>Licence et Conditions</h2>
    <p>Veuillez valider votre achat et accepter les conditions générales.</p>

    <?php if (!empty($error)): ?>
        <div class="alert danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Clé de licence (Purchase Code)</label>
            <input type="text" name="license_key" placeholder="xxxx-xxxx-xxxx-xxxx" required>
            <small>Entrez le code reçu lors de votre achat.</small>
        </div>

        <div class="form-group checkbox-group">
            <input type="checkbox" name="accept_terms" id="terms" required>
            <label for="terms">J'accepte les conditions d'utilisation du logiciel et la politique de confidentialité.</label>
        </div>

        <hr>

        <div class="actions">
            <a href="?step=database" class="btn secondary">Précédent</a>
            <button type="submit" class="btn primary">Valider la licence</button>
        </div>
    </form>
</div>