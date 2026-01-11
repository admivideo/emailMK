<?php

declare(strict_types=1);


$root = __DIR__;


require $root . '/app/autoload.php';

use App\Services\Database;

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Completa email y contraseña.';
    } else {
        $database = Database::fromEnv();
        $statement = $database->connection()->prepare(
            'SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Credenciales inválidas.';
        } else {
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            header('Location: /dashboard.php');
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Login | emailMK</title>
</head>
<body>
    <h1>Iniciar sesión</h1>

    <?php if ($error !== null) : ?>
        <p style="color: #b91c1c;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <form method="post" action="/login.php">
        <label>
            Email
            <input type="email" name="email" required>
        </label>
        <br>
        <label>
            Contraseña
            <input type="password" name="password" required>
        </label>
        <br>
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
