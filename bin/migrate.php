<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$pdo = \App\Core\Database::connection();

$schemaSql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schemaSql === false) {
    fwrite(STDERR, "Cannot read schema.sql\n");
    exit(1);
}

$pdo->exec($schemaSql);

ensureColumn($pdo, 'users', 'totp_secret', 'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) NULL');
ensureColumn($pdo, 'users', 'totp_enabled', 'ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0');
ensureColumn($pdo, 'users', 'backup_codes_json', 'ALTER TABLE users ADD COLUMN backup_codes_json LONGTEXT NULL');
ensureColumn($pdo, 'users', 'role', "ALTER TABLE users ADD COLUMN role VARCHAR(30) NOT NULL DEFAULT 'admin'");
ensureColumn($pdo, 'pages', 'seo_score', 'ALTER TABLE pages ADD COLUMN seo_score INT UNSIGNED NOT NULL DEFAULT 0');
ensureColumn($pdo, 'pages', 'seo_issues_json', 'ALTER TABLE pages ADD COLUMN seo_issues_json LONGTEXT NULL');

fwrite(STDOUT, "Migration complete.\n");

function ensureColumn(PDO $pdo, string $table, string $column, string $alterSql): void
{
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $stmt->execute(['table' => $table, 'column' => $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $exists = (int) ($row['cnt'] ?? 0) > 0;
    if (!$exists) {
        $pdo->exec($alterSql);
    }
}
