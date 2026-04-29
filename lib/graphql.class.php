<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * GraphQL — minimal zero-dependency GraphQL executor.
 *
 * This is a deliberately small subset: query operations, named queries with
 * arguments, nested selections, aliases, and `__schema` introspection. It is
 * enough for Apollo / Relay clients to talk to SevenCMS without pulling in
 * `webonyx/graphql-php`.
 *
 * Mutations, subscriptions, fragments, directives, custom scalars are NOT
 * implemented. If you need them, install webonyx and bind the renderer in
 * the container — `Container::singleton('graphql.executor', fn() => new YourGqlExecutor())`.
 *
 * Schema is built from `Schema` arrays (see GraphQLSchema). Each top-level
 * field resolves to a PHP callable returning either a scalar or an array
 * the parser can recurse into.
 *
 * Usage:
 *   $result = GraphQL::execute(GraphQLSchema::build(), $query, $variables);
 *   echo json_encode($result);
 */
class GraphQL
{
    public static function execute(array $schema, string $query, array $variables = []): array
    {
        try {
            $ast    = self::parse($query);
            $result = self::executeAst($ast, $schema, $variables);
            return ['data' => $result];
        } catch (\Throwable $e) {
            return [
                'errors' => [['message' => $e->getMessage()]],
                'data'   => null,
            ];
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Tokenizer + parser — supports a tiny subset of the GraphQL grammar:
    //   query [Name] [(varDecls)] { selectionSet }
    //   { field [(args)] [{ subSelection }], … }
    // ──────────────────────────────────────────────────────────────────

    private static function parse(string $query): array
    {
        $tokens = self::tokenize($query);
        $pos = 0;

        // Allow leading "query Name(args)" or anonymous "{ ... }".
        if (($tokens[$pos][0] ?? null) === 'name' && $tokens[$pos][1] === 'query') {
            $pos++;
            if (($tokens[$pos][0] ?? null) === 'name')   $pos++;          // operation name
            if (($tokens[$pos][0] ?? null) === 'punct' && $tokens[$pos][1] === '(') {
                // Skip variable declarations — we already have the values.
                $depth = 1; $pos++;
                while ($pos < count($tokens) && $depth > 0) {
                    if ($tokens[$pos][0] === 'punct' && $tokens[$pos][1] === '(') $depth++;
                    if ($tokens[$pos][0] === 'punct' && $tokens[$pos][1] === ')') $depth--;
                    $pos++;
                }
            }
        }

        if (!isset($tokens[$pos]) || $tokens[$pos][0] !== 'punct' || $tokens[$pos][1] !== '{') {
            throw new RuntimeException('Expected "{" at start of selection set');
        }
        $pos++;
        $selections = self::parseSelections($tokens, $pos);
        return ['kind' => 'document', 'selections' => $selections];
    }

    private static function parseSelections(array $tokens, int &$pos): array
    {
        $out = [];
        while (isset($tokens[$pos])) {
            $t = $tokens[$pos];
            if ($t[0] === 'punct' && $t[1] === '}') { $pos++; return $out; }
            if ($t[0] !== 'name')                  throw new RuntimeException("Unexpected token: {$t[1]}");

            $alias = null; $name = $t[1]; $pos++;
            if (isset($tokens[$pos]) && $tokens[$pos][0] === 'punct' && $tokens[$pos][1] === ':') {
                $pos++;
                if (!isset($tokens[$pos]) || $tokens[$pos][0] !== 'name') throw new RuntimeException('Expected field name after alias');
                $alias = $name; $name = $tokens[$pos][1]; $pos++;
            }

            $args = [];
            if (isset($tokens[$pos]) && $tokens[$pos][0] === 'punct' && $tokens[$pos][1] === '(') {
                $pos++;
                $args = self::parseArgs($tokens, $pos);
            }

            $sub = [];
            if (isset($tokens[$pos]) && $tokens[$pos][0] === 'punct' && $tokens[$pos][1] === '{') {
                $pos++;
                $sub = self::parseSelections($tokens, $pos);
            }

            $out[] = ['kind' => 'field', 'name' => $name, 'alias' => $alias, 'args' => $args, 'selections' => $sub];
        }
        throw new RuntimeException('Unterminated selection set');
    }

    private static function parseArgs(array $tokens, int &$pos): array
    {
        $args = [];
        while (isset($tokens[$pos])) {
            $t = $tokens[$pos];
            if ($t[0] === 'punct' && $t[1] === ')') { $pos++; return $args; }
            if ($t[0] !== 'name') throw new RuntimeException("Argument name expected, got {$t[1]}");

            $name = $t[1]; $pos++;
            if (!isset($tokens[$pos]) || $tokens[$pos][0] !== 'punct' || $tokens[$pos][1] !== ':') {
                throw new RuntimeException('":" expected after argument name');
            }
            $pos++;
            $val = self::parseValue($tokens, $pos);
            $args[$name] = $val;
            // optional comma
            if (isset($tokens[$pos]) && $tokens[$pos][0] === 'punct' && $tokens[$pos][1] === ',') $pos++;
        }
        throw new RuntimeException('Unterminated argument list');
    }

    private static function parseValue(array $tokens, int &$pos)
    {
        $t = $tokens[$pos] ?? [null, null];
        switch ($t[0]) {
            case 'string': $pos++; return $t[1];
            case 'number': $pos++; return is_numeric($t[1]) && strpos($t[1], '.') === false ? (int)$t[1] : (float)$t[1];
            case 'name':
                $pos++;
                return match ($t[1]) {
                    'true'  => true,
                    'false' => false,
                    'null'  => null,
                    default => $t[1],
                };
            case 'variable':
                // Variables resolved by the executor.
                $pos++;
                return ['__var' => $t[1]];
            case 'punct':
                if ($t[1] === '[') {
                    $pos++;
                    $arr = [];
                    while (isset($tokens[$pos]) && !($tokens[$pos][0] === 'punct' && $tokens[$pos][1] === ']')) {
                        $arr[] = self::parseValue($tokens, $pos);
                        if (isset($tokens[$pos]) && $tokens[$pos][0] === 'punct' && $tokens[$pos][1] === ',') $pos++;
                    }
                    $pos++; return $arr;
                }
                if ($t[1] === '{') {
                    $pos++;
                    $obj = [];
                    while (isset($tokens[$pos]) && !($tokens[$pos][0] === 'punct' && $tokens[$pos][1] === '}')) {
                        if ($tokens[$pos][0] !== 'name') throw new RuntimeException('Object key expected');
                        $key = $tokens[$pos][1]; $pos++;
                        if ($tokens[$pos][0] !== 'punct' || $tokens[$pos][1] !== ':') throw new RuntimeException('":" expected');
                        $pos++;
                        $obj[$key] = self::parseValue($tokens, $pos);
                        if (isset($tokens[$pos]) && $tokens[$pos][0] === 'punct' && $tokens[$pos][1] === ',') $pos++;
                    }
                    $pos++; return $obj;
                }
        }
        throw new RuntimeException('Unexpected value token: ' . json_encode($t));
    }

    private static function tokenize(string $q): array
    {
        $i = 0; $n = strlen($q); $tokens = [];
        while ($i < $n) {
            $c = $q[$i];
            if (ctype_space($c) || $c === ',') { $i++; continue; }
            if ($c === '#') {                            // comment
                while ($i < $n && $q[$i] !== "\n") $i++;
                continue;
            }
            if ($c === '$') {
                $i++; $start = $i;
                while ($i < $n && (ctype_alnum($q[$i]) || $q[$i] === '_')) $i++;
                $tokens[] = ['variable', substr($q, $start, $i - $start)];
                continue;
            }
            if (ctype_alpha($c) || $c === '_') {
                $start = $i;
                while ($i < $n && (ctype_alnum($q[$i]) || $q[$i] === '_')) $i++;
                $tokens[] = ['name', substr($q, $start, $i - $start)];
                continue;
            }
            if (ctype_digit($c) || ($c === '-' && $i + 1 < $n && ctype_digit($q[$i+1]))) {
                $start = $i;
                if ($c === '-') $i++;
                while ($i < $n && (ctype_digit($q[$i]) || $q[$i] === '.')) $i++;
                $tokens[] = ['number', substr($q, $start, $i - $start)];
                continue;
            }
            if ($c === '"') {
                $i++; $buf = '';
                while ($i < $n && $q[$i] !== '"') {
                    if ($q[$i] === '\\' && $i + 1 < $n) { $buf .= $q[$i+1]; $i += 2; continue; }
                    $buf .= $q[$i]; $i++;
                }
                $i++;
                $tokens[] = ['string', $buf];
                continue;
            }
            if (in_array($c, ['{','}','(',')','[',']',':','=','!','@'], true)) {
                $tokens[] = ['punct', $c]; $i++; continue;
            }
            throw new RuntimeException("Unexpected character: {$c}");
        }
        return $tokens;
    }

    // ──────────────────────────────────────────────────────────────────
    // Executor
    // ──────────────────────────────────────────────────────────────────

    private static function executeAst(array $document, array $schema, array $variables): array
    {
        $root = $schema['Query'] ?? [];
        $out  = [];
        foreach ($document['selections'] as $sel) {
            $key = $sel['alias'] ?? $sel['name'];
            $out[$key] = self::resolveField($sel, $root, null, $schema, $variables);
        }
        return $out;
    }

    private static function resolveField(array $sel, array $type, $parent, array $schema, array $variables)
    {
        // Introspection __schema
        if ($sel['name'] === '__schema') {
            return self::introspect($schema);
        }
        if ($sel['name'] === '__typename') {
            return $type['__name'] ?? 'Unknown';
        }

        if (!isset($type[$sel['name']])) {
            throw new RuntimeException("Unknown field {$sel['name']} on type " . ($type['__name'] ?? 'Query'));
        }
        $def  = $type[$sel['name']];
        $args = self::resolveArgs($sel['args'] ?? [], $variables);

        $resolver = $def['resolve'] ?? null;
        $value = is_callable($resolver) ? $resolver($parent, $args) : ($parent[$sel['name']] ?? null);

        if (empty($sel['selections'])) return $value;

        $childTypeName = $def['type'] ?? '';
        $isList        = strpos($childTypeName, '[') === 0;
        $childTypeName = trim($childTypeName, '[]!');
        $childType     = $schema[$childTypeName] ?? [];

        if ($isList) {
            $list = [];
            foreach ((array)$value as $row) {
                $list[] = self::resolveSelections($sel['selections'], $row, $childType, $schema, $variables);
            }
            return $list;
        }
        if ($value === null) return null;
        return self::resolveSelections($sel['selections'], $value, $childType, $schema, $variables);
    }

    private static function resolveSelections(array $selections, $parent, array $type, array $schema, array $variables): array
    {
        $out = [];
        foreach ($selections as $sel) {
            $key = $sel['alias'] ?? $sel['name'];
            if ($sel['name'] === '__typename') { $out[$key] = $type['__name'] ?? 'Object'; continue; }
            $out[$key] = self::resolveField($sel, $type, $parent, $schema, $variables);
        }
        return $out;
    }

    private static function resolveArgs(array $args, array $variables): array
    {
        $out = [];
        foreach ($args as $k => $v) {
            if (is_array($v) && isset($v['__var'])) $out[$k] = $variables[$v['__var']] ?? null;
            else                                    $out[$k] = $v;
        }
        return $out;
    }

    // ──────────────────────────────────────────────────────────────────
    // Introspection (minimal — type names + field names per type).
    // ──────────────────────────────────────────────────────────────────

    private static function introspect(array $schema): array
    {
        $types = [];
        foreach ($schema as $name => $type) {
            if ($name === '__name') continue;
            $fields = [];
            foreach ($type as $fName => $fDef) {
                if ($fName === '__name') continue;
                $fields[] = ['name' => $fName, 'type' => ['name' => trim((string)($fDef['type'] ?? 'String'), '[]!')]];
            }
            $types[] = ['name' => $name, 'fields' => $fields];
        }
        return [
            'queryType' => ['name' => 'Query'],
            'types'     => $types,
        ];
    }
}
