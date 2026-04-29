-- Media module migration
-- Run via CLI: php bin/sev migrate
-- Or manually:  mysql -u root -p sevencms < modules/Media/migration.sql

-- Core media table (extends what already exists in DB via RedBean)
CREATE TABLE IF NOT EXISTS `media` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED    NULL,
  `folder_id`     INT UNSIGNED    NULL,
  `filename`      VARCHAR(255)    NOT NULL,
  `original_name` VARCHAR(255)    NOT NULL,
  `mime_type`     VARCHAR(120)    NOT NULL,
  `size`          INT UNSIGNED    NOT NULL DEFAULT 0,
  `width`         INT UNSIGNED    NULL,
  `height`        INT UNSIGNED    NULL,
  `path`          VARCHAR(500)    NOT NULL,
  `disk`          VARCHAR(40)     NOT NULL DEFAULT 'local',
  `alt`           JSON            NOT NULL DEFAULT ('{}'),
  `title`         VARCHAR(255)    NOT NULL DEFAULT '',
  `description`   TEXT            NULL,
  `variants`      JSON            NOT NULL DEFAULT ('{}'),
  `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_folder` (`folder_id`),
  KEY `idx_media_user`   (`user_id`),
  KEY `idx_media_mime`   (`mime_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Folders (single-level tree via parent_id)
CREATE TABLE IF NOT EXISTS `media_folder` (
  `id`         INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `parent_id`  INT UNSIGNED    NULL,
  `name`       VARCHAR(191)    NOT NULL,
  `slug`       VARCHAR(191)    NOT NULL,
  `path`       VARCHAR(500)    NOT NULL DEFAULT '',
  `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_folder_parent_slug` (`parent_id`, `slug`),
  KEY `idx_folder_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
