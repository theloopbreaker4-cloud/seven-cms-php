<?php

defined('_SEVEN') or die('No direct script access allowed');

class User extends Model
{
    public ?int    $id               = null;

    // Profile
    public string  $firstName        = '';
    public string  $lastName         = '';
    public string  $userName         = '';
    public string  $email            = '';
    public string  $password         = '';  // hashed (bcrypt)
    public string  $avatar           = '';
    public string  $mobile           = '';
    public string  $country          = '';

    // Access control
    public string  $role             = 'user';   // 'user' | 'editor' | 'moderator' | 'admin'
    public int     $isActive         = 1;

    // OAuth
    public string  $provider         = 'local';  // 'local'|'google'|'github'|'discord'
    public string  $providerId       = '';

    // 2FA
    public int     $twoFactorEnabled = 0;
    public string  $twoFactorSecret  = '';

    // Activity
    public ?string $lastLoginAt      = null;
    public ?string $createdAt        = null;
    public ?string $updatedAt        = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public const ROLES = ['user', 'editor', 'moderator', 'admin'];

    public function isAdmin(): bool     { return $this->role === 'admin'; }
    public function isEditor(): bool    { return in_array($this->role, ['admin', 'editor'], true); }
    public function isModerator(): bool { return in_array($this->role, ['admin', 'moderator'], true); }

    public static function hashPassword(string $plainText): string {
        return password_hash($plainText, PASSWORD_BCRYPT, ['cost' => 10]);
    }

    public function verifyPassword(string $plainText): bool {
        return password_verify($plainText, $this->password);
    }

    public function auth(string $login = '', string $password = ''): string|false {
        $isEmail = str_contains($login, '@');
        $field   = $isEmail ? 'email' : 'user_name';

        $row = DB::findOne(
            $this->tableName,
            ' ' . $field . ' = :login AND is_active = 1 ',
            [':login' => strtolower($login)]
        );

        if (is_null($row)) return false;

        $this->setModel($row);

        if (!$this->verifyPassword($password)) return false;

        // Update last login
        $bean              = DB::load($this->tableName, $this->id);
        $bean->last_login_at = date('Y-m-d H:i:s');
        DB::store($bean);

        $token = new Token($this->id);
        $token->save();
        return $token->getValue();
    }

    public function register(array $data = []): ?int {
        $duplicate = DB::findOne(
            $this->tableName,
            ' email = :email OR user_name = :userName ',
            [':email' => strtolower($data['email']), ':userName' => strtolower($data['userName'])]
        );
        if (!is_null($duplicate)) return null;

        $this->firstName = $data['firstName']  ?? '';
        $this->lastName  = $data['lastName']   ?? '';
        $this->userName  = strtolower($data['userName'] ?? '');
        $this->email     = strtolower($data['email']);
        $this->password  = self::hashPassword($data['password']);
        $this->role      = 'user';
        $this->isActive  = 1;
        $this->provider  = 'local';
        $this->createdAt = date('Y-m-d H:i:s');
        $this->updatedAt = $this->createdAt;

        return $this->save();
    }

    public function toPublic(): array {
        return [
            'id'               => $this->id,
            'firstName'        => $this->firstName,
            'lastName'         => $this->lastName,
            'userName'         => $this->userName,
            'email'            => $this->email,
            'avatar'           => $this->avatar,
            'mobile'           => $this->mobile,
            'country'          => $this->country,
            'role'             => $this->role,
            'isActive'         => (bool) $this->isActive,
            'provider'         => $this->provider,
            'twoFactorEnabled' => (bool) $this->twoFactorEnabled,
            'lastLoginAt'      => $this->lastLoginAt,
            'createdAt'        => $this->createdAt,
            'updatedAt'        => $this->updatedAt,
        ];
    }
}
