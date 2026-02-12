<?php

namespace App\Controllers;

use App\Core\Cache;
use App\Core\RateLimiter;
use App\Core\View;
use App\Core\Logger;
use App\Models\Page;
use App\Services\HealthService;
use App\Services\SeoService;

final class FrontendController
{
    public function health(): void
    {
        $status = HealthService::status();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(($status['ok'] ?? false) ? 200 : 503);
        echo json_encode($status, JSON_UNESCAPED_UNICODE);
    }

    public function readiness(): void
    {
        $status = HealthService::status();
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(($status['ok'] ?? false) ? 200 : 503);
        echo json_encode($status, JSON_UNESCAPED_UNICODE);
    }

    public function captureVitals(): void
    {
        $ip = clientIp();
        if (!RateLimiter::hit('rum_' . md5($ip), 120, 60)) {
            http_response_code(429);
            return;
        }

        $body = file_get_contents('php://input');
        if (!is_string($body) || trim($body) === '') {
            http_response_code(204);
            return;
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            http_response_code(204);
            return;
        }

        $metricName = preg_replace('/[^A-Z]/', '', strtoupper((string) ($payload['name'] ?? '')));
        if ($metricName === '') {
            http_response_code(204);
            return;
        }

        $value = (float) ($payload['value'] ?? 0);
        $rating = preg_replace('/[^a-z_]/', '', strtolower((string) ($payload['rating'] ?? 'unknown')));
        $page = substr((string) ($payload['page'] ?? '/'), 0, 160);

        Logger::error('[RUM] metric=' . $metricName . ' value=' . $value . ' rating=' . $rating . ' page=' . $page);
        http_response_code(204);
    }

    public function home(): void
    {
        $page = Page::findBySlug('home');

        if (!$page) {
            http_response_code(404);
            echo 'Create a published page with slug "home" from admin.';
            return;
        }

        $this->renderPage($page);
    }

    public function show(string $slug): void
    {
        $page = Page::findBySlug($slug);

        if (!$page) {
            http_response_code(404);
            echo 'Page not found.';
            return;
        }

        $this->renderPage($page);
    }

    private function renderPage(array $page): void
    {
        $ttl = max(30, (int) ($page['cache_ttl'] ?? 120));
        $key = 'page_' . $page['slug'];
        $frontendCache = (string) config('cache.frontend_driver', 'varnish');

        header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=120, stale-if-error=86400');
        header('X-Frontend-Cache: ' . $frontendCache);

        $html = Cache::remember($key, $ttl, static function () use ($page): string {
            ob_start();
            View::render('frontend.page', [
                'page' => $page,
                'meta' => SeoService::buildMeta($page),
                'blocks' => json_decode((string) ($page['content_blocks'] ?? '[]'), true) ?: [],
            ], 'frontend');
            $rendered = (string) ob_get_clean();
            if (setting('perf_minify_html', '0') === '1') {
                $rendered = preg_replace('/\s+/', ' ', $rendered) ?? $rendered;
            }
            return $rendered;
        });

        echo $html;
    }
}
