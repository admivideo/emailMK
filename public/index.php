<?php

declare(strict_types=1);

$routes = [
    '/' => function (): void {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>emailMK</h1><p>API de emails lista.</p>';
    },
    '/health' => function (): void {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
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
