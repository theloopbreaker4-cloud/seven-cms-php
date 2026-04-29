<?php

defined('_SEVEN') or die('No direct script access allowed');

class Page extends Model
{
    public ?int    $id          = null;
    public ?int    $userId      = null;
    public ?int    $parentId    = null;
    public string  $template    = 'default';
    public string  $title       = '{}';  // {"en":"...","ru":"..."}
    public string  $content     = '{}';
    public string  $metaTitle   = '{}';
    public string  $metaDesc    = '{}';
    public int     $isPublished = 1;
    public int     $sortOrder   = 0;
    public ?string $createdAt   = null;
    public ?string $updatedAt   = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    // Find page by localized slug + lang, fallback to 'en' slug
    public function findBySlug(string $slug, string $lang = 'en'): bool
    {
        $id = Slug::findEntity('page', $lang, $slug)
           ?? Slug::findEntity('page', 'en', $slug);
        if (!$id) return false;
        $row = DB::load($this->tableName, $id);
        if (!$row || !$row->id) return false;
        $this->setModel($row);
        return true;
    }

    // Get all slugs for this page as [lang => slug]
    public function getSlugs(): array
    {
        return $this->id ? Slug::forEntity('page', $this->id) : [];
    }

    // Get slug for a specific lang
    public function getSlug(string $lang = 'en'): string
    {
        return $this->id ? Slug::get('page', $this->id, $lang) : '';
    }

    public function getPublished(): array {
        return DB::getAll(
            'SELECT * FROM `' . $this->tableName . '` WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC'
        ) ?: [];
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

    public function toArray(string $lang = 'en'): array {
        return [
            'id'          => $this->id,
            'userId'      => $this->userId,
            'parentId'    => $this->parentId,
            'slug'        => $this->getSlug($lang),
            'template'    => $this->template,
            'title'       => $this->t('title', $lang),
            'content'     => $this->t('content', $lang),
            'metaTitle'   => $this->t('metaTitle', $lang),
            'metaDesc'    => $this->t('metaDesc', $lang),
            'isPublished' => (bool) $this->isPublished,
            'sortOrder'   => $this->sortOrder,
            'createdAt'   => $this->createdAt,
        ];
    }
}
