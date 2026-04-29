# Ecom — full-stack e-commerce plugin for SevenCMS

Adds catalog, cart, checkout, payments, refunds, subscriptions, digital
deliveries, discount codes, and a sales dashboard. Three product kinds are
supported in one schema: **physical**, **digital**, and **service**
(one-off or subscription). Stripe and PayPal are first-class citizens; a
manual gateway covers cash-on-delivery / bank transfer.

---

## Install

```bash
php bin/sev plugin:install Ecom
```

This runs `modules/Ecom/migrations/2026_04_26_100001_create_ecom.sql`,
seeds permissions, and writes default settings (`USD`, no tax, sandbox PayPal).

Then in admin:

1. **Plugins → Ecom → Enable**
2. **Shop → Settings** → set currency, paste Stripe / PayPal keys
3. **Shop → Products → New** → create your first product

---

## Configuration

Settings are stored in the `settings` table with keys `ecom.*`:

| Key                              | Notes                                                  |
|----------------------------------|--------------------------------------------------------|
| `ecom.currency`                  | ISO 4217 (`USD`, `EUR`, `RUB`, …)                      |
| `ecom.tax_rate`                  | Default rate, percent (e.g. `20` for 20%)              |
| `ecom.tax_inclusive`             | `1` if prices already include tax                      |
| `ecom.stripe_public_key`         | `pk_live_…` or `pk_test_…`                             |
| `ecom.stripe_secret_key`         | server-side key                                        |
| `ecom.stripe_webhook_secret`     | from Stripe dashboard → webhooks                       |
| `ecom.paypal_client_id`          | OAuth client id                                        |
| `ecom.paypal_secret`             | OAuth client secret                                    |
| `ecom.paypal_mode`               | `sandbox` / `live`                                     |
| `ecom.paypal_webhook_id`         | for signature verification                             |

Any of those may also be supplied via env vars (`STRIPE_SECRET_KEY`,
`PAYPAL_CLIENT_ID`, …) — env wins over an empty DB value.

### Webhooks

Add these URLs in your gateway dashboards:

- Stripe → `https://<your-host>/api/v1/shop/webhook/stripe`
- PayPal → `https://<your-host>/api/v1/shop/webhook/paypal`

The handler is idempotent (event id stored in `ecom_webhook_events`),
verifies the signature, and dispatches `ecom.order.paid`,
`ecom.subscription.renewed`, etc.

---

## Data model

| Table                     | Purpose                                                     |
|---------------------------|-------------------------------------------------------------|
| `ecom_categories`         | Hierarchical product categories                             |
| `ecom_products`           | One row per product; `kind` ∈ {physical, digital, service}  |
| `ecom_product_variants`   | Per-attribute combinations (size/colour) overriding price/stock |
| `ecom_digital_assets`     | Files / external URLs / license templates                   |
| `ecom_customers`          | Shop customers (linked to `users` when authed)              |
| `ecom_addresses`          | Saved billing/shipping addresses per customer               |
| `ecom_carts` + `ecom_cart_items` | Session-keyed shopping cart                          |
| `ecom_discounts`          | Promo codes (percent / fixed / free shipping)               |
| `ecom_tax_rates`          | Per-region rates; falls back to `ecom.tax_rate`             |
| `ecom_shipping_rates`     | Manual shipping options                                     |
| `ecom_orders` + `ecom_order_items` | Orders + denormalized line snapshots                |
| `ecom_payments`           | One row per attempt; supports multiple payment methods      |
| `ecom_subscriptions`      | Local mirror of recurring billing schedules                 |
| `ecom_downloads`          | Secure tokens for digital asset delivery                    |
| `ecom_refunds`            | Audit trail for refunds                                     |
| `ecom_webhook_events`     | Idempotency for inbound webhooks                            |

All money columns are integers in **minor units** of the order's currency.
Use the `Money` helper to format/parse.

---

## Hooks

Plugins listen via `Event::listen($name, callable)`:

| Event                              | Payload                                           |
|------------------------------------|---------------------------------------------------|
| `ecom.order.created`               | `Order` model                                     |
| `ecom.order.paid`                  | `Order` model                                     |
| `ecom.order.fulfilled`             | `Order` model                                     |
| `ecom.order.cancelled`             | `['order' => Order, 'reason' => string\|null]`    |
| `ecom.order.refunded`              | `['order' => Order, 'amount' => int]`             |
| `ecom.subscription.renewed`        | `Subscription` model                              |
| `ecom.subscription.cancel_scheduled` | `Subscription` model                            |
| `ecom.subscription.cancelled`      | `Subscription` model                              |
| `ecom.download.granted`            | `['order' => Order, 'product' => Product, 'tokens' => array]` |

Use the existing `Hooks::fire(Hooks::AFTER_CREATE, 'order', ...)` channels
to react to standard CRUD lifecycle events.

---

## Adding a payment gateway

1. Create `modules/Ecom/gateways/MyGateway.php` implementing `PaymentGateway`.
2. Register in your plugin's `boot()`:
   ```php
   GatewayRegistry::register('mygw', fn() => new MyGateway());
   ```
3. Add a webhook URL: `/api/v1/shop/webhook/mygw` works out of the box —
   `EcomWebhookApiController` will route to your gateway via `GatewayRegistry`.

`PaymentGateway::handleWebhook` must return one of these normalized event
types — anything else is logged and ignored:

```
payment.succeeded
payment.failed
subscription.renewed
subscription.cancelled
refund.created
unknown
```

---

## Storefront REST API

Public catalog/cart routes (no auth):

| Endpoint                                  | Notes                                       |
|-------------------------------------------|---------------------------------------------|
| `GET  /api/v1/shop/products`              | Filters: `category`, `kind`, `q`, `locale`  |
| `GET  /api/v1/shop/products/:slug`        | Includes variants                           |
| `GET  /api/v1/shop/categories`            |                                             |
| `GET  /api/v1/shop/cart`                  | Auto-creates a cart cookie                  |
| `POST /api/v1/shop/cart/items`            | `{product_id, variant_id?, quantity}`       |
| `PUT  /api/v1/shop/cart/items/:id`        | `{quantity}`                                |
| `DELETE /api/v1/shop/cart/items/:id`      |                                             |
| `POST /api/v1/shop/cart/discount`         | `{code}`                                    |
| `POST /api/v1/shop/checkout`              | Creates an order and returns gateway intent |
| `GET  /api/v1/shop/orders/:number`        | Buyer lookup (email match or auth)          |
| `GET  /api/v1/shop/subscriptions`         | Auth required                               |
| `POST /api/v1/shop/subscriptions/:id/cancel` | Auth required, cancels at period end     |

The `POST /api/v1/shop/checkout` response shape:

```json
{
  "order":   { "id": 1, "number": "SC-20260426-A12B", "total": 1999, … },
  "payment": {
    "gateway_id":   "pi_3...",
    "client_secret":"pi_3..._secret_...",
    "redirect_url": null
  },
  "subscription": null
}
```

The frontend uses `payment.client_secret` (Stripe) or `payment.redirect_url`
(PayPal hosted approval) to complete the purchase.

---

## Digital downloads

A `digital` product can have one or more rows in `ecom_digital_assets`:

- `media_id` — refers to a `media` row (use the Media library)
- or `external_url` — for files served from a CDN/bucket
- `license_template` — text run through `{order}, {email}, {license}, {date}`
- `max_downloads`, `expires_days` — per-grant limits

When the order is paid, `OrderService::fulfillPaidOrder()` issues a row in
`ecom_downloads` per asset. The download URL is:

```
/{lang}/shop/download/{token}
```

It streams the local file or 302-redirects to the external URL. Counter
increments on each hit; expired tokens return 410.

---

## Subscriptions

For `service` products with `is_subscription = 1`, set:

- `billing_period` = `day | week | month | year`
- `billing_interval` = number (e.g. 1 = monthly when period=month)
- `trial_days` = optional integer

At checkout, `OrderService::createFromCart()` calls
`gateway->createSubscription(...)` and persists a row in
`ecom_subscriptions`. Renewals come in via webhooks; the local row's
`current_period_end` is advanced when `subscription.renewed` arrives.

A future cron job (`php bin/sev ecom:bill-due`) will handle the manual
gateway path for self-hosted billing.

---

## Permissions

Seeded into the existing RBAC. Editor role gets product/order management
out of the box; admin role gets everything via the legacy `users.role` check.

```
ecom.products.{view,create,update,delete}
ecom.orders.{view,manage,refund}
ecom.customers.{view,manage}
ecom.discounts.manage
ecom.subscriptions.{view,manage}
ecom.settings.update
ecom.reports.view
```

---

## File map

```
modules/Ecom/
├── plugin.json
├── Module.php
├── ECOM.md
├── migrations/
│   └── 2026_04_26_100001_create_ecom.sql
├── controllers/
│   ├── EcomDashboardAdminController.php
│   ├── EcomProductsAdminController.php
│   ├── EcomOrdersAdminController.php
│   ├── EcomCustomersAdminController.php
│   ├── EcomDiscountsAdminController.php
│   ├── EcomSubscriptionsAdminController.php
│   ├── EcomSettingsAdminController.php
│   └── EcomDownloadController.php
├── apiControllers/
│   ├── ShopV1ApiController.php
│   └── EcomWebhookApiController.php
├── models/
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── EcomCustomer.php
│   ├── Order.php
│   ├── Discount.php
│   └── Subscription.php
├── services/
│   ├── Money.php
│   ├── CartService.php
│   ├── TaxCalculator.php
│   ├── ShippingCalculator.php
│   ├── GatewayRegistry.php
│   ├── OrderService.php
│   ├── DigitalDelivery.php
│   └── EcomMail.php
├── gateways/
│   ├── PaymentGateway.php
│   ├── ManualGateway.php
│   ├── StripeGateway.php
│   └── PayPalGateway.php
└── emails/
    ├── order_placed.html
    ├── order_paid.html
    ├── order_shipped.html
    ├── order_cancelled.html
    ├── digital_delivered.html
    ├── subscription_renewed.html
    └── subscription_cancelled.html
```

Admin views live under `app/views/admin/ecom/` per the existing
sevenPHP convention.
