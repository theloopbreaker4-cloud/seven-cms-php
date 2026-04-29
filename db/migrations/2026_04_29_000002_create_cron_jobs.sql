-- Persistent cron job registry. Schedules registered once via CronRunner::register();
-- the row is upserted on each request so jobs added by a plugin become visible
-- without a manual install step.

CREATE TABLE IF NOT EXISTS cron_jobs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120)    NOT NULL UNIQUE,
    schedule        VARCHAR(60)     NOT NULL,                    -- "@hourly" | "@daily" | "*/N" minutes
    callback        VARCHAR(255)    NULL,                        -- 'Class::method' for in-process discovery
    is_enabled      TINYINT(1)      NOT NULL DEFAULT 1,
    last_run_at     DATETIME        NULL,
    next_run_at     DATETIME        NULL,
    last_status     ENUM('ok','error','skipped') NULL,
    last_error      TEXT            NULL,
    last_duration_ms INT UNSIGNED   NULL,
    created_at      DATETIME        NOT NULL,
    INDEX idx_cj_due (is_enabled, next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
