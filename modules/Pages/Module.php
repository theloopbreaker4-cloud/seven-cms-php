<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class PagesModule implements ModuleInterface
{
    public function getName(): string { return 'Pages'; }
    public function boot(): void {}

    public function routes(): array
    {
        return [
            'page.show'         => ['controller' => 'page', 'action' => 'show',   'params' => [':slug']],
            'admin.pages'       => ['controller' => 'page', 'action' => 'index',  'prefix' => 'admin'],
            'admin.page.create' => ['controller' => 'page', 'action' => 'create', 'prefix' => 'admin'],
            'admin.page.store'  => ['controller' => 'page', 'action' => 'store',  'prefix' => 'admin'],
            'admin.page.edit'   => ['controller' => 'page', 'action' => 'edit',   'prefix' => 'admin'],
            'admin.page.update' => ['controller' => 'page', 'action' => 'update', 'prefix' => 'admin'],
            'admin.page.delete' => ['controller' => 'page', 'action' => 'delete', 'prefix' => 'admin'],
        ];
    }
}
