<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Users API (admin, Bearer token required)
 *
 * GET    /api/user/index            — list all users
 * GET    /api/user/show/{id}        — single user
 * PUT    /api/user/update/{id}      — update user
 * DELETE /api/user/delete/{id}      — delete user
 */
class UserApiController extends ApiController
{
    public function index($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $rows  = DB::getAll('SELECT * FROM user ORDER BY created_at DESC');
        $users = [];
        foreach ($rows as $row) {
            $u = new User();
            $u->setModel($row);
            $users[] = $u->toPublic();
        }
        Logger::channel('app')->debug('API users list', ['count' => count($users), 'adminId' => $admin->id]);
        return json_encode($users);
    }

    public function show($req, $res, $params)
    {
        $this->requireAdminToken();
        $id = (int)($params[0] ?? 0);
        $u  = new User();
        $u->getOne($id);
        if (!$u->id) {
            Logger::channel('app')->warn('API user not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }
        return json_encode($u->toPublic());
    }

    public function update($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $id    = (int)($params[0] ?? 0);
        $data  = $this->body();
        $u     = new User();
        $u->getOne($id);
        if (!$u->id) {
            Logger::channel('app')->warn('API user update: not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }

        $allowed = ['firstName', 'lastName', 'userName', 'mobile', 'country', 'avatar', 'role', 'isActive'];
        $changed = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                if ($field === 'role' && !in_array($data[$field], ['user', 'admin'])) continue;
                $u->$field = $data[$field];
                $changed[] = $field;
            }
        }
        $u->updatedAt = date('Y-m-d H:i:s');
        $u->save($id);
        Logger::channel('app')->info('API user updated', ['id' => $id, 'changed' => $changed, 'adminId' => $admin->id]);
        $u->getOne($id);
        return json_encode($u->toPublic());
    }

    public function delete($req, $res, $params)
    {
        $admin = $this->requireAdminToken();
        $id    = (int)($params[0] ?? 0);
        $ip    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if ($admin->id === $id) {
            Logger::channel('auth')->warn('API user delete self attempt', ['adminId' => $admin->id, 'ip' => $ip]);
            $res->jsonError(400, 'Cannot delete yourself');
        }
        $u = new User();
        $u->getOne($id);
        if (!$u->id) {
            Logger::channel('app')->warn('API user delete: not found', ['id' => $id]);
            $res->jsonError(404, 'Not found');
        }
        $email = $u->email;
        $u->remove($id);
        Logger::channel('auth')->info('API user deleted', ['id' => $id, 'email' => $email, 'adminId' => $admin->id, 'ip' => $ip]);
        http_response_code(204);
        return '';
    }
}
