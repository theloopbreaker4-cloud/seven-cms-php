<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Language extends Model
{
    public ?int    $id         = null;
    public string  $code       = '';     // 'en', 'ru', 'ka' ...
    public string  $name       = '';     // 'English'
    public string  $nativeName = '';     // 'English', 'Русский', 'ქართული'
    public string  $flag       = '';     // emoji flag '🇬🇧'
    public int     $isActive   = 1;
    public int     $isDefault  = 0;
    public int     $sortOrder  = 0;
    public ?string $createdAt  = null;

    // All known languages with flag + native name
    public const KNOWN = [
        'en' => ['name' => 'English',    'native' => 'English',    'flag' => '🇬🇧'],
        'ru' => ['name' => 'Russian',    'native' => 'Русский',    'flag' => '🇷🇺'],
        'ka' => ['name' => 'Georgian',   'native' => 'ქართული',    'flag' => '🇬🇪'],
        'uk' => ['name' => 'Ukrainian',  'native' => 'Українська', 'flag' => '🇺🇦'],
        'az' => ['name' => 'Azerbaijani','native' => 'Azərbaycanca','flag' => '🇦🇿'],
        'hy' => ['name' => 'Armenian',   'native' => 'Հայերեն',    'flag' => '🇦🇲'],
        'be' => ['name' => 'Belarusian', 'native' => 'Беларуская', 'flag' => '🇧🇾'],
        'de' => ['name' => 'German',     'native' => 'Deutsch',    'flag' => '🇩🇪'],
        'fr' => ['name' => 'French',     'native' => 'Français',   'flag' => '🇫🇷'],
        'es' => ['name' => 'Spanish',    'native' => 'Español',    'flag' => '🇪🇸'],
        'tr' => ['name' => 'Turkish',    'native' => 'Türkçe',     'flag' => '🇹🇷'],
        'ar' => ['name' => 'Arabic',     'native' => 'العربية',    'flag' => '🇸🇦'],
        'zh' => ['name' => 'Chinese',    'native' => '中文',        'flag' => '🇨🇳'],
        'ja' => ['name' => 'Japanese',   'native' => '日本語',      'flag' => '🇯🇵'],
        'pt' => ['name' => 'Portuguese', 'native' => 'Português',  'flag' => '🇵🇹'],
        'it' => ['name' => 'Italian',    'native' => 'Italiano',   'flag' => '🇮🇹'],
        'pl' => ['name' => 'Polish',     'native' => 'Polski',     'flag' => '🇵🇱'],
        'nl' => ['name' => 'Dutch',      'native' => 'Nederlands', 'flag' => '🇳🇱'],
        'sv' => ['name' => 'Swedish',    'native' => 'Svenska',    'flag' => '🇸🇪'],
        'ko' => ['name' => 'Korean',     'native' => '한국어',      'flag' => '🇰🇷'],
    ];

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    // Get all active languages ordered by sort_order
    public static function getActive(): array {
        return DB::find('language', ' is_active = 1 ORDER BY sort_order ASC, id ASC ') ?: [];
    }

    // Get active language codes as simple array ['en','ru',...]
    public static function getActiveCodes(): array {
        $rows = self::getActive();
        return array_map(fn($r) => $r->code, $rows);
    }

    // Get default language code
    public static function getDefault(): string {
        $row = DB::findOne('language', ' is_default = 1 AND is_active = 1 ');
        return $row ? $row->code : 'en';
    }

    // Convert bean/row to array for API/frontend
    public static function rowToArray(mixed $row): array {
        return [
            'id'         => (int)$row->id,
            'code'       => $row->code,
            'name'       => $row->name,
            'nativeName' => $row->native_name,
            'flag'       => $row->flag,
            'isDefault'  => (bool)$row->is_default,
            'sortOrder'  => (int)$row->sort_order,
        ];
    }

    public function save($editId = null, $prop = null): ?int {
        $this->createdAt = $this->createdAt ?: date('Y-m-d H:i:s');
        return parent::save($editId, $prop);
    }
}
