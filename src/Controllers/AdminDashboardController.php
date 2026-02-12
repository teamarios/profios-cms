<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\View;
use App\Models\Page;

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
        ], 'admin');
    }
}
