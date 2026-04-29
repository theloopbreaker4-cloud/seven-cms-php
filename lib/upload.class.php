<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Upload
{
    // Allowed MIME types → extensions
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private const MAX_SIZE = 5 * 1024 * 1024; // 5 MB

    private string $uploadDir;

    public function __construct(string $subDir = 'uploads')
    {
        // Store uploads outside public/ reach or in a directory with no PHP execution
        $this->uploadDir = ROOT_DIR . DS . 'public' . DS . $subDir . DS;
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    // Upload a single file from $_FILES[$field]
    // Returns relative web path on success, throws RuntimeException on failure
    public function store(string $field): string
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload error or no file selected.');
        }

        $file = $_FILES[$field];

        // Size check
        if ($file['size'] > self::MAX_SIZE) {
            throw new RuntimeException('File exceeds maximum allowed size (5 MB).');
        }

        // MIME check via finfo (not trusting $_FILES['type'])
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file['tmp_name']);

        if (!array_key_exists($realMime, self::ALLOWED)) {
            throw new RuntimeException('File type not allowed: ' . $realMime);
        }

        // Generate safe filename — no user input in filename
        $ext      = self::ALLOWED[$realMime];
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest     = $this->uploadDir . $filename;

        // Directory traversal guard
        if (strpos(realpath(dirname($dest)), realpath($this->uploadDir)) !== 0) {
            throw new RuntimeException('Invalid upload path.');
        }

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException('Failed to move uploaded file.');
        }

        // Return web-accessible path
        return '/uploads/' . $filename;
    }

    // Delete a previously uploaded file by its web path
    public function delete(string $webPath): void
    {
        $filename = basename($webPath);
        // Validate filename contains only safe chars
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|gif|webp|svg)$/', $filename)) return;
        $path = $this->uploadDir . $filename;
        if (file_exists($path)) unlink($path);
    }
}
