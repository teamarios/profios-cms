<?php
$isEdit = is_array($page);
$action = $isEdit ? '/admin/pages/' . (int) $page['id'] . '/update' : '/admin/pages/store';
$blocksRaw = $isEdit ? (string) ($page['content_blocks'] ?? '[]') : '[]';
$audit = $seoAudit ?? ['score' => 0, 'issues' => []];
$issues = is_array($audit['issues'] ?? null) ? $audit['issues'] : [];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?= $isEdit ? 'Edit Page' : 'Create Page' ?></h1>
    <a href="/admin/pages" class="btn btn-outline-secondary">Back</a>
</div>

<form method="post" action="<?= e($action) ?>" id="page-form">
    <?= \App\Core\Csrf::input() ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5">Content Builder</h2>
                    <div class="d-flex gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-add-block="hero">+ Hero</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-add-block="text">+ Text</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-add-block="image">+ Image</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-add-block="faq">+ FAQ</button>
                    </div>
                    <div id="builder" class="mb-2"></div>
                    <input type="hidden" name="content_blocks" id="content_blocks" value="<?= e($blocksRaw) ?>">
                    <small class="text-muted">Drag blocks to reorder. Keep above-the-fold content in first block for better LCP.</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5">Page Settings</h2>
                    <div class="mb-2">
                        <label class="form-label">Title</label>
                        <input class="form-control" id="title_field" name="title" value="<?= e($page['title'] ?? '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Slug</label>
                        <input class="form-control" id="slug_field" name="slug" value="<?= e($page['slug'] ?? '') ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <?php $status = $page['status'] ?? 'draft'; ?>
                            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Cache TTL (seconds)</label>
                        <input class="form-control" type="number" min="30" step="30" name="cache_ttl" value="<?= e((string) ($page['cache_ttl'] ?? '120')) ?>">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5">SEO Settings</h2>
                    <div class="mb-2">
                        <label class="form-label">Meta Title</label>
                        <input class="form-control" id="meta_title_field" name="meta_title" value="<?= e($page['meta_title'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Meta Description</label>
                        <textarea class="form-control" id="meta_description_field" name="meta_description" rows="3"><?= e($page['meta_description'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Canonical URL</label>
                        <input class="form-control" id="canonical_url_field" name="canonical_url" value="<?= e($page['canonical_url'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Schema JSON-LD</label>
                        <textarea class="form-control" id="schema_json_field" name="schema_json" rows="4" placeholder='{"@context":"https://schema.org"}'><?= e($page['schema_json'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5">SEO Audit</h2>
                    <p class="mb-2">Score: <span class="badge text-bg-<?= ((int) ($audit['score'] ?? 0)) >= 80 ? 'success' : (((int) ($audit['score'] ?? 0)) >= 60 ? 'warning' : 'danger') ?>"><?= (int) ($audit['score'] ?? 0) ?>/100</span></p>
                    <?php if ($issues !== []): ?>
                        <ul class="mb-0">
                            <?php foreach ($issues as $issue): ?>
                                <li><?= e((string) $issue) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="mb-0 text-success">No critical SEO issues detected.</p>
                    <?php endif; ?>
                    <hr>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fix-action="meta_title">Auto-fix Meta Title</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fix-action="meta_description">Auto-fix Meta Description</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fix-action="canonical">Auto-fix Canonical URL</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fix-action="schema">Generate Basic Schema</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-fix-action="image_alt">Auto-fill Missing Image Alt</button>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary w-100" type="submit">Save Page</button>
        </div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
<script>
const builder = document.getElementById('builder');
const hiddenInput = document.getElementById('content_blocks');
const appBaseUrl = <?= json_encode(rtrim((string) config('url'), '/'), JSON_UNESCAPED_UNICODE) ?>;
const siteName = <?= json_encode((string) config('name'), JSON_UNESCAPED_UNICODE) ?>;
let blocks = [];

try {
    blocks = JSON.parse(hiddenInput.value || '[]');
    if (!Array.isArray(blocks)) blocks = [];
} catch (e) {
    blocks = [];
}

const templates = {
    hero: () => ({ type: 'hero', title: 'Hero Heading', text: 'Add your primary marketing message.', button_text: 'Get Started', button_url: '#' }),
    text: () => ({ type: 'text', html: '<h2>Section Title</h2><p>Write content focused on user intent and keyword relevance.</p>' }),
    image: () => ({ type: 'image', src: 'https://placehold.co/1200x600', alt: 'Descriptive image alt text' }),
    faq: () => ({ type: 'faq', title: 'Frequently Asked Questions', items: [{ q: 'Question 1', a: 'Answer 1' }, { q: 'Question 2', a: 'Answer 2' }] })
};

function renderBlock(block, index) {
    const wrap = document.createElement('div');
    wrap.className = 'block-card p-3';
    wrap.dataset.index = String(index);

    const title = document.createElement('div');
    title.className = 'd-flex justify-content-between align-items-center mb-2';
    title.innerHTML = `<strong>${block.type.toUpperCase()} Block</strong><button type="button" class="btn btn-sm btn-outline-danger" data-remove="${index}">Remove</button>`;

    const textarea = document.createElement('textarea');
    textarea.className = 'form-control';
    textarea.rows = 6;
    textarea.value = JSON.stringify(block, null, 2);
    textarea.addEventListener('input', () => {
        try {
            blocks[index] = JSON.parse(textarea.value);
            sync();
        } catch (e) {
            // ignore invalid JSON while typing
        }
    });

    wrap.appendChild(title);
    wrap.appendChild(textarea);
    return wrap;
}

function render() {
    builder.innerHTML = '';
    blocks.forEach((block, i) => builder.appendChild(renderBlock(block, i)));

    builder.querySelectorAll('[data-remove]').forEach(btn => {
        btn.addEventListener('click', () => {
            blocks.splice(Number(btn.dataset.remove), 1);
            sync();
            render();
        });
    });
}

function sync() {
    hiddenInput.value = JSON.stringify(blocks);
}

function stripHtml(html) {
    const div = document.createElement('div');
    div.innerHTML = html || '';
    return (div.textContent || div.innerText || '').trim();
}

function getPrimaryText() {
    for (const block of blocks) {
        if (block.type === 'hero' && block.text) return String(block.text);
        if (block.type === 'text' && block.html) {
            const text = stripHtml(block.html);
            if (text) return text;
        }
    }
    return '';
}

function trimLength(text, max) {
    const value = (text || '').trim();
    if (value.length <= max) return value;
    return value.slice(0, max - 1).trim() + '…';
}

function applyFix(action) {
    const titleEl = document.getElementById('title_field');
    const slugEl = document.getElementById('slug_field');
    const metaTitleEl = document.getElementById('meta_title_field');
    const metaDescEl = document.getElementById('meta_description_field');
    const canonicalEl = document.getElementById('canonical_url_field');
    const schemaEl = document.getElementById('schema_json_field');

    if (action === 'meta_title') {
        const t = titleEl.value || 'Page';
        metaTitleEl.value = trimLength(t + ' | ' + siteName, 60);
    }
    if (action === 'meta_description') {
        const source = getPrimaryText() || titleEl.value || 'Describe this page clearly for search users.';
        metaDescEl.value = trimLength(source, 155);
    }
    if (action === 'canonical') {
        const slug = (slugEl.value || '').replace(/^\\/+/, '');
        canonicalEl.value = slug === 'home' ? appBaseUrl + '/' : appBaseUrl + '/page/' + slug;
    }
    if (action === 'schema') {
        const slug = (slugEl.value || '').replace(/^\\/+/, '');
        const schema = {
            '@context': 'https://schema.org',
            '@type': 'WebPage',
            name: titleEl.value || 'Page',
            url: slug === 'home' ? appBaseUrl + '/' : appBaseUrl + '/page/' + slug
        };
        schemaEl.value = JSON.stringify(schema, null, 2);
    }
    if (action === 'image_alt') {
        let changed = false;
        blocks = blocks.map((block, idx) => {
            if (block.type === 'image' && (!block.alt || !String(block.alt).trim())) {
                changed = true;
                return { ...block, alt: `${titleEl.value || 'Page'} image ${idx + 1}` };
            }
            return block;
        });
        if (changed) {
            sync();
            render();
        }
    }
}

document.querySelectorAll('[data-add-block]').forEach(btn => {
    btn.addEventListener('click', () => {
        const type = btn.dataset.addBlock;
        blocks.push(templates[type]());
        sync();
        render();
    });
});

document.querySelectorAll('[data-fix-action]').forEach(btn => {
    btn.addEventListener('click', () => applyFix(btn.dataset.fixAction));
});

new Sortable(builder, {
    animation: 150,
    onEnd: (evt) => {
        const moved = blocks.splice(evt.oldIndex, 1)[0];
        blocks.splice(evt.newIndex, 0, moved);
        sync();
        render();
    }
});

sync();
render();
</script>
