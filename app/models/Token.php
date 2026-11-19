<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Token extends Model
{
    public ?int    $id        = null;
    public ?int    $userId    = null;
    public string  $value     = '';
    // Store auth as int (0/1) to avoid conflict with RedBeanPHP\SimpleModel typed props
    public int     $auth      = 1;
    public ?string $createdAt = null;

    /** Plain token for the cookie; only known right after creation. The DB keeps its SHA-256. */
    private ?string $plain = null;

    public function __construct(?int $userId = null)
    {
        parent::__construct();
        if ($userId) {
            $this->userId    = $userId;
            $this->auth      = 1;
            $this->plain     = Crypt::randomToken(32);
            $this->value     = hash('sha256', $this->plain);
            $this->createdAt = date('Y-m-d H:i:s');
        }
    }

    public function getValue(): string
    {
        return $this->plain ?? '';
    }

    public function setToken(string $value): void
    {
        if (!$value || !ctype_xdigit($value)) return;

        $table = DB::findOne(
            $this->tableName,
            ' value = :value AND auth = 1 AND created_at > NOW() - INTERVAL 30 DAY ',
            [':value' => hash('sha256', $value)]
        );

        if ($table) {
            $this->setModel($table);
        }
    }
}
