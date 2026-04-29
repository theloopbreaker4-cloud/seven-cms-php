<?php

defined('_SEVEN') or die('No direct script access allowed');

class Model extends RedBean_SimpleModel
{
    public ?int $id = null;

    protected string $tableName = '';

    public function __toString(): string { return get_called_class(); }
    public function getModel(): string   { return get_called_class(); }

    public function __construct()
    {
        $this->tableName = strtolower(get_called_class());
    }

    public function getOne(int $id): void
    {
        $table = DB::load($this->tableName, $id);
        $this->setModel($table);
    }

    public function getAll(): array
    {
        return DB::getAll('SELECT * FROM `' . $this->tableName . '`') ?: [];
    }

    public function select(array $array, ?string $orderByValue = null, string $orderBy = 'ASC'): array
    {
        $orderBy = strtoupper($orderBy) === 'DESC' ? 'DESC' : 'ASC';
        if ($orderByValue !== null) {
            $table = DB::findLike($this->tableName, $array, ' ORDER BY ' . $orderByValue . ' ' . $orderBy . ' ');
        } else {
            $table = DB::findLike($this->tableName, $array);
        }
        return $table ?: [];
    }

    public function save(?int $editId = null, ?string $prop = null): ?int
    {
        $tableName = $this->tableName;
        $table     = $editId ? DB::load($tableName, $editId) : DB::dispense($tableName);

        foreach (get_object_vars($this) as $key => $value) {
            if ($key !== 'id' && $key !== 'tableName' && $key !== 'bean') {
                $table->$key = $value;
            }
        }

        // Unique constraint check
        if ($prop !== null) {
            $exists = DB::findOne($tableName, ' ' . $prop . ' = :prop ', [':prop' => $table->$prop]);
            if ($exists !== null && (int)$exists->id > 0) {
                return null;
            }
        }

        $id = DB::store($table);
        $this->setModel($table);
        return (int)$id;
    }

    public function remove(?int $id = null): bool
    {
        if (!$id) return false;
        $table = DB::load($this->tableName, $id);
        DB::trash($table);
        return true;
    }

    protected function setModel(mixed $table = null): bool
    {
        if ($table === null) return false;

        if (is_array($table)) {
            foreach ($this as $key => $value) {
                if (array_key_exists($key, $table)) {
                    $this->$key = $table[$key];
                }
            }
        } else {
            foreach ($this as $key => $value) {
                if (isset($table->$key)) {
                    $this->$key = $table->$key;
                }
            }
        }
        return true;
    }
}
