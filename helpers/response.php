<?php

namespace App\Helpers;

class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function success(string $message, array $data = []): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    public static function error(string $message, int $statusCode = 400): void
    {
        self::json([
            'success' => false,
            'message' => $message,
        ], $statusCode);
    }
}
