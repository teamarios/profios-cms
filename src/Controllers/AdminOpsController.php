<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\View;
use App\Services\OpsStatusService;

final class AdminOpsController
{
    public function index(): void
    {
        Auth::requireLogin();

        View::render('admin.ops', [
            'title' => 'Ops Status',
            'checks' => OpsStatusService::checks(),
            'snapshot' => OpsStatusService::snapshot(),
        ], 'admin');
    }

    public function test(string $check): void
    {
        Auth::requireLogin();
        Csrf::verifyOrFail();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'check' => $check,
            'result' => OpsStatusService::run($check),
        ], JSON_UNESCAPED_UNICODE);
    }
}
