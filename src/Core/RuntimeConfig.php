<?php

namespace App\Core;

use PDO;

final class RuntimeConfig
{
    public static function hydrate(array &$config): void
    {
        if (!(bool) ($config['installed'] ?? false)) {
            return;
        }

        try {
            $db = $config['db'] ?? [];
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $db['host'] ?? '127.0.0.1',
                $db['port'] ?? '3306',
                $db['database'] ?? '',
                $db['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO($dsn, (string) ($db['username'] ?? ''), (string) ($db['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $stmt = $pdo->query('SELECT `key`, `value` FROM app_settings');
            $rows = $stmt->fetchAll();
            if (!$rows) {
                return;
            }

            foreach ($rows as $row) {
                $key = (string) ($row['key'] ?? '');
                $value = (string) ($row['value'] ?? '');
                $config['runtime'][$key] = $value;

                if ($key === 'cache_driver') {
                    $config['cache']['driver'] = $value;
                }
                if ($key === 'frontend_cache_driver') {
                    $config['cache']['frontend_driver'] = $value;
                }
                if ($key === 'redis_host') {
                    $config['cache']['redis']['host'] = $value;
                }
                if ($key === 'redis_port') {
                    $config['cache']['redis']['port'] = (int) $value;
                }
                if ($key === 'redis_db') {
                    $config['cache']['redis']['db'] = (int) $value;
                }
            }
        } catch (\Throwable $e) {
            Logger::error('Runtime config hydrate failed: ' . $e->getMessage());
        }
    }
}
