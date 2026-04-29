<?php

defined('_SEVEN') or die('No direct script access allowed');

$config = [
    'baseUrl'              => PROTOCOL . ROOT_URL,
    'siteName'             => Env::get('SITE_NAME', 'Seven CMS'),
    'defaultLanguage'      => 'en',
    'defaultRouter'        => 'default',
    'defaultController'    => 'home',
    'defaultAction'        => 'index',
    'defaultApiController' => 'main',
    'defaultApiAction'     => 'index',
    'viewPath'             => ROOT_DIR . DS . 'app' . DS . 'views' . DS,
    // Layout filename suffix: renders viewPath + {prefix} + viewLayout + '.html'
    // e.g. admin prefix → views/adminMaster.html, empty prefix → views/master.html
    'viewLayout'           => 'Master',
    'languages'            => ['en', 'ru', 'ka', 'uk', 'az', 'hy'],
    'keyRouters'           => [
        'default' => '',
        'api'     => 'api',
        'admin'   => 'admin',
    ],
    'cache' => [
        'driver' => Env::get('CACHE_DRIVER', 'file'), // 'file' or 'redis'
        'ttl'    => (int) Env::get('CACHE_TTL', 3600),
    ],
    'redis' => [
        'host'   => Env::get('REDIS_HOST', '127.0.0.1'),
        'port'   => (int) Env::get('REDIS_PORT', 6379),
        'prefix' => Env::get('REDIS_PREFIX', 'seven:'),
    ],
    // IPs of reverse proxies whose X-Forwarded-For header we trust for client
    // IP resolution (rate limiting, logs). Comma-separated in env. Leave empty
    // when running without a proxy — direct REMOTE_ADDR is used in that case.
    'trustedProxies' => array_filter(
        array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', '')))
    ),
];
