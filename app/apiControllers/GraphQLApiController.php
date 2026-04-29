<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * GraphQLApiController — single endpoint for the GraphQL API.
 *
 *   POST /api/v1/graphql        body: { "query": "...", "variables": { ... }, "operationName": "..." }
 *   GET  /api/v1/graphql?query= for trivial queries / introspection
 *
 * Built on the bundled `GraphQL` executor — no Composer dependency. Plugins
 * can listen on `graphql.schema` to add their own types.
 */
class GraphQLApiController extends ApiController
{
    public function endpoint($req, $res, $params)
    {
        $payload   = $this->payload();
        $query     = (string)($payload['query']     ?? '');
        $variables = (array) ($payload['variables'] ?? []);

        if ($query === '') {
            $this->jsonError(400, 'Missing "query" field');
        }

        $schema = GraphQLSchema::build();
        $result = GraphQL::execute($schema, $query, $variables);

        http_response_code(isset($result['errors']) && !isset($result['data']) ? 400 : 200);
        return print $this->json($result);
    }

    /**
     * Lightweight HTML page with an embedded GraphiQL-like playground using
     * Vanilla JS. Loads on `/api/v1/graphql/playground`.
     */
    public function playground($req, $res, $params)
    {
        header('Content-Type: text/html; charset=utf-8');
        $endpoint = '/api/v1/graphql';
        echo <<<HTML
<!doctype html>
<html><head><meta charset="utf-8"><title>SevenCMS GraphQL</title>
<style>
  body { margin:0; font-family: ui-sans-serif, system-ui, sans-serif; background:#0b0f19; color:#e2e8f0; }
  .wrap { display:grid; grid-template-columns: 1fr 1fr; height:100vh; }
  textarea, pre { box-sizing:border-box; width:100%; height:100%; padding:1rem; background:#111827; color:#e2e8f0; border:0; outline:none; font-family: ui-monospace, monospace; font-size: 13px; line-height: 1.5; }
  pre { overflow:auto; margin:0; }
  .bar { background:#1e2535; padding:.5rem 1rem; display:flex; gap:.5rem; align-items:center; }
  button { background:#667eea; color:#fff; border:0; padding:.5rem 1rem; border-radius:.5rem; cursor:pointer; }
  button:hover { background:#5568d3; }
  h1 { margin:0; font-size:1rem; flex:1; }
</style></head>
<body>
<div class="bar"><h1>SevenCMS GraphQL playground</h1><button id="run">Run</button></div>
<div class="wrap">
  <textarea id="q">{
  pages(limit: 5) { id slug isPublished }
  posts(limit: 5) { id slug isPublished }
  products(limit: 5) { id slug name basePrice kind }
}</textarea>
  <pre id="out">// Press "Run" or Cmd/Ctrl+Enter</pre>
</div>
<script>
const run = async () => {
  const q = document.getElementById('q').value;
  const r = await fetch('{$endpoint}', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ query: q }),
  });
  const data = await r.json();
  document.getElementById('out').textContent = JSON.stringify(data, null, 2);
};
document.getElementById('run').addEventListener('click', run);
document.getElementById('q').addEventListener('keydown', e => {
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') run();
});
</script>
</body></html>
HTML;
        exit;
    }

    private function payload(): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw = file_get_contents('php://input');
            $arr = $raw ? json_decode($raw, true) : null;
            return is_array($arr) ? $arr : $_POST;
        }
        return [
            'query'     => (string)($_GET['query']     ?? ''),
            'variables' => is_string($_GET['variables'] ?? null) ? json_decode((string)$_GET['variables'], true) : [],
        ];
    }
}
