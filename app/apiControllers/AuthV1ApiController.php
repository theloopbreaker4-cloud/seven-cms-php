<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * AuthV1ApiController — JWT auth with refresh tokens.
 *
 *   POST /api/v1/auth/login    {email,password}      -> {access_token, refresh_token, user}
 *   POST /api/v1/auth/refresh  {refresh_token}       -> {access_token, refresh_token}  (rotation)
 *   POST /api/v1/auth/logout   {refresh_token?}      -> {ok:true}
 *   GET  /api/v1/auth/me                              -> {user, permissions[], roles[]}
 *
 * Access tokens live 15 minutes; refresh tokens rotate on every use, valid for 30 days.
 */
class AuthV1ApiController extends ApiV1Controller
{
    public const ACCESS_TTL = 900; // 15 min

    public function login($req, $res, $params)
    {
        RateLimit::check('api_login');

        $body = $this->jsonBody();
        $email    = trim((string)($body['email']    ?? ''));
        $password = (string)        ($body['password'] ?? '');
        if ($email === '' || $password === '') {
            RateLimit::hit('api_login');
            $this->jsonError(400, 'email and password required');
        }

        $row = DB::findOne('users', ' email = :e ', [':e' => $email]);
        if (!$row)                    { RateLimit::hit('api_login'); $this->jsonError(401, 'Invalid credentials'); }
        if (!password_verify($password, (string)$row['password'])) { RateLimit::hit('api_login'); $this->jsonError(401, 'Invalid credentials'); }
        if (!(int)$row['is_active'])  $this->jsonError(403, 'Account inactive');

        RateLimit::clear('api_login');
        return print $this->json($this->buildAuthResponse($row));
    }

    public function refresh($req, $res, $params)
    {
        RateLimit::check('api_refresh');

        $body  = $this->jsonBody();
        $plain = (string)($body['refresh_token'] ?? '');
        if ($plain === '') $this->jsonError(400, 'refresh_token required');

        $rotated = RefreshToken::rotate($plain);
        if (!$rotated) { RateLimit::hit('api_refresh'); $this->jsonError(401, 'Invalid refresh token'); }

        $row = DB::findOne('users', ' id = :id ', [':id' => $rotated['user_id']]);
        if (!$row || !((int)$row['is_active'])) $this->jsonError(403, 'Account inactive');

        $access = Jwt::sign($this->claimsFor($row), self::ACCESS_TTL);
        return print $this->json([
            'access_token'  => $access,
            'refresh_token' => $rotated['token'],
            'expires_in'    => self::ACCESS_TTL,
        ]);
    }

    public function logout($req, $res, $params)
    {
        $body  = $this->jsonBody();
        $plain = (string)($body['refresh_token'] ?? '');
        if ($plain) RefreshToken::revoke($plain);

        // Revoke all if `all=true`.
        if (!empty($body['all'])) {
            $u = $this->authUser();
            if ($u) RefreshToken::revokeAllForUser((int)$u['id']);
        }

        if (class_exists('ActivityLog')) ActivityLog::log('auth.logout');
        return print $this->json(['ok' => true]);
    }

    public function me($req, $res, $params)
    {
        $u = $this->requireAuth();
        $row = DB::findOne('users', ' id = :id ', [':id' => $u['id']]);
        if (!$row) $this->jsonError(404, 'Not found');

        $perms = class_exists('Permission') ? Permission::userPermissions((int)$u['id']) : [];
        $roles = class_exists('Permission') ? Permission::userRoles((int)$u['id'])       : [];

        return print $this->json([
            'user' => [
                'id'        => (int)$row['id'],
                'email'     => $row['email'],
                'firstName' => $row['first_name'] ?? null,
                'lastName'  => $row['last_name']  ?? null,
                'role'      => $row['role'],
            ],
            'permissions' => $perms,
            'roles'       => $roles,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────

    private function buildAuthResponse(array $userRow): array
    {
        $access  = Jwt::sign($this->claimsFor($userRow), self::ACCESS_TTL);
        $refresh = RefreshToken::issue((int)$userRow['id'], (string)($_SERVER['HTTP_USER_AGENT'] ?? null));

        if (class_exists('ActivityLog')) {
            ActivityLog::log('auth.login', 'users', (int)$userRow['id'], 'API login');
        }

        return [
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'expires_in'    => self::ACCESS_TTL,
            'user'          => [
                'id'        => (int)$userRow['id'],
                'email'     => $userRow['email'],
                'firstName' => $userRow['first_name'] ?? null,
                'lastName'  => $userRow['last_name']  ?? null,
                'role'      => $userRow['role'],
            ],
        ];
    }

    private function claimsFor(array $userRow): array
    {
        return [
            'sub'   => (int)$userRow['id'],
            'email' => $userRow['email'],
            'role'  => $userRow['role'],
        ];
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $arr = $raw ? json_decode($raw, true) : null;
        return is_array($arr) ? $arr : $_POST;
    }
}
