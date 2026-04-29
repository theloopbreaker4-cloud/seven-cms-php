# Multi-site

[← Back to docs](index.md)

Run many sites from one SevenCMS install. Each site has its own host names,
theme, default locale, and settings; content (pages, posts, products,
content entries) can be scoped per site or shared across all of them.

## Concept

- A **site** is a row in the `sites` table.
- A site has many **host names** (apex, www, tenant subdomains) in `site_hosts`.
- `SiteResolver::current()` picks the active site for every request based on
  `HTTP_HOST`. The default install has one site flagged `is_default = 1`.
- Existing content tables got a nullable `site_id` column. `NULL` means
  *shared with every site*; a value means *belongs to that site only*.

## Setup

```bash
php bin/sev migrate
```

The migration creates `sites` + `site_hosts`, adds `site_id` columns, seeds
the default site (id=1).

In admin: **Sites → New site**, give it a name + slug, save. Open the
editor, add host names (e.g. `shop.example.com`, `www.shop.example.com`).
The first time a request arrives on that host, SevenCMS will resolve it
to the new site.

## Per-site settings

Each site has a JSON `settings` column for arbitrary options. Read in code:

```php
$brand = SiteResolver::setting('brand_name', 'SevenCMS');
$theme = SiteResolver::current()['theme'];
```

Plugins are encouraged to namespace their per-site settings:

```php
$paymentMode = SiteResolver::setting('ecom.stripe_mode', 'test');
```

## Scoping content

When you create content in admin, the controller can stamp the row with
the active site:

```php
DB::execute(
    'INSERT INTO page (site_id, slug, title, content, is_published) VALUES (:s, :slug, :t, :c, 1)',
    [':s' => SiteResolver::currentId(), ':slug' => $slug, ':t' => $title, ':c' => $content]
);
```

Public queries that should respect site scoping:

```php
$pages = DB::getAll(
    'SELECT * FROM page
      WHERE is_published = 1
        AND (site_id IS NULL OR site_id = :s)
      ORDER BY id DESC',
    [':s' => SiteResolver::currentId()]
);
```

The `site_id IS NULL` clause keeps "shared" content visible to every site,
which is what you want for things like `/legal/terms`.

## API

The REST API auto-applies the same scoping when controllers ask for it:
the storefront `/api/v1/shop/products` filters by current site, the
content API `/api/v1/content/{type}` does the same. Cross-site reads are
opt-in via `?site=all` (admin only) — implement on a per-controller basis.

## Themes per site

Set `sites.theme` to the folder name under `themes/`. The view layer
prepends that folder to the lookup path so each site can ship its own
templates without affecting others.

```
themes/
├── default/
│   ├── layout.html
│   └── page.html
└── shop/
    ├── layout.html
    └── page.html
```

A blank `theme` falls back to `themes/default/`.

---

[← Back to docs](index.md)
