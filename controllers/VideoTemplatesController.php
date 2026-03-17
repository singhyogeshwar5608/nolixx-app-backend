<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\JWT;
use App\Helpers\Response;
use App\Services\CloudinaryService;
use PDO;

class VideoTemplatesController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function listTemplateVideos(): void
    {
        $payload = JWT::getPayload();
        $adminId = null;
        if ($payload && isset($payload->admin_id) && is_numeric($payload->admin_id)) {
            $adminId = (int) $payload->admin_id;
        }

        if (!$adminId) {
            Response::error('Unauthorized', 401);
        }

        try {
            $stmt = $this->db->query('
                SELECT tv.*, c.name AS category
                FROM template_videos tv
                LEFT JOIN categories c ON tv.category_id = c.id
                ORDER BY tv.created_at DESC
            ');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success('Template videos retrieved', [
                'videos' => $rows,
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to get template videos: ' . $e->getMessage(), 500);
        }
    }

    public function updateTemplateVideo(): void
    {
        $payload = JWT::getPayload();
        $adminId = null;
        if ($payload && isset($payload->admin_id) && is_numeric($payload->admin_id)) {
            $adminId = (int) $payload->admin_id;
        }

        if (!$adminId) {
            Response::error('Unauthorized', 401);
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            Response::error('id is required', 422);
        }

        try {
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? '')) ?: null;
            $description = trim((string) ($_POST['description'] ?? '')) ?: null;

            if ($categoryId <= 0) {
                Response::error('category_id is required', 422);
            }
            if ($videoUrl === '') {
                Response::error('video_url is required', 422);
            }

            $stmt = $this->db->prepare('UPDATE template_videos SET category_id = ?, video_url = ?, title = ?, description = ? WHERE id = ?');
            $stmt->execute([$categoryId, $videoUrl, $title, $description, $id]);

            Response::success('Template video updated successfully', [
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to update template video: ' . $e->getMessage(), 500);
        }
    }

    public function deleteTemplateVideo(): void
    {
        $payload = JWT::getPayload();
        $adminId = null;
        if ($payload && isset($payload->admin_id) && is_numeric($payload->admin_id)) {
            $adminId = (int) $payload->admin_id;
        }

        if (!$adminId) {
            Response::error('Unauthorized', 401);
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            Response::error('id is required', 422);
        }

        try {
            $stmt = $this->db->prepare('DELETE FROM template_videos WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('Template video deleted successfully', [
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to delete template video: ' . $e->getMessage(), 500);
        }
    }

    public function listForApp(): void
    {
        try {
            $stmt = $this->db->query('
                SELECT tv.*, c.name AS category
                FROM template_videos tv
                LEFT JOIN categories c ON tv.category_id = c.id
                ORDER BY tv.created_at DESC
            ');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success('Template videos retrieved', [
                'videos' => $rows,
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to get template videos: ' . $e->getMessage(), 500);
        }
    }

    public function createTemplateVideo(): void
    {
        $payload = JWT::getPayload();
        $adminId = null;
        if ($payload && isset($payload->admin_id) && is_numeric($payload->admin_id)) {
            $adminId = (int) $payload->admin_id;
        }

        if (!$adminId) {
            Response::error('Unauthorized', 401);
        }

        try {
            $categoryId = isset($_POST['category_id']) ? (int) $_POST['category_id'] : 0;
            $videoUrl = trim((string) ($_POST['video_url'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? '')) ?: null;
            $description = trim((string) ($_POST['description'] ?? '')) ?: null;

            if ($categoryId <= 0) {
                Response::error('category_id is required', 422);
            }
            if ($videoUrl === '') {
                Response::error('video_url is required', 422);
            }

            $stmt = $this->db->prepare('INSERT INTO template_videos (category_id, video_url, title, description) VALUES (?, ?, ?, ?)');
            $stmt->execute([$categoryId, $videoUrl, $title, $description]);

            Response::success('Template video saved successfully', [
                'id' => (int) $this->db->lastInsertId(),
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to save template video: ' . $e->getMessage(), 500);
        }
    }

    public function upload(): void
    {
        $payload = JWT::getPayload();
        $userId = JWT::extractUserIdFromPayload($payload);

        $adminId = null;
        if ($payload && isset($payload->admin_id) && is_numeric($payload->admin_id)) {
            $adminId = (int) $payload->admin_id;
        }

        if (!$userId && !$adminId) {
            Response::error('Unauthorized', 401);
        }

        try {
            $title = trim((string) ($_POST['title'] ?? '')) ?: null;
            $description = trim((string) ($_POST['description'] ?? '')) ?: null;

            if (empty($_FILES['video']['tmp_name'])) {
                Response::error('Video file is required', 422);
            }

            $upload = CloudinaryService::uploadMedia($_FILES['video'], 'videos', 'video');
            $videoUrl = $upload['url'];
            $cloudinaryPublicId = $upload['public_id'] ?? null;

            $videoId = 0;
            if ($userId) {
                $stmt = $this->db->prepare('
                    INSERT INTO video_templates (user_id, youtube_url, video_url, cloudinary_public_id, title, description)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$userId, $videoUrl, $videoUrl, $cloudinaryPublicId, $title, $description]);
                $videoId = (int) $this->db->lastInsertId();
            }

            Response::success('Video uploaded successfully', [
                'video_id' => $videoId,
                'video_url' => $videoUrl,
                'cloudinary_public_id' => $cloudinaryPublicId,
            ]);
        } catch (\Throwable $e) {
            Response::error('Failed to upload video: ' . $e->getMessage(), 500);
        }
    }
}
