<?php

namespace App\Services;

final class SeoService
{
    public static function buildMeta(array $page): array
    {
        $title = trim((string) ($page['meta_title'] ?? '')) ?: (string) ($page['title'] ?? config('name'));
        $description = trim((string) ($page['meta_description'] ?? ''));
        $canonical = trim((string) ($page['canonical_url'] ?? '')) ?: appUrl('/page/' . ($page['slug'] ?? ''));

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'og_type' => 'website',
        ];
    }
}
