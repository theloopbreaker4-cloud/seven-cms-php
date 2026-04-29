<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * LocalStorage — writes files under the public uploads directory.
 *
 * Paths are relative (e.g. "2026/04/abc.jpg"). Resolved to:
 *   ROOT_DIR/public/{baseDir}/{relativePath}
 * and served via the URL:
 *   {baseUrl}/{relativePath}
 */
class LocalStorage implements StorageDriver
{
    public function __construct(
        private string $baseDir = 'uploads',  // relative to ROOT_DIR/public
        private string $baseUrl = '/uploads'
    ) {}

    public function put(string $relativePath, string $contents, string $mimeType = 'application/octet-stream'): string
    {
        $abs = $this->absolute($relativePath);
        $dir = dirname($abs);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        if (file_put_contents($abs, $contents) === false) {
            throw new RuntimeException("Failed to write {$abs}");
        }
        return $this->url($relativePath);
    }

    public function get(string $relativePath): ?string
    {
        $abs = $this->absolute($relativePath);
        return is_file($abs) ? (file_get_contents($abs) ?: null) : null;
    }

    public function delete(string $relativePath): bool
    {
        $abs = $this->absolute($relativePath);
        if (!file_exists($abs)) return true;
        return @unlink($abs);
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolute($relativePath));
    }

    public function url(string $relativePath): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($relativePath, '/');
    }

    private function absolute(string $relativePath): string
    {
        $clean = ltrim($relativePath, '/');
        return ROOT_DIR . '/public/' . trim($this->baseDir, '/') . '/' . $clean;
    }
}
