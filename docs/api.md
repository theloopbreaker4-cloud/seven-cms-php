# REST API

[← Back to docs](index.md)

## Versioning

All new endpoints live under `/api/v1`. The legacy `/api/*` endpoints are
preserved for backwards compatibility but new clients should target v1.

## Authentication

### Login

```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "admin@example.com", "password": "secret" }
```

Response:

```json
{
  "access_token":  "<JWT, 15 min>",
  "refresh_token": "<plain hex, 30 days>",
  "expires_in":    900,
  "user": { "id": 1, "email": "admin@example.com", "firstName": "...", "role": "admin" }
}
```

### Refresh

```http
POST /api/v1/auth/refresh
Content-Type: application/json

{ "refresh_token": "..." }
```

Returns a *new* access + refresh pair. Refresh tokens **rotate** — the old
one is revoked on first use. Replays are detected and the entire chain is
invalidated.

### Logout

```http
POST /api/v1/auth/logout
Authorization: Bearer <access>
Content-Type: application/json

{ "refresh_token": "...", "all": false }
```

Pass `"all": true` to revoke every active session for the user.

### Authorized requests

```http
Authorization: Bearer <access_token>
```

`/api/v1/auth/me` returns the current user along with their permissions and
roles for client-side routing.

## REST endpoints

| Resource          | Endpoints                                                              |
|-------------------|------------------------------------------------------------------------|
| **Auth**          | `/auth/{login,refresh,logout,me}`                                      |
| **Content types** | `GET /content/types`                                                   |
| **Content**       | `GET /content/{slug}` · `GET /content/{slug}/{entrySlug}` · CRUD with auth |
| **Media**         | `GET /media` · `POST /media` (multipart) · `DELETE /media/{id}`        |
| **Shop catalog**  | `GET /shop/products`, `GET /shop/products/{slug}`, `GET /shop/categories` |
| **Cart**          | `GET /shop/cart` · `POST /shop/cart/items` · `PUT /shop/cart/items/{id}` · `DELETE /shop/cart/items/{id}` · `POST /shop/cart/discount` |
| **Checkout**      | `POST /shop/checkout`                                                  |
| **Orders**        | `GET /shop/orders/{number}` (buyer lookup)                             |
| **Subscriptions** | `GET /shop/subscriptions` · `POST /shop/subscriptions/{id}/cancel` (auth) |
| **Webhooks**      | `POST /shop/webhook/{stripe|paypal}`                                   |

## Pagination

List endpoints return:

```json
{
  "items":  [ ... ],
  "total":  152,
  "limit":  50,
  "offset": 0
}
```

Pass `?limit=` and `?offset=` query params.

## Rate limiting

Default: **120 requests per minute per IP per controller**. Exceeded requests
get `429 Too Many Requests` with `Retry-After: 60`. Override per-controller
by setting `protected int $rateLimitPerMinute = N;` on a subclass of
`ApiV1Controller`.

## CORS

`ApiController` sends:

```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

`OPTIONS` requests short-circuit with `204`.

---

[← Back to docs](index.md)
