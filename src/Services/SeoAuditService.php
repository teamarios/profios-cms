<?php

namespace App\Services;

final class SeoAuditService
{
    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? (int) mb_strlen($value) : strlen($value);
    }

    public static function analyze(array $page, array $runtimeSettings = []): array
    {
        $score = 0;
        $issues = [];

        $title = trim((string) ($page['meta_title'] ?? ''));
        $titleLen = self::length($title);
        if ($titleLen >= 30 && $titleLen <= 60) {
            $score += 20;
        } else {
            $issues[] = 'Meta title should be 30-60 characters.';
        }

        $description = trim((string) ($page['meta_description'] ?? ''));
        $descLen = self::length($description);
        if ($descLen >= 70 && $descLen <= 160) {
            $score += 20;
        } else {
            $issues[] = 'Meta description should be 70-160 characters.';
        }

        if (trim((string) ($page['canonical_url'] ?? '')) !== '') {
            $score += 10;
        } else {
            $issues[] = 'Canonical URL is missing.';
        }

        $schema = trim((string) ($page['schema_json'] ?? ''));
        if ($schema !== '') {
            json_decode($schema, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $score += 15;
            } else {
                $issues[] = 'Page schema JSON is invalid.';
            }
        } else {
            $issues[] = 'Page schema JSON-LD is missing.';
        }

        $blocks = $page['content_blocks'] ?? [];
        if (is_string($blocks)) {
            $decoded = json_decode($blocks, true);
            $blocks = is_array($decoded) ? $decoded : [];
        }

        if (is_array($blocks) && count($blocks) > 0) {
            $score += 10;
        } else {
            $issues[] = 'Page has no content blocks.';
        }

        $heroOkay = false;
        $allImageAltOkay = true;
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if (($block['type'] ?? '') === 'hero' && trim((string) ($block['title'] ?? '')) !== '') {
                    $heroOkay = true;
                }
                if (($block['type'] ?? '') === 'image' && trim((string) ($block['alt'] ?? '')) === '') {
                    $allImageAltOkay = false;
                }
            }
        }

        if ($heroOkay) {
            $score += 10;
        } else {
            $issues[] = 'Add a hero block with a clear headline.';
        }

        if ($allImageAltOkay) {
            $score += 5;
        } else {
            $issues[] = 'All image blocks should include alt text.';
        }

        if (!empty($runtimeSettings['gtm_container_id'] ?? '')) {
            $score += 5;
        } else {
            $issues[] = 'GTM container ID is not configured globally.';
        }

        $internalLinks = $runtimeSettings['seo_internal_links_json'] ?? '[]';
        $decodedLinks = json_decode((string) $internalLinks, true);
        if (is_array($decodedLinks) && count($decodedLinks) > 0) {
            $score += 5;
        } else {
            $issues[] = 'Global internal links list is empty.';
        }

        $ttl = (int) ($page['cache_ttl'] ?? 120);
        if ($ttl >= 60 && $ttl <= 3600) {
            $score += 5;
        } else {
            $issues[] = 'Cache TTL should be between 60 and 3600 seconds.';
        }

        return [
            'score' => min(100, max(0, $score)),
            'issues' => $issues,
        ];
    }
}
