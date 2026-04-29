<?php

defined('_SEVEN') or die('No direct script access allowed');

class Slide extends Model
{
    public ?int    $id         = null;
    public string  $title      = '{}';   // {"en":"...","ru":"..."}
    public string  $subtitle   = '{}';
    public string  $buttonText = '{}';
    public string  $buttonUrl  = '';
    public string  $image      = '';     // URL or path
    public string  $overlay    = 'none'; // 'none' | 'dark' | 'light'
    public int     $sortOrder  = 0;
    public int     $isActive   = 1;
    public ?string $createdAt  = null;
    public ?string $updatedAt  = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function getActive(): array {
        return DB::getAll('SELECT * FROM slide WHERE is_active = 1 ORDER BY sort_order ASC, id ASC') ?: [];
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
            'id'         => $this->id,
            'title'      => $this->t('title', $lang),
            'subtitle'   => $this->t('subtitle', $lang),
            'buttonText' => $this->t('buttonText', $lang),
            'buttonUrl'  => $this->buttonUrl,
            'image'      => $this->image,
            'overlay'    => $this->overlay,
            'sortOrder'  => $this->sortOrder,
            'isActive'   => (bool) $this->isActive,
        ];
    }
}
