<?php

namespace App\Services;

final class SentryService
{
    public static function captureException(\Throwable $e): void
    {
        self::capture([
            'level' => 'error',
            'message' => $e->getMessage(),
            'exception' => [
                'values' => [[
                    'type' => get_class($e),
                    'value' => $e->getMessage(),
                    'stacktrace' => [
                        'frames' => self::formatTrace($e->getTrace()),
                    ],
                ]],
            ],
            'extra' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        ]);
    }

    public static function captureError(string $message, string $file, int $line, int $severity): void
    {
        self::capture([
            'level' => 'error',
            'message' => $message,
            'logger' => 'php-error-handler',
            'extra' => [
                'file' => $file,
                'line' => $line,
                'severity' => $severity,
            ],
        ]);
    }

    private static function capture(array $payload): void
    {
        $dsn = trim((string) setting('sentry_dsn', env('SENTRY_DSN', '')));
        if ($dsn === '') {
            return;
        }

        $parsed = self::parseDsn($dsn);
        if ($parsed === null) {
            return;
        }

        $eventId = bin2hex(random_bytes(16));
        $event = array_merge([
            'event_id' => $eventId,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'platform' => 'php',
            'environment' => setting('sentry_environment', env('APP_ENV', 'production')),
            'release' => setting('sentry_release', (string) config('release', '')),
            'server_name' => gethostname() ?: 'unknown-host',
            'request' => self::requestContext(),
        ], $payload);

        $envelopeHeader = [
            'event_id' => $eventId,
            'dsn' => $dsn,
            'sent_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $itemHeader = ['type' => 'event'];

        $body = json_encode($envelopeHeader, JSON_UNESCAPED_SLASHES) . "\n"
            . json_encode($itemHeader, JSON_UNESCAPED_SLASHES) . "\n"
            . json_encode($event, JSON_UNESCAPED_SLASHES);

        if (!is_string($body) || $body === '') {
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-sentry-envelope\r\n",
                'content' => $body,
                'timeout' => 1.5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($parsed['ingest_url'], false, $context);
    }

    private static function parseDsn(string $dsn): ?array
    {
        $parts = parse_url($dsn);
        if (!is_array($parts)) {
            return null;
        }

        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if ($scheme === '' || $host === '' || $path === '') {
            return null;
        }

        $segments = explode('/', $path);
        $projectId = end($segments);
        if ($projectId === false || $projectId === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $basePath = '';
        if (count($segments) > 1) {
            $basePath = '/' . implode('/', array_slice($segments, 0, -1));
        }

        return [
            'ingest_url' => $scheme . '://' . $host . $port . $basePath . '/api/' . $projectId . '/envelope/',
        ];
    }

    private static function requestContext(): array
    {
        return [
            'url' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            'headers' => [
                'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''),
                'user-agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ],
        ];
    }

    private static function formatTrace(array $trace): array
    {
        $frames = [];
        foreach ($trace as $t) {
            $frames[] = [
                'filename' => (string) ($t['file'] ?? ''),
                'function' => (string) ($t['function'] ?? ''),
                'lineno' => (int) ($t['line'] ?? 0),
            ];
        }

        return $frames;
    }
}
