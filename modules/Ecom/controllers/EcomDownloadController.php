<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * EcomDownloadController — serves a digital asset by its grant token.
 *
 *   GET /:lang/shop/download/:token
 *
 * Streams either a local Media file or 302-redirects to the asset's external URL.
 * Increments download counter on each successful hit; refuses when the token has
 * expired or the per-grant download limit is exhausted.
 */
class EcomDownloadController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function serve($req, $res, $params)
    {
        $token = (string)($params[0] ?? '');
        if ($token === '') $res->errorCode(404);

        $asset = DigitalDelivery::consume($token);
        if (!$asset) $res->errorCode(410); // Gone

        // External URL — redirect (e.g. cloud bucket, CDN).
        if (!empty($asset['external_url'])) {
            header('Location: ' . $asset['external_url']);
            exit;
        }

        // Local Media row → stream the file.
        if (!empty($asset['media_id'])) {
            $media = DB::findOne('media', ' id = :id ', [':id' => (int)$asset['media_id']]);
            if (!$media || empty($media['path'])) $res->errorCode(404);
            $abs = ROOT_DIR . '/public' . $media['path'];
            if (!is_file($abs)) $res->errorCode(404);

            header('Content-Type: ' . ($media['mime_type'] ?? 'application/octet-stream'));
            header('Content-Length: ' . filesize($abs));
            $name = $asset['license_key']
                ? ($asset['name'] . '_' . $asset['license_key'])
                : ($media['original_name'] ?? 'download');
            header('Content-Disposition: attachment; filename="' . addslashes((string)$name) . '"');
            header('X-Accel-Buffering: no');
            readfile($abs);
            exit;
        }

        $res->errorCode(404);
    }
}
