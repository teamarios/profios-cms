<?php
$checks = $checks ?? [];
$snapshot = $snapshot ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Ops Status</h1>
    <button id="run-all-checks" class="btn btn-primary">Run All Live Tests</button>
</div>

<div class="alert alert-info">
    This screen validates production readiness for CDN/WAF, SSL/HSTS, cache stack, backups, monitoring, logs, and SEO integrations.
</div>

<input type="hidden" id="ops-csrf" value="<?= e(\App\Core\Csrf::token()) ?>">

<div class="row g-3">
    <?php foreach ($checks as $id => $label): ?>
        <?php $row = $snapshot[$id] ?? ['ok' => false, 'message' => 'Not checked']; ?>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0"><?= e((string) $label) ?></h2>
                        <span class="badge text-bg-<?= ($row['ok'] ?? false) ? 'success' : 'warning' ?>" id="badge-<?= e((string) $id) ?>">
                            <?= ($row['ok'] ?? false) ? 'PASS' : 'ACTION' ?>
                        </span>
                    </div>
                    <p class="small text-muted mb-1 mt-2" id="msg-<?= e((string) $id) ?>"><?= e((string) ($row['message'] ?? 'Not checked')) ?></p>
                    <p class="small mb-2" id="rec-<?= e((string) $id) ?>">
                        <?= e((string) ($row['recommendation'] ?? '')) ?>
                    </p>
                    <button class="btn btn-outline-primary btn-sm ops-run-btn" data-check="<?= e((string) $id) ?>">Run Live Test</button>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
const csrf = document.getElementById('ops-csrf').value;
const allButtons = Array.from(document.querySelectorAll('.ops-run-btn'));
const runAllBtn = document.getElementById('run-all-checks');

function updateCard(check, result) {
    const badge = document.getElementById('badge-' + check);
    const msg = document.getElementById('msg-' + check);
    const rec = document.getElementById('rec-' + check);
    if (!badge || !msg || !rec) return;

    badge.classList.remove('text-bg-success', 'text-bg-warning');
    badge.classList.add(result.ok ? 'text-bg-success' : 'text-bg-warning');
    badge.textContent = result.ok ? 'PASS' : 'ACTION';
    msg.textContent = result.message || '';
    rec.textContent = result.recommendation || '';
}

async function runCheck(check) {
    const form = new URLSearchParams();
    form.set('_csrf', csrf);

    const response = await fetch('/admin/ops/test/' + encodeURIComponent(check), {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: form.toString(),
        credentials: 'same-origin'
    });

    if (!response.ok) {
        throw new Error('Check failed with status ' + response.status);
    }

    const data = await response.json();
    updateCard(check, data.result || { ok: false, message: 'Invalid response', recommendation: '' });
}

allButtons.forEach((button) => {
    button.addEventListener('click', async () => {
        const check = button.getAttribute('data-check');
        button.disabled = true;
        try {
            await runCheck(check);
        } catch (e) {
            updateCard(check, { ok: false, message: 'Live test failed.', recommendation: String(e) });
        } finally {
            button.disabled = false;
        }
    });
});

runAllBtn.addEventListener('click', async () => {
    runAllBtn.disabled = true;
    for (const button of allButtons) {
        const check = button.getAttribute('data-check');
        try {
            await runCheck(check);
        } catch (e) {
            updateCard(check, { ok: false, message: 'Live test failed.', recommendation: String(e) });
        }
    }
    runAllBtn.disabled = false;
});
</script>
