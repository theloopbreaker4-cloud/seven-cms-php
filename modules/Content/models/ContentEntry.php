<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ContentEntry — a single row of user content for a given ContentType.
 *
 * Storage model:
 *   - Indexed columns: id, type_id, slug, status, locale, sort_order, author_id, published_at
 *   - All typed values: stored in `data` JSON column (one key per ContentField.key)
 *
 * Relations are kept in `content_relations` and resolved separately by ContentRelationStore.
 */
class ContentEntry extends Model
{
    public ?int    $id          = null;
    public ?int    $typeId      = null;
    public string  $slug        = '';
    public string  $status      = 'draft';
    public string  $locale      = 'en';
    public int     $sortOrder   = 0;
    public string  $data        = '{}';
    public ?int    $authorId    = null;
    public ?string $publishedAt = null;
    public ?string $createdAt   = null;
    public ?string $updatedAt   = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function dataArray(): array
    {
        $arr = json_decode($this->data ?: '{}', true);
        return is_array($arr) ? $arr : [];
    }

    /**
     * List entries of a content type with optional filters.
     *
     * @param array{status?:string,locale?:string,limit?:int,offset?:int,q?:string} $opts
     */
    public static function listByType(int $typeId, array $opts = []): array
    {
        $where = ['type_id = :t'];
        $args  = [':t' => $typeId];

        if (!empty($opts['status'])) { $where[] = 'status = :s'; $args[':s'] = $opts['status']; }
        if (!empty($opts['locale'])) { $where[] = 'locale = :l'; $args[':l'] = $opts['locale']; }
        if (!empty($opts['q']))      { $where[] = '(slug LIKE :q OR JSON_SEARCH(data, "one", :q) IS NOT NULL)'; $args[':q'] = '%' . $opts['q'] . '%'; }

        $limit  = (int)($opts['limit']  ?? 50);
        $offset = (int)($opts['offset'] ?? 0);
        $sql = 'SELECT * FROM content_entries WHERE ' . implode(' AND ', $where)
            . ' ORDER BY sort_order ASC, id DESC LIMIT ' . max(1, min(500, $limit))
            . ' OFFSET ' . max(0, $offset);

        return DB::getAll($sql, $args) ?: [];
    }

    public static function findById(int $id): ?ContentEntry
    {
        $row = DB::findOne('content_entries', ' id = :id ', [':id' => $id]);
        return $row ? new self($row) : null;
    }

    public static function findBySlug(int $typeId, string $slug, string $locale = 'en'): ?ContentEntry
    {
        $row = DB::findOne(
            'content_entries',
            ' type_id = :t AND slug = :s AND locale = :l ',
            [':t' => $typeId, ':s' => $slug, ':l' => $locale]
        );
        return $row ? new self($row) : null;
    }

    /**
     * Create or update an entry. Validates fields, persists, fires hooks, snapshots revision.
     *
     * @param array<string,mixed> $payload  ['slug','status','locale','sort_order','data','published_at']
     */
    public static function persist(ContentType $type, array $payload, ?int $entryId = null): ContentEntry
    {
        $isUpdate = $entryId !== null;
        $entry    = $isUpdate ? self::findById($entryId) : new self();
        if ($isUpdate && !$entry) throw new InvalidArgumentException('Entry not found');

        $entry->typeId      = $type->id;
        $entry->slug        = self::buildSlug($type, (string)($payload['slug'] ?? ''), (string)($payload['data']['title'] ?? ''));
        $entry->status      = in_array(($payload['status'] ?? 'draft'), ['draft','published','archived'], true)
                                ? $payload['status'] : 'draft';
        $entry->locale      = (string)($payload['locale']    ?? 'en');
        $entry->sortOrder   = (int)   ($payload['sort_order'] ?? 0);
        $entry->publishedAt = !empty($payload['published_at']) ? (string)$payload['published_at']
                              : ($entry->status === 'published' ? date('Y-m-d H:i:s') : null);

        // Validate + cast field values.
        $rawData = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $clean   = [];
        foreach ($type->fields() as $fRow) {
            $field = new ContentField($fRow);
            $clean[$field->key] = $field->castValue($rawData[$field->key] ?? null);
        }
        $entry->data = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($isUpdate) {
            Hooks::fire(Hooks::BEFORE_UPDATE, 'content', $entry);
        } else {
            $entry->createdAt = date('Y-m-d H:i:s');
            $entry->authorId  = self::currentUserId();
            Hooks::fire(Hooks::BEFORE_CREATE, 'content', $entry);
        }

        $id = $entry->save();
        if (!$id) throw new RuntimeException('Failed to save content entry');

        // Snapshot revision if enabled.
        if ($type->enableRevisions) {
            Revisions::snapshot('content_entries', $id, [
                'slug'        => $entry->slug,
                'status'      => $entry->status,
                'locale'      => $entry->locale,
                'data'        => $clean,
                'published_at'=> $entry->publishedAt,
            ], ['type_slug' => $type->slug]);
        }

        if (class_exists('ActivityLog')) {
            ActivityLog::log(
                $isUpdate ? 'content.update' : 'content.create',
                'content_entries', $id,
                ($isUpdate ? 'Updated' : 'Created') . " {$type->slug}: {$entry->slug}"
            );
        }

        if ($isUpdate) Hooks::fire(Hooks::AFTER_UPDATE, 'content', $entry);
        else           Hooks::fire(Hooks::AFTER_CREATE, 'content', $entry);

        return $entry;
    }

    public static function deleteById(int $id): bool
    {
        $entry = self::findById($id);
        if (!$entry) return false;

        Hooks::fire(Hooks::BEFORE_DELETE, 'content', $entry);
        DB::execute('DELETE FROM content_entries WHERE id = :id', [':id' => $id]);
        if (class_exists('ActivityLog')) {
            ActivityLog::log('content.delete', 'content_entries', $id, "Deleted entry #{$id}");
        }
        Hooks::fire(Hooks::AFTER_DELETE, 'content', $entry);
        return true;
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'typeId'      => $this->typeId,
            'slug'        => $this->slug,
            'status'      => $this->status,
            'locale'      => $this->locale,
            'sortOrder'   => $this->sortOrder,
            'data'        => $this->dataArray(),
            'authorId'    => $this->authorId,
            'publishedAt' => $this->publishedAt,
            'createdAt'   => $this->createdAt,
            'updatedAt'   => $this->updatedAt,
        ];
    }

    private static function buildSlug(ContentType $type, string $explicit, string $fallback): string
    {
        $slug = trim($explicit) !== '' ? $explicit : $fallback;
        return ContentType::slugify($slug ?: ('entry-' . substr(bin2hex(random_bytes(4)), 0, 6)));
    }

    private static function currentUserId(): ?int
    {
        if (!class_exists('Auth')) return null;
        $u = Auth::getCurrentUser();
        return $u && isset($u->id) ? (int)$u->id : null;
    }
}
