<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * MediaAdminController — admin endpoints for the Media library.
 *
 * Endpoints:
 *   GET  /admin/media                  — list with folder/mime/q filters
 *   POST /admin/media/upload           — drag&drop multi-upload (JSON)
 *   POST /admin/media/update/:id       — update title/alt/description (JSON)
 *   GET  /admin/media/delete/:id       — delete one (HTML redirect or JSON for XHR)
 *   POST /admin/media/bulk-delete      — delete many (JSON)
 *   POST /admin/media/folder/create    — create folder (JSON)
 *   GET  /admin/media/folder/delete/:id — delete empty folder
 */
class MediaAdminController extends Controller
{
    public function __construct($app) { parent::__construct($app); }

    public function index($req, $res, $params)
    {
        $this->requireAdmin($res);
        $this->app->setTitle(AdminLang::t('media', 'nav'));

        $folderId = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
        $mime     = isset($_GET['mime'])   ? (string)$_GET['mime']  : '';
        $search   = isset($_GET['q'])      ? (string)$_GET['q']     : '';

        $where = [];
        $args  = [];
        if ($folderId !== null && $folderId > 0) { $where[] = 'folder_id = :fid'; $args[':fid'] = $folderId; }
        if ($mime !== '')                        { $where[] = 'mime_type LIKE :m'; $args[':m']  = $mime . '%'; }
        if ($search !== '')                      { $where[] = '(original_name LIKE :q OR title LIKE :q)'; $args[':q'] = '%' . $search . '%'; }

        $sql = 'SELECT * FROM media';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC LIMIT 500';

        $media   = DB::getAll($sql, $args) ?: [];
        $folders = class_exists('MediaFolder') ? MediaFolder::tree() : [];

        return $this->app->view->render('index', compact('media', 'folders', 'folderId', 'mime', 'search'));
    }

    public function upload($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $admin    = Auth::getCurrentUser();
        $folderId = isset($_POST['folder_id']) && $_POST['folder_id'] !== '' ? (int)$_POST['folder_id'] : null;

        $files = $this->normalizeFiles($_FILES['files'] ?? $_FILES['file'] ?? null);
        if (!$files) return $this->json($res, ['ok' => false, 'message' => 'No files received'], 400);

        $created = [];
        $failed  = [];
        foreach ($files as $f) {
            $media = new Media();
            $id    = $media->upload($f, (int)($admin->id ?? 0), $folderId);
            if ($id) {
                if (class_exists('Event')) Event::dispatch('media.afterCreate', $media);
                Logger::channel('app')->info('Media uploaded', ['id' => $id, 'name' => $f['name']]);
                $created[] = $media->toArray();
            } else {
                $failed[] = ['name' => $f['name'], 'reason' => 'rejected'];
            }
        }

        return $this->json($res, ['ok' => true, 'created' => $created, 'failed' => $failed]);
    }

    public function update($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('media', ' id = :id ', [':id' => $id]);
        if (!$row) return $this->json($res, ['ok' => false, 'message' => 'Not found'], 404);

        $title       = trim((string)($_POST['title'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $altRaw      = $_POST['alt'] ?? '{}';
        $alt         = is_array($altRaw)
            ? json_encode($altRaw, JSON_UNESCAPED_UNICODE)
            : (json_decode((string)$altRaw, true) !== null ? (string)$altRaw : '{}');

        DB::execute(
            'UPDATE media SET title = :t, description = :d, alt = :a WHERE id = :id',
            [':t' => $title, ':d' => $description ?: null, ':a' => $alt, ':id' => $id]
        );
        if (class_exists('Event')) Event::dispatch('media.afterUpdate', ['id' => $id]);

        return $this->json($res, ['ok' => true]);
    }

    public function delete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('media', ' id = :id ', [':id' => $id]);
        if ($row) {
            (new Media($row))->deleteFile();
            \R::trash($row);
            if (class_exists('Event')) Event::dispatch('media.afterDelete', ['id' => $id]);
        }
        if ($req->isXhr()) return $this->json($res, ['ok' => true]);

        $lang = $this->app->router->getLanguage();
        header('Location: /' . $lang . '/admin/media');
        exit;
    }

    public function bulkDelete($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $ids = array_filter($ids, fn($x) => $x > 0);
        if (!$ids) return $this->json($res, ['ok' => false, 'message' => 'No ids'], 400);

        $deleted = 0;
        foreach ($ids as $id) {
            $row = DB::findOne('media', ' id = :id ', [':id' => $id]);
            if (!$row) continue;
            (new Media($row))->deleteFile();
            \R::trash($row);
            $deleted++;
        }
        if (class_exists('Event')) Event::dispatch('media.afterBulkDelete', ['ids' => $ids]);

        return $this->json($res, ['ok' => true, 'deleted' => $deleted]);
    }

    public function folderCreate($req, $res, $params)
    {
        $this->requireAdmin($res);
        if (!$req->isMethod('POST')) $res->errorCode(405);

        $name     = trim((string)($_POST['name'] ?? ''));
        $parentId = $_POST['parent_id'] ?? null;
        $parentId = ($parentId === '' || $parentId === null) ? null : (int)$parentId;

        $folder = new MediaFolder();
        $id     = $folder->create($name, $parentId);
        if (!$id) return $this->json($res, ['ok' => false, 'message' => 'Could not create'], 422);

        return $this->json($res, ['ok' => true, 'folder' => $folder->toArray()]);
    }

    public function folderDelete($req, $res, $params)
    {
        $this->requireAdmin($res);
        $id  = (int)($params[0] ?? 0);
        $row = DB::findOne('media_folder', ' id = :id ', [':id' => $id]);
        if (!$row) return $this->json($res, ['ok' => false, 'message' => 'Not found'], 404);

        // Refuse if anything is inside.
        $count = DB::getCell(
            'SELECT (SELECT COUNT(*) FROM media WHERE folder_id = :id)
                  + (SELECT COUNT(*) FROM media_folder WHERE parent_id = :id)',
            [':id' => $id]
        );
        if ((int)$count > 0) return $this->json($res, ['ok' => false, 'message' => 'Folder not empty'], 409);

        \R::trash($row);
        return $this->json($res, ['ok' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────

    private function normalizeFiles($input): array
    {
        if (!$input) return [];
        if (isset($input['name']) && !is_array($input['name'])) return [$input];

        $count = count($input['name'] ?? []);
        $out   = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = [
                'name'     => $input['name'][$i]     ?? '',
                'type'     => $input['type'][$i]     ?? '',
                'tmp_name' => $input['tmp_name'][$i] ?? '',
                'error'    => $input['error'][$i]    ?? UPLOAD_ERR_NO_FILE,
                'size'     => $input['size'][$i]     ?? 0,
            ];
        }
        return $out;
    }

    private function json($res, array $data, int $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
