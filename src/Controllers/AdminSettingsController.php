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
            'title' => 'SEO, Analytics & Security Settings',
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
            'google_site_verification' => trim((string) ($_POST['google_site_verification'] ?? '')),
            'ga4_measurement_id' => strtoupper(trim((string) ($_POST['ga4_measurement_id'] ?? ''))),
            'ga4_transport_url' => rtrim(trim((string) ($_POST['ga4_transport_url'] ?? '')), '/'),
            'gtm_container_id' => strtoupper(trim((string) ($_POST['gtm_container_id'] ?? ''))),
            'gtm_server_url' => trim((string) ($_POST['gtm_server_url'] ?? '')),
            'sentry_dsn' => trim((string) ($_POST['sentry_dsn'] ?? '')),
            'sentry_environment' => trim((string) ($_POST['sentry_environment'] ?? config('env', 'production'))),
            'sentry_release' => trim((string) ($_POST['sentry_release'] ?? '')),
            'sentry_traces_sample_rate' => trim((string) ($_POST['sentry_traces_sample_rate'] ?? '0.2')),
            'perf_rum_web_vitals' => isset($_POST['perf_rum_web_vitals']) ? '1' : '0',
            'security_csp' => (string) ($_POST['security_csp'] ?? "default-src 'self' https: data: 'unsafe-inline'; frame-ancestors 'self';"),
            'security_hsts_enabled' => isset($_POST['security_hsts_enabled']) ? '1' : '0',
            'security_xss_protection' => isset($_POST['security_xss_protection']) ? '1' : '0',
            'security_force_https' => isset($_POST['security_force_https']) ? '1' : '0',
            'security_force_2fa_roles' => strtolower(trim((string) ($_POST['security_force_2fa_roles'] ?? 'admin'))),
            'seo_robots_noindex_nonprod' => isset($_POST['seo_robots_noindex_nonprod']) ? '1' : '0',
            'ops_cdn_enabled' => isset($_POST['ops_cdn_enabled']) ? '1' : '0',
            'ops_cdn_base_url' => rtrim(trim((string) ($_POST['ops_cdn_base_url'] ?? '')), '/'),
            'ops_opcache_enabled' => isset($_POST['ops_opcache_enabled']) ? '1' : '0',
            'ops_brotli_enabled' => isset($_POST['ops_brotli_enabled']) ? '1' : '0',
            'ops_monitoring_alert_email' => trim((string) ($_POST['ops_monitoring_alert_email'] ?? '')),
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

        if ($payload['ga4_measurement_id'] !== '' && !preg_match('/^G-[A-Z0-9]+$/', $payload['ga4_measurement_id'])) {
            $_SESSION['flash_error'] = 'GA4 Measurement ID must look like G-XXXXXXXX.';
            redirect('/admin/settings');
        }
        if ($payload['gtm_container_id'] !== '' && !preg_match('/^GTM-[A-Z0-9]+$/', strtoupper($payload['gtm_container_id']))) {
            $_SESSION['flash_error'] = 'GTM Container ID must look like GTM-XXXXXXX.';
            redirect('/admin/settings');
        }

        if ($payload['ga4_transport_url'] !== '' && !filter_var($payload['ga4_transport_url'], FILTER_VALIDATE_URL)) {
            $_SESSION['flash_error'] = 'GA4 transport URL is invalid.';
            redirect('/admin/settings');
        }
        if ($payload['gtm_server_url'] !== '' && !filter_var($payload['gtm_server_url'], FILTER_VALIDATE_URL)) {
            $_SESSION['flash_error'] = 'Server-side GTM URL is invalid.';
            redirect('/admin/settings');
        }

        if ($payload['sentry_dsn'] !== '' && !preg_match('/^https?:\/\/[^\/]+\/.+$/', $payload['sentry_dsn'])) {
            $_SESSION['flash_error'] = 'Sentry DSN format is invalid.';
            redirect('/admin/settings');
        }

        $sampleRate = (float) $payload['sentry_traces_sample_rate'];
        if ($sampleRate < 0 || $sampleRate > 1) {
            $_SESSION['flash_error'] = 'Sentry sample rate must be between 0 and 1.';
            redirect('/admin/settings');
        }
        $payload['sentry_traces_sample_rate'] = number_format($sampleRate, 2, '.', '');

        if ($payload['ops_cdn_base_url'] !== '' && !filter_var($payload['ops_cdn_base_url'], FILTER_VALIDATE_URL)) {
            $_SESSION['flash_error'] = 'CDN base URL is invalid.';
            redirect('/admin/settings');
        }

        if ($payload['ops_monitoring_alert_email'] !== '' && !filter_var($payload['ops_monitoring_alert_email'], FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash_error'] = 'Monitoring email must be a valid email.';
            redirect('/admin/settings');
        }

        Setting::upsertMany(array_map('strval', $payload));
        $_SESSION['flash_success'] = 'Settings updated.';
        redirect('/admin/settings');
    }
}
