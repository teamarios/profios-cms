<?php

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function input(): string
    {
        $token = self::token();
        return '<input type="hidden" name="_csrf" value="' . e($token) . '">';
    }

    public static function validate(?string $token): bool
    {
        return isset($_SESSION['_csrf']) && is_string($token) && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function verifyOrFail(): void
    {
        if (!self::validate($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'Invalid CSRF token.';
            exit;
        }
    }
}
