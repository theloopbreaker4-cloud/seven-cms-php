<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Utils
{
    public static function toCamelCase($value) {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return lcfirst(str_replace(' ', '', $value));
    }

    public static function toSnakeCase($value) {
        return strtolower(preg_replace('/(?<=\w)(?=[A-Z])/', '_$1', $value));
    }

    public static function toTrainCase($value) {
        return strtolower(preg_replace('/(?<=\w)(?=[A-Z])/', '-$1', $value));
    }

    // Fixed: was using backslash 'lib\extension' — now uses DS constant
    public static function loadExtension($path = '', $logger = null) {
        $loadPath = ROOT_DIR . DS . 'lib' . DS . 'extension' . DS . strtolower($path) . '.php';
        if (file_exists($loadPath)) {
            include($loadPath);
        } elseif (!is_null($logger)) {
            $logger->warn('Failed to include extension', ['path' => $loadPath]);
        }
    }
}
