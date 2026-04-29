-- Multi-site support.
--
-- A "site" is a logical tenant identified by one or more host names. Every
-- request resolves to exactly one site through `SiteResolver::current()`, which
-- caches the result for the rest of the request.
--
-- The default install has a single site (id=1, slug='default') so every
-- existing query keeps working unchanged.

CREATE TABLE IF NOT EXISTS `sites` (
    `id`           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `slug`         VARCHAR(64)    NOT NULL,
    `name`         VARCHAR(160)   NOT NULL,
    `is_default`   TINYINT(1)     NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)     NOT NULL DEFAULT 1,
    `theme`        VARCHAR(80)    NULL,
    `default_locale` VARCHAR(8)   NOT NULL DEFAULT 'en',
    `settings`     JSON           NOT NULL DEFAULT ('{}'),
    `created_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_sites_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A site can answer to many host names (apex + www + tenant subdomains).
CREATE TABLE IF NOT EXISTS `site_hosts` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `site_id`     INT UNSIGNED  NOT NULL,
    `host`        VARCHAR(191)  NOT NULL,
    `is_primary`  TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_site_hosts_host` (`host`),
    KEY `idx_site_hosts_site` (`site_id`),
    CONSTRAINT `fk_site_hosts_site`
        FOREIGN KEY (`site_id`) REFERENCES `sites`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed a default site so single-site installs work without configuration.
INSERT IGNORE INTO `sites` (id, slug, name, is_default, is_active, default_locale)
VALUES (1, 'default', 'Default site', 1, 1, 'en');

-- Add site_id column to existing tables so content can be scoped without
-- breaking older queries (NULL = "shared / available to every site").
ALTER TABLE `page` ADD COLUMN IF NOT EXISTS `site_id` INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `post` ADD COLUMN IF NOT EXISTS `site_id` INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `media` ADD COLUMN IF NOT EXISTS `site_id` INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `content_entries` ADD COLUMN IF NOT EXISTS `site_id` INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `ecom_products` ADD COLUMN IF NOT EXISTS `site_id` INT UNSIGNED NULL AFTER `id`;
ALTER TABLE `ecom_orders`   ADD COLUMN IF NOT EXISTS `site_id` INT UNSIGNED NULL AFTER `id`;

-- Indexes to keep per-site queries fast.
CREATE INDEX IF NOT EXISTS `idx_page_site`            ON `page` (`site_id`);
CREATE INDEX IF NOT EXISTS `idx_post_site`            ON `post` (`site_id`);
CREATE INDEX IF NOT EXISTS `idx_media_site`           ON `media` (`site_id`);
CREATE INDEX IF NOT EXISTS `idx_content_entries_site` ON `content_entries` (`site_id`);
CREATE INDEX IF NOT EXISTS `idx_ecom_products_site`   ON `ecom_products` (`site_id`);
CREATE INDEX IF NOT EXISTS `idx_ecom_orders_site`     ON `ecom_orders` (`site_id`);

-- New permission for managing sites.
INSERT IGNORE INTO `permissions` (slug, module, action, description) VALUES
    ('sites.view',   'sites', 'view',   'View sites'),
    ('sites.manage', 'sites', 'manage', 'Create / edit / delete sites');
