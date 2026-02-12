<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($meta['title'] ?? config('name')) ?></title>
    <?php if (!empty($meta['description'])): ?>
        <meta name="description" content="<?= e($meta['description']) ?>">
    <?php endif; ?>
    <?php if (!empty($meta['canonical'])): ?>
        <link rel="canonical" href="<?= e($meta['canonical']) ?>">
    <?php endif; ?>
    <?php if (setting('google_site_verification', '') !== ''): ?>
        <meta name="google-site-verification" content="<?= e(setting('google_site_verification', '')) ?>">
    <?php endif; ?>
    <?php if (setting('seo_geo_latitude', '') !== '' && setting('seo_geo_longitude', '') !== ''): ?>
        <meta name="geo.position" content="<?= e(setting('seo_geo_latitude', '')) ?>;<?= e(setting('seo_geo_longitude', '')) ?>">
        <meta name="ICBM" content="<?= e(setting('seo_geo_latitude', '')) ?>, <?= e(setting('seo_geo_longitude', '')) ?>">
    <?php endif; ?>
    <?php if (setting('seo_geo_region', '') !== ''): ?>
        <meta name="geo.region" content="<?= e(setting('seo_geo_region', '')) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= e($meta['title'] ?? config('name')) ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($meta['description'])): ?>
        <meta property="og:description" content="<?= e($meta['description']) ?>">
    <?php endif; ?>
    <?php $bootstrapCss = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css'; ?>
    <?php if (setting('perf_preload_bootstrap_css', '1') === '1'): ?>
        <link rel="preload" href="<?= e($bootstrapCss) ?>" as="style" onload="this.onload=null;this.rel='stylesheet'">
        <noscript><link rel="stylesheet" href="<?= e($bootstrapCss) ?>"></noscript>
    <?php else: ?>
        <link href="<?= e($bootstrapCss) ?>" rel="stylesheet">
    <?php endif; ?>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; }
        .hero { padding: 6rem 0; background: linear-gradient(180deg,#f7fbff,#eef5ff); }
        .faq-item { border-bottom: 1px solid #eee; padding: 1rem 0; }
        .perf-safe-animation { will-change: transform; transform: translateZ(0); }
    </style>
    <?php if (setting('perf_preconnect_third_party', '1') === '1'): ?>
        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
        <link rel="preconnect" href="https://www.googletagmanager.com" crossorigin>
    <?php endif; ?>
    <?php if (setting('perf_preload_lcp_image', '1') === '1' && !empty($blocks) && is_array($blocks)): ?>
        <?php
        $lcpSrc = '';
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'image' && !empty($b['src'])) {
                $lcpSrc = (string) $b['src'];
                break;
            }
        }
        ?>
        <?php if ($lcpSrc !== ''): ?>
            <link rel="preload" as="image" href="<?= e($lcpSrc) ?>">
        <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($page['schema_json'])): ?>
        <script type="application/ld+json"><?= $page['schema_json'] ?></script>
    <?php endif; ?>
    <?php $globalSchema = setting('seo_global_schema_json', ''); ?>
    <?php if ($globalSchema !== ''): ?>
        <script type="application/ld+json"><?= $globalSchema ?></script>
    <?php endif; ?>
    <?php $gtmContainer = setting('gtm_container_id', ''); ?>
    <?php $gtmServer = rtrim((string) setting('gtm_server_url', ''), '/'); ?>
    <?php $ga4MeasurementId = setting('ga4_measurement_id', ''); ?>
    <?php $ga4TransportUrl = setting('ga4_transport_url', ''); ?>
    <?php $sentryDsn = setting('sentry_dsn', ''); ?>
    <?php $sentryEnvironment = setting('sentry_environment', config('env', 'production')); ?>
    <?php $sentryRelease = setting('sentry_release', ''); ?>
    <?php $sentryTracesSampleRate = setting('sentry_traces_sample_rate', '0.20'); ?>
    <?php if ($gtmContainer !== '' && setting('perf_limit_third_party_scripts', '1') === '1'): ?>
        <script>
            (function(loadFn){
                if (<?= setting('perf_defer_third_party_js', '1') === '1' ? 'true' : 'false' ?>) {
                    window.addEventListener('load', function(){ setTimeout(loadFn, 500); });
                } else {
                    loadFn();
                }
            })(function(){
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
                '<?= e($gtmServer !== '' ? $gtmServer . '/gtm.js?id=' : 'https://www.googletagmanager.com/gtm.js?id=') ?>'+i+dl;f.parentNode.insertBefore(j,f);
                })(window,document,'script','dataLayer','<?= e($gtmContainer) ?>');
            });
        </script>
    <?php endif; ?>
    <?php $headerCode = setting('seo_header_code', ''); ?>
    <?php if ($headerCode !== ''): ?>
        <?= $headerCode ?>
    <?php endif; ?>
    <?php if ($ga4MeasurementId !== '' && setting('perf_limit_third_party_scripts', '1') === '1'): ?>
        <script>
            (function(loadFn){
                if (<?= setting('perf_defer_third_party_js', '1') === '1' ? 'true' : 'false' ?>) {
                    window.addEventListener('load', function(){ setTimeout(loadFn, 400); });
                } else {
                    loadFn();
                }
            })(function(){
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=<?= e($ga4MeasurementId) ?>';
                document.head.appendChild(s);
                window.dataLayer = window.dataLayer || [];
                function gtag(){ dataLayer.push(arguments); }
                window.gtag = gtag;
                gtag('js', new Date());
                gtag('config', '<?= e($ga4MeasurementId) ?>', {
                    send_page_view: true<?= $ga4TransportUrl !== '' ? ",\n                    transport_url: '" . e($ga4TransportUrl) . "'" : '' . "\n" ?>
                });
            });
        </script>
    <?php endif; ?>
    <?php if ($sentryDsn !== '' && setting('perf_limit_third_party_scripts', '1') === '1'): ?>
        <script src="https://browser.sentry-cdn.com/8.33.0/bundle.tracing.replay.min.js" crossorigin="anonymous" defer></script>
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () {
                    if (!window.Sentry) {
                        return;
                    }
                    window.Sentry.init({
                        dsn: '<?= e($sentryDsn) ?>',
                        environment: '<?= e($sentryEnvironment) ?>',
                        release: '<?= e($sentryRelease) ?>',
                        tracesSampleRate: <?= (float) $sentryTracesSampleRate ?>,
                        replaysSessionSampleRate: 0.0,
                        replaysOnErrorSampleRate: 1.0
                    });
                }, 350);
            });
        </script>
    <?php endif; ?>
</head>
<body>
<?php if ($gtmContainer !== ''): ?>
<noscript><iframe src="<?= e($gtmServer !== '' ? $gtmServer . '/ns.html?id=' . $gtmContainer : 'https://www.googletagmanager.com/ns.html?id=' . $gtmContainer) ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif; ?>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container">
        <a class="navbar-brand" href="/"><?= e(config('name')) ?></a>
        <a class="btn btn-outline-primary btn-sm" href="/admin">Admin</a>
    </div>
</nav>

<main>
    <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" defer></script>
<?php if (setting('perf_user_timing_marks', '1') === '1'): ?>
    <script>
        performance.mark('cms_dom_ready');
        window.addEventListener('load', function () {
            performance.mark('cms_window_load');
            performance.measure('cms_load_time', 'cms_dom_ready', 'cms_window_load');
        });
    </script>
<?php endif; ?>
<?php if (setting('perf_rum_web_vitals', '1') === '1'): ?>
    <script type="module">
        import {onCLS, onINP, onLCP, onFCP, onTTFB} from 'https://unpkg.com/web-vitals@4/dist/web-vitals.attribution.js?module';

        const sendMetric = (metric) => {
            const payload = JSON.stringify({
                name: metric.name,
                value: metric.value,
                rating: metric.rating,
                delta: metric.delta,
                id: metric.id,
                page: window.location.pathname,
                ts: Date.now()
            });
            navigator.sendBeacon('/rum/vitals', payload);

            if (window.gtag && '<?= e($ga4MeasurementId) ?>' !== '') {
                window.gtag('event', metric.name, {
                    event_category: 'Web Vitals',
                    event_label: metric.id,
                    value: Math.round(metric.value),
                    non_interaction: true
                });
            }
        };

        onCLS(sendMetric);
        onINP(sendMetric);
        onLCP(sendMetric);
        onFCP(sendMetric);
        onTTFB(sendMetric);
    </script>
<?php endif; ?>
<?php $footerCode = setting('seo_footer_code', ''); ?>
<?php if ($footerCode !== ''): ?>
    <?= $footerCode ?>
<?php endif; ?>
</body>
</html>
