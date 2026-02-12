<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Models\User;
use App\Services\BackupCodeService;
use App\Services\TotpService;

final class AdminSecurityController
{
    public function index(): void
    {
        Auth::requireLogin();

        $authUser = Auth::user();
        $user = User::find((int) ($authUser['id'] ?? 0));
        if (!$user) {
            Auth::logout();
            redirect('/admin/login');
        }

        $backupCodes = json_decode((string) ($user['backup_codes_json'] ?? '[]'), true);
        $backupCount = is_array($backupCodes) ? count($backupCodes) : 0;

        View::render('admin.security', [
            'title' => 'Security',
            'user' => $user,
            'backupCount' => $backupCount,
            'otpUri' => TotpService::getOtpAuthUri((string) ($user['totp_secret'] ?? ''), (string) $user['email'], (string) config('name')),
        ], 'admin');
    }

    public function rotateTwoFactor(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $authUser = Auth::user();
        $user = User::find((int) ($authUser['id'] ?? 0));
        if (!$user) {
            redirect('/admin/login');
        }

        $secret = TotpService::generateSecret();
        $plainBackupCodes = BackupCodeService::generate(8);
        $hashed = BackupCodeService::hashCodes($plainBackupCodes);

        User::updateTwoFactor((int) $user['id'], $secret, 1, json_encode($hashed, JSON_UNESCAPED_UNICODE));

        $_SESSION['flash_success'] = '2FA secret rotated. Update your authenticator app.';
        $_SESSION['flash_backup_codes'] = $plainBackupCodes;
        redirect('/admin/security');
    }

    public function disableTwoFactor(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $authUser = Auth::user();
        $user = User::find((int) ($authUser['id'] ?? 0));
        if (!$user) {
            redirect('/admin/login');
        }

        $role = strtolower((string) ($user['role'] ?? 'admin'));
        $forceRoles = array_filter(array_map('trim', explode(',', strtolower((string) setting('security_force_2fa_roles', 'admin')))));
        if (in_array($role, $forceRoles, true)) {
            $_SESSION['flash_error'] = '2FA cannot be disabled for your role due to policy.';
            redirect('/admin/security');
        }

        User::updateTwoFactor((int) $user['id'], '', 0, json_encode([], JSON_UNESCAPED_UNICODE));
        $_SESSION['flash_success'] = '2FA disabled.';
        redirect('/admin/security');
    }

    public function regenerateBackupCodes(): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        $authUser = Auth::user();
        $user = User::find((int) ($authUser['id'] ?? 0));
        if (!$user) {
            redirect('/admin/login');
        }

        if ((int) ($user['totp_enabled'] ?? 0) !== 1 || trim((string) ($user['totp_secret'] ?? '')) === '') {
            $_SESSION['flash_error'] = 'Enable 2FA before generating backup codes.';
            redirect('/admin/security');
        }

        $plainBackupCodes = BackupCodeService::generate(8);
        $hashed = BackupCodeService::hashCodes($plainBackupCodes);
        User::updateBackupCodes((int) $user['id'], json_encode($hashed, JSON_UNESCAPED_UNICODE));

        $_SESSION['flash_success'] = 'Backup codes regenerated.';
        $_SESSION['flash_backup_codes'] = $plainBackupCodes;
        redirect('/admin/security');
    }
}
