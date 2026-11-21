<?php

/**
 * @package    SevenCMS
 * @author     theloopbreaker4-cloud <https://github.com/theloopbreaker4-cloud>
 * @license    ISC
 */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Serving Seven CMS under a sub-path, e.g. https://example.com/cms/ next to another app.
 *
 * Templates, controllers and front-end code are written with root-absolute URLs ("/en/admin",
 * "/uploads/a.png"). Instead of threading a prefix through all of them, the prefix is applied at
 * the edges of the request:
 *
 *   in   the prefix is stripped from REQUEST_URI before routing (a proxy may also strip it);
 *   out  Location headers, URL attributes and URL literals in inline scripts of HTML responses,
 *        and app paths in JSON responses get the prefix; cookies are scoped to it (cookiePath());
 *   JS   bundles read it from <meta name="seven-base"> (src/shared/base.js), and the head
 *        script from headTags() prefixes same-origin fetch()/XHR calls.
 *
 * Configure with BASE_PATH=/cms, or a BASE_URL that has a path (https://example.com/cms).
 * Empty (the default) = served at the domain root, and nothing here does anything.
 */
final class BasePath
{
    private static string $path = '';

    /** First path segments the app itself serves; used to spot app URLs inside scripts/JSON. */
    private const APP_SEGMENTS = ['api', 'uploads', 'admin', 'site', 'setup', 'auth', 'favicon.svg', 'favicon.ico', 'brand.svg'];

    public static function boot(): void
    {
        $raw = trim((string)Env::get('BASE_PATH', ''));
        if ($raw === '') {
            $raw = (string)(parse_url((string)Env::get('BASE_URL', ''), PHP_URL_PATH) ?? '');
        }
        $raw = '/' . trim($raw, '/');
        if ($raw === '/' || !preg_match('~^(/[A-Za-z0-9._-]+)+$~', $raw)) return;

        self::$path = $raw;
        self::stripRequestUri();
        if (PHP_SAPI === 'cli') return;

        header_register_callback([self::class, 'fixHeaders']);
        ob_start([self::class, 'filterOutput']);
    }

    /** "/cms", or "" when served at the root. */
    public static function get(): string
    {
        return self::$path;
    }

    /** Path for cookies: the app's own prefix, so they don't leak to neighbours on the domain. */
    public static function cookiePath(): string
    {
        return self::$path === '' ? '/' : self::$path . '/';
    }

    /** "/en/x" → "/cms/en/x". Full URLs, "//host" and already-prefixed paths are returned as is. */
    public static function url(string $url): string
    {
        if (self::$path === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) return $url;
        return self::isPrefixed($url) ? $url : self::$path . $url;
    }

    /** <meta> + fetch/XHR shim for layouts' <head>; empty when served at the root. */
    public static function headTags(string $nonce = ''): string
    {
        if (self::$path === '') return '';
        $p = htmlspecialchars(self::$path, ENT_QUOTES, 'UTF-8');
        $n = $nonce !== '' ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8') . '"' : '';
        $js = json_encode(self::$path, JSON_UNESCAPED_SLASHES);
        return '<meta name="seven-base" content="' . $p . '">' . "\n"
            . '<script' . $n . '>(function(b){'
            . 'function f(u){return typeof u==="string"&&u.charAt(0)==="/"&&u.charAt(1)!=="/"'
            . '&&u.indexOf("/cdn-cgi/")!==0' // the CDN's own endpoints belong to the domain
            . '&&u!==b&&u.indexOf(b+"/")!==0&&u.indexOf(b+"?")!==0?b+u:u}'
            . 'var nf=window.fetch;if(nf){window.fetch=function(i,o){return nf.call(this,f(i),o)}}'
            . 'var xo=XMLHttpRequest.prototype.open;XMLHttpRequest.prototype.open=function(m,u){'
            . 'arguments[1]=f(u);return xo.apply(this,arguments)};window.__SEVEN_BASE__=b;'
            . '})(' . $js . ');</script>';
    }

    // ── internals ─────────────────────────────────────────────────────────────

    private static function isPrefixed(string $url): bool
    {
        $p = self::$path;
        return $url === $p || str_starts_with($url, $p . '/') || str_starts_with($url, $p . '?') || str_starts_with($url, $p . '#');
    }

    private static function stripRequestUri(): void
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        if (!self::isPrefixed($uri)) return; // the proxy already stripped it
        $rest = substr($uri, strlen(self::$path));
        $_SERVER['REQUEST_URI'] = ($rest === '' || $rest[0] !== '/') ? '/' . $rest : $rest;
    }

    /** Runs right before headers go out: prefix same-site redirects. */
    public static function fixHeaders(): void
    {
        foreach (headers_list() as $h) {
            if (stripos($h, 'Location:') !== 0) continue;
            $loc = trim(substr($h, 9));
            $new = self::fixLocation($loc);
            if ($new === $loc) continue;
            $code = http_response_code();
            header('Location: ' . $new, true, ($code >= 300 && $code < 400) ? $code : 302);
        }
    }

    private static function fixLocation(string $loc): string
    {
        if (str_starts_with($loc, '/')) return self::url($loc);
        $u = parse_url($loc);
        $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        if (empty($u['host'])) return $loc;
        $locHost = strtolower($u['host'] . (isset($u['port']) ? ':' . $u['port'] : ''));
        if ($locHost !== $host) return $loc;
        $path = $u['path'] ?? '/';
        if (self::isPrefixed($path)) return $loc;
        $pos = strpos($loc, $u['host']) + strlen($u['host']) + (isset($u['port']) ? strlen(':' . $u['port']) : 0);
        return substr($loc, 0, $pos) . self::$path . substr($loc, $pos);
    }

    /** Output buffer callback: rewrite HTML and JSON bodies, pass everything else through. */
    public static function filterOutput(string $buffer, int $phase): string
    {
        if ($buffer === '') return $buffer;
        $type = '';
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0) $type = strtolower($h);
        }
        if ($type === '' || str_contains($type, 'text/html')) return self::rewriteHtml($buffer);
        if (str_contains($type, 'json')) return self::rewriteJson($buffer);
        return $buffer;
    }

    public static function rewriteHtml(string $html): string
    {
        $p    = self::$path;
        $skip = preg_quote(ltrim($p, '/'), '~');

        // URL attributes whose value is a root-absolute path.
        $html = preg_replace(
            '~(\s(?:href|src|action|formaction|poster|data-[\w-]+)\s*=\s*)(["\'])/(?!/|' . $skip . '(?:[/"\'?#]))~i',
            '$1$2' . $p . '/',
            $html
        ) ?? $html;

        // srcset="a.png 1x, /b.png 2x"
        $html = preg_replace_callback('~(\ssrcset\s*=\s*)(["\'])(.*?)\2~is', function ($m) {
            $parts = array_map(function ($c) {
                $c = trim($c);
                return self::url($c);
            }, explode(',', $m[3]));
            return $m[1] . $m[2] . implode(', ', $parts) . $m[2];
        }, $html) ?? $html;

        // CSS url(/…) in inline styles and <style> blocks.
        $html = preg_replace(
            '~url\(\s*(["\']?)/(?!/|' . $skip . '[/"\'?#)])~i',
            'url($1' . $p . '/',
            $html
        ) ?? $html;

        // URL literals inside inline <script> blocks and on*="" handlers.
        $html = preg_replace_callback(
            '~(<script\b(?![^>]*\bsrc\s*=)[^>]*>)(.*?)(</script>)~is',
            fn($m) => $m[1] . self::rewriteScript($m[2]) . $m[3],
            $html
        ) ?? $html;
        $html = preg_replace_callback(
            '~(\son[a-z]+\s*=\s*)(["\'])(.*?)\2~is',
            fn($m) => $m[1] . $m[2] . self::rewriteScript($m[3]) . $m[2],
            $html
        ) ?? $html;

        return $html;
    }

    /** String literals in JS that start with an app path: '/en/…', `/${lang}/…`, "\/uploads\/…". */
    public static function rewriteScript(string $js): string
    {
        $seg = self::segmentPattern();
        return preg_replace_callback(
            '~(["\'`])(\\\\?/)(?=(?:\$\{|(?:' . $seg . ')(?:\\\\?/|["\'`?#])))~',
            fn($m) => $m[1] . ($m[2] === '/' ? self::$path . '/' : str_replace('/', '\/', self::$path) . '\/'),
            $js
        ) ?? $js;
    }

    /** JSON string values that are app paths ("/uploads/…", "/en/…") get the prefix. */
    public static function rewriteJson(string $json): string
    {
        $seg = self::segmentPattern();
        return preg_replace_callback(
            '~"(\\\\?/)(?=(?:' . $seg . ')(?:\\\\?/|"))~',
            fn($m) => '"' . ($m[1] === '/' ? self::$path . '/' : str_replace('/', '\/', self::$path) . '\/'),
            $json
        ) ?? $json;
    }

    private static function segmentPattern(): string
    {
        $langs = [];
        try {
            $langs = (array)(Seven::app()->config['languages'] ?? []);
        } catch (Throwable) {
        }
        $langs = array_merge($langs, ['en', 'ru', 'ka', 'uk', 'az', 'hy']);
        $all = array_unique(array_merge($langs, self::APP_SEGMENTS));
        return implode('|', array_map(fn($s) => preg_quote((string)$s, '~'), $all));
    }
}
