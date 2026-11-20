-- Blog tables, previously created by RedBean's fluid mode. Its guesses were wrong in
-- ways that bite later: title/content as VARCHAR(191) although they hold JSON for
-- every language, and view_count as TINYINT, so `view_count + 1` failed after 255
-- views. Explicit schema + repair of existing tables.

CREATE TABLE IF NOT EXISTS `post` (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    site_id         INT UNSIGNED NULL,
    user_id         INT UNSIGNED NULL,
    category_id     INT UNSIGNED NULL,
    cover_image_id  INT UNSIGNED NULL,
    title           TEXT NULL,
    excerpt         TEXT NULL,
    content         MEDIUMTEXT NULL,
    cover_image     VARCHAR(500) NULL,
    meta_title      TEXT NULL,
    meta_desc       TEXT NULL,
    is_published    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    view_count      INT UNSIGNED NOT NULL DEFAULT 0,
    published_at    DATETIME NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_post_published (is_published, published_at),
    INDEX idx_post_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `category` (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id    INT UNSIGNED NULL,
    slug         VARCHAR(191) NOT NULL DEFAULT '',
    name         TEXT NULL,
    description  TEXT NULL,
    type         VARCHAR(32) NOT NULL DEFAULT 'post',
    sort_order   INT NOT NULL DEFAULT 0,
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_category_type (type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tag` (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(191) NOT NULL DEFAULT '',
    name        TEXT NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_tag_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `posttag` (
    id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id  INT UNSIGNED NOT NULL,
    tag_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_posttag_post (post_id),
    INDEX idx_posttag_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `post` MODIFY COLUMN IF EXISTS title TEXT NULL;
ALTER TABLE `post` MODIFY COLUMN IF EXISTS excerpt TEXT NULL;
ALTER TABLE `post` MODIFY COLUMN IF EXISTS content MEDIUMTEXT NULL;
ALTER TABLE `post` MODIFY COLUMN IF EXISTS meta_title TEXT NULL;
ALTER TABLE `post` MODIFY COLUMN IF EXISTS meta_desc TEXT NULL;
ALTER TABLE `post` MODIFY COLUMN IF EXISTS cover_image VARCHAR(500) NULL;
ALTER TABLE `post` MODIFY COLUMN IF EXISTS view_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE `post` ADD COLUMN IF NOT EXISTS site_id INT UNSIGNED NULL;
ALTER TABLE `category` MODIFY COLUMN IF EXISTS name TEXT NULL;
ALTER TABLE `category` MODIFY COLUMN IF EXISTS description TEXT NULL;
ALTER TABLE `category` MODIFY COLUMN IF EXISTS sort_order INT NOT NULL DEFAULT 0;
ALTER TABLE `tag` MODIFY COLUMN IF EXISTS name TEXT NULL;
