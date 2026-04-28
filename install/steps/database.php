<?php
$error = null;
$success = null;

// On récupère les valeurs postées ou on met des valeurs par défaut
$host = $_POST['DB_HOST'] ?? '127.0.0.1';
$name = $_POST['DB_DATABASE'] ?? '';
$user = $_POST['DB_USERNAME'] ?? '';
$pass = $_POST['DB_PASSWORD'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. On nettoie les entrées
    $host = sanitize($host);
    $name = sanitize($name);
    $user = sanitize($user);
    $pass = $pass; // On ne sanitize pas le mot de passe (peut contenir des caractères spéciaux)

    // 2. On teste la connexion via la fonction dans helpers.php
    $connectionTest = testDatabaseConnection($host, $name, $user, $pass);

    if ($connectionTest === true) {
        // 3. Si ça marche, on met à jour le fichier .env
        $envUpdated = updateEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST'       => $host,
            'DB_PORT'       => '3306',
            'DB_DATABASE'   => $name,
            'DB_USERNAME'   => $user,
            'DB_PASSWORD'   => $pass,
        ]);

        if ($envUpdated) {
            nextStep('license');
        } else {
            $error = "Impossible d'écrire dans le fichier .env. Vérifiez les permissions.";
        }
    } else {
        // Affiche l'erreur retournée par PDO
        $error = "Erreur de connexion : " . $connectionTest;
    }
}
?>

<div class="card">
    <div class='progress-container'>
        <div class='progress-bar' style='width:50%'></div>
    </div>

    <h2>Configuration de la base de données</h2>
    <p>Entrez vos paramètres de connexion MySQL. Ces informations seront inscrites dans votre fichier <code>.env</code>.</p>

    <?php if ($error): ?>
        <div class="alert danger">
            <strong>Erreur :</strong> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Hôte de la base de données</label>
            <input type="text" name="DB_HOST" value="<?php echo $host; ?>" required>
        </div>

        <div class="form-group">
            <label>Nom de la base de données</label>
            <input type="text" name="DB_DATABASE" value="<?php echo $name; ?>" placeholder="ex: laravel_db" required>
        </div>

        <div class="form-group">
            <label>Utilisateur</label>
            <input type="text" name="DB_USERNAME" value="<?php echo $user; ?>" placeholder="root" required>
        </div>

        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="DB_PASSWORD" value="<?php echo $pass; ?>">
        </div>

        <hr>

        <div class="actions">
            <a href="?step=server" class="btn secondary">Précédent</a>
            <button type="submit" class="btn primary">Vérifier & Enregistrer</button>
        </div>
    </form>
</div>