<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * S3Storage — S3-compatible object storage (AWS S3, Cloudflare R2, MinIO, Backblaze B2).
 *
 * Implementation uses AWS SDK if installed (composer require aws/aws-sdk-php). When the SDK
 * is missing the methods throw `RuntimeException` with a hint — the storage abstraction is
 * still useful so the application doesn't hard-fail at boot.
 *
 * Configure via .env:
 *   STORAGE_DRIVER=s3
 *   S3_KEY=…
 *   S3_SECRET=…
 *   S3_REGION=auto
 *   S3_BUCKET=my-bucket
 *   S3_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com   (optional, for R2/MinIO)
 *   S3_PUBLIC_URL=https://cdn.example.com                       (public base URL)
 */
class S3Storage implements StorageDriver
{
    private $client = null;

    public function __construct(
        private string $bucket,
        private string $region = 'auto',
        private ?string $endpoint = null,
        private string $publicUrl = '',
        private ?string $key = null,
        private ?string $secret = null
    ) {}

    public function put(string $relativePath, string $contents, string $mimeType = 'application/octet-stream'): string
    {
        $client = $this->client();
        $client->putObject([
            'Bucket'      => $this->bucket,
            'Key'         => ltrim($relativePath, '/'),
            'Body'        => $contents,
            'ContentType' => $mimeType,
            'ACL'         => 'public-read',
        ]);
        return $this->url($relativePath);
    }

    public function get(string $relativePath): ?string
    {
        $client = $this->client();
        try {
            $obj = $client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => ltrim($relativePath, '/'),
            ]);
            return (string)$obj['Body'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function delete(string $relativePath): bool
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => ltrim($relativePath, '/'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function exists(string $relativePath): bool
    {
        try {
            $this->client()->headObject([
                'Bucket' => $this->bucket,
                'Key'    => ltrim($relativePath, '/'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function url(string $relativePath): string
    {
        $base = rtrim($this->publicUrl ?: ($this->endpoint . '/' . $this->bucket), '/');
        return $base . '/' . ltrim($relativePath, '/');
    }

    private function client()
    {
        if ($this->client) return $this->client;

        if (!class_exists('Aws\\S3\\S3Client')) {
            throw new RuntimeException(
                'S3Storage requires aws/aws-sdk-php. Install with: composer require aws/aws-sdk-php'
            );
        }

        $cfg = [
            'version' => 'latest',
            'region'  => $this->region,
        ];
        if ($this->endpoint) {
            $cfg['endpoint']                = $this->endpoint;
            $cfg['use_path_style_endpoint'] = true;
        }
        if ($this->key && $this->secret) {
            $cfg['credentials'] = ['key' => $this->key, 'secret' => $this->secret];
        }
        $this->client = new \Aws\S3\S3Client($cfg);
        return $this->client;
    }
}
