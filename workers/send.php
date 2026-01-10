<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/app/autoload.php';

$appConfig = require $root . '/config/app.php';
$databaseConfig = require $root . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

$running = true;
$intervalSeconds = 5;

while ($running) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] Worker activo. Esperando correos... ";
    echo "(env: {$appConfig['env']}, db: {$databaseConfig['name']})" . PHP_EOL;

    sleep($intervalSeconds);
}
