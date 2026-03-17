<?php

namespace App\Helpers;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;

class JWT
{
    private static string $secretKey = '';
    private static string $algorithm = 'HS256';

    private static function ensureKey(): void
    {
        if (!self::$secretKey) {
            $envSecret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? getenv('JWT_SECRET');
            self::$secretKey = $envSecret ?: 'fallback-secret-key-change-me-please-1234567890';
        }
    }

    public static function encode(array $payload): string
    {
        self::ensureKey();
        $payload['iat'] = time();
        $payload['exp'] = time() + (60 * 60 * 24 * 7); // 7 days
        
        return FirebaseJWT::encode($payload, self::$secretKey, self::$algorithm);
    }

    public static function decode(string $token): ?object
    {
        try {
            self::ensureKey();
            return FirebaseJWT::decode($token, new Key(self::$secretKey, self::$algorithm));
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    public static function verify(): ?object
    {
        $token = self::getTokenFromHeader();
        
        if (!$token) {
            return null;
        }
        
        return self::decode($token);
    }

    public static function getPayload(): ?object
    {
        return self::verify();
    }

    public static function getAuthenticatedUserId(): ?int
    {
        $payload = self::verify();
        return self::extractUserIdFromPayload($payload);
    }

    public static function extractUserIdFromPayload(?object $payload): ?int
    {
        if (!$payload) {
            return null;
        }

        foreach (['user_id', 'userId', 'id'] as $key) {
            if (isset($payload->$key) && is_numeric($payload->$key)) {
                return (int) $payload->$key;
            }
        }

        return null;
    }
}
