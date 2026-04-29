<?php

defined('_SEVEN') or die('No direct script access allowed');

class Tag extends Model
{
    public ?int    $id        = null;
    public string  $slug      = '';
    public string  $name      = '{}';
    public ?string $createdAt = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function findBySlug(string $slug): bool {
        $row = DB::findOne($this->tableName, ' slug = :slug ', [':slug' => $slug]);
        if ($row) { $this->setModel($row); return true; }
        return false;
    }

    public function getAll(): array {
        return DB::find($this->tableName, ' 1 ORDER BY slug ASC ') ?: [];
    }

    public function forPost(int $postId): array {
        return DB::getAll(
            'SELECT t.* FROM `tag` t
             INNER JOIN `posttag` pt ON pt.tag_id = t.id
             WHERE pt.post_id = :pid ORDER BY t.slug ASC',
            [':pid' => $postId]
        ) ?: [];
    }

    public function syncForPost(int $postId, array $tagIds): void {
        DB::exec('DELETE FROM `posttag` WHERE post_id = :pid', [':pid' => $postId]);
        foreach (array_unique(array_filter($tagIds, 'is_numeric')) as $tid) {
            $pivot = DB::dispense('posttag');
            $pivot->post_id = $postId;
            $pivot->tag_id  = (int)$tid;
            DB::store($pivot);
        }
    }

    public function t(string $lang = 'en'): string {
        $data = json_decode($this->name ?? '{}', true);
        return $data[$lang] ?? $data['en'] ?? $this->slug;
    }

    public function save($editId = null, $prop = null): ?int {
        $this->createdAt = $this->createdAt ?: date('Y-m-d H:i:s');
        return parent::save($editId, $prop);
    }

    public function toArray(): array {
        return [
            'id'   => $this->id,
            'slug' => $this->slug,
            'name' => json_decode($this->name, true) ?: [],
        ];
    }
}
