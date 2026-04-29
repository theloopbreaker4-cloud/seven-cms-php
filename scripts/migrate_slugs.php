<?php
/**
 * Migrate existing page.slug and post.slug columns into the slug table.
 * Run once after deploying the localized-slug feature.
 * Usage: wsl.exe -d Ubuntu bash -c 'php /mnt/d/Works/SevenCMSProjects/sevenPHP/scripts/migrate_slugs.php'
 */

define('_SEVEN', true);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', dirname(__DIR__));
define('ENVIRONMENT', 'dev');
define('ROOT_URL', 'localhost');
define('PROTOCOL', 'http://');

if (!defined('STDIN')) define('STDIN', 'php://input');

require_once ROOT_DIR . DS . 'lib' . DS . 'env.class.php';
Env::load(ROOT_DIR . DS . '.env');

require_once ROOT_DIR . DS . 'lib' . DS . 'extension' . DS . 'rb' . DS . 'rb.php';

$files = glob(ROOT_DIR . DS . 'config' . DS . '*.config.php');
foreach ($files as $f) require_once $f;

/** @var array $dbConfig */
\R::setup(
    'mysql:host=' . $dbConfig['dbHost'] . ';dbname=' . $dbConfig['dbname'] . ';charset=utf8mb4',
    $dbConfig['user'],
    $dbConfig['password']
);
\R::setAutoResolve(true);

$now = date('Y-m-d H:i:s');
$migrated = 0;
$skipped  = 0;

foreach (['page', 'post'] as $type) {
    // Check if slug column still exists in the table
    $cols = \R::getAll("SHOW COLUMNS FROM `{$type}` LIKE 'slug'");
    if (empty($cols)) {
        echo "[{$type}] No 'slug' column found, skipping.\n";
        continue;
    }

    $rows = \R::getAll("SELECT id, slug FROM `{$type}` WHERE slug IS NOT NULL AND slug != ''");
    echo "[{$type}] Found " . count($rows) . " rows with slugs.\n";

    foreach ($rows as $row) {
        $id   = (int)$row['id'];
        $slug = trim($row['slug']);
        if ($slug === '') { $skipped++; continue; }

        // Skip if already in slug table for this entity + en
        $exists = \R::findOne('slug', ' entity_type = :t AND entity_id = :id AND lang = "en" ', [':t' => $type, ':id' => $id]);
        if ($exists) {
            echo "  [{$type}#{$id}] Already has slug '{$exists->slug}', skipping.\n";
            $skipped++;
            continue;
        }

        // Check slug conflict with other entities
        $conflict = \R::findOne('slug', ' entity_type = :t AND lang = "en" AND slug = :s AND entity_id != :id ',
            [':t' => $type, ':s' => $slug, ':id' => $id]);
        if ($conflict) {
            $slug .= '-' . $id;
            echo "  [{$type}#{$id}] Conflict, using slug '{$slug}'.\n";
        }

        $b = \R::dispense('slug');
        $b->entity_type = $type;
        $b->entity_id   = $id;
        $b->lang        = 'en';
        $b->slug        = $slug;
        $b->created_at  = $now;
        \R::store($b);

        echo "  [{$type}#{$id}] Migrated slug '{$slug}'.\n";
        $migrated++;
    }
}

echo "\nDone. Migrated: {$migrated}, Skipped: {$skipped}.\n";
echo "You can now drop the 'slug' columns from page and post tables if desired.\n";
