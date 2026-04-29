<?php

defined('_SEVEN') or die('No direct script access allowed');

interface CacheDriverInterface
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): void;
    public function delete(string $key): void;
    public function flush(): void;
    public function has(string $key): bool;
}
