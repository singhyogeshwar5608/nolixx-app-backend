<?php

namespace App\Controllers;

use App\Helpers\Response;
use Google_Client;
use Google_Service_YouTube;

class YoutubeAuthController
{
    private function getCredentialsPath(): string
    {
        $configPath = __DIR__ . '/../config/youtube.php';
        $config = file_exists($configPath) ? require $configPath : [];
        return $config['credentials'] ?? __DIR__ . '/../credentials.json';
    }

    private function getRedirectUri(): string
    {
        $configured = trim((string)($_ENV['YOUTUBE_REDIRECT_URI'] ?? getenv('YOUTUBE_REDIRECT_URI') ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8000';

        return $scheme . '://' . $host . '/auth/youtube/callback';
    }

    private function getTokenFilePath(): string
    {
        $tokenDir = __DIR__ . '/../uploads';
        if (!is_dir($tokenDir)) {
            mkdir($tokenDir, 0777, true);
        }

        return $tokenDir . '/youtube_token.json';
    }

    private function buildClient(): Google_Client
    {
        $credentialsPath = $this->getCredentialsPath();
        if (!file_exists($credentialsPath)) {
            throw new \RuntimeException('YouTube credentials file not found at: ' . $credentialsPath);
        }

        $client = new Google_Client();
        $client->setAuthConfig($credentialsPath);
        $client->setRedirectUri($this->getRedirectUri());
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->addScope(Google_Service_YouTube::YOUTUBE_UPLOAD);

        return $client;
    }

    public function redirectToGoogle(): void
    {
        try {
            $client = $this->buildClient();
            $authUrl = $client->createAuthUrl();

            header('Location: ' . $authUrl);
            exit;
        } catch (\Throwable $e) {
            Response::error('Failed to start YouTube auth: ' . $e->getMessage(), 500);
        }
    }

    public function callback(): void
    {
        try {
            if (!isset($_GET['code']) || trim((string)$_GET['code']) === '') {
                $error = isset($_GET['error']) ? (string)$_GET['error'] : 'Missing code';
                Response::error('YouTube auth failed: ' . $error, 400);
            }

            $client = $this->buildClient();
            $token = $client->fetchAccessTokenWithAuthCode((string)$_GET['code']);

            if (isset($token['error'])) {
                $msg = is_string($token['error']) ? $token['error'] : 'Unknown error';
                $desc = isset($token['error_description']) ? (string)$token['error_description'] : '';
                Response::error('YouTube token exchange failed: ' . $msg . ($desc ? (' - ' . $desc) : ''), 400);
            }

            $tokenFile = $this->getTokenFilePath();

            $existing = [];
            if (file_exists($tokenFile)) {
                $decoded = json_decode((string)file_get_contents($tokenFile), true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }

            if (!isset($token['refresh_token']) && isset($existing['refresh_token'])) {
                $token['refresh_token'] = $existing['refresh_token'];
            }

            file_put_contents($tokenFile, json_encode($token, JSON_PRETTY_PRINT));

            Response::success('YouTube connected successfully', [
                'token_saved' => true,
                'token_file' => $tokenFile,
                'has_refresh_token' => !empty($token['refresh_token']),
                'redirect_uri_used' => $this->getRedirectUri(),
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to complete YouTube auth: ' . $e->getMessage(), 500);
        }
    }
}
