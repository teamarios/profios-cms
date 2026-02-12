<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Services\UpdateService;

final class AdminUpdatesController
{
    public function index(): void
    {
        Auth::requireLogin();

        View::render('admin.updates', [
            'title' => 'GitHub Updates',
            'status' => UpdateService::status(),
            'settings' => [
                'ops_updates_enabled' => setting('ops_updates_enabled', '0'),
                'ops_repo_url' => setting('ops_repo_url', ''),
                'ops_branch' => setting('ops_branch', 'main'),
                'ops_git_user_name' => setting('ops_git_user_name', 'Profios CMS Bot'),
                'ops_git_user_email' => setting('ops_git_user_email', 'noreply@example.com'),
            ],
        ], 'admin');
    }

    public function saveConfig(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        UpdateService::saveConfig([
            'ops_updates_enabled' => isset($_POST['ops_updates_enabled']),
            'ops_repo_url' => trim((string) ($_POST['ops_repo_url'] ?? '')),
            'ops_branch' => trim((string) ($_POST['ops_branch'] ?? 'main')),
            'ops_git_user_name' => trim((string) ($_POST['ops_git_user_name'] ?? 'Profios CMS Bot')),
            'ops_git_user_email' => trim((string) ($_POST['ops_git_user_email'] ?? 'noreply@example.com')),
        ]);

        $_SESSION['flash_success'] = 'Update settings saved.';
        redirect('/admin/updates');
    }

    public function pull(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        try {
            $_SESSION['updates_log'] = UpdateService::pull();
            $_SESSION['flash_success'] = 'Pulled latest updates.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        redirect('/admin/updates');
    }

    public function push(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $message = trim((string) ($_POST['commit_message'] ?? 'CMS automated update'));

        try {
            $_SESSION['updates_log'] = UpdateService::push($message);
            $_SESSION['flash_success'] = 'Pushed updates to GitHub.';
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }

        redirect('/admin/updates');
    }
}
