<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * BlockType — describes one kind of block in the page builder.
 *
 * Subclasses implement `render($data, $children, $context)` to output HTML
 * and `schema()` to declare which fields the admin UI shows when editing
 * this block. The schema mirrors `ContentField` shapes:
 *
 *   ['key' => 'title', 'label' => 'Title', 'field_type' => 'text']
 *
 * Supported field_type values:
 *   text, richtext, number, boolean, image, media, select,
 *   multiselect, color, url, json, repeater, productPicker, contentPicker
 */
abstract class BlockType
{
    /** Unique key used to identify the block in storage. */
    abstract public function key(): string;

    /** Human-readable label shown in the picker. */
    abstract public function label(): string;

    /** Group label used to bucket blocks in the picker (e.g. "Layout", "Content"). */
    public function group(): string { return 'Content'; }

    /** Emoji or short string for the picker. */
    public function icon(): string { return '🧱'; }

    /** Whether this block can have children blocks dropped into it. */
    public function isContainer(): bool { return false; }

    /** Slot names accepted when the block is a container. */
    public function slots(): array { return ['default']; }

    /**
     * Field schema for the editor.
     *
     * @return array<int, array{key:string,label:string,field_type:string,settings?:array}>
     */
    public function schema(): array { return []; }

    /**
     * Render the block to HTML.
     *
     * @param array  $data     The persisted JSON for this block.
     * @param string $children Already-rendered child HTML (for containers).
     * @param array  $context  Site-wide info (lang, site, …).
     */
    abstract public function render(array $data, string $children, array $context): string;
}
