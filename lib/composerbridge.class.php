<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ComposerBridge — opt-in Composer autoloader integration.
 *
 * SevenCMS historically uses a manual `require_once` loader in lib/seven.class.php.
 * That keeps zero-install installs working — drop the project on any LAMP host and
 * it runs without `composer install`. This bridge adds a *parallel* Composer
 * autoloader when `vendor/autoload.php` is present, so plugins (or Stripe/AWS/etc.)
 * can ship classes via `composer require` without touching the manual loader.
 *
 * Bootstrap order (public/index.php) is intentionally:
 *   1. lib/env.class.php     — read .env
 *   2. lib/seven.class.php   — manual class loader, defines core helpers
 *   3. ComposerBridge::boot()— adds vendor autoload if installed
 *
 * Production deployment recommendation:
 *   composer install --no-dev --optimize-autoloader
 *
 * Returns true if Composer's autoloader was registered.
 */
class ComposerBridge
{
    private static bool $booted = false;

    public static function boot(): bool
    {
        if (self::$booted) return true;

        $autoload = ROOT_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!is_file($autoload)) return false;

        require_once $autoload;
        self::$booted = true;
        return true;
    }

    public static function isInstalled(): bool
    {
        return is_file(ROOT_DIR . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
    }

    /** Returns the installed version of a Composer package, or null. */
    public static function packageVersion(string $package): ?string
    {
        $installedFile = ROOT_DIR . '/vendor/composer/installed.json';
        if (!is_file($installedFile)) return null;
        $data = json_decode((string)file_get_contents($installedFile), true);
        $packages = $data['packages'] ?? $data ?? [];
        foreach ($packages as $p) {
            if (($p['name'] ?? '') === $package) return (string)($p['version'] ?? '');
        }
        return null;
    }
}
