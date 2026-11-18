# Seven CMS — Git commit history

Snapshot of the master branch commit history as of 2026-05-06.

This file is the canonical record of legitimate, intentional commits on the
project's master branch. Use it as a reference if the git history needs to be
rebuilt or verified after a force-push, repository rewrite, or contributor
cleanup.

> All commits below were authored by `theloopbreaker4-cloud`.
> The legacy commits from 2017 (authors: `xiogoperus`, `airobenutus`) were
> already squashed into the `Initial commit` (0f887ae) on 2026-04-29.
> Those legacy commits remain visible on GitHub only via the dependabot
> branches that were never rebased onto the new history.

## Commits — newest first

| SHA | Date | Subject |
|---|---|---|
| `d153af5` | 2026-05-06 | chore(todo): track custom theme creation + Modules→Plugins rename + empty list bug |
| `bb998fb` | 2026-05-06 | fix(themes): preview cards show their own palette + consistent gradient buttons |
| `fe8265f` | 2026-04-30 | Remove stale public/package-lock.json (legacy from old jQuery-era public/package.json) |
| `e838fdc` | 2026-04-30 | Add .gitattributes, finish CSP nonce migration, drop 'unsafe-inline' in prod |
| `71e7aaa` | 2026-04-30 | Add TODO.md, exclude .claude/ and CLAUDE.md from git |
| `39f8052` | 2026-04-30 | Close TODO: rate limit on Cache, CSP nonce, UI Kit demos, security/forms docs |
| `f040cc3` | 2026-04-30 | Add file headers, drop legacy files, rename nginx config |
| `e6f1598` | 2026-04-29 | Add project status & credits section to README |
| `0f887ae` | 2026-04-29 | Initial commit (squashed history under correct author) |

## What each commit covers

### `d153af5` — chore(todo)
Adds three open items to TODO.md:
- Custom theme creation in admin (light + dark variants mandatory)
- Rename `Modules` → `Plugins` (or `Extensions`) — naming decision pending
- Investigate empty Modules page bug (plugin list not rendering)

### `bb998fb` — fix(themes)
Four related theme fixes:
1. **Theme preview cards** — wrapped each card in `data-palette="<slug>" data-theme="dark|light"` so SCSS theme selectors (`[data-palette="forest"][data-theme="dark"]` etc.) override CSS variables locally. Each card now shows its own palette instead of the active theme's colors.
2. **Default/Sand/Violet themes** got `--primary-gradient` and `--primary-gradient-hover` (Aurora/Forest/Midnight already had them). Primary buttons now look consistent across all six themes.
3. **Aurora hover** changed from violet to darker teal so it stays in-gamma with the teal primary.
4. **forest/theme.json + midnight/theme.json** — `preview` was an array of hex strings instead of `{light, dark}` object — normalized to match the other themes.

### `fe8265f` — Remove stale public/package-lock.json
Cleanup of jQuery-era artifact.

### `e838fdc` — `.gitattributes` + CSP nonce migration finish
- Adds `.gitattributes` (`* text=auto eol=lf`) to kill LF/CRLF warnings on Windows.
- Drops `'unsafe-inline'` from production CSP after every inline `<script>`/`<style>` got a per-request `nonce` via `Csp::nonce()`.
- Dev mode keeps `'unsafe-inline'` as fallback for Vite HMR shims.

### `71e7aaa` — TODO.md + ignore `.claude/`
- Adds project TODO file with the open backlog.
- Excludes `.claude/` directory and `CLAUDE.md` from git (those are local Claude Code workspace files, not project artifacts).

### `39f8052` — Close TODO items
Multi-item batch:
- `RateLimit` migrated off PHP sessions to the `Cache` facade — works for stateless API clients.
- CSP per-request nonce wired through `Master.html`.
- UI Kit demo pages added under `/admin/ui` (forms, pickers, validation, select, counter, shake).
- `docs/security.md` + `docs/forms.md` written and registered in `HelpAdminController::topics()`.

### `f040cc3` — File headers + cleanup
- Stamps every PHP source file with the standard header:
  ```php
  <?php
  /** SevenCMS — github.com/theloopbreaker4-cloud/seven-cms-php */
  defined('_SEVEN') or die('No direct script access allowed');
  ```
- Drops legacy files: `public/bundle.js`, `public/package.json`, `lib/extension/rb/rb.php.bak43`.
- Renames `rewrite_nginx.info` → `nginx.conf.example`.

### `e6f1598` — README project status & credits
Adds the "Project status & credits" section to README — transparent disclosure that the project uses AI-assisted development.

### `0f887ae` — Initial commit
The squashed-history reset on 2026-04-29. Combines the entire usable codebase under a single commit authored by `theloopbreaker4-cloud`. Replaces the prior history that had legacy authorship from 2017 (`xiogoperus`, `airobenutus`).

## Dependabot branches (still on remote)

These five branches were auto-created by Dependabot in 2017–2023 against the *old* history and were never rebased onto the new squashed history. They are the reason `xiogoperus` / `airobenutus` still appear in the GitHub Contributors list:

- `dependabot/npm_and_yarn/decode-uri-component-0.2.2`
- `dependabot/npm_and_yarn/json5-and-webpack-1.0.2`
- `dependabot/npm_and_yarn/loader-utils-1.4.2`
- `dependabot/npm_and_yarn/public/jquery-3.5.0`
- `dependabot/npm_and_yarn/qs-6.4.1`

**To remove the legacy contributors from the GitHub Contributors list:** delete these five branches on the remote. They reference dependencies of the old jQuery/webpack stack that the rewrite no longer uses, so they are safe to drop.

```bash
git push origin --delete dependabot/npm_and_yarn/decode-uri-component-0.2.2
git push origin --delete dependabot/npm_and_yarn/json5-and-webpack-1.0.2
git push origin --delete dependabot/npm_and_yarn/loader-utils-1.4.2
git push origin --delete dependabot/npm_and_yarn/public/jquery-3.5.0
git push origin --delete dependabot/npm_and_yarn/qs-6.4.1
```

## Deployments to clean up

GitHub still has 6 failed deployments in environment `xiogop` from June 2017. To delete:

1. `Settings` → `Environments` → `xiogop` → `Delete environment` (deletes all 6 deployments with it).
2. Or via API: `gh api -X DELETE /repos/theloopbreaker4-cloud/seven-cms-php/environments/xiogop`
