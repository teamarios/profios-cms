<?php

namespace App\Controllers;

use App\Models\Page;

final class SeoController
{
    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');

        $urls = Page::published();
        $base = rtrim((string) config('url'), '/');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        echo '<url>';
        echo '<loc>' . e($base . '/') . '</loc>';
        echo '<changefreq>daily</changefreq>';
        echo '<priority>1.0</priority>';
        echo '</url>';

        foreach ($urls as $url) {
            echo '<url>';
            echo '<loc>' . e($base . '/page/' . $url['slug']) . '</loc>';
            if (!empty($url['updated_at'])) {
                echo '<lastmod>' . date('c', strtotime($url['updated_at'])) . '</lastmod>';
            }
            echo '<changefreq>weekly</changefreq>';
            echo '<priority>0.8</priority>';
            echo '</url>';
        }

        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $base = rtrim((string) config('url'), '/');
        $env = strtolower((string) config('env', 'production'));
        $forceNoIndex = setting('seo_robots_noindex_nonprod', '1') === '1'
            && $env !== 'production';

        echo "User-agent: *\n";
        if ($forceNoIndex) {
            echo "Disallow: /\n\n";
            echo '# Non-production environment (' . $env . ')' . "\n";
            return;
        }

        echo "Allow: /\n";
        echo "Disallow: /admin\n\n";
        echo 'Sitemap: ' . $base . "/sitemap.xml\n";
    }
}
