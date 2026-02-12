<?php

namespace App\Core;

final class RateLimiter
{
    public static function hit(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $path = self::path($key);
        $now = time();

        $payload = ['count' => 0, 'expires_at' => $now + $decaySeconds];
        if (is_file($path)) {
            $raw = file_get_contents($path);
            if ($raw !== false) {
                $existing = json_decode($raw, true);
                if (is_array($existing) && isset($existing['count'], $existing['expires_at'])) {
                    $payload = $existing;
                }
            }
        }

        if ((int) $payload['expires_at'] < $now) {
            $payload = ['count' => 0, 'expires_at' => $now + $decaySeconds];
        }

        $payload['count'] = (int) $payload['count'] + 1;
        file_put_contents($path, json_encode($payload), LOCK_EX);

        return (int) $payload['count'] <= $maxAttempts;
    }

    public static function clear(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function path(string $key): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return STORAGE_PATH . '/cache/rate_' . $safe . '.json';
    }
}
