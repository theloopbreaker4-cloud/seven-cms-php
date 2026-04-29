<?php

defined('_SEVEN') or die('No direct script access allowed');

class Menu extends Model
{
    public ?int    $id        = null;
    public string  $handle    = '';  // 'main' | 'footer' | 'sidebar'
    public string  $name      = '';
    public ?string $createdAt = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function findByHandle(string $handle): bool {
        $row = DB::findOne($this->tableName, ' handle = :h ', [':h' => $handle]);
        if ($row) { $this->setModel($row); return true; }
        return false;
    }

    public function getItems(): array {
        if (!$this->id) return [];
        return DB::find('menuitem', ' menu_id = :mid ORDER BY sort_order ASC ', [':mid' => $this->id]) ?: [];
    }

    public function save($editId = null, $prop = null): ?int {
        $this->createdAt = $this->createdAt ?: date('Y-m-d H:i:s');
        return parent::save($editId, $prop);
    }
}
