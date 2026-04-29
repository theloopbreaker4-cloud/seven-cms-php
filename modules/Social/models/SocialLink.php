<?php

defined('_SEVEN') or die('No direct script access allowed');

class SocialLink extends Model
{
    public ?int    $id        = null;
    public string  $platform  = '';
    public string  $url       = '';
    public string  $label     = '';
    public int     $sortOrder = 0;
    public int     $isActive  = 1;
    public ?string $createdAt = null;

    public function __construct($data = [])
    {
        parent::__construct();
        $this->tableName = 'sociallink';
        if ($data) $this->setModel($data);
    }

    public function getActive(): array
    {
        return DB::getAll('SELECT * FROM sociallink WHERE is_active = 1 ORDER BY sort_order ASC, id ASC') ?: [];
    }

    public function getAll(): array
    {
        return DB::getAll('SELECT * FROM sociallink ORDER BY sort_order ASC, id ASC') ?: [];
    }

    public function save(?int $editId = null, ?string $prop = null): ?int
    {
        if (!$this->createdAt) $this->createdAt = date('Y-m-d H:i:s');
        return parent::save($editId, $prop);
    }

    public function toArray(): array
    {
        return [
            'id'        => (int) $this->id,
            'platform'  => $this->platform,
            'url'       => $this->url,
            'label'     => $this->label,
            'sortOrder' => (int) $this->sortOrder,
            'isActive'  => (bool) $this->isActive,
        ];
    }
}
