<style>
    .success-card {
        text-align: center;
        padding: 3rem;
    }
    .check-circle {
        width: 70px;
        height: 70px;
        background: var(--success);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 30px;
        margin: 0 auto 1.5rem;
    }
    .setup-guide {
        background: #f1f5f9;
        text-align: left;
        padding: 1.5rem;
        border-radius: 0.5rem;
        margin-top: 2rem;
    }
</style>

<style>
    .success-card { text-align: center; padding: 2.5rem; }
    
    .check-circle {
        width: 60px; height: 60px; background: var(--success); color: white;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 28px; margin: 0 auto 1.5rem;
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
    }

    .recap-container {
        text-align: left; background: #f8fafc; border: 1px solid var(--border);
        border-radius: 0.75rem; margin-top: 1.5rem; overflow: hidden;
    }

    .recap-header {
        background: #f1f5f9; padding: 0.75rem 1rem;
        font-size: 0.85rem; font-weight: 700; color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.025em;
        border-bottom: 1px solid var(--border);
    }

    .recap-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 0.75rem 1rem; border-bottom: 1px solid var(--border);
    }

    .recap-row:last-child { border-bottom: none; }

    .recap-label { font-size: 0.9rem; color: var(--text-muted); }

    .recap-value {
        font-family: 'Courier New', monospace; font-weight: 600;
        font-size: 0.9rem; color: var(--text-main);
    }

    .copy-link {
        font-size: 0.7rem; color: var(--primary); text-decoration: none;
        cursor: pointer; margin-left: 8px; border: 1px solid var(--primary);
        padding: 2px 6px; border-radius: 4px;
    }

    .copy-link:hover { background: var(--primary); color: white; }

    .setup-guide {
        background: #fffbeb; border: 1px solid #fde68a;
        text-align: left; padding: 1rem; border-radius: 0.5rem;
        margin-top: 1.5rem; color: #92400e; font-size: 0.85rem;
    }
</style>

<div class="card success-card">
    <div class="check-circle">✓</div>
    <h2>Installation Terminée !</h2>
    <p>Gardez précieusement ces informations de configuration.</p>

    <!-- Récapitulatif Admin -->
    <div class="recap-container">
        <div class="recap-header">Accès Administrateur</div>
        <div class="recap-row">
            <span class="recap-label">Email</span>
            <span class="recap-value"><?= htmlspecialchars($_SESSION['admin_email'] ?? 'Non défini') ?></span>
        </div>
        <div class="recap-row">
            <span class="recap-label">Mot de passe</span>
            <span class="recap-value">******** (Défini par vous)</span>
        </div>
    </div>

    <!-- Récapitulatif WebSocket / Services -->
    <div class="recap-container">
        <div class="recap-header">Configuration WebSocket</div>
        <div class="recap-row">
            <span class="recap-label">Port Reverb</span>
            <span class="recap-value">8080</span>
        </div>
        <div class="recap-row">
            <span class="recap-label">Broadcasting</span>
            <span class="recap-value">reverb</span>
        </div>
    </div>

    <div class="setup-guide">
        <strong>⚡ Rappel :</strong> N'oubliez pas de lancer le serveur WebSocket avec la commande <code>php artisan reverb:start</code> pour activer les notifications en temps réel.
    </div>

    <div class="actions" style="margin-top: 2rem;">
        <a href="../login" class="btn primary" style="text-decoration:none; width: 100%;">
            Lancer l'application →
        </a>
    </div>
    
    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 1rem;">
        Le dossier <code>/install</code> a été verrouillé par sécurité.
    </p>
</div>

<script>
    // Petit script optionnel pour copier les valeurs au clic
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Copié dans le presse-papier !');
        });
    }
</script>