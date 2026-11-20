<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class ApiController extends Controller
{
    public function __construct(mixed $app, mixed $data = [])
    {
        parent::__construct($app, $data);
        $this->cors();
    }

    protected function cors(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }

    protected function json(mixed $data, int $code = 200): string
    {
        http_response_code($code);
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function jsonError(int $code, mixed $message): never
    {
        http_response_code($code);
        $body = is_array($message) ? $message : ['error' => $message];
        print json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Bearer → active User, or abort. Accepts the JWT access token from
     * POST /api/v1/auth/login (the supported way for headless clients) and, for old
     * integrations, an opaque session token. Cookies are deliberately not read here:
     * these endpoints change data and must not be reachable by CSRF.
     */
    private function bearerUser(): User
    {
        $token = $this->bearerToken();
        if (!$token) $this->jsonError(401, 'Unauthorized');

        if (substr_count($token, '.') === 2) {
            $claims = class_exists('Jwt') ? Jwt::verify($token) : null;
            if (!$claims || empty($claims['sub'])) $this->jsonError(401, 'Invalid or expired token');
            $userId = (int)$claims['sub'];
        } else {
            $t = new Token();
            $t->setToken($token);
            if (!$t->id || !$t->auth) $this->jsonError(401, 'Invalid or expired token');
            $userId = (int)$t->userId;
        }
        $user = new User();
        $user->getOne($userId);
        if (!$user->id || !$user->isActive) $this->jsonError(403, 'Forbidden');
        return $user;
    }

    protected function requireAdminToken(): mixed
    {
        $user = $this->bearerUser();
        if ($user->role !== 'admin') $this->jsonError(403, 'Admin only');
        return $user;
    }

    protected function requireEditorToken(): mixed
    {
        $user = $this->bearerUser();
        if (!$user->isEditor()) $this->jsonError(403, 'Editor access required');
        return $user;
    }

    protected function requireModeratorToken(): mixed
    {
        $user = $this->bearerUser();
        if (!$user->isModerator()) $this->jsonError(403, 'Moderator access required');
        return $user;
    }

    protected function requireAuthToken(): mixed
    {
        return $this->bearerUser();
    }

    protected function bearerToken(): ?string
    {
        $headers = getallheaders();
        $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function body(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }
}
