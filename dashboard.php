<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userEmail = $_SESSION['user_email'] ?? 'usuario';
$config = require __DIR__ . '/config/database.php';
$uploadErrors = [];
$uploadSuccess = '';
$campaignErrors = [];
$campaignSuccess = '';
$campaignData = [
    'name' => '',
    'subject' => '',
    'from_email' => '',
    'from_name' => '',
    'status' => 'draft',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'subscribers') {
    if (!isset($_FILES['subscribers_csv']) || $_FILES['subscribers_csv']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors[] = 'No se pudo cargar el archivo CSV.';
    } else {
        $fileTmpPath = $_FILES['subscribers_csv']['tmp_name'];
        $fileName = $_FILES['subscribers_csv']['name'];

        if (strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== 'csv') {
            $uploadErrors[] = 'El archivo debe tener extensión .csv.';
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

                $handle = fopen($fileTmpPath, 'rb');
                if ($handle === false) {
                    $uploadErrors[] = 'No se pudo leer el archivo CSV.';
                } else {
                    $header = fgetcsv($handle);
                    if (!$header) {
                        $uploadErrors[] = 'El archivo CSV está vacío.';
                    } else {
                        $headerMap = array_flip(array_map('trim', $header));
                        $requiredHeaders = ['id', 'email', 'nombre', 'creado_en', 'actualizado_en'];

                        foreach ($requiredHeaders as $requiredHeader) {
                            if (!array_key_exists($requiredHeader, $headerMap)) {
                                $uploadErrors[] = 'Falta la columna obligatoria: ' . $requiredHeader;
                            }
                        }

                        if (!$uploadErrors) {
                            $insert = $pdo->prepare(
                                'INSERT INTO subscribers (id, email, name, created_at, updated_at)
                                 VALUES (:id, :email, :name, :created_at, :updated_at)
                                 ON DUPLICATE KEY UPDATE
                                   email = VALUES(email),
                                   name = VALUES(name),
                                   created_at = VALUES(created_at),
                                   updated_at = VALUES(updated_at)'
                            );

                            $rowsInserted = 0;
                            while (($row = fgetcsv($handle)) !== false) {
                                $email = trim($row[$headerMap['email']] ?? '');
                                $id = trim($row[$headerMap['id']] ?? '');
                                $name = trim($row[$headerMap['nombre']] ?? '');
                                $createdAt = trim($row[$headerMap['creado_en']] ?? '');
                                $updatedAt = trim($row[$headerMap['actualizado_en']] ?? '');

                                if ($email === '') {
                                    continue;
                                }

                                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    continue;
                                }

                                $insert->execute([
                                    'id' => $id !== '' ? (int) $id : null,
                                    'email' => $email,
                                    'name' => $name !== '' ? $name : null,
                                    'created_at' => $createdAt !== '' ? $createdAt : null,
                                    'updated_at' => $updatedAt !== '' ? $updatedAt : null,
                                ]);
                                $rowsInserted++;
                            }

                            $uploadSuccess = sprintf('Se procesaron %d suscriptores.', $rowsInserted);
                        }
                    }

                    fclose($handle);
                }
            } catch (PDOException $exception) {
                $uploadErrors[] = 'No se pudo guardar el CSV en la base de datos.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'campaign') {
    $campaignData = [
        'name' => trim($_POST['name'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'from_email' => trim($_POST['from_email'] ?? ''),
        'from_name' => trim($_POST['from_name'] ?? ''),
        'status' => trim($_POST['status'] ?? 'draft'),
    ];

    if ($campaignData['name'] === '' || $campaignData['subject'] === '' || $campaignData['from_email'] === '') {
        $campaignErrors[] = 'Nombre, asunto y email remitente son obligatorios.';
    }

    if ($campaignData['from_email'] !== '' && !filter_var($campaignData['from_email'], FILTER_VALIDATE_EMAIL)) {
        $campaignErrors[] = 'El email remitente no es válido.';
    }

    $allowedStatuses = ['draft', 'scheduled', 'sending', 'sent'];
    if ($campaignData['status'] === '' || !in_array($campaignData['status'], $allowedStatuses, true)) {
        $campaignErrors[] = 'El estado seleccionado no es válido.';
    }

    if (!$campaignErrors) {
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

            $statement = $pdo->prepare(
                'INSERT INTO campaigns (name, subject, from_email, from_name, status)
                 VALUES (:name, :subject, :from_email, :from_name, :status)'
            );
            $statement->execute([
                'name' => $campaignData['name'],
                'subject' => $campaignData['subject'],
                'from_email' => $campaignData['from_email'],
                'from_name' => $campaignData['from_name'] !== '' ? $campaignData['from_name'] : null,
                'status' => $campaignData['status'],
            ]);

            $campaignSuccess = 'Campaña creada correctamente.';
            $campaignData = [
                'name' => '',
                'subject' => '',
                'from_email' => '',
                'from_name' => '',
                'status' => 'draft',
            ];
        } catch (PDOException $exception) {
            $campaignErrors[] = 'No se pudo guardar la campaña en la base de datos.';
        }
    }
}
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

      form {
        margin-top: 24px;
        display: grid;
        gap: 12px;
      }

      input[type='file'] {
        padding: 8px;
        border: 1px solid #d0d5dd;
        border-radius: 8px;
        background-color: #f8fafc;
      }

      button {
        width: fit-content;
        padding: 10px 18px;
        background-color: #2563eb;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        cursor: pointer;
      }

      .notice {
        padding: 10px 12px;
        border-radius: 8px;
        background: #ecfccb;
        color: #365314;
      }

      .error {
        padding: 10px 12px;
        border-radius: 8px;
        background: #fee2e2;
        color: #991b1b;
      }

      .section {
        margin-top: 32px;
        padding-top: 24px;
        border-top: 1px solid #e2e8f0;
      }

      label {
        font-weight: 600;
      }

      input,
      select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d0d5dd;
        border-radius: 8px;
        font-size: 0.95rem;
      }
    </style>
  </head>
  <body>
    <main>
      <h1>Bienvenido/a</h1>
      <p>Hola, <?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?>. Has iniciado sesión correctamente.</p>
      <?php if ($uploadSuccess): ?>
        <p class="notice"><?php echo htmlspecialchars($uploadSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endif; ?>
      <?php foreach ($uploadErrors as $error): ?>
        <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
      <?php endforeach; ?>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="form_type" value="subscribers" />
        <label for="subscribers_csv">Subir CSV de suscriptores</label>
        <input type="file" id="subscribers_csv" name="subscribers_csv" accept=".csv" required />
        <button type="submit">Cargar y procesar</button>
      </form>

      <section class="section">
        <h2>Crear campaña</h2>
        <?php if ($campaignSuccess): ?>
          <p class="notice"><?php echo htmlspecialchars($campaignSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php foreach ($campaignErrors as $error): ?>
          <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endforeach; ?>
        <form method="post">
          <input type="hidden" name="form_type" value="campaign" />
          <label for="campaign_name">Nombre</label>
          <input
            type="text"
            id="campaign_name"
            name="name"
            required
            value="<?php echo htmlspecialchars($campaignData['name'], ENT_QUOTES, 'UTF-8'); ?>"
          />

          <label for="campaign_subject">Asunto</label>
          <input
            type="text"
            id="campaign_subject"
            name="subject"
            required
            value="<?php echo htmlspecialchars($campaignData['subject'], ENT_QUOTES, 'UTF-8'); ?>"
          />

          <label for="campaign_from_email">Email remitente</label>
          <input
            type="email"
            id="campaign_from_email"
            name="from_email"
            required
            value="<?php echo htmlspecialchars($campaignData['from_email'], ENT_QUOTES, 'UTF-8'); ?>"
          />

          <label for="campaign_from_name">Nombre remitente</label>
          <input
            type="text"
            id="campaign_from_name"
            name="from_name"
            value="<?php echo htmlspecialchars($campaignData['from_name'], ENT_QUOTES, 'UTF-8'); ?>"
          />

          <label for="campaign_status">Estado</label>
          <select id="campaign_status" name="status">
            <option value="draft" <?php echo $campaignData['status'] === 'draft' ? 'selected' : ''; ?>>Borrador</option>
            <option value="scheduled" <?php echo $campaignData['status'] === 'scheduled' ? 'selected' : ''; ?>>Programada</option>
            <option value="sending" <?php echo $campaignData['status'] === 'sending' ? 'selected' : ''; ?>>Envío</option>
            <option value="sent" <?php echo $campaignData['status'] === 'sent' ? 'selected' : ''; ?>>Enviada</option>
          </select>

          <button type="submit">Crear campaña</button>
        </form>
      </section>
    </main>
  </body>
</html>
