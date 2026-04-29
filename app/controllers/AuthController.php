<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Auth controller — GET routes only.
 * All login/signup/reset logic is handled by AuthApiController (Bearer token API).
 * Vue SPA pages call the API directly; these routes just serve the Vue shell.
 */
class AuthController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    private function captchaSettings(): array
    {
        return [
            'captchaOnLogin'    => (bool)Setting::get('captcha_on_login',    '0'),
            'captchaOnRegister' => (bool)Setting::get('captcha_on_register', '1'),
            'captchaOnForgot'   => (bool)Setting::get('captcha_on_forgot',   '1'),
        ];
    }

    /** GET /{lang}/auth — login page shell */
    public function index($req, $res, $params)
    {
        $this->app->setTitle(Lang::t('signin', 'auth'));
        if (Auth::isLogin()) $res->redirect('home', 'index');
        return $this->viewData($this->captchaSettings());
    }

    /** GET /{lang}/auth/register */
    public function register($req, $res, $params)
    {
        $this->app->setTitle(Lang::t('signup', 'auth'));
        if (Auth::isLogin()) $res->redirect('home', 'index');
        return $this->viewData($this->captchaSettings());
    }

    /** GET /{lang}/auth/forgot */
    public function forgot($req, $res, $params)
    {
        $this->app->setTitle(Lang::t('recoverPassword', 'auth'));
        if (Auth::isLogin()) $res->redirect('home', 'index');
        return $this->viewData($this->captchaSettings());
    }

    /** GET /{lang}/auth/recover/{token} */
    public function recover($req, $res, $params)
    {
        $this->app->setTitle(Lang::t('resetPassword', 'auth'));
        $token = preg_replace('/[^a-f0-9]/', '', $params[0] ?? '');
        return $this->viewData(['token' => $token]);
    }

    /** GET /{lang}/auth/logout — clear server-side cookie + session */
    public function logout($req, $res, $params)
    {
        $user    = Auth::getCurrentUser();
        $authLog = Logger::channel('auth');
        if ($user) {
            $authLog->info('User logged out', ['userId' => $user->id]);
        }
        $res->removeCookie('token');
        Auth::clearToken();
        Session::regenerate();
        // Open-redirect-safe: only accept paths starting with a single "/"
        // followed by a 2-letter language code. Reject "//evil.com/..." (which
        // browsers treat as protocol-relative), "javascript:", "\evil", etc.
        $next = $_GET['next'] ?? '';
        if ($next !== ''
            && $next[0] === '/'
            && ($next[1] ?? '') !== '/'
            && ($next[1] ?? '') !== '\\'
            && preg_match('#^/[a-z]{2}(/|$)#', $next)
            && !preg_match('#[\s:]#', $next)) {
            header('Location: ' . $next);
            exit;
        }
        $res->redirect('auth', 'index');
    }
}
