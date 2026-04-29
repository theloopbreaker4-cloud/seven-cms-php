<?php

defined('_SEVEN') or die('No direct script access allowed');

class CacheAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Cache');

        $activeDriver = get_class(Cache::driver());
        $isRedis      = $activeDriver === 'CacheRedisDriver';
        $redisAvail   = $this->probeRedis();
        $cfg          = $this->app->config['cache'] ?? [];
        $redisCfg     = $this->app->config['redis'] ?? [];

        Logger::channel('app')->debug('Admin cache page', ['driver' => $activeDriver]);
        return $this->app->view->render('index', compact('activeDriver', 'isRedis', 'redisAvail', 'cfg', 'redisCfg'));
    }

    public function flush($req, $res, $params)
    {
        $this->requireAdmin($res);
        Cache::flush();
        Logger::channel('app')->info('Cache flushed', ['adminId' => Auth::getCurrentUser()?->id]);
        Session::setFlash('Cache flushed successfully.');
        $res->redirect('cache', 'index');
    }

    public function save($req, $res, $params)
    {
        $this->requireAdmin($res);

        $data       = $req->getData();
        $driver     = in_array($data['driver'] ?? '', ['file', 'redis']) ? $data['driver'] : 'file';
        $ttl        = max(60, min(86400, (int)($data['ttl'] ?? 3600)));
        $redisHost  = trim($data['redis_host'] ?? '127.0.0.1');
        $redisPort  = max(1, min(65535, (int)($data['redis_port'] ?? 6379)));
        $redisPrefix = preg_replace('/[^a-z0-9_:\-]/i', '', $data['redis_prefix'] ?? 'seven:');

        $envPath = ROOT_DIR . DS . '.env';

        Env::set($envPath, 'CACHE_DRIVER', $driver);
        Env::set($envPath, 'CACHE_TTL',    (string)$ttl);
        Env::set($envPath, 'REDIS_HOST',   $redisHost);
        Env::set($envPath, 'REDIS_PORT',   (string)$redisPort);
        Env::set($envPath, 'REDIS_PREFIX', $redisPrefix);

        Logger::channel('app')->info('Cache settings saved', [
            'driver' => $driver, 'adminId' => Auth::getCurrentUser()?->id
        ]);
        Session::setFlash('Cache settings saved. Restart the server to apply changes.');
        $res->redirect('cache', 'index');
    }

    private function probeRedis(): bool
    {
        if (!extension_loaded('redis')) return false;
        $cfg  = $this->app->config['redis'] ?? [];
        try {
            $r = new \Redis();
            $ok = @$r->connect($cfg['host'] ?? '127.0.0.1', (int)($cfg['port'] ?? 6379), 1.0);
            if ($ok) $r->close();
            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }
}
