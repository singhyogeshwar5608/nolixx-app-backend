<?php

namespace App\Controllers;

use App\Config\Database;
use App\Helpers\Response;
use App\Helpers\JWT;
use PDO;

class SettingsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function get(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $stmt = $this->db->query('SELECT * FROM app_settings LIMIT 1');
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$settings) {
                Response::error('Settings not found', 404);
            }

            Response::success('Settings retrieved', $settings);
        } catch (\Exception $e) {
            Response::error('Failed to get settings', 500);
        }
    }

    public function update(): void
    {
        if (!JWT::verify()) {
            Response::error('Unauthorized', 401);
        }

        try {
            $appName = $_POST['appName'] ?? '';
            $maintenanceMode = isset($_POST['maintenanceMode']) ? (bool)$_POST['maintenanceMode'] : false;
            $facebookUrl = $_POST['facebookUrl'] ?? '';
            $twitterUrl = $_POST['twitterUrl'] ?? '';
            $instagramUrl = $_POST['instagramUrl'] ?? '';

            $logo = $this->uploadFile($_FILES['logo'] ?? null, 'settings');
            $splashImage = $this->uploadFile($_FILES['splashImage'] ?? null, 'settings');

            $updates = [
                'app_name = ?',
                'maintenance_mode = ?',
                'facebook_url = ?',
                'twitter_url = ?',
                'instagram_url = ?'
            ];
            $params = [$appName, $maintenanceMode, $facebookUrl, $twitterUrl, $instagramUrl];

            if ($logo) {
                $updates[] = 'logo = ?';
                $params[] = $logo;
            }

            if ($splashImage) {
                $updates[] = 'splash_image = ?';
                $params[] = $splashImage;
            }

            $stmt = $this->db->prepare('UPDATE app_settings SET ' . implode(', ', $updates));
            $stmt->execute($params);

            Response::success('Settings updated successfully');
        } catch (\Exception $e) {
            Response::error('Failed to update settings', 500);
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
}
