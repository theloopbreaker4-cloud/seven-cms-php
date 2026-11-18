<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Social links public API
 *
 * GET  /api/social/links              — public list of active links
 * POST /api/social/store              — create (admin)
 * PUT  /api/social/update/{id}        — update (admin)
 * DELETE /api/social/delete/{id}      — delete (admin)
 */
class SocialApiController extends ApiController
{
    public function links($req, $res, $params)
    {
        $rows  = (new SocialLink())->getActive();
        $links = array_map(fn($r) => (new SocialLink($r))->toArray(), $rows);
        return json_encode($links);
    }

    public function store($req, $res, $params)
    {
        $this->requireAdminToken();
        $data = $this->body();

        if (empty($data['platform']) || empty($data['url'])) {
            $res->jsonError(422, 'platform and url are required');
        }

        $sl            = new SocialLink();
        $sl->platform  = strtolower(trim($data['platform']));
        $sl->url       = trim($data['url']);
        $sl->label     = trim($data['label'] ?? '');
        $sl->sortOrder = (int)($data['sortOrder'] ?? 0);
        $sl->isActive  = isset($data['isActive']) ? (int)(bool)$data['isActive'] : 1;

        $id = $sl->save();
        Logger::channel('app')->info('Social link created', ['id' => $id, 'platform' => $sl->platform]);
        http_response_code(201);
        $sl->getOne($id);
        return json_encode($sl->toArray());
    }

    public function update($req, $res, $params)
    {
        $this->requireAdminToken();
        $id   = (int)($params[0] ?? 0);
        $data = $this->body();

        $sl = new SocialLink();
        $sl->getOne($id);
        if (!$sl->id) $res->jsonError(404, 'Not found');

        if (isset($data['platform']))  $sl->platform  = strtolower(trim($data['platform']));
        if (isset($data['url']))       $sl->url       = trim($data['url']);
        if (isset($data['label']))     $sl->label     = trim($data['label']);
        if (isset($data['sortOrder'])) $sl->sortOrder = (int)$data['sortOrder'];
        if (isset($data['isActive']))  $sl->isActive  = (int)(bool)$data['isActive'];

        $sl->save($id);
        Logger::channel('app')->info('Social link updated', ['id' => $id]);
        $sl->getOne($id);
        return json_encode($sl->toArray());
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdminToken();
        $id = (int)($params[0] ?? 0);
        $sl = new SocialLink();
        $sl->getOne($id);
        if (!$sl->id) $res->jsonError(404, 'Not found');
        $sl->remove($id);
        Logger::channel('app')->info('Social link deleted', ['id' => $id]);
        http_response_code(204);
        return '';
    }
}
