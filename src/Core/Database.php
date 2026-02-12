<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) config('db.host');
        $port = (string) config('db.port');
        $database = (string) config('db.database');
        $username = (string) config('db.username');
        $password = (string) config('db.password');
        $charset = (string) config('db.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $attempts = 0;
        $maxAttempts = 3;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            try {
                self::$pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                return self::$pdo;
            } catch (PDOException $e) {
                $lastException = $e;
                $attempts++;
                usleep(200000);
            }
        }

        throw new RuntimeException('Database connection failed.', 0, $lastException);
    }
}
