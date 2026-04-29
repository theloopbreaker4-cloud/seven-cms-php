# Storefront

[← Back to docs](index.md)

A minimal Vue 3 storefront sits on top of the Shop REST API. It loads Vue
from a CDN — no extra Vite setup is required to get a working catalogue.

| Path             | What                                              |
|------------------|---------------------------------------------------|
| `/{lang}/shop`            | Catalogue grid (`/api/v1/shop/products`)  |
| `/{lang}/shop/{slug}`     | Product detail page (server-rendered)     |
| `/{lang}/shop/cart`       | Cart with quantity / discount controls    |

When the Ecom plugin is **not** installed, `/shop` renders a friendly
"plugin missing" page instead of crashing.

## Currency picker

The header automatically shows a `<select>` of enabled currencies when
multi-currency is on. Picking a currency:

1. Sets `?currency=XXX` (and the `seven_currency` cookie).
2. Reloads the catalogue with prices converted via
   `CurrencyService::convert()` server-side.

## Templates

The pages are PHP views with `<script type="module">` blocks. Source:

```
app/views/site/shop/
├── disabled.html      shown when ecom plugin missing
├── index.html         Vue 3 catalogue
├── product.html       server-rendered, with "Add to cart" → JS
└── cart.html          Vue 3 cart
```

To replace with a fully built Vue app, point `vite.config` at a new entry
under `src/shop/main.ts` and swap the CDN imports for `Vite::tags(...)`.

## Routes

```php
Route::add('shop',         ['controller' => 'shop', 'action' => 'index']);
Route::add('shop.cart',    ['controller' => 'shop', 'action' => 'cart']);
Route::add('shop.product', ['controller' => 'shop', 'action' => 'product']);
```

## API endpoints used

| Method | URL                                       | Used by               |
|--------|-------------------------------------------|-----------------------|
| GET    | `/api/v1/shop/currencies`                 | Currency dropdown     |
| GET    | `/api/v1/shop/categories`                 | Category filter       |
| GET    | `/api/v1/shop/products`                   | Catalogue grid        |
| GET    | `/api/v1/shop/cart`                       | Cart load             |
| POST   | `/api/v1/shop/cart/items`                 | Add to cart           |
| PUT    | `/api/v1/shop/cart/items/:id`             | Quantity update       |
| DELETE | `/api/v1/shop/cart/items/:id`             | Remove                |
| POST   | `/api/v1/shop/cart/discount`              | Apply discount code   |
| POST   | `/api/v1/shop/checkout`                   | Begin checkout        |

See [REST API](#mdlink#api.md#) for full payload shapes.

---

[← Back to docs](#mdlink#index.md#)
