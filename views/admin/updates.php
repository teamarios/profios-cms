<?php $s = $settings ?? []; $st = $status ?? []; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">GitHub Updates</h1>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger"><?= e($_SESSION['flash_error']) ?></div>
    <?php unset($_SESSION['flash_error']); ?>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Update Configuration</h2>
                <form method="post" action="/admin/updates/config" class="row g-2">
                    <?= \App\Core\Csrf::input() ?>
                    <div class="col-12 form-check">
                        <input class="form-check-input" type="checkbox" id="ops_updates_enabled" name="ops_updates_enabled" <?= ($s['ops_updates_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="ops_updates_enabled">Enable web-triggered git updates</label>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Repo URL</label>
                        <input class="form-control" name="ops_repo_url" value="<?= e($s['ops_repo_url'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Branch</label>
                        <input class="form-control" name="ops_branch" value="<?= e($s['ops_branch'] ?? 'main') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Git User Name</label>
                        <input class="form-control" name="ops_git_user_name" value="<?= e($s['ops_git_user_name'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Git User Email</label>
                        <input class="form-control" name="ops_git_user_email" value="<?= e($s['ops_git_user_email'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit">Save Config</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5">Repository Status</h2>
                <p class="mb-1"><strong>Available:</strong> <?= !empty($st['available']) ? 'Yes' : 'No' ?></p>
                <p class="mb-1"><strong>Branch:</strong> <?= e((string) ($st['branch'] ?? '-')) ?></p>
                <p class="mb-1"><strong>Remote:</strong> <?= e((string) ($st['remote'] ?? '-')) ?></p>
                <p class="mb-0"><strong>Last Commit:</strong> <?= e((string) ($st['last_commit'] ?? ($st['message'] ?? '-'))) ?></p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h5">Actions</h2>
                <form method="post" action="/admin/updates/pull" class="mb-3">
                    <?= \App\Core\Csrf::input() ?>
                    <button class="btn btn-outline-primary" type="submit">Pull Latest from GitHub</button>
                </form>

                <form method="post" action="/admin/updates/push" class="row g-2">
                    <?= \App\Core\Csrf::input() ?>
                    <div class="col-12">
                        <label class="form-label">Commit Message</label>
                        <input class="form-control" name="commit_message" value="CMS automated update">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-outline-success" type="submit">Push Latest to GitHub</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($_SESSION['updates_log']) && is_array($_SESSION['updates_log'])): ?>
    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <h2 class="h5">Last Update Log</h2>
            <pre class="small bg-light p-3 rounded" style="max-height:300px;overflow:auto;"><?= e(implode("\n", $_SESSION['updates_log'])) ?></pre>
        </div>
    </div>
    <?php unset($_SESSION['updates_log']); ?>
<?php endif; ?>
