<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class PageBuilderModule implements ModuleInterface
{
    public function getName(): string { return 'PageBuilder'; }

    public function boot(): void
    {
        // Make the model autoload — needed before BlockTypes::bootDefaults() runs.
        require_once __DIR__ . '/models/BlockType.php';
        require_once __DIR__ . '/models/BlockTypes.php';
        require_once __DIR__ . '/models/Block.php';
        require_once __DIR__ . '/models/BlockRenderer.php';

        BlockTypes::bootDefaults();
    }

    public function onInstall(): void { /* migrations seed permissions */ }

    public function routes(): array
    {
        return [
            'admin.pagebuilder.edit'    => ['controller' => 'pageBuilder', 'action' => 'edit',    'prefix' => 'admin'],
            'admin.pagebuilder.save'    => ['controller' => 'pageBuilder', 'action' => 'save',    'prefix' => 'admin'],
            'admin.pagebuilder.preview' => ['controller' => 'pageBuilder', 'action' => 'preview', 'prefix' => 'admin'],
        ];
    }
}
