<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class CtaBlock extends BlockType
{
    public function key(): string   { return 'cta'; }
    public function label(): string { return 'Call to action'; }
    public function group(): string { return 'Content'; }
    public function icon(): string  { return '🎯'; }

    public function schema(): array
    {
        return [
            ['key' => 'title',  'label' => 'Title',  'field_type' => 'text'],
            ['key' => 'body',   'label' => 'Body',   'field_type' => 'text'],
            ['key' => 'label',  'label' => 'Button label', 'field_type' => 'text'],
            ['key' => 'url',    'label' => 'Button URL',   'field_type' => 'url'],
            ['key' => 'variant','label' => 'Variant', 'field_type' => 'select',
             'settings' => ['options' => [
                 ['value' => 'primary',   'label' => 'Primary'],
                 ['value' => 'outline',   'label' => 'Outline'],
                 ['value' => 'subtle',    'label' => 'Subtle'],
             ]]],
        ];
    }

    public function render(array $data, string $children, array $context): string
    {
        $title = htmlspecialchars((string)($data['title'] ?? ''));
        $body  = htmlspecialchars((string)($data['body']  ?? ''));
        $label = htmlspecialchars((string)($data['label'] ?? ''));
        $url   = htmlspecialchars((string)($data['url']   ?? '#'));
        $variant = (string)($data['variant'] ?? 'primary');

        $btnStyle = match ($variant) {
            'outline' => 'border:1px solid currentColor;',
            'subtle'  => 'background:rgba(102,126,234,.15);color:#667eea;',
            default   => 'background:#667eea;color:#fff;',
        };
        return <<<HTML
<section class="pb-cta-section" style="text-align:center;padding:3rem 1rem;background:rgba(102,126,234,.05);">
  <h2 style="font-size:1.5rem;font-weight:700;">{$title}</h2>
  <p style="margin:.5rem 0 1.5rem;opacity:.8;">{$body}</p>
  <a href="{$url}" style="display:inline-block;padding:.75rem 1.5rem;border-radius:.5rem;text-decoration:none;{$btnStyle}">{$label}</a>
</section>
HTML;
    }
}
