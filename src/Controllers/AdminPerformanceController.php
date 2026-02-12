<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\Setting;
use App\Services\PerformanceAuditService;

final class AdminPerformanceController
{
    public function index(): void
    {
        Auth::requireLogin();

        $defaults = PerformanceAuditService::defaults();
        $settings = array_merge($defaults, Setting::all());
        $audit = PerformanceAuditService::audit($settings);

        View::render('admin.performance', [
            'title' => 'Performance Center',
            'settings' => $settings,
            'audit' => $audit,
        ], 'admin');
    }

    public function save(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $defaults = PerformanceAuditService::defaults();
        $payload = [];
        foreach ($defaults as $key => $value) {
            $payload[$key] = isset($_POST[$key]) ? '1' : '0';
        }

        Setting::upsertMany($payload);
        $_SESSION['flash_success'] = 'Performance settings saved.';
        redirect('/admin/performance');
    }
}
