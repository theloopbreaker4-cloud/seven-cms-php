# Installing under a sub-path

Seven CMS normally owns a whole domain (`https://example.com/`). It can also live under a path
next to another site, for example `https://example.com/cms/`.

## Configure

```ini
BASE_URL=https://example.com/cms
BASE_PATH=/cms        # optional, taken from BASE_URL's path when empty
```

That is all the application needs. `lib/basepath.class.php` applies the prefix at the edges of
each request, so templates, controllers and plugins keep using root-absolute URLs (`/en/admin`,
`/uploads/a.png`):

| Where              | What happens                                                                         |
| ------------------ | ------------------------------------------------------------------------------------ |
| incoming request   | `/cms` is stripped from `REQUEST_URI` before routing (fine if a proxy already did)  |
| `Location` headers | same-site redirects get the prefix                                                   |
| HTML responses     | `href`, `src`, `action`, `srcset`, `data-*`, CSS `url()` and app URLs in inline scripts |
| JSON responses     | string values that are app paths (`"/uploads/…"`, `"/en/…"`)                         |
| cookies            | scoped to `/cms/` instead of `/`                                                     |
| front-end bundles  | read `<meta name="seven-base">` via `src/shared/base.js` (`withBase()`, router base); `fetch`/XHR to `/…` are prefixed by a small head script |

With `BASE_PATH` empty nothing above runs.

**Writing new code:** keep using root-absolute paths in PHP and templates. In Vue/JS code use
`withBase('/en/…')` for links you build yourself and for `location.href`; `RouterLink` and
`fetch()` are handled already. Stored data (uploads, rich text) stays prefix-free, so a site can
move between a domain root and a sub-path without touching the database.

## nginx

Proxy the path to the CMS's own server block (or PHP-FPM pool):

```nginx
# in the main site's server { }
location = /cms { return 301 /cms/; }
location /cms/ {
    proxy_pass http://127.0.0.1:8090/;          # trailing slash: the CMS receives /en/…
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-Proto $scheme;
    client_max_body_size 8m;
}
```

The CMS server block on `127.0.0.1:8090` is the normal one from `nginx.conf.example`
(with `set_real_ip_from 127.0.0.1; real_ip_header X-Real-IP;` so rate limits see client IPs,
and `fastcgi_param HTTPS on;` when the public site is HTTPS).

## Develop

```bash
BASE_PATH=/cms php -S localhost:8086 -t public public/router.php   # open http://localhost:8086/cms/en/
```
