<?php
require __DIR__ . '/../vendor/autoload.php';
session_start();

$client = new Google_Client();
$client->setAuthConfig(__DIR__ . '/../credentials.json');
$client->addScope(Google_Service_YouTube::YOUTUBE_UPLOAD);
$client->setRedirectUri('http://127.0.0.1:8000/oauth2callback.php');
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

if (!isset($_GET['code'])) {
    header('Location: ' . $client->createAuthUrl());
    exit;
} else {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        echo 'Error fetching token: ' . $token['error'];
        exit;
    }

    $tokenDir = __DIR__ . '/../uploads';
    if (!is_dir($tokenDir)) mkdir($tokenDir, 0777, true);
    file_put_contents($tokenDir . '/youtube_token.json', json_encode($token, JSON_PRETTY_PRINT));

    echo "Token saved successfully!";
}