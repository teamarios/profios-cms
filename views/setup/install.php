<?php
$old = $_SESSION['setup_old'] ?? [];
unset($_SESSION['setup_old']);
$values = array_merge($defaults ?? [], $old);
$autoCreds = ($values['stack_auto_credentials'] ?? 'false') === 'true';
?>
<div class="container py-4" style="max-width: 900px;">
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 mb-2">Post-Install SEO and Tracking Checklist</h2>
            <ol class="mb-0">
                <li>Complete installation and login to `/admin`.</li>
                <li>Open `SEO, Analytics & Security` settings.</li>
                <li>Add Search Console verification token.</li>
                <li>Add GA4 Measurement ID and SGTM transport URL.</li>
                <li>Add GTM container and server GTM URL.</li>
                <li>Add Sentry DSN, environment, release, and sample rate.</li>
                <li>Review production toggles: CDN, OPcache, Brotli/Gzip, non-prod noindex.</li>
                <li>Open `Performance Center` and apply all recommended toggles.</li>
            </ol>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 mb-2">Server Stack Installation Progress</h2>
            <div class="progress" role="progressbar" aria-label="Install progress">
                <div id="install-progress-bar" class="progress-bar" style="width: 0%">0%</div>
            </div>
            <p id="install-progress-message" class="small text-muted mb-0 mt-2">Waiting for installer status...</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h3 mb-2">CMS Setup Wizard</h1>
            <p class="text-muted mb-4">Fill all fields once. The installer writes `.env` and stores runtime config in database.</p>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <form method="post" action="/setup/install" class="row g-3">
                <?= \App\Core\Csrf::input() ?>
                <input type="text" name="contact_url" value="" style="display:none" tabindex="-1" autocomplete="off">

                <div class="col-12"><h2 class="h5 mt-2">Application</h2></div>
                <div class="col-md-6">
                    <label class="form-label">App Name</label>
                    <input name="app_name" class="form-control" required value="<?= e($values['app_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">App URL</label>
                    <input name="app_url" class="form-control" required value="<?= e($values['app_url'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Environment</label>
                    <select name="app_env" class="form-select">
                        <?php $env = $values['app_env'] ?? 'production'; ?>
                        <option value="production" <?= $env === 'production' ? 'selected' : '' ?>>production</option>
                        <option value="local" <?= $env === 'local' ? 'selected' : '' ?>>local</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="app_debug" id="app_debug" <?= ($values['app_debug'] ?? '') === 'true' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="app_debug">Enable debug mode</label>
                    </div>
                </div>

                <div class="col-12"><h2 class="h5 mt-3">Database</h2></div>
                <?php if ($autoCreds): ?>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">Database credentials were auto-generated and pre-configured by stack installer.</div>
                        <input type="hidden" name="db_host" value="<?= e($values['db_host'] ?? '') ?>">
                        <input type="hidden" name="db_port" value="<?= e($values['db_port'] ?? '') ?>">
                        <input type="hidden" name="db_name" value="<?= e($values['db_name'] ?? '') ?>">
                        <input type="hidden" name="db_user" value="<?= e($values['db_user'] ?? '') ?>">
                        <input type="hidden" name="db_pass" value="<?= e($values['db_pass'] ?? '') ?>">
                    </div>
                <?php else: ?>
                    <div class="col-md-4">
                        <label class="form-label">DB Host</label>
                        <input name="db_host" class="form-control" required value="<?= e($values['db_host'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Port</label>
                        <input name="db_port" class="form-control" required value="<?= e($values['db_port'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Database</label>
                        <input name="db_name" class="form-control" required value="<?= e($values['db_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input name="db_user" class="form-control" required value="<?= e($values['db_user'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input name="db_pass" type="password" class="form-control" value="<?= e($values['db_pass'] ?? '') ?>">
                    </div>
                <?php endif; ?>

                <div class="col-12"><h2 class="h5 mt-3">Admin Account</h2></div>
                <div class="col-md-4">
                    <label class="form-label">Admin Name</label>
                    <input name="admin_name" class="form-control" required value="<?= e($values['admin_name'] ?? 'Admin User') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Admin Email</label>
                    <input name="admin_email" type="email" class="form-control" required value="<?= e($values['admin_email'] ?? 'admin@example.com') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Admin Password</label>
                    <input name="admin_pass" type="password" class="form-control" minlength="8" required>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="admin_totp_enabled" id="admin_totp_enabled" <?= ($values['admin_totp_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="admin_totp_enabled">Enable 2FA (Google Authenticator)</label>
                    </div>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Google Authenticator Secret</label>
                    <input name="admin_totp_secret" class="form-control" value="<?= e($values['admin_totp_secret'] ?? '') ?>" pattern="[A-Z2-7]{16,64}">
                    <small class="text-muted">Add this secret in Google Authenticator manually (key entry).</small>
                </div>
                <div class="col-md-12">
                    <?php
                    $issuer = $values['app_name'] ?? 'Profios CMS';
                    $email = $values['admin_email'] ?? 'admin@example.com';
                    $secret = $values['admin_totp_secret'] ?? '';
                    $totpEnabled = ($values['admin_totp_enabled'] ?? '1') === '1';
                    ?>
                    <?php if ($totpEnabled): ?>
                        <small class="text-muted">OTP URI: <code><?= e(\App\Services\TotpService::getOtpAuthUri((string) $secret, (string) $email, (string) $issuer)) ?></code></small>
                    <?php endif; ?>
                </div>

                <div class="col-12"><h2 class="h5 mt-3">Caching</h2></div>
                <div class="col-md-4">
                    <label class="form-label">Backend Cache Driver</label>
                    <?php $cacheDriver = $values['cache_driver'] ?? 'redis'; ?>
                    <select name="cache_driver" class="form-select">
                        <option value="redis" <?= $cacheDriver === 'redis' ? 'selected' : '' ?>>redis (recommended)</option>
                        <option value="file" <?= $cacheDriver === 'file' ? 'selected' : '' ?>>file fallback</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Frontend Cache Strategy</label>
                    <input name="frontend_cache_driver" class="form-control" value="<?= e($values['frontend_cache_driver'] ?? 'varnish') ?>">
                </div>
                <?php if ($autoCreds): ?>
                    <div class="col-md-4">
                        <label class="form-label">Session Name</label>
                        <input class="form-control" value="<?= e($values['session_name'] ?? '') ?>" readonly>
                        <input type="hidden" name="session_name" value="<?= e($values['session_name'] ?? '') ?>">
                        <small class="text-muted">Auto-generated by installer.</small>
                    </div>
                <?php else: ?>
                    <div class="col-md-4">
                        <label class="form-label">Session Name</label>
                        <input name="session_name" class="form-control" value="<?= e($values['session_name'] ?? 'profios_cms_session') ?>">
                    </div>
                <?php endif; ?>
                <?php if ($autoCreds): ?>
                    <div class="col-12">
                        <div class="alert alert-info mb-0">Redis credentials were auto-generated and pre-configured by stack installer.</div>
                        <input type="hidden" name="redis_host" value="<?= e($values['redis_host'] ?? '127.0.0.1') ?>">
                        <input type="hidden" name="redis_port" value="<?= e($values['redis_port'] ?? '6379') ?>">
                        <input type="hidden" name="redis_db" value="<?= e($values['redis_db'] ?? '0') ?>">
                        <input type="hidden" name="redis_pass" value="<?= e($values['redis_pass'] ?? '') ?>">
                    </div>
                <?php else: ?>
                    <div class="col-md-4">
                        <label class="form-label">Redis Host</label>
                        <input name="redis_host" class="form-control" value="<?= e($values['redis_host'] ?? '127.0.0.1') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Redis Port</label>
                        <input name="redis_port" class="form-control" value="<?= e($values['redis_port'] ?? '6379') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Redis DB</label>
                        <input name="redis_db" class="form-control" value="<?= e($values['redis_db'] ?? '0') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Redis Password</label>
                        <input name="redis_pass" type="password" class="form-control" value="<?= e($values['redis_pass'] ?? '') ?>">
                    </div>
                <?php endif; ?>

                <div class="col-12 mt-4">
                    <button class="btn btn-primary btn-lg" type="submit">Install CMS</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const progressBar = document.getElementById('install-progress-bar');
const progressMessage = document.getElementById('install-progress-message');

async function refreshInstallProgress() {
    try {
        const res = await fetch('/setup/progress', { cache: 'no-store' });
        if (!res.ok) return;
        const data = await res.json();
        const pct = Math.max(0, Math.min(100, Number(data.percent || 0)));
        progressBar.style.width = pct + '%';
        progressBar.textContent = pct + '%';
        if (data.status === 'failed') {
            progressBar.classList.remove('bg-success');
            progressBar.classList.add('bg-danger');
        } else if (data.status === 'completed') {
            progressBar.classList.remove('bg-danger');
            progressBar.classList.add('bg-success');
        }
        progressMessage.textContent = (data.message || 'Installer status unavailable') + (data.updated_at ? ` (updated ${data.updated_at})` : '');
    } catch (e) {
        // ignore transient polling errors
    }
}

refreshInstallProgress();
setInterval(refreshInstallProgress, 2500);
</script>
