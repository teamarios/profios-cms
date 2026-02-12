<div class="container py-5">
    <?php foreach ($blocks as $index => $block): ?>
        <?php $type = $block['type'] ?? 'text'; ?>

        <?php if ($type === 'hero'): ?>
            <section class="hero rounded-4 mb-4 text-center">
                <div class="container">
                    <h1 class="display-5 fw-bold"><?= e($block['title'] ?? $page['title']) ?></h1>
                    <p class="lead mb-3"><?= e($block['text'] ?? '') ?></p>
                    <?php if (!empty($block['button_text']) && !empty($block['button_url'])): ?>
                        <a href="<?= e($block['button_url']) ?>" class="btn btn-primary btn-lg"><?= e($block['button_text']) ?></a>
                    <?php endif; ?>
                </div>
            </section>
        <?php elseif ($type === 'image'): ?>
            <section class="mb-4 text-center">
                <?php
                $imgWidth = (int) ($block['width'] ?? 1200);
                $imgHeight = (int) ($block['height'] ?? 600);
                $imgAlt = trim((string) ($block['alt'] ?? ''));
                if ($imgAlt === '') {
                    $imgAlt = trim((string) ($page['title'] ?? 'Page image'));
                }
                $lazyEnabled = setting('perf_lazy_images', '1') === '1';
                ?>
                <img src="<?= e($block['src'] ?? '') ?>"
                     alt="<?= e($imgAlt) ?>"
                     class="img-fluid rounded-3"
                     width="<?= $imgWidth ?>"
                     height="<?= $imgHeight ?>"
                     style="aspect-ratio: <?= $imgWidth ?>/<?= $imgHeight ?>;"
                     loading="<?= $lazyEnabled ? 'lazy' : 'eager' ?>"
                     decoding="async">
            </section>
        <?php elseif ($type === 'faq'): ?>
            <section class="mb-4">
                <h2 class="h4 mb-3"><?= e($block['title'] ?? 'FAQ') ?></h2>
                <?php foreach (($block['items'] ?? []) as $item): ?>
                    <div class="faq-item">
                        <h3 class="h6 mb-1"><?= e($item['q'] ?? '') ?></h3>
                        <p class="mb-0"><?= e($item['a'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php else: ?>
            <section class="mb-4">
                <?= $block['html'] ?? '<p>' . e($block['text'] ?? '') . '</p>' ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php
    $internalLinksRaw = setting('seo_internal_links_json', '[]');
    $internalLinks = json_decode($internalLinksRaw, true);
    ?>
    <?php if (is_array($internalLinks) && count($internalLinks) > 0): ?>
        <section class="mt-5">
            <h2 class="h5">Related Pages</h2>
            <ul>
                <?php foreach ($internalLinks as $link): ?>
                    <?php if (!empty($link['anchor']) && !empty($link['url'])): ?>
                        <li><a href="<?= e($link['url']) ?>"><?= e($link['anchor']) ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
