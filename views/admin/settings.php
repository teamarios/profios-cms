<?php $s = $settings ?? []; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">SEO, Analytics & Security Settings</h1>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<form method="post" action="/admin/settings/update" class="row g-3">
    <?= \App\Core\Csrf::input() ?>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Tracking, Search Console and Tag Delivery</h2>
                <div class="mb-3">
                    <label class="form-label">Google Site Verification Code</label>
                    <input class="form-control" name="google_site_verification" placeholder="abc123XYZ" value="<?= e($s['google_site_verification'] ?? '') ?>">
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">GA4 Measurement ID</label>
                        <input class="form-control" name="ga4_measurement_id" placeholder="G-XXXXXXXXXX" value="<?= e($s['ga4_measurement_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">GA4 Transport URL (SGTM endpoint)</label>
                        <input class="form-control" name="ga4_transport_url" placeholder="https://gtm.yourdomain.com" value="<?= e($s['ga4_transport_url'] ?? '') ?>">
                        <small class="text-muted">Use your server-side GTM domain for first-party analytics delivery.</small>
                    </div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">GTM Container ID</label>
                        <input class="form-control" name="gtm_container_id" placeholder="GTM-XXXXXXX" value="<?= e($s['gtm_container_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Server-side GTM URL</label>
                        <input class="form-control" name="gtm_server_url" placeholder="https://gtm.yourdomain.com" value="<?= e($s['gtm_server_url'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Header Code (Custom tags/pixels)</label>
                    <textarea class="form-control" name="seo_header_code" rows="4" placeholder="<script>...</script>"><?= e($s['seo_header_code'] ?? '') ?></textarea>
                </div>
                <div class="mb-0">
                    <label class="form-label">Footer Code</label>
                    <textarea class="form-control" name="seo_footer_code" rows="4" placeholder="<script>...</script>"><?= e($s['seo_footer_code'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Sentry Monitoring</h2>
                <div class="mb-3">
                    <label class="form-label">Sentry DSN</label>
                    <input class="form-control" name="sentry_dsn" placeholder="https://public@o0.ingest.sentry.io/0" value="<?= e($s['sentry_dsn'] ?? '') ?>">
                </div>
                <div class="row g-3 mb-2">
                    <div class="col-md-4">
                        <label class="form-label">Sentry Environment</label>
                        <input class="form-control" name="sentry_environment" value="<?= e($s['sentry_environment'] ?? config('env', 'production')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sentry Release</label>
                        <input class="form-control" name="sentry_release" placeholder="2026.02.12" value="<?= e($s['sentry_release'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Trace Sample Rate (0-1)</label>
                        <input class="form-control" name="sentry_traces_sample_rate" value="<?= e($s['sentry_traces_sample_rate'] ?? '0.20') ?>">
                    </div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="perf_rum_web_vitals" id="perf_rum_web_vitals" <?= ($s['perf_rum_web_vitals'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="perf_rum_web_vitals">Enable Real User Monitoring (Web Vitals beacon)</label>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Schema, Internal Links, Geotagging</h2>
                <div class="mb-3">
                    <label class="form-label">Global Schema JSON-LD</label>
                    <textarea class="form-control" name="seo_global_schema_json" rows="6" placeholder='[{"@context":"https://schema.org","@type":"Organization"}]'><?= e($s['seo_global_schema_json'] ?? '') ?></textarea>
                    <small class="text-muted">Use JSON object or array.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Internal Links JSON</label>
                    <textarea class="form-control" name="seo_internal_links_json" rows="5" placeholder='[{"anchor":"SEO Services","url":"/page/seo-services"}]'><?= e($s['seo_internal_links_json'] ?? '[]') ?></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Latitude</label>
                        <input class="form-control" name="seo_geo_latitude" value="<?= e($s['seo_geo_latitude'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Longitude</label>
                        <input class="form-control" name="seo_geo_longitude" value="<?= e($s['seo_geo_longitude'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Region / Place</label>
                        <input class="form-control" name="seo_geo_region" placeholder="IN-TN, Chennai" value="<?= e($s['seo_geo_region'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Production Operations</h2>
                <div class="row g-3">
                    <div class="col-md-6 form-check">
                        <input class="form-check-input" type="checkbox" name="ops_cdn_enabled" id="ops_cdn_enabled" <?= ($s['ops_cdn_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ops_cdn_enabled">CDN enabled (Cloudflare/Fastly/etc.)</label>
                    </div>
                    <div class="col-md-6 form-check">
                        <input class="form-check-input" type="checkbox" name="ops_opcache_enabled" id="ops_opcache_enabled" <?= ($s['ops_opcache_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ops_opcache_enabled">PHP OPcache enabled</label>
                    </div>
                    <div class="col-md-6 form-check">
                        <input class="form-check-input" type="checkbox" name="ops_brotli_enabled" id="ops_brotli_enabled" <?= ($s['ops_brotli_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ops_brotli_enabled">Brotli/Gzip compression enabled</label>
                    </div>
                    <div class="col-md-6 form-check">
                        <input class="form-check-input" type="checkbox" name="seo_robots_noindex_nonprod" id="seo_robots_noindex_nonprod" <?= ($s['seo_robots_noindex_nonprod'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="seo_robots_noindex_nonprod">Noindex robots on non-production environments</label>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-7">
                        <label class="form-label">CDN Base URL</label>
                        <input class="form-control" name="ops_cdn_base_url" placeholder="https://cdn.example.com" value="<?= e($s['ops_cdn_base_url'] ?? '') ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Ops Alert Email</label>
                        <input class="form-control" name="ops_monitoring_alert_email" placeholder="ops@example.com" value="<?= e($s['ops_monitoring_alert_email'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Security and Spam Control</h2>
                <div class="mb-2 form-check">
                    <input class="form-check-input" type="checkbox" name="security_hsts_enabled" id="security_hsts_enabled" <?= ($s['security_hsts_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="security_hsts_enabled">Enable HSTS header</label>
                </div>
                <div class="mb-2 form-check">
                    <input class="form-check-input" type="checkbox" name="security_xss_protection" id="security_xss_protection" <?= ($s['security_xss_protection'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="security_xss_protection">Enable legacy X-XSS-Protection header</label>
                </div>
                <div class="mb-2 form-check">
                    <input class="form-check-input" type="checkbox" name="security_force_https" id="security_force_https" <?= ($s['security_force_https'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="security_force_https">Force HTTPS redirects</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Force 2FA Roles</label>
                    <input class="form-control" name="security_force_2fa_roles" value="<?= e($s['security_force_2fa_roles'] ?? 'admin') ?>" placeholder="admin,editor">
                    <small class="text-muted">Comma-separated roles that must use 2FA.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Content-Security-Policy</label>
                    <textarea class="form-control" name="security_csp" rows="4"><?= e($s['security_csp'] ?? "default-src 'self' https: data: 'unsafe-inline'; frame-ancestors 'self';") ?></textarea>
                </div>

                <hr>

                <div class="mb-2 form-check">
                    <input class="form-check-input" type="checkbox" name="spam_honeypot_enabled" id="spam_honeypot_enabled" <?= ($s['spam_honeypot_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="spam_honeypot_enabled">Enable honeypot fields</label>
                </div>
                <div class="mb-2 form-check">
                    <input class="form-check-input" type="checkbox" name="spam_rate_limit_enabled" id="spam_rate_limit_enabled" <?= ($s['spam_rate_limit_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="spam_rate_limit_enabled">Enable login rate limiting</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Max Attempts</label>
                        <input class="form-control" type="number" min="3" name="spam_rate_limit_max" value="<?= e($s['spam_rate_limit_max'] ?? '8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Window (seconds)</label>
                        <input class="form-control" type="number" min="60" name="spam_rate_limit_window" value="<?= e($s['spam_rate_limit_window'] ?? '900') ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <button class="btn btn-primary btn-lg" type="submit">Save Settings</button>
    </div>
</form>
