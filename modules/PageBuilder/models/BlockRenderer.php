<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * BlockRenderer — turns a stored block tree into HTML.
 *
 *   echo BlockRenderer::render('page', $pageId, ['locale' => $lang]);
 *
 * Loads all rows for the entity, builds the tree by parent_id, then walks
 * recursively. For container blocks, child HTML is concatenated and passed
 * into the type's `render($data, $children, $context)` method.
 *
 * Image fields are auto-resolved: a block whose schema declares an
 * `image` / `media` field may contain a media id; the renderer fetches the
 * matching `media` row and exposes `<key>_url` in the data array.
 */
class BlockRenderer
{
    public static function render(string $entityType, int $entityId, array $context = []): string
    {
        $rows = Block::tree($entityType, $entityId);
        if (!$rows) return '';

        // Index by parent
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[$r['parent_id'] ?? 0][] = $r;
        }

        return self::renderChildren($byParent, 0, $context);
    }

    private static function renderChildren(array $byParent, int $parentId, array $context): string
    {
        if (empty($byParent[$parentId])) return '';
        usort($byParent[$parentId], fn($a, $b) => (int)$a['sort_order'] <=> (int)$b['sort_order']);

        $out = '';
        foreach ($byParent[$parentId] as $row) {
            if (!(int)$row['is_visible']) continue;

            $type = BlockTypes::get((string)$row['block_type']);
            if (!$type) {
                $out .= "<!-- unknown block: " . htmlspecialchars($row['block_type']) . " -->";
                continue;
            }

            $data = json_decode((string)$row['data'], true) ?: [];
            self::resolveMediaUrls($data, $type->schema());

            $children = '';
            if ($type->isContainer()) {
                $children = self::renderChildren($byParent, (int)$row['id'], $context);
            }

            $out .= $type->render($data, $children, $context);
        }
        return $out;
    }

    /**
     * For every field of type 'image'/'media' in the block's schema, look up
     * the media row by id and add a sibling `<key>_url` value to the data
     * passed to render().
     */
    private static function resolveMediaUrls(array &$data, array $schema): void
    {
        foreach ($schema as $f) {
            if (!in_array($f['field_type'] ?? '', ['image', 'media'], true)) continue;
            $key = $f['key'];
            if (empty($data[$key])) continue;
            $mediaId = (int)$data[$key];
            if (!$mediaId) continue;
            $row = DB::findOne('media', ' id = :id ', [':id' => $mediaId]);
            if ($row) $data[$key . '_url'] = (string)$row['path'];
        }
    }
}
