-- Role-based access control.
--
-- Built around three concepts:
--   role         — named bundle of permissions ("admin", "editor", "author", "viewer")
--   permission   — a "{module}.{action}" string ("pages.create", "media.delete", …)
--   user_roles   — N:M between users and roles
--
-- A user's effective permissions are the union of permissions of all their roles.
-- The Permission helper (lib/permission.class.php) caches per-request lookups.

CREATE TABLE IF NOT EXISTS `roles` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `slug`          VARCHAR(64)   NOT NULL,
    `name`          VARCHAR(120)  NOT NULL,
    `description`   VARCHAR(500)  NULL,
    `is_system`     TINYINT(1)    NOT NULL DEFAULT 0,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `slug`          VARCHAR(120)  NOT NULL,
    `module`        VARCHAR(60)   NOT NULL,
    `action`        VARCHAR(60)   NOT NULL,
    `description`   VARCHAR(500)  NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_permissions_slug` (`slug`),
    KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id`       INT UNSIGNED  NOT NULL,
    `permission_id` INT UNSIGNED  NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    KEY `idx_role_permissions_perm` (`permission_id`),
    CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`)       REFERENCES `roles`(`id`)       ON DELETE CASCADE,
    CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
    `user_id`       INT UNSIGNED  NOT NULL,
    `role_id`       INT UNSIGNED  NOT NULL,
    `assigned_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role_id`),
    KEY `idx_user_roles_role` (`role_id`),
    CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed system roles. Admin gets everything by convention (Permission::can() shortcut).
INSERT IGNORE INTO `roles` (slug, name, description, is_system) VALUES
    ('admin',  'Administrator', 'Full access to the entire system',  1),
    ('editor', 'Editor',        'Manage all content; cannot manage users or settings', 1),
    ('author', 'Author',        'Create and edit own content', 1),
    ('viewer', 'Viewer',        'Read-only access to admin', 1);

-- Seed core permissions. Plugins add their own via plugin install hook.
INSERT IGNORE INTO `permissions` (slug, module, action, description) VALUES
    ('pages.view',     'pages',    'view',    'View pages'),
    ('pages.create',   'pages',    'create',  'Create pages'),
    ('pages.update',   'pages',    'update',  'Edit pages'),
    ('pages.delete',   'pages',    'delete',  'Delete pages'),
    ('pages.publish',  'pages',    'publish', 'Publish pages'),

    ('blog.view',      'blog',     'view',    'View blog posts'),
    ('blog.create',    'blog',     'create',  'Create blog posts'),
    ('blog.update',    'blog',     'update',  'Edit blog posts'),
    ('blog.delete',    'blog',     'delete',  'Delete blog posts'),
    ('blog.publish',   'blog',     'publish', 'Publish blog posts'),

    ('media.view',     'media',    'view',    'Browse media library'),
    ('media.upload',   'media',    'upload',  'Upload media'),
    ('media.update',   'media',    'update',  'Edit media metadata'),
    ('media.delete',   'media',    'delete',  'Delete media'),

    ('users.view',     'users',    'view',    'View users'),
    ('users.create',   'users',    'create',  'Create users'),
    ('users.update',   'users',    'update',  'Edit users'),
    ('users.delete',   'users',    'delete',  'Delete users'),

    ('settings.view',   'settings', 'view',   'View settings'),
    ('settings.update', 'settings', 'update', 'Update settings'),

    ('plugins.view',    'plugins',  'view',    'View plugins list'),
    ('plugins.install', 'plugins',  'install', 'Install plugins'),
    ('plugins.toggle',  'plugins',  'toggle',  'Enable/disable plugins'),

    ('content.view',    'content',  'view',    'View content types and entries'),
    ('content.create',  'content',  'create',  'Create content'),
    ('content.update',  'content',  'update',  'Edit content'),
    ('content.delete',  'content',  'delete',  'Delete content'),
    ('content.publish', 'content',  'publish', 'Publish content'),
    ('content.types',   'content',  'types',   'Define content types and fields');

-- Default role bindings. Admin is wired in code (Permission::can() returns true for admins).
INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id
  FROM `roles` r
  JOIN `permissions` p ON 1=1
 WHERE r.slug = 'editor'
   AND p.slug IN (
       'pages.view','pages.create','pages.update','pages.delete','pages.publish',
       'blog.view','blog.create','blog.update','blog.delete','blog.publish',
       'media.view','media.upload','media.update','media.delete',
       'content.view','content.create','content.update','content.delete','content.publish'
   );

INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id
  FROM `roles` r
  JOIN `permissions` p ON 1=1
 WHERE r.slug = 'author'
   AND p.slug IN (
       'pages.view','pages.create','pages.update',
       'blog.view','blog.create','blog.update',
       'media.view','media.upload','media.update',
       'content.view','content.create','content.update'
   );

INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id
  FROM `roles` r
  JOIN `permissions` p ON 1=1
 WHERE r.slug = 'viewer'
   AND p.slug IN (
       'pages.view','blog.view','media.view','content.view'
   );
