<?php

namespace App\Services;

use App\Core\Cache;

final class OpsStatusService
{
    public static function checks(): array
    {
        return [
            'search_console' => 'Search Console Token',
            'ga4' => 'GA4 Measurement',
            'sgtm' => 'Server-side GTM',
            'sentry' => 'Sentry Monitoring',
            'cdn_waf' => 'CDN + WAF',
            'ssl_hsts_http2' => 'SSL + HSTS + HTTP/2 readiness',
            'ssl_renewal' => 'Cert auto-renew readiness',
            'opcache' => 'PHP OPcache',
            'php_fpm_pool' => 'PHP-FPM pool tuning',
            'compression' => 'Brotli/Gzip readiness',
            'image_pipeline' => 'Image pipeline',
            'backup_automation' => 'Automated backups',
            'monitoring_alerts' => 'Monitoring + alerts',
            'log_aggregation' => 'Log retention/aggregation',
            'database_health' => 'MySQL health',
            'redis_health' => 'Redis health',
        ];
    }

    public static function snapshot(): array
    {
        $out = [];
        foreach (array_keys(self::checks()) as $id) {
            $out[$id] = self::run($id);
        }

        return $out;
    }

    public static function run(string $id): array
    {
        return match ($id) {
            'search_console' => self::searchConsole(),
            'ga4' => self::ga4(),
            'sgtm' => self::sgtm(),
            'sentry' => self::sentry(),
            'cdn_waf' => self::cdnWaf(),
            'ssl_hsts_http2' => self::sslHstsHttp2(),
            'ssl_renewal' => self::sslRenewal(),
            'opcache' => self::opcache(),
            'php_fpm_pool' => self::phpFpmPool(),
            'compression' => self::compression(),
            'image_pipeline' => self::imagePipeline(),
            'backup_automation' => self::backupAutomation(),
            'monitoring_alerts' => self::monitoringAlerts(),
            'log_aggregation' => self::logAggregation(),
            'database_health' => self::databaseHealth(),
            'redis_health' => self::redisHealth(),
            default => [
                'ok' => false,
                'message' => 'Unknown check id.',
                'recommendation' => 'Use a valid check key from Ops Status.',
            ],
        };
    }

    private static function searchConsole(): array
    {
        $token = trim((string) setting('google_site_verification', ''));
        if ($token === '') {
            return self::fail('Verification token not set.', 'Add Google Site Verification in Settings.');
        }

        return self::ok('Verification token is configured.');
    }

    private static function ga4(): array
    {
        $mid = strtoupper(trim((string) setting('ga4_measurement_id', '')));
        if ($mid === '' || !preg_match('/^G-[A-Z0-9]+$/', $mid)) {
            return self::fail('GA4 Measurement ID missing/invalid.', 'Set GA4 ID in Settings.');
        }

        return self::ok('GA4 measurement ID is valid.');
    }

    private static function sgtm(): array
    {
        $url = trim((string) setting('gtm_server_url', ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return self::fail('Server-side GTM URL missing/invalid.', 'Set SGTM URL in Settings.');
        }

        $probe = self::probe($url . '/gtm.js');
        if (!$probe['ok']) {
            return self::fail('SGTM endpoint probe failed.', 'Check SGTM server, DNS, TLS, and firewall.', ['probe' => $probe['message']]);
        }

        return self::ok('SGTM endpoint reachable.', ['probe' => $probe['message']]);
    }

    private static function sentry(): array
    {
        $dsn = trim((string) setting('sentry_dsn', env('SENTRY_DSN', '')));
        if ($dsn === '' || !preg_match('/^https?:\/\/[^\/]+\/.+$/', $dsn)) {
            return self::fail('Sentry DSN missing/invalid.', 'Set Sentry DSN in Settings.');
        }

        return self::ok('Sentry DSN format valid.');
    }

    private static function cdnWaf(): array
    {
        $enabled = setting('ops_cdn_enabled', '0') === '1';
        $headers = [
            'cf-ray' => (string) ($_SERVER['HTTP_CF_RAY'] ?? ''),
            'x-forwarded-for' => (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            'via' => (string) ($_SERVER['HTTP_VIA'] ?? ''),
        ];
        $hasEdgeHint = $headers['cf-ray'] !== '' || stripos($headers['via'], 'varnish') !== false || $headers['x-forwarded-for'] !== '';

        if (!$enabled) {
            return self::fail('CDN/WAF toggle is disabled.', 'Enable CDN/WAF and set CDN base URL.', ['edge_header_detected' => $hasEdgeHint ? 'yes' : 'no']);
        }

        return self::ok('CDN/WAF marked as enabled.', ['edge_header_detected' => $hasEdgeHint ? 'yes' : 'no']);
    }

    private static function sslHstsHttp2(): array
    {
        $url = (string) config('url', '');
        $isHttpsUrl = stripos($url, 'https://') === 0;
        $hstsEnabled = setting('security_hsts_enabled', '0') === '1';
        $httpsForced = setting('security_force_https', '0') === '1';
        $http2Hint = (string) ($_SERVER['SERVER_PROTOCOL'] ?? '');

        if (!$isHttpsUrl || !$hstsEnabled || !$httpsForced) {
            return self::fail(
                'HTTPS/HSTS/force-HTTPS are not fully enforced.',
                'Use HTTPS APP_URL, enable HSTS, and force HTTPS redirects.',
                ['server_protocol' => $http2Hint]
            );
        }

        return self::ok('HTTPS + HSTS + HTTPS redirect configured.', ['server_protocol' => $http2Hint]);
    }

    private static function opcache(): array
    {
        if (!function_exists('opcache_get_status')) {
            return self::fail('OPcache extension unavailable.', 'Enable OPcache in php.ini.');
        }

        $status = opcache_get_status(false);
        $isEnabled = is_array($status) && (($status['opcache_enabled'] ?? false) === true);
        if (!$isEnabled) {
            return self::fail('OPcache is not enabled.', 'Enable and tune OPcache memory and interned strings.');
        }

        return self::ok('OPcache active.');
    }

    private static function phpFpmPool(): array
    {
        $template = BASE_PATH . '/deploy/php/php-fpm-profios.conf';
        if (!is_file($template)) {
            return self::fail('FPM tuning template missing.', 'Add deploy/php/php-fpm-profios.conf and apply in production.');
        }

        return self::ok('FPM pool tuning template is available.');
    }

    private static function compression(): array
    {
        $flag = setting('ops_brotli_enabled', '1') === '1';
        if (!$flag) {
            return self::fail('Compression toggle disabled.', 'Enable Brotli/Gzip in server config and settings.');
        }

        return self::ok('Compression readiness enabled (verify via response Content-Encoding).');
    }

    private static function imagePipeline(): array
    {
        $script = BASE_PATH . '/bin/image-pipeline.sh';
        $variantsDir = STORAGE_PATH . '/uploads/variants';
        if (!is_file($script)) {
            return self::fail('Image pipeline script missing.', 'Add or restore bin/image-pipeline.sh.');
        }

        $hasVariants = is_dir($variantsDir) && (glob($variantsDir . '/*') ?: []) !== [];
        if (!$hasVariants) {
            return self::fail('No generated variants found yet.', 'Run bin/image-pipeline.sh to generate WebP/AVIF/responsive assets.');
        }

        return self::ok('Image variants present.', ['variants_dir' => $variantsDir]);
    }

    private static function backupAutomation(): array
    {
        $backupScript = BASE_PATH . '/bin/backup.sh';
        $restoreScript = BASE_PATH . '/bin/restore-smoke-test.sh';
        $latest = STORAGE_PATH . '/backups/latest.json';
        if (!is_file($backupScript) || !is_file($restoreScript)) {
            return self::fail('Backup or restore script missing.', 'Ensure backup.sh and restore-smoke-test.sh exist.');
        }
        if (!is_file($latest)) {
            return self::fail('No backup manifest found.', 'Run backup script and schedule cron/systemd timer.');
        }

        return self::ok('Backup scripts and latest manifest found.', ['manifest' => $latest]);
    }

    private static function sslRenewal(): array
    {
        $timer = BASE_PATH . '/deploy/systemd/cert-renew.timer';
        $service = BASE_PATH . '/deploy/systemd/cert-renew.service';
        if (!is_file($timer) || !is_file($service)) {
            return self::fail('Cert renew systemd templates missing.', 'Add and enable cert-renew systemd timer/service.');
        }

        return self::ok('Cert renew templates are present.');
    }

    private static function monitoringAlerts(): array
    {
        $email = trim((string) setting('ops_monitoring_alert_email', ''));
        $healthTimer = BASE_PATH . '/deploy/systemd/app-healthcheck.timer';
        if ($email === '') {
            return self::fail('Monitoring alert email not configured.', 'Set ops alert email in Settings.');
        }
        if (!is_file($healthTimer)) {
            return self::fail('Healthcheck timer config missing.', 'Add and enable deploy/systemd/app-healthcheck.timer.');
        }

        return self::ok('Monitoring basics configured.', ['alert_email' => $email]);
    }

    private static function logAggregation(): array
    {
        $logrotate = BASE_PATH . '/deploy/logging/logrotate-profios.conf';
        if (!is_file($logrotate)) {
            return self::fail('Log retention policy file missing.', 'Add deploy/logging/logrotate-profios.conf.');
        }

        return self::ok('Log retention policy template found.', ['logrotate' => $logrotate]);
    }

    private static function databaseHealth(): array
    {
        $status = HealthService::status();
        $db = $status['checks']['database'] ?? ['ok' => false, 'message' => 'Unknown'];
        if (($db['ok'] ?? false) !== true) {
            return self::fail((string) ($db['message'] ?? 'Database not healthy'), 'Check MySQL service and credentials.');
        }

        return self::ok((string) ($db['message'] ?? 'Database healthy.'));
    }

    private static function redisHealth(): array
    {
        $status = Cache::redisHealth();
        if (($status['configured'] ?? false) !== true) {
            return self::fail('Redis not configured as cache driver.', 'Enable Redis cache driver in setup/settings.');
        }

        if (($status['connected'] ?? false) !== true) {
            return self::fail((string) ($status['message'] ?? 'Redis unhealthy.'), 'Check Redis service, password, and firewall.');
        }

        return self::ok((string) ($status['message'] ?? 'Redis healthy.'));
    }

    private static function probe(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        try {
            $body = @file_get_contents($url, false, $ctx);
            $headers = $http_response_header ?? [];
            $statusLine = is_array($headers) && isset($headers[0]) ? (string) $headers[0] : '';
            if ($body === false && $statusLine === '') {
                return ['ok' => false, 'message' => 'No response'];
            }

            return ['ok' => true, 'message' => $statusLine !== '' ? $statusLine : 'Reachable'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private static function ok(string $message, array $details = []): array
    {
        return [
            'ok' => true,
            'message' => $message,
            'recommendation' => '',
            'details' => $details,
            'checked_at' => now(),
        ];
    }

    private static function fail(string $message, string $recommendation, array $details = []): array
    {
        return [
            'ok' => false,
            'message' => $message,
            'recommendation' => $recommendation,
            'details' => $details,
            'checked_at' => now(),
        ];
    }
}
