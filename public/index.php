<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require $root . '/app/autoload.php';

$appConfig = require $root . '/config/app.php';
$databaseConfig = require $root . '/config/database.php';

date_default_timezone_set($appConfig['timezone']);

$routes = [
    '/' => function () use ($appConfig): void {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>' . htmlspecialchars($appConfig['name'], ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>API de emails lista.</p>';
    },
    '/health' => function () use ($appConfig, $databaseConfig): void {
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'app' => $appConfig['name'],
            'env' => $appConfig['env'],
            'database' => $databaseConfig['name'],
        ]);
    },
];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rtrim($path, '/') ?: '/';

if (array_key_exists($path, $routes)) {
    $routes[$path]();
    return;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Ruta no encontrada';
