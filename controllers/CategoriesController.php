<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use App\Services\CloudinaryService;
use PDO;

class CategoriesController
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
            $stmt = $this->db->query('SELECT * FROM categories ORDER BY created_at DESC');
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success('Categories retrieved', $categories);
        } catch (\Exception $e) {
            Response::error('Failed to get categories', 500);
        }
    }

    public function listForApp(): void
    {
        try {
            $stmt = $this->db->query('SELECT id, name, icon, icon_public_id, is_active FROM categories WHERE is_active = 1 ORDER BY name ASC');
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success('Categories fetched', $categories);
        } catch (\Exception $e) {
            Response::error('Failed to fetch categories', 500);
        }
    }

    public function getById(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = ?');
            $stmt->execute([$id]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$category) {
                Response::error('Category not found', 404);
            }

            Response::success('Category retrieved', $category);
        } catch (\Exception $e) {
            Response::error('Failed to get category', 500);
        }
    }

    public function create(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                Response::error('Category name is required', 422);
            }
            $isActive = isset($_POST['isActive']) ? (bool)$_POST['isActive'] : true;
            $iconPublicId = $_POST['icon_public_id'] ?? null;

            $icon = $this->uploadFile($_FILES['icon'] ?? null);
            if (!$icon) {
                $icon = $_POST['icon'] ?? null;
            }

            $stmt = $this->db->prepare('INSERT INTO categories (name, icon, icon_public_id, is_active) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $icon, $iconPublicId, $isActive]);

            Response::success('Category created successfully', ['id' => $this->db->lastInsertId()]);
        } catch (\Exception $e) {
            Response::error('Failed to create category', 500);
        }
    }

    public function update(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('SELECT name, icon_public_id FROM categories WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                Response::error('Category not found', 404);
            }

            $currentPublicId = $existing['icon_public_id'] ?? null;

            $name = trim($_POST['name'] ?? '');
            if ($name === '') {
                Response::error('Category name is required', 422);
            }
            $isActive = isset($_POST['isActive']) ? (bool)$_POST['isActive'] : true;
            $iconPublicId = $_POST['icon_public_id'] ?? null;

            $icon = $this->uploadFile($_FILES['icon'] ?? null);
            if (!$icon) {
                $icon = $_POST['icon'] ?? null;
            }

            if ($iconPublicId && $currentPublicId && $currentPublicId !== $iconPublicId) {
                CloudinaryService::deleteImage($currentPublicId);
            }

            $updates = ['name = ?', 'is_active = ?'];
            $params = [$name, $isActive];

            if ($icon) {
                $updates[] = 'icon = ?';
                $params[] = $icon;
            }

            if ($iconPublicId) {
                $updates[] = 'icon_public_id = ?';
                $params[] = $iconPublicId;
            }

            $params[] = $id;

            $stmt = $this->db->prepare('UPDATE categories SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute($params);

            Response::success('Category updated successfully');
        } catch (\Exception $e) {
            Response::error('Failed to update category', 500);
        }
    }

    public function delete(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('DELETE FROM categories WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('Category deleted successfully');
        } catch (\Exception $e) {
            Response::error('Failed to delete category', 500);
        }
    }

    public function toggleStatus(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('UPDATE categories SET is_active = NOT is_active WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('Category status toggled successfully');
        } catch (\Exception $e) {
            Response::error('Failed to toggle status', 500);
        }
    }

    private function uploadFile($file): ?string
    {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $uploadDir = __DIR__ . '/../uploads/categories/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return '/uploads/categories/' . $filename;
        }

        return null;
    }
}
