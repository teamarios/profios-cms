<?php

namespace App\Services;

use App\Core\Cache;
use App\Core\Database;

final class HealthService
{
    public static function status(): array
    {
        $checks = [
            'app' => [
                'ok' => true,
                'message' => 'Application running.',
            ],
            'database' => self::databaseCheck(),
            'redis' => self::redisCheck(),
        ];

        $healthy = true;
        foreach ($checks as $check) {
            if (($check['ok'] ?? false) !== true) {
                $healthy = false;
                break;
            }
        }

        return [
            'ok' => $healthy,
            'checks' => $checks,
            'timestamp' => gmdate('c'),
        ];
    }

    private static function databaseCheck(): array
    {
        try {
            $pdo = Database::connection();
            $stmt = $pdo->query('SELECT 1');
            $ok = $stmt !== false;
            return [
                'ok' => $ok,
                'message' => $ok ? 'Database reachable.' : 'Database ping failed.',
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
        }
    }

    private static function redisCheck(): array
    {
        $status = Cache::redisHealth();
        if (($status['configured'] ?? false) !== true) {
            return [
                'ok' => true,
                'message' => (string) ($status['message'] ?? 'Redis not configured.'),
            ];
        }

        return [
            'ok' => (bool) ($status['connected'] ?? false),
            'message' => (string) ($status['message'] ?? ''),
        ];
    }
}
