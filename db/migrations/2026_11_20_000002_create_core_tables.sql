-- Core tables that used to exist only once RedBean's fluid mode created them on the
-- first save, with types guessed from the first value (TINYINT for 0, VARCHAR(191)
-- for short strings). Explicit schemas let the database be frozen in production.
-- The MODIFY … IF EXISTS lines repair tables fluid mode already created.

CREATE TABLE IF NOT EXISTS `token` (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NULL,
    value       VARCHAR(191) NOT NULL DEFAULT '',
    auth        TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_token_value (value),
    INDEX idx_token_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `slug` (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type  VARCHAR(64)  NOT NULL DEFAULT '',
    entity_id    INT UNSIGNED NOT NULL DEFAULT 0,
    lang         VARCHAR(8)   NOT NULL DEFAULT '',
    slug         VARCHAR(191) NOT NULL DEFAULT '',
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_slug_lookup (entity_type, lang, slug),
    INDEX idx_slug_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `language` (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code         VARCHAR(8)   NOT NULL DEFAULT '',
    name         VARCHAR(191) NOT NULL DEFAULT '',
    native_name  VARCHAR(191) NOT NULL DEFAULT '',
    flag         VARCHAR(32)  NOT NULL DEFAULT '',
    is_active    TINYINT UNSIGNED NOT NULL DEFAULT 1,
    is_default   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order   INT NOT NULL DEFAULT 0,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_language_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `adminlang` (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(8)   NOT NULL DEFAULT '',
    name        VARCHAR(191) NOT NULL DEFAULT '',
    is_default  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_active   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    INDEX idx_adminlang_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `language` MODIFY COLUMN IF EXISTS sort_order INT NOT NULL DEFAULT 0;
ALTER TABLE `adminlang` ADD COLUMN IF NOT EXISTS is_active TINYINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE `token` MODIFY COLUMN IF EXISTS auth TINYINT UNSIGNED NOT NULL DEFAULT 1;
