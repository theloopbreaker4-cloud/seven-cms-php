-- Key/value site settings (Setting model). Created up front so plugin migrations
-- can seed their own keys; existing installs already have it (RedBean-created).
CREATE TABLE IF NOT EXISTS `setting` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(191)  NOT NULL,
    `value`      TEXT          NULL,
    `group`      VARCHAR(64)   NOT NULL DEFAULT 'general',
    `type`       VARCHAR(32)   NOT NULL DEFAULT 'string',
    `label`      VARCHAR(191)  NULL,
    `updated_at` DATETIME      NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_setting_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
