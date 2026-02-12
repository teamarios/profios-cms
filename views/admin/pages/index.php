<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Pages</h1>
    <a href="/admin/pages/create" class="btn btn-primary">New Page</a>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Slug</th>
                <th>Status</th>
                <th>SEO</th>
                <th>Updated</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($pages as $page): ?>
                <tr>
                    <td><?= (int) $page['id'] ?></td>
                    <td><?= e($page['title']) ?></td>
                    <td><code><?= e($page['slug']) ?></code></td>
                    <td><span class="badge text-bg-<?= $page['status'] === 'published' ? 'success' : 'secondary' ?>"><?= e($page['status']) ?></span></td>
                    <?php
                    $score = (int) ($page['seo_score'] ?? 0);
                    $issues = json_decode((string) ($page['seo_issues_json'] ?? '[]'), true);
                    $issueCount = is_array($issues) ? count($issues) : 0;
                    ?>
                    <td>
                        <span class="badge text-bg-<?= $score >= 80 ? 'success' : ($score >= 60 ? 'warning' : 'danger') ?>"><?= $score ?>/100</span>
                        <small class="text-muted"><?= $issueCount ?> issues</small>
                    </td>
                    <td><?= e($page['updated_at']) ?></td>
                    <td class="text-end">
                        <a href="/page/<?= e($page['slug']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>
                        <a href="/admin/pages/<?= (int) $page['id'] ?>/edit" class="btn btn-sm btn-outline-primary">Edit</a>
                        <form class="d-inline" method="post" action="/admin/pages/<?= (int) $page['id'] ?>/delete" onsubmit="return confirm('Delete this page?');">
                            <?= \App\Core\Csrf::input() ?>
                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
