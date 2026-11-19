<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * Public comments API.
 *
 * GET  /api/comment/post/{postId}  — approved comments of a published post (no emails)
 * POST /api/comment/store          — { postId, name, email, content, website } → pending
 *
 * "website" is a honeypot: humans never see it, bots fill it in.
 */
class CommentApiController extends ApiController
{
    public function post($req, $res, $params)
    {
        $postId = (int)($params[0] ?? 0);
        if (!$this->publishedPost($postId)) $res->jsonError(404, 'Not found');

        $rows = DB::getAll(
            'SELECT id, author_name, content, created_at FROM comment
              WHERE post_id = :p AND status = "approved" ORDER BY created_at ASC LIMIT 500',
            [':p' => $postId]
        ) ?: [];
        return json_encode(array_map(fn($r) => [
            'id'         => (int)$r['id'],
            'authorName' => (string)$r['author_name'],
            'content'    => (string)$r['content'],
            'createdAt'  => (string)$r['created_at'],
        ], $rows), JSON_UNESCAPED_UNICODE);
    }

    public function store($req, $res, $params)
    {
        if (!$req->isMethod('POST')) $res->jsonError(405, 'Method Not Allowed');
        if (!(bool)(Setting::get('comments_on') ?? true)) $res->jsonError(403, 'Comments are closed.');
        RateLimit::check('comment');

        $body    = $this->body();
        $postId  = (int)($body['postId'] ?? 0);
        $name    = trim(strip_tags((string)($body['name'] ?? '')));
        $email   = strtolower(trim((string)($body['email'] ?? '')));
        $content = trim(strip_tags((string)($body['content'] ?? '')));

        if (!empty($body['website'])) {
            // Pretend success so bots don't learn about the trap.
            http_response_code(201);
            return json_encode(['status' => 'pending']);
        }
        if (!$this->publishedPost($postId))                     $res->jsonError(404, 'Post not found.');
        if ($name === '' || mb_strlen($name) > 80)              $res->jsonError(422, 'Name is required (max 80 characters).');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $res->jsonError(422, 'A valid email is required.');
        $len = mb_strlen($content);
        if ($len < 2 || $len > 2000)                            $res->jsonError(422, 'Comment must be 2–2000 characters.');

        $user = Auth::getCurrentUser();
        DB::execute(
            'INSERT INTO comment (post_id, user_id, author_name, author_email, content, status, ip)
             VALUES (:p, :u, :n, :e, :c, "pending", :ip)',
            [
                ':p' => $postId, ':u' => $user?->id, ':n' => $name, ':e' => $email,
                ':c' => $content, ':ip' => RateLimit::clientIp(),
            ]
        );
        RateLimit::hit('comment');

        $lang = preg_match('/^[a-z]{2}$/', (string)($body['lang'] ?? '')) ? $body['lang'] : 'en';
        Notify::admins('comment.pending', [
            'title'   => 'New comment awaiting approval',
            'message' => mb_substr($name . ': ' . $content, 0, 140),
            'url'     => '/' . $lang . '/admin/comment',
            'icon'    => '💬',
        ]);

        http_response_code(201);
        return json_encode(['status' => 'pending']);
    }

    private function publishedPost(int $id): bool
    {
        return $id > 0 && (int)DB::getCell('SELECT COUNT(*) FROM post WHERE id = :id AND is_published = 1', [':id' => $id]) === 1;
    }
}
