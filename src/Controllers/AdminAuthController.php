<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\RateLimiter;
use App\Core\View;

final class AdminAuthController
{
    public function loginForm(): void
    {
        if (Auth::check()) {
            redirect('/admin');
        }

        View::render('admin.login', ['title' => 'Admin Login'], 'auth');
    }

    public function login(): void
    {
        Csrf::verifyOrFail();

        $honeypotEnabled = setting('spam_honeypot_enabled', '1') === '1';
        if ($honeypotEnabled && !empty($_POST['company_website'] ?? '')) {
            usleep(300000);
            $_SESSION['flash_error'] = 'Login failed.';
            redirect('/admin/login');
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $otp = trim((string) ($_POST['otp_code'] ?? ''));
        $ip = clientIp();
        $key = 'login_' . md5(strtolower($email) . '|' . $ip);

        $rateLimitEnabled = setting('spam_rate_limit_enabled', '1') === '1';
        $maxAttempts = (int) setting('spam_rate_limit_max', '8');
        $windowSeconds = (int) setting('spam_rate_limit_window', '900');
        if ($rateLimitEnabled && !RateLimiter::hit($key, max(3, $maxAttempts), max(60, $windowSeconds))) {
            $_SESSION['flash_error'] = 'Too many login attempts. Please try later.';
            redirect('/admin/login');
        }

        $user = Auth::credentials($email, $password);
        if ($user === null) {
            $_SESSION['flash_error'] = 'Invalid credentials.';
            redirect('/admin/login');
        }

        $role = strtolower((string) ($user['role'] ?? 'admin'));
        $forceRoles = array_filter(array_map('trim', explode(',', strtolower((string) setting('security_force_2fa_roles', 'admin')))));
        $policyRequiresTotp = in_array($role, $forceRoles, true);

        if ($policyRequiresTotp && !Auth::requiresTotp($user)) {
            $_SESSION['flash_error'] = '2FA is required for your role. Contact administrator.';
            redirect('/admin/login');
        }

        if (($policyRequiresTotp || Auth::requiresTotp($user)) && !Auth::verifyTotpOrBackup($user, $otp)) {
            $_SESSION['flash_error'] = 'Invalid 2FA or backup code.';
            redirect('/admin/login');
        }

        Auth::login($user);
        if ($rateLimitEnabled) {
            RateLimiter::clear($key);
        }
        redirect('/admin');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('/admin/login');
    }
}
