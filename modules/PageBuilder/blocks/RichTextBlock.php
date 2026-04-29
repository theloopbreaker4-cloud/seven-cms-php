<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class RichTextBlock extends BlockType
{
    public function key(): string   { return 'richtext'; }
    public function label(): string { return 'Rich text'; }
    public function group(): string { return 'Content'; }
    public function icon(): string  { return '✏️'; }

    public function schema(): array
    {
        return [['key' => 'html', 'label' => 'Content', 'field_type' => 'richtext']];
    }

    public function render(array $data, string $children, array $context): string
    {
        // Rich text is trusted — admins authored it. Sanitization should happen
        // on save in the editor (TinyMCE / TipTap). Here we trust the stored HTML.
        $html = (string)($data['html'] ?? '');
        return "<div class=\"pb-richtext\" style=\"max-width:48rem;margin:0 auto;padding:1rem;line-height:1.7;\">{$html}</div>";
    }
}
