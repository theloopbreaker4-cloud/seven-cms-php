<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Model extends RedBean_SimpleModel
{
    public ?int $id = null;

    protected string $tableName = '';

    public function __toString(): string { return get_called_class(); }
    public function getModel(): string   { return get_called_class(); }

    public function __construct()
    {
        // Subclasses whose table isn't their lowercased class name declare it:
        //   protected string $tableName = 'ecom_orders';
        if ($this->tableName === '') $this->tableName = strtolower(get_called_class());
    }

    /**
     * RedBean only dispenses/loads bean types matching [a-z0-9]+ ("Invalid bean type"
     * for ecom_orders), so tables with underscores are written with plain SQL.
     */
    protected function usesBeans(): bool
    {
        return (bool)preg_match('/^[a-z0-9]+$/', $this->tableName);
    }

    /** @var array<string, string[]> table => column names */
    private static array $columnCache = [];

    protected function columns(): array
    {
        $t = $this->tableName;
        if (!isset(self::$columnCache[$t])) {
            self::$columnCache[$t] = array_map(fn($r) => $r['Field'], DB::getAll('SHOW COLUMNS FROM `' . $t . '`') ?: []);
        }
        return self::$columnCache[$t];
    }

    public static function snake(string $key): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
    }

    public function getOne(int $id): void
    {
        if (!$this->usesBeans()) {
            $this->setModel(DB::getRow('SELECT * FROM `' . $this->tableName . '` WHERE id = ?', [$id]) ?: null);
            return;
        }
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

        // Explicit NULLs would override NOT NULL DEFAULT CURRENT_TIMESTAMP columns.
        $now = date('Y-m-d H:i:s');
        if (property_exists($this, 'createdAt') && empty($this->createdAt)) $this->createdAt = $now;
        if (property_exists($this, 'updatedAt')) $this->updatedAt = $now;

        if (!$this->usesBeans()) return $this->saveSql($editId, $prop);

        $table     = $editId ? DB::load($tableName, $editId) : DB::dispense($tableName);

        // Write columns directly: the magic setter treats "coverImage" as a link
        // to cover_image_id whenever both exist and throws "Cannot cast to bean".
        foreach (get_object_vars($this) as $key => $value) {
            if ($key !== 'id' && $key !== 'tableName' && $key !== 'bean') {
                $table->setProperty($table->beau($key), $value, false, true);
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

    /**
     * INSERT/UPDATE for tables RedBean can't dispense. Properties map to snake_case
     * columns; properties without a column are ignored (no fluid schema changes here,
     * these tables come from migrations). Arrays are stored as JSON, bools as 0/1.
     */
    private function saveSql(?int $editId, ?string $prop): ?int
    {
        $cols = array_flip($this->columns());
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if ($key === 'id' || $key === 'tableName' || $key === 'bean') continue;
            $col = isset($cols[$key]) ? $key : self::snake($key);
            if (!isset($cols[$col])) continue;
            if (is_array($value) || is_object($value)) $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            elseif (is_bool($value)) $value = (int)$value;
            $data[$col] = $value;
        }
        if (!$editId && array_key_exists('created_at', $data) && $data['created_at'] === null) unset($data['created_at']);

        if ($prop !== null) {
            $col = isset($cols[$prop]) ? $prop : self::snake($prop);
            if (isset($cols[$col], $data[$col])) {
                $other = DB::getCell(
                    'SELECT id FROM `' . $this->tableName . '` WHERE `' . $col . '` = ? AND id <> ? LIMIT 1',
                    [$data[$col], (int)$editId]
                );
                if ($other) return null;
            }
        }
        if (!$data) return $editId;

        if ($editId) {
            $set = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($data)));
            DB::execute('UPDATE `' . $this->tableName . '` SET ' . $set . ' WHERE id = ?', [...array_values($data), $editId]);
            $id = $editId;
        } else {
            $names = implode(', ', array_map(fn($c) => "`$c`", array_keys($data)));
            $marks = implode(', ', array_fill(0, count($data), '?'));
            DB::execute('INSERT INTO `' . $this->tableName . '` (' . $names . ') VALUES (' . $marks . ')', array_values($data));
            $id = DB::lastInsertId();
        }
        $this->setModel(DB::getRow('SELECT * FROM `' . $this->tableName . '` WHERE id = ?', [$id]) ?: null);
        $this->id = (int)$id;
        return (int)$id;
    }

    public function remove(?int $id = null): bool
    {
        if (!$id) return false;
        if (!$this->usesBeans()) {
            DB::execute('DELETE FROM `' . $this->tableName . '` WHERE id = ?', [$id]);
            return true;
        }
        $table = DB::load($this->tableName, $id);
        DB::trash($table);
        return true;
    }

    protected function setModel(mixed $table = null): bool
    {
        if ($table === null) return false;

        // Rows come from DB::getAll() (arrays) or DB::findOne()/load() (beans), both keyed
        // by snake_case columns, while model properties are camelCase. Match either spelling;
        // before this, every getAll()-built model silently lost basePrice, createdAt, …
        if ($table instanceof \RedBeanPHP\OODBBean) $table = $table->export();
        elseif (is_object($table)) $table = get_object_vars($table);
        if (!is_array($table)) return false;

        foreach ($this as $key => $value) {
            if (array_key_exists($key, $table)) {
                $v = $table[$key];
            } else {
                $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
                if ($snake === $key || !array_key_exists($snake, $table)) continue;
                $v = $table[$snake];
            }
            try {
                $this->$key = $v;
            } catch (\TypeError) {
                // NULL column into a non-nullable property: keep the default
            }
        }
        return true;
    }
}
