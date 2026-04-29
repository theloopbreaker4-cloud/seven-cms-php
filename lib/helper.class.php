<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Helper
{
    // Generate a URL-safe slug from any string
    // "Hello World!" → "hello-world"
    public static function slug(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        // Transliterate Cyrillic / Georgian / etc. if iconv available
        if (function_exists('iconv')) {
            $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        }
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        return trim($text, '-');
    }

    // Truncate a string to N chars, append ellipsis
    public static function truncate(string $text, int $length = 160, string $suffix = '…'): string
    {
        $text = strip_tags($text);
        if (mb_strlen($text, 'UTF-8') <= $length) return $text;
        return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
    }

    // Format a datetime string for display
    // "2025-04-17 14:30:00" → "17 Apr 2025"
    public static function formatDate(string $datetime, string $format = 'd M Y'): string
    {
        try {
            return (new DateTime($datetime))->format($format);
        } catch (Exception) {
            return $datetime;
        }
    }

    // Time ago: "2025-04-10 00:00:00" → "7 days ago"
    public static function timeAgo(string $datetime): string
    {
        try {
            $diff = (new DateTime())->diff(new DateTime($datetime));
        } catch (Exception) {
            return '';
        }
        return match (true) {
            $diff->y > 0 => $diff->y . ' year'  . ($diff->y > 1 ? 's' : '') . ' ago',
            $diff->m > 0 => $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago',
            $diff->d > 0 => $diff->d . ' day'   . ($diff->d > 1 ? 's' : '') . ' ago',
            $diff->h > 0 => $diff->h . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago',
            default      => 'just now',
        };
    }

    // Output-escape a string for safe HTML display
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Check if current URL matches a controller (for nav active state)
    public static function isActive(string $controller): string
    {
        $current = Seven::app()->router->getController();
        return $current === $controller ? 'active' : '';
    }

    // Pluralize an English word (basic)
    public static function plural(int $count, string $singular, string $plural): string
    {
        return $count === 1 ? $singular : $plural;
    }

    // Paginate an array (returns slice + meta)
    public static function paginate(array $items, int $page = 1, int $perPage = 10): array
    {
        $total   = count($items);
        $pages   = (int)ceil($total / $perPage);
        $page    = max(1, min($page, $pages ?: 1));
        $offset  = ($page - 1) * $perPage;
        return [
            'items'   => array_slice($items, $offset, $perPage),
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'perPage' => $perPage,
        ];
    }
}
