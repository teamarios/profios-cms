CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'admin',
    totp_secret VARCHAR(64) NULL,
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    backup_codes_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    meta_title VARCHAR(190) NULL,
    meta_description VARCHAR(255) NULL,
    canonical_url VARCHAR(255) NULL,
    schema_json LONGTEXT NULL,
    content_blocks LONGTEXT NOT NULL,
    seo_score INT UNSIGNED NOT NULL DEFAULT 0,
    seo_issues_json LONGTEXT NULL,
    cache_ttl INT UNSIGNED NOT NULL DEFAULT 120,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    published_at DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_slug_status (slug, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(190) NOT NULL UNIQUE,
    `value` LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
