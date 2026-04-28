<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists(__DIR__.'/installed.lock')) {
    die('Installer disabled');
}

$allowed = ['welcome','server','database','license','admin','finish'];
$step = $_GET['step'] ?? 'welcome';

if (!in_array($step, $allowed)) {
    $step = 'welcome';
}

require __DIR__ . '/helpers.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Laravel Installer</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php
$file = __DIR__ . "/steps/$step.php";

if (file_exists($file)) {
    require $file;
} else {
    echo "<div class='card'>Step introuvable: $step</div>";
}
?>

</body>
</html>