<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Browser session auth for the bundled site (same session as the admin).
 *
 * POST   /api/v1/session                  { login, password } → sets the httpOnly `token` cookie
 * DELETE /api/v1/session                  → revokes it
 * GET    /api/v1/session                  → current user
 * POST   /api/v1/account/register         { firstName, lastName, userName, email, password }
 * POST   /api/v1/account/password/forgot  { email, lang }
 * POST   /api/v1/account/password/reset   { token, password, confirmPassword }
 *
 * The session token never reaches JavaScript: it used to be returned in the body and
 * written to a script-readable cookie, so any XSS could lift an admin session.
 * Headless clients use the JWT endpoints (/api/v1/auth/*). The legacy /api/auth/*
 * URLs reach the same actions.
 */
class AuthApiController extends ApiController
{
    public function login($req, $res, $params)
    {
        if (!$req->isMethod('POST')) $res->jsonError(405, 'Method Not Allowed');
        RateLimit::check('site_login');

        $body     = $this->body();
        $login    = trim($body['login'] ?? '');
        $password = $body['password'] ?? '';
        $ip       = RateLimit::clientIp();
        $log      = Logger::channel('auth');

        if (!$login || !$password) {
            RateLimit::hit('site_login');
            $log->warn('Site login: empty fields', ['ip' => $ip]);
            $this->jsonError(422, 'login and password are required');
        }

        $user       = new User();
        $tokenValue = $user->auth($login, $password);

        if ($tokenValue === false) {
            RateLimit::hit('site_login');
            $log->warn('Site login: invalid credentials', ['login' => $login, 'ip' => $ip]);
            $this->jsonError(401, 'Invalid credentials');
        }

        RateLimit::clear('site_login');
        $this->startSession($res, $tokenValue);
        Event::emit('user.login', ['user' => $user]);
        $log->info('Site login: success', ['login' => $login, 'ip' => $ip]);
        http_response_code(200);
        return json_encode(['user' => $user->toPublic()]);
    }

    public function logout($req, $res, $params)
    {
        $token = $this->sessionToken();
        if ($token) {
            $t = new Token();
            $t->setToken($token);
            if ($t->id) {
                Logger::channel('auth')->info('Site logout', ['userId' => $t->userId, 'ip' => RateLimit::clientIp()]);
                $t->auth = 0;
                $t->save($t->id);
            }
        }
        $res->removeCookie('token');
        return json_encode(['message' => 'Logged out']);
    }

    public function me($req, $res, $params)
    {
        $token = $this->sessionToken();
        if (!$token) $this->jsonError(401, 'Unauthorized');
        $t = new Token();
        $t->setToken($token);
        if (!$t->id || !$t->auth) $this->jsonError(401, 'Invalid or expired session');
        $user = new User();
        $user->getOne($t->userId);
        if (!$user->id || !$user->isActive) $this->jsonError(403, 'Forbidden');
        return json_encode(['user' => $user->toPublic()]);
    }

    /** Cookie session first (site), Bearer as a fallback for old clients. */
    private function sessionToken(): ?string
    {
        $c = (string)($_COOKIE['token'] ?? '');
        if ($c !== '' && preg_match('/^[A-Za-z0-9]+$/', $c)) return $c;
        return $this->bearerToken();
    }

    private function startSession($res, string $tokenValue): void
    {
        if (class_exists('Session')) Session::regenerate();
        $res->createCookie('token', $tokenValue);
    }

    public function register($req, $res, $params)
    {
        if (!$req->isMethod('POST')) $res->jsonError(405, 'Method Not Allowed');
        RateLimit::check('api_register');

        $body = $this->body();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log  = Logger::channel('auth');

        $v = Validator::make($body)
            ->rule('firstName', 'required|max:64')
            ->rule('lastName',  'required|max:64')
            ->rule('userName',  'required|max:32|unique:user,userName')
            ->rule('email',     'required|email|unique:user,email')
            ->rule('password',  'required|min:8');

        if ($v->fails()) {
            RateLimit::hit('api_register');
            $log->warn('API register: validation failed', ['ip' => $ip]);
            $res->jsonError(422, $v->errors());
        }

        $user = new User();
        $id   = $user->register($body);
        if (is_null($id)) {
            $log->warn('API register: email/username taken', ['ip' => $ip]);
            $res->jsonError(409, 'Email or username already exists');
        }

        $tokenValue = $user->auth($body['email'], $body['password']);
        if ($tokenValue) $this->startSession($res, $tokenValue);
        Event::emit('user.registered', ['userId' => $id]);
        $log->info('API register: success', ['id' => $id, 'email' => $body['email'], 'ip' => $ip]);
        http_response_code(201);
        return json_encode(['user' => $user->toPublic()]);
    }

    public function forgot($req, $res, $params)
    {
        if (!$req->isMethod('POST')) $res->jsonError(405, 'Method Not Allowed');
        RateLimit::check('api_forgot');

        $body  = $this->body();
        $email = filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log   = Logger::channel('auth');

        if (!$email) {
            RateLimit::hit('api_forgot');
            $res->jsonError(422, 'A valid email address is required');
        }
        RateLimit::hit('api_forgot');

        $log->info('API forgot: reset requested', ['email' => $email, 'ip' => $ip]);
        PasswordReset::request($email, (string)($body['lang'] ?? 'en'));
        return json_encode(['message' => 'If that email exists, a reset link has been sent.']);
    }

    public function reset($req, $res, $params)
    {
        if (!$req->isMethod('POST')) $res->jsonError(405, 'Method Not Allowed');
        RateLimit::check('api_reset');

        $body    = $this->body();
        $token   = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');
        $pass    = $body['password'] ?? '';
        $confirm = $body['confirmPassword'] ?? $pass;
        $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log     = Logger::channel('auth');

        if (!$token || !$pass || !$confirm) {
            $res->jsonError(422, 'token, password, and confirmPassword are required');
        }
        if ($pass !== $confirm) {
            $res->jsonError(422, 'Passwords do not match');
        }
        if (strlen($pass) < 8) {
            $res->jsonError(422, 'Password must be at least 8 characters');
        }

        if (!PasswordReset::complete($token, $pass)) {
            RateLimit::hit('api_reset');
            $log->warn('API reset: invalid or expired token', ['ip' => $ip]);
            $res->jsonError(400, 'This reset link is invalid or has expired.');
        }
        RateLimit::clear('api_reset');
        $log->info('API reset: password changed', ['ip' => $ip]);
        return json_encode(['message' => 'Password updated successfully.']);
    }
}
