<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use PDO;

class AdminAuthController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function login(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email format', 400);
            }

            if (empty($password)) {
                Response::error('Password is required', 400);
            }

            $stmt = $this->db->prepare('SELECT * FROM admins WHERE email = ?');
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$admin || !password_verify($password, $admin['password'])) {
                Response::error('Invalid credentials', 401);
            }

            $token = JWT::encode([
                'admin_id' => $admin['id'],
                'email' => $admin['email'],
                'name' => $admin['name']
            ]);

            Response::success('Login successful', [
                'token' => $token,
                'user' => [
                    'id' => $admin['id'],
                    'name' => $admin['name'],
                    'email' => $admin['email']
                ]
            ]);
        } catch (\Exception $e) {
            Response::error('Login failed: ' . $e->getMessage(), 500);
        }
    }

    public function logout(): void
    {
        Response::success('Logged out successfully');
    }
}
