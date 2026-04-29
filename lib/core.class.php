<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

require_once(dirname(__FILE__) . DS . 'general.class.php');

class Core
{
    public const VERSION = '1.0.0';

    public static ?object $app      = null;
    public static mixed   $reqFiles = null;

    public static function getVersion(): string
    {
        return self::VERSION;
    }

    public static function app(): ?object
    {
        return self::$app;
    }

    public static function createWebApp(mixed $reqFiles = null): object
    {
        self::$reqFiles = $reqFiles;
        return self::createApp('General');
    }

    public static function createApp(string $class): object
    {
        self::$app = new $class(self::$reqFiles);
        return self::$app;
    }
}
