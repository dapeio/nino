# Changelog

## 0.10.0-beta

Security and reliability hardening pass, plus a module-system refactor.

- **CSRF**: `\Nino\Csrf` protection is now always active; `\Nino\Modules\Csrf`
  only powers the `[csrf]` shortcode. Tokens are accepted from header or JSON
  body, enforcement matches methods exactly instead of "beyond POST" loosely,
  and it no longer depends on an enable flag
- **Auth/session**: throttled logins per IP, stopped user enumeration,
  session tokens instead of client-IP binding, password hash dropped from
  the PHP session, strict session cookie flags and token lifetime, fixed
  parallel logins cancelling each other out
- **Headers**: valid `X-Frame-Options`, completed CSP, fixed mail header
  injection and sender headers, stopped leaking credentials through the
  error handler
- **Filesystem/locking**: switched `Elements` to `Filesystem::mutate()`
  (closed 8 early-return lock leaks), atomic file writes with correct
  locking, `Filesystem` now mutates its lock primitive on every read,
  config path and rotating-log edge cases fixed
- **Module system**: modules moved to
  `_nino/Nino/Modules/<Name>/<Name>.php` and are now loaded via
  `spl_autoload_register()`; `\Nino\Shortcodes` renamed to `\Nino\Modules`
- **Refactors**: added `Http::fail()`/`Http::ok()` response helpers, moved
  `\Nino\Text` into the kernel (Admin/Dev keep only their delta), reworked
  the rotating log, moved `backupManifest()` to `\Nino\Backup`, trimmed
  comment density across the codebase
- **Fixes**: shortcode recursion guard, element query keys combined with
  AND, uri rename no longer breaks, route locale handling, asset bundle
  rebuilt only when changed, `array_merge_recursive` replaced with a
  scalar-overwrite merge, a dashboard permission leak, response header
  filtering, `Callbacks::doCallbacks()`, `writeContentData()` now checks
  its return value, `Text::saveBatch()` no longer rebuilds entries per call
- **Tests**: added `concurrency-smoke.php` and `dev-smoke.php`, expanded
  `kernel-smoke.php` and `admin-smoke.php`

## 0.9.0-beta

First tagged release. Highlights since the initial drop:

- Fixed the default Content-Security-Policy being wiped by the Jstext
  module's own script-src fragment
- Fixed error display/logging defaults for production (display off, log on)
- Fixed locale-switch redirects, which never actually redirected
- Added `session_regenerate_id()` on login (session fixation defense)
- `/data/` and uploaded images are no longer git-tracked
- Newsletter signup is now double opt-in (confirm link by mail) with a
  self-service unsubscribe link; everything moved under `/.newsletter`
  (routes registered by the `Newsletter` class itself, not `config.php`)
- `router.php` serves the bundled demo images on the local dev server
- Simplified all `docs/*.md` down to plain reference material; removed
  `docs/security.md`