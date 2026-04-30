<?php
$requirements = checkRequirements();
$permissions = checkPermissions();
$allOk = !in_array(false, $requirements) && !in_array(false, $permissions);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
    nextStep('database');
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

    p.subtitle {
        text-align: center;
        color: var(--text-muted);
        font-size: 0.95rem;
        margin-bottom: 2rem;
    }

    h3 {
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin: 1.5rem 0 1rem;
        border-left: 3px solid var(--primary);
        padding-left: 10px;
    }

    hr {
        border: 0;
        border-top: 1px solid var(--border);
        margin: 2rem 0;
    }

    /* Check-list style */
    .check-list {
        list-style: none;
        padding: 0;
        margin: 0 0 1.5rem 0;
    }

    .check-list li {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.85rem 1rem;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
        font-weight: 500;
        transition: transform 0.2s ease;
    }

    .check-list li:hover {
        transform: translateX(4px);
        border-color: #cbd5e1;
    }

    /* Status Badges */
    .badge {
        padding: 0.35rem 0.75rem;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .badge.success {
        background-color: #ecfdf5;
        color: var(--success);
        border: 1px solid #d1fae5;
    }

    .badge.danger {
        background-color: #fef2f2;
        color: var(--danger);
        border: 1px solid #fecaca;
    }

    /* Actions & Buttons */
    .actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
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
        border-color: #cbd5e1;
    }

    .btn.disabled {
        background-color: #f1f5f9;
        color: #94a3b8;
        cursor: not-allowed;
        border: 1px solid var(--border);
    }

    .icon-status {
        font-size: 1rem;
    }
</style>

<div class="card">
    <h2>Vérification du serveur</h2>
    <p class="subtitle">Nous analysons votre configuration pour garantir une installation sans erreur.</p>

    <h3>Extensions PHP</h3>
    <ul class="check-list">
        <?php foreach ($requirements as $label => $success): ?>
            <li>
                <span><?= $label ?></span>
                <span class="badge <?= $success ? 'success' : 'danger' ?>">
                    <span class="icon-status"><?= $success ? '✓' : '✕' ?></span>
                    <?= $success ? 'OPÉRATIONNEL' : 'MANQUANT' ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Permissions des dossiers</h3>
    <ul class="check-list">
        <?php foreach ($permissions as $label => $success): ?>
            <li>
                <span><small style="color:var(--text-muted)">Dossier :</small> <?= $label ?></span>
                <span class="badge <?= $success ? 'success' : 'danger' ?>">
                    <span class="icon-status"><?= $success ? '✓' : '✕' ?></span>
                    <?= $success ? 'LECTURE/ÉCRITURE' : 'ACCÈS REFUSÉ' ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <hr>

    <form method="POST">
        <div class="actions">
            <a href="?step=welcome" class="btn secondary">Précédent</a>
            <?php if ($allOk): ?>
                <button type="submit" class="btn primary">Continuer l'installation</button>
            <?php else: ?>
                <button type="button" class="btn disabled" disabled>Corrigez les erreurs pour continuer</button>
            <?php endif; ?>
        </div>
    </form>
</div>