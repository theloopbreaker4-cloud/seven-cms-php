<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ContentV1ApiController — public/read + admin/write API for Custom Content Types.
 *
 *   GET  /api/v1/content/types                       — list active types          (public)
 *   GET  /api/v1/content/:typeSlug                   — list published entries    (public)
 *   GET  /api/v1/content/:typeSlug/:entrySlug        — single published entry    (public)
 *   POST /api/v1/content/:typeSlug                   — create entry              (auth: content.create)
 *   PUT  /api/v1/content/:typeSlug/:id               — update entry              (auth: content.update)
 *   DELETE /api/v1/content/:typeSlug/:id             — delete entry              (auth: content.delete)
 */
class ContentV1ApiController extends ApiV1Controller
{
    public function types($req, $res, $params)
    {
        $rows = ContentType::all(true);
        return print $this->json(['items' => array_map(fn($r) => (new ContentType($r))->toArray(), $rows)]);
    }

    public function listEntries($req, $res, $params)
    {
        $typeSlug = (string)($params[0] ?? '');
        $type     = ContentType::findBySlug($typeSlug);
        if (!$type) $this->jsonError(404, 'Type not found');

        $limit  = (int)($_GET['limit']  ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $items  = ContentEntry::listByType((int)$type->id, [
            'status' => 'published',
            'locale' => (string)($_GET['locale'] ?? ''),
            'q'      => (string)($_GET['q']      ?? ''),
            'limit'  => $limit,
            'offset' => $offset,
        ]);
        $total = (int)(DB::getCell(
            'SELECT COUNT(*) FROM content_entries WHERE type_id = :t AND status = "published"',
            [':t' => $type->id]
        ) ?? 0);

        return print $this->paginate(
            array_map(fn($r) => (new ContentEntry($r))->toArray(), $items),
            $total,
            ['limit' => $limit, 'offset' => $offset]
        );
    }

    public function showEntry($req, $res, $params)
    {
        $type = ContentType::findBySlug((string)($params[0] ?? ''));
        if (!$type) $this->jsonError(404, 'Type not found');

        $entry = ContentEntry::findBySlug((int)$type->id, (string)($params[1] ?? ''), (string)($_GET['locale'] ?? 'en'));
        if (!$entry || $entry->status !== 'published') {
            // Allow draft preview when the request carries a valid preview token.
            $token = (string)($_GET['token'] ?? '');
            $payload = $token ? PreviewToken::verify($token) : null;
            if (!$payload || $payload['e'] !== 'content_entries' || $payload['i'] !== ($entry->id ?? 0)) {
                $this->jsonError(404, 'Entry not found');
            }
        }
        return print $this->json($entry->toArray());
    }

    public function createEntry($req, $res, $params)
    {
        $this->requirePermission('content.create');
        $type = ContentType::findBySlug((string)($params[0] ?? ''));
        if (!$type) $this->jsonError(404, 'Type not found');

        $body  = $this->jsonBody();
        $entry = ContentEntry::persist($type, $body);
        return print $this->json($entry->toArray(), 201);
    }

    public function updateEntry($req, $res, $params)
    {
        $this->requirePermission('content.update');
        $type = ContentType::findBySlug((string)($params[0] ?? ''));
        if (!$type) $this->jsonError(404, 'Type not found');

        $id    = (int)($params[1] ?? 0);
        $body  = $this->jsonBody();
        $entry = ContentEntry::persist($type, $body, $id);
        return print $this->json($entry->toArray());
    }

    public function deleteEntry($req, $res, $params)
    {
        $this->requirePermission('content.delete');
        $id = (int)($params[1] ?? 0);
        ContentEntry::deleteById($id);
        return print $this->json(['ok' => true]);
    }

    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $arr = $raw ? json_decode($raw, true) : null;
        return is_array($arr) ? $arr : $_POST;
    }
}
