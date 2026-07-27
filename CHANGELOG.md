# Changelog

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