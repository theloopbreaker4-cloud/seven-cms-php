-- =================================================================
-- E-commerce schema
-- =================================================================
-- Supports three product kinds in one model:
--   physical     — stocked, shippable
--   digital      — download/license delivered after payment
--   service      — non-shippable; can be one-off or subscription
--
-- Money is stored as INT in *minor* units (cents/kopeks) — never as float.
-- Currency lives at the order level (read from settings at checkout time).
-- =================================================================

-- Catalog
CREATE TABLE IF NOT EXISTS `ecom_categories` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `parent_id`    INT UNSIGNED  NULL,
    `slug`         VARCHAR(160)  NOT NULL,
    `name`         JSON          NOT NULL DEFAULT ('{}'),
    `description`  JSON          NOT NULL DEFAULT ('{}'),
    `image_id`     INT UNSIGNED  NULL,
    `sort_order`   INT           NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_cat_slug` (`slug`),
    KEY `idx_ecom_cat_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecom_products` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `slug`             VARCHAR(191)  NOT NULL,
    `kind`             ENUM('physical','digital','service')
                       NOT NULL DEFAULT 'physical',
    `name`             JSON          NOT NULL DEFAULT ('{}'),
    `short_description`JSON          NOT NULL DEFAULT ('{}'),
    `description`      JSON          NOT NULL DEFAULT ('{}'),
    `images`           JSON          NOT NULL DEFAULT ('[]'),
    `category_id`      INT UNSIGNED  NULL,
    `tags`             JSON          NOT NULL DEFAULT ('[]'),
    -- Recurring billing — only meaningful when kind='service'
    `is_subscription`  TINYINT(1)    NOT NULL DEFAULT 0,
    `billing_period`   ENUM('day','week','month','year')
                       NULL,
    `billing_interval` SMALLINT      NULL,
    `trial_days`       SMALLINT      NULL,
    -- Default pricing/inventory; overridden by variants when present
    `base_price`       INT           NOT NULL DEFAULT 0,
    `compare_at_price` INT           NULL,
    `sku`              VARCHAR(120)  NULL,
    `track_inventory`  TINYINT(1)    NOT NULL DEFAULT 0,
    `stock`            INT           NOT NULL DEFAULT 0,
    `weight_grams`     INT           NULL,
    `tax_class`        VARCHAR(60)   NOT NULL DEFAULT 'standard',
    -- Visibility
    `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
    `is_featured`      TINYINT(1)    NOT NULL DEFAULT 0,
    `published_at`     DATETIME      NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_product_slug` (`slug`),
    KEY `idx_ecom_product_category` (`category_id`),
    KEY `idx_ecom_product_active`   (`is_active`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Variants (size/color etc) — keyed by attribute combination stored as JSON.
CREATE TABLE IF NOT EXISTS `ecom_product_variants` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`    INT UNSIGNED  NOT NULL,
    `sku`           VARCHAR(120)  NULL,
    `attributes`    JSON          NOT NULL DEFAULT ('{}'),
    `price`         INT           NOT NULL DEFAULT 0,
    `compare_at_price` INT        NULL,
    `stock`         INT           NOT NULL DEFAULT 0,
    `weight_grams`  INT           NULL,
    `image_id`      INT UNSIGNED  NULL,
    `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`    INT           NOT NULL DEFAULT 0,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_variant_product` (`product_id`),
    UNIQUE KEY `uniq_ecom_variant_sku` (`sku`),
    CONSTRAINT `fk_ecom_variant_product`
        FOREIGN KEY (`product_id`) REFERENCES `ecom_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Digital downloads — file or external URL
CREATE TABLE IF NOT EXISTS `ecom_digital_assets` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `product_id`   INT UNSIGNED  NOT NULL,
    `variant_id`   INT UNSIGNED  NULL,
    `name`         VARCHAR(191)  NOT NULL,
    `media_id`     INT UNSIGNED  NULL,
    `external_url` VARCHAR(500)  NULL,
    `license_template` TEXT      NULL,
    `max_downloads` INT          NULL,
    `expires_days`  INT          NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_digital_product` (`product_id`),
    CONSTRAINT `fk_ecom_digital_product`
        FOREIGN KEY (`product_id`) REFERENCES `ecom_products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Customers (1:1 with users.id when authed; standalone for guest checkout)
CREATE TABLE IF NOT EXISTS `ecom_customers` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `user_id`       INT UNSIGNED  NULL,
    `email`         VARCHAR(191)  NOT NULL,
    `first_name`    VARCHAR(120)  NULL,
    `last_name`     VARCHAR(120)  NULL,
    `phone`         VARCHAR(40)   NULL,
    `accepts_marketing` TINYINT(1) NOT NULL DEFAULT 0,
    `stripe_customer_id`  VARCHAR(120) NULL,
    `paypal_payer_id`     VARCHAR(120) NULL,
    `notes`         TEXT          NULL,
    `total_spent`   BIGINT        NOT NULL DEFAULT 0,
    `orders_count`  INT UNSIGNED  NOT NULL DEFAULT 0,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_customer_email` (`email`),
    KEY `idx_ecom_customer_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecom_addresses` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `customer_id`  INT UNSIGNED  NOT NULL,
    `kind`         ENUM('billing','shipping') NOT NULL,
    `is_default`   TINYINT(1)    NOT NULL DEFAULT 0,
    `name`         VARCHAR(191)  NULL,
    `company`      VARCHAR(191)  NULL,
    `phone`        VARCHAR(40)   NULL,
    `line1`        VARCHAR(255)  NOT NULL,
    `line2`        VARCHAR(255)  NULL,
    `city`         VARCHAR(120)  NOT NULL,
    `state`        VARCHAR(120)  NULL,
    `postcode`     VARCHAR(40)   NULL,
    `country`      CHAR(2)       NOT NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_addr_customer` (`customer_id`),
    CONSTRAINT `fk_ecom_addr_customer`
        FOREIGN KEY (`customer_id`) REFERENCES `ecom_customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Carts (session id for guests, customer_id for authed; merge on login)
CREATE TABLE IF NOT EXISTS `ecom_carts` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `token`        CHAR(40)      NOT NULL,
    `customer_id`  INT UNSIGNED  NULL,
    `currency`     CHAR(3)       NOT NULL DEFAULT 'USD',
    `discount_code`VARCHAR(60)   NULL,
    `note`         VARCHAR(500)  NULL,
    `expires_at`   DATETIME      NOT NULL,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_cart_token` (`token`),
    KEY `idx_ecom_cart_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecom_cart_items` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `cart_id`    INT UNSIGNED  NOT NULL,
    `product_id` INT UNSIGNED  NOT NULL,
    `variant_id` INT UNSIGNED  NULL,
    `quantity`   INT UNSIGNED  NOT NULL DEFAULT 1,
    `unit_price` INT           NOT NULL,
    `meta`       JSON          NOT NULL DEFAULT ('{}'),
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_ci_cart` (`cart_id`),
    UNIQUE KEY `uniq_ecom_ci_cart_variant` (`cart_id`, `product_id`, `variant_id`),
    CONSTRAINT `fk_ecom_ci_cart`
        FOREIGN KEY (`cart_id`) REFERENCES `ecom_carts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Discounts
CREATE TABLE IF NOT EXISTS `ecom_discounts` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `code`            VARCHAR(60)   NOT NULL,
    `kind`            ENUM('percent','fixed','free_shipping')
                      NOT NULL DEFAULT 'percent',
    `value`           INT           NOT NULL DEFAULT 0,
    `min_subtotal`    INT           NULL,
    `applies_to`      ENUM('all','category','product')
                      NOT NULL DEFAULT 'all',
    `applies_id`      INT UNSIGNED  NULL,
    `usage_limit`     INT UNSIGNED  NULL,
    `usage_count`     INT UNSIGNED  NOT NULL DEFAULT 0,
    `per_customer_limit` INT UNSIGNED NULL,
    `starts_at`       DATETIME      NULL,
    `ends_at`         DATETIME      NULL,
    `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_discount_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tax classes (per-region rates)
CREATE TABLE IF NOT EXISTS `ecom_tax_rates` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`         VARCHAR(120)  NOT NULL,
    `tax_class`    VARCHAR(60)   NOT NULL DEFAULT 'standard',
    `country`      CHAR(2)       NOT NULL,
    `state`        VARCHAR(120)  NULL,
    `rate_bp`      INT UNSIGNED  NOT NULL,
    `is_inclusive` TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_tax_country` (`country`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shipping methods (manual rates)
CREATE TABLE IF NOT EXISTS `ecom_shipping_rates` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `name`         JSON          NOT NULL DEFAULT ('{}'),
    `country`      CHAR(2)       NULL,
    `min_subtotal` INT           NULL,
    `max_subtotal` INT           NULL,
    `min_weight`   INT           NULL,
    `max_weight`   INT           NULL,
    `price`        INT           NOT NULL DEFAULT 0,
    `is_active`    TINYINT(1)    NOT NULL DEFAULT 1,
    `sort_order`   INT           NOT NULL DEFAULT 0,
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders + items
CREATE TABLE IF NOT EXISTS `ecom_orders` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `number`          VARCHAR(40)   NOT NULL,
    `customer_id`     INT UNSIGNED  NULL,
    `email`           VARCHAR(191)  NOT NULL,
    `currency`        CHAR(3)       NOT NULL DEFAULT 'USD',
    `status`          ENUM('pending','paid','partially_paid','fulfilled','shipped','delivered',
                          'cancelled','refunded','partially_refunded','failed')
                      NOT NULL DEFAULT 'pending',
    `payment_status`  ENUM('unpaid','authorized','paid','refunded','partially_refunded','failed')
                      NOT NULL DEFAULT 'unpaid',
    `fulfillment_status` ENUM('unfulfilled','partial','fulfilled')
                      NOT NULL DEFAULT 'unfulfilled',
    `subtotal`        INT           NOT NULL DEFAULT 0,
    `discount_total`  INT           NOT NULL DEFAULT 0,
    `tax_total`       INT           NOT NULL DEFAULT 0,
    `shipping_total`  INT           NOT NULL DEFAULT 0,
    `total`           INT           NOT NULL DEFAULT 0,
    `discount_code`   VARCHAR(60)   NULL,
    `billing_address` JSON          NOT NULL DEFAULT ('{}'),
    `shipping_address`JSON          NOT NULL DEFAULT ('{}'),
    `shipping_method` VARCHAR(120)  NULL,
    `note`            TEXT          NULL,
    `meta`            JSON          NOT NULL DEFAULT ('{}'),
    `placed_at`       DATETIME      NULL,
    `paid_at`         DATETIME      NULL,
    `cancelled_at`    DATETIME      NULL,
    `refunded_at`     DATETIME      NULL,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_order_number` (`number`),
    KEY `idx_ecom_order_customer` (`customer_id`),
    KEY `idx_ecom_order_status`   (`status`),
    KEY `idx_ecom_order_placed`   (`placed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ecom_order_items` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`      INT UNSIGNED  NOT NULL,
    `product_id`    INT UNSIGNED  NULL,
    `variant_id`    INT UNSIGNED  NULL,
    `kind`          ENUM('physical','digital','service') NOT NULL DEFAULT 'physical',
    `name`          VARCHAR(255)  NOT NULL,
    `sku`           VARCHAR(120)  NULL,
    `quantity`      INT UNSIGNED  NOT NULL DEFAULT 1,
    `unit_price`    INT           NOT NULL DEFAULT 0,
    `discount`      INT           NOT NULL DEFAULT 0,
    `tax`           INT           NOT NULL DEFAULT 0,
    `total`         INT           NOT NULL DEFAULT 0,
    `meta`          JSON          NOT NULL DEFAULT ('{}'),
    PRIMARY KEY (`id`),
    KEY `idx_ecom_oi_order`   (`order_id`),
    KEY `idx_ecom_oi_product` (`product_id`),
    CONSTRAINT `fk_ecom_oi_order`
        FOREIGN KEY (`order_id`) REFERENCES `ecom_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments — one order may have multiple payment attempts/methods
CREATE TABLE IF NOT EXISTS `ecom_payments` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`        INT UNSIGNED  NOT NULL,
    `gateway`         VARCHAR(40)   NOT NULL,
    `gateway_id`      VARCHAR(191)  NULL,
    `status`          ENUM('pending','authorized','paid','failed','refunded','cancelled')
                      NOT NULL DEFAULT 'pending',
    `amount`          INT           NOT NULL,
    `currency`        CHAR(3)       NOT NULL,
    `payload`         JSON          NOT NULL DEFAULT ('{}'),
    `error`           TEXT          NULL,
    `paid_at`         DATETIME      NULL,
    `refunded_amount` INT           NOT NULL DEFAULT 0,
    `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_pay_order`   (`order_id`),
    KEY `idx_ecom_pay_gateway` (`gateway`,`gateway_id`),
    CONSTRAINT `fk_ecom_pay_order`
        FOREIGN KEY (`order_id`) REFERENCES `ecom_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subscriptions (recurring billing for kind='service' + is_subscription=1)
CREATE TABLE IF NOT EXISTS `ecom_subscriptions` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `customer_id`      INT UNSIGNED  NOT NULL,
    `product_id`       INT UNSIGNED  NOT NULL,
    `variant_id`       INT UNSIGNED  NULL,
    `gateway`          VARCHAR(40)   NOT NULL,
    `gateway_subscription_id` VARCHAR(191) NULL,
    `status`           ENUM('trialing','active','past_due','paused','cancelled','expired')
                       NOT NULL DEFAULT 'active',
    `currency`         CHAR(3)       NOT NULL,
    `unit_price`       INT           NOT NULL,
    `quantity`         INT UNSIGNED  NOT NULL DEFAULT 1,
    `billing_period`   ENUM('day','week','month','year') NOT NULL DEFAULT 'month',
    `billing_interval` SMALLINT      NOT NULL DEFAULT 1,
    `current_period_start` DATETIME  NULL,
    `current_period_end`   DATETIME  NULL,
    `trial_ends_at`        DATETIME  NULL,
    `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0,
    `cancelled_at`     DATETIME      NULL,
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_sub_customer` (`customer_id`),
    KEY `idx_ecom_sub_status`   (`status`),
    KEY `idx_ecom_sub_period`   (`current_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Digital download grants — one row per product/variant per order
CREATE TABLE IF NOT EXISTS `ecom_downloads` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`      INT UNSIGNED  NOT NULL,
    `customer_id`   INT UNSIGNED  NOT NULL,
    `product_id`    INT UNSIGNED  NOT NULL,
    `variant_id`    INT UNSIGNED  NULL,
    `asset_id`      INT UNSIGNED  NOT NULL,
    `token`         CHAR(64)      NOT NULL,
    `license_key`   VARCHAR(191)  NULL,
    `download_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_downloads`  INT UNSIGNED NULL,
    `expires_at`    DATETIME      NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_download_token` (`token`),
    KEY `idx_ecom_download_customer` (`customer_id`),
    KEY `idx_ecom_download_order`    (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Refunds (audit trail)
CREATE TABLE IF NOT EXISTS `ecom_refunds` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `order_id`   INT UNSIGNED  NOT NULL,
    `payment_id` INT UNSIGNED  NULL,
    `amount`     INT           NOT NULL,
    `reason`     VARCHAR(255)  NULL,
    `gateway_id` VARCHAR(191)  NULL,
    `created_by` INT UNSIGNED  NULL,
    `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ecom_refund_order` (`order_id`),
    CONSTRAINT `fk_ecom_refund_order`
        FOREIGN KEY (`order_id`) REFERENCES `ecom_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stripe webhook idempotency
CREATE TABLE IF NOT EXISTS `ecom_webhook_events` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `gateway`    VARCHAR(40)     NOT NULL,
    `event_id`   VARCHAR(191)    NOT NULL,
    `event_type` VARCHAR(120)    NOT NULL,
    `payload`    JSON            NOT NULL,
    `processed`  TINYINT(1)      NOT NULL DEFAULT 0,
    `error`      TEXT            NULL,
    `received_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ecom_webhook_event` (`gateway`,`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed permissions (RBAC)
INSERT IGNORE INTO `permissions` (slug, module, action, description) VALUES
    ('ecom.products.view',   'ecom', 'view',   'View products'),
    ('ecom.products.create', 'ecom', 'create', 'Create products'),
    ('ecom.products.update', 'ecom', 'update', 'Edit products'),
    ('ecom.products.delete', 'ecom', 'delete', 'Delete products'),
    ('ecom.orders.view',     'ecom', 'view',   'View orders'),
    ('ecom.orders.manage',   'ecom', 'manage', 'Update order status / fulfill'),
    ('ecom.orders.refund',   'ecom', 'refund', 'Issue refunds'),
    ('ecom.customers.view',  'ecom', 'view',   'View customers'),
    ('ecom.customers.manage','ecom', 'manage', 'Edit customer details'),
    ('ecom.discounts.manage','ecom', 'manage', 'Manage discount codes'),
    ('ecom.subscriptions.view',   'ecom', 'view',   'View subscriptions'),
    ('ecom.subscriptions.manage', 'ecom', 'manage', 'Cancel/pause subscriptions'),
    ('ecom.settings.update', 'ecom', 'update', 'Update e-commerce settings'),
    ('ecom.reports.view',    'ecom', 'view',   'View sales reports');

-- Editor role gets product/order management; system admin gets everything implicitly.
INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id
  FROM `roles` r JOIN `permissions` p ON 1=1
 WHERE r.slug = 'editor'
   AND p.slug IN (
       'ecom.products.view','ecom.products.create','ecom.products.update','ecom.products.delete',
       'ecom.orders.view','ecom.orders.manage',
       'ecom.customers.view','ecom.customers.manage',
       'ecom.discounts.manage',
       'ecom.subscriptions.view',
       'ecom.reports.view'
   );
