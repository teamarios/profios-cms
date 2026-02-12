<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);

define('VIEW_PATH', BASE_PATH . '/views');

define('STORAGE_PATH', BASE_PATH . '/storage');

require_once BASE_PATH . '/src/helpers.php';

loadEnv(BASE_PATH . '/.env');

$config = require BASE_PATH . '/config/app.php';

error_reporting(E_ALL);
ini_set('display_errors', config('debug') ? '1' : '0');

session_name((string) ($config['session_name'] ?? 'profios_cms'));
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = BASE_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

set_exception_handler(static function (Throwable $e): void {
    if (class_exists(\App\Core\Logger::class)) {
        \App\Core\Logger::error($e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
    }
    if (class_exists(\App\Services\SentryService::class)) {
        \App\Services\SentryService::captureException($e);
    }

    http_response_code(500);
    if (config('debug')) {
        echo 'Unhandled exception: ' . e($e->getMessage());
        return;
    }

    echo 'Service temporarily unavailable. Please retry.';
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    if (class_exists(\App\Core\Logger::class)) {
        \App\Core\Logger::error($message . ' at ' . $file . ':' . $line);
    }
    if (class_exists(\App\Services\SentryService::class)) {
        \App\Services\SentryService::captureError($message, $file, $line, $severity);
    }

    return true;
});
