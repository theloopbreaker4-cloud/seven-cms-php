<?php

defined('_SEVEN') or die('No direct script access allowed');

class MenuItem extends Model
{
    public ?int    $id        = null;
    public ?int    $menuId    = null;
    public ?int    $parentId  = null;
    public string  $label     = '{}';  // {"en":"Home","ru":"Главная"}
    public string  $url       = '';    // custom URL or empty
    public string  $linkType  = 'custom'; // 'custom' | 'page' | 'post' | 'category'
    public ?int    $linkId    = null;  // id of linked entity
    public string  $target    = '_self';
    public int     $sortOrder = 0;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function t(string $lang = 'en'): string {
        $data = json_decode($this->label ?? '{}', true);
        return $data[$lang] ?? $data['en'] ?? '';
    }

    public function resolveUrl(string $lang = 'en'): string {
        if ($this->linkType === 'custom' || !$this->linkId) return $this->url;
        return match($this->linkType) {
            'page'     => '/' . $lang . '/page/' . $this->linkId,
            'post'     => '/' . $lang . '/blog/' . $this->linkId,
            'category' => '/' . $lang . '/blog?category=' . $this->linkId,
            default    => $this->url,
        };
    }

    public function toArray(string $lang = 'en'): array {
        return [
            'id'        => $this->id,
            'parentId'  => $this->parentId,
            'label'     => $this->t($lang),
            'url'       => $this->resolveUrl($lang),
            'target'    => $this->target,
            'sortOrder' => $this->sortOrder,
        ];
    }
}
