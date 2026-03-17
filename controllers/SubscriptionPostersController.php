<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use PDO;

class SubscriptionPostersController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
        $this->ensureTableExists();
    }

    public function listAdmin(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $stmt = $this->db->query('SELECT * FROM subscription_posters ORDER BY sort_order ASC, created_at DESC');
            $posters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $posters = $this->attachPosterImages($posters);
            Response::success('Subscription posters retrieved', $posters);
        } catch (\Exception $e) {
            Response::error('Failed to fetch subscription posters', 500);
        }
    }

    public function listForApp(): void
    {
        try {
            $stmt = $this->db->query('SELECT id, title, image_url, category_id FROM subscription_posters WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC');
            $posters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $posters = $this->attachPosterImages($posters);
            Response::success('Subscription posters fetched', $posters);
        } catch (\Exception $e) {
            Response::error('Failed to fetch subscription posters', 500);
        }
    }

    public function create(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $title = trim($_POST['title'] ?? '');
            if ($title === '') {
                Response::error('Title is required', 422);
            }

            $categoryId = isset($_POST['categoryId']) && $_POST['categoryId'] !== '' ? (int) $_POST['categoryId'] : null;
            $sortOrder = isset($_POST['sortOrder']) ? (int) $_POST['sortOrder'] : 0;
            $isActive = isset($_POST['isActive']) ? (bool) $_POST['isActive'] : true;

            $imageUrl = trim($_POST['imageUrl'] ?? '');
            $primaryImage = $imageUrl !== '' ? $imageUrl : null;
            $singleUpload = $primaryImage ? null : $this->uploadImage($_FILES['image'] ?? null);
            if ($singleUpload) {
                $primaryImage = $singleUpload;
            }

            $additionalImages = $this->collectAdditionalImages();

            if (!$primaryImage && !empty($additionalImages)) {
                $primaryImage = array_shift($additionalImages);
            }

            if (!$primaryImage) {
                Response::error('Poster image or URL is required', 422);
            }

            $allImages = $this->buildImageSet($primaryImage, $additionalImages);

            $stmt = $this->db->prepare('INSERT INTO subscription_posters (title, image_url, category_id, is_active, sort_order) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $primaryImage, $categoryId, $isActive, $sortOrder]);

            $posterId = (int) $this->db->lastInsertId();
            $this->savePosterImages($posterId, $allImages, true);

            Response::success('Subscription poster created', [
                'id' => $posterId,
                'title' => $title,
                'image_url' => $primaryImage,
                'images' => $allImages,
                'category_id' => $categoryId,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to create subscription poster', 500);
        }
    }

    public function update(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            Response::error('Invalid poster id', 422);
        }

        try {
            $stmt = $this->db->prepare('SELECT * FROM subscription_posters WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $poster = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$poster) {
                Response::error('Subscription poster not found', 404);
            }

            $existingImagesMap = $this->getPosterImages([$id]);
            $existingImages = $existingImagesMap[$id] ?? [];

            $title = trim($_POST['title'] ?? $poster['title']);
            if ($title === '') {
                Response::error('Title is required', 422);
            }

            $categoryId = isset($_POST['categoryId']) && $_POST['categoryId'] !== '' ? (int) $_POST['categoryId'] : null;
            $sortOrder = isset($_POST['sortOrder']) ? (int) $_POST['sortOrder'] : (int) $poster['sort_order'];
            $isActive = isset($_POST['isActive']) ? (bool) $_POST['isActive'] : (bool) $poster['is_active'];

            $imageUrl = trim($_POST['imageUrl'] ?? '');
            if ($imageUrl !== '') {
                $imagePath = $imageUrl;
                if ($poster['image_url'] && $this->isLocalPath($poster['image_url']) && $poster['image_url'] !== $imagePath) {
                    $this->deleteImage($poster['image_url']);
                }
            } else {
                $newImage = $this->uploadImage($_FILES['image'] ?? null);
                $imagePath = $newImage ?: $poster['image_url'];

                if ($newImage && $poster['image_url'] && $this->isLocalPath($poster['image_url'])) {
                    $this->deleteImage($poster['image_url']);
                }
            }

            $additionalImages = $this->collectAdditionalImages();
            $finalImages = $this->buildImageSet($imagePath, $additionalImages, $existingImages);

            $stmt = $this->db->prepare('UPDATE subscription_posters SET title = ?, image_url = ?, category_id = ?, is_active = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$title, $imagePath, $categoryId, $isActive, $sortOrder, $id]);

            $this->savePosterImages($id, $finalImages, true);

            Response::success('Subscription poster updated', [
                'id' => $id,
                'title' => $title,
                'image_url' => $imagePath,
                'images' => $finalImages,
                'category_id' => $categoryId,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to update subscription poster', 500);
        }
    }

    public function delete(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            Response::error('Invalid poster id', 422);
        }

        try {
            $stmt = $this->db->prepare('SELECT image_url FROM subscription_posters WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $poster = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$poster) {
                Response::error('Subscription poster not found', 404);
            }

            $imagesMap = $this->getPosterImages([$id]);
            $allImages = $imagesMap[$id] ?? [];

            if (!empty($poster['image_url']) && $this->isLocalPath($poster['image_url'])) {
                $this->deleteImage($poster['image_url']);
            }

            foreach ($allImages as $url) {
                if ($url !== $poster['image_url'] && $this->isLocalPath($url)) {
                    $this->deleteImage($url);
                }
            }

            $stmt = $this->db->prepare('DELETE FROM subscription_posters WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('Subscription poster deleted');
        } catch (\Exception $e) {
            Response::error('Failed to delete subscription poster', 500);
        }
    }

    public function toggle(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            Response::error('Invalid poster id', 422);
        }

        try {
            $stmt = $this->db->prepare('UPDATE subscription_posters SET is_active = NOT is_active WHERE id = ?');
            $stmt->execute([$id]);
            Response::success('Subscription poster status toggled');
        } catch (\Exception $e) {
            Response::error('Failed to toggle subscription poster status', 500);
        }
    }

    private function uploadImage(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $uploadDir = __DIR__ . '/../uploads/subscription_posters/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('subposter_', true) . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return '/uploads/subscription_posters/' . $filename;
        }

        return null;
    }

    private function deleteImage(?string $path): void
    {
        if (!$path) {
            return;
        }

        $root = dirname(__DIR__);
        $fullPath = $root . $path;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    private function ensureTableExists(): void
    {
        $sqlPosters = <<<SQL
CREATE TABLE IF NOT EXISTS subscription_posters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    category_id INT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscription_posters_active (is_active),
    INDEX idx_subscription_posters_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $sqlImages = <<<SQL
CREATE TABLE IF NOT EXISTS subscription_poster_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poster_id INT NOT NULL,
    url VARCHAR(500) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_subscription_poster_images_poster (poster_id),
    CONSTRAINT fk_subscription_poster_images_poster FOREIGN KEY (poster_id) REFERENCES subscription_posters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $this->db->exec($sqlPosters);
        $this->db->exec($sqlImages);
    }

    private function isLocalPath(string $path): bool
    {
        return !preg_match('/^https?:\/\//i', $path);
    }

    private function attachPosterImages(array $posters): array
    {
        if (empty($posters)) {
            return $posters;
        }

        $ids = array_values(array_filter(array_map(static fn($poster) => $poster['id'] ?? null, $posters)));
        $imagesMap = $this->getPosterImages($ids);

        foreach ($posters as &$poster) {
            $pid = $poster['id'] ?? null;
            $poster['images'] = $pid && isset($imagesMap[$pid]) ? $imagesMap[$pid] : [];
        }
        unset($poster);

        return $posters;
    }

    private function collectAdditionalImages(): array
    {
        $urls = [];
        $uploads = $this->uploadMultipleImages($_FILES['images'] ?? null);
        if (!empty($uploads)) {
            $urls = array_merge($urls, $uploads);
        }

        if (isset($_POST['images'])) {
            $urls = array_merge($urls, $this->extractImageUrlsFromPost($_POST['images']));
        }

        return $this->filterImageUrls($urls);
    }

    private function uploadMultipleImages($files): array
    {
        if (!$files) {
            return [];
        }

        $results = [];
        if (isset($files['error']) && is_array($files['error'])) {
            $count = count($files['error']);
            for ($i = 0; $i < $count; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }

                $single = [
                    'name' => $files['name'][$i] ?? '',
                    'type' => $files['type'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$i] ?? 0,
                ];

                $uploadedPath = $this->uploadImage($single);
                if ($uploadedPath) {
                    $results[] = $uploadedPath;
                }
            }
            return $results;
        }

        $singlePath = $this->uploadImage($files);
        return $singlePath ? [$singlePath] : [];
    }

    private function extractImageUrlsFromPost($value): array
    {
        return $this->normalizeValueToArray($value);
    }

    private function normalizeValueToArray($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $this->filterImageUrls($value);
        }

        $value = trim((string) $value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            return $this->filterImageUrls($decoded);
        }

        $parts = explode(',', $value);
        return $this->filterImageUrls($parts);
    }

    private function filterImageUrls(array $urls): array
    {
        $clean = [];
        foreach ($urls as $url) {
            $trimmed = trim((string) $url);
            if ($trimmed === '') {
                continue;
            }
            if (!in_array($trimmed, $clean, true)) {
                $clean[] = $trimmed;
            }
        }
        return $clean;
    }

    private function buildImageSet(?string $primary, array $additional, array $fallback = []): array
    {
        $result = [];
        if ($primary) {
            $result[] = $primary;
        }

        foreach ($additional as $url) {
            if (!in_array($url, $result, true)) {
                $result[] = $url;
            }
        }

        if (empty($additional) && !empty($fallback)) {
            foreach ($fallback as $url) {
                if (!in_array($url, $result, true)) {
                    $result[] = $url;
                }
            }
        }

        return $result;
    }

    private function savePosterImages(int $posterId, array $urls, bool $replaceExisting = false): void
    {
        $urls = $this->filterImageUrls($urls);

        if ($replaceExisting) {
            $stmt = $this->db->prepare('DELETE FROM subscription_poster_images WHERE poster_id = ?');
            $stmt->execute([$posterId]);
        }

        if (empty($urls)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO subscription_poster_images (poster_id, url, sort_order) VALUES (?, ?, ?)');
        $order = 0;
        foreach ($urls as $url) {
            $stmt->execute([$posterId, $url, $order]);
            $order++;
        }
    }

    private function getPosterImages(array $posterIds): array
    {
        $posterIds = array_values(array_unique(array_filter(array_map('intval', $posterIds))));
        if (empty($posterIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($posterIds), '?'));
        $stmt = $this->db->prepare('SELECT poster_id, url FROM subscription_poster_images WHERE poster_id IN (' . $placeholders . ') ORDER BY sort_order ASC, id ASC');
        $stmt->execute($posterIds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $row) {
            $pid = (int) $row['poster_id'];
            if (!isset($map[$pid])) {
                $map[$pid] = [];
            }
            $map[$pid][] = $row['url'];
        }

        return $map;
    }
}
