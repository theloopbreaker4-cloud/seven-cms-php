<?php

defined('_SEVEN') or die('No direct script access allowed');

class BlogModule implements ModuleInterface
{
    public function getName(): string { return 'Blog'; }

    public function boot(): void
    {
        Event::on('post.published', function(array $data): void {
            Logger::channel('app')->info('Blog: post published', ['id' => $data['id'] ?? null]);
        });
    }

    public function routes(): array
    {
        return [
            // Site
            'blog.index'   => ['controller' => 'blog', 'action' => 'index'],
            'blog.show'    => ['controller' => 'blog', 'action' => 'show', 'params' => [':slug']],
            // Admin — Posts
            'admin.posts'        => ['controller' => 'blog', 'action' => 'index',  'prefix' => 'admin'],
            'admin.blog.create'  => ['controller' => 'blog', 'action' => 'create', 'prefix' => 'admin'],
            'admin.blog.store'   => ['controller' => 'blog', 'action' => 'store',  'prefix' => 'admin'],
            'admin.blog.edit'    => ['controller' => 'blog', 'action' => 'edit',   'prefix' => 'admin'],
            'admin.blog.update'  => ['controller' => 'blog', 'action' => 'update', 'prefix' => 'admin'],
            'admin.blog.delete'  => ['controller' => 'blog', 'action' => 'delete', 'prefix' => 'admin'],
            // Admin — Categories
            'admin.categories'       => ['controller' => 'category', 'action' => 'index',  'prefix' => 'admin'],
            'admin.category.create'  => ['controller' => 'category', 'action' => 'create', 'prefix' => 'admin'],
            'admin.category.store'   => ['controller' => 'category', 'action' => 'store',  'prefix' => 'admin'],
            'admin.category.edit'    => ['controller' => 'category', 'action' => 'edit',   'prefix' => 'admin'],
            'admin.category.update'  => ['controller' => 'category', 'action' => 'update', 'prefix' => 'admin'],
            'admin.category.delete'  => ['controller' => 'category', 'action' => 'delete', 'prefix' => 'admin'],
            // Admin — Tags
            'admin.tags'        => ['controller' => 'tag', 'action' => 'index',  'prefix' => 'admin'],
            'admin.tag.create'  => ['controller' => 'tag', 'action' => 'create', 'prefix' => 'admin'],
            'admin.tag.store'   => ['controller' => 'tag', 'action' => 'store',  'prefix' => 'admin'],
            'admin.tag.edit'    => ['controller' => 'tag', 'action' => 'edit',   'prefix' => 'admin'],
            'admin.tag.update'  => ['controller' => 'tag', 'action' => 'update', 'prefix' => 'admin'],
            'admin.tag.delete'  => ['controller' => 'tag', 'action' => 'delete', 'prefix' => 'admin'],
            // Admin — Comments
            'admin.comments'        => ['controller' => 'comment', 'action' => 'index',   'prefix' => 'admin'],
            'admin.comment.approve' => ['controller' => 'comment', 'action' => 'approve', 'prefix' => 'admin'],
            'admin.comment.delete'  => ['controller' => 'comment', 'action' => 'delete',  'prefix' => 'admin'],
        ];
    }
}
