<?php

declare(strict_types=1);

$running = true;
$intervalSeconds = 5;

while ($running) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Worker activo. Esperando correos..." . PHP_EOL;

    sleep($intervalSeconds);
}
