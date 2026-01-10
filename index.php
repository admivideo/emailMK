<?php
$projectName = 'emailMK';
$message = '¡Bienvenido/a al proyecto!';
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Bienvenida - <?php echo htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'); ?></title>
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
        margin: 12vh auto;
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
        margin: 0 0 16px;
        line-height: 1.6;
      }

      ul {
        padding-left: 20px;
        margin: 0;
      }
    </style>
  </head>
  <body>
    <main>
      <h1><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></h1>
      <p>Este es un punto de inicio simple para el proyecto <?php echo htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8'); ?>.</p>
      <p>Desde aquí puedes:</p>
      <ul>
        <li>Explorar la estructura del repositorio.</li>
        <li>Definir los próximos pasos del equipo.</li>
        <li>Documentar avances importantes.</li>
      </ul>
    </main>
  </body>
</html>
