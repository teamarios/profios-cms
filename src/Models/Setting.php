<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use PDO;

final class Setting
{
    public static function set(string $key, string $value): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO app_settings (`key`, `value`, updated_at) VALUES (:key, :value, :updated_at) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)');
            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Setting::set failed: ' . $e->getMessage());
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('SELECT `value` FROM app_settings WHERE `key` = :key LIMIT 1');
            $stmt->execute(['key' => $key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['value'] ?? $default;
        } catch (\Throwable $e) {
            Logger::error('Setting::get failed: ' . $e->getMessage());
            return $default;
        }
    }

    public static function all(): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query('SELECT `key`, `value` FROM app_settings');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $out = [];

            foreach ($rows as $row) {
                if (isset($row['key'], $row['value'])) {
                    $out[(string) $row['key']] = (string) $row['value'];
                }
            }

            return $out;
        } catch (\Throwable $e) {
            Logger::error('Setting::all failed: ' . $e->getMessage());
            return [];
        }
    }

    public static function upsertMany(array $data): void
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->prepare('INSERT INTO app_settings (`key`, `value`, updated_at)
                VALUES (:key, :value, :updated_at)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)');

            foreach ($data as $key => $value) {
                $stmt->execute([
                    'key' => (string) $key,
                    'value' => (string) $value,
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Logger::error('Setting::upsertMany failed: ' . $e->getMessage());
        }
    }
}
