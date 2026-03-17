<?php

namespace App\Services;

class CloudinaryService
{
    public static function deleteImage(?string $publicId): bool
    {
        if (!$publicId) {
            return true;
        }

        try {
            [$cloudName, $apiKey, $apiSecret] = self::getCredentials();
        } catch (\RuntimeException $e) {
            return false;
        }

        $timestamp = time();
        $signaturePayload = sprintf('public_id=%s&timestamp=%d%s', $publicId, $timestamp, $apiSecret);
        $signature = sha1($signaturePayload);

        $payload = http_build_query([
            'public_id' => $publicId,
            'timestamp' => $timestamp,
            'api_key' => $apiKey,
            'signature' => $signature,
        ]);

        $endpoint = sprintf('https://api.cloudinary.com/v1_1/%s/image/destroy', $cloudName);
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return false;
        }

        return $statusCode >= 200 && $statusCode < 300;
    }

    public static function uploadMedia(?array $file, string $folder = '', string $resourceType = 'image'): array
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('No file provided for Cloudinary upload.');
        }

        [$cloudName, $apiKey, $apiSecret] = self::getCredentials();

        $endpoint = sprintf('https://api.cloudinary.com/v1_1/%s/%s/upload', $cloudName, $resourceType);
        $timestamp = time();
        $folder = trim($folder);
        $publicId = self::buildPublicId($file);

        $paramsToSign = [
            'public_id' => $publicId,
            'timestamp' => $timestamp,
        ];
        if ($folder !== '') {
            $paramsToSign['folder'] = trim($folder, '/');
        }
        ksort($paramsToSign);

        $signature = sha1(self::buildSignaturePayload($paramsToSign) . $apiSecret);

        $postFields = [
            'file' => new \CURLFile($file['tmp_name'], $file['type'] ?? null, $file['name'] ?? 'upload'),
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'public_id' => $publicId,
            'signature' => $signature,
        ];
        if ($folder !== '') {
            $postFields['folder'] = $paramsToSign['folder'];
        }

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Cloudinary upload error: ' . $error);
        }

        $data = json_decode($response, true);
        if ($statusCode < 200 || $statusCode >= 300 || !is_array($data) || empty($data['secure_url'])) {
            throw new \RuntimeException('Cloudinary upload failed: ' . $response);
        }

        return [
            'url' => $data['secure_url'],
            'public_id' => $data['public_id'] ?? null,
            'format' => $data['format'] ?? null,
            'duration' => $data['duration'] ?? null,
            'resource_type' => $data['resource_type'] ?? $resourceType,
        ];
    }

    private static function getCredentials(): array
    {
        $cloudName = $_ENV['CLOUDINARY_CLOUD_NAME'] ?? getenv('CLOUDINARY_CLOUD_NAME');
        $apiKey = $_ENV['CLOUDINARY_API_KEY'] ?? getenv('CLOUDINARY_API_KEY');
        $apiSecret = $_ENV['CLOUDINARY_API_SECRET'] ?? getenv('CLOUDINARY_API_SECRET');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new \RuntimeException('Cloudinary credentials are missing.');
        }

        return [$cloudName, $apiKey, $apiSecret];
    }

    private static function buildPublicId(array $file): string
    {
        $base = pathinfo($file['name'] ?? '', PATHINFO_FILENAME) ?: 'upload';
        return $base . '-' . uniqid();
    }

    private static function buildSignaturePayload(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $parts[] = $key . '=' . $value;
        }

        return implode('&', $parts);
    }
}
