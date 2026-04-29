<?php

defined('_SEVEN') or die('No direct script access allowed');

class SpacerBlock extends BlockType
{
    public function key(): string   { return 'spacer'; }
    public function label(): string { return 'Spacer'; }
    public function group(): string { return 'Layout'; }
    public function icon(): string  { return '↕'; }

    public function schema(): array
    {
        return [['key' => 'height', 'label' => 'Height (rem)', 'field_type' => 'number']];
    }

    public function render(array $data, string $children, array $context): string
    {
        $h = max(0.25, (float)($data['height'] ?? 2));
        return "<div class=\"pb-spacer\" aria-hidden=\"true\" style=\"height:{$h}rem;\"></div>";
    }
}
