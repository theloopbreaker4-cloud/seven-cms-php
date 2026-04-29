<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Request
{
    protected static ?RequestData $requestData = null;

    public static function getRequestData(): RequestData
    {
        self::$requestData = new RequestData(self::getMethod());
        return self::$requestData;
    }

    public static function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
}

class RequestData
{
    protected ?array  $data   = null;
    protected ?string $cookie = null;
    protected string  $method;

    public function __toString(): string { return 'RequestData'; }

    public function __construct(string $method)
    {
        $this->method = $method;
        switch ($method) {
            case 'GET':
                $this->data = $_GET;
                break;
            case 'POST':
                if (empty($_POST)) {
                    $raw = file_get_contents('php://input');
                    $this->data = json_decode($raw, true) ?? [];
                } else {
                    $this->data = $_POST;
                }
                break;
            case 'PUT':
            case 'DELETE':
                $raw = file_get_contents('php://input');
                $this->data = json_decode($raw, true) ?? [];
                break;
            default:
                $this->data = [];
        }
    }

    public function getMethod(): string { return $this->method; }

    public function isMethod(string $method): bool { return $this->method === $method; }

    // Return raw data (use only when you need unescaped, e.g. JSON API)
    public function getData(): ?array { return $this->data; }

    // Return a single sanitized string value
    public function get(string $key, string $default = ''): string
    {
        $value = $this->data[$key] ?? $default;
        return $this->sanitizeString($value);
    }

    // Return a sanitized integer
    public function getInt(string $key, int $default = 0): int
    {
        return (int)($this->data[$key] ?? $default);
    }

    // Return a validated email or empty string
    public function getEmail(string $key): string
    {
        $value = trim($this->data[$key] ?? '');
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    // Return sanitized array of strings (e.g. multilingual fields)
    public function getArray(string $key): array
    {
        $value = $this->data[$key] ?? [];
        if (!is_array($value)) return [];
        return array_map([$this, 'sanitizeString'], $value);
    }

    public function getCookieToken(): ?string
    {
        return isset($_COOKIE['token']) ? $this->sanitizeString($_COOKIE['token']) : null;
    }

    public function getCookie(string $key): ?string
    {
        if (!isset($_COOKIE[$key])) return null;
        $this->cookie = $this->sanitizeString($_COOKIE[$key]);
        return $this->cookie;
    }

    // Sanitize a string: strip tags, encode special chars
    private function sanitizeString(mixed $value): string
    {
        if (!is_string($value)) $value = (string)$value;
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
