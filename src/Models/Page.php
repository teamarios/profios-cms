<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class Page
{
    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = :slug AND status = :status LIMIT 1');
        $stmt->execute(['slug' => $slug, 'status' => 'published']);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        return $page ?: null;
    }

    public static function all(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT * FROM pages ORDER BY updated_at DESC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function published(): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT slug, updated_at FROM pages WHERE status = :status ORDER BY updated_at DESC');
        $stmt->execute(['status' => 'published']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        return $page ?: null;
    }

    public static function create(array $data): int
    {
        $pdo = Database::connection();
        $sql = 'INSERT INTO pages (title, slug, status, meta_title, meta_description, canonical_url, schema_json, content_blocks, seo_score, seo_issues_json, cache_ttl, created_at, updated_at, published_at)
                VALUES (:title, :slug, :status, :meta_title, :meta_description, :canonical_url, :schema_json, :content_blocks, :seo_score, :seo_issues_json, :cache_ttl, :created_at, :updated_at, :published_at)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'],
            'canonical_url' => $data['canonical_url'],
            'schema_json' => $data['schema_json'],
            'content_blocks' => $data['content_blocks'],
            'seo_score' => $data['seo_score'],
            'seo_issues_json' => $data['seo_issues_json'],
            'cache_ttl' => $data['cache_ttl'],
            'created_at' => now(),
            'updated_at' => now(),
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::connection();
        $sql = 'UPDATE pages SET title = :title, slug = :slug, status = :status, meta_title = :meta_title, meta_description = :meta_description,
                canonical_url = :canonical_url, schema_json = :schema_json, content_blocks = :content_blocks, seo_score = :seo_score, seo_issues_json = :seo_issues_json, cache_ttl = :cache_ttl,
                updated_at = :updated_at, published_at = :published_at
                WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'slug' => $data['slug'],
            'status' => $data['status'],
            'meta_title' => $data['meta_title'],
            'meta_description' => $data['meta_description'],
            'canonical_url' => $data['canonical_url'],
            'schema_json' => $data['schema_json'],
            'content_blocks' => $data['content_blocks'],
            'seo_score' => $data['seo_score'],
            'seo_issues_json' => $data['seo_issues_json'],
            'cache_ttl' => $data['cache_ttl'],
            'updated_at' => now(),
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);
    }

    public static function delete(int $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM pages WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
