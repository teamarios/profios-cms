<?php

namespace App\Services;

final class PerformanceAuditService
{
    public static function defaults(): array
    {
        return [
            'perf_preload_bootstrap_css' => '1',
            'perf_preconnect_third_party' => '1',
            'perf_defer_third_party_js' => '1',
            'perf_lazy_images' => '1',
            'perf_explicit_image_dimensions' => '1',
            'perf_preload_lcp_image' => '1',
            'perf_reduce_unused_css' => '1',
            'perf_reduce_unused_js' => '1',
            'perf_minify_css' => '0',
            'perf_minify_html' => '0',
            'perf_font_display_swap' => '1',
            'perf_enable_long_cache_assets' => '1',
            'perf_avoid_non_composited_animations' => '1',
            'perf_limit_third_party_scripts' => '1',
            'perf_user_timing_marks' => '1',
        ];
    }

    public static function audit(array $settings): array
    {
        $checks = [
            'LCP' => self::ok($settings, ['perf_preload_lcp_image', 'perf_lazy_images']),
            'INP' => self::ok($settings, ['perf_defer_third_party_js', 'perf_limit_third_party_scripts']),
            'CLS' => self::ok($settings, ['perf_explicit_image_dimensions', 'perf_avoid_non_composited_animations']),
            'FCP' => self::ok($settings, ['perf_preload_bootstrap_css', 'perf_reduce_unused_css']),
            'TTFB' => self::ok($settings, ['perf_enable_long_cache_assets']),
            'Render blocking requests' => self::ok($settings, ['perf_preload_bootstrap_css']),
            'Font display' => self::ok($settings, ['perf_font_display_swap']),
            'Use efficient cache lifetimes' => self::ok($settings, ['perf_enable_long_cache_assets']),
            'Layout shift culprits' => self::ok($settings, ['perf_explicit_image_dimensions']),
            'Forced reflow' => self::ok($settings, ['perf_reduce_unused_js']),
            'LCP breakdown' => self::ok($settings, ['perf_preload_lcp_image']),
            'LCP request discovery' => self::ok($settings, ['perf_preload_lcp_image']),
            'Network dependency tree' => self::ok($settings, ['perf_preconnect_third_party']),
            'Improve image delivery' => self::ok($settings, ['perf_lazy_images', 'perf_explicit_image_dimensions']),
            'Duplicated JavaScript' => self::ok($settings, ['perf_reduce_unused_js']),
            'Legacy JavaScript' => self::ok($settings, ['perf_reduce_unused_js']),
            '3rd parties code' => self::ok($settings, ['perf_limit_third_party_scripts', 'perf_defer_third_party_js']),
            'Reduce JavaScript' => self::ok($settings, ['perf_reduce_unused_js']),
            'Minimize main-thread work' => self::ok($settings, ['perf_defer_third_party_js']),
            'Reduce unused CSS' => self::ok($settings, ['perf_reduce_unused_css']),
            'Reduce unused JavaScript' => self::ok($settings, ['perf_reduce_unused_js']),
            'Image explicit width and height' => self::ok($settings, ['perf_explicit_image_dimensions']),
            'Minify CSS' => self::ok($settings, ['perf_minify_css']),
            'Avoid enormous network payloads' => self::ok($settings, ['perf_reduce_unused_css', 'perf_reduce_unused_js']),
            'Avoid long main-thread tasks' => self::ok($settings, ['perf_defer_third_party_js']),
            'User Timing marks and measures' => self::ok($settings, ['perf_user_timing_marks']),
            'Avoid non-composited animations' => self::ok($settings, ['perf_avoid_non_composited_animations']),
        ];

        $total = count($checks);
        $passed = count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'ok'));
        $score = $total > 0 ? (int) round(($passed / $total) * 100) : 0;

        return [
            'score' => $score,
            'checks' => $checks,
        ];
    }

    private static function ok(array $settings, array $keys): array
    {
        foreach ($keys as $key) {
            if (($settings[$key] ?? '0') !== '1') {
                return ['status' => 'needs_attention', 'hint' => 'Enable ' . $key . '.'];
            }
        }

        return ['status' => 'ok', 'hint' => 'Configured.'];
    }
}
