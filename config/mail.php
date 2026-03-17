<?php

namespace App\Config;

class MailConfig
{
    public static function settings(): array
    {
        return [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => $_ENV['EMAIL_USER'] ?? '',
            'password' => $_ENV['EMAIL_PASS'] ?? '',
            'from_email' => $_ENV['EMAIL_USER'] ?? 'no-reply@example.com',
            'from_name' => 'Nolixx App',
        ];
    }
}
