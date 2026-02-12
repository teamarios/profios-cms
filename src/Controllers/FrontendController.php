<?php

namespace App\Controllers;

use App\Core\Cache;
use App\Core\View;
use App\Models\Page;
use App\Services\SeoService;

final class FrontendController
{
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
