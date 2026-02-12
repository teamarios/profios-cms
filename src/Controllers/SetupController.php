<?php

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\View;
use App\Services\BackupCodeService;
use App\Services\TotpService;
use PDO;

final class SetupController
{
    public function index(): void
    {
        if (config('installed')) {
            redirect('/admin/login');
        }

        View::render('setup.install', [
            'title' => 'Install CMS',
            'defaults' => [
                'app_name' => env('APP_NAME', 'Profios CMS'),
                'app_url' => env('APP_URL', 'http://localhost:8000'),
                'app_env' => env('APP_ENV', 'production'),
                'db_host' => env('DB_HOST', '127.0.0.1'),
                'db_port' => env('DB_PORT', '3306'),
                'db_name' => env('DB_DATABASE', 'profios_cms'),
                'db_user' => env('DB_USERNAME', 'root'),
                'db_pass' => env('DB_PASSWORD', ''),
                'cache_driver' => env('CACHE_DRIVER', 'redis'),
                'frontend_cache_driver' => env('FRONTEND_CACHE_DRIVER', 'varnish'),
                'redis_host' => env('REDIS_HOST', '127.0.0.1'),
                'redis_port' => env('REDIS_PORT', '6379'),
                'redis_pass' => env('REDIS_PASSWORD', ''),
                'redis_db' => env('REDIS_DB', '0'),
                'stack_auto_credentials' => env('STACK_AUTO_CREDENTIALS', 'false'),
                'google_site_verification' => '',
                'ga4_measurement_id' => '',
                'ga4_transport_url' => '',
                'sentry_dsn' => env('SENTRY_DSN', ''),
                'sentry_environment' => env('APP_ENV', 'production'),
                'sentry_release' => env('APP_RELEASE', ''),
                'admin_totp_enabled' => '1',
                'admin_totp_secret' => TotpService::generateSecret(),
            ],
        ], 'auth');
    }

    public function install(): void
    {
        if (config('installed')) {
            redirect('/admin/login');
        }

        Csrf::verifyOrFail();

        if (!empty($_POST['contact_url'] ?? '')) {
            usleep(300000);
            $_SESSION['flash_error'] = 'Setup validation failed.';
            redirect('/setup');
        }

        $ip = clientIp();
        if (!RateLimiter::hit('setup_' . md5($ip), 10, 3600)) {
            $_SESSION['flash_error'] = 'Too many setup attempts from this IP. Try later.';
            redirect('/setup');
        }

        $payload = $this->payload();

        try {
            $pdo = $this->connectServer($payload);
            $this->ensureDatabase($pdo, $payload['db_name']);

            $dbPdo = $this->connectDatabase($payload);
            $this->runSchema($dbPdo);

            $backupCodes = $this->upsertAdmin($dbPdo, $payload);
            $this->upsertHomePage($dbPdo, $payload);
            $this->saveSettings($dbPdo, $payload);

            $this->writeEnv($payload);
            loadEnv(BASE_PATH . '/.env');

            $_SESSION['flash_success'] = 'Installation complete. You can sign in now.';
            if ($backupCodes !== []) {
                $_SESSION['flash_backup_codes'] = $backupCodes;
            }
            redirect('/admin/login');
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'Setup failed: ' . $e->getMessage();
            $_SESSION['setup_old'] = $payload;
            redirect('/setup');
        }
    }

    public function progress(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $path = STORAGE_PATH . '/install-progress.json';
        if (!is_file($path)) {
            echo json_encode([
                'status' => 'idle',
                'percent' => 0,
                'message' => 'No installer progress found.',
                'updated_at' => now(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            echo json_encode([
                'status' => 'idle',
                'percent' => 0,
                'message' => 'Progress file is empty.',
                'updated_at' => now(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            echo json_encode([
                'status' => 'unknown',
                'percent' => 0,
                'message' => 'Progress data is invalid.',
                'updated_at' => now(),
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    private function payload(): array
    {
        return [
            'app_name' => trim((string) ($_POST['app_name'] ?? 'Profios CMS')),
            'app_url' => trim((string) ($_POST['app_url'] ?? 'http://localhost:8000')),
            'app_env' => trim((string) ($_POST['app_env'] ?? 'production')),
            'app_debug' => isset($_POST['app_debug']) ? 'true' : 'false',
            'db_host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
            'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
            'db_name' => trim((string) ($_POST['db_name'] ?? 'profios_cms')),
            'db_user' => trim((string) ($_POST['db_user'] ?? 'root')),
            'db_pass' => (string) ($_POST['db_pass'] ?? env('DB_PASSWORD', '')),
            'admin_name' => trim((string) ($_POST['admin_name'] ?? 'Admin User')),
            'admin_email' => trim((string) ($_POST['admin_email'] ?? 'admin@example.com')),
            'admin_pass' => (string) ($_POST['admin_pass'] ?? ''),
            'admin_totp_enabled' => isset($_POST['admin_totp_enabled']) ? '1' : '0',
            'admin_totp_secret' => strtoupper(trim((string) ($_POST['admin_totp_secret'] ?? ''))),
            'cache_driver' => ($_POST['cache_driver'] ?? 'redis') === 'redis' ? 'redis' : 'file',
            'frontend_cache_driver' => trim((string) ($_POST['frontend_cache_driver'] ?? 'varnish')),
            'redis_host' => trim((string) ($_POST['redis_host'] ?? '127.0.0.1')),
            'redis_port' => trim((string) ($_POST['redis_port'] ?? '6379')),
            'redis_pass' => (string) ($_POST['redis_pass'] ?? env('REDIS_PASSWORD', '')),
            'redis_db' => trim((string) ($_POST['redis_db'] ?? '0')),
            'session_name' => trim((string) ($_POST['session_name'] ?? 'profios_cms_session')),
        ];
    }

    private function connectServer(array $payload): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $payload['db_host'], $payload['db_port']);
        return new PDO($dsn, $payload['db_user'], $payload['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private function ensureDatabase(PDO $pdo, string $dbName): void
    {
        $safeDbName = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName);
        if ($safeDbName === '') {
            throw new \RuntimeException('Invalid database name.');
        }

        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $safeDbName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function connectDatabase(array $payload): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $payload['db_host'],
            $payload['db_port'],
            $payload['db_name']
        );

        return new PDO($dsn, $payload['db_user'], $payload['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    }

    private function runSchema(PDO $pdo): void
    {
        $schemaPath = BASE_PATH . '/database/schema.sql';
        $sql = file_get_contents($schemaPath);
        if ($sql === false) {
            throw new \RuntimeException('Unable to read schema file.');
        }

        $pdo->exec($sql);
        $this->ensureColumn($pdo, 'users', 'totp_secret', 'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL');
        $this->ensureColumn($pdo, 'users', 'totp_enabled', 'ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureColumn($pdo, 'users', 'backup_codes_json', 'ALTER TABLE users ADD COLUMN backup_codes_json LONGTEXT NULL');
        $this->ensureColumn($pdo, 'users', 'role', "ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'admin'");
        $this->ensureColumn($pdo, 'pages', 'seo_score', 'ALTER TABLE pages ADD COLUMN seo_score INT UNSIGNED NOT NULL DEFAULT 0');
        $this->ensureColumn($pdo, 'pages', 'seo_issues_json', 'ALTER TABLE pages ADD COLUMN seo_issues_json LONGTEXT NULL');
        $pdo->exec('CREATE TABLE IF NOT EXISTS app_settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(190) NOT NULL UNIQUE,
            `value` LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_key (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private function ensureColumn(PDO $pdo, string $table, string $column, string $alterSql): void
    {
        $check = $pdo->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
        $check->execute([
            'table' => $table,
            'column' => $column,
        ]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        $exists = (int) ($row['cnt'] ?? 0) > 0;
        if (!$exists) {
            $pdo->exec($alterSql);
        }
    }

    private function upsertAdmin(PDO $pdo, array $payload): array
    {
        if ($payload['admin_pass'] === '') {
            throw new \RuntimeException('Admin password is required.');
        }

        if ($payload['admin_totp_enabled'] === '1' && !preg_match('/^[A-Z2-7]{16,64}$/', $payload['admin_totp_secret'])) {
            throw new \RuntimeException('Invalid Google Authenticator secret format.');
        }

        $plainBackupCodes = $payload['admin_totp_enabled'] === '1' ? BackupCodeService::generate(8) : [];
        $hashedBackupCodes = $plainBackupCodes === [] ? [] : BackupCodeService::hashCodes($plainBackupCodes);

        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, totp_secret, totp_enabled, backup_codes_json, created_at, updated_at)
            VALUES (:name, :email, :password, :role, :totp_secret, :totp_enabled, :backup_codes_json, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE name = VALUES(name), password = VALUES(password), role = VALUES(role), totp_secret = VALUES(totp_secret), totp_enabled = VALUES(totp_enabled), backup_codes_json = VALUES(backup_codes_json), updated_at = VALUES(updated_at)');

        $stmt->execute([
            'name' => $payload['admin_name'],
            'email' => $payload['admin_email'],
            'password' => password_hash($payload['admin_pass'], PASSWORD_DEFAULT),
            'role' => 'admin',
            'totp_secret' => $payload['admin_totp_secret'],
            'totp_enabled' => (int) $payload['admin_totp_enabled'],
            'backup_codes_json' => json_encode($hashedBackupCodes, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plainBackupCodes;
    }

    private function saveSettings(PDO $pdo, array $payload): void
    {
        $stmt = $pdo->prepare('INSERT INTO app_settings (`key`, `value`, updated_at)
            VALUES (:key, :value, :updated_at)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = VALUES(updated_at)');

        $settings = [
            'cache_driver' => $payload['cache_driver'],
            'frontend_cache_driver' => $payload['frontend_cache_driver'],
            'redis_host' => $payload['redis_host'],
            'redis_port' => $payload['redis_port'],
            'redis_db' => $payload['redis_db'],
            'security_hsts_enabled' => '1',
            'security_xss_protection' => '1',
            'security_force_https' => '0',
            'security_csp' => "default-src 'self' https: data: 'unsafe-inline'; frame-ancestors 'self';",
            'spam_honeypot_enabled' => '1',
            'spam_rate_limit_enabled' => '1',
            'spam_rate_limit_max' => '8',
            'spam_rate_limit_window' => '900',
            'google_site_verification' => '',
            'ga4_measurement_id' => '',
            'ga4_transport_url' => '',
            'sentry_dsn' => env('SENTRY_DSN', ''),
            'sentry_environment' => env('APP_ENV', 'production'),
            'sentry_release' => env('APP_RELEASE', ''),
            'sentry_traces_sample_rate' => '0.20',
            'perf_rum_web_vitals' => '1',
            'seo_robots_noindex_nonprod' => '1',
            'ops_cdn_enabled' => '0',
            'ops_cdn_base_url' => '',
            'ops_opcache_enabled' => '1',
            'ops_brotli_enabled' => '1',
            'ops_monitoring_alert_email' => '',
            'seo_internal_links_json' => '[]',
            'security_force_2fa_roles' => 'admin',
            'ops_updates_enabled' => '0',
            'ops_branch' => 'main',
            'ops_git_user_name' => 'Profios CMS Bot',
            'ops_git_user_email' => 'noreply@example.com',
            'perf_preload_bootstrap_css' => '1',
            'perf_preconnect_third_party' => '1',
            'perf_defer_third_party_js' => '1',
            'perf_lazy_images' => '1',
            'perf_explicit_image_dimensions' => '1',
            'perf_preload_lcp_image' => '1',
            'perf_reduce_unused_css' => '1',
            'perf_reduce_unused_js' => '1',
            'perf_minify_css' => '0',
            'perf_minify_html' => '0',
            'perf_font_display_swap' => '1',
            'perf_enable_long_cache_assets' => '1',
            'perf_avoid_non_composited_animations' => '1',
            'perf_limit_third_party_scripts' => '1',
            'perf_user_timing_marks' => '1',
        ];

        foreach ($settings as $key => $value) {
            $stmt->execute([
                'key' => $key,
                'value' => (string) $value,
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertHomePage(PDO $pdo, array $payload): void
    {
        $stmt = $pdo->prepare('INSERT INTO pages (title, slug, status, meta_title, meta_description, canonical_url, schema_json, content_blocks, seo_score, seo_issues_json, cache_ttl, created_at, updated_at, published_at)
            VALUES (:title, :slug, :status, :meta_title, :meta_description, :canonical_url, :schema_json, :content_blocks, :seo_score, :seo_issues_json, :cache_ttl, :created_at, :updated_at, :published_at)
            ON DUPLICATE KEY UPDATE title = VALUES(title), status = VALUES(status), updated_at = VALUES(updated_at), published_at = VALUES(published_at)');

        $stmt->execute([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
            'meta_title' => $payload['app_name'] . ' | SEO & Marketing CMS',
            'meta_description' => 'Fast and secure custom CMS with block builder and SEO-first controls.',
            'canonical_url' => rtrim($payload['app_url'], '/') . '/',
            'schema_json' => json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $payload['app_name'],
            ], JSON_UNESCAPED_UNICODE),
            'content_blocks' => json_encode([
                [
                    'type' => 'hero',
                    'title' => 'Welcome to ' . $payload['app_name'],
                    'text' => 'Your SEO-ready and high-performance CMS is installed.',
                    'button_text' => 'Start Editing',
                    'button_url' => '/admin',
                ],
            ], JSON_UNESCAPED_UNICODE),
            'seo_score' => 78,
            'seo_issues_json' => json_encode(['Review meta title/description lengths for final production content.'], JSON_UNESCAPED_UNICODE),
            'cache_ttl' => 120,
            'created_at' => now(),
            'updated_at' => now(),
            'published_at' => now(),
        ]);
    }

    private function writeEnv(array $payload): void
    {
        $values = [
            'APP_NAME' => $payload['app_name'],
            'APP_URL' => $payload['app_url'],
            'APP_ENV' => $payload['app_env'],
            'APP_DEBUG' => $payload['app_debug'],
            'APP_KEY' => generateAppKey(),
            'APP_INSTALLED' => 'true',
            'STACK_AUTO_CREDENTIALS' => env('STACK_AUTO_CREDENTIALS', 'false'),
            'DB_HOST' => $payload['db_host'],
            'DB_PORT' => $payload['db_port'],
            'DB_DATABASE' => $payload['db_name'],
            'DB_USERNAME' => $payload['db_user'],
            'DB_PASSWORD' => $payload['db_pass'],
            'SESSION_NAME' => $payload['session_name'],
            'CACHE_DRIVER' => $payload['cache_driver'],
            'CACHE_PREFIX' => 'profios_cms_',
            'REDIS_HOST' => $payload['redis_host'],
            'REDIS_PORT' => $payload['redis_port'],
            'REDIS_PASSWORD' => $payload['redis_pass'],
            'REDIS_DB' => $payload['redis_db'],
            'FRONTEND_CACHE_DRIVER' => $payload['frontend_cache_driver'],
            'SENTRY_DSN' => env('SENTRY_DSN', ''),
            'APP_RELEASE' => env('APP_RELEASE', ''),
        ];

        writeEnvFile(BASE_PATH . '/.env', $values);
    }
}
