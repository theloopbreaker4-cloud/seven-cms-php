<?php

defined('_SEVEN') or die('No direct script access allowed');

class CacheFileDriver implements CacheDriverInterface
{
    private string $dir;

    public function __construct(string $dir = '')
    {
        $this->dir = $dir ?: ROOT_DIR . DS . 'storage' . DS . 'cache';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);
        if (!file_exists($path)) return null;

        $raw  = file_get_contents($path);
        $data = unserialize($raw);

        if ($data === false || (isset($data['expires']) && $data['expires'] < time())) {
            @unlink($path);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        file_put_contents(
            $this->path($key),
            serialize(['expires' => time() + $ttl, 'value' => $value]),
            LOCK_EX
        );
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    public function flush(): void
    {
        foreach (glob($this->dir . DS . '*.cache') as $file) {
            @unlink($file);
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    private function path(string $key): string
    {
        return $this->dir . DS . md5($key) . '.cache';
    }
}
