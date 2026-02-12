<?php

namespace App\Core;

final class Cache
{
    private static ?\Redis $redis = null;

    public static function remember(string $key, int $ttl, callable $resolver): string
    {
        $cacheKey = self::key($key);

        if (self::isRedisEnabled()) {
            $redis = self::redis();
            if ($redis instanceof \Redis) {
                $cached = $redis->get($cacheKey);
                if (is_string($cached)) {
                    return $cached;
                }
            }
        }

        $path = self::path($cacheKey);
        $fileCached = self::readFileCache($path);
        if ($fileCached !== null) {
            return $fileCached;
        }

        $content = (string) $resolver();

        if (self::isRedisEnabled()) {
            $redis = self::redis();
            if ($redis instanceof \Redis) {
                $redis->setex($cacheKey, $ttl, $content);
            }
        }

        self::writeFileCache($path, $content, $ttl);

        return $content;
    }

    public static function clearByPrefix(string $prefix): void
    {
        $fullPrefix = self::key($prefix);
        if (self::isRedisEnabled()) {
            $redis = self::redis();
            if ($redis instanceof \Redis) {
                $keys = $redis->keys($fullPrefix . '*');
                if (is_array($keys) && $keys !== []) {
                    $redis->del($keys);
                }
            }
        }

        $cachePath = (string) config('cache_path');
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $fullPrefix);
        $files = glob($cachePath . '/' . $safePrefix . '*.cache');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private static function key(string $key): string
    {
        return (string) config('cache.prefix', 'profios_cms_') . $key;
    }

    private static function readFileCache(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $payload = json_decode($raw, true);
        if (
            is_array($payload)
            && isset($payload['expires_at'], $payload['content'])
            && (int) $payload['expires_at'] >= time()
        ) {
            return (string) $payload['content'];
        }

        return null;
    }

    private static function writeFileCache(string $path, string $content, int $ttl): void
    {
        file_put_contents($path, json_encode([
            'expires_at' => time() + $ttl,
            'content' => $content,
        ], JSON_UNESCAPED_UNICODE));
    }

    private static function isRedisEnabled(): bool
    {
        return config('cache.driver', 'file') === 'redis';
    }

    private static function redis(): ?\Redis
    {
        if (!class_exists(\Redis::class)) {
            return null;
        }

        if (self::$redis instanceof \Redis) {
            return self::$redis;
        }

        $host = (string) config('cache.redis.host', '127.0.0.1');
        $port = (int) config('cache.redis.port', 6379);
        $timeout = (float) config('cache.redis.timeout', 1.0);
        $password = (string) config('cache.redis.password', '');
        $db = (int) config('cache.redis.db', 0);

        try {
            $redis = new \Redis();
            $redis->connect($host, $port, $timeout);
            if ($password !== '') {
                $redis->auth($password);
            }
            $redis->select($db);
            self::$redis = $redis;
            return self::$redis;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function redisHealth(): array
    {
        if (!self::isRedisEnabled()) {
            return [
                'configured' => false,
                'connected' => false,
                'message' => 'Redis cache driver is not enabled.',
            ];
        }

        $redis = self::redis();
        if (!($redis instanceof \Redis)) {
            return [
                'configured' => true,
                'connected' => false,
                'message' => 'Unable to connect to Redis.',
            ];
        }

        try {
            $pong = $redis->ping();
            $ok = is_string($pong) && stripos($pong, 'PONG') !== false;
            return [
                'configured' => true,
                'connected' => $ok,
                'message' => $ok ? 'Redis reachable.' : 'Redis ping failed.',
            ];
        } catch (\Throwable) {
            return [
                'configured' => true,
                'connected' => false,
                'message' => 'Redis ping failed.',
            ];
        }
    }

    private static function path(string $key): string
    {
        $cachePath = (string) config('cache_path');
        if (!is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        return $cachePath . '/' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $key) . '.cache';
    }
}
