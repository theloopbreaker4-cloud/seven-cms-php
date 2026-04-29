<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Block — single row in `pb_blocks`.
 *
 * Persists a tree of blocks scoped to (entity_type, entity_id). The `data`
 * column holds per-block JSON; the `block_type` column matches a key in the
 * BlockTypes registry.
 */
class Block extends Model
{
    public ?int    $id          = null;
    public string  $entityType  = 'page';
    public ?int    $entityId    = null;
    public ?int    $parentId    = null;
    public string  $blockType   = '';
    public string  $data        = '{}';
    public int     $sortOrder   = 0;
    public int     $isVisible   = 1;
    public string  $slot        = 'default';
    public ?string $createdAt   = null;
    public ?string $updatedAt   = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function dataArray(): array
    {
        return json_decode($this->data ?: '{}', true) ?: [];
    }

    /** Fetch all blocks for an entity, ordered for tree assembly. */
    public static function tree(string $entityType, int $entityId): array
    {
        return DB::getAll(
            'SELECT * FROM pb_blocks
              WHERE entity_type = :et AND entity_id = :ei
              ORDER BY parent_id IS NULL DESC, parent_id ASC, sort_order ASC, id ASC',
            [':et' => $entityType, ':ei' => $entityId]
        ) ?: [];
    }

    /**
     * Persist a tree of blocks. The input is a flat array where each block
     * has an "id" (optional, for existing rows), "block_type", "data", "slot",
     * "sort_order", and "parent_uid"/"uid" for client-side parent links when
     * blocks haven't been assigned a DB id yet.
     *
     * Steps:
     *   1. Wipe any blocks that aren't in the input list.
     *   2. Insert/update each row.
     *   3. Resolve parent_uid → parent_id after all inserts.
     */
    public static function saveTree(string $entityType, int $entityId, array $blocks): void
    {
        $existingIds = array_column(
            DB::getAll('SELECT id FROM pb_blocks WHERE entity_type = :et AND entity_id = :ei',
                [':et' => $entityType, ':ei' => $entityId]) ?: [],
            'id'
        );
        $keepIds = [];
        $uidToId = []; // client-side uid -> persisted id

        foreach ($blocks as $b) {
            $id = isset($b['id']) ? (int)$b['id'] : 0;
            $payload = [
                ':et'   => $entityType,
                ':ei'   => $entityId,
                ':bt'   => (string)($b['block_type'] ?? ''),
                ':d'    => is_array($b['data'] ?? null)
                              ? json_encode($b['data'], JSON_UNESCAPED_UNICODE)
                              : (string)($b['data'] ?? '{}'),
                ':so'   => (int)($b['sort_order'] ?? 0),
                ':iv'   => isset($b['is_visible']) ? (int)$b['is_visible'] : 1,
                ':sl'   => (string)($b['slot'] ?? 'default'),
            ];
            if ($id && in_array($id, $existingIds, true)) {
                DB::execute(
                    'UPDATE pb_blocks SET block_type = :bt, data = :d, sort_order = :so, is_visible = :iv, slot = :sl WHERE id = :id',
                    array_merge($payload, [':id' => $id])
                );
                $keepIds[] = $id;
            } else {
                DB::execute(
                    'INSERT INTO pb_blocks (entity_type, entity_id, block_type, data, sort_order, is_visible, slot)
                     VALUES (:et, :ei, :bt, :d, :so, :iv, :sl)',
                    $payload
                );
                $id = (int)DB::lastInsertId();
                $keepIds[] = $id;
            }
            if (!empty($b['uid'])) $uidToId[(string)$b['uid']] = $id;
        }

        // Resolve parent_uid → parent_id
        foreach ($blocks as $b) {
            $id = $uidToId[(string)($b['uid'] ?? '')] ?? (int)($b['id'] ?? 0);
            if (!$id) continue;
            $parentId = null;
            if (!empty($b['parent_uid']) && isset($uidToId[(string)$b['parent_uid']])) {
                $parentId = $uidToId[(string)$b['parent_uid']];
            } elseif (!empty($b['parent_id'])) {
                $parentId = (int)$b['parent_id'];
            }
            DB::execute('UPDATE pb_blocks SET parent_id = :p WHERE id = :id',
                [':p' => $parentId, ':id' => $id]);
        }

        // Remove deleted rows
        $toDelete = array_diff($existingIds, $keepIds);
        foreach ($toDelete as $id) {
            DB::execute('DELETE FROM pb_blocks WHERE id = :id', [':id' => (int)$id]);
        }
    }
}
