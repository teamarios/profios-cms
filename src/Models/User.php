<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public static function updateTwoFactor(int $id, string $secret, int $enabled, string $backupCodesJson): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET totp_secret = :secret, totp_enabled = :enabled, backup_codes_json = :backup_codes_json, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'secret' => $secret,
            'enabled' => $enabled,
            'backup_codes_json' => $backupCodesJson,
            'updated_at' => now(),
        ]);
    }

    public static function updateBackupCodes(int $id, string $backupCodesJson): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('UPDATE users SET backup_codes_json = :backup_codes_json, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'backup_codes_json' => $backupCodesJson,
            'updated_at' => now(),
        ]);
    }
}
