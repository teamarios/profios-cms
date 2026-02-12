<?php $s = $settings ?? []; $auditData = $audit ?? ['score' => 0, 'checks' => []]; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Performance Center</h1>
    <span class="badge text-bg-<?= ((int) $auditData['score']) >= 80 ? 'success' : (((int) $auditData['score']) >= 60 ? 'warning' : 'danger') ?>">Score <?= (int) $auditData['score'] ?>/100</span>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
    <div class="alert alert-success"><?= e($_SESSION['flash_success']) ?></div>
    <?php unset($_SESSION['flash_success']); ?>
<?php endif; ?>

<form method="post" action="/admin/performance/save" class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <?= \App\Core\Csrf::input() ?>
        <h2 class="h5">Optimization Toggles</h2>
        <div class="row g-2">
            <?php
            $labels = [
                'perf_preload_bootstrap_css' => 'Preload CSS (render-blocking reduction)',
                'perf_preconnect_third_party' => 'Preconnect third-party origins',
                'perf_defer_third_party_js' => 'Defer third-party JavaScript',
                'perf_lazy_images' => 'Lazy-load non-critical images',
                'perf_explicit_image_dimensions' => 'Set explicit image width/height',
                'perf_preload_lcp_image' => 'Preload LCP hero image',
                'perf_reduce_unused_css' => 'Reduce unused CSS',
                'perf_reduce_unused_js' => 'Reduce unused JavaScript',
                'perf_minify_css' => 'Minify CSS output',
                'perf_minify_html' => 'Minify HTML output',
                'perf_font_display_swap' => 'Use font-display: swap policy',
                'perf_enable_long_cache_assets' => 'Use efficient cache lifetimes',
                'perf_avoid_non_composited_animations' => 'Avoid non-composited animations',
                'perf_limit_third_party_scripts' => 'Limit third-party scripts',
                'perf_user_timing_marks' => 'Enable User Timing marks/measures',
                'perf_rum_web_vitals' => 'Enable Web Vitals RUM beacon',
                'ops_cdn_enabled' => 'CDN/edge cache active',
                'ops_opcache_enabled' => 'PHP OPcache enabled',
                'ops_brotli_enabled' => 'Brotli/Gzip compression enabled',
            ];
            ?>
            <?php foreach ($labels as $key => $label): ?>
                <div class="col-md-6 form-check">
                    <input class="form-check-input" type="checkbox" id="<?= e($key) ?>" name="<?= e($key) ?>" <?= ($s[$key] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="<?= e($key) ?>"><?= e($label) ?></label>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn btn-primary mt-3" type="submit">Save Performance Settings</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h2 class="h5">PageSpeed Recommendation Matrix</h2>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Metric / Opportunity</th>
                    <th>Status</th>
                    <th>Action Hint</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($auditData['checks'] ?? []) as $metric => $row): ?>
                    <tr>
                        <td><?= e((string) $metric) ?></td>
                        <td><span class="badge text-bg-<?= ($row['status'] ?? '') === 'ok' ? 'success' : 'warning' ?>"><?= e((string) ($row['status'] ?? '-')) ?></span></td>
                        <td><?= e((string) ($row['hint'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
