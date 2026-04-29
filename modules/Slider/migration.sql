-- Slider module migration
-- Run once: mysql -u root -p sevencms < modules/Slider/migration.sql

CREATE TABLE IF NOT EXISTS `slide` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `title`       JSON            NOT NULL DEFAULT ('{}'),
  `subtitle`    JSON            NOT NULL DEFAULT ('{}'),
  `button_text` JSON            NOT NULL DEFAULT ('{}'),
  `button_url`  VARCHAR(500)    NOT NULL DEFAULT '',
  `image`       VARCHAR(1000)   NOT NULL DEFAULT '',
  `overlay`     ENUM('none','dark','light') NOT NULL DEFAULT 'none',
  `sort_order`  SMALLINT        NOT NULL DEFAULT 0,
  `is_active`   TINYINT(1)      NOT NULL DEFAULT 1,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slide_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
