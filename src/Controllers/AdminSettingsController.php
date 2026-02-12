<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\Setting;

final class AdminSettingsController
{
    public function edit(): void
    {
        Auth::requireLogin();

        View::render('admin.settings', [
            'title' => 'SEO & Security Settings',
            'settings' => Setting::all(),
        ], 'admin');
    }

    public function update(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $payload = [
            'seo_header_code' => (string) ($_POST['seo_header_code'] ?? ''),
            'seo_footer_code' => (string) ($_POST['seo_footer_code'] ?? ''),
            'seo_global_schema_json' => (string) ($_POST['seo_global_schema_json'] ?? ''),
            'seo_internal_links_json' => (string) ($_POST['seo_internal_links_json'] ?? '[]'),
            'seo_geo_latitude' => trim((string) ($_POST['seo_geo_latitude'] ?? '')),
            'seo_geo_longitude' => trim((string) ($_POST['seo_geo_longitude'] ?? '')),
            'seo_geo_region' => trim((string) ($_POST['seo_geo_region'] ?? '')),
            'gtm_container_id' => trim((string) ($_POST['gtm_container_id'] ?? '')),
            'gtm_server_url' => trim((string) ($_POST['gtm_server_url'] ?? '')),
            'security_csp' => (string) ($_POST['security_csp'] ?? "default-src 'self' https: data: 'unsafe-inline'; frame-ancestors 'self';"),
            'security_hsts_enabled' => isset($_POST['security_hsts_enabled']) ? '1' : '0',
            'security_xss_protection' => isset($_POST['security_xss_protection']) ? '1' : '0',
            'security_force_https' => isset($_POST['security_force_https']) ? '1' : '0',
            'security_force_2fa_roles' => strtolower(trim((string) ($_POST['security_force_2fa_roles'] ?? 'admin'))),
            'spam_honeypot_enabled' => isset($_POST['spam_honeypot_enabled']) ? '1' : '0',
            'spam_rate_limit_enabled' => isset($_POST['spam_rate_limit_enabled']) ? '1' : '0',
            'spam_rate_limit_max' => max(3, (int) ($_POST['spam_rate_limit_max'] ?? 8)),
            'spam_rate_limit_window' => max(60, (int) ($_POST['spam_rate_limit_window'] ?? 900)),
        ];

        $roles = array_filter(array_map('trim', explode(',', $payload['security_force_2fa_roles'])));
        $safeRoles = [];
        foreach ($roles as $role) {
            $clean = preg_replace('/[^a-z0-9_-]/', '', $role);
            if ($clean !== '') {
                $safeRoles[] = $clean;
            }
        }
        $payload['security_force_2fa_roles'] = $safeRoles === [] ? 'admin' : implode(',', array_unique($safeRoles));

        if ($payload['seo_global_schema_json'] !== '') {
            json_decode($payload['seo_global_schema_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['flash_error'] = 'Global schema JSON is invalid.';
                redirect('/admin/settings');
            }
        }

        if ($payload['seo_internal_links_json'] !== '') {
            json_decode($payload['seo_internal_links_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['flash_error'] = 'Internal links JSON is invalid.';
                redirect('/admin/settings');
            }
        }

        Setting::upsertMany(array_map('strval', $payload));
        $_SESSION['flash_success'] = 'Settings updated.';
        redirect('/admin/settings');
    }
}
