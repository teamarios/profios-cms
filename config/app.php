<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Profios CMS'),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'env' => env('APP_ENV', 'production'),
    'debug' => env('APP_DEBUG', 'false') === 'true',
    'installed' => env('APP_INSTALLED', 'false') === 'true',
    'app_key' => env('APP_KEY', 'change-me'),
    'session_name' => env('SESSION_NAME', 'profios_cms_session'),
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'database' => env('DB_DATABASE', 'profios_cms'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'file'),
        'prefix' => env('CACHE_PREFIX', 'profios_cms_'),
        'frontend_driver' => env('FRONTEND_CACHE_DRIVER', 'varnish'),
        'redis' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => (int) env('REDIS_PORT', '6379'),
            'password' => env('REDIS_PASSWORD', ''),
            'db' => (int) env('REDIS_DB', '0'),
            'timeout' => 1.0,
        ],
    ],
    'cache_path' => STORAGE_PATH . '/cache',
];
