<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Media model — represents a single uploaded asset.
 *
 * Storage layout: /public/uploads/{YYYY}/{MM}/{uuid}.{ext}
 * Variants live next to the original under /uploads/.../variants/{uuid}-{label}.{ext}.
 */
class Media extends Model
{
    public ?int    $id           = null;
    public ?int    $userId       = null;
    public ?int    $folderId     = null;
    public string  $filename     = '';
    public string  $originalName = '';
    public string  $mimeType     = '';
    public int     $size         = 0;
    public ?int    $width        = null;
    public ?int    $height       = null;
    public string  $path         = '';
    public string  $disk         = 'local';
    public string  $alt          = '{}';
    public string  $title        = '';
    public ?string $description  = null;
    public string  $variants     = '{}';
    public ?string $createdAt    = null;
    public ?string $updatedAt    = null;

    /** Bytes — keep in sync with PHP upload_max_filesize / post_max_size. */
    public const MAX_SIZE = 25 * 1024 * 1024; // 25 MB

    /** Whitelist for security; everything else is rejected. */
    public const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml',
        'application/pdf',
        'video/mp4', 'video/webm',
        'audio/mpeg', 'audio/ogg', 'audio/wav',
    ];

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    /**
     * Upload one file. Returns inserted media id or null on failure.
     */
    public function upload(array $file, int $userId, ?int $folderId = null): ?int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > self::MAX_SIZE)                          return null;

        // Detect mime from content; do not trust browser-provided $file['type'].
        $mime = $this->detectMime($file['tmp_name']) ?: ($file['type'] ?? 'application/octet-stream');
        if (!in_array($mime, self::ALLOWED_MIME, true)) return null;

        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uuid = bin2hex(random_bytes(16));

        $subdir = $folderId ? $this->resolveFolderPath($folderId) : date('Y/m');
        $absDir = ROOT_DIR . '/public/uploads/' . $subdir;
        if (!is_dir($absDir)) mkdir($absDir, 0755, true);

        $stored  = $uuid . ($ext ? '.' . $ext : '');
        $absFile = $absDir . '/' . $stored;
        if (!move_uploaded_file($file['tmp_name'], $absFile)) return null;

        [$w, $h] = $this->probeDimensions($absFile, $mime);

        $this->userId       = $userId;
        $this->folderId     = $folderId;
        $this->filename     = $stored;
        $this->originalName = $file['name'];
        $this->mimeType     = $mime;
        $this->size         = (int)$file['size'];
        $this->width        = $w;
        $this->height       = $h;
        $this->path         = '/uploads/' . $subdir . '/' . $stored;
        $this->disk         = 'local';
        $this->alt          = '{}';
        $this->title        = '';
        $this->variants     = '{}';
        $this->createdAt    = date('Y-m-d H:i:s');

        $id = $this->save();
        if (!$id) return null;

        $this->maybeGenerateVariants($absFile, $mime, $id);

        return $id;
    }

    /** Delete file from disk plus all known variants. Does NOT touch DB row. */
    public function deleteFile(): void
    {
        if (!$this->path || $this->disk !== 'local') return;

        $abs = ROOT_DIR . '/public' . $this->path;
        if (file_exists($abs)) @unlink($abs);

        $variants = json_decode($this->variants ?: '{}', true) ?: [];
        foreach ($variants as $rel) {
            $vAbs = ROOT_DIR . '/public' . $rel;
            if (is_string($rel) && file_exists($vAbs)) @unlink($vAbs);
        }
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'userId'       => $this->userId,
            'folderId'     => $this->folderId,
            'filename'     => $this->filename,
            'originalName' => $this->originalName,
            'mimeType'     => $this->mimeType,
            'size'         => $this->size,
            'width'        => $this->width,
            'height'       => $this->height,
            'path'         => $this->path,
            'disk'         => $this->disk,
            'alt'          => json_decode($this->alt ?: '{}', true) ?: [],
            'title'        => $this->title,
            'description'  => $this->description,
            'variants'     => json_decode($this->variants ?: '{}', true) ?: [],
            'createdAt'    => $this->createdAt,
            'updatedAt'    => $this->updatedAt,
        ];
    }

    public function isImage(): bool { return str_starts_with($this->mimeType, 'image/'); }

    private function detectMime(string $path): ?string
    {
        if (!function_exists('finfo_open')) return null;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) return null;
        $mime = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);
        return $mime;
    }

    private function probeDimensions(string $path, string $mime): array
    {
        if (!str_starts_with($mime, 'image/') || $mime === 'image/svg+xml') return [null, null];
        $info = @getimagesize($path);
        return $info ? [(int)$info[0], (int)$info[1]] : [null, null];
    }

    private function resolveFolderPath(int $folderId): string
    {
        $row = DB::findOne('media_folder', ' id = :id ', [':id' => $folderId]);
        return $row && !empty($row['path']) ? trim((string)$row['path'], '/') : date('Y/m');
    }

    private function maybeGenerateVariants(string $absFile, string $mime, int $mediaId): void
    {
        if (!class_exists('MediaProcessor')) return;
        try {
            $variants = MediaProcessor::generate($absFile, $this->path, $mime);
            if ($variants) {
                DB::execute(
                    'UPDATE media SET variants = :v WHERE id = :id',
                    [':v' => json_encode($variants, JSON_UNESCAPED_SLASHES), ':id' => $mediaId]
                );
                $this->variants = json_encode($variants, JSON_UNESCAPED_SLASHES);
            }
        } catch (\Throwable $e) {
            Logger::channel('app')->warning('Media variant generation failed', [
                'id' => $mediaId, 'error' => $e->getMessage(),
            ]);
        }
    }
}
