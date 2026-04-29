<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Revisions — generic snapshot store for any entity.
 *
 *   Revisions::snapshot('content_entries', 42, $payload);
 *   $list = Revisions::list('content_entries', 42, 20);
 *   $rev  = Revisions::get($revisionId);
 *   $payload = Revisions::restore($revisionId);   // returns the snapshotted data array
 */
class Revisions
{
    public static function snapshot(
        string $entityType,
        int $entityId,
        array $data,
        array $meta = [],
        ?string $comment = null,
        ?int $authorId = null
    ): int {
        if ($authorId === null && class_exists('Auth')) {
            $u = Auth::getCurrentUser();
            $authorId = $u && isset($u->id) ? (int)$u->id : null;
        }

        DB::execute(
            'INSERT INTO revisions (entity_type, entity_id, data, meta, comment, author_id)
             VALUES (:et, :ei, :d, :m, :c, :a)',
            [
                ':et' => $entityType,
                ':ei' => $entityId,
                ':d'  => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':m'  => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':c'  => $comment,
                ':a'  => $authorId,
            ]
        );
        return (int)DB::lastInsertId();
    }

    public static function list(string $entityType, int $entityId, int $limit = 20): array
    {
        return DB::getAll(
            'SELECT id, comment, author_id, created_at, JSON_EXTRACT(meta, "$") AS meta
               FROM revisions
              WHERE entity_type = :et AND entity_id = :ei
              ORDER BY id DESC
              LIMIT ' . max(1, min(200, $limit)),
            [':et' => $entityType, ':ei' => $entityId]
        ) ?: [];
    }

    public static function get(int $revisionId): ?array
    {
        return DB::findOne('revisions', ' id = :id ', [':id' => $revisionId]) ?: null;
    }

    /** @return array decoded snapshot data */
    public static function restore(int $revisionId): ?array
    {
        $row = self::get($revisionId);
        if (!$row) return null;
        $data = json_decode((string)$row['data'], true);
        return is_array($data) ? $data : null;
    }
}
