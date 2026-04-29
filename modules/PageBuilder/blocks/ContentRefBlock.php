<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ContentRefBlock — embed a list of CCT entries (recipes, FAQs, …).
 *
 * The block accepts a content type slug and a limit. Renders a simple
 * card list using the entry's `title` data field if present.
 */
class ContentRefBlock extends BlockType
{
    public function key(): string   { return 'content_ref'; }
    public function label(): string { return 'Content list'; }
    public function group(): string { return 'Content'; }
    public function icon(): string  { return '📚'; }

    public function schema(): array
    {
        return [
            ['key' => 'type_slug', 'label' => 'Content type slug', 'field_type' => 'text'],
            ['key' => 'limit',     'label' => 'Limit',             'field_type' => 'number'],
            ['key' => 'columns',   'label' => 'Columns',           'field_type' => 'number'],
        ];
    }

    public function render(array $data, string $children, array $context): string
    {
        if (!class_exists('ContentEntry') || !class_exists('ContentType')) return '';

        $type = ContentType::findBySlug((string)($data['type_slug'] ?? ''));
        if (!$type) return '<div class="pb-empty" style="opacity:.6;text-align:center;padding:2rem;">Type not found.</div>';

        $rows = ContentEntry::listByType((int)$type->id, [
            'status' => 'published',
            'limit'  => max(1, min(50, (int)($data['limit'] ?? 12))),
        ]);
        $cols = max(1, min(6, (int)($data['columns'] ?? 3)));
        $lang = (string)($context['locale'] ?? 'en');

        $cards = [];
        foreach ($rows as $r) {
            $entry = new ContentEntry($r);
            $title = '';
            $d = $entry->dataArray();
            if (!empty($d['title']) && is_string($d['title'])) $title = htmlspecialchars($d['title']);
            elseif (!empty($d['title']) && is_array($d['title'])) $title = htmlspecialchars((string)($d['title'][$lang] ?? array_values($d['title'])[0] ?? ''));
            else $title = htmlspecialchars($entry->slug);
            $url = '/' . $lang . '/' . $type->slug . '/' . htmlspecialchars($entry->slug);
            $cards[] = "<a href=\"{$url}\" class=\"pb-content-card\" style=\"display:block;border:1px solid rgba(0,0,0,.08);border-radius:.5rem;padding:1rem;text-decoration:none;color:inherit;\"><strong>{$title}</strong></a>";
        }

        return "<section class=\"pb-content-list\" style=\"max-width:72rem;margin:0 auto;padding:1.5rem 1rem;\">"
             . "<div style=\"display:grid;grid-template-columns:repeat({$cols}, minmax(0, 1fr));gap:1rem;\">"
             . implode("\n", $cards) . '</div></section>';
    }
}
