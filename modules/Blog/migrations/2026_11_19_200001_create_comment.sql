-- Blog comments. Visitors post into 'pending'; admins approve or delete.
CREATE TABLE IF NOT EXISTS `comment` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `post_id`      INT UNSIGNED  NOT NULL,
    `user_id`      INT UNSIGNED  NULL,
    `parent_id`    INT UNSIGNED  NULL,
    `author_name`  VARCHAR(80)   NOT NULL DEFAULT '',
    `author_email` VARCHAR(191)  NOT NULL DEFAULT '',
    `content`      TEXT          NOT NULL,
    `status`       ENUM('pending','approved','spam') NOT NULL DEFAULT 'pending',
    `ip`           VARCHAR(45)   NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comment_post_status` (`post_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
