<?php

declare(strict_types=1);

function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}

function writeEnvFile(string $path, array $values): void
{
    $lines = [];
    foreach ($values as $key => $value) {
        $safe = str_replace('"', '\"', (string) $value);
        $lines[] = $key . '="' . $safe . '"';
    }

    $content = implode(PHP_EOL, $lines) . PHP_EOL;
    file_put_contents($path, $content, LOCK_EX);
}

function generateAppKey(): string
{
    return bin2hex(random_bytes(32));
}

function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);
    if ($value !== false) {
        return $value;
    }

    return $default;
}

function config(string $key, mixed $default = null): mixed
{
    global $config;

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function appUrl(string $path = ''): string
{
    $base = rtrim((string) config('url', ''), '/');
    $path = '/' . ltrim($path, '/');
    return $base . ($path === '/' ? '' : $path);
}

function clientIp(): string
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $value = (string) $_SERVER[$key];
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }
            return $value;
        }
    }

    return '0.0.0.0';
}

function setting(string $key, ?string $default = null): ?string
{
    $runtime = config('runtime', []);
    if (is_array($runtime) && array_key_exists($key, $runtime)) {
        return (string) $runtime[$key];
    }

    return $default;
}
