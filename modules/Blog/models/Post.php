<?php

defined('_SEVEN') or die('No direct script access allowed');

class Post extends Model
{
    public ?int    $id           = null;
    public ?int    $userId       = null;
    public ?int    $categoryId   = null;
    public ?int    $coverImageId = null;
    public string  $title       = '{}';
    public string  $excerpt     = '{}';
    public string  $content     = '{}';
    public string  $coverImage  = '';
    public string  $metaTitle   = '{}';
    public string  $metaDesc    = '{}';
    public int     $isPublished = 0;
    public int     $viewCount   = 0;
    public ?string $publishedAt = null;
    public ?string $createdAt   = null;
    public ?string $updatedAt   = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function findBySlug(string $slug, string $lang = 'en'): bool
    {
        $id = Slug::findEntity('post', $lang, $slug)
           ?? Slug::findEntity('post', 'en', $slug);
        if (!$id) return false;
        $row = DB::findOne($this->tableName, ' id = :id AND is_published = 1 ', [':id' => $id]);
        if (!$row) return false;
        $this->setModel($row);
        return true;
    }

    public function getSlugs(): array { return $this->id ? Slug::forEntity('post', $this->id) : []; }
    public function getSlug(string $lang = 'en'): string { return $this->id ? Slug::get('post', $this->id, $lang) : ''; }

    public function getPublished(int $limit = 10, int $offset = 0, ?int $categoryId = null): array {
        $where = ' WHERE is_published = 1 ';
        $bind  = [];
        if ($categoryId) { $where .= ' AND category_id = :cid '; $bind[':cid'] = $categoryId; }
        return DB::getAll(
            'SELECT * FROM `' . $this->tableName . '`' . $where
            . ' ORDER BY published_at DESC, created_at DESC'
            . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset,
            $bind
        ) ?: [];
    }

    public function countPublished(?int $categoryId = null): int {
        $where = ' WHERE is_published = 1 ';
        $bind  = [];
        if ($categoryId) { $where .= ' AND category_id = :cid '; $bind[':cid'] = $categoryId; }
        return (int) DB::getCell('SELECT COUNT(*) FROM `' . $this->tableName . '`' . $where, $bind);
    }

    public function incrementViews(): void {
        if (!$this->id) return;
        DB::exec('UPDATE `post` SET view_count = view_count + 1 WHERE id = :id', [':id' => $this->id]);
        $this->viewCount++;
    }

    public function t(string $field, string $lang = 'en'): string {
        $data = json_decode($this->$field ?? '{}', true);
        return $data[$lang] ?? $data['en'] ?? '';
    }

    public function save($editId = null, $prop = null): ?int {
        $this->updatedAt = date('Y-m-d H:i:s');
        if (!$this->createdAt) $this->createdAt = $this->updatedAt;
        if ($this->isPublished && !$this->publishedAt) $this->publishedAt = $this->updatedAt;
        return parent::save($editId, $prop);
    }

    public function toArray(string $lang = 'en'): array {
        return [
            'id'          => $this->id,
            'userId'      => $this->userId,
            'categoryId'  => $this->categoryId,
            'slug'        => $this->getSlug($lang),
            'title'       => $this->t('title', $lang),
            'excerpt'     => $this->t('excerpt', $lang),
            'content'     => $this->t('content', $lang),
            'coverImage'  => $this->coverImage,
            'isPublished' => (bool) $this->isPublished,
            'viewCount'   => $this->viewCount,
            'publishedAt' => $this->publishedAt,
            'createdAt'   => $this->createdAt,
        ];
    }
}
