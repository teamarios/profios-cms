<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Admin') ?> | <?= e(config('name')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fa; }
        .sidebar { min-height: 100vh; background: #111827; }
        .sidebar a { color: #d1d5db; text-decoration: none; display: block; padding: 0.65rem 1rem; }
        .sidebar a:hover { background: #1f2937; color: #fff; }
        .content-wrap { padding: 1.5rem; }
        .block-card { border: 1px solid #dee2e6; border-radius: 0.5rem; background: #fff; margin-bottom: 0.75rem; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-md-2 sidebar p-0">
            <h6 class="text-light px-3 py-3 border-bottom border-secondary"><?= e(config('name')) ?></h6>
            <a href="/admin">Dashboard</a>
            <a href="/admin/pages">Pages</a>
            <a href="/admin/pages/create">Create Page</a>
            <a href="/admin/settings">SEO & Security</a>
            <a href="/admin/security">My Security</a>
            <a href="/admin/performance">Performance</a>
            <a href="/admin/updates">Updates</a>
            <a href="/admin/logout">Logout</a>
        </aside>
        <section class="col-md-10 content-wrap">
            <?= $content ?>
        </section>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" defer></script>
</body>
</html>
