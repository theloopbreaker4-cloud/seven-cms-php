<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Posts (Blog) API (admin, Bearer token required)
 *
 * GET    /api/post/index           — list
 * GET    /api/post/show/{id}       — single
 * POST   /api/post/store           — create
 * PUT    /api/post/update/{id}     — update
 * DELETE /api/post/delete/{id}     — delete
 */
class PostApiController extends ApiController
{
    private function toArray(Post $p): array
    {
        return [
            'id'          => (int) $p->id,
            'slugs'       => $p->getSlugs(),
            'slug'        => $p->getSlug('en'),
            'title'       => json_decode($p->title,   true) ?: [],
            'excerpt'     => json_decode($p->excerpt, true) ?: [],
            'content'     => json_decode($p->content, true) ?: [],
            'coverImage'  => $p->coverImage,
            'isPublished' => (bool) $p->isPublished,
            'createdAt'   => $p->createdAt,
            'updatedAt'   => $p->updatedAt,
        ];
    }

    public function index($req, $res, $params)
    {
        $admin  = $this->requireAdminToken();
        $limit  = max(1, min(200, (int)($_GET['limit']  ?? 50)));
        $offset = max(0, (int)($_GET['offset'] ?? 0));
        $rows   = DB::getAll(
            'SELECT * FROM post ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $posts = [];
        foreach ($rows as $row) {
            $p = new Post();
            $p->setModel($row);
            $posts[] = $this->toArray($p);
        }
        Logger::channel('app')->debug('API posts list', ['count' => count($posts), 'adminId' => $admin->id]);
        return json_encode($posts);
    }

    public function show($req, $res, $params)
    {
        $this->requireAdminToken();
        $id = (int)($params[0] ?? 0);
        $p  = new Post();
        $p->getOne($id);
        if (!$p->id) {
            Logger::channel('app')->warn('API post not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }
        return json_encode($this->toArray($p));
    }

    public function store($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $data  = $this->body();
        $p              = new Post();
        $p->title       = json_encode($data['title']   ?? [], JSON_UNESCAPED_UNICODE);
        $p->excerpt     = json_encode($data['excerpt'] ?? [], JSON_UNESCAPED_UNICODE);
        $p->content     = json_encode($data['content'] ?? [], JSON_UNESCAPED_UNICODE);
        $p->coverImage  = $data['coverImage'] ?? '';
        $p->isPublished = isset($data['isPublished']) ? (int)(bool)$data['isPublished'] : 1;

        $id = $p->save();
        if (!$id) {
            $res->jsonError(500, 'Failed to create post');
        }
        if (!empty($data['slugs']) && is_array($data['slugs'])) {
            $errors = Slug::saveForEntity('post', $id, $data['slugs']);
            if ($errors) $res->jsonError(409, 'Slug conflict: ' . implode('; ', $errors));
        } elseif (!empty($data['slug'])) {
            Slug::saveForEntity('post', $id, ['en' => $data['slug']]);
        }

        Logger::channel('app')->info('API post created', ['id' => $id, 'adminId' => $admin->id]);
        $p->getOne($id);
        http_response_code(201);
        return json_encode($this->toArray($p));
    }

    public function update($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $id    = (int)($params[0] ?? 0);
        $data  = $this->body();
        $p     = new Post();
        $p->getOne($id);
        if (!$p->id) {
            Logger::channel('app')->warn('API post update: not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }

        if (isset($data['title']))                    $p->title       = json_encode($data['title'],   JSON_UNESCAPED_UNICODE);
        if (isset($data['excerpt']))                  $p->excerpt     = json_encode($data['excerpt'], JSON_UNESCAPED_UNICODE);
        if (isset($data['content']))                  $p->content     = json_encode($data['content'], JSON_UNESCAPED_UNICODE);
        if (array_key_exists('coverImage', $data))   $p->coverImage  = $data['coverImage'];
        if (isset($data['isPublished']))              $p->isPublished = (int)(bool)$data['isPublished'];

        $p->save($id);
        if (!empty($data['slugs']) && is_array($data['slugs'])) {
            Slug::saveForEntity('post', $id, $data['slugs']);
        } elseif (!empty($data['slug'])) {
            Slug::saveForEntity('post', $id, ['en' => $data['slug']]);
        }
        Logger::channel('app')->info('API post updated', ['id' => $id, 'adminId' => $admin->id]);
        $p->getOne($id);
        return json_encode($this->toArray($p));
    }

    public function delete($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $id    = (int)($params[0] ?? 0);
        $p     = new Post();
        $p->getOne($id);
        if (!$p->id) {
            Logger::channel('app')->warn('API post delete: not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }
        $p->remove($id);
        Slug::deleteForEntity('post', $id);
        Logger::channel('app')->info('API post deleted', ['id' => $id, 'adminId' => $admin->id]);
        http_response_code(204);
        return '';
    }
}
