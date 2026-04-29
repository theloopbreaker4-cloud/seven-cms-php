<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * PageBuilderAdminController — drag-and-drop block editor.
 *
 *   GET  /admin/pagebuilder/edit/:entity_type/:entity_id     editor UI
 *   POST /admin/pagebuilder/save/:entity_type/:entity_id     persist tree (JSON)
 *   GET  /admin/pagebuilder/preview/:entity_type/:entity_id  render-only preview HTML
 *
 * The UI is single-page: on first load it ships the catalog + current tree
 * as JSON; the JS app handles add/remove/reorder/edit and POSTs the full
 * tree back on Save.
 */
class PageBuilderAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function edit($req, $res, $params)
    {
        $this->requirePerm('pagebuilder.view', $res);

        $entityType = (string)($params[0] ?? 'page');
        $entityId   = (int)   ($params[1] ?? 0);
        $this->app->setTitle('Page builder');

        $catalog = BlockTypes::catalog();
        $tree    = Block::tree($entityType, $entityId);

        return $this->app->view->render('pagebuilder/edit', [
            'entityType' => $entityType,
            'entityId'   => $entityId,
            'catalog'    => $catalog,
            'tree'       => $tree,
        ]);
    }

    public function save($req, $res, $params)
    {
        $this->requirePerm('pagebuilder.edit', $res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $entityType = (string)($params[0] ?? 'page');
        $entityId   = (int)   ($params[1] ?? 0);

        $raw = (string)file_get_contents('php://input');
        $body = json_decode($raw, true) ?: [];
        $blocks = is_array($body['blocks'] ?? null) ? $body['blocks'] : [];

        Block::saveTree($entityType, $entityId, $blocks);
        if (class_exists('ActivityLog')) {
            ActivityLog::log('pagebuilder.save', $entityType, $entityId, 'Saved ' . count($blocks) . ' block(s)');
        }
        return $this->json($res, ['ok' => true, 'count' => count($blocks)]);
    }

    public function preview($req, $res, $params)
    {
        $this->requirePerm('pagebuilder.view', $res);
        $entityType = (string)($params[0] ?? 'page');
        $entityId   = (int)   ($params[1] ?? 0);
        header('Content-Type: text/html; charset=utf-8');
        echo BlockRenderer::render($entityType, $entityId, ['locale' => $this->app->router->getLanguage()]);
        exit;
    }

    private function requirePerm(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }

    private function json($res, array $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
