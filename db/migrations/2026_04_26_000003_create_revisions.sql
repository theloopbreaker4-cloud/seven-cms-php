-- Generic revisions table — works for any entity (content_entries, page, post, …).
-- We snapshot the full payload as JSON so this stays decoupled from individual schemas.

CREATE TABLE IF NOT EXISTS `revisions` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `entity_type`   VARCHAR(64)   NOT NULL,
    `entity_id`     INT UNSIGNED  NOT NULL,
    `data`          JSON          NOT NULL,
    `meta`          JSON          NOT NULL DEFAULT ('{}'),
    `comment`       VARCHAR(500)  NULL,
    `author_id`     INT UNSIGNED  NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_revisions_entity` (`entity_type`,`entity_id`),
    KEY `idx_revisions_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
