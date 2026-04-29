<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Permission — RBAC helper.
 *
 *   Permission::can('pages.update');                  // current user
 *   Permission::can('pages.update', $userId);         // explicit user
 *   Permission::userPermissions($userId);             // ['pages.view', …]
 *   Permission::userRoles($userId);                   // ['admin']
 *   Permission::syncRoles($userId, ['editor','author']);
 *
 * Admins (legacy `users.role = 'admin'`) and members of role "admin" always pass.
 *
 * Per-request cache prevents repeated joins on the same user.
 */
class Permission
{
    /** @var array<int,array<string,bool>> [userId => [perm => true]] */
    private static array $cache = [];

    public static function can(string $permission, ?int $userId = null): bool
    {
        $userId ??= self::currentUserId();
        if (!$userId) return false;

        // Admins (system role or legacy users.role) bypass.
        if (self::isAdmin($userId)) return true;

        if (!isset(self::$cache[$userId])) {
            self::$cache[$userId] = array_fill_keys(self::userPermissions($userId), true);
        }
        return isset(self::$cache[$userId][$permission]);
    }

    public static function isAdmin(?int $userId = null): bool
    {
        $userId ??= self::currentUserId();
        if (!$userId) return false;

        // Legacy users.role column.
        $role = DB::getCell('SELECT role FROM users WHERE id = :id', [':id' => $userId]);
        if ($role === 'admin') return true;

        // New: membership in role 'admin'.
        $cnt = DB::getCell(
            'SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :u AND r.slug = "admin"',
            [':u' => $userId]
        );
        return (int)$cnt > 0;
    }

    /** @return array<int,string> permission slugs */
    public static function userPermissions(int $userId): array
    {
        $rows = DB::getAll(
            'SELECT DISTINCT p.slug
               FROM user_roles ur
               JOIN role_permissions rp ON rp.role_id = ur.role_id
               JOIN permissions p       ON p.id      = rp.permission_id
              WHERE ur.user_id = :u',
            [':u' => $userId]
        ) ?: [];
        return array_column($rows, 'slug');
    }

    /** @return array<int,string> role slugs */
    public static function userRoles(int $userId): array
    {
        $rows = DB::getAll(
            'SELECT r.slug FROM user_roles ur
               JOIN roles r ON r.id = ur.role_id
              WHERE ur.user_id = :u',
            [':u' => $userId]
        ) ?: [];
        return array_column($rows, 'slug');
    }

    /** Replace all roles of a user with the given list (by slug). */
    public static function syncRoles(int $userId, array $roleSlugs): void
    {
        DB::execute('DELETE FROM user_roles WHERE user_id = :u', [':u' => $userId]);
        if (!$roleSlugs) { unset(self::$cache[$userId]); return; }

        $placeholders = implode(',', array_fill(0, count($roleSlugs), '?'));
        $ids = DB::getAll(
            "SELECT id FROM roles WHERE slug IN ({$placeholders})",
            array_values($roleSlugs)
        ) ?: [];
        foreach ($ids as $row) {
            DB::execute(
                'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:u, :r)',
                [':u' => $userId, ':r' => (int)$row['id']]
            );
        }
        unset(self::$cache[$userId]);
    }

    private static function currentUserId(): ?int
    {
        if (!class_exists('Auth')) return null;
        $u = Auth::getCurrentUser();
        return $u && isset($u->id) ? (int)$u->id : null;
    }

    public static function clearCache(): void { self::$cache = []; }
}
