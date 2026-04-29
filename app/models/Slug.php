<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Localized slugs table.
 * Schema: id, entity_type (page|post), entity_id, lang, slug
 * Unique index on (entity_type, lang, slug).
 */
class Slug extends Model
{
    public ?int    $id          = null;
    public string  $entityType  = '';  // 'page' | 'post'
    public int     $entityId    = 0;
    public string  $lang        = '';
    public string  $slug        = '';
    public ?string $createdAt   = null;

    public function __construct($data = [])
    {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    // Find entity_id by type + lang + slug
    public static function findEntity(string $type, string $lang, string $slug): ?int
    {
        $row = DB::findOne(
            'slug',
            ' entity_type = :t AND lang = :l AND slug = :s ',
            [':t' => $type, ':l' => $lang, ':s' => $slug]
        );
        return $row ? (int)$row->entity_id : null;
    }

    // Get all slugs for one entity as [lang => slug]
    public static function forEntity(string $type, int $entityId): array
    {
        $rows = DB::find(
            'slug',
            ' entity_type = :t AND entity_id = :id ',
            [':t' => $type, ':id' => $entityId]
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[$r->lang] = $r->slug;
        }
        return $out;
    }

    // Get slug for one entity+lang, fallback to 'en'
    public static function get(string $type, int $entityId, string $lang): string
    {
        $row = DB::findOne(
            'slug',
            ' entity_type = :t AND entity_id = :id AND lang = :l ',
            [':t' => $type, ':id' => $entityId, ':l' => $lang]
        );
        if ($row) return $row->slug;

        // Fallback to English slug
        $row = DB::findOne(
            'slug',
            ' entity_type = :t AND entity_id = :id AND lang = "en" ',
            [':t' => $type, ':id' => $entityId]
        );
        return $row ? $row->slug : '';
    }

    // Save all slugs for an entity from [lang => slug] array
    // Skips empty slugs, skips duplicates across other entities
    public static function saveForEntity(string $type, int $entityId, array $slugs): array
    {
        $errors = [];
        foreach ($slugs as $lang => $slug) {
            $slug = self::normalize($slug);
            if ($slug === '') continue;

            // Check conflict with other entities
            $conflict = DB::findOne(
                'slug',
                ' entity_type = :t AND lang = :l AND slug = :s AND entity_id != :id ',
                [':t' => $type, ':l' => $lang, ':s' => $slug, ':id' => $entityId]
            );
            if ($conflict) {
                $errors[$lang] = "Slug '{$slug}' already used by another {$type}.";
                continue;
            }

            // Upsert
            $existing = DB::findOne(
                'slug',
                ' entity_type = :t AND entity_id = :id AND lang = :l ',
                [':t' => $type, ':id' => $entityId, ':l' => $lang]
            );
            $b = $existing ?: DB::dispense('slug');
            $b->entity_type = $type;
            $b->entity_id   = $entityId;
            $b->lang        = $lang;
            $b->slug        = $slug;
            $b->created_at  = $b->created_at ?? date('Y-m-d H:i:s');
            DB::store($b);
        }
        return $errors;
    }

    // Delete all slugs for an entity (on delete)
    public static function deleteForEntity(string $type, int $entityId): void
    {
        DB::exec(
            'DELETE FROM slug WHERE entity_type = :t AND entity_id = :id',
            [':t' => $type, ':id' => $entityId]
        );
    }

    // Normalize: lowercase, trim, replace spaces/underscores with hyphens
    // Preserves unicode letters (Georgian, Cyrillic etc.)
    public static function normalize(string $input): string
    {
        $s = trim($input);
        $s = mb_strtolower($s);
        $s = preg_replace('/[\s_]+/u', '-', $s);
        $s = preg_replace('/[^\p{L}\p{N}\-]/u', '', $s);
        $s = preg_replace('/-+/', '-', $s);
        return trim($s, '-');
    }
}
