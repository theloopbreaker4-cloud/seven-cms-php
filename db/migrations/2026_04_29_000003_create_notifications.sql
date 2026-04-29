-- In-app notifications shown in the admin bell dropdown.
-- For broadcast (all admins) leave user_id NULL; for user-targeted set user_id.

CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NULL,                            -- NULL = broadcast to all admins
    type        VARCHAR(80)     NOT NULL,                        -- 'order.placed' | 'subscription.renewed' | 'system' | …
    title       VARCHAR(255)    NOT NULL,
    message     TEXT            NULL,
    url         VARCHAR(500)    NULL,                            -- where the bell entry should link to
    icon        VARCHAR(40)     NULL,                            -- emoji or token icon
    severity    ENUM('info','success','warning','error') NOT NULL DEFAULT 'info',
    meta        JSON            NULL,
    read_at     DATETIME        NULL,
    created_at  DATETIME        NOT NULL,
    INDEX idx_n_user_read (user_id, read_at),
    INDEX idx_n_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Calendar events shown on the dashboard calendar widget. Plugins can write here.
CREATE TABLE IF NOT EXISTS calendar_events (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED    NULL,                            -- NULL = visible to all admins
    title       VARCHAR(255)    NOT NULL,
    description TEXT            NULL,
    starts_at   DATETIME        NOT NULL,
    ends_at     DATETIME        NULL,
    color       VARCHAR(20)     NULL,                            -- CSS color, default --primary
    source_type VARCHAR(40)     NOT NULL DEFAULT 'manual',       -- 'manual' | 'post' | 'order' | etc.
    source_id   INT UNSIGNED    NULL,
    notify_at   DATETIME        NULL,                            -- when to fire a notification
    notified    TINYINT(1)      NOT NULL DEFAULT 0,
    url         VARCHAR(500)    NULL,
    created_at  DATETIME        NOT NULL,
    INDEX idx_ce_starts (starts_at),
    INDEX idx_ce_notify (notify_at, notified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
