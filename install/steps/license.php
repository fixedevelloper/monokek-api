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

<div class="card">
    <h2>Licence et Conditions</h2>
    <p>Veuillez valider votre achat et accepter les conditions générales.</p>

    <?php if ($error): ?>
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
            <label for="terms">J'accepte les conditions d'utilisation du logiciel.</label>
        </div>

        <hr>

        <div class="actions">
            <a href="?step=database" class="btn secondary">Précédent</a>
            <button type="submit" class="btn primary">Valider la licence</button>
        </div>
    </form>
</div>