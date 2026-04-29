<?php

/**
 * Test bootstrap.
 *
 * Tests do NOT touch the database or HTTP layer. They cover pure logic
 * (Container, JWT, TOTP, Money). Anything else needs an integration suite.
 *
 * Composer's autoload covers most of the codebase. We also pre-load a few
 * core libs that have implicit ordering.
 */

declare(strict_types=1);

if (!defined('_SEVEN'))   define('_SEVEN', 1);
if (!defined('ROOT_DIR')) define('ROOT_DIR', dirname(__DIR__));
if (!defined('DS'))       define('DS', DIRECTORY_SEPARATOR);

// Composer autoload (must exist after `composer install`).
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php missing — run `composer install` first.\n");
    exit(1);
}
require_once $autoload;

// Pre-load specific libs that don't autoload by class name (file != classname).
foreach (['env', 'jwt', 'container', 'totp', 'composerbridge'] as $f) {
    $p = ROOT_DIR . '/lib/' . $f . '.class.php';
    if (is_file($p)) require_once $p;
}

// Test-only stub for Logger (real one writes to disk).
if (!class_exists('Logger')) {
    final class Logger {
        public static function channel(string $name): self { return new self(); }
        public function debug(string $m, array $c = []): void {}
        public function info (string $m, array $c = []): void {}
        public function warning(string $m, array $c = []): void {}
        public function error(string $m, array $c = []): void {}
    }
}
