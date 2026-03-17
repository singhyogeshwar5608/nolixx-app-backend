<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $host = (string)($_ENV['DB_HOST'] ?? 'localhost');
            $port = (string)($_ENV['DB_PORT'] ?? '');
            if ($port !== '') {
                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                    $host,
                    $port,
                    $_ENV['DB_NAME'] ?? ''
                );
            } else {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
                    $host,
                    $_ENV['DB_NAME'] ?? ''
                );
            }

            try {
                $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                self::$instance = $pdo;
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }
        }

        return self::$instance;
    }
}
