<?php

defined('_SEVEN') or die('No direct script access allowed');

interface ModuleInterface
{
    /** Module unique identifier, e.g. 'blog', 'admin', 'gallery' */
    public function getName(): string;

    /** Called once when the module is registered */
    public function boot(): void;

    /** Return named routes this module contributes */
    public function routes(): array;
}
