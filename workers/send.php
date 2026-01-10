<?php

declare(strict_types=1);

use App\Services\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;
use Throwable;

$root = dirname(__DIR__);

require $root . '/app/autoload.php';

$appConfig = require $root . '/config/app.php';
$databaseConfig = require $root . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

$database = Database::fromEnv()->connection();
$running = true;
$intervalSeconds = 5;
$batchSize = 20;
$maxAttempts = 5;
$baseBackoffSeconds = 60;

$selectPending = $database->prepare(
    'SELECT sq.id AS queue_id,
            sq.attempts,
            sq.campaign_message_id,
            cm.status AS message_status,
            c.subject,
            c.from_email,
            c.from_name,
            s.email AS subscriber_email,
            s.name AS subscriber_name
     FROM send_queue sq
     INNER JOIN campaign_messages cm ON cm.id = sq.campaign_message_id
     INNER JOIN campaigns c ON c.id = cm.campaign_id
     INNER JOIN subscribers s ON s.id = cm.subscriber_id
     WHERE sq.status = :status
       AND sq.scheduled_at <= NOW()
     ORDER BY sq.scheduled_at ASC
     LIMIT :limit'
);
$selectPending->bindValue('status', 'pending');
$selectPending->bindValue('limit', $batchSize, PDO::PARAM_INT);

$updateQueue = $database->prepare(
    'UPDATE send_queue
        SET status = :status,
            scheduled_at = :scheduled_at,
            attempts = :attempts,
            last_attempt_at = :last_attempt_at
      WHERE id = :id'
);
$updateMessage = $database->prepare(
    'UPDATE campaign_messages
        SET status = :status,
            sent_at = :sent_at
      WHERE id = :id'
);
$insertEvent = $database->prepare(
    'INSERT INTO events (campaign_message_id, type, payload, occurred_at)
     VALUES (:campaign_message_id, :type, :payload, :occurred_at)'
);

while ($running) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Worker activo. Esperando correos... ";
    echo "(env: {$appConfig['env']}, db: {$databaseConfig['name']})" . PHP_EOL;

    $selectPending->execute();
    $pendingMessages = $selectPending->fetchAll();

    if ($pendingMessages === []) {
        sleep($intervalSeconds);
        continue;
    }

    foreach ($pendingMessages as $row) {
        $database->beginTransaction();

        try {
            $attempts = (int) $row['attempts'] + 1;
            $now = new DateTimeImmutable();
            $delivery = deliverMessage($row);

            $scheduledAt = null;
            $queueStatus = 'sent';
            $messageStatus = 'sent';
            $sentAt = $now->format('Y-m-d H:i:s');
            $eventType = 'sent';

            if ($delivery['success'] === false) {
                $queueStatus = $attempts >= $maxAttempts ? 'failed' : 'pending';
                $messageStatus = $queueStatus === 'failed' ? 'failed' : 'pending';
                $eventType = 'failed';
                $sentAt = null;

                $backoffSeconds = min(3600, $baseBackoffSeconds * (2 ** ($attempts - 1)));
                $scheduledAt = $now->add(new DateInterval('PT' . $backoffSeconds . 'S'))
                    ->format('Y-m-d H:i:s');
            }

            $updateQueue->execute([
                'status' => $queueStatus,
                'scheduled_at' => $scheduledAt,
                'attempts' => $attempts,
                'last_attempt_at' => $now->format('Y-m-d H:i:s'),
                'id' => $row['queue_id'],
            ]);

            $updateMessage->execute([
                'status' => $messageStatus,
                'sent_at' => $sentAt,
                'id' => $row['campaign_message_id'],
            ]);

            $insertEvent->execute([
                'campaign_message_id' => $row['campaign_message_id'],
                'type' => $eventType,
                'payload' => json_encode(
                    [
                        'response' => $delivery['message'],
                        'attempts' => $attempts,
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
                'occurred_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $database->commit();
        } catch (Throwable $exception) {
            $database->rollBack();
            $errorMessage = $exception->getMessage();
            echo "[{$timestamp}] Error al enviar: {$errorMessage}" . PHP_EOL;
        }
    }

    sleep($intervalSeconds);
}

function deliverMessage(array $row): array
{
    $email = $row['subscriber_email'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Correo inválido para el destinatario.',
        ];
    }

    $recipientName = $row['subscriber_name'] ?: 'suscriptor';
    $subject = $row['subject'];
    $fromEmail = $row['from_email'];
    $fromName = $row['from_name'] ?: 'Equipo';

    echo sprintf(
        'Enviando a %s <%s> desde %s <%s> asunto "%s".%s',
        $recipientName,
        $email,
        $fromName,
        $fromEmail,
        $subject,
        PHP_EOL
    );

    return [
        'success' => true,
        'message' => 'Mensaje entregado (simulado).',
    ];
}
