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
        $row = DB::findOne('ecom_downloads', ' token = :t ', [':t' => $token]);
        if (!$row) return null;
        if (!empty($row['expires_at']) && strtotime((string)$row['expires_at']) < time()) return null;
        if (!empty($row['max_downloads']) && (int)$row['download_count'] >= (int)$row['max_downloads']) return null;

        DB::execute('UPDATE ecom_downloads SET download_count = download_count + 1 WHERE id = :id',
            [':id' => (int)$row['id']]);

        $asset = DB::findOne('ecom_digital_assets', ' id = :a ', [':a' => (int)$row['asset_id']]);
        return $asset ? array_merge($asset, ['license_key' => $row['license_key']]) : null;
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
