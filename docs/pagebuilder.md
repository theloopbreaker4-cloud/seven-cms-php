# Page builder

[← Back to docs](index.md)

## Concept

The Page Builder lets editors compose pages from reusable **blocks** —
heroes, columns, products grids, rich text, etc. — with drag-and-drop.

Blocks are stored in `pb_blocks`, keyed by an entity (`entity_type` +
`entity_id`). Any record can have a block tree attached: a normal CMS page,
a CCT entry, or whatever you invent. The Page Builder doesn't care.

## Setup

```bash
php bin/sev plugin:install PageBuilder
```

Open `/admin/pagebuilder/edit/page/{id}` to start composing. The editor has
three columns:

```
┌──────────┬──────────────────────┬──────────────┐
│ Catalog  │     Canvas (tree)    │  Inspector   │
│  ├ Layout│  ┌─────────────────┐ │  Title  […] │
│  ├ Content│  │ Hero            │ │  CTA URL […] │
│  └ E-com │  │ ├─ Columns      │ │              │
│          │  │ │  ├─ Image     │ │              │
│          │  │ │  └─ Text      │ │              │
│          │  │ └─ CTA          │ │              │
│          │  └─────────────────┘ │              │
└──────────┴──────────────────────┴──────────────┘
```

## Built-in blocks

| Key            | Group       | Notes                                            |
|----------------|-------------|--------------------------------------------------|
| `hero`         | Layout      | Title, subtitle, image, CTA                      |
| `columns`      | Layout      | 2/3/4 columns container — drop other blocks in   |
| `spacer`       | Layout      | Configurable vertical gap                        |
| `richtext`     | Content     | WYSIWYG-friendly HTML                            |
| `image`        | Content     | Single image with caption + roundness            |
| `cta`          | Content     | Call-to-action panel                             |
| `content_ref`  | Content     | List CCT entries (e.g. all "recipe" entries)     |
| `products`     | E-commerce  | Grid of products from the Ecom plugin            |
| `html`         | Advanced    | Raw HTML escape hatch                            |

## Custom blocks

Subclass `BlockType` and register in your plugin's `boot()`:

```php
class TestimonialBlock extends BlockType
{
    public function key(): string   { return 'testimonial'; }
    public function label(): string { return 'Testimonial'; }
    public function group(): string { return 'Content'; }
    public function icon(): string  { return '💬'; }

    public function schema(): array
    {
        return [
            ['key' => 'quote',  'label' => 'Quote',   'field_type' => 'richtext'],
            ['key' => 'author', 'label' => 'Author',  'field_type' => 'text'],
            ['key' => 'avatar', 'label' => 'Avatar',  'field_type' => 'image'],
        ];
    }

    public function render(array $data, string $children, array $context): string
    {
        $q = htmlspecialchars((string)($data['quote'] ?? ''));
        $a = htmlspecialchars((string)($data['author'] ?? ''));
        $img = htmlspecialchars((string)($data['avatar_url'] ?? ''));
        return <<<HTML
<blockquote class="pb-testimonial" style="text-align:center;padding:2rem;max-width:48rem;margin:0 auto;">
  <p style="font-size:1.25rem;">{$q}</p>
  <footer style="margin-top:1rem;">
    <img src="{$img}" alt="" style="width:48px;height:48px;border-radius:50%;" />
    <cite>{$a}</cite>
  </footer>
</blockquote>
HTML;
    }
}

// In your Module.php:
public function boot(): void
{
    require_once __DIR__ . '/blocks/TestimonialBlock.php';
    BlockTypes::register(new TestimonialBlock());
}
```

The block immediately shows up in the catalog under "Content" with your
configured icon and schema-based inspector form.

## Embedding into pages

To render the block tree on a public page:

```php
// app/views/site/page.html (or wherever you render a page)
echo BlockRenderer::render('page', $page->id, ['locale' => $lang]);
```

`BlockRenderer` resolves all media ids in `image`/`media` fields to URLs
(`{key}_url`) before passing the data to each block's `render()` — so your
block code doesn't need to query `media` itself.

## Storage layout

```
pb_blocks
├── id
├── entity_type   ('page' | 'content_entries' | …)
├── entity_id
├── parent_id     (NULL = root)
├── block_type    matches BlockTypes registry key
├── data          per-block JSON
├── sort_order
├── is_visible
└── slot          for container blocks with named slots
```

## Permissions

| slug                 | Notes                                  |
|----------------------|----------------------------------------|
| `pagebuilder.view`   | Open the editor                        |
| `pagebuilder.edit`   | Add / remove / reorder blocks          |
| `pagebuilder.publish`| (reserved for future draft → live flow)|

Editor role gets all three on install.

---

[← Back to docs](index.md)
