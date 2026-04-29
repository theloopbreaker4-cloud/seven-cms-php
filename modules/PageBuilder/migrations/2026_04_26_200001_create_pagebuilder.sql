-- Page builder.
--
-- Stores block trees per "page" (which can be a normal CMS page, a CCT entry,
-- or any other entity addressed by `entity_type` + `entity_id`).
--
-- Each block has a parent_id so we get a tree. `block_type` matches a key in
-- the BlockTypes registry. `data` is per-block JSON; the type defines the schema.
-- `sort_order` orders siblings.

CREATE TABLE IF NOT EXISTS `pb_blocks` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `entity_type`  VARCHAR(64)   NOT NULL,
    `entity_id`    INT UNSIGNED  NOT NULL,
    `parent_id`    INT UNSIGNED  NULL,
    `block_type`   VARCHAR(80)   NOT NULL,
    `data`         JSON          NOT NULL DEFAULT ('{}'),
    `sort_order`   INT           NOT NULL DEFAULT 0,
    `is_visible`   TINYINT(1)    NOT NULL DEFAULT 1,
    `slot`         VARCHAR(60)   NOT NULL DEFAULT 'default',
    `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                   ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pb_block_entity` (`entity_type`, `entity_id`, `parent_id`, `sort_order`),
    KEY `idx_pb_block_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (slug, module, action, description) VALUES
    ('pagebuilder.view',    'pagebuilder', 'view',    'View page builder'),
    ('pagebuilder.edit',    'pagebuilder', 'edit',    'Edit blocks'),
    ('pagebuilder.publish', 'pagebuilder', 'publish', 'Publish block changes');

INSERT IGNORE INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id FROM `roles` r JOIN `permissions` p ON 1=1
 WHERE r.slug = 'editor'
   AND p.slug IN ('pagebuilder.view','pagebuilder.edit','pagebuilder.publish');
