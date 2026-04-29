<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Auth
{
    protected static ?Token $token = null;

    public static function setToken(?string $value): void
    {
        if (!$value) return;
        $t = new Token();
        $t->setToken($value);
        // Only keep if token was actually found in DB
        if ($t->id) {
            self::$token = $t;
        }
    }

    public static function clearToken(): void
    {
        if (self::$token && self::$token->id) {
            self::$token->auth = 0;
            self::$token->save(self::$token->id);
        }
        self::$token = null;
    }

    public static function getToken(): ?Token
    {
        return self::$token;
    }

    public static function getCurrentUser(): ?User
    {
        if (!self::$token || !self::$token->userId) return null;
        $user = new User();
        $user->getOne(self::$token->userId);
        return $user->id ? $user : null;
    }

    public static function isLogin(): bool
    {
        return self::$token !== null && (bool) self::$token->id;
    }
}
