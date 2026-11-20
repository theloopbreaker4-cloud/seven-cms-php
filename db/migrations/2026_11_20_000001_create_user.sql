-- `user` used to be created implicitly by RedBean's fluid mode on the installer's
-- first save, so its column types depended on the first values written: a NULL
-- last_login_at became TINYINT and every later login failed with "Out of range
-- value for column 'last_login_at'". Give it an explicit schema.
CREATE TABLE IF NOT EXISTS `user` (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    first_name          VARCHAR(191) NULL,
    last_name           VARCHAR(191) NULL,
    user_name           VARCHAR(191) NULL,
    email               VARCHAR(191) NULL,
    password            VARCHAR(191) NULL,
    avatar              VARCHAR(191) NULL,
    mobile              VARCHAR(191) NULL,
    country             VARCHAR(191) NULL,
    role                VARCHAR(191) NULL,
    is_active           TINYINT UNSIGNED NULL,
    provider            VARCHAR(191) NULL,
    provider_id         VARCHAR(191) NULL,
    two_factor_enabled  TINYINT UNSIGNED NULL,
    two_factor_secret   VARCHAR(191) NULL,
    last_login_at       DATETIME NULL,
    created_at          DATETIME NULL,
    updated_at          DATETIME NULL,
    PRIMARY KEY (id),
    INDEX idx_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repair tables that fluid mode already created with the wrong types.
ALTER TABLE `user`
    MODIFY last_login_at DATETIME NULL,
    MODIFY provider_id   VARCHAR(191) NULL;
