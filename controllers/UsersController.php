<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use App\Services\CloudinaryService;
use PDO;

class UsersController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getAll(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $page = $_GET['page'] ?? 1;
            $search = $_GET['search'] ?? '';
            $limit = 20;
            $offset = ($page - 1) * $limit;

            $where = '';
            $params = [];

            if ($search) {
                $where = 'WHERE name LIKE ? OR email LIKE ?';
                $params = ["%$search%", "%$search%"];
            }

            $stmt = $this->db->prepare("
                SELECT * FROM users 
                $where
                ORDER BY created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM users $where");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

            Response::success('Users retrieved', [
                'users' => $users,
                'totalPages' => ceil($total / $limit)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get users', 500);
        }
    }

    public function getById(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Response::error('User not found', 404);
            }

            Response::success('User retrieved', $user);
        } catch (\Exception $e) {
            Response::error('Failed to get user', 500);
        }
    }

    public function ban(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('UPDATE users SET is_banned = 1 WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('User banned successfully');
        } catch (\Exception $e) {
            Response::error('Failed to ban user', 500);
        }
    }

    public function unban(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('UPDATE users SET is_banned = 0 WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('User unbanned successfully');
        } catch (\Exception $e) {
            Response::error('Failed to unban user', 500);
        }
    }

    public function delete(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('User deleted successfully');
        } catch (\Exception $e) {
            Response::error('Failed to delete user', 500);
        }
    }

    public function saveFromApp(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $name = trim($input['name'] ?? '');
            $businessName = trim($input['business_name'] ?? '');
            $email = trim($input['email'] ?? '');
            $phone = trim($input['phone'] ?? '');
            $avatarUrl = $input['avatar_url'] ?? null;
            $avatarPublicId = $input['avatar_public_id'] ?? null;

            if ($name === '') {
                Response::error('Name is required', 400);
            }

            if ($businessName === '') {
                Response::error('Business name is required', 400);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Valid email is required', 400);
            }

            $existingStmt = $this->db->prepare('SELECT id, email_verified, provider FROM users WHERE email = ? LIMIT 1');
            $existingStmt->execute([$email]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

            $emailVerified = false;
            if ($existing) {
                $emailVerified = (int) ($existing['email_verified'] ?? 0) === 1;
                if (!$emailVerified && isset($existing['provider']) && $existing['provider'] === 'google') {
                    $emailVerified = true;
                }
            }

            if (!$emailVerified) {
                $otpStmt = $this->db->prepare('SELECT id FROM email_otps WHERE email = ? AND is_verified = 1 ORDER BY created_at DESC LIMIT 1');
                $otpStmt->execute([$email]);
                $otpRecord = $otpStmt->fetch(PDO::FETCH_ASSOC);

                if (!$otpRecord) {
                    Response::error('Email has not been verified via OTP', 403);
                }

                $emailVerified = true;
            }

            if ($existing) {
                $updateStmt = $this->db->prepare('UPDATE users SET name = ?, business_name = ?, phone = ?, avatar_url = ?, avatar_public_id = ?, email_verified = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $updateStmt->execute([$name, $businessName, $phone ?: null, $avatarUrl, $avatarPublicId, $emailVerified ? 1 : 0, $existing['id']]);
                Response::success('User profile updated', ['id' => $existing['id']]);
            } else {
                $insertStmt = $this->db->prepare('INSERT INTO users (name, email, business_name, phone, avatar_url, avatar_public_id, email_verified) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $insertStmt->execute([$name, $email, $businessName, $phone ?: null, $avatarUrl, $avatarPublicId, $emailVerified ? 1 : 0]);
                Response::success('User profile created', ['id' => $this->db->lastInsertId()]);
            }
        } catch (\Exception $e) {
            Response::error('Failed to save user profile: ' . $e->getMessage(), 500);
        }
    }

    public function checkEmailAvailability(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Valid email is required', 400);
        }

        try {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                Response::error('This email is already registered.', 409);
            }

            Response::success('Email available');
        } catch (\Exception $e) {
            Response::error('Failed to check email', 500);
        }
    }

    public function getProfile(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error('Valid email is required', 400);
        }

        try {
            $stmt = $this->db->prepare('SELECT id, name, email, business_name, phone, avatar_url, avatar_public_id, downloads_count, created_at, updated_at FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Response::error('User not found', 404);
            }

            Response::success('User profile fetched', $user);
        } catch (\Exception $e) {
            Response::error('Failed to fetch profile', 500);
        }
    }

    public function updateAvatar(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $newAvatarUrl = $input['avatar_url'] ?? null;
        $newPublicId = $input['avatar_public_id'] ?? null;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$newAvatarUrl) {
            Response::error('Valid email and avatar_url are required', 400);
        }

        try {
            $stmt = $this->db->prepare('SELECT id, avatar_public_id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                Response::error('User not found', 404);
            }

            $oldPublicId = $user['avatar_public_id'] ?? null;

            if ($oldPublicId && $oldPublicId !== $newPublicId) {
                CloudinaryService::deleteImage($oldPublicId);
            }

            $updateStmt = $this->db->prepare('UPDATE users SET avatar_url = ?, avatar_public_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
            $updateStmt->execute([$newAvatarUrl, $newPublicId, $user['id']]);

            Response::success('Avatar updated successfully');
        } catch (\Exception $e) {
            Response::error('Failed to update avatar', 500);
        }
    }
}