# Nino

*[Deutsch](README.de.md)*

**Hi, I am Nino.**
Nino is a lightweight PHP microframework for websites. It is package-independent, works without a database, and provides all the essential features of a modern framework.

### _nino
The Nino PHP core (*_nino*) consolidates all necessary methods into a single file with just 3,800 lines of PHP code, enabling the creation of multilingual websites—including file-based content management, templating, and comprehensive backend and frontend tools.
Texts and recurring content (such as posts/nodes) are stored in PHP files, while templates are designed in an HTML-like format. A shortcode system combines logic and presentation into a dynamic, flexible, and high-performance template engine. The Nino core fully handles language/user management and high-performance delivery.

Thanks to a clear single-point-of-data concept and a well-thought-out callback system, customization through your own modules is straightforward. However, the most important tools for modern mobile-first websites (elements, newsletters, image uploads, forms, locale pickers, navigation, and more) are already available as core modules—including the necessary CSS and JavaScript code.

### _dev / _admin
The Nino core (*_nino*) focuses exclusively on rendering the website. In parallel, *_dev* (for developers) and *_admin* (for administrators) complement the frontend as graphical management tools.
Both are entirely optional and can be completely removed if needed. They enable efficient and rapid development, while providing operators and administrators with a simple and user-friendly way to maintain ongoing data—without requiring technical expertise.

A well-designed permission system ensures that admin accounts only have the access they need. Compact logging, integrated backups, and a robust security concept complete the toolset for everyday use.

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
