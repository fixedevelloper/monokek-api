<?php
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['admin_email'] ?? '';
    $pass  = $_POST['admin_password'] ?? '';
    $name  = $_POST['admin_name'] ?? 'Admin';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Veuillez saisir une adresse email valide.";
    } elseif (strlen($pass) < 8) {
        $error = "Le mot de passe doit faire au moins 8 caractères.";
    } else {
        try {
            // 1. Connexion à la DB définie à l'étape précédente
            $dsn = "mysql:host=".getenv('DB_HOST').";dbname=".getenv('DB_DATABASE').";charset=utf8mb4";
            $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 2. Importation des tables (Optionnel si vous utilisez 'artisan migrate')
            // Ici, on part du principe que vous avez un fichier SQL de base
            $sql = file_get_contents(rootPath() . '/database/schema.sql');
            $pdo->exec($sql);

            // 3. Création du compte admin
            $hashedPassword = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $email, $hashedPassword]);

            nextStep('finish');
            
        } catch (Exception $e) {
            $error = "Erreur lors de la création du compte : " . $e->getMessage();
        }
    }
}
?>

<div class="card">
    <h2>Compte Administrateur</h2>
    <p>Configurez les accès pour gérer votre site Laravel.</p>

    <?php if ($error): ?>
        <div class="alert danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Nom complet</label>
            <input type="text" name="admin_name" placeholder="Ex: Jean Dupont" required>
        </div>

        <div class="form-group">
            <label>Adresse Email</label>
            <input type="email" name="admin_email" placeholder="admin@votre-site.com" required>
        </div>

        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="admin_password" placeholder="8 caractères minimum" required>
        </div>

        <hr>
        
        <div class="actions">
            <a href="?step=license" class="btn secondary">Précédent</a>
            <button type="submit" class="btn primary">Créer le compte et Terminer</button>
        </div>
    </form>
</div>