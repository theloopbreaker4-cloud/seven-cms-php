# GraphQL

[← Back to docs](index.md)

## Endpoint

```
POST /api/v1/graphql
GET  /api/v1/graphql?query=...
GET  /api/v1/graphql/playground   # interactive playground
```

POST body shape:

```json
{
  "query":         "...",
  "variables":     { "limit": 10 },
  "operationName": null
}
```

## Built-in queries

```graphql
query {
  me { id email role }

  pages(limit: 10)             { id slug isPublished }
  page(slug: "about")          { id slug title content }

  posts(limit: 10)             { id slug isPublished }
  post(slug: "hello-world")    { id title content }

  products(limit: 5, kind: "physical") {
    id slug name basePrice kind isSubscription
  }
  product(slug: "blue-tshirt", locale: "en") {
    id name basePrice description images
  }

  contentEntries(type: "recipe", limit: 20) { id slug data }
  contentEntry(type: "recipe", slug: "pizza") { id data }
}
```

## Schema

Defined in [`lib/graphqlschema.class.php`](../lib/graphqlschema.class.php).
Top-level fields:

| Field             | Args                                                   | Returns           |
|-------------------|--------------------------------------------------------|-------------------|
| `me`              |                                                        | `User`            |
| `user(id)`        |                                                        | `User`            |
| `page(id, slug)`  |                                                        | `Page`            |
| `pages(limit)`    |                                                        | `[Page]`          |
| `post(id, slug)`  |                                                        | `Post`            |
| `posts(limit)`    |                                                        | `[Post]`          |
| `product(slug, locale)` |                                                  | `Product`         |
| `products(category, kind, q, limit, offset, locale)` |                                | `[Product]`       |
| `contentEntry(type, slug, locale)` |                                       | `ContentEntry`    |
| `contentEntries(type, locale, q, limit, offset)` |                            | `[ContentEntry]`  |

## Extending

Plugins extend the schema via the `graphql.schema` event:

```php
Event::listen('graphql.schema', function (&$schema) {
    $schema['Query']['reviewsForProduct'] = [
        'type'    => '[Review]',
        'resolve' => fn($p, $a) => DB::getAll(
            'SELECT * FROM reviews WHERE product_id = :p ORDER BY id DESC LIMIT 50',
            [':p' => (int)($a['productId'] ?? 0)]
        ),
    ];
    $schema['Review'] = [
        '__name' => 'Review',
        'id'        => ['type' => 'Int'],
        'productId' => ['type' => 'Int'],
        'rating'    => ['type' => 'Int'],
        'body'      => ['type' => 'String'],
    ];
});
```

## Playground

Open `/api/v1/graphql/playground` for a tiny built-in query editor with
Cmd/Ctrl+Enter to run.

## Implementation notes

The bundled executor (`lib/graphql.class.php`) supports a deliberately small
subset of the GraphQL grammar:

- query operations (no mutations / subscriptions)
- arguments + variables
- aliases
- nested selections + lists
- `__schema` introspection (minimal — type names + field names)

If you need full GraphQL semantics — fragments, directives, custom scalars,
mutations — install `webonyx/graphql-php` and override the executor by
binding `graphql.executor` in the container. The `GraphQLApiController`
will pick up the override automatically (rebind it inside your plugin's
`boot()`).

---

[← Back to docs](index.md)
