<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Named route registry + URL generator.
 *
 * Route definition array:
 *   [
 *     'controller' => 'blog',
 *     'action'     => 'show',
 *     'prefix'     => '',          // '' = site, 'admin', 'api'
 *     'params'     => [':slug'],   // optional param placeholders
 *     'middleware' => [],          // MiddlewareInterface class names to run
 *     'path'       => 'api/v1/shop/cart/items/{id}', // optional explicit REST path
 *     'method'     => 'PUT',       // optional, with 'path': GET/POST/PUT/DELETE or a list
 *   ]
 *
 * Without 'path' the route name is the path ("admin.ecom.products" ↔ /admin/ecom/products).
 *
 * Usage:
 *   Route::add('blog.show', ['controller' => 'blog', 'action' => 'show', 'params' => [':slug']]);
 *   Route::url('blog.show', ['slug' => 'hello-world']);
 *   // → /en/blog/show/hello-world
 *
 *   Route::middleware('blog.show', [AuthMiddleware::class]);
 *   Route::middlewareFor('blog.show');  // returns configured middleware instances
 */
class Route
{
    /** @var array<string, array> */
    private static array $routes = [];

    public static function add(string $name, array $definition): void
    {
        self::$routes[$name] = $definition;
    }

    public static function get(string $name): ?array
    {
        return self::$routes[$name] ?? null;
    }

    public static function all(): array
    {
        return self::$routes;
    }

    public static function has(string $name): bool
    {
        return isset(self::$routes[$name]);
    }

    /**
     * Generate a URL for a named route.
     * $params replaces :param placeholders, extra values appended as path segments.
     */
    public static function url(string $name, array $params = []): string
    {
        $def = self::$routes[$name] ?? null;
        if (!$def) return '#';

        $prefix     = $def['prefix'] ?? '';
        $controller = $def['controller'] ?? '';
        $action     = $def['action'] ?? 'index';
        $base       = Seven::app()->config['baseUrl'];

        if (!empty($def['path'])) {
            $path = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$params) {
                $v = $params[$m[1]] ?? '';
                unset($params[$m[1]]);
                return rawurlencode((string)$v);
            }, trim((string)$def['path'], '/'));
            if ($prefix === '') $path = Seven::app()->router->getLanguage() . '/' . $path;
            return $base . '/' . $path . ($params ? '?' . http_build_query($params) : '');
        }

        if ($prefix) {
            $path = $base . '/' . $prefix . '/' . $controller . '/' . $action;
        } else {
            $lang = Seven::app()->router->getLanguage();
            $path = $base . '/' . $lang . '/' . $controller . '/' . $action;
        }

        // Replace :placeholders from $params
        $placeholders = $def['params'] ?? [];
        foreach ($placeholders as $placeholder) {
            $key = ltrim($placeholder, ':');
            if (isset($params[$key])) {
                $path .= '/' . rawurlencode((string)$params[$key]);
                unset($params[$key]);
            }
        }

        // Append any remaining params as query string
        if ($params) {
            $path .= '?' . http_build_query($params);
        }

        return $path;
    }

    /**
     * Match URL segments (language already stripped) against route names, where
     * a name's dots are its path: "admin.ecom.products.create" ↔ admin/ecom/products/create.
     * The longest matching name wins; leftover segments become params.
     *
     * @return array{name:string, def:array, params:array}|null
     */
    public static function match(array $segments, ?string $method = null): ?array
    {
        $lowered = array_map('strtolower', $segments);

        $byPath = self::matchPath($segments, strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET')));
        if ($byPath) return $byPath;

        $best    = null;
        $bestLen = 0;

        foreach (self::$routes as $name => $def) {
            $parts = explode('.', strtolower($name));
            $len   = count($parts);
            if ($len <= $bestLen || $len > count($lowered)) continue;
            if (array_slice($lowered, 0, $len) !== $parts) continue;
            if (!self::nameMatchesPrefix($parts, (string)($def['prefix'] ?? ''))) continue;
            $best    = $name;
            $bestLen = $len;
        }

        if ($best === null) return null;

        // A controller-level index route ("admin.blog") must not swallow
        // /admin/blog/edit/5 — leave those to /{controller}/{action}/{params}.
        $leftover = count($segments) - $bestLen;
        if ($leftover > 0 && (self::$routes[$best]['action'] ?? 'index') === 'index') return null;

        return [
            'name'   => $best,
            'def'    => self::$routes[$best],
            'params' => array_slice($segments, $bestLen),
        ];
    }

    /**
     * Routes with an explicit 'path' (e.g. 'api/v1/shop/cart/items/{id}') and optional
     * 'method' ('GET', 'POST', … or a list). Literal segments compare case-insensitively,
     * {placeholders} capture in order and become positional params ($params[0], …).
     * When several match, the one with the most literal segments wins, so
     * 'shop/cart/items' beats 'shop/cart/{x}'.
     */
    private static function matchPath(array $segments, string $method): ?array
    {
        if ($method === 'HEAD') $method = 'GET';
        $best = null; $bestScore = -1;
        foreach (self::$routes as $name => $def) {
            if (empty($def['path'])) continue;
            if (!empty($def['method'])) {
                $allowed = array_map('strtoupper', (array)$def['method']);
                if (!in_array($method, $allowed, true) && !($method === 'OPTIONS')) continue;
            }
            $parts = explode('/', trim((string)$def['path'], '/'));
            if (count($parts) !== count($segments)) continue;
            $params = []; $literal = 0; $ok = true;
            foreach ($parts as $i => $p) {
                if (preg_match('/^\{\w+\}$/', $p)) { $params[] = $segments[$i]; continue; }
                if (strcasecmp($p, $segments[$i]) !== 0) { $ok = false; break; }
                $literal++;
            }
            if ($ok && $literal > $bestScore) {
                $best = ['name' => $name, 'def' => $def, 'params' => $params];
                $bestScore = $literal;
            }
        }
        return $best;
    }

    /** A name like "api.v1.auth.login" only counts as a path when it starts with its own prefix. */
    private static function nameMatchesPrefix(array $parts, string $prefix): bool
    {
        if ($prefix === '') return !in_array($parts[0], ['admin', 'api'], true);
        $prefixParts = explode('/', strtolower($prefix));
        return array_slice($parts, 0, count($prefixParts)) === $prefixParts;
    }

    /**
     * Attach middleware class names to a route.
     * @param string[] $classNames
     */
    public static function middleware(string $name, array $classNames): void
    {
        if (!isset(self::$routes[$name])) return;
        self::$routes[$name]['middleware'] = $classNames;
    }

    /**
     * Return instantiated MiddlewareInterface objects for a named route.
     * @return MiddlewareInterface[]
     */
    public static function middlewareFor(string $name): array
    {
        $def = self::$routes[$name] ?? [];
        $instances = [];
        foreach ($def['middleware'] ?? [] as $class) {
            if (class_exists($class)) {
                $instances[] = new $class();
            }
        }
        return $instances;
    }
}

/**
 * Global helper for templates.
 *   echo route('blog.show', ['slug' => $post->slug]);
 */
function route(string $name, array $params = []): string
{
    return Route::url($name, $params);
}
