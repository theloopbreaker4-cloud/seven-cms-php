-- `sociallink`, previously created by RedBean's fluid mode. Explicit schema + repair.

CREATE TABLE IF NOT EXISTS `sociallink` (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    platform    VARCHAR(64)  NOT NULL DEFAULT '',
    url         VARCHAR(500) NOT NULL DEFAULT '',
    label       VARCHAR(191) NOT NULL DEFAULT '',
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at  DATETIME NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `sociallink` MODIFY COLUMN IF EXISTS url VARCHAR(500) NOT NULL DEFAULT '';
ALTER TABLE `sociallink` MODIFY COLUMN IF EXISTS sort_order INT NOT NULL DEFAULT 0;
