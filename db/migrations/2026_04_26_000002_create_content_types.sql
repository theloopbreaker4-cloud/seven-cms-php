-- Custom Content Types (CCT)
-- A "content type" is a user-defined entity (e.g. "Recipe", "Event", "Product") with
-- a configurable list of fields. Entries are stored row-per-content in `content_entries`,
-- with the typed values held in a JSON `data` column. We keep typed indexed columns
-- (slug, status, locale, sort_order, published_at) for fast queries.
--
-- Field schema lives in `content_fields`. The admin UI builds it; the renderer reads it.

CREATE TABLE IF NOT EXISTS `content_types` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `slug`          VARCHAR(64)   NOT NULL,
    `name`          VARCHAR(120)  NOT NULL,
    `description`   VARCHAR(500)  NULL,
    `icon`          VARCHAR(60)   NOT NULL DEFAULT 'box',
    `is_singleton`  TINYINT(1)    NOT NULL DEFAULT 0,
    `enable_revisions` TINYINT(1) NOT NULL DEFAULT 1,
    `enable_drafts`    TINYINT(1) NOT NULL DEFAULT 1,
    `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_content_types_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_fields` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `type_id`       INT UNSIGNED  NOT NULL,
    `key`           VARCHAR(64)   NOT NULL,
    `label`         VARCHAR(160)  NOT NULL,
    `field_type`    ENUM('text','richtext','number','boolean','image','media',
                         'select','multiselect','date','datetime',
                         'relation','repeater','json')
                    NOT NULL,
    `required`      TINYINT(1)    NOT NULL DEFAULT 0,
    `localized`     TINYINT(1)    NOT NULL DEFAULT 0,
    `settings`      JSON          NOT NULL DEFAULT ('{}'),
    `sort_order`    SMALLINT      NOT NULL DEFAULT 0,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_content_fields_type_key` (`type_id`,`key`),
    KEY `idx_content_fields_type` (`type_id`),
    CONSTRAINT `fk_content_fields_type`
        FOREIGN KEY (`type_id`) REFERENCES `content_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_entries` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `type_id`       INT UNSIGNED  NOT NULL,
    `slug`          VARCHAR(191)  NOT NULL,
    `status`        ENUM('draft','published','archived')
                    NOT NULL DEFAULT 'draft',
    `locale`        VARCHAR(8)    NOT NULL DEFAULT 'en',
    `sort_order`    INT           NOT NULL DEFAULT 0,
    `data`          JSON          NOT NULL DEFAULT ('{}'),
    `author_id`     INT UNSIGNED  NULL,
    `published_at`  DATETIME      NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_content_entries_type_slug_locale` (`type_id`,`slug`,`locale`),
    KEY `idx_content_entries_type_status` (`type_id`,`status`),
    KEY `idx_content_entries_published_at` (`published_at`),
    CONSTRAINT `fk_content_entries_type`
        FOREIGN KEY (`type_id`) REFERENCES `content_types`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pivot for many-to-many relations between content entries.
-- `relation_key` matches the `key` of a content_fields row whose field_type='relation'.
CREATE TABLE IF NOT EXISTS `content_relations` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `from_entry_id`   INT UNSIGNED  NOT NULL,
    `to_entry_id`     INT UNSIGNED  NOT NULL,
    `relation_key`    VARCHAR(64)   NOT NULL,
    `sort_order`      INT           NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_relation` (`from_entry_id`,`to_entry_id`,`relation_key`),
    KEY `idx_relation_from` (`from_entry_id`,`relation_key`),
    KEY `idx_relation_to`   (`to_entry_id`,`relation_key`),
    CONSTRAINT `fk_relation_from` FOREIGN KEY (`from_entry_id`) REFERENCES `content_entries`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_relation_to`   FOREIGN KEY (`to_entry_id`)   REFERENCES `content_entries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
