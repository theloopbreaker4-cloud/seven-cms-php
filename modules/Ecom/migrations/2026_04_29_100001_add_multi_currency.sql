-- Multi-currency support: enabled currencies + FX rates + customer preferred currency.
--
-- Existing data is preserved: orders.currency stays as-is, products price stays in
-- the shop's base currency. Conversion is computed at display/checkout time.

CREATE TABLE IF NOT EXISTS ecom_currencies (
    code        CHAR(3)         NOT NULL PRIMARY KEY,         -- ISO 4217
    is_enabled  TINYINT(1)      NOT NULL DEFAULT 1,
    is_base     TINYINT(1)      NOT NULL DEFAULT 0,           -- exactly one row should be base
    label       VARCHAR(64)     NULL,
    created_at  DATETIME        NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ecom_fx_rates (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    base_code       CHAR(3)     NOT NULL,                     -- "from"
    quote_code      CHAR(3)     NOT NULL,                     -- "to"
    rate            DECIMAL(18,8) NOT NULL,                   -- 1 base = rate * quote
    source          VARCHAR(60) NOT NULL DEFAULT 'manual',
    fetched_at      DATETIME    NOT NULL,
    UNIQUE KEY uniq_pair (base_code, quote_code, fetched_at),
    INDEX idx_pair_recent (base_code, quote_code, fetched_at DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customer preferred currency. Falls back to ecom.currency setting.
ALTER TABLE ecom_customers
    ADD COLUMN preferred_currency CHAR(3) NULL AFTER country;

-- Seed common currencies. Base = USD.
INSERT IGNORE INTO ecom_currencies (code, is_enabled, is_base, label, created_at) VALUES
    ('USD', 1, 1, 'US Dollar',        NOW()),
    ('EUR', 1, 0, 'Euro',             NOW()),
    ('GBP', 1, 0, 'Pound Sterling',   NOW()),
    ('RUB', 0, 0, 'Russian Rouble',   NOW()),
    ('UAH', 0, 0, 'Ukrainian Hryvnia',NOW()),
    ('GEL', 0, 0, 'Georgian Lari',    NOW()),
    ('AMD', 0, 0, 'Armenian Dram',    NOW()),
    ('AZN', 0, 0, 'Azerbaijani Manat',NOW()),
    ('JPY', 0, 0, 'Japanese Yen',     NOW()),
    ('CHF', 0, 0, 'Swiss Franc',      NOW());

-- Settings flags read by Money + checkout.
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('ecom.multi_currency_enabled', '0'),
    ('ecom.fx_provider',            'manual');
