<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ContentRelationStore — read/write the `content_relations` pivot.
 *
 * Used by `relation` field type. The field's `settings.cardinality` decides the UI:
 *   - "one"  — single picker, stored as an array of one id
 *   - "many" — multi picker, stored as array of ids in order
 */
class ContentRelationStore
{
    /** Replace the relations of one field for a given entry. */
    public static function set(int $fromEntryId, string $relationKey, array $toIds): void
    {
        DB::execute(
            'DELETE FROM content_relations WHERE from_entry_id = :f AND relation_key = :k',
            [':f' => $fromEntryId, ':k' => $relationKey]
        );
        $order = 0;
        foreach (array_unique(array_map('intval', $toIds)) as $toId) {
            if ($toId <= 0) continue;
            DB::execute(
                'INSERT IGNORE INTO content_relations (from_entry_id, to_entry_id, relation_key, sort_order)
                 VALUES (:f, :t, :k, :o)',
                [':f' => $fromEntryId, ':t' => $toId, ':k' => $relationKey, ':o' => $order++]
            );
        }
    }

    /**
     * Resolve the entries pointed to by a field of an entry.
     *
     * @return array<int,array> raw rows from content_entries.
     */
    public static function resolve(int $fromEntryId, string $relationKey, int $limit = 100): array
    {
        return DB::getAll(
            'SELECT e.* FROM content_entries e
               JOIN content_relations r ON r.to_entry_id = e.id
              WHERE r.from_entry_id = :f AND r.relation_key = :k
              ORDER BY r.sort_order ASC, e.id ASC
              LIMIT ' . max(1, min(500, $limit)),
            [':f' => $fromEntryId, ':k' => $relationKey]
        ) ?: [];
    }

    /** Reverse lookup — entries that reference $toId via $relationKey. */
    public static function reverse(int $toId, string $relationKey, int $limit = 100): array
    {
        return DB::getAll(
            'SELECT e.* FROM content_entries e
               JOIN content_relations r ON r.from_entry_id = e.id
              WHERE r.to_entry_id = :t AND r.relation_key = :k
              ORDER BY r.sort_order ASC, e.id ASC
              LIMIT ' . max(1, min(500, $limit)),
            [':t' => $toId, ':k' => $relationKey]
        ) ?: [];
    }
}
