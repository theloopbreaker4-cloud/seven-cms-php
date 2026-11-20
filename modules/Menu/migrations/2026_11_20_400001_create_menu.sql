-- Menu tables, previously created by RedBean's fluid mode. Explicit schema + repair.

CREATE TABLE IF NOT EXISTS `menu` (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    handle      VARCHAR(64)  NOT NULL DEFAULT '',
    name        VARCHAR(191) NOT NULL DEFAULT '',
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_menu_handle (handle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `menuitem` (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    menu_id     INT UNSIGNED NULL,
    parent_id   INT UNSIGNED NULL,
    label       TEXT NULL,
    url         VARCHAR(500) NOT NULL DEFAULT '',
    link_type   VARCHAR(32)  NOT NULL DEFAULT 'custom',
    link_id     INT UNSIGNED NULL,
    target      VARCHAR(16)  NOT NULL DEFAULT '_self',
    sort_order  INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    INDEX idx_menuitem_menu (menu_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `menuitem` MODIFY COLUMN IF EXISTS label TEXT NULL;
ALTER TABLE `menuitem` MODIFY COLUMN IF EXISTS url VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE `menuitem` MODIFY COLUMN IF EXISTS sort_order INT NOT NULL DEFAULT 0;
