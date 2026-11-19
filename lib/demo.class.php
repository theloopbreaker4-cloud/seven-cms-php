<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Demo — public showcase mode (DEMO_MODE=true in .env).
 *
 * Visitors sign in with a shared admin account and may create/edit content,
 * but anything that could lock others out, leak secrets or touch the server
 * is read-only. Data is restored hourly by `php bin/sev demo:reset`.
 */
class Demo
{
    /** Admin controllers whose state-changing requests are refused. */
    private const LOCKED_ADMIN = [
        'user', 'role', 'setting', 'mail', 'plugin', 'cron', 'site', 'twofactor',
        'language', 'cache', 'ecomsettings', 'ecomcurrency',
    ];

    /** API controller → actions (or '*') refused in demo. */
    private const LOCKED_API = [
        'user'   => ['*'],
        'auth'   => ['register', 'forgot', 'reset'],
        'authv1' => ['logout'],
    ];

    public const MAX_UPLOAD = 2 * 1024 * 1024;

    public static function enabled(): bool
    {
        return filter_var(Env::get('DEMO_MODE', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function login(): string    { return (string)Env::get('DEMO_LOGIN', 'demo@seven.dev'); }
    public static function password(): string { return (string)Env::get('DEMO_PASSWORD', 'demo1234'); }

    /** Message explaining why this request is refused, or null when allowed. */
    public static function blockReason(Router $router, string $method): ?string
    {
        if (!self::enabled()) return null;
        $method = strtoupper($method);
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) return null;

        $prefix = $router->getMethodPrefix();
        if ($prefix === 'admin') {
            $controller = strtolower((string)$router->getController());
            if ($controller === 'auth') return null; // sign in / out
            if (in_array($controller, self::LOCKED_ADMIN, true)) {
                return 'This section is read-only in the demo.';
            }
        }

        if ($prefix === 'api') {
            $actions = self::LOCKED_API[strtolower((string)$router->getApiController())] ?? [];
            if (in_array('*', $actions, true) || in_array(strtolower((string)$router->getApiAction()), $actions, true)) {
                return 'Disabled in the demo.';
            }
        }
        return null;
    }
}
