<?php
/** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */

defined('_SEVEN') or die('No direct script access allowed');

/**
 * GraphQLSchema — declarative schema for SevenCMS.
 *
 * Each top-level "type" is an array. Field entries have:
 *   - 'type'    string         — '"String"', '"Int"', '"Page"', '"[Page]"', '"Page!"'…
 *   - 'resolve' callable|null  — function ($parent, $args) returning the field value
 *
 * Plugins extend the schema by listening on `graphql.schema` and modifying the
 * passed-by-reference array. Example:
 *
 *   Event::listen('graphql.schema', function (&$schema) {
 *       $schema['Query']['reviews'] = [
 *           'type'    => '[Review]',
 *           'resolve' => fn($p, $a) => DB::getAll('SELECT * FROM reviews LIMIT 50'),
 *       ];
 *       $schema['Review'] = [ '__name' => 'Review',
 *           'id'     => ['type' => 'Int'],
 *           'rating' => ['type' => 'Int'],
 *           'body'   => ['type' => 'String'],
 *       ];
 *   });
 */
class GraphQLSchema
{
    public static function build(): array
    {
        $schema = [
            // Top-level Query
            'Query' => [
                '__name' => 'Query',

                'me' => [
                    'type'    => 'User',
                    'resolve' => function ($_, $args) {
                        if (!class_exists('Auth')) return null;
                        $u = Auth::getCurrentUser();
                        return $u && isset($u->id) ? self::userToArray((int)$u->id) : null;
                    },
                ],
                'user' => [
                    'type'    => 'User',
                    'resolve' => fn($_, $args) => self::userToArray((int)($args['id'] ?? 0)),
                ],

                // Pages
                'page' => [
                    'type'    => 'Page',
                    'resolve' => function ($_, $args) {
                        $where = ['is_published = 1'];
                        $params = [];
                        if (!empty($args['id']))   { $where[] = 'id = :id';     $params[':id']  = (int)$args['id']; }
                        if (!empty($args['slug'])) { $where[] = 'slug = :slug'; $params[':slug'] = (string)$args['slug']; }
                        $sql = 'SELECT * FROM page WHERE ' . implode(' AND ', $where) . ' LIMIT 1';
                        return DB::findOne('page', implode(' AND ', $where), $params) ?: null;
                    },
                ],
                'pages' => [
                    'type'    => '[Page]',
                    'resolve' => function ($_, $args) {
                        $limit = max(1, min(100, (int)($args['limit'] ?? 20)));
                        return DB::getAll(
                            'SELECT * FROM page WHERE is_published = 1 ORDER BY id DESC LIMIT ' . $limit
                        ) ?: [];
                    },
                ],

                // Blog
                'post' => [
                    'type'    => 'Post',
                    'resolve' => function ($_, $args) {
                        $where = ['is_published = 1']; $params = [];
                        if (!empty($args['slug'])) { $where[] = 'slug = :slug'; $params[':slug'] = (string)$args['slug']; }
                        if (!empty($args['id']))   { $where[] = 'id = :id';     $params[':id']   = (int)$args['id']; }
                        return DB::findOne('post', implode(' AND ', $where), $params) ?: null;
                    },
                ],
                'posts' => [
                    'type'    => '[Post]',
                    'resolve' => function ($_, $args) {
                        $limit = max(1, min(100, (int)($args['limit'] ?? 20)));
                        return DB::getAll('SELECT * FROM post WHERE is_published = 1 ORDER BY id DESC LIMIT ' . $limit) ?: [];
                    },
                ],

                // Custom Content Types (only when Content plugin is installed)
                'contentEntry' => [
                    'type'    => 'ContentEntry',
                    'resolve' => function ($_, $args) {
                        if (!class_exists('ContentEntry') || !class_exists('ContentType')) return null;
                        $type = ContentType::findBySlug((string)($args['type'] ?? ''));
                        if (!$type) return null;
                        $entry = ContentEntry::findBySlug(
                            (int)$type->id,
                            (string)($args['slug']   ?? ''),
                            (string)($args['locale'] ?? 'en')
                        );
                        return $entry ? $entry->toArray() : null;
                    },
                ],
                'contentEntries' => [
                    'type'    => '[ContentEntry]',
                    'resolve' => function ($_, $args) {
                        if (!class_exists('ContentEntry') || !class_exists('ContentType')) return [];
                        $type = ContentType::findBySlug((string)($args['type'] ?? ''));
                        if (!$type) return [];
                        $rows = ContentEntry::listByType((int)$type->id, [
                            'status' => 'published',
                            'locale' => (string)($args['locale'] ?? ''),
                            'q'      => (string)($args['q']      ?? ''),
                            'limit'  => (int)($args['limit']     ?? 50),
                            'offset' => (int)($args['offset']    ?? 0),
                        ]);
                        $out = [];
                        foreach ($rows as $r) $out[] = (new ContentEntry($r))->toArray();
                        return $out;
                    },
                ],

                // E-commerce (when Ecom plugin installed)
                'product' => [
                    'type'    => 'Product',
                    'resolve' => function ($_, $args) {
                        if (!class_exists('Product')) return null;
                        $p = Product::findBySlug((string)($args['slug'] ?? ''));
                        return $p ? $p->toArray((string)($args['locale'] ?? 'en')) : null;
                    },
                ],
                'products' => [
                    'type'    => '[Product]',
                    'resolve' => function ($_, $args) {
                        if (!class_exists('Product')) return [];
                        $rows = Product::listPublic([
                            'category_id' => isset($args['category']) ? (int)$args['category'] : null,
                            'kind'        => (string)($args['kind']  ?? ''),
                            'q'           => (string)($args['q']     ?? ''),
                            'limit'       => (int)($args['limit']    ?? 50),
                            'offset'      => (int)($args['offset']   ?? 0),
                        ]);
                        $locale = (string)($args['locale'] ?? 'en');
                        $out = [];
                        foreach ($rows as $r) $out[] = (new Product($r))->toArray($locale);
                        return $out;
                    },
                ],
            ],

            // Object types
            'User' => [
                '__name' => 'User',
                'id'         => ['type' => 'Int'],
                'email'      => ['type' => 'String'],
                'firstName'  => ['type' => 'String'],
                'lastName'   => ['type' => 'String'],
                'role'       => ['type' => 'String'],
                'isActive'   => ['type' => 'Boolean'],
            ],

            'Page' => [
                '__name' => 'Page',
                'id'           => ['type' => 'Int'],
                'slug'         => ['type' => 'String'],
                'title'        => ['type' => 'JSON', 'resolve' => fn($p) => json_decode((string)($p['title'] ?? '{}'), true)],
                'content'      => ['type' => 'JSON', 'resolve' => fn($p) => json_decode((string)($p['content'] ?? '{}'), true)],
                'isPublished'  => ['type' => 'Boolean', 'resolve' => fn($p) => (bool)($p['is_published'] ?? 0)],
                'publishedAt'  => ['type' => 'String',  'resolve' => fn($p) => $p['published_at'] ?? null],
            ],

            'Post' => [
                '__name' => 'Post',
                'id'           => ['type' => 'Int'],
                'slug'         => ['type' => 'String'],
                'title'        => ['type' => 'JSON', 'resolve' => fn($p) => json_decode((string)($p['title'] ?? '{}'), true)],
                'excerpt'      => ['type' => 'JSON', 'resolve' => fn($p) => json_decode((string)($p['excerpt'] ?? '{}'), true)],
                'content'      => ['type' => 'JSON', 'resolve' => fn($p) => json_decode((string)($p['content'] ?? '{}'), true)],
                'coverImage'   => ['type' => 'String'],
                'isPublished'  => ['type' => 'Boolean', 'resolve' => fn($p) => (bool)($p['is_published'] ?? 0)],
            ],

            'ContentEntry' => [
                '__name' => 'ContentEntry',
                'id'        => ['type' => 'Int'],
                'slug'      => ['type' => 'String'],
                'status'    => ['type' => 'String'],
                'locale'    => ['type' => 'String'],
                'data'      => ['type' => 'JSON'],
                'publishedAt' => ['type' => 'String'],
            ],

            'Product' => [
                '__name' => 'Product',
                'id'              => ['type' => 'Int'],
                'slug'            => ['type' => 'String'],
                'kind'            => ['type' => 'String'],
                'name'            => ['type' => 'String'],
                'shortDescription'=> ['type' => 'String'],
                'description'     => ['type' => 'String'],
                'images'          => ['type' => '[String]'],
                'basePrice'       => ['type' => 'Int'],
                'compareAtPrice'  => ['type' => 'Int'],
                'sku'             => ['type' => 'String'],
                'isActive'        => ['type' => 'Boolean'],
                'isFeatured'      => ['type' => 'Boolean'],
                'isSubscription'  => ['type' => 'Boolean'],
                'billingPeriod'   => ['type' => 'String'],
                'billingInterval' => ['type' => 'Int'],
            ],
        ];

        // Let plugins extend the schema.
        if (class_exists('Event')) {
            Event::dispatch('graphql.schema', $schema);
        }

        return $schema;
    }

    private static function userToArray(int $userId): ?array
    {
        $row = DB::findOne('users', ' id = :id ', [':id' => $userId]);
        if (!$row) return null;
        return [
            'id'        => (int)$row['id'],
            'email'     => $row['email'],
            'firstName' => $row['first_name'] ?? null,
            'lastName'  => $row['last_name']  ?? null,
            'role'      => $row['role'],
            'isActive'  => (bool)($row['is_active'] ?? 0),
        ];
    }
}
