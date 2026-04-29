<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class HeroBlock extends BlockType
{
    public function key(): string   { return 'hero'; }
    public function label(): string { return 'Hero'; }
    public function group(): string { return 'Layout'; }
    public function icon(): string  { return '🌅'; }

    public function schema(): array
    {
        return [
            ['key' => 'title',     'label' => 'Title',     'field_type' => 'text'],
            ['key' => 'subtitle',  'label' => 'Subtitle',  'field_type' => 'text'],
            ['key' => 'image',     'label' => 'Background image', 'field_type' => 'image'],
            ['key' => 'cta_label', 'label' => 'Button label', 'field_type' => 'text'],
            ['key' => 'cta_url',   'label' => 'Button URL',   'field_type' => 'url'],
            ['key' => 'align',     'label' => 'Alignment',    'field_type' => 'select',
             'settings' => ['options' => [
                 ['value' => 'left',   'label' => 'Left'],
                 ['value' => 'center', 'label' => 'Center'],
                 ['value' => 'right',  'label' => 'Right'],
             ]]],
        ];
    }

    public function render(array $data, string $children, array $context): string
    {
        $title    = htmlspecialchars((string)($data['title']    ?? ''));
        $subtitle = htmlspecialchars((string)($data['subtitle'] ?? ''));
        $cta      = htmlspecialchars((string)($data['cta_label']?? ''));
        $url      = htmlspecialchars((string)($data['cta_url']  ?? '#'));
        $align    = htmlspecialchars((string)($data['align']    ?? 'center'));
        $imgUrl   = $data['image_url'] ?? ''; // resolved by renderer

        $bg = $imgUrl ? "background-image:url('" . htmlspecialchars($imgUrl) . "');background-size:cover;background-position:center;" : '';

        $btn = $cta ? "<a href=\"{$url}\" class=\"pb-cta\" style=\"display:inline-block;margin-top:1rem;padding:.75rem 1.5rem;background:#667eea;color:#fff;border-radius:.5rem;text-decoration:none;\">{$cta}</a>" : '';

        return <<<HTML
<section class="pb-hero" style="text-align:{$align};{$bg}padding:5rem 1rem;">
  <h1 style="font-size:2.25rem;font-weight:700;margin-bottom:.5rem;">{$title}</h1>
  <p style="font-size:1.125rem;opacity:.8;">{$subtitle}</p>
  {$btn}
</section>
HTML;
    }
}
