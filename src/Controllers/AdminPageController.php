<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Cache;
use App\Core\Csrf;
use App\Core\View;
use App\Models\Page;
use App\Models\Setting;
use App\Services\SeoAuditService;

final class AdminPageController
{
    public function index(): void
    {
        Auth::requireLogin();
        $runtimeSettings = Setting::all();
        $pages = Page::all();
        foreach ($pages as &$page) {
            if ((int) ($page['seo_score'] ?? 0) <= 0) {
                $audit = SeoAuditService::analyze($page, $runtimeSettings);
                $page['seo_score'] = $audit['score'];
                $page['seo_issues_json'] = json_encode($audit['issues'], JSON_UNESCAPED_UNICODE);
            }
        }
        unset($page);

        View::render('admin.pages.index', [
            'title' => 'Pages',
            'pages' => $pages,
        ], 'admin');
    }

    public function create(): void
    {
        Auth::requireLogin();

        View::render('admin.pages.edit', [
            'title' => 'Create Page',
            'page' => null,
            'seoAudit' => ['score' => 0, 'issues' => ['Save draft to generate SEO audit.']],
        ], 'admin');
    }

    public function edit(string $id): void
    {
        Auth::requireLogin();

        $page = Page::find((int) $id);
        if (!$page) {
            http_response_code(404);
            echo 'Page not found.';
            return;
        }

        View::render('admin.pages.edit', [
            'title' => 'Edit Page',
            'page' => $page,
            'seoAudit' => SeoAuditService::analyze($page, Setting::all()),
        ], 'admin');
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $data = $this->validatedPayload();
        Page::create($data);
        Cache::clearByPrefix('page_');
        redirect('/admin/pages');
    }

    public function update(string $id): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $data = $this->validatedPayload();
        Page::update((int) $id, $data);
        Cache::clearByPrefix('page_');
        redirect('/admin/pages');
    }

    public function delete(string $id): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        Page::delete((int) $id);
        Cache::clearByPrefix('page_');
        redirect('/admin/pages');
    }

    private function validatedPayload(): array
    {
        $title = trim((string) ($_POST['title'] ?? 'Untitled Page'));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug) ?: 'untitled-page';
        $slug = trim($slug, '-') ?: 'untitled-page';

        $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';

        $blocks = json_decode((string) ($_POST['content_blocks'] ?? '[]'), true);
        if (!is_array($blocks)) {
            $blocks = [];
        }

        $payload = [
            'title' => $title,
            'slug' => $slug,
            'status' => $status,
            'meta_title' => trim((string) ($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string) ($_POST['meta_description'] ?? '')),
            'canonical_url' => trim((string) ($_POST['canonical_url'] ?? '')),
            'schema_json' => trim((string) ($_POST['schema_json'] ?? '')),
            'content_blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE),
            'cache_ttl' => max(30, (int) ($_POST['cache_ttl'] ?? 120)),
        ];

        $audit = SeoAuditService::analyze($payload, Setting::all());
        $payload['seo_score'] = $audit['score'];
        $payload['seo_issues_json'] = json_encode($audit['issues'], JSON_UNESCAPED_UNICODE);

        return $payload;
    }
}
