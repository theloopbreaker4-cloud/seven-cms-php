<?php

defined('_SEVEN') or die('No direct script access allowed');

class MenuModule implements ModuleInterface
{
    public function getName(): string { return 'Menu'; }
    public function boot(): void {}

    public function routes(): array
    {
        return [
            'admin.menus'       => ['controller' => 'menu', 'action' => 'index',  'prefix' => 'admin'],
            'admin.menu.create' => ['controller' => 'menu', 'action' => 'create', 'prefix' => 'admin'],
            'admin.menu.store'  => ['controller' => 'menu', 'action' => 'store',  'prefix' => 'admin'],
            'admin.menu.edit'   => ['controller' => 'menu', 'action' => 'edit',   'prefix' => 'admin'],
            'admin.menu.update' => ['controller' => 'menu', 'action' => 'update', 'prefix' => 'admin'],
            'admin.menu.delete' => ['controller' => 'menu', 'action' => 'delete', 'prefix' => 'admin'],
        ];
    }
}
