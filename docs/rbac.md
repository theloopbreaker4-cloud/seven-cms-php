# Roles, permissions, 2FA, audit log

[← Back to docs](index.md)

## Roles

Tables: `roles`, `permissions`, `role_permissions`, `user_roles`.

System roles seeded on install:

| Slug   | Name          | Notes                                                |
|--------|---------------|------------------------------------------------------|
| admin  | Administrator | Bypasses every check (legacy `users.role` honored too) |
| editor | Editor        | Manages all content; cannot manage users / settings  |
| author | Author        | Creates and edits own content                        |
| viewer | Viewer        | Read-only                                            |

You can add as many custom roles as you like at **Admin → Roles**.

## Permissions

A permission slug is `{module}.{action}`:

```
pages.view / pages.create / pages.update / pages.delete / pages.publish
blog.{view,create,update,delete,publish}
media.{view,upload,update,delete}
content.{view,create,update,delete,publish,types}
users.{view,create,update,delete}
settings.{view,update}
plugins.{view,install,toggle}
ecom.products.{view,create,update,delete}
ecom.orders.{view,manage,refund}
ecom.customers.{view,manage}
ecom.discounts.manage
ecom.subscriptions.{view,manage}
ecom.settings.update
ecom.reports.view
```

Plugins extend the catalog by inserting into `permissions` from their
migration or `onInstall` hook.

### Checking permissions

```php
if (Permission::can('content.update')) { /* current user */ }
if (Permission::can('content.update', $userId)) { /* explicit */ }

$perms = Permission::userPermissions($userId);     // ['content.view', …]
$roles = Permission::userRoles($userId);           // ['editor']

Permission::syncRoles($userId, ['editor', 'author']);  // replace assignments
```

The matrix at **Admin → Roles** is editable inline — toggle a checkbox to
add or remove a permission from a role. Admin role cannot be downgraded
because the bypass lives in code.

## 2FA

`Totp` (`lib/totp.class.php`) is RFC 6238 — Google Authenticator, 1Password,
Authy compatible. Storage:

| Column         | Notes                                          |
|----------------|------------------------------------------------|
| `user_totp.secret` | base32 string, 160-bit                     |
| `user_totp.enabled` | 0 / 1                                     |
| `user_totp.recovery_codes` | JSON array of bcrypt hashes, single-use |

Flow:

1. Admin opens `/admin/2fa`.
2. Server issues a fresh secret if missing, plus 8 recovery codes (shown once).
3. Admin scans the QR code with their authenticator app.
4. Admin types a code → server verifies → marks `enabled = 1`.
5. Disable: type a current code → row deleted.

## Activity log

Every mutation that goes through `Hooks::fire(after*)` or explicit calls to
`ActivityLog::log()` is captured in `activity_log`:

| Column        | Meaning                                  |
|---------------|------------------------------------------|
| user_id       | Actor (NULL when system / webhook)       |
| action        | `module.action` slug                     |
| entity_type   | Optional table name                      |
| entity_id     | Optional row id                          |
| description   | Human-readable summary                   |
| meta          | JSON (diff, ip, userAgent, …)            |
| ip            | Resolved client IP (CF / forwarded headers honored) |

View at **Admin → Activity log**. Filter by user, entity type, or action prefix.

---

[← Back to docs](index.md)
