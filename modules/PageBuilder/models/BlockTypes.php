<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * BlockTypes — registry of available block types.
 *
 *   BlockTypes::register(new HeroBlock());
 *   $type = BlockTypes::get('hero');
 *   $catalog = BlockTypes::catalog();    // for the admin picker
 */
class BlockTypes
{
    /** @var array<string, BlockType> */
    private static array $types = [];

    public static function register(BlockType $type): void
    {
        self::$types[$type->key()] = $type;
    }

    public static function get(string $key): ?BlockType
    {
        return self::$types[$key] ?? null;
    }

    /** @return array<string, BlockType> */
    public static function all(): array
    {
        ksort(self::$types);
        return self::$types;
    }

    public static function catalog(): array
    {
        $out = [];
        foreach (self::all() as $type) {
            $out[$type->group()][] = [
                'key'   => $type->key(),
                'label' => $type->label(),
                'icon'  => $type->icon(),
                'isContainer' => $type->isContainer(),
                'slots' => $type->slots(),
                'schema'=> $type->schema(),
            ];
        }
        return $out;
    }

    public static function bootDefaults(): void
    {
        require_once __DIR__ . '/../blocks/HeroBlock.php';
        require_once __DIR__ . '/../blocks/RichTextBlock.php';
        require_once __DIR__ . '/../blocks/ColumnsBlock.php';
        require_once __DIR__ . '/../blocks/ImageBlock.php';
        require_once __DIR__ . '/../blocks/CtaBlock.php';
        require_once __DIR__ . '/../blocks/HtmlBlock.php';
        require_once __DIR__ . '/../blocks/ProductsBlock.php';
        require_once __DIR__ . '/../blocks/ContentRefBlock.php';
        require_once __DIR__ . '/../blocks/SpacerBlock.php';

        self::register(new HeroBlock());
        self::register(new RichTextBlock());
        self::register(new ColumnsBlock());
        self::register(new ImageBlock());
        self::register(new CtaBlock());
        self::register(new HtmlBlock());
        self::register(new ProductsBlock());
        self::register(new ContentRefBlock());
        self::register(new SpacerBlock());

        // Plugins can extend the catalog.
        if (class_exists('Event')) Event::dispatch('pagebuilder.blocks', null);
    }
}
