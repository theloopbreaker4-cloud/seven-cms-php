-- Refresh / personal access tokens for the API.
-- Access tokens are short-lived JWTs (signed with JWT_SECRET, no DB lookup).
-- Refresh tokens are long-lived, stored hashed (SHA-256), one row per token.
-- A single rotated refresh token replaces the previous row (track via parent_id).

CREATE TABLE IF NOT EXISTS `api_refresh_tokens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`        INT UNSIGNED    NOT NULL,
    `token_hash`     CHAR(64)        NOT NULL,
    `parent_id`      BIGINT UNSIGNED NULL,
    `device`         VARCHAR(255)    NULL,
    `ip`             VARCHAR(64)     NULL,
    `expires_at`     DATETIME        NOT NULL,
    `revoked_at`     DATETIME        NULL,
    `last_used_at`   DATETIME        NULL,
    `created_at`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_refresh_hash` (`token_hash`),
    KEY `idx_refresh_user`   (`user_id`),
    KEY `idx_refresh_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TOTP secrets for 2FA. Stored as base32 strings; recovery_codes are bcrypt-hashed.
CREATE TABLE IF NOT EXISTS `user_totp` (
    `user_id`         INT UNSIGNED  NOT NULL,
    `secret`          VARCHAR(64)   NOT NULL,
    `enabled`         TINYINT(1)    NOT NULL DEFAULT 0,
    `recovery_codes`  JSON          NOT NULL DEFAULT ('[]'),
    `confirmed_at`    DATETIME      NULL,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                      ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
