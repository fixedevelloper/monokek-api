<?php
$requirements = checkRequirements();
$permissions = checkPermissions();
$allOk = !in_array(false, $requirements) && !in_array(false, $permissions);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allOk) {
    nextStep('database');
}
?>

<div class="card">
    <h2>Vérification du serveur</h2>
    <p>Nous vérifions si votre serveur est prêt à accueillir Laravel.</p>

    <hr>

    <h3>Extensions PHP</h3>
    <ul class="check-list">
        <?php foreach ($requirements as $label => $success): ?>
            <li>
                <?php echo $label; ?> 
                <span class="badge <?php echo $success ? 'success' : 'danger'; ?>">
                    <?php echo $success ? '✅ OK' : '❌ Manquant'; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <h3>Permissions des dossiers</h3>
    <ul class="check-list">
        <?php foreach ($permissions as $label => $success): ?>
            <li>
                Dossier : <?php echo $label; ?> 
                <span class="badge <?php echo $success ? 'success' : 'danger'; ?>">
                    <?php echo $success ? '✅ Scriptible' : '❌ Erreur d\'écriture'; ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <hr>

    <form method="POST">
        <div class="actions">
            <a href="?step=welcome" class="btn secondary">Précédent</a>
            <?php if ($allOk): ?>
                <button type="submit" class="btn primary">Continuer</button>
            <?php else: ?>
                <button type="button" class="btn disabled" disabled>Corrigez les erreurs pour continuer</button>
            <?php endif; ?>
        </div>
    </form>
</div>