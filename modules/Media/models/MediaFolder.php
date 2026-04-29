<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * MediaFolder — hierarchical folder for grouping media assets.
 *
 * `path` is denormalized for fast lookup; rebuilt on rename/move.
 */
class MediaFolder extends Model
{
    public ?int    $id        = null;
    public ?int    $parentId  = null;
    public string  $name      = '';
    public string  $slug      = '';
    public string  $path      = '';
    public ?string $createdAt = null;
    public ?string $updatedAt = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function create(string $name, ?int $parentId = null): ?int
    {
        $name = trim($name);
        if ($name === '') return null;

        $slug = self::slugify($name);
        $path = $parentId ? self::resolvePath($parentId) . '/' . $slug : $slug;

        $this->parentId  = $parentId;
        $this->name      = $name;
        $this->slug      = $slug;
        $this->path      = $path;
        $this->createdAt = date('Y-m-d H:i:s');

        $id = $this->save();
        if (!$id) return null;

        $abs = ROOT_DIR . '/public/uploads/' . $path;
        if (!is_dir($abs)) @mkdir($abs, 0755, true);

        return $id;
    }

    /**
     * Returns the tree of folders, denormalized into a flat list with `depth`.
     */
    public static function tree(): array
    {
        $rows = DB::getAll('SELECT * FROM media_folder ORDER BY path ASC') ?: [];
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[(int)($r['parent_id'] ?? 0)][] = $r;
        }
        $out = [];
        $walk = function (int $parent, int $depth) use (&$walk, &$out, $byParent) {
            foreach (($byParent[$parent] ?? []) as $row) {
                $row['depth'] = $depth;
                $out[]        = $row;
                $walk((int)$row['id'], $depth + 1);
            }
        };
        $walk(0, 0);
        return $out;
    }

    public static function resolvePath(int $id): string
    {
        $row = DB::findOne('media_folder', ' id = :id ', [':id' => $id]);
        return $row['path'] ?? '';
    }

    public static function slugify(string $name): string
    {
        $slug = preg_replace('~[^\pL\d]+~u', '-', $name);
        $slug = trim((string)iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$slug), '-');
        $slug = strtolower(preg_replace('~[^-a-z0-9]+~i', '', (string)$slug));
        return $slug ?: 'folder-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'parentId'  => $this->parentId,
            'name'      => $this->name,
            'slug'      => $this->slug,
            'path'      => $this->path,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
