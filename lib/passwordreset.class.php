<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * PasswordReset — one-time, hashed, expiring reset tokens.
 *
 *   PasswordReset::request($email, $lang);   // queues the email if the account exists
 *   PasswordReset::complete($token, $pass);  // true on success; revokes all sessions
 */
class PasswordReset
{
    private const TTL_SEC = 3600;

    public static function request(string $email, string $lang = 'en'): void
    {
        $user = DB::findOne('user', ' email = :e AND is_active = 1 ', [':e' => strtolower($email)]);
        if (!$user) return;

        DB::execute(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = :u AND used_at IS NULL',
            [':u' => (int)$user['id']]
        );

        $token = bin2hex(random_bytes(32));
        DB::execute(
            // Expiry uses the DB clock — the same one complete() compares against.
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:u, :h, NOW() + INTERVAL ' . self::TTL_SEC . ' SECOND)',
            [':u' => (int)$user['id'], ':h' => hash('sha256', $token)]
        );

        $lang = preg_match('/^[a-z]{2}$/', $lang) ? $lang : 'en';
        $link = Seven::app()->config['baseUrl'] . '/' . $lang . '/auth/recover/' . $token;
        $site = htmlspecialchars((string)(Seven::app()->config['siteName'] ?? 'Seven CMS'), ENT_QUOTES, 'UTF-8');
        $href = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        Mailer::queue(
            (string)$user['email'],
            'Reset your password — ' . $site,
            "<p>Someone asked to reset the password for your {$site} account.</p>"
            . "<p><a href=\"{$href}\">Choose a new password</a> (valid for 1 hour).</p>"
            . '<p>If it wasn\'t you, ignore this email — your password stays the same.</p>'
        );
    }

    public static function complete(string $token, string $password): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return false;

        $row = DB::findOne(
            'password_resets',
            ' token_hash = :h AND used_at IS NULL AND expires_at > NOW() ',
            [':h' => hash('sha256', $token)]
        );
        if (!$row) return false;

        $userId = (int)$row['user_id'];
        DB::execute('UPDATE password_resets SET used_at = NOW() WHERE id = :id', [':id' => (int)$row['id']]);
        DB::execute(
            'UPDATE user SET password = :p, updated_at = NOW() WHERE id = :u',
            [':p' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]), ':u' => $userId]
        );

        // Whoever knew the old password must not stay signed in.
        DB::execute('UPDATE token SET auth = 0 WHERE user_id = :u', [':u' => $userId]);
        RefreshToken::revokeAllForUser($userId);
        return true;
    }
}
