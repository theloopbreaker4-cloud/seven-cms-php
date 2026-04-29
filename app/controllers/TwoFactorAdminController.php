<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * TwoFactorAdminController — TOTP setup for the currently logged-in admin user.
 *
 *   GET  /admin/2fa            — show QR + recovery codes (issues new secret if missing)
 *   POST /admin/2fa/enable     — verify code + persist enabled=1 + store recovery codes
 *   POST /admin/2fa/disable    — verify code, then disable
 */
class TwoFactorAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $user = Auth::getCurrentUser();
        $row  = DB::findOne('user_totp', ' user_id = :u ', [':u' => (int)$user->id]);

        if (!$row) {
            // Issue new secret + recovery codes (not enabled yet).
            $secret = Totp::generateSecret();
            $codes  = Totp::generateRecoveryCodes();
            DB::execute(
                'INSERT INTO user_totp (user_id, secret, enabled, recovery_codes)
                 VALUES (:u, :s, 0, :rc)',
                [
                    ':u'  => (int)$user->id,
                    ':s'  => $secret,
                    ':rc' => json_encode(Totp::hashRecoveryCodes($codes)),
                ]
            );
            $row    = DB::findOne('user_totp', ' user_id = :u ', [':u' => (int)$user->id]);
            $plain  = $codes;
        } else {
            $plain = []; // already issued; user only sees them once
        }

        $secret = (string)$row['secret'];
        $issuer = 'SevenCMS';
        $email  = (string)$user->email;
        $uri    = Totp::otpAuthUri($secret, $email, $issuer);
        $qr     = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($uri);

        return $this->app->view->render('users/twofactor', [
            'enabled' => (bool)$row['enabled'],
            'secret'  => $secret,
            'qr'      => $qr,
            'codes'   => $plain,
        ]);
    }

    public function enable($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $user = Auth::getCurrentUser();
        $row  = DB::findOne('user_totp', ' user_id = :u ', [':u' => (int)$user->id]);
        if (!$row) $this->back();

        $code = trim((string)($_POST['code'] ?? ''));
        if (!Totp::verify((string)$row['secret'], $code)) {
            Session::setFlash('Invalid code — try again.');
            $this->back();
        }
        DB::execute(
            'UPDATE user_totp SET enabled = 1, confirmed_at = NOW() WHERE user_id = :u',
            [':u' => (int)$user->id]
        );
        ActivityLog::log('auth.2fa.enabled', 'users', (int)$user->id, '2FA enabled');
        $this->back();
    }

    public function disable($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $user = Auth::getCurrentUser();
        $row  = DB::findOne('user_totp', ' user_id = :u ', [':u' => (int)$user->id]);
        if (!$row) $this->back();

        $code = trim((string)($_POST['code'] ?? ''));
        if (!Totp::verify((string)$row['secret'], $code)) {
            Session::setFlash('Invalid code — try again.');
            $this->back();
        }
        DB::execute('DELETE FROM user_totp WHERE user_id = :u', [':u' => (int)$user->id]);
        ActivityLog::log('auth.2fa.disabled', 'users', (int)$user->id, '2FA disabled');
        $this->back();
    }

    private function back(): void
    {
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/2fa');
        exit;
    }
}
