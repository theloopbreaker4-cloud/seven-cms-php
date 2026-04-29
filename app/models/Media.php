<?php

defined('_SEVEN') or die('No direct script access allowed');

class Media extends Model
{
    public ?int    $id         = null;
    public ?int    $userId     = null;    // uploader
    public string  $filename   = '';      // stored filename (uuid.ext)
    public string  $originalName = '';
    public string  $mimeType   = '';
    public int     $size       = 0;       // bytes
    public string  $path       = '';      // relative path: /uploads/2026/04/file.jpg
    public string  $alt        = '{}';   // {"en":"..."}
    public string  $disk       = 'local'; // 'local' | 's3'
    public ?string $createdAt  = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function upload(array $file, int $userId): ?int {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;

        $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
                    'application/pdf','video/mp4','audio/mpeg'];
        if (!in_array($file['type'], $allowed, true)) return null;

        $maxBytes = 10 * 1024 * 1024; // 10 MB
        if ($file['size'] > $maxBytes) return null;

        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $uuid     = bin2hex(random_bytes(16));
        $subdir   = date('Y/m');
        $dir      = ROOT_DIR . '/public/uploads/' . $subdir;
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $stored = $uuid . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) return null;

        $this->userId       = $userId;
        $this->filename     = $stored;
        $this->originalName = $file['name'];
        $this->mimeType     = $file['type'];
        $this->size         = $file['size'];
        $this->path         = '/uploads/' . $subdir . '/' . $stored;
        $this->alt          = '{}';
        $this->disk         = 'local';
        $this->createdAt    = date('Y-m-d H:i:s');

        return $this->save();
    }

    public function getRecent(int $limit = 50): array {
        return DB::getAll(
            'SELECT * FROM `media` ORDER BY created_at DESC LIMIT ' . (int)$limit
        ) ?: [];
    }

    public function deleteFile(): void {
        if ($this->path && $this->disk === 'local') {
            $abs = ROOT_DIR . '/public' . $this->path;
            if (file_exists($abs)) unlink($abs);
        }
    }

    public function toArray(): array {
        return [
            'id'           => $this->id,
            'filename'     => $this->filename,
            'originalName' => $this->originalName,
            'mimeType'     => $this->mimeType,
            'size'         => $this->size,
            'path'         => $this->path,
            'alt'          => json_decode($this->alt, true) ?: [],
            'createdAt'    => $this->createdAt,
        ];
    }
}
