<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * HtmlSanitizer — allowlist cleaner for editor-authored rich text.
 *
 *   HtmlSanitizer::clean($html)  // safe to echo raw
 *
 * Disallowed elements are unwrapped (their text survives); script-like
 * elements are dropped with their content. Only http(s), mailto, tel and
 * relative URLs are kept. Plain text without tags gets <p>/<br> so line
 * breaks typed in a textarea still show.
 */
class HtmlSanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [],
        'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
        'ul' => [], 'ol' => [], 'li' => [],
        'blockquote' => [], 'code' => [], 'pre' => [],
        'a'   => ['href', 'title'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => [], 'td' => [],
    ];

    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'form'];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';
        if ($html === strip_tags($html)) {
            return self::paragraphs($html);
        }

        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if (!$root) return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        self::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) $out .= $doc->saveHTML($child);
        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof DOMElement) continue;

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            self::walk($child);

            if (!isset(self::ALLOWED[$tag])) {
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }

            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (!in_array($name, self::ALLOWED[$tag], true)
                    || (in_array($name, ['href', 'src'], true) && !self::safeUrl($attr->value))) {
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href') && preg_match('~^https?://~i', $child->getAttribute('href'))) {
                $child->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');
        if ($url === '' || str_starts_with($url, '//')) return false;
        if (preg_match('~^(https?:|mailto:|tel:)~i', $url)) return true;
        return !preg_match('~^[a-z][a-z0-9+.-]*:~i', $url); // relative paths and #anchors
    }

    private static function paragraphs(string $text): string
    {
        $blocks = preg_split('/\R{2,}/', $text) ?: [];
        $html = '';
        foreach ($blocks as $b) {
            $b = trim($b);
            if ($b === '') continue;
            $html .= '<p>' . nl2br(htmlspecialchars($b, ENT_QUOTES, 'UTF-8'), false) . '</p>';
        }
        return $html;
    }
}
