<?php

defined('_SEVEN') or die('No direct script access allowed');

class ContentModule implements ModuleInterface
{
    public function getName(): string { return 'Content'; }

    public function boot(): void { /* noop */ }

    public function onInstall(): void
    {
        // Migrations are run by the Migrator before this hook fires.
    }

    public function routes(): array
    {
        return [
            // Types
            'admin.content.types'         => ['controller' => 'content', 'action' => 'typesIndex',  'prefix' => 'admin'],
            'admin.content.types.create'  => ['controller' => 'content', 'action' => 'typesCreate', 'prefix' => 'admin'],
            'admin.content.types.store'   => ['controller' => 'content', 'action' => 'typesStore',  'prefix' => 'admin'],
            'admin.content.types.edit'    => ['controller' => 'content', 'action' => 'typesEdit',   'prefix' => 'admin'],
            'admin.content.types.update'  => ['controller' => 'content', 'action' => 'typesUpdate', 'prefix' => 'admin'],
            'admin.content.types.delete'  => ['controller' => 'content', 'action' => 'typesDelete', 'prefix' => 'admin'],

            // Fields
            'admin.content.fields.store'   => ['controller' => 'content', 'action' => 'fieldsStore',   'prefix' => 'admin'],
            'admin.content.fields.update'  => ['controller' => 'content', 'action' => 'fieldsUpdate',  'prefix' => 'admin'],
            'admin.content.fields.delete'  => ['controller' => 'content', 'action' => 'fieldsDelete',  'prefix' => 'admin'],
            'admin.content.fields.reorder' => ['controller' => 'content', 'action' => 'fieldsReorder', 'prefix' => 'admin'],

            // Entries (per content type)
            'admin.content.entries'           => ['controller' => 'content', 'action' => 'entriesIndex',     'prefix' => 'admin'],
            'admin.content.entries.create'    => ['controller' => 'content', 'action' => 'entriesCreate',    'prefix' => 'admin'],
            'admin.content.entries.store'     => ['controller' => 'content', 'action' => 'entriesStore',     'prefix' => 'admin'],
            'admin.content.entries.edit'      => ['controller' => 'content', 'action' => 'entriesEdit',      'prefix' => 'admin'],
            'admin.content.entries.update'    => ['controller' => 'content', 'action' => 'entriesUpdate',    'prefix' => 'admin'],
            'admin.content.entries.delete'    => ['controller' => 'content', 'action' => 'entriesDelete',    'prefix' => 'admin'],
            'admin.content.entries.preview'   => ['controller' => 'content', 'action' => 'entriesPreview',   'prefix' => 'admin'],
            'admin.content.entries.revisions' => ['controller' => 'content', 'action' => 'entriesRevisions', 'prefix' => 'admin'],
            'admin.content.entries.restore'   => ['controller' => 'content', 'action' => 'entriesRestore',   'prefix' => 'admin'],
        ];
    }
}
