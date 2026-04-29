-- Outbound email queue. Mailer::queue() inserts; worker pops + sends.

CREATE TABLE IF NOT EXISTS mail_queue (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email        VARCHAR(255)    NOT NULL,
    to_name         VARCHAR(255)    NULL,
    from_email      VARCHAR(255)    NULL,
    from_name       VARCHAR(255)    NULL,
    reply_to        VARCHAR(255)    NULL,
    subject         VARCHAR(500)    NOT NULL,
    body_html       MEDIUMTEXT      NULL,
    body_text       MEDIUMTEXT      NULL,
    headers_json    TEXT            NULL,
    attempts        TINYINT         UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    TINYINT         UNSIGNED NOT NULL DEFAULT 5,
    status          ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
    last_error      TEXT            NULL,
    available_at    DATETIME        NOT NULL,
    sent_at         DATETIME        NULL,
    created_at      DATETIME        NOT NULL,
    INDEX idx_mq_status_avail (status, available_at),
    INDEX idx_mq_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
