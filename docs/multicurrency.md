# Multi-currency

[← Back to docs](index.md)

The Ecom plugin can display prices in multiple currencies. Storage is
unchanged — every product still keeps its `price` in **minor units of the
shop's base currency**. Conversion happens at display and checkout time
using the latest `ecom_fx_rates` row.

## Quick start

1. Open `/admin/ecom/currencies`.
2. Enable the currencies you want to expose (USD is base by default).
3. Tick **Enable multi-currency on the storefront**.
4. Pick a provider:
   - **Manual** — you enter rates by hand and click Save.
   - **exchangerate.host** — free public source, refreshed daily by the
     `ecom.fx.refresh` cron job.
5. Click **Refresh now** if you want immediate rates without waiting for the
   cron tick.

That's it. The storefront and the `/api/v1/shop/currencies` endpoint will
start exposing the picker.

## Active currency resolution

When multi-currency is on, `CurrencyService::active()` checks in order:

1. `?currency=XXX` query string (sets a 30-day cookie when valid).
2. `seven_currency` cookie.
3. The customer's `preferred_currency` (when authenticated).
4. The shop's base currency.

## Conversion

```php
$priceInBase = 12345;   // $123.45 in the base currency, USD
$pretty = CurrencyService::display($priceInBase);    // formatted in active currency

// Or convert manually:
$converted = CurrencyService::convert($priceInBase, 'USD', 'EUR');   // EUR minor units
```

Rate lookup tries direct → via base → inverse, picking the most recent
`fetched_at`. When no usable rate exists, `convert()` returns the input
**without** conversion (graceful fallback rather than zeroing the price).

## Custom FX provider

```php
class MyFxProvider
{
    /** @return array<string,float>  ['EUR' => 0.93, 'GBP' => 0.79] */
    public function fetch(string $base, array $quotes): array
    {
        // Call your aggregator…
        return ['EUR' => 0.93, 'GBP' => 0.79];
    }
}

Container::singleton('ecom.fx.provider', fn() => new MyFxProvider());
```

The `ecom.fx.refresh` cron prefers the container service when present,
falling back to the public `exchangerate.host` endpoint.

## Schema

```
ecom_currencies
├── code         CHAR(3) PK   ISO 4217
├── is_enabled
├── is_base      exactly one row should be true
├── label
└── created_at

ecom_fx_rates
├── id
├── base_code, quote_code
├── rate         DECIMAL(18,8)
├── source       'manual' | 'exchangerate.host' | …
└── fetched_at

ecom_customers.preferred_currency CHAR(3) NULL
```

## Caveats

- **Order totals** are stored in the currency of the order at checkout time,
  not in base. So historical revenue stays consistent if rates drift later.
- **Refunds** through Stripe / PayPal use the order's currency, not the
  active one — gateways enforce this themselves.
- This feature is **off by default** — single-currency shops keep working
  exactly as before.

---

[← Back to docs](#mdlink#index.md#)
