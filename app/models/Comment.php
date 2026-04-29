<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

class Comment extends Model
{
    public ?int    $id         = null;
    public ?int    $postId     = null;
    public ?int    $userId     = null;   // null = guest
    public ?int    $parentId   = null;   // threaded replies
    public string  $authorName = '';
    public string  $authorEmail = '';
    public string  $content    = '';
    public string  $status     = 'pending'; // 'pending' | 'approved' | 'spam'
    public ?string $createdAt  = null;

    public function __construct($data = []) {
        parent::__construct();
        if ($data) $this->setModel($data);
    }

    public function forPost(int $postId, string $status = 'approved'): array {
        return DB::find(
            $this->tableName,
            ' post_id = :pid AND status = :s ORDER BY created_at ASC ',
            [':pid' => $postId, ':s' => $status]
        ) ?: [];
    }

    public function getPending(): array {
        return DB::find($this->tableName, ' status = "pending" ORDER BY created_at DESC ') ?: [];
    }

    public function countForPost(int $postId): int {
        return (int) DB::getCell(
            'SELECT COUNT(*) FROM `comment` WHERE post_id = :pid AND status = "approved"',
            [':pid' => $postId]
        );
    }

    public function approve(): void {
        $this->status = 'approved';
        $this->save($this->id);
    }

    public function spam(): void {
        $this->status = 'spam';
        $this->save($this->id);
    }

    public function save($editId = null, $prop = null): ?int {
        $this->createdAt = $this->createdAt ?: date('Y-m-d H:i:s');
        return parent::save($editId, $prop);
    }

    public function toArray(): array {
        return [
            'id'          => $this->id,
            'postId'      => $this->postId,
            'userId'      => $this->userId,
            'parentId'    => $this->parentId,
            'authorName'  => $this->authorName,
            'authorEmail' => $this->authorEmail,
            'content'     => $this->content,
            'status'      => $this->status,
            'createdAt'   => $this->createdAt,
        ];
    }
}
