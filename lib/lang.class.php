<?php

defined('_SEVEN') or die('No direct script access allowed');

class Lang
{
    protected static $languageData = null;
    protected static $location     = null;

    // Fixed: was Lang::load($this) — no argument needed, reads from Seven::app()
    public static function load() {
        $langCode     = Seven::app()->router->getLanguage();
        $langFilePath = ROOT_DIR . DS . 'lang' . DS . 'site' . DS . strtolower($langCode) . '.php';
        if (file_exists($langFilePath)) {
            self::$languageData = include($langFilePath);
            return true;
        }
        return false;
    }

    public static function change($langCode = 'en') {
        $router     = Seven::app()->router;
        $controller = $router->getController();
        $action     = $router->getAction();
        $slug       = $router->getSlug();
        $params     = $router->getParams();

        // Reconstruct the path segment after the language prefix
        if ($slug) {
            // blog/my-post or page/my-page — slug replaces action
            $path = $controller . '/' . $slug;
        } elseif ($action && $action !== 'index') {
            $path = $controller . '/' . $action;
            if ($params) $path .= '/' . implode('/', $params);
        } else {
            $path = $controller !== 'home' ? $controller : '';
        }

        self::$location = '/' . $langCode . ($path ? '/' . $path : '/');
        return self::$location;
    }

    public static function allGroups(): array {
        return self::$languageData ?? [];
    }

    public static function hasKey($key, $prefix = null) {
        if (!is_null($prefix)) {
            return isset(self::$languageData[strtolower($prefix)])
                && !is_null(self::findT($key, self::$languageData));
        }
        return !is_null(self::findT($key, self::$languageData));
    }

    public static function t($key, $prefix = null, $defaultValue = '') {
        $fArray = self::$languageData ?? [];
        if (!is_null($prefix)) {
            $fArray = $fArray[strtolower($prefix)] ?? [];
        }
        return $fArray[strtolower($key)] ?? self::findT($key, $fArray) ?? $defaultValue;
    }

    protected static function findT($tKey, $array = []) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $found = self::findT($tKey, $value);
                if (!is_null($found)) return $found;
            } elseif ($key === $tKey) {
                return $value;
            }
        }
        return null;
    }
}
