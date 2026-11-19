<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * DigitalDelivery — issues secure download tokens for digital products.
 *
 *   DigitalDelivery::grant($order, $product, $variantId);
 *   $url = DigitalDelivery::downloadUrl($downloadToken);   // public link with token
 *
 * Each row in `ecom_downloads` holds:
 *   - token (random 32 bytes hex) — the only thing exposed in the link
 *   - max_downloads / expires_at  — copied from the asset's settings
 *   - license_key                 — generated when the asset has a license_template
 *
 * The actual file streaming happens in EcomDownloadController::serve().
 */
class DigitalDelivery
{
    public static function grant(Order $order, Product $product, ?int $variantId = null): array
    {
        $assets = $product->digitalAssets();
        $created = [];
        foreach ($assets as $asset) {
            if (!empty($asset['variant_id']) && (int)$asset['variant_id'] !== ($variantId ?? 0)) continue;

            $token   = bin2hex(random_bytes(32));
            $expires = !empty($asset['expires_days'])
                ? (new DateTimeImmutable('+' . (int)$asset['expires_days'] . ' days'))->format('Y-m-d H:i:s')
                : null;
            $license = !empty($asset['license_template']) ? self::renderLicense((string)$asset['license_template'], $order) : null;

            DB::execute(
                'INSERT INTO ecom_downloads
                    (order_id, customer_id, product_id, variant_id, asset_id, token, license_key, max_downloads, expires_at)
                 VALUES (:o, :c, :p, :v, :a, :t, :lk, :md, :ex)',
                [
                    ':o'  => $order->id,
                    ':c'  => $order->customerId,
                    ':p'  => $product->id,
                    ':v'  => $variantId,
                    ':a'  => (int)$asset['id'],
                    ':t'  => $token,
                    ':lk' => $license,
                    ':md' => $asset['max_downloads'] !== null ? (int)$asset['max_downloads'] : null,
                    ':ex' => $expires,
                ]
            );
            $created[] = $token;
        }

        if ($created) {
            Event::dispatch('ecom.download.granted', ['order' => $order, 'product' => $product, 'tokens' => $created]);
            if (class_exists('EcomMail')) EcomMail::digitalDelivered($order, $product, $created);
        }
        return $created;
    }

    /** Validate a token, increment counter, return the underlying asset or null. */
    public static function consume(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $row = DB::findOne('ecom_downloads', ' token = :t ', [':t' => $token]);
        if (!$row) return null;
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) return null;

        // Check-and-increment in one statement so parallel requests can't exceed the limit.
        $claimed = DB::exec(
            'UPDATE ecom_downloads SET download_count = download_count + 1
              WHERE id = :id AND (max_downloads IS NULL OR download_count < max_downloads)',
            [':id' => (int)$row['id']]
        );
        if ((int)$claimed !== 1) return null;

        $asset = DB::findOne('ecom_digital_assets', ' id = :a ', [':a' => (int)$row['asset_id']]);
        return $asset ? array_merge($asset->export(), ['license_key' => $row['license_key']]) : null;
    }

    private const ALLOWED_EXT = [
        'zip', '7z', 'rar', 'tar', 'gz', 'pdf', 'epub', 'mobi', 'txt', 'md', 'csv', 'json',
        'mp3', 'm4a', 'wav', 'flac', 'ogg', 'mp4', 'mov', 'webm',
        'png', 'jpg', 'jpeg', 'webp', 'gif', 'psd', 'ai', 'eps', 'svg', 'ttf', 'otf', 'woff2',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'sketch', 'fig',
    ];
    private const MAX_BYTES = 200 * 1024 * 1024;

    public static function privateDir(): string
    {
        return ROOT_DIR . '/storage/private/digital';
    }

    /**
     * Save an uploaded file outside the web root and attach it to a product.
     * Returns an error message, or null on success.
     */
    public static function store(int $productId, array $file, array $meta): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return 'Upload failed or no file selected.';
        $max = Demo::enabled() ? Demo::MAX_UPLOAD : self::MAX_BYTES;
        if ((int)$file['size'] > $max) return 'File is too large.';

        $original = basename((string)($file['name'] ?? 'file'));
        $ext      = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) return "File type .{$ext} is not allowed.";

        $dir = self::privateDir();
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) return 'Could not save the file.';

        $limit = fn($v) => ($v === null || $v === '' || (int)$v < 1) ? null : (int)$v;
        DB::execute(
            'INSERT INTO ecom_digital_assets
                (product_id, name, storage_path, original_name, size_bytes, max_downloads, expires_days)
             VALUES (:p, :n, :s, :o, :b, :m, :e)',
            [
                ':p' => $productId,
                ':n' => trim(strip_tags($meta['name'] ?? '')) ?: $original,
                ':s' => $stored,
                ':o' => $original,
                ':b' => (int)$file['size'],
                ':m' => $limit($meta['max_downloads'] ?? null),
                ':e' => $limit($meta['expires_days'] ?? null),
            ]
        );
        return null;
    }

    /** Absolute path for a stored file name, or null if it escapes the private dir. */
    public static function filePath(string $stored): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}\.[a-z0-9]{1,10}$/', $stored)) return null;
        $abs = self::privateDir() . '/' . $stored;
        return is_file($abs) ? $abs : null;
    }

    public static function deleteFile(string $stored): void
    {
        $abs = self::filePath($stored);
        if ($abs) unlink($abs);
    }

    public static function downloadUrl(string $token, string $lang = 'en'): string
    {
        $base = (string)Env::get('BASE_URL', '');
        return rtrim($base, '/') . '/' . $lang . '/shop/download/' . $token;
    }

    private static function renderLicense(string $template, Order $order): string
    {
        $vars = [
            '{order}'    => $order->number,
            '{email}'    => $order->email,
            '{license}'  => strtoupper(bin2hex(random_bytes(8))),
            '{date}'     => date('Y-m-d'),
        ];
        return strtr($template, $vars);
    }
}
