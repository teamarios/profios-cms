<?php

declare(strict_types=1);

use App\Controllers\AdminAuthController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AdminPageController;
use App\Controllers\AdminSecurityController;
use App\Controllers\AdminSettingsController;
use App\Controllers\AdminPerformanceController;
use App\Controllers\AdminUpdatesController;
use App\Controllers\FrontendController;
use App\Controllers\SeoController;
use App\Controllers\SetupController;
use App\Core\Router;
use App\Core\RuntimeConfig;

require_once dirname(__DIR__) . '/bootstrap.php';
RuntimeConfig::hydrate($config);
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? null) === '443')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (setting('security_force_https', '0') === '1' && !$isHttps) {
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
if (setting('security_hsts_enabled', '0') === '1') {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload');
}
if (setting('security_xss_protection', '1') === '1') {
    header('X-XSS-Protection: 1; mode=block');
}
$csp = setting('security_csp', '');
if ($csp !== '') {
    header('Content-Security-Policy: ' . $csp);
}

$router = new Router();
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

if (str_starts_with($requestPath, '/admin') || str_starts_with($requestPath, '/setup')) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

if (!config('installed')) {
    $setupAllowed = str_starts_with($requestPath, '/setup');
    if (!$setupAllowed) {
        redirect('/setup');
    }
} elseif (str_starts_with($requestPath, '/setup')) {
    redirect('/admin/login');
}

$router->get('/setup', [SetupController::class, 'index']);
$router->get('/setup/progress', [SetupController::class, 'progress']);
$router->post('/setup/install', [SetupController::class, 'install']);

$router->get('/', [FrontendController::class, 'home']);
$router->get('/page/{slug}', [FrontendController::class, 'show']);
$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/robots.txt', [SeoController::class, 'robots']);

$router->get('/admin/login', [AdminAuthController::class, 'loginForm']);
$router->post('/admin/login', [AdminAuthController::class, 'login']);
$router->get('/admin/logout', [AdminAuthController::class, 'logout']);

$router->get('/admin', [AdminDashboardController::class, 'index']);
$router->get('/admin/pages', [AdminPageController::class, 'index']);
$router->get('/admin/pages/create', [AdminPageController::class, 'create']);
$router->get('/admin/pages/{id}/edit', [AdminPageController::class, 'edit']);
$router->post('/admin/pages/store', [AdminPageController::class, 'store']);
$router->post('/admin/pages/{id}/update', [AdminPageController::class, 'update']);
$router->post('/admin/pages/{id}/delete', [AdminPageController::class, 'delete']);
$router->get('/admin/settings', [AdminSettingsController::class, 'edit']);
$router->post('/admin/settings/update', [AdminSettingsController::class, 'update']);
$router->get('/admin/security', [AdminSecurityController::class, 'index']);
$router->post('/admin/security/rotate-2fa', [AdminSecurityController::class, 'rotateTwoFactor']);
$router->post('/admin/security/disable-2fa', [AdminSecurityController::class, 'disableTwoFactor']);
$router->post('/admin/security/backup-codes', [AdminSecurityController::class, 'regenerateBackupCodes']);
$router->get('/admin/performance', [AdminPerformanceController::class, 'index']);
$router->post('/admin/performance/save', [AdminPerformanceController::class, 'save']);
$router->get('/admin/updates', [AdminUpdatesController::class, 'index']);
$router->post('/admin/updates/config', [AdminUpdatesController::class, 'saveConfig']);
$router->post('/admin/updates/pull', [AdminUpdatesController::class, 'pull']);
$router->post('/admin/updates/push', [AdminUpdatesController::class, 'push']);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
