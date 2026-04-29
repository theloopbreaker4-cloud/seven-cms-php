<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * StorageDriver — minimal storage abstraction for media files.
 *
 * Implementations:
 *   - LocalStorage   (writes under ROOT_DIR/public/uploads)
 *   - S3Storage      (writes to an S3-compatible bucket; requires aws-sdk or guzzle for signed PUT)
 *
 * The application talks only through this interface, so plugins or media
 * settings can swap drivers via the Container without touching call sites.
 */
interface StorageDriver
{
    /** Persist file content. Returns the public URL or path. */
    public function put(string $relativePath, string $contents, string $mimeType = 'application/octet-stream'): string;

    /** Read file contents (or null if missing). */
    public function get(string $relativePath): ?string;

    /** Delete file. Returns true if deleted (or didn't exist). */
    public function delete(string $relativePath): bool;

    /** Whether the file exists. */
    public function exists(string $relativePath): bool;

    /** Get a public URL for the file. */
    public function url(string $relativePath): string;
}
