<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use PDO;

class TemplatesController
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
            $category = $_GET['category'] ?? '';
            $subscription = $_GET['subscription'] ?? '';
            $limit = 12;
            $offset = ($page - 1) * $limit;

            $where = [];
            $params = [];

            if ($search) {
                $where[] = 't.title LIKE ?';
                $params[] = "%$search%";
            }

            if ($category) {
                $where[] = 't.category_id = ?';
                $params[] = $category;
            }

            if ($subscription) {
                $where[] = 't.subscription_id = ?';
                $params[] = $subscription;
            }

            $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

            $stmt = $this->db->prepare("
                SELECT t.*, c.name as category, s.name as subscriptionType 
                FROM templates t
                LEFT JOIN categories c ON t.category_id = c.id
                LEFT JOIN subscriptions s ON t.subscription_id = s.id
                $whereClause
                ORDER BY t.created_at DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($params);
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $templateIds = array_values(array_filter(array_map(static fn($t) => $t['id'] ?? null, $templates)));
            $imagesByTemplate = $this->getImagesForTemplates($templateIds);
            foreach ($templates as &$t) {
                $id = $t['id'] ?? null;
                $t['images'] = $id && isset($imagesByTemplate[$id]) ? $imagesByTemplate[$id] : [];
            }
            unset($t);

            $countStmt = $this->db->prepare("SELECT COUNT(*) as count FROM templates t $whereClause");
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

            Response::success('Templates retrieved', [
                'templates' => $templates,
                'totalPages' => ceil($total / $limit)
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to get templates: ' . $e->getMessage(), 500);
        }
    }

    public function listForApp(): void
    {
        try {
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
            if ($limit <= 0 || $limit > 100) {
                $limit = 50;
            }

            $stmt = $this->db->prepare('
                SELECT t.id, t.title, t.description, t.thumbnail, t.main_image, t.tags, t.created_at, c.name as category_name
                FROM templates t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.status = "published"
                ORDER BY t.created_at DESC
                LIMIT ?
            ');
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $templateIds = array_values(array_filter(array_map(static fn($t) => $t['id'] ?? null, $templates)));
            $imagesByTemplate = $this->getImagesForTemplates($templateIds);
            foreach ($templates as &$t) {
                $id = $t['id'] ?? null;
                $t['images'] = $id && isset($imagesByTemplate[$id]) ? $imagesByTemplate[$id] : [];
            }
            unset($t);

            Response::success('Templates fetched', $templates);
        } catch (\Exception $e) {
            Response::error('Failed to fetch templates: ' . $e->getMessage(), 500);
        }
    }

    public function listForAppByCategory(): void
    {
        try {
            $categoryId = isset($_GET['categoryId']) ? (int) $_GET['categoryId'] : null;
            $categoryName = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;
            if ($limit <= 0 || $limit > 200) {
                $limit = 100;
            }

            if (!$categoryId && $categoryName === '') {
                Response::error('categoryId or name is required', 422);
            }

            $sql = '
                SELECT t.id, t.title, t.description, t.thumbnail, t.main_image, t.tags, t.created_at, c.name as category_name
                FROM templates t
                LEFT JOIN categories c ON t.category_id = c.id
                WHERE t.status = "published"
            ';
            $params = [];
            if ($categoryId) {
                $sql .= ' AND t.category_id = ?';
                $params[] = $categoryId;
            } else {
                $sql .= ' AND LOWER(TRIM(c.name)) = ?';
                $params[] = strtolower($categoryName);
            }
            $sql .= ' ORDER BY t.created_at DESC LIMIT ?';
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            foreach ($params as $index => $value) {
                $paramIndex = $index + 1;
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($paramIndex, $value, $type);
            }
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $templateIds = array_values(array_filter(array_map(static fn($t) => $t['id'] ?? null, $templates)));
            $imagesByTemplate = $this->getImagesForTemplates($templateIds);
            foreach ($templates as &$t) {
                $id = $t['id'] ?? null;
                $t['images'] = $id && isset($imagesByTemplate[$id]) ? $imagesByTemplate[$id] : [];
            }
            unset($t);

            Response::success('Templates fetched', $templates);
        } catch (\Exception $e) {
            Response::error('Failed to fetch templates by category: ' . $e->getMessage(), 500);
        }
    }

    public function getById(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('SELECT * FROM templates WHERE id = ?');
            $stmt->execute([$id]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                Response::error('Template not found', 404);
            }

            $imagesByTemplate = $this->getImagesForTemplates([(int)$template['id']]);
            $template['images'] = $imagesByTemplate[(int)$template['id']] ?? [];

            Response::success('Template retrieved', $template);
        } catch (\Exception $e) {
            Response::error('Failed to get template: ' . $e->getMessage(), 500);
        }
    }

    public function create(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $categoryId = $_POST['categoryId'] ?? null;
            $subscriptionId = $_POST['subscriptionId'] ?? null;
            $tags = $_POST['tags'] ?? '';
            $isFeatured = isset($_POST['isFeatured']) ? (bool)$_POST['isFeatured'] : false;
            $status = $_POST['status'] ?? 'published';

            $thumbnail = $this->uploadFile($_FILES['thumbnail'] ?? null, 'thumbnails');
            if (!$thumbnail) {
                $thumbnail = $_POST['thumbnail'] ?? null;
            }

            $newThumbnails = $this->uploadFiles($_FILES['thumbnails'] ?? null, 'thumbnails');
            if (empty($newThumbnails)) {
                $newThumbnails = $this->extractUrlsFromPost('thumbnails');
            }

            $thumbnails = $this->uploadFiles($_FILES['thumbnails'] ?? null, 'thumbnails');
            if (empty($thumbnails)) {
                $thumbnails = $this->extractUrlsFromPost('thumbnails');
            }

            if (!$thumbnail && !empty($thumbnails)) {
                $thumbnail = $thumbnails[0];
            }

            $mainImage = $this->uploadFile($_FILES['mainImage'] ?? null, 'images');
            if (!$mainImage) {
                $mainImage = $_POST['mainImage'] ?? $_POST['main_image'] ?? null;
            }

            $stmt = $this->db->prepare('
                INSERT INTO templates (title, description, thumbnail, main_image, category_id, subscription_id, tags, is_featured, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$title, $description, $thumbnail, $mainImage, $categoryId, $subscriptionId, $tags, $isFeatured, $status]);

            $templateId = (int)$this->db->lastInsertId();
            if (!empty($thumbnails)) {
                $this->saveTemplateImages($templateId, $thumbnails);
            }

            Response::success('Template created successfully', ['id' => $templateId]);
        } catch (\Exception $e) {
            Response::error('Failed to create template: ' . $e->getMessage(), 500);
        }
    }

    public function update(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $categoryId = $_POST['categoryId'] ?? null;
            $subscriptionId = $_POST['subscriptionId'] ?? null;
            $tags = $_POST['tags'] ?? '';
            $isFeatured = isset($_POST['isFeatured']) ? (bool)$_POST['isFeatured'] : false;
            $status = $_POST['status'] ?? 'published';

            $thumbnail = $this->uploadFile($_FILES['thumbnail'] ?? null, 'thumbnails');
            if (!$thumbnail) {
                $thumbnail = $_POST['thumbnail'] ?? null;
            }

            $mainImage = $this->uploadFile($_FILES['mainImage'] ?? null, 'images');
            if (!$mainImage) {
                $mainImage = $_POST['mainImage'] ?? $_POST['main_image'] ?? null;
            }

            $updates = ['title = ?', 'description = ?', 'category_id = ?', 'subscription_id = ?', 'tags = ?', 'is_featured = ?', 'status = ?'];
            $params = [$title, $description, $categoryId, $subscriptionId, $tags, $isFeatured, $status];

            if ($thumbnail) {
                $updates[] = 'thumbnail = ?';
                $params[] = $thumbnail;
            }

            if ($mainImage) {
                $updates[] = 'main_image = ?';
                $params[] = $mainImage;
            }

            $params[] = $id;

            $stmt = $this->db->prepare('UPDATE templates SET ' . implode(', ', $updates) . ' WHERE id = ?');
            $stmt->execute($params);

            if (!empty($newThumbnails)) {
                $this->saveTemplateImages((int)$id, $newThumbnails);
            }

            Response::success('Template updated successfully');
        } catch (\Exception $e) {
            Response::error('Failed to update template', 500);
        }
    }

    public function delete(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        $id = $_GET['id'] ?? 0;

        try {
            $stmt = $this->db->prepare('DELETE FROM template_images WHERE template_id = ?');
            $stmt->execute([$id]);

            $stmt = $this->db->prepare('DELETE FROM templates WHERE id = ?');
            $stmt->execute([$id]);

            Response::success('Template deleted successfully');
        } catch (\Exception $e) {
            Response::error('Failed to delete template', 500);
        }
    }

    private function uploadFile($file, $folder): ?string
    {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $uploadDir = __DIR__ . "/../uploads/$folder/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $filepath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return "/uploads/$folder/$filename";
        }

        return null;
    }

    private function uploadFiles($files, string $folder): array
    {
        if (!$files) {
            return [];
        }

        if (isset($files['error']) && is_array($files['error'])) {
            $results = [];
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
                $url = $this->uploadFile($single, $folder);
                if ($url) {
                    $results[] = $url;
                }
            }
            return $results;
        }

        $singleUrl = $this->uploadFile($files, $folder);
        return $singleUrl ? [$singleUrl] : [];
    }

    private function extractUrlsFromPost(string $key): array
    {
        if (!isset($_POST[$key])) {
            return [];
        }

        $raw = $_POST[$key];
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw), static fn($v) => $v !== ''));
        }

        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), static fn($v) => $v !== ''));
        }

        $parts = array_map('trim', explode(',', $raw));
        return array_values(array_filter($parts, static fn($v) => $v !== ''));
    }

    private function saveTemplateImages(int $templateId, array $urls): void
    {
        $urls = array_values(array_filter(array_map('trim', $urls), static fn($v) => $v !== ''));
        if (empty($urls)) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO template_images (template_id, url, sort_order) VALUES (?, ?, ?)');
        $order = 0;
        foreach ($urls as $url) {
            $stmt->execute([$templateId, $url, $order]);
            $order++;
        }
    }

    private function getImagesForTemplates(array $templateIds): array
    {
        $templateIds = array_values(array_unique(array_map('intval', array_filter($templateIds, static fn($v) => $v !== null && $v !== ''))));
        if (empty($templateIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($templateIds), '?'));
        $stmt = $this->db->prepare('SELECT template_id, url FROM template_images WHERE template_id IN (' . $placeholders . ') ORDER BY sort_order ASC, id ASC');
        $stmt->execute($templateIds);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $tid = (int)$row['template_id'];
            if (!isset($out[$tid])) {
                $out[$tid] = [];
            }
            $out[$tid][] = $row['url'];
        }

        return $out;
    }
}
