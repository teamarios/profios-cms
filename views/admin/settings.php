<?php $s = $settings ?? []; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">SEO & Security Settings</h1>
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
                <h2 class="h5">Tracking and Code Injection</h2>
                <div class="mb-3">
                    <label class="form-label">Header Code (GA4, Search Console, Pixels)</label>
                    <textarea class="form-control" name="seo_header_code" rows="5" placeholder="<script>...</script>"><?= e($s['seo_header_code'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Footer Code</label>
                    <textarea class="form-control" name="seo_footer_code" rows="5" placeholder="<script>...</script>"><?= e($s['seo_footer_code'] ?? '') ?></textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">GTM Container ID</label>
                        <input class="form-control" name="gtm_container_id" placeholder="GTM-XXXXXXX" value="<?= e($s['gtm_container_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Server-side GTM URL</label>
                        <input class="form-control" name="gtm_server_url" placeholder="https://gtm.yourdomain.com" value="<?= e($s['gtm_server_url'] ?? '') ?>">
                    </div>
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
