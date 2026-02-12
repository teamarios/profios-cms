<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Page;
use App\Models\Setting;

final class AdminDashboardController
{
    public function index(): void
    {
        Auth::requireLogin();

        $pages = Page::all();
        View::render('admin.dashboard', [
            'title' => 'Dashboard',
            'pagesCount' => count($pages),
            'publishedCount' => count(array_filter($pages, static fn (array $p): bool => $p['status'] === 'published')),
            'integrations' => [
                'Search Console' => trim((string) Setting::get('google_site_verification', '')) !== '',
                'GA4' => trim((string) Setting::get('ga4_measurement_id', '')) !== '',
                'Server GTM' => trim((string) Setting::get('gtm_server_url', '')) !== '',
                'Sentry' => trim((string) Setting::get('sentry_dsn', '')) !== '',
            ],
        ], 'admin');
    }
}
