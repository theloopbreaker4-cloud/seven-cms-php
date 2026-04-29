<?php

defined('_SEVEN') or die('No direct script access allowed');

class SliderModule implements ModuleInterface
{
    public function getName(): string { return 'Slider'; }
    public function boot(): void {}

    public function routes(): array
    {
        return [
            'admin.slider'         => ['controller' => 'slider', 'action' => 'index',   'prefix' => 'admin'],
            'admin.slider.create'  => ['controller' => 'slider', 'action' => 'create',  'prefix' => 'admin'],
            'admin.slider.store'   => ['controller' => 'slider', 'action' => 'store',   'prefix' => 'admin'],
            'admin.slider.edit'    => ['controller' => 'slider', 'action' => 'edit',    'prefix' => 'admin'],
            'admin.slider.update'  => ['controller' => 'slider', 'action' => 'update',  'prefix' => 'admin'],
            'admin.slider.delete'  => ['controller' => 'slider', 'action' => 'delete',  'prefix' => 'admin'],
            'admin.slider.reorder' => ['controller' => 'slider', 'action' => 'reorder', 'prefix' => 'admin'],
        ];
    }
}
