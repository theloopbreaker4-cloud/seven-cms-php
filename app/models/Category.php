<?php

defined('_SEVEN') or die('No direct script access allowed');

class Category extends Model
{
    public ?int    $id         = null;
    public ?int    $parentId   = null;  // null = root category
    public string  $slug       = '';
    public string  $name       = '{}';  // {"en":"...","ru":"..."}
    public string  $description = '{}';
    public string  $type       = 'post'; // 'post' | 'page'
    public int     $sortOrder  = 0;
    public ?string $createdAt  = null;
    public ?string $updatedAt  = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function findBySlug(string $slug): bool {
        $row = DB::findOne($this->tableName, ' slug = :slug ', [':slug' => $slug]);
        if ($row) { $this->setModel($row); return true; }
        return false;
    }

    public function getByType(string $type = 'post'): array {
        return DB::find($this->tableName, ' type = :type ORDER BY sort_order ASC, id ASC ', [':type' => $type]) ?: [];
    }

    public function getChildren(int $parentId): array {
        return DB::find($this->tableName, ' parent_id = :pid ORDER BY sort_order ASC ', [':pid' => $parentId]) ?: [];
    }

    public function t(string $field, string $lang = 'en'): string {
        $data = json_decode($this->$field ?? '{}', true);
        return $data[$lang] ?? $data['en'] ?? '';
    }

    public function save($editId = null, $prop = null): ?int {
        $this->updatedAt = date('Y-m-d H:i:s');
        if (!$this->createdAt) $this->createdAt = $this->updatedAt;
        return parent::save($editId, $prop);
    }

    public function toArray(): array {
        return [
            'id'          => $this->id,
            'parentId'    => $this->parentId,
            'slug'        => $this->slug,
            'name'        => json_decode($this->name, true) ?: [],
            'description' => json_decode($this->description, true) ?: [],
            'type'        => $this->type,
            'sortOrder'   => $this->sortOrder,
        ];
    }
}
