<?php

namespace App\Controllers;

use App\Config\Database;
use App\Config\MailConfig;
use App\Helpers\JWT;
use App\Helpers\Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PDO;

class AuthController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function sendOtp(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = isset($input['email']) ? trim($input['email']) : '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email format', 400);
            }

            $this->issueOtpForEmail($email);

            Response::success('OTP sent successfully to your email');
        } catch (\Exception $e) {
            Response::error('Failed to send OTP. Please try again.', 500);
        }
    }

    public function checkExistingUser(): void
    {
        $input      = json_decode(file_get_contents('php://input'), true);
        $identifier = isset($input['identifier']) ? trim((string) $input['identifier']) : '';
        if ($identifier === '') {
            $identifier = isset($input['email']) ? trim((string) $input['email']) : '';
        }
        if ($identifier === '') {
            $identifier = isset($input['phone']) ? trim((string) $input['phone']) : '';
        }

        if ($identifier === '') {
            Response::error('Email or phone is required', 400);
        }

        try {
            $user = $this->findUserByIdentifier($identifier);

            if (!$user) {
                Response::error('No account found with that email or phone number', 404);
            }

            Response::success('User found', [
                'user' => [
                    'id'            => $user['id'],
                    'name'          => $user['name'],
                    'email'         => $user['email'],
                    'business_name' => $user['business_name'],
                    'phone'         => $user['phone'],
                    'avatar_url'    => $user['avatar_url'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to check user', 500);
        }
    }

    public function loginSendOtp(): void
    {
        try {
            $input      = json_decode(file_get_contents('php://input'), true);
            $identifier = isset($input['identifier']) ? trim((string) $input['identifier']) : '';
            if ($identifier === '') {
                $identifier = isset($input['email']) ? trim((string) $input['email']) : '';
            }
            if ($identifier === '') {
                $identifier = isset($input['phone']) ? trim((string) $input['phone']) : '';
            }

            if ($identifier === '') {
                Response::error('Email or phone is required', 400);
            }

            $user = $this->findUserByIdentifier($identifier);

            if (!$user) {
                Response::error('No account found with that email or phone number', 404);
            }

            $this->issueOtpForEmail($user['email']);

            Response::success('OTP sent to your registered email address', [
                'email' => $user['email'],
                'user'  => [
                    'id'            => $user['id'],
                    'name'          => $user['name'],
                    'business_name' => $user['business_name'],
                    'phone'         => $user['phone'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to send OTP. Please try again.', 500);
        }
    }

    public function verifyOtp(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = isset($input['email']) ? trim($input['email']) : '';
            $otp   = isset($input['otp']) ? trim((string) $input['otp']) : '';

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Invalid email format', 400);
            }

            if ($otp === '') {
                Response::error('OTP is required', 400);
            }

            $record = $this->validateOtpOrFail($email, $otp);
            $this->markOtpAsVerified((int) $record['id']);
            $this->markEmailVerified($email);
            $this->clearPendingOtps($email);

            Response::success('OTP verified successfully', ['email' => $email]);
        } catch (\Exception $e) {
            Response::error('Failed to verify OTP. Please try again.', 500);
        }
    }

    public function loginVerifyOtp(): void
    {
        try {
            $input      = json_decode(file_get_contents('php://input'), true);
            $identifier = isset($input['identifier']) ? trim((string) $input['identifier']) : '';
            if ($identifier === '') {
                $identifier = isset($input['email']) ? trim((string) $input['email']) : '';
            }
            if ($identifier === '') {
                $identifier = isset($input['phone']) ? trim((string) $input['phone']) : '';
            }
            $otp = isset($input['otp']) ? trim((string) $input['otp']) : '';

            if ($identifier === '') {
                Response::error('Email or phone is required', 400);
            }
            if ($otp === '') {
                Response::error('OTP is required', 400);
            }

            $user = $this->findUserByIdentifier($identifier);

            if (!$user) {
                Response::error('No account found with that email or phone number', 404);
            }

            $record = $this->validateOtpOrFail($user['email'], $otp);
            $this->markOtpAsVerified((int) $record['id']);
            $this->markEmailVerified($user['email']);
            $this->clearPendingOtps($user['email']);

            $token = JWT::encode([
                'user_id' => $user['id'],
                'email'   => $user['email'],
                'name'    => $user['name'],
            ]);

            Response::success('Login successful', [
                'token' => $token,
                'user'  => [
                    'id'            => $user['id'],
                    'name'          => $user['name'],
                    'email'         => $user['email'],
                    'business_name' => $user['business_name'],
                    'phone'         => $user['phone'],
                    'avatar_url'    => $user['avatar_url'],
                ],
            ]);
        } catch (\Exception $e) {
            Response::error('Failed to verify OTP. Please try again.', 500);
        }
    }

    public function googleLogin(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!is_array($input)) {
                Response::error('Invalid JSON body.', 400);
            }

            $idToken = $input['id_token'] ?? null;
            if (!$idToken) {
                Response::error('Missing id_token.', 400);
            }

            $googleInfo = $this->verifyGoogleIdToken($idToken);
            if ($googleInfo === null) {
                Response::error('Invalid Google ID token.', 401);
            }

            $googleSub  = $googleInfo['sub'] ?? null;
            $email      = $googleInfo['email'] ?? null;
            $emailVerif = $googleInfo['email_verified'] ?? 'false';
            $name       = $googleInfo['name'] ?? '';
            $picture    = $googleInfo['picture'] ?? null;
            $aud        = $googleInfo['aud'] ?? null;
            $iss        = $googleInfo['iss'] ?? null;

            $clientId = getenv('GOOGLE_WEB_CLIENT_ID');
            if (!$clientId && isset($_ENV['GOOGLE_WEB_CLIENT_ID'])) {
                $clientId = $_ENV['GOOGLE_WEB_CLIENT_ID'];
            }
            $clientId = trim((string) $clientId);

            if ($clientId === '') {
                Response::error('GOOGLE_WEB_CLIENT_ID is missing on server.', 500);
            }

            if (!$googleSub || !$email) {
                Response::error('Google token missing required fields.', 401);
            }

            if ($aud !== $clientId) {
                Response::error('Google token audience mismatch.', 401);
            }

            if (!in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true)) {
                Response::error('Google token issuer is invalid.', 401);
            }

            if ($emailVerif !== true && $emailVerif !== 'true') {
                Response::error('Google email is not verified.', 401);
            }

            $pdo = $this->db;
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('SELECT * FROM users WHERE google_sub = :sub LIMIT 1');
            $stmt->execute([':sub' => $googleSub]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $now = date('Y-m-d H:i:s');

            if ($user) {
                $profileComplete = (int) ($user['profile_complete'] ?? 0);
                if (empty($user['business_name']) || empty($user['phone'])) {
                    $profileComplete = 0;
                }

                $avatarUrl = $picture ?: ($user['avatar_url'] ?? null);

                $update = $pdo->prepare(
                    'UPDATE users
                     SET google_sub      = :sub,
                         provider        = :provider,
                         avatar_url      = :avatar_url,
                         profile_complete= :profile_complete,
                         email_verified  = :email_verified,
                         last_login_at   = :last_login_at
                     WHERE id = :id'
                );
                $update->execute([
                    ':sub'              => $googleSub,
                    ':provider'         => 'google',
                    ':avatar_url'       => $avatarUrl,
                    ':profile_complete' => $profileComplete,
                    ':email_verified'   => 1,
                    ':last_login_at'    => $now,
                    ':id'               => $user['id'],
                ]);

                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $user['id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO users (
                         google_sub,
                         provider,
                         name,
                         email,
                         business_name,
                         phone,
                         avatar_url,
                         avatar_public_id,
                         profile_complete,
                         email_verified,
                         created_at,
                         updated_at,
                         last_login_at
                     ) VALUES (
                         :sub,
                         :provider,
                         :name,
                         :email,
                         :business_name,
                         :phone,
                         :avatar_url,
                         :avatar_public_id,
                         :profile_complete,
                         :email_verified,
                         :created_at,
                         :updated_at,
                         :last_login_at
                     )'
                );
                $insert->execute([
                    ':sub'              => $googleSub,
                    ':provider'         => 'google',
                    ':name'             => $name ?: '',
                    ':email'            => $email,
                    ':business_name'    => '',
                    ':phone'            => null,
                    ':avatar_url'       => $picture,
                    ':avatar_public_id' => null,
                    ':profile_complete' => 0,
                    ':email_verified'   => 1,
                    ':created_at'       => $now,
                    ':updated_at'       => $now,
                    ':last_login_at'    => $now,
                ]);

                $userId = (int) $pdo->lastInsertId();
                $stmt   = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $pdo->commit();

            $this->markEmailVerified($user['email']);
            $this->clearPendingOtps($user['email']);

            if (!$user) {
                Response::error('Unable to load Google user after login.', 500);
            }

            $token = JWT::encode([
                'user_id' => $user['id'],
                'email'   => $user['email'],
                'name'    => $user['name'],
            ]);

            $responseUser = [
                'id'               => (int) $user['id'],
                'name'             => $user['name'] ?? '',
                'email'            => $user['email'] ?? '',
                'business_name'    => $user['business_name'] ?? '',
                'phone'            => $user['phone'] ?? '',
                'avatar_url'       => $user['avatar_url'] ?? '',
                'profile_complete' => (bool) $user['profile_complete'],
                'provider'         => $user['provider'] ?? 'google',
                'email_verified'   => true,
            ];

            Response::success('Login successful', [
                'token' => $token,
                'jwt'   => $token,
                'user'  => $responseUser,
            ]);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            Response::error(
                'Google debug: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
                500
            );
        }
    }

    private function verifyGoogleIdToken(string $idToken): ?array
    {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $result = curl_exec($ch);

        if ($result === false) {
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return null;
        }

        $data = json_decode($result, true);
        if (!is_array($data) || empty($data['sub'])) {
            return null;
        }

        return $data;
    }

    private function issueOtpForEmail(string $email): void
    {
        $otp       = sprintf('%06d', random_int(100000, 999999));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $stmt = $this->db->prepare('DELETE FROM email_otps WHERE email = ? AND is_verified = 0');
        $stmt->execute([$email]);

        $stmt = $this->db->prepare('INSERT INTO email_otps (email, otp, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$email, $otp, $expiresAt]);

        $this->sendEmail($email, $otp);
    }

    private function validateOtpOrFail(string $email, string $otp): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM email_otps
             WHERE email = ? AND otp = ? AND is_verified = 0
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([$email, $otp]);
        $record = $stmt->fetch();

        if (!$record) {
            Response::error('Invalid OTP', 400);
        }

        if (strtotime($record['expires_at']) < time()) {
            Response::error('OTP has expired', 400);
        }

        return $record;
    }

    private function markOtpAsVerified(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE email_otps SET is_verified = 1 WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function markEmailVerified(string $email): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email_verified = 1 WHERE email = ?');
        $stmt->execute([$email]);
    }

    private function clearPendingOtps(string $email): void
    {
        $stmt = $this->db->prepare('DELETE FROM email_otps WHERE email = ?');
        $stmt->execute([$email]);
    }

    private function findUserByIdentifier(string $identifier): ?array
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$identifier]);
            $user = $stmt->fetch();
            return $user ?: null;
        }

        $stmt = $this->db->prepare('SELECT * FROM users WHERE phone = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $user = $stmt->fetch();

        if ($user) {
            return $user;
        }

        $normalized = $this->normalizePhone($identifier);

        if ($normalized !== '' && $normalized !== $identifier) {
            $stmt = $this->db->prepare(
                "SELECT * FROM users
                 WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = ?
                 LIMIT 1"
            );
            $stmt->execute([$normalized]);
            $user = $stmt->fetch();

            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }

    private function sendEmail(string $email, string $otp): void
    {
        $config = MailConfig::settings();
        $mail   = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $config['host'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $config['username'];
            $mail->Password   = $config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $config['port'];

            $mail->setFrom($config['from_email'], $config['from_name']);
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Your OTP Code';
            $mail->Body    = "
                <html>
                <body style='font-family: Arial, sans-serif;'>
                    <h2>Your OTP Code</h2>
                    <p>Your OTP code is: <strong style='font-size: 24px; color: #5E60CE;'>{$otp}</strong></p>
                    <p>This code will expire in 5 minutes.</p>
                    <p>If you did not request this code, please ignore this email.</p>
                </body>
                </html>
            ";

            $mail->send();
        } catch (Exception $e) {
            throw new \Exception('Email could not be sent: ' . $mail->ErrorInfo);
        }
    }
}