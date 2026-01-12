<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userEmail = $_SESSION['user_email'] ?? 'usuario';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard - emailMK</title>
    <style>
      body {
        font-family: Arial, sans-serif;
        background-color: #f7f7f9;
        color: #1f2933;
        margin: 0;
        padding: 0;
      }

      main {
        max-width: 720px;
        margin: 15vh auto;
        background: #ffffff;
        padding: 32px;
        border-radius: 12px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
      }

      h1 {
        margin: 0 0 12px;
        font-size: 2rem;
      }

      p {
        margin: 0;
        line-height: 1.6;
      }
    </style>
  </head>
  <body>
    <main>
      <h1>Bienvenido/a</h1>
      <p>Hola, <?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?>. Has iniciado sesión correctamente.</p>
    </main>
  </body>
</html>
