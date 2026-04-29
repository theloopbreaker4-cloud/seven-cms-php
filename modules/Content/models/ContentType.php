<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ContentType — definition of a custom content type.
 *
 * A content type has a slug ("recipe"), a name ("Recipe"), and a list of fields
 * (see ContentField). Entries of this type live in `content_entries` with the
 * type-specific values stored in the `data` JSON column.
 */
class ContentType extends Model
{
    public ?int    $id              = null;
    public string  $slug            = '';
    public string  $name            = '';
    public ?string $description     = null;
    public string  $icon            = 'box';
    public int     $isSingleton     = 0;
    public int     $enableRevisions = 1;
    public int     $enableDrafts    = 1;
    public int     $isActive        = 1;
    public ?string $createdAt       = null;
    public ?string $updatedAt       = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    /** Find by slug. */
    public static function findBySlug(string $slug): ?ContentType
    {
        $row = DB::findOne('content_types', ' slug = :s ', [':s' => $slug]);
        return $row ? new self($row) : null;
    }

    public static function all(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM content_types';
        if ($activeOnly) $sql .= ' WHERE is_active = 1';
        $sql .= ' ORDER BY name ASC';
        return DB::getAll($sql) ?: [];
    }

    public function fields(): array
    {
        if (!$this->id) return [];
        return DB::getAll(
            'SELECT * FROM content_fields WHERE type_id = :t ORDER BY sort_order ASC, id ASC',
            [':t' => $this->id]
        ) ?: [];
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'slug'            => $this->slug,
            'name'            => $this->name,
            'description'     => $this->description,
            'icon'            => $this->icon,
            'isSingleton'     => (bool)$this->isSingleton,
            'enableRevisions' => (bool)$this->enableRevisions,
            'enableDrafts'    => (bool)$this->enableDrafts,
            'isActive'        => (bool)$this->isActive,
            'createdAt'       => $this->createdAt,
            'updatedAt'       => $this->updatedAt,
        ];
    }

    public static function slugify(string $value): string
    {
        $slug = preg_replace('~[^\pL\d]+~u', '-', $value);
        $slug = trim((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$slug), '-');
        $slug = strtolower(preg_replace('~[^-a-z0-9_]+~i', '', (string)$slug));
        return $slug ?: 'type-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }
}
