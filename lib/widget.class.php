<?php

defined('_SEVEN') or die('No direct script access allowed');

// Reusable HTML widget helpers for views
// Usage in templates: <?= Widget::pagination($page, $pages, $baseUrl) ?>

class Widget
{
    // Pagination links
    // $page    — current page (1-based)
    // $pages   — total pages
    // $baseUrl — base URL, page number appended as query ?page=N
    public static function pagination(int $page, int $pages, string $baseUrl): string
    {
        if ($pages <= 1) return '';

        $lang = Seven::app()->router->getLanguage();
        $html = '<nav class="flex items-center gap-1 mt-6">';

        for ($i = 1; $i <= $pages; $i++) {
            $url    = $baseUrl . '?page=' . $i;
            $active = $i === $page
                ? 'bg-[var(--primary)] text-white'
                : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)] hover:text-[var(--primary)]';
            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" '
                   . 'class="px-3 py-1 rounded text-sm ' . $active . '">' . $i . '</a>';
        }

        $html .= '</nav>';
        return $html;
    }

    // Flash / alert box
    // $type: 'success' | 'error' | 'info' | 'warning'
    public static function alert(string $message, string $type = 'info'): string
    {
        $colors = [
            'success' => 'bg-green-100 border-green-400 text-green-800',
            'error'   => 'bg-red-100 border-red-400 text-red-800',
            'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-800',
            'info'    => 'bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--text-primary)]',
        ];
        $cls = $colors[$type] ?? $colors['info'];
        return '<div class="px-4 py-3 rounded border text-sm mb-4 ' . $cls . '">'
             . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
             . '</div>';
    }

    // Badge (label pill)
    public static function badge(string $text, string $color = 'primary'): string
    {
        $cls = $color === 'primary'
            ? 'bg-[var(--primary)] text-white'
            : 'bg-[var(--bg-tertiary)] text-[var(--text-secondary)]';
        return '<span class="px-2 py-0.5 rounded-full text-xs font-medium ' . $cls . '">'
             . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    // Published/draft status badge
    public static function status(bool $isPublished): string
    {
        return $isPublished
            ? self::badge('Published', 'primary')
            : self::badge('Draft', 'secondary');
    }

    // Language tab switcher (for multilingual admin forms)
    // $langs  — array of language codes ['en','ru',...]
    // $active — current active language code
    public static function langTabs(array $langs, string $active = 'en'): string
    {
        $html = '<div class="flex gap-1 mb-4 border-b border-[var(--border-color)]">';
        foreach ($langs as $lang) {
            $cls = $lang === $active
                ? 'px-3 py-1.5 text-sm font-medium text-[var(--primary)] border-b-2 border-[var(--primary)] -mb-px'
                : 'px-3 py-1.5 text-sm text-[var(--text-secondary)] hover:text-[var(--primary)]';
            $html .= '<button type="button" data-lang="' . $lang . '" class="lang-tab ' . $cls . '">'
                   . strtoupper($lang) . '</button>';
        }
        $html .= '</div>';
        return $html;
    }

    // Empty state block (for empty lists)
    public static function empty(string $message = 'No items found.'): string
    {
        return '<div class="text-center py-16 text-[var(--text-tertiary)] text-sm">'
             . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
             . '</div>';
    }

    // Breadcrumb nav
    // $crumbs — [['label' => 'Home', 'url' => '/en/'], ['label' => 'Pages']]
    public static function breadcrumb(array $crumbs): string
    {
        $html  = '<nav class="flex items-center gap-2 text-sm text-[var(--text-tertiary)] mb-4">';
        $last  = count($crumbs) - 1;
        foreach ($crumbs as $i => $crumb) {
            if ($i === $last) {
                $html .= '<span class="text-[var(--text-primary)]">'
                       . htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($crumb['url'] ?? '#', ENT_QUOTES, 'UTF-8') . '" '
                       . 'class="hover:text-[var(--primary)]">'
                       . htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') . '</a>'
                       . '<span>/</span>';
            }
        }
        $html .= '</nav>';
        return $html;
    }
}
