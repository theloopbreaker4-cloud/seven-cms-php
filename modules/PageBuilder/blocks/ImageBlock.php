<?php

defined('_SEVEN') or die('No direct script access allowed');

class ImageBlock extends BlockType
{
    public function key(): string   { return 'image'; }
    public function label(): string { return 'Image'; }
    public function group(): string { return 'Content'; }
    public function icon(): string  { return '🖼'; }

    public function schema(): array
    {
        return [
            ['key' => 'image', 'label' => 'Image',   'field_type' => 'image'],
            ['key' => 'alt',   'label' => 'Alt text','field_type' => 'text'],
            ['key' => 'caption','label' => 'Caption','field_type' => 'text'],
            ['key' => 'width', 'label' => 'Max width (rem)', 'field_type' => 'number'],
            ['key' => 'rounded','label' => 'Rounded corners','field_type' => 'boolean'],
        ];
    }

    public function render(array $data, string $children, array $context): string
    {
        $url = htmlspecialchars((string)($data['image_url'] ?? ''));
        if ($url === '') return '';
        $alt = htmlspecialchars((string)($data['alt'] ?? ''));
        $cap = htmlspecialchars((string)($data['caption'] ?? ''));
        $w   = (float)($data['width'] ?? 48);
        $r   = !empty($data['rounded']) ? 'border-radius:1rem;' : '';
        $html = "<figure class=\"pb-image\" style=\"max-width:{$w}rem;margin:1.5rem auto;text-align:center;\">"
              . "<img src=\"{$url}\" alt=\"{$alt}\" style=\"width:100%;height:auto;{$r}\" />";
        if ($cap !== '') $html .= "<figcaption style=\"margin-top:.5rem;font-size:.875rem;opacity:.7;\">{$cap}</figcaption>";
        $html .= '</figure>';
        return $html;
    }
}
