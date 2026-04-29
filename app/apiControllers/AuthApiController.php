<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Auth API — Bearer token via Authorization header
 *
 * POST /api/auth/login    { login, password }
 * POST /api/auth/logout
 * GET  /api/auth/me
 * POST /api/auth/register { firstName, lastName, userName, email, password }
 * POST /api/auth/forgot   { email }
 * POST /api/auth/reset    { token, password, confirmPassword }
 */
class AuthApiController extends ApiController
{
    public function login($req, $res, $params)
    {
        if (!$req->isMethod('POST')) $res->jsonError(405, 'Method Not Allowed');

        $body     = $this->body();
        $login    = trim($body['login'] ?? '');
        $password = $body['password'] ?? '';
        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log      = Logger::channel('auth');

        if (!$login || !$password) {
            $log->warn('API login: empty fields', ['ip' => $ip]);
            $this->jsonError(422, 'login and password are required');
        }

        $user       = new User();
        $tokenValue = $user->auth($login, $password);

        if ($tokenValue === false) {
            $log->warn('API login: invalid credentials', ['login' => $login, 'ip' => $ip]);
            $this->jsonError(401, 'Invalid credentials');
        }

        Event::emit('user.login', ['user' => $user]);
        $log->info('API login: success', ['login' => $login, 'ip' => $ip]);
        http_response_code(200);
        return json_encode(['token' => $tokenValue, 'user' => $user->toPublic()]);
    }

    public function logout($req, $res, $params)
    {
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $log   = Logger::channel('auth');
        $token = $this->bearerToken();
        if ($token) {
            $t = new Token();
            $t->setToken($token);
            if ($t->id) {
                $log->info('API logout', ['userId' => $t->userId, 'ip' => $ip]);
                $t->auth = 0;
                $t->save($t->id);
            }
        }
        return json_encode(['message' => 'Logged out']);
    }

    public function me($req, $res, $params)
    {
        $user = $this->requireAuthToken();
        Logger::channel('auth')->debug('API me', ['userId' => $user->id]);
        return json_encode($user->toPublic());
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
        Event::emit('user.registered', ['userId' => $id]);
        $log->info('API register: success', ['id' => $id, 'email' => $body['email'], 'ip' => $ip]);
        http_response_code(201);
        return json_encode(['token' => $tokenValue ?: null, 'user' => $user->toPublic()]);
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
        // TODO: generate token and send email
        return json_encode(['message' => 'If that email exists, a reset link has been sent.']);
    }

    public function reset($req, $res, $params)
    {
        if (!$req->isMethod('POST')) $res->jsonError(405, 'Method Not Allowed');
        RateLimit::check('api_reset');

        $body    = $this->body();
        $token   = preg_replace('/[^a-f0-9]/', '', $body['token'] ?? '');
        $pass    = $body['password'] ?? '';
        $confirm = $body['confirmPassword'] ?? '';
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

        $log->info('API reset: password reset submitted', ['ip' => $ip]);
        // TODO: validate token and update password
        return json_encode(['message' => 'Password updated successfully.']);
    }
}
