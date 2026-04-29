<?php

defined('_SEVEN') or die('No direct script access allowed');

/**
 * ErrorPage — pretty dev-mode error renderer.
 *
 * Mimics the Seven admin's dark palette so errors look native to the CMS
 * rather than a stock PHP whitescreen. Use ErrorPage::render() from a
 * try/catch, or ErrorPage::register() to install global exception +
 * shutdown handlers (for errors that fire before process() runs).
 */
class ErrorPage
{
    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $e): void
    {
        if (defined('ENVIRONMENT') && ENVIRONMENT !== 'dev') {
            http_response_code(500);
            echo 'Internal Server Error';
            return;
        }
        self::render($e);
    }

    public static function handleShutdown(): void
    {
        $err = error_get_last();
        if (!$err) return;
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatal, true)) return;

        // Wrap the fatal in a synthetic exception for uniform rendering.
        $e = new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']);
        self::handleException($e);
    }

    public static function render(Throwable $e): void
    {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
        }

        $class    = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
        $message  = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $file     = htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8');
        $line     = (int)$e->getLine();
        $code     = (int)$e->getCode();
        $uri      = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
        $method   = htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'GET', ENT_QUOTES, 'UTF-8');
        $php      = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
        $copyText = htmlspecialchars($e->getMessage() . "\n in " . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8');

        $sourceSnippet = self::sourceSnippet($e->getFile(), $e->getLine());
        $traceRows     = self::traceRows($e);

        echo <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Error · Seven CMS</title>
<style>
  *,*::before,*::after { box-sizing: border-box; }
  html,body { margin:0; padding:0; }
  body {
    background:#0d1117; color:#e6edf3;
    font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,system-ui,sans-serif;
    min-height:100vh;
  }
  .wrap { max-width:1100px; margin:0 auto; padding:32px 20px 60px; }
  .head { display:flex; align-items:center; gap:14px; margin-bottom:8px; }
  .badge {
    display:inline-flex; align-items:center; justify-content:center;
    width:36px; height:36px; border-radius:8px;
    background:rgba(248,81,73,.12); color:#f85149; font-size:20px;
  }
  .cls { font-size:13px; color:#8b949e; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; }
  h1 { margin:6px 0 14px; font-size:20px; font-weight:600; color:#f0f6fc; word-break:break-word; }
  .meta {
    display:flex; flex-wrap:wrap; gap:16px;
    padding:12px 14px; background:#161b22; border:1px solid #30363d; border-radius:8px;
    font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px;
    margin-bottom:20px;
  }
  .meta b { color:#8b949e; font-weight:500; margin-right:6px; }
  .meta .v { color:#7ee787; }
  .section { margin-top:22px; }
  .section h2 {
    font-size:11px; text-transform:uppercase; letter-spacing:.08em;
    color:#8b949e; margin:0 0 8px; font-weight:600;
  }
  .panel {
    background:#161b22; border:1px solid #30363d; border-radius:8px; overflow:hidden;
  }
  .source { padding:0; }
  .source pre {
    margin:0; padding:14px 0; font:12px/1.6 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
    overflow-x:auto;
  }
  .source .ln {
    display:flex; padding:0 14px;
  }
  .source .ln.cur { background:rgba(248,81,73,.10); }
  .source .num {
    color:#6e7681; min-width:48px; user-select:none; text-align:right; padding-right:14px;
  }
  .source .ln.cur .num { color:#f85149; font-weight:bold; }
  .source .code { white-space:pre; color:#e6edf3; }
  .trace { padding:6px 0; max-height:520px; overflow-y:auto; }
  .frame {
    display:grid; grid-template-columns:36px 1fr; gap:10px; padding:10px 14px;
    border-bottom:1px solid #21262d; font-size:12px;
  }
  .frame:last-child { border-bottom:0; }
  .idx { color:#6e7681; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; text-align:right; }
  .call { color:#79c0ff; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; word-break:break-all; }
  .loc  { color:#8b949e; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; margin-top:2px; word-break:break-all; }
  .loc .fileline { color:#a5d6ff; }
  .actions { display:flex; gap:8px; margin-top:16px; }
  .btn {
    display:inline-flex; align-items:center; gap:6px;
    padding:7px 14px; border-radius:6px; font-size:12px; font-weight:500;
    background:#21262d; color:#e6edf3; border:1px solid #30363d;
    cursor:pointer; text-decoration:none; transition:background .15s;
  }
  .btn:hover { background:#30363d; }
  .btn.primary { background:#238636; border-color:#2ea043; color:#fff; }
  .btn.primary:hover { background:#2ea043; }
  ::-webkit-scrollbar { width:10px; height:10px; }
  ::-webkit-scrollbar-thumb { background:#30363d; border-radius:5px; }
  ::-webkit-scrollbar-thumb:hover { background:#484f58; }
</style>
</head>
<body>
<div class="wrap">
  <div class="head">
    <div class="badge">!</div>
    <div>
      <div class="cls">{$class}</div>
    </div>
  </div>
  <h1>{$message}</h1>

  <div class="meta">
    <div><b>File</b><span class="v">{$file}:{$line}</span></div>
    <div><b>URI</b><span class="v">{$method} {$uri}</span></div>
    <div><b>PHP</b><span class="v">{$php}</span></div>
    <div><b>Code</b><span class="v">{$code}</span></div>
  </div>

  <div class="section">
    <h2>Source</h2>
    <div class="panel source">{$sourceSnippet}</div>
  </div>

  <div class="section">
    <h2>Stack trace</h2>
    <div class="panel trace">{$traceRows}</div>
  </div>

  <div class="actions">
    <button class="btn primary" onclick="navigator.clipboard.writeText(this.dataset.copy);this.textContent='Copied'" data-copy="{$copyText}">Copy error</button>
    <a class="btn" href="javascript:history.back()">← Back</a>
    <a class="btn" href="/">Home</a>
  </div>
</div>
</body>
</html>
HTML;
    }

    private static function sourceSnippet(string $file, int $line, int $context = 6): string
    {
        if (!is_file($file) || !is_readable($file)) return '<pre style="padding:14px;color:#8b949e">Source not available.</pre>';
        $lines = @file($file, FILE_IGNORE_NEW_LINES);
        if (!$lines) return '<pre style="padding:14px;color:#8b949e">Source not available.</pre>';

        $start = max(1, $line - $context);
        $end   = min(count($lines), $line + $context);
        $out   = '<pre>';
        for ($i = $start; $i <= $end; $i++) {
            $cur  = $i === $line ? ' cur' : '';
            $code = htmlspecialchars($lines[$i - 1] ?? '', ENT_QUOTES, 'UTF-8');
            $out .= '<div class="ln' . $cur . '"><span class="num">' . $i . '</span><span class="code">' . $code . '</span></div>';
        }
        $out .= '</pre>';
        return $out;
    }

    private static function traceRows(Throwable $e): string
    {
        $trace = $e->getTrace();
        if (!$trace) return '<div style="padding:14px;color:#8b949e">No stack frames.</div>';

        $out = '';
        foreach ($trace as $i => $f) {
            $func   = ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? '');
            $func   = htmlspecialchars($func, ENT_QUOTES, 'UTF-8');
            $loc    = '';
            if (isset($f['file'])) {
                $loc = '<span class="fileline">' . htmlspecialchars($f['file'], ENT_QUOTES, 'UTF-8')
                     . ':' . (int)($f['line'] ?? 0) . '</span>';
            } else {
                $loc = '[internal]';
            }
            $out .= '<div class="frame">'
                 . '<div class="idx">#' . $i . '</div>'
                 . '<div><div class="call">' . $func . '()</div><div class="loc">' . $loc . '</div></div>'
                 . '</div>';
        }
        return $out;
    }
}
