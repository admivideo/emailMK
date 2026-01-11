<?php

declare(strict_types=1);

$root = __DIR__;

require $root . '/app/autoload.php';

use App\Controllers\CampaignController;
use App\Services\Database;

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

$database = Database::fromEnv();
$connection = $database->connection();
$campaignController = new CampaignController($connection);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_profile') {
        $senderName = trim($_POST['sender_name'] ?? '');
        $signature = trim($_POST['signature'] ?? '');
        $logoUrl = trim($_POST['logo_url'] ?? '');

        $statement = $connection->prepare(
            'INSERT INTO user_profiles (user_id, sender_name, signature, logo_url) VALUES (:user_id, :sender_name, :signature, :logo_url)
            ON DUPLICATE KEY UPDATE sender_name = VALUES(sender_name), signature = VALUES(signature), logo_url = VALUES(logo_url)'
        );
        $statement->execute([
            'user_id' => $_SESSION['user_id'],
            'sender_name' => $senderName,
            'signature' => $signature,
            'logo_url' => $logoUrl,
        ]);

        $_SESSION['flash'] = 'Preferencias guardadas.';
        header('Location: /dashboard.php');
        exit;
    }

    if ($action === 'create_campaign' || $action === 'update_campaign') {
        $payload = [
            'name' => trim($_POST['name'] ?? ''),
            'subject' => trim($_POST['subject'] ?? ''),
            'from_email' => trim($_POST['from_email'] ?? ''),
            'from_name' => trim($_POST['from_name'] ?? ''),
            'status' => trim($_POST['status'] ?? 'draft') ?: 'draft',
        ];

        if ($payload['name'] === '' || $payload['subject'] === '' || $payload['from_email'] === '') {
            $error = 'Nombre, asunto y email remitente son obligatorios.';
        } else {
            if ($action === 'create_campaign') {
                $campaignController->create($payload);
                $_SESSION['flash'] = 'Campaña creada.';
            } else {
                $campaignId = (int) ($_POST['campaign_id'] ?? 0);
                if ($campaignId <= 0) {
                    $error = 'Selecciona una campaña válida.';
                } else {
                    $campaignController->update($campaignId, $payload);
                    $_SESSION['flash'] = 'Campaña actualizada.';
                }
            }
        }

        if ($error === null) {
            header('Location: /dashboard.php');
            exit;
        }
    }

    if ($action === 'delete_campaign') {
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);
        if ($campaignId > 0) {
            $campaignController->delete($campaignId);
            $_SESSION['flash'] = 'Campaña eliminada.';
            header('Location: /dashboard.php');
            exit;
        }

        $error = 'Selecciona una campaña válida.';
    }
}

$profileStatement = $connection->prepare(
    'SELECT sender_name, signature, logo_url FROM user_profiles WHERE user_id = :user_id LIMIT 1'
);
$profileStatement->execute(['user_id' => $_SESSION['user_id']]);
$profile = $profileStatement->fetch() ?: [
    'sender_name' => '',
    'signature' => '',
    'logo_url' => '',
];

$campaigns = $campaignController->list();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Dashboard | emailMK</title>
</head>
<body>
    <h1>Hola <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'usuario', ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><a href="/dashboard.php?action=logout">Cerrar sesión</a></p>

    <?php if ($flash !== null) : ?>
        <p style="color: #047857;"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <?php if ($error !== null) : ?>
        <p style="color: #b91c1c;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <section>
        <h2>Preferencias de usuario</h2>
        <form method="post" action="/dashboard.php">
            <input type="hidden" name="action" value="save_profile">
            <label>
                Nombre remitente
                <input type="text" name="sender_name" value="<?php echo htmlspecialchars($profile['sender_name'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <br>
            <label>
                Firma
                <textarea name="signature" rows="4" cols="40"><?php echo htmlspecialchars($profile['signature'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>
            <br>
            <label>
                Logo (URL)
                <input type="url" name="logo_url" value="<?php echo htmlspecialchars($profile['logo_url'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <br>
            <button type="submit">Guardar preferencias</button>
        </form>
    </section>

    <section>
        <h2>Crear campaña</h2>
        <form method="post" action="/dashboard.php">
            <input type="hidden" name="action" value="create_campaign">
            <label>
                Nombre
                <input type="text" name="name" required>
            </label>
            <br>
            <label>
                Asunto
                <input type="text" name="subject" required>
            </label>
            <br>
            <label>
                Email remitente
                <input type="email" name="from_email" required>
            </label>
            <br>
            <label>
                Nombre remitente
                <input type="text" name="from_name">
            </label>
            <br>
            <label>
                Estado
                <select name="status">
                    <option value="draft">Borrador</option>
                    <option value="scheduled">Programada</option>
                    <option value="sent">Enviada</option>
                </select>
            </label>
            <br>
            <button type="submit">Crear campaña</button>
        </form>
    </section>

    <section>
        <h2>Campañas</h2>
        <?php if (empty($campaigns)) : ?>
            <p>No hay campañas aún.</p>
        <?php else : ?>
            <table border="1" cellpadding="6">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Asunto</th>
                        <th>Remitente</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($campaigns as $campaign) : ?>
                        <tr>
                            <td><?php echo (int) $campaign['id']; ?></td>
                            <td><?php echo htmlspecialchars($campaign['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($campaign['subject'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($campaign['from_name'] ?: $campaign['from_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($campaign['status'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <form method="post" action="/dashboard.php" style="margin-bottom: 8px;">
                                    <input type="hidden" name="action" value="update_campaign">
                                    <input type="hidden" name="campaign_id" value="<?php echo (int) $campaign['id']; ?>">
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($campaign['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <input type="text" name="subject" value="<?php echo htmlspecialchars($campaign['subject'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <input type="email" name="from_email" value="<?php echo htmlspecialchars($campaign['from_email'], ENT_QUOTES, 'UTF-8'); ?>" required>
                                    <input type="text" name="from_name" value="<?php echo htmlspecialchars($campaign['from_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                    <select name="status">
                                        <option value="draft" <?php echo $campaign['status'] === 'draft' ? 'selected' : ''; ?>>Borrador</option>
                                        <option value="scheduled" <?php echo $campaign['status'] === 'scheduled' ? 'selected' : ''; ?>>Programada</option>
                                        <option value="sent" <?php echo $campaign['status'] === 'sent' ? 'selected' : ''; ?>>Enviada</option>
                                    </select>
                                    <button type="submit">Actualizar</button>
                                </form>
                                <form method="post" action="/dashboard.php">
                                    <input type="hidden" name="action" value="delete_campaign">
                                    <input type="hidden" name="campaign_id" value="<?php echo (int) $campaign['id']; ?>">
                                    <button type="submit">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</body>
</html>
