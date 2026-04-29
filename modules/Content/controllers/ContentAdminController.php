<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ContentAdminController — admin endpoints for Custom Content Types (CCT).
 *
 * Routes (all under /:lang/admin):
 *   GET  /content/types                    — list types
 *   GET  /content/types/create             — new type form
 *   POST /content/types/store              — persist type
 *   GET  /content/types/edit/:id           — edit type + manage fields
 *   POST /content/types/update/:id         — save type
 *   POST /content/types/delete/:id         — delete type (cascades fields/entries)
 *
 *   POST /content/fields/store/:typeId     — add a field to a type
 *   POST /content/fields/update/:id        — update a field
 *   POST /content/fields/delete/:id        — delete a field
 *   POST /content/fields/reorder/:typeId   — reorder fields (body: ids[])
 *
 *   GET  /content/entries/:typeSlug                — list entries for a type
 *   GET  /content/entries/:typeSlug/create         — new entry form
 *   POST /content/entries/:typeSlug/store          — persist entry
 *   GET  /content/entries/:typeSlug/edit/:id       — edit entry
 *   POST /content/entries/:typeSlug/update/:id     — update entry
 *   POST /content/entries/:typeSlug/delete/:id     — delete entry
 *   GET  /content/entries/:typeSlug/preview/:id    — issue preview link (returns JSON token)
 *   GET  /content/entries/:typeSlug/revisions/:id  — list revisions
 *   POST /content/entries/:typeSlug/restore/:rev   — restore from a revision
 */
class ContentAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    // ──────────────────────────────────────────────────────────────────
    // Content types
    // ──────────────────────────────────────────────────────────────────

    public function typesIndex($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $this->app->setTitle('Content Types');
        $types = ContentType::all(false);
        return $this->app->view->render('content/types/index', compact('types'));
    }

    public function typesCreate($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $this->app->setTitle('New Content Type');
        return $this->app->view->render('content/types/edit', ['type' => null, 'fields' => []]);
    }

    public function typesStore($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $type = new ContentType();
        $type->slug            = ContentType::slugify((string)($_POST['slug'] ?? $_POST['name'] ?? ''));
        $type->name            = trim((string)($_POST['name'] ?? ''));
        $type->description     = trim((string)($_POST['description'] ?? '')) ?: null;
        $type->icon            = (string)($_POST['icon'] ?? 'box');
        $type->isSingleton     = !empty($_POST['is_singleton'])     ? 1 : 0;
        $type->enableRevisions = !empty($_POST['enable_revisions']) ? 1 : 0;
        $type->enableDrafts    = !empty($_POST['enable_drafts'])    ? 1 : 0;
        $type->isActive        = 1;
        $type->createdAt       = date('Y-m-d H:i:s');
        $id = $type->save();

        ActivityLog::log('content.types.create', 'content_types', $id, "Created type {$type->slug}");
        $this->redirectAdmin('content/types/edit/' . $id);
    }

    public function typesEdit($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $id   = (int)($params[0] ?? 0);
        $row  = DB::findOne('content_types', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);
        $type = new ContentType($row);
        $this->app->setTitle('Edit Type: ' . $type->name);
        return $this->app->view->render('content/types/edit', [
            'type'   => $type,
            'fields' => $type->fields(),
        ]);
    }

    public function typesUpdate($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $id = (int)($params[0] ?? 0);
        DB::execute(
            'UPDATE content_types
                SET name = :n, description = :d, icon = :i,
                    is_singleton = :s, enable_revisions = :r, enable_drafts = :dr,
                    is_active = :a
              WHERE id = :id',
            [
                ':n'  => trim((string)($_POST['name'] ?? '')),
                ':d'  => trim((string)($_POST['description'] ?? '')) ?: null,
                ':i'  => (string)($_POST['icon'] ?? 'box'),
                ':s'  => !empty($_POST['is_singleton'])     ? 1 : 0,
                ':r'  => !empty($_POST['enable_revisions']) ? 1 : 0,
                ':dr' => !empty($_POST['enable_drafts'])    ? 1 : 0,
                ':a'  => isset($_POST['is_active']) ? (int)!!$_POST['is_active'] : 1,
                ':id' => $id,
            ]
        );
        ActivityLog::log('content.types.update', 'content_types', $id, 'Updated type');
        $this->redirectAdmin('content/types/edit/' . $id);
    }

    public function typesDelete($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $id = (int)($params[0] ?? 0);
        DB::execute('DELETE FROM content_types WHERE id = :id', [':id' => $id]);
        ActivityLog::log('content.types.delete', 'content_types', $id, 'Deleted type');
        $this->redirectAdmin('content/types');
    }

    // ──────────────────────────────────────────────────────────────────
    // Fields
    // ──────────────────────────────────────────────────────────────────

    public function fieldsStore($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $typeId = (int)($params[0] ?? 0);

        $field = new ContentField();
        $field->typeId    = $typeId;
        $field->key       = ContentType::slugify((string)($_POST['key'] ?? $_POST['label'] ?? ''));
        $field->label     = trim((string)($_POST['label'] ?? ''));
        $field->fieldType = in_array(($_POST['field_type'] ?? 'text'), ContentField::TYPES, true)
                            ? $_POST['field_type'] : 'text';
        $field->required  = !empty($_POST['required'])  ? 1 : 0;
        $field->localized = !empty($_POST['localized']) ? 1 : 0;
        $field->settings  = is_array($_POST['settings'] ?? null)
                            ? json_encode($_POST['settings'], JSON_UNESCAPED_UNICODE)
                            : (string)($_POST['settings'] ?? '{}');
        $field->sortOrder = (int)(DB::getCell(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM content_fields WHERE type_id = :t',
            [':t' => $typeId]
        ) ?? 1);
        $field->createdAt = date('Y-m-d H:i:s');
        $field->save();

        $this->redirectAdmin('content/types/edit/' . $typeId);
    }

    public function fieldsUpdate($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('content_fields', ' id = :id ', [':id' => $id]);
        if (!$row) $res->errorCode(404);

        DB::execute(
            'UPDATE content_fields
                SET label = :l, field_type = :ft, required = :r, localized = :loc, settings = :s
              WHERE id = :id',
            [
                ':l'   => trim((string)($_POST['label'] ?? $row['label'])),
                ':ft'  => in_array(($_POST['field_type'] ?? $row['field_type']), ContentField::TYPES, true)
                            ? $_POST['field_type'] : $row['field_type'],
                ':r'   => !empty($_POST['required'])  ? 1 : 0,
                ':loc' => !empty($_POST['localized']) ? 1 : 0,
                ':s'   => is_array($_POST['settings'] ?? null)
                            ? json_encode($_POST['settings'], JSON_UNESCAPED_UNICODE)
                            : (string)($_POST['settings'] ?? $row['settings']),
                ':id'  => $id,
            ]
        );
        $this->redirectAdmin('content/types/edit/' . (int)$row['type_id']);
    }

    public function fieldsDelete($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('content_fields', ' id = :id ', [':id' => $id]);
        if ($row) DB::execute('DELETE FROM content_fields WHERE id = :id', [':id' => $id]);
        $this->redirectAdmin('content/types/edit/' . (int)($row['type_id'] ?? 0));
    }

    public function fieldsReorder($req, $res, $params)
    {
        $this->requirePermission('content.types', $res);
        $typeId = (int)($params[0] ?? 0);
        $ids    = array_map('intval', (array)($_POST['ids'] ?? []));
        $order  = 0;
        foreach ($ids as $id) {
            DB::execute(
                'UPDATE content_fields SET sort_order = :o WHERE id = :id AND type_id = :t',
                [':o' => $order++, ':id' => $id, ':t' => $typeId]
            );
        }
        return $this->json($res, ['ok' => true]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Entries
    // ──────────────────────────────────────────────────────────────────

    public function entriesIndex($req, $res, $params)
    {
        $this->requirePermission('content.view', $res);
        $type = $this->resolveType($res, $params[0] ?? '');
        $this->app->setTitle($type->name);

        $entries = ContentEntry::listByType($type->id, [
            'status' => (string)($_GET['status'] ?? ''),
            'locale' => (string)($_GET['locale'] ?? ''),
            'q'      => (string)($_GET['q']      ?? ''),
            'limit'  => 100,
        ]);
        return $this->app->view->render('content/entries/index', compact('type', 'entries'));
    }

    public function entriesCreate($req, $res, $params)
    {
        $this->requirePermission('content.create', $res);
        $type = $this->resolveType($res, $params[0] ?? '');
        $this->app->setTitle('New ' . $type->name);
        return $this->app->view->render('content/entries/edit', [
            'type'   => $type,
            'entry'  => null,
            'fields' => $type->fields(),
        ]);
    }

    public function entriesStore($req, $res, $params)
    {
        $this->requirePermission('content.create', $res);
        $type = $this->resolveType($res, $params[0] ?? '');
        $entry = ContentEntry::persist($type, $this->collectEntryPayload());
        $this->persistRelations($entry, $type);
        $this->redirectAdmin('content/entries/' . $type->slug . '/edit/' . $entry->id);
    }

    public function entriesEdit($req, $res, $params)
    {
        $this->requirePermission('content.update', $res);
        $type  = $this->resolveType($res, $params[0] ?? '');
        $id    = (int)($params[1] ?? 0);
        $entry = ContentEntry::findById($id);
        if (!$entry || $entry->typeId !== $type->id) $res->errorCode(404);
        $this->app->setTitle('Edit ' . $type->name);
        return $this->app->view->render('content/entries/edit', [
            'type'   => $type,
            'entry'  => $entry,
            'fields' => $type->fields(),
        ]);
    }

    public function entriesUpdate($req, $res, $params)
    {
        $this->requirePermission('content.update', $res);
        $type  = $this->resolveType($res, $params[0] ?? '');
        $id    = (int)($params[1] ?? 0);
        $entry = ContentEntry::persist($type, $this->collectEntryPayload(), $id);
        $this->persistRelations($entry, $type);
        $this->redirectAdmin('content/entries/' . $type->slug . '/edit/' . $entry->id);
    }

    public function entriesDelete($req, $res, $params)
    {
        $this->requirePermission('content.delete', $res);
        $type = $this->resolveType($res, $params[0] ?? '');
        $id   = (int)($params[1] ?? 0);
        ContentEntry::deleteById($id);
        $this->redirectAdmin('content/entries/' . $type->slug);
    }

    public function entriesPreview($req, $res, $params)
    {
        $this->requirePermission('content.view', $res);
        $type  = $this->resolveType($res, $params[0] ?? '');
        $id    = (int)($params[1] ?? 0);
        $token = PreviewToken::create('content_entries', $id, 3600);
        $lang  = $this->app->router->getLanguage();
        $url   = "/{$lang}/preview/content/{$type->slug}/{$id}?token={$token}";
        return $this->json($res, ['ok' => true, 'token' => $token, 'url' => $url]);
    }

    public function entriesRevisions($req, $res, $params)
    {
        $this->requirePermission('content.view', $res);
        $id   = (int)($params[1] ?? 0);
        $list = Revisions::list('content_entries', $id, 50);
        return $this->json($res, ['ok' => true, 'revisions' => $list]);
    }

    public function entriesRestore($req, $res, $params)
    {
        $this->requirePermission('content.update', $res);
        $type = $this->resolveType($res, $params[0] ?? '');
        $rev  = (int)($params[1] ?? 0);
        $data = Revisions::restore($rev);
        if (!$data) $res->errorCode(404);

        // The snapshot includes the original entry id via the revision row's entity_id.
        $row = Revisions::get($rev);
        if (!$row) $res->errorCode(404);

        ContentEntry::persist($type, [
            'slug'         => (string)($data['slug'] ?? ''),
            'status'       => (string)($data['status'] ?? 'draft'),
            'locale'       => (string)($data['locale'] ?? 'en'),
            'data'         => is_array($data['data'] ?? null) ? $data['data'] : [],
            'published_at' => $data['published_at'] ?? null,
        ], (int)$row['entity_id']);

        return $this->json($res, ['ok' => true]);
    }

    // ──────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────

    private function collectEntryPayload(): array
    {
        return [
            'slug'         => (string)($_POST['slug']         ?? ''),
            'status'       => (string)($_POST['status']       ?? 'draft'),
            'locale'       => (string)($_POST['locale']       ?? 'en'),
            'sort_order'   => (int)   ($_POST['sort_order']   ?? 0),
            'published_at' => (string)($_POST['published_at'] ?? '') ?: null,
            'data'         => is_array($_POST['data'] ?? null) ? $_POST['data'] : [],
        ];
    }

    private function persistRelations(ContentEntry $entry, ContentType $type): void
    {
        if (!$entry->id) return;
        foreach ($type->fields() as $fRow) {
            if ($fRow['field_type'] !== 'relation') continue;
            $key  = (string)$fRow['key'];
            $vals = $_POST['data'][$key] ?? [];
            $ids  = is_array($vals) ? array_map('intval', array_filter($vals))
                                    : (is_numeric($vals) ? [(int)$vals] : []);
            ContentRelationStore::set($entry->id, $key, $ids);
        }
    }

    private function resolveType($res, string $slug): ContentType
    {
        $type = ContentType::findBySlug($slug);
        if (!$type) $res->errorCode(404);
        return $type;
    }

    private function requirePermission(string $perm, $res): void
    {
        if (class_exists('Permission')) {
            if (!Permission::can($perm)) $res->errorCode(403);
        } else {
            $this->requireAdmin($res);
        }
    }

    private function redirectAdmin(string $path): void
    {
        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/' . ltrim($path, '/'));
        exit;
    }

    private function json($res, array $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
