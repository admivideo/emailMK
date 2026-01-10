<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

final class Database
{
    private PDO $connection;

    public function __construct(
        string $host,
        string $database,
        string $username,
        string $password,
        string $charset = 'utf8mb4'
    ) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $database, $charset);

        $this->connection = new PDO(
            $dsn,
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public static function fromEnv(): self
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        return new self(
            getenv('DB_HOST') ?: ($config['host'] ?? '127.0.0.1'),
            getenv('DB_NAME') ?: ($config['database'] ?? 'emailmk'),
            getenv('DB_USER') ?: ($config['user'] ?? 'root'),
            getenv('DB_PASSWORD') ?: ($config['password'] ?? '')
        );
    }

    public function connection(): PDO
    {
        return $this->connection;
    }
}
