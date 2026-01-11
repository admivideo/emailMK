<?php

declare(strict_types=1);

$root = __DIR__;

if (!is_file($root . '/app/autoload.php')) {
    $root = dirname(__DIR__);
}

require $root . '/app/autoload.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (($_GET['action'] ?? '') === 'logout') {
    session_destroy();
    header('Location: /login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Dashboard | emailMK</title>
</head>
<body>
    <h1>Hola <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'usuario', ENT_QUOTES, 'UTF-8'); ?></h1>
    <p>Bienvenido al dashboard.</p>
    <p><a href="/dashboard.php?action=logout">Cerrar sesión</a></p>
</body>
</html>
