<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use PDO;

class SubscriptionsController
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
            $stmt = $this->db->query('SELECT * FROM subscriptions ORDER BY created_at DESC');
            $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success('Subscriptions retrieved', $subscriptions);
        } catch (\Exception $e) {
            Response::error('Failed to get subscriptions', 500);
        }
    }

    public function getById(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('SELECT * FROM subscriptions WHERE id = ?');
            $stmt->execute([$id]);
            $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$subscription) {
                Response::error('Subscription not found', 404);
            }

            Response::success('Subscription retrieved', $subscription);
        } catch (\Exception $e) {
            Response::error('Failed to get subscription', 500);
        }
    }

    public function create(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $name = $input['name'] ?? '';
            $price = $input['price'] ?? 0;
            $duration = $input['duration'] ?? 'monthly';
            $isActive = isset($input['isActive']) ? (bool)$input['isActive'] : true;

            $stmt = $this->db->prepare('INSERT INTO subscriptions (name, price, duration, is_active) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $price, $duration, $isActive]);

            Response::success('Subscription created successfully', ['id' => $this->db->lastInsertId()]);
        } catch (\Exception $e) {
            Response::error('Failed to create subscription', 500);
        }
    }

    public function update(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $name = $input['name'] ?? '';
            $price = $input['price'] ?? 0;
            $duration = $input['duration'] ?? 'monthly';
            $isActive = isset($input['isActive']) ? (bool)$input['isActive'] : true;

            $stmt = $this->db->prepare('UPDATE subscriptions SET name = ?, price = ?, duration = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$name, $price, $duration, $isActive, $id]);

            Response::success('Subscription updated successfully');
        } catch (\Exception $e) {
            Response::error('Failed to update subscription', 500);
        }
    }

    public function delete(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('DELETE FROM subscriptions WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('Subscription deleted successfully');
        } catch (\Exception $e) {
            Response::error('Failed to delete subscription', 500);
        }
    }

    public function toggleStatus(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('UPDATE subscriptions SET is_active = NOT is_active WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('Subscription status toggled successfully');
        } catch (\Exception $e) {
            Response::error('Failed to toggle status', 500);
        }
    }
}
