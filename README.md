# Nino

*[Deutsch](README.de.md)*

Nino is a PHP micro-framework designed for small to medium-sized websites. It operates entirely file-based and provides all essential functions without external packages or dependencies.
The Nino.php core consolidates all necessary methods in a single file (~3,800 lines of PHP code) to output multilingual websites with templating and file-based content management.
Texts and recurring content (such as posts/nodes) are stored in PHP array files, while templates reside in HTML-like .tpl files. A callback, textfill, and shortcode system delivers a compact yet dynamically flexible and powerful templating engine - see `docs/modules.md` for the full callback reference.
Presentation and logic are always strictly separated, and a per-module permission system (`docs/developer.md`'s "Permission system") lets an admin account be scoped to exactly the modules it needs instead of all-or-nothing.
The optional `_dev` (for developers) and `_admin` (for administrators) packages extend the frontend with a simple graphical administration app - both are entirely optional and safe to delete outright if a project doesn't need one.
The frontend includes complete, modern starter themes and comprehensive JavaScript/CSS tools - all entirely dependency-free.

## Core Nino.php Features
- HTTP server (request handling and output)
- User authentication (including timeout protection)
- Per-module permission system for admin accounts
- System-wide callbacks for flexible intervention
- File management (read, write, caching)
- Element management (dynamic multilingual content, similar to posts/nodes)
- Template and HTML rendering (multilingual text fills and shortcodes)
- Image upload
- System-wide locale handling
- Module management for additional functions

## Optional Nino.php Modules
- CSS/JS asset management (rendering and minification)
- Locale picker
- Multilingual JavaScript texts
- Navigation menus (including burger menu)
- CSRF protection
- HTML forms
- Newsletter (double opt-in signup + self-service unsubscribe)

## Quick start

```
php -S 127.0.0.1:8000 router.php
```

Requires PHP 8.4+ with the `gd` extension (image crop/resize) and `phar.ini`
support for `PharData` (used by the admin backup/restore feature, not
restricted by `phar.readonly`). No other setup - `config.php` in the
project root holds the site's own configuration and is read on every
request. See **[docs/setup.md](docs/setup.md)** for the router script
`_admin`/`_dev` need locally, webserver config, and the full path to a
production go-live.

## Layout

```
index.php        Main site entry point
config.php        Site config: routes, modules, locales, form settings

_nino/           Kernel - Nino.php (backend), Nino.js/Nino.css/Nino.ui.js (frontend core)
_admin/           Admin dashboard - content editors (values), users, backups, activity log
_dev/             Developer tools - element/text/image "schema" editors, config editor, restore

elements/         Element type content (one .php file per type)
text/             Per-locale text content + global.php (incl. design tokens) + blacklist.php
templates/        .tpl page/section templates
assets/           Project style.css/script.js
images/           Uploaded images (admin-managed, generated on demand)
docs/             administrator.md, developer.md, modules.md, setup.md,
                  design-system.md
tests/            Dependency-free smoke tests (php tests/*.php)
data/             Runtime data (newsletter subscribers, form submissions,
                  logs) - created on demand, never tracked in git
```

## Documentation

Not sure where to start? "I want to edit content" → administrator.md. "I
want to change how it works" → developer.md. "I want to build a page" →
design-system.md. "I'm about to deploy" → setup.md.

- **[docs/administrator.md](docs/administrator.md)** - day-to-day content
  editing via `/_admin` and `/_dev`: elements, text, images, users,
  permissions, backups, restore, activity log. No programming knowledge
  needed. (German)
- **[docs/developer.md](docs/developer.md)** - how the kernel is put
  together: the init → request → output pipeline,
  templates/shortcodes/textfills, callbacks, the permission system,
  `_admin`/`_dev`'s architecture, testing conventions.
- **[docs/modules.md](docs/modules.md)** - writing your own module: the
  callback hook mechanism, every system-wide hook point, a worked example.
- **[docs/setup.md](docs/setup.md)** - taking a fresh project from an empty
  checkout to a deployed, production-ready site.
- **[docs/design-system.md](docs/design-system.md)** - the frontend's
  built-in design system (sections, buttons, design tokens via
  `[[/ui/...]]` fills).

## Testing

```
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/dev-smoke.php
```

Each is a standalone script (no PHPUnit) that runs against an isolated
sandbox directory and prints a pass/fail summary. CI runs all three on
every push.

## License

[MIT](LICENSE)