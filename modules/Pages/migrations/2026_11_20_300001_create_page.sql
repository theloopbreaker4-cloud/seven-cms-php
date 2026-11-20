-- `page`, previously created by RedBean's fluid mode (title/content as VARCHAR,
-- sort_order as TINYINT). Explicit schema + repair of existing tables.

CREATE TABLE IF NOT EXISTS `page` (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id       INT UNSIGNED NULL,
    user_id       INT UNSIGNED NULL,
    parent_id     INT UNSIGNED NULL,
    slug          VARCHAR(191) NULL,
    template      VARCHAR(64) NOT NULL DEFAULT 'default',
    title         TEXT NULL,
    content       MEDIUMTEXT NULL,
    meta_title    TEXT NULL,
    meta_desc     TEXT NULL,
    is_published  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_page_published (is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `page` MODIFY COLUMN IF EXISTS title TEXT NULL;
ALTER TABLE `page` MODIFY COLUMN IF EXISTS content MEDIUMTEXT NULL;
ALTER TABLE `page` MODIFY COLUMN IF EXISTS meta_title TEXT NULL;
ALTER TABLE `page` MODIFY COLUMN IF EXISTS meta_desc TEXT NULL;
ALTER TABLE `page` MODIFY COLUMN IF EXISTS sort_order INT NOT NULL DEFAULT 0;
ALTER TABLE `page` ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL;
ALTER TABLE `page` ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL;
ALTER TABLE `page` ADD COLUMN IF NOT EXISTS parent_id INT UNSIGNED NULL;
