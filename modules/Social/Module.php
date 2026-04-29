<?php

defined('_SEVEN') or die('No direct script access allowed');

class SocialModule implements ModuleInterface
{
    public function getName(): string { return 'Social'; }
    public function boot(): void {}

    public function routes(): array
    {
        return [
            'admin.social'        => ['controller' => 'social', 'action' => 'index',  'prefix' => 'admin'],
            'admin.social.create' => ['controller' => 'social', 'action' => 'create', 'prefix' => 'admin'],
            'admin.social.store'  => ['controller' => 'social', 'action' => 'store',  'prefix' => 'admin'],
            'admin.social.edit'   => ['controller' => 'social', 'action' => 'edit',   'prefix' => 'admin'],
            'admin.social.update' => ['controller' => 'social', 'action' => 'update', 'prefix' => 'admin'],
            'admin.social.delete' => ['controller' => 'social', 'action' => 'delete', 'prefix' => 'admin'],
            // API
            'api.social.links'  => ['controller' => 'social', 'action' => 'links',  'prefix' => 'api'],
            'api.social.store'  => ['controller' => 'social', 'action' => 'store',  'prefix' => 'api'],
            'api.social.update' => ['controller' => 'social', 'action' => 'update', 'prefix' => 'api'],
            'api.social.delete' => ['controller' => 'social', 'action' => 'delete', 'prefix' => 'api'],
        ];
    }
}
