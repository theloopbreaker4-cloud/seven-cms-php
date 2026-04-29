<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ProductsBlock — embed a list of products from the Ecom plugin.
 *
 * Filters: category id, kind (physical/digital/service), featured-only,
 * limit, sort. Renders cards with name, price, and a buy button.
 */
class ProductsBlock extends BlockType
{
    public function key(): string   { return 'products'; }
    public function label(): string { return 'Products grid'; }
    public function group(): string { return 'E-commerce'; }
    public function icon(): string  { return '🛍'; }

    public function schema(): array
    {
        return [
            ['key' => 'category_id', 'label' => 'Category id (optional)', 'field_type' => 'number'],
            ['key' => 'kind',        'label' => 'Kind', 'field_type' => 'select',
             'settings' => ['options' => [
                 ['value' => '',         'label' => 'Any'],
                 ['value' => 'physical', 'label' => 'Physical'],
                 ['value' => 'digital',  'label' => 'Digital'],
                 ['value' => 'service',  'label' => 'Service'],
             ]]],
            ['key' => 'featured', 'label' => 'Featured only', 'field_type' => 'boolean'],
            ['key' => 'limit',    'label' => 'Limit',         'field_type' => 'number'],
            ['key' => 'columns',  'label' => 'Columns',       'field_type' => 'select',
             'settings' => ['options' => [
                 ['value' => '2','label'=>'2'], ['value' => '3','label'=>'3'], ['value' => '4','label'=>'4'],
             ]]],
        ];
    }

    public function render(array $data, string $children, array $context): string
    {
        if (!class_exists('Product')) {
            return '<div class="pb-products-empty" style="text-align:center;padding:2rem;opacity:.6">Ecom plugin is not installed.</div>';
        }
        $rows = Product::listPublic([
            'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'kind'        => (string)($data['kind'] ?? ''),
            'featured'    => !empty($data['featured']),
            'limit'       => max(1, min(48, (int)($data['limit'] ?? 8))),
        ]);
        $columns = max(1, min(6, (int)($data['columns'] ?? 3)));
        $locale  = (string)($context['locale'] ?? 'en');
        $cur     = (string)(DB::getCell('SELECT value FROM settings WHERE `key` = "ecom.currency"') ?? 'USD');

        $cards = [];
        foreach ($rows as $r) {
            $p = new Product($r);
            $name  = htmlspecialchars($p->pickI18n('name', $locale));
            $price = class_exists('Money') ? Money::format((int)$p->basePrice, $cur) : (string)$p->basePrice;
            $url   = '/' . $locale . '/shop/product/' . htmlspecialchars($p->slug);
            $img   = '';
            $images = json_decode($p->images ?: '[]', true) ?: [];
            if (!empty($images[0]) && is_string($images[0])) {
                $img = "<img src=\"" . htmlspecialchars($images[0]) . "\" alt=\"\" style=\"width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:.5rem;\" />";
            }
            $cards[] = <<<HTML
<a href="{$url}" class="pb-product-card" style="display:block;text-decoration:none;color:inherit;border:1px solid rgba(0,0,0,.1);border-radius:.75rem;overflow:hidden;padding:.75rem;">
  {$img}
  <p style="font-weight:600;margin-top:.5rem;">{$name}</p>
  <p style="opacity:.8;">{$price}</p>
</a>
HTML;
        }

        $grid = implode("\n", $cards) ?: '<p style="text-align:center;opacity:.6;padding:2rem;">No products found.</p>';
        return "<section class=\"pb-products\" style=\"max-width:72rem;margin:0 auto;padding:1.5rem 1rem;\">"
             . "<div style=\"display:grid;grid-template-columns:repeat({$columns}, minmax(0, 1fr));gap:1rem;\">{$grid}</div>"
             . "</section>";
    }
}
