<?php

namespace App\Core;

use PDO;
use App\Models\User;
use App\Services\BackupCodeService;
use App\Services\TotpService;

final class Auth
{
    public static function credentials(string $email, string $password): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        return $user;
    }

    public static function requiresTotp(array $user): bool
    {
        return isset($user['totp_enabled']) && (int) $user['totp_enabled'] === 1 && !empty($user['totp_secret']);
    }

    public static function verifyTotpOrBackup(array $user, string $otp): bool
    {
        if (!self::requiresTotp($user)) {
            return true;
        }

        if (TotpService::verify((string) $user['totp_secret'], $otp)) {
            return true;
        }

        $backupCodes = json_decode((string) ($user['backup_codes_json'] ?? '[]'), true);
        if (!is_array($backupCodes) || $backupCodes === []) {
            return false;
        }

        $result = BackupCodeService::consume($otp, $backupCodes);
        if (($result['matched'] ?? false) === true) {
            User::updateBackupCodes((int) $user['id'], json_encode($result['remaining'] ?? [], JSON_UNESCAPED_UNICODE));
            return true;
        }

        return false;
    }

    public static function login(array $user): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'] ?? 'admin',
        ];
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/admin/login');
        }
    }
}
