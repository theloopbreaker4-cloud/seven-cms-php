<?php

defined('_SEVEN') or die('No direct script access allowed');

class Csrf
{
    private const KEY = '_csrf_token';

    // Generate or return existing token for this session
    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    // Render a hidden input — use in every form: echo Csrf::field()
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8')
            . '">';
    }

    // Validate POST token — call at start of every POST handler
    public static function verify(array $data): void
    {
        $submitted = $data['_csrf_token'] ?? '';
        if (!$submitted || !hash_equals(self::token(), $submitted)) {
            http_response_code(419);
            exit('CSRF token mismatch.');
        }
    }

    // Rotate token after successful POST (prevents token fixation)
    public static function rotate(): void
    {
        $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
}
