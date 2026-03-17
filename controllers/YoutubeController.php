<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Google_Client;
use Google_Service_YouTube;
use Google_Service_YouTube_Video;
use Google_Service_YouTube_VideoSnippet;
use Google_Service_YouTube_VideoStatus;

class YoutubeController {

    private function getTokenPath(): string {
        $tokenDir = __DIR__ . '/../uploads';
        if (!is_dir($tokenDir)) mkdir($tokenDir, 0777, true);
        return $tokenDir . '/youtube_token.json';
    }

    private function buildClient(): Google_Client {
        $client = new Google_Client();
        $client->setAuthConfig(__DIR__ . '/../credentials.json');
        $client->addScope(Google_Service_YouTube::YOUTUBE_UPLOAD);
        $client->setAccessType('offline');

        $tokenFile = $this->getTokenPath();

        if (!file_exists($tokenFile)) {
            throw new \RuntimeException('YouTube token not found. Please authenticate first.');
        }

        $token = json_decode(file_get_contents($tokenFile), true);
        if (!$token || !isset($token['access_token'])) {
            throw new \RuntimeException('Invalid token format. Please re-authenticate.');
        }

        $client->setAccessToken($token);

        // Refresh token if expired
        if ($client->isAccessTokenExpired()) {
            if ($client->getRefreshToken()) {
                $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                file_put_contents($tokenFile, json_encode($newToken, JSON_PRETTY_PRINT));
                $client->setAccessToken($newToken);
            } else {
                throw new \RuntimeException('Access token expired and no refresh token available.');
            }
        }

        return $client;
    }

    public function upload($filePath, $title) {
        $client = $this->buildClient();
        $youtube = new Google_Service_YouTube($client);

        $snippet = new Google_Service_YouTube_VideoSnippet();
        $snippet->setTitle($title);

        $status = new Google_Service_YouTube_VideoStatus();
        $status->privacyStatus = "public";

        $video = new Google_Service_YouTube_Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        $response = $youtube->videos->insert(
            "snippet,status",
            $video,
            [
                "data" => file_get_contents($filePath),
                "mimeType" => "video/*",
                "uploadType" => "multipart"
            ]
        );

        if (!isset($response['id'])) {
            throw new \RuntimeException('YouTube upload failed');
        }

        return "https://youtube.com/watch?v=" . $response['id'];
    }
}