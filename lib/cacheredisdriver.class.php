<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Redis cache driver (requires PHP redis extension or predis).
 * Falls back gracefully — if connection fails, every operation is a no-op.
 */
class CacheRedisDriver implements CacheDriverInterface
{
    private ?\Redis $redis = null;

    public function __construct(string $host = '127.0.0.1', int $port = 6379, string $prefix = 'seven:')
    {
        if (!extension_loaded('redis')) return;

        try {
            $r = new \Redis();
            if ($r->connect($host, $port, 2.0)) {
                $r->setOption(\Redis::OPT_PREFIX, $prefix);
                $r->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
                $this->redis = $r;
            }
        } catch (\Throwable) {
            // Redis unavailable — silently degrade
        }
    }

    public function get(string $key): mixed
    {
        if (!$this->redis) return null;
        $val = $this->redis->get($key);
        return $val === false ? null : $val;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        if (!$this->redis) return;
        $this->redis->setex($key, $ttl, $value);
    }

    public function delete(string $key): void
    {
        if (!$this->redis) return;
        $this->redis->del($key);
    }

    public function flush(): void
    {
        if (!$this->redis) return;
        $this->redis->flushDB();
    }

    public function has(string $key): bool
    {
        if (!$this->redis) return false;
        return (bool) $this->redis->exists($key);
    }

    public function isConnected(): bool
    {
        return $this->redis !== null;
    }
}
