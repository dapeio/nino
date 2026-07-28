# Developer Guide

How Nino is put together, for whoever maintains or extends it. Companions:
`docs/modules.md` (writing your own module), `docs/setup.md` (go-live),
`docs/design-system.md` (frontend building blocks),
`docs/administrator.md` (content editing).

## Philosophy

Nino is a filesystem-based PHP micro-framework: no database, no build
step, no dependencies. Content lives in plain `.php` files that `return`
an array, or `.tpl` files with `[[key]]`/`[template x]`/`[shortcode]`
markup.

**`_nino/Nino.php` is the only mandatory piece** - routing, templating,
auth, elements, locales, form, newsletter, all in one file. `_admin` and
`_dev` are optional add-ons built on top of the kernel, using nothing the
kernel doesn't expose to any other module; a project can delete both
directories and lose nothing else.

Each major piece is deliberately a single file (related classes stay next
to each other, one place to grep):

```
_nino/Nino.php     Kernel - namespace Nino { ... } and Nino\Modules { ... }
_admin/Admin.php     Admin dashboard backend (optional)
_dev/Dev.php         Developer tools backend (optional)
```

## `$appData`: the one thing every function shares

Almost every kernel function takes `array &$appData` as its first
argument - that's the entire state model. One associative array, built
once per request in `\Nino\init()`, threaded by reference through every
call. No DI container, no singletons, no globals beyond it.

- `/nino/...` keys - persistent config, round-trips through `config.php`
  (`/nino/auth/user`, `/nino/http/routes`, `/nino/modules`, ...).
- `./nino/...` keys - request/runtime-only state, never written to disk
  (`./nino/auth/current`, `./nino/callbacks`,
  `./nino/filesystem/cache`, `./nino/html/fills`, ...).

There's exactly one `$appData` per request, never shared across requests -
nothing survives except what's explicitly persisted to `config.php` or
the filesystem.

## init → request → output

Every request goes through the same three calls (see `index.php`,
`_admin/index.php`, `_dev/index.php`):

```php
$appData = \Nino\init();     // build $appData: session, filesystem cache,
                              // config.php, locale, auth, then every
                              // /nino/modules class's ::init()
$request = \Nino\request( $appData, $_SERVER );  // parse request, resolve
                              // the route, run callbacks, render the body
\Nino\output( $appData, $request );  // send headers/status/body, exit
```

`Http::response()` is where routing happens: the route table is matched
by exact `METHOD://uri` key first, then `/parent/*` wildcards walking up,
then the `/404` route. Afterwards two callback dispatches run:

- `/nino/http/response` - fires for **every** request (Auth's
  login/logout and Csrf's rejection live here).
- `/nino/http/response/GET://uri` (etc.) - fires only for that exact
  route.

`Http::request()` seeds the response header with the default security
headers (CSP, HSTS, ...), so a callback that extends one of them (eg.
Jstext appending its script-src nonce) works on the real value. Redirects
are done via the response array (`statusCode` 302 + `Location` header),
never by calling `header()` directly - `Http::output()` sends everything
in one place at the end.

## Templates, Shortcodes, Textfills

`Html::renderHtml( $appData, $html )` does three things in order:

```php
$html = self::_renderFills( $appData, $html );       // [[key]] -> value
$html = self::_renderShortcodes( $appData, $html );  // [name args]content[/name]
$html = Callbacks::doCallbacks( $appData, '/nino/html/render', $html );
```

**Textfills** (`[[key]]`) are `str_replace()` substitutions against the
current locale's fills (text content from `/text/*.php` plus per-request
values like `[[/nino/http/request/uri]]`). The replace loop runs until
no new `[[` appears - a fill's value can contain another `[[key]]` and it
resolves too.

**Shortcodes** (`[name arg1 key="value"]content[/name]`, or self-closing)
dispatch to their registered handler via
`/nino/html/shortcode/<name>` - shortcodes are just callbacks. The
handler's return value is **recursively rendered again**, which is what
makes nesting work: `[template x]` returns the raw file content and the
recursion resolves that file's own fills and shortcodes. A `[template x]`
inside `x.tpl` itself recurses forever - nothing guards against that.

## Callbacks: the one hook mechanism

```php
Callbacks::registerCallback( $appData, $name, $callback, $prio = 5 );
Callbacks::doCallbacks( $appData, $name, $args );
```

`$name` is just a string key by convention. `$prio` is `0`-`9`, lower runs
first (`Csrf` registers at `1` so its rejection is visible to everything
after it). Every registered callback runs in priority order - the chain
never stops early, so respecting an earlier rejection is each callback's
own job (`Csrf` sets the status code to `403`; `Admin::guard()`/
`Dev::guard()` check the status is still `200` before doing anything).
See `docs/modules.md` for every hook the kernel fires.

## AppData & Filesystem

- `Filesystem::getFileContent( $appData, $path, $default )` - reads with a
  per-request cache; `.php` files are `include`d for their return value.
  Staleness is mtime-based at 1-second resolution - where that matters,
  drop the cache entry before re-reading (see
  `AppData::writeContentData()`).
- `Filesystem::putFileContent( $appData, $path, $content )` - `.php` files
  are written as `<?php return var_export(...)`. Locks via `flock()`.
- `AppData::writeContentData( $appData, $keys )` - persists specific
  top-level keys into `config.php`, re-reading the file fresh first so
  concurrent writes to other keys aren't clobbered.

## `_admin` and `_dev`: separate entry points, not routes

`_admin/index.php` and `_dev/index.php` are complete, independent
bootstrap scripts, not routes in the main site's `config.php`. Each
requires the kernel, its own backend file, then calls its own `::init()`.
Consequence: a callback registered only in `Admin::init()` never fires
for a request that is routed to the root `index.php` (eg. the kernel's
generic `/.nino/auth/login` endpoint).

Every `apiXxx()` method starts with a guard:

```php
if( Admin::guard( $appData, $request ) === false )  // 401 if not logged in
    return;
```

### Permission system

Every `/nino/auth/user` record has a `perms` array of strings. The kernel
primitive:

```php
\Nino\Auth::checkPermission( $appData, string $perm, string $username = '' ): bool;
```

A perm matches exactly, or via a parent `/*` wildcard -
`/_admin/elements/manage` is granted by `/_admin/elements/*`, `/_admin/*`
or `/*`. `_admin` builds per-module gating on top
(`Admin::guardPerm()`, one `MANAGE_PERM` constant per domain class,
`Admin\Users::KNOWN_PERMS` as the whitelist for the checkbox UI). Two
deliberate gaps: no self-service permission editing in `_admin` (a
manager can strip their own rights and needs another manager - or `_dev` -
to fix it), and `_dev`'s own Users tab sets raw, unwhitelisted perms as
the recovery path for exactly that.

### `_dev` is deliberately decoupled from `config.php`

`_dev` logs in against its own hardcoded `PASSWORD_HASH` constant, not
`config.php`'s user records - so it still works to restore `config.php`
when that file's data is what's broken. Rate limiting is one shared
counter in `_dev/.lockout.json`.

### Backup / Restore / activity log

On by default, individually disabled via `/nino/admin/backups` /
`/nino/admin/logs` (`false`).

- `Admin\Backup` - daily encrypted (AES-256-GCM) tar.gz of everything the
  panel can write, under a one-time random directory in `_admin/`, 14-day
  retention.
- `Dev\Restore` - reads Backup's output independently (own copy of the key
  in `_dev/.restore-key.php`), takes a safety snapshot of the current
  state before every restore.
- `Admin\Logs` - one line per login/mutating action, daily files, 14-day
  retention.

## Testing

`tests/*.php` are dependency-free PHP scripts (no PHPUnit), each running
against an isolated sandbox under `sys_get_temp_dir()`:

```
tests/kernel-smoke.php    Kernel
tests/admin-smoke.php     _admin/Admin.php
tests/dev-smoke.php       _dev/Dev.php
```

CI (`.github/workflows/ci.yml`) runs all three plus `php -l`/
`node --check` on every push. When adding tests: dispatch through the real
entry point (`Admin::handlePost()`, not `apiXxx()` directly) when the
tested behavior hooks in there, and compare against a baseline captured
right before your own action instead of assuming empty state.

## Encryption / stub conventions

For another admin-managed file that sits inside the webroot but must not
be readable via direct request (the pattern `Admin\Backup`, `Admin\Logs`
and `Dev\Restore` use):

1. One-time random directory name (`bin2hex(random_bytes(16))`),
   persisted, never linked from anywhere.
2. `.php` extension, content wrapped as
   `<?php http_response_code(403); exit; return '<payload>';` with **no
   closing `?>` tag** - payload as base64 inside the single-quoted string.
3. Encrypt only if the content is credential-grade (AES-256-GCM,
   `random_bytes(12)` iv, `$iv . $tag . $cipher` concatenated) - otherwise
   the stub alone is enough.