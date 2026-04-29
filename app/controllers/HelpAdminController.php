<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * HelpAdminController — categorized in-admin documentation browser.
 *
 *   GET /admin/help                — overview + categories
 *   GET /admin/help/topic/:slug    — render docs/{slug}.md as HTML
 *
 * The admin sidebar tree is defined in `topics()` — every category has an
 * icon, a list of topics, and each topic maps to a `docs/{file}.md` plus a
 * list of anchor links. Pages are rendered through `Markdown::render()` so
 * `docs/` remains the single source of truth — edits there appear instantly
 * in the admin UI.
 */
class HelpAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle('Help & Docs');

        return $this->app->view->render('index', [
            'topics'  => self::topics(),
            'current' => null,
            'html'    => null,
        ]);
    }

    public function topic($req, $res, $params)
    {
        $this->requireAdmin($res);
        $slug  = (string)($params[0] ?? '');
        $topic = self::findTopic($slug);
        if (!$topic) $res->errorCode(404);

        $path = ROOT_DIR . '/docs/' . $topic['file'];
        if (!is_file($path)) $res->errorCode(404);
        $md = (string)file_get_contents($path);

        $html = class_exists('Markdown') ? Markdown::render($md) : nl2br(htmlspecialchars($md));

        // Rewrite md-link placeholders to in-admin URLs.
        $lang = $this->app->router->getLanguage();
        $html = preg_replace_callback(
            '/#mdlink#([a-z0-9_-]+)\.md(#[a-z0-9_-]+)?#/i',
            function ($m) use ($lang) {
                $sl     = $m[1] === 'index' ? '' : $m[1];
                $anchor = $m[2] ?? '';
                $base   = "/{$lang}/admin/help" . ($sl ? "/topic/{$sl}" : '');
                return $base . $anchor;
            },
            (string)$html
        ) ?? $html;

        $this->app->setTitle('Help: ' . $topic['title']);

        return $this->app->view->render('topic', [
            'topics'  => self::topics(),
            'current' => $topic,
            'html'    => $html,
        ]);
    }

    /** Fully expanded help tree (matches docs/index.md). */
    public static function topics(): array
    {
        return [
            ['label' => 'Getting Started', 'icon' => '🚀', 'topics' => [
                ['slug' => 'getting-started', 'title' => 'Installation & first run', 'file' => 'getting-started.md', 'anchors' => [
                    ['label' => 'Installation',     'hash' => '#installation'],
                    ['label' => 'Configuration',    'hash' => '#configuration'],
                    ['label' => 'Five-minute tour', 'hash' => '#five-minute-tour'],
                    ['label' => 'Upgrading',        'hash' => '#upgrading'],
                ]],
            ]],
            ['label' => 'Architecture', 'icon' => '🏗', 'topics' => [
                ['slug' => 'architecture', 'title' => 'Architecture overview', 'file' => 'architecture.md', 'anchors' => [
                    ['label' => 'Overview',          'hash' => '#overview'],
                    ['label' => 'Service container', 'hash' => '#service-container'],
                    ['label' => 'Hooks',             'hash' => '#hooks'],
                    ['label' => 'Migrations',        'hash' => '#migrations'],
                    ['label' => 'Storage',           'hash' => '#storage-abstraction'],
                ]],
                ['slug' => 'composer', 'title' => 'Composer integration', 'file' => 'composer.md'],
                ['slug' => 'cli',      'title' => 'CLI tool',             'file' => 'cli.md'],
            ]],
            ['label' => 'Plugins', 'icon' => '🧩', 'topics' => [
                ['slug' => 'plugins', 'title' => 'Plugins guide', 'file' => 'plugins.md', 'anchors' => [
                    ['label' => 'Lifecycle',        'hash' => '#lifecycle'],
                    ['label' => 'Writing a plugin', 'hash' => '#writing-a-plugin'],
                    ['label' => 'plugin.json',      'hash' => '#pluginjson'],
                    ['label' => 'Hook reference',   'hash' => '#hook-reference'],
                ]],
            ]],
            ['label' => 'Content', 'icon' => '📝', 'topics' => [
                ['slug' => 'content-types', 'title' => 'Custom content types', 'file' => 'content-types.md', 'anchors' => [
                    ['label' => 'Overview',      'hash' => '#overview'],
                    ['label' => 'Field types',   'hash' => '#field-types'],
                    ['label' => 'Relationships', 'hash' => '#relationships'],
                    ['label' => 'Revisions',     'hash' => '#revisions'],
                    ['label' => 'Preview mode',  'hash' => '#preview-mode'],
                ]],
            ]],
            ['label' => 'Users & Access', 'icon' => '👥', 'topics' => [
                ['slug' => 'rbac', 'title' => 'Roles, permissions, 2FA, audit log', 'file' => 'rbac.md', 'anchors' => [
                    ['label' => 'Roles',        'hash' => '#roles'],
                    ['label' => 'Permissions',  'hash' => '#permissions'],
                    ['label' => '2FA',          'hash' => '#2fa'],
                    ['label' => 'Activity log', 'hash' => '#activity-log'],
                ]],
            ]],
            ['label' => 'Media', 'icon' => '🖼', 'topics' => [
                ['slug' => 'media', 'title' => 'Media library', 'file' => 'media.md', 'anchors' => [
                    ['label' => 'Upload',           'hash' => '#upload'],
                    ['label' => 'Folders',          'hash' => '#folders'],
                    ['label' => 'Variants & WebP',  'hash' => '#variants'],
                    ['label' => 'Storage drivers',  'hash' => '#storage-drivers'],
                ]],
            ]],
            ['label' => 'APIs', 'icon' => '🌐', 'topics' => [
                ['slug' => 'api', 'title' => 'REST API v1', 'file' => 'api.md', 'anchors' => [
                    ['label' => 'Auth',          'hash' => '#authentication'],
                    ['label' => 'Endpoints',     'hash' => '#rest-endpoints'],
                    ['label' => 'Pagination',    'hash' => '#pagination'],
                    ['label' => 'Rate limiting', 'hash' => '#rate-limiting'],
                ]],
                ['slug' => 'graphql', 'title' => 'GraphQL endpoint', 'file' => 'graphql.md'],
            ]],
            ['label' => 'E-commerce', 'icon' => '🛒', 'topics' => [
                ['slug' => 'ecom', 'title' => 'E-commerce', 'file' => 'ecom.md', 'anchors' => [
                    ['label' => 'Setup',          'hash' => '#setup'],
                    ['label' => 'Products',       'hash' => '#products'],
                    ['label' => 'Orders',         'hash' => '#orders'],
                    ['label' => 'Stripe',         'hash' => '#stripe'],
                    ['label' => 'PayPal',         'hash' => '#paypal'],
                    ['label' => 'Subscriptions',  'hash' => '#subscriptions'],
                    ['label' => 'Digital files',  'hash' => '#digital-delivery'],
                    ['label' => 'Discounts',      'hash' => '#discounts'],
                    ['label' => 'Tax & shipping', 'hash' => '#tax--shipping'],
                ]],
            ]],
            ['label' => 'Multi-site',    'icon' => '🌍', 'topics' => [
                ['slug' => 'multisite',   'title' => 'Multi-site',   'file' => 'multisite.md'],
            ]],
            ['label' => 'Page builder',  'icon' => '🧱', 'topics' => [
                ['slug' => 'pagebuilder', 'title' => 'Page builder', 'file' => 'pagebuilder.md'],
            ]],
            ['label' => 'Operations', 'icon' => '⚙', 'topics' => [
                ['slug' => 'cron',          'title' => 'Cron & scheduler',  'file' => 'cron.md', 'anchors' => [
                    ['label' => 'Schedule grammar', 'hash' => '#schedule-grammar'],
                    ['label' => 'Built-in jobs',    'hash' => '#built-in-jobs'],
                    ['label' => 'OS cron setup',    'hash' => '#os-cron-setup'],
                ]],
                ['slug' => 'mail',          'title' => 'Mail queue',        'file' => 'mail.md'],
                ['slug' => 'notifications', 'title' => 'Notifications',     'file' => 'notifications.md'],
                ['slug' => 'calendar',      'title' => 'Admin calendar',    'file' => 'calendar.md'],
                ['slug' => 'multicurrency', 'title' => 'Multi-currency',    'file' => 'multicurrency.md'],
                ['slug' => 'testing',       'title' => 'PHPUnit tests',     'file' => 'testing.md'],
                ['slug' => 'storefront',    'title' => 'Storefront',        'file' => 'storefront.md'],
            ]],
        ];
    }

    private static function findTopic(string $slug): ?array
    {
        foreach (self::topics() as $cat) {
            foreach ($cat['topics'] as $t) {
                if ($t['slug'] === $slug) return $t;
            }
        }
        return null;
    }
}
