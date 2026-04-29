<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class HtmlBlock extends BlockType
{
    public function key(): string   { return 'html'; }
    public function label(): string { return 'Raw HTML'; }
    public function group(): string { return 'Advanced'; }
    public function icon(): string  { return '<>'; }

    public function schema(): array
    {
        return [['key' => 'html', 'label' => 'HTML', 'field_type' => 'json']];
    }

    public function render(array $data, string $children, array $context): string
    {
        // Trusted — only admins can edit blocks. Sanitize on save if your
        // editor opens this to non-admins.
        return (string)($data['html'] ?? '');
    }
}
