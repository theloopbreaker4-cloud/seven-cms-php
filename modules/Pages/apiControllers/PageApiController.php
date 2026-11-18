<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Pages API (admin, Bearer token required)
 *
 * GET    /api/page/index          — list all pages
 * GET    /api/page/show/{id}      — single page
 * POST   /api/page/store          — create
 * PUT    /api/page/update/{id}    — update
 * DELETE /api/page/delete/{id}    — delete
 */
class PageApiController extends ApiController
{
    private function toArray(Page $p): array
    {
        return [
            'id'          => (int) $p->id,
            'slugs'       => $p->getSlugs(),
            'slug'        => $p->getSlug('en'),
            'title'       => json_decode($p->title, true)   ?: [],
            'content'     => json_decode($p->content, true) ?: [],
            'metaDesc'    => json_decode($p->metaDesc, true) ?: [],
            'isPublished' => (bool) $p->isPublished,
            'createdAt'   => $p->createdAt,
            'updatedAt'   => $p->updatedAt,
        ];
    }

    public function index($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $rows  = DB::getAll('SELECT * FROM page ORDER BY created_at DESC');
        $pages = [];
        foreach ($rows as $row) {
            $p = new Page();
            $p->setModel($row);
            $pages[] = $this->toArray($p);
        }
        Logger::channel('app')->debug('API pages list', ['count' => count($pages), 'adminId' => $admin->id]);
        return json_encode($pages);
    }

    public function show($req, $res, $params)
    {
        $this->requireAdminToken();
        $id = (int)($params[0] ?? 0);
        $p  = new Page();
        $p->getOne($id);
        if (!$p->id) {
            Logger::channel('app')->warn('API page not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }
        return json_encode($this->toArray($p));
    }

    public function store($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $data  = $this->body();
        $p              = new Page();
        $p->title       = json_encode($data['title']    ?? [], JSON_UNESCAPED_UNICODE);
        $p->content     = json_encode($data['content']  ?? [], JSON_UNESCAPED_UNICODE);
        $p->metaDesc    = json_encode($data['metaDesc'] ?? [], JSON_UNESCAPED_UNICODE);
        $p->isPublished = isset($data['isPublished']) ? (int)(bool)$data['isPublished'] : 1;

        $id = $p->save();
        if (!$id) {
            $res->jsonError(500, 'Failed to create page');
        }
        if (!empty($data['slugs']) && is_array($data['slugs'])) {
            $errors = Slug::saveForEntity('page', $id, $data['slugs']);
            if ($errors) $res->jsonError(409, 'Slug conflict: ' . implode('; ', $errors));
        } elseif (!empty($data['slug'])) {
            Slug::saveForEntity('page', $id, ['en' => $data['slug']]);
        }

        Logger::channel('app')->info('API page created', ['id' => $id, 'adminId' => $admin->id]);
        $p->getOne($id);
        http_response_code(201);
        return json_encode($this->toArray($p));
    }

    public function update($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $id    = (int)($params[0] ?? 0);
        $data  = $this->body();
        $p     = new Page();
        $p->getOne($id);
        if (!$p->id) {
            Logger::channel('app')->warn('API page update: not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }

        if (isset($data['title']))       $p->title       = json_encode($data['title'],   JSON_UNESCAPED_UNICODE);
        if (isset($data['content']))     $p->content     = json_encode($data['content'], JSON_UNESCAPED_UNICODE);
        if (isset($data['metaDesc']))    $p->metaDesc    = json_encode($data['metaDesc'],JSON_UNESCAPED_UNICODE);
        if (isset($data['isPublished'])) $p->isPublished = (int)(bool)$data['isPublished'];

        $p->save($id);
        if (!empty($data['slugs']) && is_array($data['slugs'])) {
            Slug::saveForEntity('page', $id, $data['slugs']);
        } elseif (!empty($data['slug'])) {
            Slug::saveForEntity('page', $id, ['en' => $data['slug']]);
        }
        Logger::channel('app')->info('API page updated', ['id' => $id, 'adminId' => $admin->id]);
        $p->getOne($id);
        return json_encode($this->toArray($p));
    }

    public function delete($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $id    = (int)($params[0] ?? 0);
        $p     = new Page();
        $p->getOne($id);
        if (!$p->id) {
            Logger::channel('app')->warn('API page delete: not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }
        $p->remove($id);
        Slug::deleteForEntity('page', $id);
        Logger::channel('app')->info('API page deleted', ['id' => $id, 'adminId' => $admin->id]);
        http_response_code(204);
        return '';
    }
}
