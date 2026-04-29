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
