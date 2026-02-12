<div class="container" style="max-width: 480px; margin-top: 80px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <h1 class="h4 mb-3">Admin Login</h1>
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success"><?= e($_SESSION['flash_success']) ?></div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_backup_codes']) && is_array($_SESSION['flash_backup_codes'])): ?>
                <div class="alert alert-warning">
                    <strong>Save your backup codes:</strong><br>
                    <?= e(implode(', ', $_SESSION['flash_backup_codes'])) ?>
                </div>
                <?php unset($_SESSION['flash_backup_codes']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>
            <form method="post" action="/admin/login">
                <?= \App\Core\Csrf::input() ?>
                <input type="text" name="company_website" value="" style="display:none" tabindex="-1" autocomplete="off">
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">2FA Code or Backup Code</label>
                    <input type="text" name="otp_code" class="form-control" placeholder="Enter 6-digit OTP or backup code">
                </div>
                <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </form>
        </div>
    </div>
</div>
