# Media library

[← Back to docs](index.md)

## Upload

`Admin → Media`. Drag and drop files onto the dropzone, or click *browse*.
Multi-upload is supported; files queue and upload sequentially with a
progress bar.

Defaults:

- Max size: **25 MB**
- Mime sniffing: `finfo` reads the actual file content (browser-supplied
  `$_FILES[*]['type']` is ignored)
- Allowed kinds: `image/*`, `video/*`, `audio/*`, `application/pdf`,
  `image/svg+xml`

## Folders

Create folders in the sidebar (the "+ New" link). Folders are tree-shaped
via `parent_id`; the table also denormalizes the full `path` so we can
serve uploads from `/uploads/{folder/path}/{filename}`.

Empty-folder rule: deleting a folder with media or sub-folders inside
returns `409 Conflict` with the message *Folder not empty*.

## Variants

When `gd` (or `imagick`) is present, image uploads (jpeg / png) get
auto-generated variants:

| Label  | Width      |
|--------|------------|
| thumb  | 320 px     |
| medium | 768 px     |
| large  | 1600 px    |
| webp   | full size  |

Variants live next to the original under `variants/{uuid}-{label}.webp`
and are referenced from the `media.variants` JSON column. The frontend can
build a `<picture>` element by reading them.

Disabling: drop or rename `MediaProcessor` — the upload code falls back
to "no variants" silently.

## Storage drivers

Implementation lives behind `StorageDriver` (`lib/storage.interface.php`):

```
LocalStorage   public/uploads/...                    (default)
S3Storage      AWS S3 / Cloudflare R2 / MinIO        (composer require aws/aws-sdk-php)
```

Swap globally:

```ini
# .env
STORAGE_DRIVER=s3
S3_KEY=...
S3_SECRET=...
S3_REGION=auto
S3_BUCKET=my-cdn
S3_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com
S3_PUBLIC_URL=https://cdn.example.com
```

Or per-plugin by binding into the container:

```php
Container::singleton('storage.cdn', fn() => new S3Storage(
    bucket:   'big-files',
    region:   'auto',
    endpoint: 'https://...',
    publicUrl:'https://cdn.example.com',
    key:      Env::get('S3_KEY'),
    secret:   Env::get('S3_SECRET'),
));
```

---

[← Back to docs](index.md)
