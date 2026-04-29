<?php

defined('_SEVEN') or die('No direct script access allowed');

// Fixed: was "extends Api" — Response is a web response, not an API helper
class Response
{
    public $errorMessage = null;

    public function errorCode($code = 404, $message = '', $temp = false) {
        http_response_code($code);
        $this->errorMessage = $message;
        if (empty($message) || $temp) {
            $prefix = Seven::app()->router->getMethodPrefix();
            $sub    = $prefix ?: 'site';
            $path   = Seven::app()->config['viewPath'] . $sub . DS . 'error' . $code . '.html';
            // Fallback to site/ if admin error view missing
            if (!file_exists($path) && $sub !== 'site') {
                $path = Seven::app()->config['viewPath'] . 'site' . DS . 'error' . $code . '.html';
            }
            if (file_exists($path)) {
                ob_start();
                include($path);
                print(ob_get_clean());
            } else {
                Seven::app()->logger->warn('Error view not found', ['path' => $path]);
                print('Error ' . $code);
            }
        } else {
            print($message);
        }
        exit;
    }

    public function createCookie(string $name, string $value, ?int $time = null): void
    {
        if (is_null($time)) $time = Config::get('timeCookie');
        if (empty($name) || empty($value)) return;
        $secure = (PROTOCOL === 'https://');
        setcookie($name, $value, [
            'expires'  => time() + $time,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public function removeCookie(string $name, ?int $time = null): void
    {
        if (is_null($time)) $time = Config::get('timeCookie');
        if (!isset($_COOKIE[$name])) return;
        unset($_COOKIE[$name]);
        $secure = (PROTOCOL === 'https://');
        setcookie($name, '', [
            'expires'  => time() - $time,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    public function redirect(string $controller, string $method = 'index', array $args = []): never
    {
        $safeArgs = array_map(fn($a) => rawurlencode((string)$a), $args);
        $prefix   = Seven::app()->router->getMethodPrefix();
        $lang     = Seven::app()->router->getLanguage();

        // admin/api use prefix; site pages use lang prefix
        $base = Seven::app()->config['baseUrl'];
        if ($prefix && $prefix !== '') {
            $location = $base . '/' . $prefix . '/' . $controller . '/' . $method;
        } else {
            $location = $base . '/' . $lang . '/' . $controller . '/' . $method;
        }
        if ($safeArgs) {
            $location .= '/' . implode('/', $safeArgs);
        }
        header('Location: ' . $location);
        exit;
    }

    public function redirectUrl(string $url): never
    {
        // Only allow relative or same-origin URLs
        $parsed = parse_url($url);
        if (!empty($parsed['scheme']) || !empty($parsed['host'])) {
            // External URL — only allow if it starts with configured baseUrl
            $base = Seven::app()->config['baseUrl'];
            if (!str_starts_with($url, $base)) {
                header('Location: ' . $base . '/');
                exit;
            }
        }
        header('Location: ' . $url);
        exit;
    }
}
