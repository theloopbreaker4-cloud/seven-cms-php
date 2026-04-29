<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class MediaModule implements ModuleInterface
{
    public function getName(): string { return 'Media'; }

    public function boot(): void
    {
        // Make sure folder root exists.
        $uploads = ROOT_DIR . '/public/uploads';
        if (!is_dir($uploads)) @mkdir($uploads, 0755, true);
    }

    public function routes(): array
    {
        return [
            // List + filters
            'admin.media'                => ['controller' => 'media', 'action' => 'index',        'prefix' => 'admin'],

            // Upload + edit + delete
            'admin.media.upload'         => ['controller' => 'media', 'action' => 'upload',       'prefix' => 'admin'],
            'admin.media.update'         => ['controller' => 'media', 'action' => 'update',       'prefix' => 'admin'],
            'admin.media.delete'         => ['controller' => 'media', 'action' => 'delete',       'prefix' => 'admin'],
            'admin.media.bulkDelete'     => ['controller' => 'media', 'action' => 'bulkDelete',   'prefix' => 'admin'],

            // Folders
            'admin.media.folder.create'  => ['controller' => 'media', 'action' => 'folderCreate', 'prefix' => 'admin'],
            'admin.media.folder.delete'  => ['controller' => 'media', 'action' => 'folderDelete', 'prefix' => 'admin'],
        ];
    }
}
