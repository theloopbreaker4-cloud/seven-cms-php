CREATE TABLE IF NOT EXISTS `plugins` (
    `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(120)   NOT NULL,
    `version`       VARCHAR(40)    NOT NULL DEFAULT '0.0.0',
    `status`        ENUM('installed','enabled','disabled','uninstalled')
                    NOT NULL DEFAULT 'uninstalled',
    `config`        JSON           NOT NULL DEFAULT ('{}'),
    `installed_at`  DATETIME       NULL,
    `updated_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_plugins_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
