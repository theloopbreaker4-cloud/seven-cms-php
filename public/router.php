<?php
// Router for PHP built-in server (php -S)
// Serves static files directly, routes everything else to index.php

$uri  = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Block direct directory access — route to index.php instead
if (is_dir($file)) {
    require_once __DIR__ . '/index.php';
    exit;
}

// Serve existing static files directly (css, js, images, fonts, svg, ico)
if ($uri !== '/' && file_exists($file) && is_file($file)) {
    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'svg'  => 'image/svg+xml',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'json' => 'application/json',
        'map'  => 'application/json',
    ];
    if (isset($mime[$ext])) {
        header('Content-Type: ' . $mime[$ext]);
        readfile($file);
        exit;
    }
    return false;
}

// Route everything else through index.php
require_once __DIR__ . '/index.php';
