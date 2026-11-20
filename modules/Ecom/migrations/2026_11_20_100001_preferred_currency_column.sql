-- 2026_04_29_100001 added preferred_currency "AFTER country", but ecom_customers has
-- no country column, so the ALTER failed (silently, under RedBean's fluid mode) and
-- the migration was still recorded as applied. Add the column where it's missing.
-- Works on MySQL and MariaDB (no ADD COLUMN IF NOT EXISTS needed).
SET @sev_sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE ecom_customers ADD COLUMN preferred_currency CHAR(3) NULL', 'DO 0') FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'ecom_customers' AND column_name = 'preferred_currency');
PREPARE sev_stmt FROM @sev_sql;
EXECUTE sev_stmt;
DEALLOCATE PREPARE sev_stmt;
