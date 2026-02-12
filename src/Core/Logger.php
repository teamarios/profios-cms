<?php

namespace App\Core;

final class Logger
{
    public static function error(string $message): void
    {
        $path = STORAGE_PATH . '/logs/app.log';
        $line = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL;
        file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }
}
