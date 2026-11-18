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
 *   ]
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
    public static function match(array $segments): ?array
    {
        $lowered = array_map('strtolower', $segments);
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
