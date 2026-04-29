# Custom content types

[← Back to docs](index.md)

## Overview

A **content type** is an entity you define at runtime — recipe, event, FAQ,
landing-page section. The `Content` plugin stores type definitions in
`content_types`, field schemas in `content_fields`, and the actual rows in
`content_entries.data` (JSON).

Open **Admin → Content Types**. Click *New type*, give it a name + slug, then
add fields. Entries appear immediately under
`/admin/content/entries/{slug}`.

## Field types

| Type          | UI                              | Stored as                        |
|---------------|---------------------------------|----------------------------------|
| `text`        | Single-line input               | string                           |
| `richtext`    | Textarea / WYSIWYG              | HTML string                      |
| `number`      | Number input                    | int or float                     |
| `boolean`     | Checkbox                        | true / false                     |
| `image`       | Media picker                    | media id                         |
| `media`       | Media picker (any kind)         | media id                         |
| `select`      | Dropdown                        | string                           |
| `multiselect` | Multi-dropdown                  | array of strings                 |
| `date`        | Date picker                     | `YYYY-MM-DD` string              |
| `datetime`    | Datetime picker                 | `YYYY-MM-DD HH:ii` string        |
| `relation`    | Picker over another type        | array of entry ids               |
| `repeater`    | Nested rows                     | array of objects                 |
| `json`        | Raw JSON textarea               | array                            |

Each field has a `settings` JSON for per-type options:

```jsonc
// select / multiselect
{ "options": [ {"value":"red","label":"Red"}, {"value":"blue","label":"Blue"} ] }

// number
{ "min": 0, "max": 100, "step": 0.1 }

// relation
{ "to": "recipe", "cardinality": "many" }

// repeater
{ "fields": [ {"key":"q","label":"Question","field_type":"text"},
              {"key":"a","label":"Answer","field_type":"richtext"} ] }
```

## Relationships

Use the `relation` field type. The pivot lives in `content_relations`:

```
from_entry_id, to_entry_id, relation_key, sort_order
```

Read related entries:

```php
$related = ContentRelationStore::resolve($entry->id, 'tags', 50);
```

Reverse lookups (who points to me?):

```php
$citing = ContentRelationStore::reverse($entry->id, 'tags');
```

## Revisions

Types with `enable_revisions = 1` snapshot every save into the `revisions`
table.

```php
$history = Revisions::list('content_entries', $entry->id, 20);
$payload = Revisions::restore($revisionId);   // returns the snapshotted data
ContentEntry::persist($type, $payload, $entry->id);
```

The admin entry editor exposes a *History* button that opens the list.

## Preview mode

To share a draft, click **Preview link** in the entry editor. It returns:

```
/{lang}/preview/content/{type}/{id}?token=...
```

The token is a stateless HMAC signature (`PreviewToken::create`) valid for 1
hour by default. Anyone with the link can view the draft until it expires —
no DB write, no row to revoke. Re-issue a new token if you suspect leakage.

In the public REST API, drafts return `404` *unless* the request carries
`?token=...`:

```
GET /api/v1/content/recipe/secret-pizza?token=...
```

---

[← Back to docs](index.md)
