<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ApiV1Controller — base controller for /api/v1/* endpoints.
 *
 * Differences from the legacy ApiController:
 *   - Authenticates via JWT access token (not Token model)
 *   - Provides RBAC-aware `requirePermission(...)`
 *   - `paginate()` helper produces consistent { items, total, limit, offset } shape
 *   - Rate-limits routes by default (configurable per controller)
 */
abstract class ApiV1Controller extends ApiController
{
    /** Cached current user resolved from Authorization header. */
    protected ?array $currentUser = null;

    /** Override per-controller for tighter rate limits. */
    protected int $rateLimitPerMinute = 120;

    public function __construct(mixed $app, mixed $data = [])
    {
        parent::__construct($app, $data);
        $this->applyRateLimit();
    }

    /**
     * Resolve the current user from a JWT access token. Returns null when missing/invalid.
     * Stateless — no DB lookup unless `loadUser=true`.
     *
     * @return array{id:int, email:string, role:string}|null
     */
    protected function authUser(bool $loadUser = false): ?array
    {
        if ($this->currentUser !== null) return $this->currentUser;

        $token = $this->bearerToken();
        if (!$token) return null;

        $claims = Jwt::verify($token);
        if (!$claims || empty($claims['sub'])) return null;

        $user = ['id' => (int)$claims['sub'], 'email' => (string)($claims['email'] ?? ''), 'role' => (string)($claims['role'] ?? 'user')];

        if ($loadUser) {
            $row = DB::findOne('users', ' id = :id ', [':id' => $user['id']]);
            if (!$row || !((int)$row['is_active'])) return null;
            $user = ['id' => (int)$row['id'], 'email' => (string)$row['email'], 'role' => (string)$row['role']];
        }

        return $this->currentUser = $user;
    }

    protected function requireAuth(): array
    {
        $u = $this->authUser();
        if (!$u) $this->jsonError(401, 'Unauthorized');
        return $u;
    }

    protected function requirePermission(string $permission): array
    {
        $u = $this->requireAuth();
        if (class_exists('Permission')) {
            if (!Permission::can($permission, (int)$u['id'])) $this->jsonError(403, 'Forbidden');
        } else {
            // Fallback: only admin role passes.
            if (($u['role'] ?? '') !== 'admin') $this->jsonError(403, 'Forbidden');
        }
        return $u;
    }

    /**
     * Build a standardized list response.
     *
     * @param array         $items
     * @param int           $total
     * @param array{limit?:int, offset?:int} $opts
     */
    protected function paginate(array $items, int $total, array $opts = []): string
    {
        return $this->json([
            'items'  => $items,
            'total'  => $total,
            'limit'  => (int)($opts['limit']  ?? 50),
            'offset' => (int)($opts['offset'] ?? 0),
        ]);
    }

    private function applyRateLimit(): void
    {
        if (!class_exists('RateLimit')) return;
        $key = 'api:' . ($this->clientIp() ?? 'anon') . ':' . static::class;
        if (!RateLimit::allow($key, $this->rateLimitPerMinute, 60)) {
            header('Retry-After: 60');
            $this->jsonError(429, 'Too Many Requests');
        }
    }

    private function clientIp(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
            if (!empty($_SERVER[$h])) return trim(explode(',', (string)$_SERVER[$h])[0]);
        }
        return null;
    }
}
