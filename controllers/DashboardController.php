<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use PDO;

class DashboardController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getStats(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $stmt = $this->db->query('SELECT COUNT(*) as count FROM users');
            $totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $this->db->query('SELECT COUNT(*) as count FROM templates');
            $totalTemplates = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $this->db->query('SELECT COUNT(*) as count FROM categories');
            $totalCategories = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $stmt = $this->db->query('SELECT COUNT(*) as count FROM subscriptions WHERE is_active = 1');
            $activeSubscriptions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            Response::success('Stats retrieved', [
                'totalUsers' => (int)$totalUsers,
                'totalTemplates' => (int)$totalTemplates,
                'totalCategories' => (int)$totalCategories,
                'activeSubscriptions' => (int)$activeSubscriptions
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get stats', 500);
        }
    }

    public function getRecentUploads(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $stmt = $this->db->query('
                SELECT t.*, c.name as category, s.name as subscriptionType 
                FROM templates t
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN subscriptions s ON t.subscription_id = s.id
                ORDER BY t.created_at DESC
                LIMIT 10
            ');
            $uploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success('Recent uploads retrieved', $uploads);
        } catch (\Exception $e) {
            Response::error('Failed to get recent uploads', 500);
        }
    }
}
