<?php $twoFaEnabled = (int) ($user['totp_enabled'] ?? 0) === 1; ?>
<?php
$forceRoles = array_filter(array_map('trim', explode(',', strtolower((string) setting('security_force_2fa_roles', 'admin')))));
$role = strtolower((string) ($user['role'] ?? 'admin'));
$roleRequires2fa = in_array($role, $forceRoles, true);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Account Security</h1>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_backup_codes']) && is_array($_SESSION['flash_backup_codes'])): ?>
    <div class="alert alert-warning">
        <strong>New backup codes (save now):</strong><br>
        <?= e(implode(', ', $_SESSION['flash_backup_codes'])) ?>
    </div>
    <?php unset($_SESSION['flash_backup_codes']); ?>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5">Two-Factor Authentication</h2>
        <p class="mb-2">Role: <span class="badge text-bg-dark"><?= e((string) ($user['role'] ?? 'admin')) ?></span></p>
        <p class="mb-2">Status: <span class="badge text-bg-<?= $twoFaEnabled ? 'success' : 'secondary' ?>"><?= $twoFaEnabled ? 'Enabled' : 'Disabled' ?></span></p>
        <p class="mb-2">Backup codes remaining: <strong><?= (int) $backupCount ?></strong></p>

        <?php if ($twoFaEnabled): ?>
            <div class="mb-3">
                <label class="form-label">Current Secret (for Google Authenticator)</label>
                <input class="form-control" value="<?= e((string) ($user['totp_secret'] ?? '')) ?>" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">OTP URI</label>
                <textarea class="form-control" rows="2" readonly><?= e($otpUri) ?></textarea>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="/admin/security/rotate-2fa">
                <?= \App\Core\Csrf::input() ?>
                <button class="btn btn-primary" type="submit">Enable / Rotate 2FA</button>
            </form>

            <form method="post" action="/admin/security/backup-codes">
                <?= \App\Core\Csrf::input() ?>
                <button class="btn btn-outline-primary" type="submit">Regenerate Backup Codes</button>
            </form>

            <form method="post" action="/admin/security/disable-2fa" onsubmit="return confirm('Disable 2FA for this account?');">
                <?= \App\Core\Csrf::input() ?>
                <button class="btn btn-outline-danger" type="submit" <?= $roleRequires2fa ? 'disabled' : '' ?>>Disable 2FA</button>
            </form>
        </div>
        <?php if ($roleRequires2fa): ?>
            <small class="text-muted d-block mt-2">2FA is mandatory for role policy: <?= e((string) setting('security_force_2fa_roles', 'admin')) ?></small>
        <?php endif; ?>
    </div>
</div>
