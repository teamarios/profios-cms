<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <a href="/admin/pages/create" class="btn btn-primary">Create Page</a>
</div>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted">Total Pages</h2>
                <div class="display-6"><?= (int) $pagesCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted">Published Pages</h2>
                <div class="display-6"><?= (int) $publishedCount ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-muted">Production Integrations</h2>
                <?php foreach (($integrations ?? []) as $name => $enabled): ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= e((string) $name) ?></span>
                        <span class="badge text-bg-<?= $enabled ? 'success' : 'secondary' ?>"><?= $enabled ? 'On' : 'Off' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
