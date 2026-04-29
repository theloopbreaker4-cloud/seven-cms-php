<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class ColumnsBlock extends BlockType
{
    public function key(): string       { return 'columns'; }
    public function label(): string     { return 'Columns'; }
    public function group(): string     { return 'Layout'; }
    public function icon(): string      { return '🟰'; }
    public function isContainer(): bool { return true; }
    public function slots(): array      { return ['col1', 'col2', 'col3', 'col4']; }

    public function schema(): array
    {
        return [
            ['key' => 'count', 'label' => 'Number of columns', 'field_type' => 'select',
             'settings' => ['options' => [
                 ['value' => '2', 'label' => '2'],
                 ['value' => '3', 'label' => '3'],
                 ['value' => '4', 'label' => '4'],
             ]]],
            ['key' => 'gap',  'label' => 'Gap (rem)', 'field_type' => 'number'],
        ];
    }

    public function render(array $data, string $children, array $context): string
    {
        $count = (int)($data['count'] ?? 2);
        $gap   = (float)($data['gap']   ?? 1.5);
        return "<div class=\"pb-columns\" style=\"display:grid;grid-template-columns:repeat({$count}, minmax(0, 1fr));gap:{$gap}rem;max-width:72rem;margin:0 auto;padding:1rem;\">{$children}</div>";
    }
}
