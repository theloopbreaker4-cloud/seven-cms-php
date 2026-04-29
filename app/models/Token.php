<?php

defined('_SEVEN') or die('No direct script access allowed');

class Token extends Model
{
    public ?int    $id        = null;
    public ?int    $userId    = null;
    public string  $value     = '';
    // Store auth as int (0/1) to avoid conflict with RedBeanPHP\SimpleModel typed props
    public int     $auth      = 1;
    public ?string $createdAt = null;

    public function __construct(?int $userId = null)
    {
        parent::__construct();
        if ($userId) {
            $this->userId    = $userId;
            $this->auth      = 1;
            $this->value     = Crypt::randomToken(32);
            $this->createdAt = date('Y-m-d H:i:s');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setToken(string $value): void
    {
        if (!$value || !ctype_xdigit($value)) return;

        $table = DB::findOne(
            $this->tableName,
            ' value = :value AND auth = 1 ',
            [':value' => $value]
        );

        if ($table) {
            $this->setModel($table);
        }
    }
}
