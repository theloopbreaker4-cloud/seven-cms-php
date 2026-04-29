<?php

defined('_SEVEN') or die('No direct script access allowed');

class Setting extends Model
{
    public ?int    $id        = null;
    public string  $key       = '';
    public string  $value     = '';
    public string  $group     = 'general'; // 'general' | 'seo' | 'mail' | 'social'
    public string  $type      = 'string';  // 'string' | 'text' | 'bool' | 'int' | 'json'
    public string  $label     = '';
    public ?string $updatedAt = null;

    protected static array $cache = [];

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public static function get(string $key, mixed $default = null): mixed {
        if (isset(self::$cache[$key])) return self::$cache[$key];

        $row = DB::findOne('setting', ' `key` = :k ', [':k' => $key]);
        if (!$row) return $default;

        $val = self::cast($row->value, $row->type ?? 'string');
        self::$cache[$key] = $val;
        return $val;
    }

    public static function set(string $key, mixed $value): void {
        self::$cache[$key] = $value;
        $row = DB::findOne('setting', ' `key` = :k ', [':k' => $key]);
        if (!$row) {
            $row        = DB::dispense('setting');
            $row->type  = 'string';
            $row->group = 'general';
        }

        $row->key        = $key;
        $row->value      = is_array($value) ? json_encode($value) : (string)$value;
        $row->updated_at = date('Y-m-d H:i:s');
        DB::store($row);
    }

    public static function getGroup(string $group): array {
        $rows = DB::find('setting', ' `group` = :g ORDER BY `key` ASC ', [':g' => $group]) ?: [];
        $out  = [];
        foreach ($rows as $r) {
            $out[$r->key] = self::cast($r->value, $r->type ?? 'string');
        }
        return $out;
    }

    private static function cast(string $value, string $type): mixed {
        return match($type) {
            'bool'  => (bool)(int)$value,
            'int'   => (int)$value,
            'json'  => json_decode($value, true) ?? [],
            default => $value,
        };
    }

    public function save($editId = null, $prop = null): ?int {
        $this->updatedAt = date('Y-m-d H:i:s');
        return parent::save($editId, $prop);
    }
}
