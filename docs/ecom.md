# E-commerce

[← Back to docs](index.md)

> Full reference also lives at [`modules/Ecom/ECOM.md`](../modules/Ecom/ECOM.md).
> This page is the user-facing operator's guide; the in-plugin doc focuses on
> developer extension points (writing a payment driver, hooks, etc.).

## Setup

```bash
php bin/sev plugin:install Ecom
```

Then in admin:

1. **Plugins → Ecom → Enable**
2. **Shop → Settings**: pick currency, paste Stripe / PayPal keys
3. **Shop → Products → New** → create your first product

### Webhooks

Configure in your gateway dashboards:

- Stripe → `https://<your-host>/api/v1/shop/webhook/stripe`
- PayPal → `https://<your-host>/api/v1/shop/webhook/paypal`

Subscribe at minimum to:

- **Stripe**: `payment_intent.succeeded`, `payment_intent.payment_failed`,
  `invoice.paid`, `customer.subscription.deleted`, `charge.refunded`
- **PayPal**: `PAYMENT.CAPTURE.COMPLETED`,
  `BILLING.SUBSCRIPTION.PAYMENT.COMPLETED`, `BILLING.SUBSCRIPTION.CANCELLED`

## Products

Each product has a **kind**: `physical`, `digital`, or `service`.

### Physical
- Stock managed via `track_inventory` + `stock` (or per-variant `stock`)
- Ships → contributes weight, requires shipping address

### Digital
- Stock is unlimited
- Add files in `ecom_digital_assets` (Media library or external URL)
- After payment, buyer receives a secure download link valid for `expires_days`
  with at most `max_downloads` hits
- License keys can be auto-generated via the asset's `license_template`

### Service / Subscription
- `service` + `is_subscription = 0` → one-off service (no shipping, no stock)
- `service` + `is_subscription = 1` → recurring billing
  - `billing_period`: day / week / month / year
  - `billing_interval`: 1 = every period, 3 = every 3 periods, …
  - `trial_days`: optional free trial

### Variants

Use variants when you sell the same product in multiple flavours (size,
colour, length). Each variant overrides price, stock, weight, and SKU.
The product's `base_price` becomes the "starting from" anchor.

## Orders

Three independent statuses:

| Field                | Values                                                          |
|----------------------|-----------------------------------------------------------------|
| `status`             | pending / paid / fulfilled / shipped / delivered / cancelled / refunded / failed |
| `payment_status`     | unpaid / authorized / paid / refunded / partially_refunded / failed |
| `fulfillment_status` | unfulfilled / partial / fulfilled                               |

This split exists because real-world workflows mix them — paid + unfulfilled
(awaiting ship), unpaid + fulfilled (cash on delivery), etc.

### Refunds

`Admin → Orders → view` → **Issue refund**. The amount is checked against the
remaining refundable balance; the gateway is called via `PaymentGateway::refund`,
the refund row is recorded, and the order's payment status updates.

## Stripe

The bundled `StripeGateway` calls Stripe's REST API directly with `curl`
— no SDK dependency. If you'd rather use the official SDK, install
`composer require stripe/stripe-php` and override the gateway in your plugin
boot:

```php
GatewayRegistry::register('stripe', fn() => new MyStripeWithSdk());
```

Webhook signature verification uses the standard `t=<timestamp>,v1=<sig>`
header with HMAC-SHA256 against `STRIPE_WEBHOOK_SECRET`.

## PayPal

Uses Orders v2 + Subscriptions API. Subscription products require a Plan
created in PayPal's dashboard — pass the plan id via the `plan_id` option
when calling `createSubscription`.

Webhook verification uses PayPal's `verify-webhook-signature` endpoint with
the configured `PAYPAL_WEBHOOK_ID`.

## Subscriptions

Local mirror in `ecom_subscriptions` tracks:

- `status`: trialing / active / past_due / paused / cancelled / expired
- `current_period_start` / `current_period_end`
- `gateway_subscription_id` — opaque id from the gateway
- `cancel_at_period_end` — soft cancel; flips to `cancelled` after the period

Renewal events come in via webhooks. The webhook handler advances
`current_period_end`, status, and fires `ecom.subscription.renewed`.

## Digital delivery

When an order is paid:

1. `OrderService::fulfillPaidOrder()` runs.
2. For every `digital` line item, every matching asset gets a row in
   `ecom_downloads` with a fresh 32-byte token.
3. The buyer's email is sent with links like
   `/{lang}/shop/download/{token}`.

Each token tracks its own counter and expiry. Hitting the URL streams the
file (or 302-redirects to the asset's external URL) and increments the
counter. Expired or maxed-out tokens return 410 Gone.

## Discounts

Three kinds:

- `percent` — value is `15.00` for 15% (stored as basis points × 100)
- `fixed`   — flat amount in minor units
- `free_shipping` — sets shipping total to 0

Each code can have:

- `min_subtotal` — minimum cart total
- `usage_limit` — global cap
- `per_customer_limit`
- `starts_at` / `ends_at` — date window

## Tax & shipping

### Taxes

Per-region rules in `ecom_tax_rates`:

| Field         | Notes                                                |
|---------------|------------------------------------------------------|
| `country`     | ISO 3166-1 alpha-2                                   |
| `state`       | optional, NULL means "any state in country"          |
| `tax_class`   | matches `ecom_products.tax_class` (default `standard`) |
| `rate_bp`     | basis points × 10 000 (1500 = 15%)                   |
| `is_inclusive`| 1 if existing prices already contain tax             |

Falls back to `ecom.tax_rate` setting when no row matches.

### Shipping

Manual rates in `ecom_shipping_rates` are matched by:

- `country` (NULL = any)
- `min_subtotal` / `max_subtotal`
- `min_weight`   / `max_weight`

The cheapest matching rate is offered automatically; pass
`shipping_method` to `POST /api/v1/shop/checkout` to force a specific one.

---

[← Back to docs](index.md)
