<?php
session_start();

$config = require __DIR__ . '/config/database.php';
$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Debes ingresar email y contraseña.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no tiene un formato válido.';
    } else {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database']
            );
            $pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $statement = $pdo->prepare('SELECT id, email, password FROM users WHERE email = :email LIMIT 1');
            $statement->execute(['email' => $email]);
            $user = $statement->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                header('Location: dashboard.php');
                exit;
            }

            $errors[] = 'Credenciales inválidas.';
        } catch (PDOException $exception) {
            $errors[] = 'No se pudo validar el acceso. Inténtalo más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - emailMK</title>
    <style>
      body {
        font-family: Arial, sans-serif;
        background-color: #f5f6f8;
        color: #1f2933;
        margin: 0;
        padding: 0;
      }

      main {
        max-width: 420px;
        margin: 15vh auto;
        background: #ffffff;
        padding: 32px;
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
      }

      h1 {
        margin: 0 0 16px;
        font-size: 1.75rem;
      }

      label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
      }

      input {
        width: 100%;
        padding: 10px 12px;
        margin-bottom: 16px;
        border: 1px solid #d0d5dd;
        border-radius: 8px;
        font-size: 1rem;
      }

      button {
        width: 100%;
        padding: 12px;
        background-color: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
      }

      .error {
        background: #fee2e2;
        color: #991b1b;
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 16px;
      }
    </style>
  </head>
  <body>
    <main>
      <h1>Iniciar sesión</h1>
      <?php foreach ($errors as $error): ?>
        <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>
      <form method="post" action="">
        <label for="email">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          autocomplete="username"
          required
          value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"
        />

        <label for="password">Contraseña</label>
        <input
          type="password"
          id="password"
          name="password"
          autocomplete="current-password"
          required
        />

        <button type="submit">Entrar</button>
      </form>
    </main>
  </body>
</html>
