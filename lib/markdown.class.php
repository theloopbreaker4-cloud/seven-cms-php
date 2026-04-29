<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Markdown — minimal Markdown→HTML renderer with no external dependency.
 *
 * Supports: headings (#–######), paragraphs, bold/italic, inline code, fenced
 * code blocks (```lang), unordered/ordered lists, blockquotes, hr, links,
 * images, tables (GFM), and the Setext h1/h2 form. This is enough for our
 * docs/*.md.
 *
 * Output is escaped for HTML except inside fenced code blocks where the
 * original characters are preserved.
 *
 *   Markdown::render(file_get_contents('docs/index.md'));
 *
 * If you'd rather use a real library, install one and swap by binding
 * `markdown.renderer` in the container.
 */
class Markdown
{
    public static function render(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Fenced code blocks first — they should not be parsed for other rules.
        $codeBlocks = [];
        $text = preg_replace_callback(
            '/```([a-zA-Z0-9_+-]*)\n(.*?)\n```/s',
            function ($m) use (&$codeBlocks) {
                $idx = count($codeBlocks);
                $lang = htmlspecialchars((string)$m[1]);
                $body = htmlspecialchars((string)$m[2]);
                $codeBlocks[$idx] = "<pre class=\"md-code\"><code data-lang=\"{$lang}\">{$body}</code></pre>";
                return "\x01CODE{$idx}\x01";
            },
            $text
        ) ?? $text;

        // Block-level pass.
        $blocks = preg_split('/\n{2,}/', trim($text)) ?: [];
        $html = '';
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;
            if (preg_match('/^\x01CODE\d+\x01$/', $block)) { $html .= $block . "\n"; continue; }
            $html .= self::renderBlock($block) . "\n";
        }

        // Restore code blocks.
        $html = preg_replace_callback('/\x01CODE(\d+)\x01/', fn($m) => $codeBlocks[(int)$m[1]] ?? '', $html);

        return $html;
    }

    private static function renderBlock(string $block): string
    {
        // ATX headings
        if (preg_match('/^(#{1,6})\s+(.+?)\s*#*$/m', $block, $m) && substr_count($block, "\n") === 0) {
            $level = strlen($m[1]);
            $body  = self::renderInline($m[2]);
            $id    = self::slug(strip_tags($body));
            return "<h{$level} id=\"{$id}\">{$body}</h{$level}>";
        }

        // Setext h1/h2
        if (preg_match('/^(.+?)\n(=+|-+)$/s', $block, $m)) {
            $level = $m[2][0] === '=' ? 1 : 2;
            $body  = self::renderInline($m[1]);
            $id    = self::slug(strip_tags($body));
            return "<h{$level} id=\"{$id}\">{$body}</h{$level}>";
        }

        // Horizontal rule
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $block)) return '<hr />';

        // Blockquote
        if ($block[0] === '>') {
            $inner = preg_replace('/^>\s?/m', '', $block);
            return '<blockquote>' . self::renderInline((string)$inner) . '</blockquote>';
        }

        // Unordered list
        if (preg_match('/^(\s*[-*+]\s+)/m', $block)) {
            $items = preg_split('/^\s*[-*+]\s+/m', $block, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return '<ul>' . implode('', array_map(
                fn($i) => '<li>' . self::renderInline(trim($i)) . '</li>',
                $items
            )) . '</ul>';
        }

        // Ordered list
        if (preg_match('/^\s*\d+\.\s+/m', $block)) {
            $items = preg_split('/^\s*\d+\.\s+/m', $block, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return '<ol>' . implode('', array_map(
                fn($i) => '<li>' . self::renderInline(trim($i)) . '</li>',
                $items
            )) . '</ol>';
        }

        // GFM table
        if (preg_match('/\|.+\|\n\|[\s\-:|]+\|\n/', $block)) {
            return self::renderTable($block);
        }

        // Plain paragraph
        return '<p>' . self::renderInline($block) . '</p>';
    }

    private static function renderTable(string $block): string
    {
        $lines = explode("\n", trim($block));
        if (count($lines) < 2) return '<p>' . self::renderInline($block) . '</p>';
        $header = self::splitRow($lines[0]);
        array_shift($lines); array_shift($lines); // drop header + separator
        $rows = $lines;

        $thead = '<tr>' . implode('', array_map(
            fn($c) => '<th>' . self::renderInline($c) . '</th>', $header
        )) . '</tr>';

        $tbody = '';
        foreach ($rows as $row) {
            if (trim($row) === '') continue;
            $cells = self::splitRow($row);
            $tbody .= '<tr>' . implode('', array_map(
                fn($c) => '<td>' . self::renderInline($c) . '</td>', $cells
            )) . '</tr>';
        }

        return "<table class=\"md-table\"><thead>{$thead}</thead><tbody>{$tbody}</tbody></table>";
    }

    private static function splitRow(string $line): array
    {
        $line = trim($line, " |");
        return array_map('trim', explode('|', $line));
    }

    private static function renderInline(string $text): string
    {
        // Escape what isn't already-encoded HTML from heading id.
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // Inline code
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text) ?? $text;

        // Bold + italic
        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/\*(.+?)\*/s',     '<em>$1</em>',         $text) ?? $text;
        $text = preg_replace('/__(.+?)__/s',     '<strong>$1</strong>', $text) ?? $text;
        $text = preg_replace('/_(.+?)_/s',       '<em>$1</em>',         $text) ?? $text;

        // Images:  ![alt](url)
        $text = preg_replace('/!\[(.*?)\]\((.+?)\)/', '<img src="$2" alt="$1" />', $text) ?? $text;

        // Links:   [label](url)
        $text = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function ($m) {
            $label = $m[1];
            $url   = $m[2];
            // Convert local .md links to admin help links.
            if (preg_match('/\.md(#.+)?$/', $url)) {
                $url = '#mdlink#' . $url . '#';
            }
            return '<a href="' . $url . '">' . $label . '</a>';
        }, $text) ?? $text;

        // Line breaks within paragraph
        return nl2br($text, false);
    }

    private static function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('~[^\pL\d]+~u', '-', $s) ?? $s;
        $s = trim((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$s), '-');
        return strtolower(preg_replace('~[^-a-z0-9]+~i', '', (string)$s));
    }
}
