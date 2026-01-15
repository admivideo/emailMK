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
$campaignListError = '';
$campaigns = [];
$templateErrors = [];
$templateSuccess = '';
$templateListError = '';
$templates = [];
$templateId = null;
$templateOptions = [];
$templateData = [
    'name' => '',
    'subject' => '',
    'preheader' => '',
    'html_body' => '',
    'text_body' => '',
];
$newTemplateData = [
    'name' => '',
    'subject' => '',
    'preheader' => '',
    'html_body' => '',
    'text_body' => '',
];
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

$allowedSortColumns = [
    'id' => 'id',
    'template_name' => 'template_name',
    'name' => 'name',
    'subject' => 'subject',
    'from_email' => 'from_email',
    'from_name' => 'from_name',
    'status' => 'status',
    'created_at' => 'created_at',
    'updated_at' => 'updated_at',
];
$sortColumn = $_GET['sort'] ?? 'created_at';
$sortDirection = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
if (!array_key_exists($sortColumn, $allowedSortColumns)) {
    $sortColumn = 'created_at';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'campaign') {
    $campaignData = [
        'name' => trim($_POST['name'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'from_email' => trim($_POST['from_email'] ?? ''),
        'from_name' => trim($_POST['from_name'] ?? ''),
        'status' => trim($_POST['status'] ?? 'draft'),
    ];
    $templateIdInput = (int) ($_POST['template_id'] ?? 0);

    if ($campaignData['name'] === '' || $campaignData['subject'] === '' || $campaignData['from_email'] === '') {
        $campaignErrors[] = 'Nombre, asunto y email remitente son obligatorios.';
    }

    if ($templateIdInput <= 0) {
        $campaignErrors[] = 'Debes seleccionar una plantilla válida.';
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
                'INSERT INTO campaigns (template_id, name, subject, from_email, from_name, status)
                 VALUES (:template_id, :name, :subject, :from_email, :from_name, :status)'
            );
            $statement->execute([
                'template_id' => $templateIdInput,
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'template') {
    $templateId = $_POST['template_id'] !== '' ? (int) ($_POST['template_id'] ?? 0) : null;
    $templateData = [
        'name' => trim($_POST['template_name'] ?? ''),
        'subject' => trim($_POST['template_subject'] ?? ''),
        'preheader' => trim($_POST['template_preheader'] ?? ''),
        'html_body' => trim($_POST['template_html_body'] ?? ''),
        'text_body' => trim($_POST['template_text_body'] ?? ''),
    ];

    if ($templateData['name'] === '' || $templateData['subject'] === '' || $templateData['html_body'] === '') {
        $templateErrors[] = 'Nombre, asunto y HTML son obligatorios.';
    }

    if (!$templateErrors) {
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

            if ($templateId) {
                $statement = $pdo->prepare(
                    'UPDATE plantillas
                     SET name = :name,
                         subject = :subject,
                         preheader = :preheader,
                         html_body = :html_body,
                         text_body = :text_body
                     WHERE id = :id'
                );
                $statement->execute([
                    'id' => $templateId,
                    'name' => $templateData['name'],
                    'subject' => $templateData['subject'],
                    'preheader' => $templateData['preheader'] !== '' ? $templateData['preheader'] : null,
                    'html_body' => $templateData['html_body'],
                    'text_body' => $templateData['text_body'] !== '' ? $templateData['text_body'] : null,
                ]);
                $templateSuccess = 'Plantilla actualizada correctamente.';
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO plantillas (name, subject, preheader, html_body, text_body)
                     VALUES (:name, :subject, :preheader, :html_body, :text_body)'
                );
                $statement->execute([
                    'name' => $templateData['name'],
                    'subject' => $templateData['subject'],
                    'preheader' => $templateData['preheader'] !== '' ? $templateData['preheader'] : null,
                    'html_body' => $templateData['html_body'],
                    'text_body' => $templateData['text_body'] !== '' ? $templateData['text_body'] : null,
                ]);
                $templateSuccess = 'Plantilla creada correctamente.';
            }

            $templateData = [
                'name' => '',
                'subject' => '',
                'preheader' => '',
                'html_body' => '',
                'text_body' => '',
            ];
            $templateId = null;
        } catch (PDOException $exception) {
            $templateErrors[] = 'No se pudo guardar la plantilla en la base de datos.';
        }
    }
}

if (isset($_GET['edit_template'])) {
    $templateId = (int) $_GET['edit_template'];
}

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

    $campaignsStatement = $pdo->prepare(
        sprintf(
            'SELECT campaigns.id, campaigns.name, campaigns.subject, campaigns.from_email, campaigns.from_name, campaigns.status, campaigns.created_at, campaigns.updated_at, plantillas.name AS template_name
             FROM campaigns
             LEFT JOIN plantillas ON campaigns.template_id = plantillas.id
             ORDER BY %s %s',
            $allowedSortColumns[$sortColumn],
            strtoupper($sortDirection)
        )
    );
    $campaignsStatement->execute();
    $campaigns = $campaignsStatement->fetchAll();
} catch (PDOException $exception) {
    $campaignListError = 'No se pudo cargar el listado de campañas.';
}

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

    $templatesStatement = $pdo->prepare(
        'SELECT id, name, subject, preheader, html_body, text_body, created_at, updated_at FROM plantillas ORDER BY created_at DESC'
    );
    $templatesStatement->execute();
    $templates = $templatesStatement->fetchAll();
    $templateOptions = $templates;
} catch (PDOException $exception) {
    $templateListError = 'No se pudo cargar el listado de plantillas.';
}

if ($templateId && !$templateErrors) {
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

        $templateStatement = $pdo->prepare(
            'SELECT id, name, subject, preheader, html_body, text_body FROM plantillas WHERE id = :id'
        );
        $templateStatement->execute(['id' => $templateId]);
        $selectedTemplate = $templateStatement->fetch();

        if ($selectedTemplate) {
            $templateData = [
                'name' => $selectedTemplate['name'],
                'subject' => $selectedTemplate['subject'],
                'preheader' => $selectedTemplate['preheader'] ?? '',
                'html_body' => $selectedTemplate['html_body'],
                'text_body' => $selectedTemplate['text_body'] ?? '',
            ];
        } else {
            $templateId = null;
            $templateErrors[] = 'No se encontró la plantilla seleccionada.';
        }
    } catch (PDOException $exception) {
        $templateErrors[] = 'No se pudo cargar la plantilla seleccionada.';
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
        display: none;
      }

      .section.is-active {
        display: block;
      }

      header {
        position: absolute;
        top: 16px;
        right: 16px;
      }

      .menu-toggle {
        display: none;
      }

      .menu-button {
        display: inline-flex;
        flex-direction: column;
        justify-content: center;
        gap: 5px;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        border: 1px solid #d0d5dd;
        background: #ffffff;
        cursor: pointer;
        padding: 8px;
      }

      .menu-button span {
        display: block;
        height: 2px;
        width: 100%;
        background: #1f2933;
        border-radius: 999px;
      }

      nav {
        position: absolute;
        right: 0;
        top: 52px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 16px;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        display: none;
        min-width: 180px;
      }

      nav a {
        display: block;
        color: #1f2933;
        text-decoration: none;
        font-weight: 600;
        padding: 8px 0;
      }

      .menu-toggle:checked + .menu-button + nav {
        display: block;
      }

      @media (min-width: 768px) {
        header {
          position: fixed;
        }

        nav {
          min-width: 220px;
        }
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

      textarea {
        width: 100%;
        min-height: 140px;
        padding: 10px 12px;
        border: 1px solid #d0d5dd;
        border-radius: 8px;
        font-size: 0.95rem;
        font-family: inherit;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
        font-size: 0.95rem;
      }

      th,
      td {
        border-bottom: 1px solid #e2e8f0;
        padding: 10px 8px;
        text-align: left;
      }

      th a {
        color: inherit;
        text-decoration: none;
        display: inline-flex;
        gap: 4px;
        align-items: center;
      }

      .sort-indicator {
        font-size: 0.75rem;
      }

      .table-wrapper {
        overflow-x: auto;
      }

      .helper {
        font-size: 0.9rem;
        color: #52606d;
      }
    </style>
  </head>
  <body>
    <header>
      <input type="checkbox" id="menu-toggle" class="menu-toggle" />
      <label for="menu-toggle" class="menu-button" aria-label="Abrir menú">
        <span></span>
        <span></span>
        <span></span>
      </label>
      <nav aria-label="Menú principal">
        <a href="#suscribers" data-section="suscribers">Suscribers</a>
        <a href="#plantillas" data-section="plantillas">Plantillas</a>
        <a href="#campanas" data-section="campanas">Campañas</a>
      </nav>
    </header>
    <main>
      <h1>Bienvenido/a</h1>
      <p>Hola, <?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?>. Has iniciado sesión correctamente.</p>
      <section class="section is-active" id="suscribers">
        <h2>Suscribers</h2>
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
      </section>

      <section class="section" id="campanas">
        <h2>Campañas</h2>
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

          <label for="campaign_template">Plantilla</label>
          <select id="campaign_template" name="template_id" required>
            <option value="">Selecciona una plantilla</option>
            <?php foreach ($templateOptions as $templateOption): ?>
              <option value="<?php echo htmlspecialchars((string) $templateOption['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($templateOption['name'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>

          <button type="submit">Crear campaña</button>
        </form>

        <h3>Listado de campañas</h3>
        <?php if ($campaignListError): ?>
          <p class="error"><?php echo htmlspecialchars($campaignListError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (!$campaigns): ?>
          <p>No hay campañas registradas.</p>
        <?php else: ?>
          <?php
            $labels = [
                'id' => 'ID',
                'template_name' => 'Plantilla',
                'name' => 'Nombre',
                'subject' => 'Asunto',
                'from_email' => 'Email remitente',
                'from_name' => 'Nombre remitente',
                'status' => 'Estado',
                'created_at' => 'Creado',
                'updated_at' => 'Actualizado',
            ];

            $nextDirection = $sortDirection === 'asc' ? 'desc' : 'asc';
          ?>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <?php foreach ($labels as $column => $label): ?>
                    <?php
                      $isActiveSort = $sortColumn === $column;
                      $direction = $isActiveSort ? $nextDirection : 'asc';
                    ?>
                    <th>
                      <a href="?sort=<?php echo urlencode($column); ?>&dir=<?php echo urlencode($direction); ?>#campanas">
                        <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($isActiveSort): ?>
                          <span class="sort-indicator"><?php echo $sortDirection === 'asc' ? '▲' : '▼'; ?></span>
                        <?php endif; ?>
                      </a>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($campaigns as $campaign): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($campaign['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['template_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['from_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['from_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($campaign['updated_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <section class="section" id="plantillas">
        <h2>Plantillas</h2>
        <?php if ($templateSuccess): ?>
          <p class="notice"><?php echo htmlspecialchars($templateSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php foreach ($templateErrors as $error): ?>
          <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endforeach; ?>
        <h3>Crear plantilla</h3>
        <form method="post">
          <input type="hidden" name="form_type" value="template" />
          <input type="hidden" name="template_id" value="" />
          <label for="new_template_name">Nombre</label>
          <input
            type="text"
            id="new_template_name"
            name="template_name"
            required
            value="<?php echo htmlspecialchars($newTemplateData['name'], ENT_QUOTES, 'UTF-8'); ?>"
          />

          <label for="new_template_subject">Asunto</label>
          <input
            type="text"
            id="new_template_subject"
            name="template_subject"
            required
            value="<?php echo htmlspecialchars($newTemplateData['subject'], ENT_QUOTES, 'UTF-8'); ?>"
          />

          <label for="new_template_preheader">Preheader</label>
          <input
            type="text"
            id="new_template_preheader"
            name="template_preheader"
            value="<?php echo htmlspecialchars($newTemplateData['preheader'], ENT_QUOTES, 'UTF-8'); ?>"
          />

          <label for="new_template_html_body">HTML del email</label>
          <p class="helper">Puedes usar la variable <strong>{{subscriber_name}}</strong> para personalizar el nombre del destinatario.</p>
          <textarea
            id="new_template_html_body"
            name="template_html_body"
            required
          ><?php echo htmlspecialchars($newTemplateData['html_body'], ENT_QUOTES, 'UTF-8'); ?></textarea>

          <label for="new_template_text_body">Texto alternativo</label>
          <p class="helper">Incluye {{subscriber_name}} para mostrar el nombre del destinatario en texto plano.</p>
          <textarea
            id="new_template_text_body"
            name="template_text_body"
          ><?php echo htmlspecialchars($newTemplateData['text_body'], ENT_QUOTES, 'UTF-8'); ?></textarea>

          <button type="submit">Crear plantilla</button>
        </form>

        <?php if ($templateId): ?>
          <h3>Editar plantilla</h3>
          <form method="post">
            <input type="hidden" name="form_type" value="template" />
            <input type="hidden" name="template_id" value="<?php echo htmlspecialchars((string) $templateId, ENT_QUOTES, 'UTF-8'); ?>" />
            <label for="template_name">Nombre</label>
            <input
              type="text"
              id="template_name"
              name="template_name"
              required
              value="<?php echo htmlspecialchars($templateData['name'], ENT_QUOTES, 'UTF-8'); ?>"
            />

            <label for="template_subject">Asunto</label>
            <input
              type="text"
              id="template_subject"
              name="template_subject"
              required
              value="<?php echo htmlspecialchars($templateData['subject'], ENT_QUOTES, 'UTF-8'); ?>"
            />

            <label for="template_preheader">Preheader</label>
            <input
              type="text"
              id="template_preheader"
              name="template_preheader"
              value="<?php echo htmlspecialchars($templateData['preheader'], ENT_QUOTES, 'UTF-8'); ?>"
            />

            <label for="template_html_body">HTML del email</label>
            <p class="helper">Puedes usar la variable <strong>{{subscriber_name}}</strong> para personalizar el nombre del destinatario.</p>
            <textarea
              id="template_html_body"
              name="template_html_body"
              required
            ><?php echo htmlspecialchars($templateData['html_body'], ENT_QUOTES, 'UTF-8'); ?></textarea>

            <label for="template_text_body">Texto alternativo</label>
            <p class="helper">Incluye {{subscriber_name}} para mostrar el nombre del destinatario en texto plano.</p>
            <textarea
              id="template_text_body"
              name="template_text_body"
            ><?php echo htmlspecialchars($templateData['text_body'], ENT_QUOTES, 'UTF-8'); ?></textarea>

            <button type="submit">Actualizar plantilla</button>
          </form>
        <?php endif; ?>

        <h3>Listado de plantillas</h3>
        <?php if ($templateListError): ?>
          <p class="error"><?php echo htmlspecialchars($templateListError, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php elseif (!$templates): ?>
          <p>No hay plantillas registradas.</p>
        <?php else: ?>
          <div class="table-wrapper">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Asunto</th>
                  <th>Preheader</th>
                  <th>HTML</th>
                  <th>Texto</th>
                  <th>Creado</th>
                  <th>Actualizado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($templates as $template): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($template['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($template['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($template['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($template['preheader'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($template['html_body'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($template['text_body'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($template['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($template['updated_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <a href="dashboard.php?edit_template=<?php echo urlencode($template['id']); ?>#plantillas">Editar</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
    <script>
      const sections = document.querySelectorAll('.section');
      const menuLinks = document.querySelectorAll('nav a[data-section]');

      const setActiveSection = (sectionId) => {
        sections.forEach((section) => {
          section.classList.toggle('is-active', section.id === sectionId);
        });
      };

      menuLinks.forEach((link) => {
        link.addEventListener('click', (event) => {
          event.preventDefault();
          const sectionId = link.dataset.section;
          if (sectionId) {
            setActiveSection(sectionId);
            history.replaceState(null, '', `#${sectionId}`);
            const menuToggle = document.getElementById('menu-toggle');
            if (menuToggle) {
              menuToggle.checked = false;
            }
          }
        });
      });

      if (window.location.hash) {
        const sectionId = window.location.hash.replace('#', '');
        setActiveSection(sectionId);
      }
    </script>
  </body>
</html>
