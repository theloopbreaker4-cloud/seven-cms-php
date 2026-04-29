-- Activity / audit log.
-- Captures who did what to which entity, with a small JSON payload for context (diff, ip, ua).

CREATE TABLE IF NOT EXISTS `activity_log` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED    NULL,
    `action`        VARCHAR(60)     NOT NULL,
    `entity_type`   VARCHAR(64)     NULL,
    `entity_id`     INT UNSIGNED    NULL,
    `description`   VARCHAR(500)    NULL,
    `meta`          JSON            NOT NULL DEFAULT ('{}'),
    `ip`            VARCHAR(64)     NULL,
    `user_agent`    VARCHAR(255)    NULL,
    `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_user`   (`user_id`),
    KEY `idx_activity_entity` (`entity_type`,`entity_id`),
    KEY `idx_activity_action` (`action`),
    KEY `idx_activity_time`   (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
