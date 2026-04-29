<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Vite manifest helper
 *
 * In dev mode (VITE_DEV=true in .env): injects Vite HMR client + entry as module scripts.
 * In prod mode: reads public/.vite/manifest.json and injects hashed CSS + JS.
 *
 * Usage in layout:
 *   echo Vite::tags('src/site/main.js')
 *   echo Vite::tags('src/admin/main.js')
 */
class Vite
{
    private const DEV_URL = 'http://localhost:5173';

    private static ?array $manifest = null;

    public static function tags(string $entry): string
    {
        if (Env::get('VITE_DEV', 'false') === 'true') {
            return self::devTags($entry);
        }
        return self::prodTags($entry);
    }

    // -----------------------------------------------------------------------

    private static function devTags(string $entry): string
    {
        $base = self::DEV_URL;
        return implode("\n", [
            '<script type="module" src="' . $base . '/@vite/client"></script>',
            '<script type="module" src="' . $base . '/' . $entry . '"></script>',
        ]);
    }

    private static function prodTags(string $entry): string
    {
        $manifest = self::loadManifest();
        if (!$manifest || !isset($manifest[$entry])) {
            Logger::channel('app')->warn('Vite manifest entry not found', ['entry' => $entry]);
            return '<!-- Vite: entry not found: ' . htmlspecialchars($entry) . ' -->';
        }

        $chunk  = $manifest[$entry];
        $output = [];

        // CSS files for this entry
        foreach ($chunk['css'] ?? [] as $css) {
            $output[] = '<link rel="stylesheet" href="/' . $css . '" />';
        }

        // Main JS
        $output[] = '<script type="module" src="/' . $chunk['file'] . '"></script>';

        // Preload imported chunks
        foreach ($chunk['imports'] ?? [] as $importKey) {
            if (isset($manifest[$importKey]['file'])) {
                $output[] = '<link rel="modulepreload" href="/' . $manifest[$importKey]['file'] . '" />';
            }
        }

        return implode("\n", $output);
    }

    private static function manifestPath(): string
    {
        return ROOT_DIR . DS . 'public' . DS . '.vite' . DS . 'manifest.json';
    }

    private static function loadManifest(): ?array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = self::manifestPath();

        if (!file_exists($path)) {
            Logger::channel('app')->warn('Vite manifest.json not found — run npm run build');
            return null;
        }

        $json = file_get_contents($path);
        self::$manifest = json_decode($json, true) ?? [];
        return self::$manifest;
    }
}
