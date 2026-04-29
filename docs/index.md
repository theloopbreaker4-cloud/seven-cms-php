# SevenCMS Documentation

Welcome. Pick a topic from the categories below — the same tree is mirrored in
the admin panel under **Help**, so you can read the docs while you work.

---

## 🚀 Getting Started
- [Installation & first run](getting-started.md#installation)
- [Configuration & .env](getting-started.md#configuration)
- [Five-minute tour](getting-started.md#five-minute-tour)
- [Upgrading from older versions](getting-started.md#upgrading)

## 🏗 Architecture
- [Overview](architecture.md#overview)
- [Service container (DI)](architecture.md#service-container)
- [Hooks & events](architecture.md#hooks)
- [Database migrations](architecture.md#migrations)
- [Storage abstraction](architecture.md#storage)
- [Composer integration](composer.md)

## 🧩 Plugins
- [Plugin lifecycle](plugins.md#lifecycle)
- [Writing a plugin (step-by-step)](plugins.md#writing-a-plugin)
- [plugin.json reference](plugins.md#plugin-json)
- [Hook reference](plugins.md#hook-reference)

## 📝 Content
- [Custom content types](content-types.md#overview)
- [Field types](content-types.md#field-types)
- [Relationships](content-types.md#relationships)
- [Revisions & history](content-types.md#revisions)
- [Preview mode (drafts)](content-types.md#preview-mode)

## 👥 Users & Access
- [Roles & permissions](rbac.md#roles)
- [Permissions catalog](rbac.md#permissions)
- [Two-factor auth (TOTP)](rbac.md#2fa)
- [Activity log](rbac.md#activity-log)

## 🖼 Media
- [Upload & folders](media.md#upload)
- [Image variants & WebP](media.md#variants)
- [Storage drivers (Local / S3)](media.md#drivers)

## 🌐 APIs
- [REST API v1](api.md#rest)
- [JWT + refresh tokens](api.md#jwt)
- [Rate limiting](api.md#rate-limiting)
- [GraphQL endpoint](graphql.md)

## 🛒 E-commerce
- [Setup](ecom.md#setup)
- [Products & variants](ecom.md#products)
- [Orders & fulfillment](ecom.md#orders)
- [Payments — Stripe](ecom.md#stripe)
- [Payments — PayPal](ecom.md#paypal)
- [Subscriptions](ecom.md#subscriptions)
- [Digital downloads](ecom.md#digital-delivery)
- [Discounts](ecom.md#discounts)
- [Taxes & shipping](ecom.md#tax-shipping)

## 🌍 Multi-site
- [Setup multiple domains](multisite.md#setup)
- [Per-site settings](multisite.md#settings)
- [Content scoping](multisite.md#scoping)

## 🧱 Page builder
- [Concept](pagebuilder.md#concept)
- [Built-in blocks](pagebuilder.md#blocks)
- [Custom blocks](pagebuilder.md#custom-blocks)
- [Embedding into pages](pagebuilder.md#embedding)

## ⚙ CLI tool
- [`bin/sev` reference](cli.md)

## 🛡 Security & Forms
- [Security: CSRF, rate limiting, file uploads, headers](security.md)
- [Form helpers: validation, custom select, counter, pickers](forms.md)

## ⏱ Operations
- [Cron & scheduler](cron.md)
- [Mail queue](mail.md)
- [Notifications](notifications.md)
- [Admin calendar](calendar.md)
- [Multi-currency](multicurrency.md)
- [Storefront](storefront.md)
- [PHPUnit tests](testing.md)
