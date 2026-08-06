# Nino

*[Deutsch](README.de.md)*

## **Hi, I am Nino.**
Nino is a lightweight PHP framework for websites. It works **independently of packages**, requires **no database**, and still provides all the essential features for developing and running modern web applications.
The framework includes a predefined file structure, a **kernel** for dynamically generating and rendering the page (_nino), and an **optional** graphical interface for developers (_admin), operators (_editor), and a first-run setup wizard (_install).

## _nino
The kernel (_nino) comprises PHP/JS/CSS files for both frontend and backend. It bundles the entire PHP code into a **single file with 5,000 lines** and includes all the methods needed to render multilingual websites - including a complete backend, file-based content management, templating, and an extensive toolkit of additional frontend and backend tools.
Texts and recurring content on the website (such as posts, services, news, etc.) are stored in **PHP files**, while templates are built in an **HTML-like format**. The Nino kernel fully handles the request (locale/auth/route), renders the templates, and delivers the result cleanly.
All data for the current PHP lifecycle is passed through a **single array** via a **callback system**. This makes customizations and further development easy, **without touching the kernel**.
Alongside the \Nino kernel, the most important functions of a modern website (elements, newsletter, image uploads, forms, locale picker, navigation, and more) are already available as **optional modules** in \Nino\Modules - including all necessary CSS and JS code. Custom modules can be developed and integrated easily through the callback system.

### Nino.php's kernel features
- HTTP request/response handling
- System-wide callbacks for flexible intervention
- File management (read, write, caching)
- Multilingual element management (recurring content and posts)
- Template and HTML rendering with multilingual textfills and shortcodes
- User authentication (incl. timeout protection)
- Image upload
- System-wide locale handling
- Simple mailing
- Error logging
- Config handling
- Module management for additional functions

### Optional Nino modules
- CSS/JS asset management (rendering and minification)
- Locale picker
- Multilingual JavaScript texts
- Navigation menus (incl. burger menu)
- CSRF protection
- HTML forms
- Newsletter (double opt-in signup + unsubscribe link)

## _admin / _editor / _install
The Nino core _nino is **solely responsible for rendering the page**. In parallel, _admin (for developers) and _editor (for administrators) complement the frontend as graphical management tools, and _install offers a graphical, step-by-step setup wizard for a fresh checkout.
All three are **entirely optional** and can be removed completely if needed. They enable efficient, fast development and then give operators and administrators a simple, pleasant way to maintain ongoing data - **with no technical know-how required**.
A well-thought-out **permission system** ensures administrator accounts only get the access they need. Compact logging, an integrated backup, and a clean security concept round out the toolset for everyday use.

### _install UI features
- Environment checks: PHP version, required extensions, file/folder permissions
- Pick locales/modules/pages and assemble starting content from a library (routes, templates, text) - `_install/library/`, see `docs/_install.md`
- Pick a theme from a preview grid - stylesheet, webfonts and everything else it ships, copied in and bundled
- Bulk-fill the site's always-present "base" text content
- Create the first _editor account(s)
- Set the real _admin password (also the wizard's own lock-out condition)

### _templates UI features
- Load any `templates/page-*.tpl` into a visual block tree - recognized from its own CSS classes, no migration and no marker attributes
- Block library as one directory per block (`_templates/library/`), same manifest convention as `_install`
- Grid widths and spacing drawn to scale; everything else a labelled box, see `docs/_templates.md`
- Byte-exact round-trip: saving an untouched template changes nothing

### _admin UI features
- Create/edit/delete element types
- Create/edit/delete textfills
- Scan for undefined textfills
- Set up editable image slots for administrators
- Create/edit/delete administrators
- Granular permission system for admin accounts
- Backup restore
- Edit the most important config values

### _editor UI features
- Create/edit/delete elements (multilingual)
- Edit textfills (multilingual)
- Upload/replace the defined image slots
- Change password/email for administrator accounts
- View form submissions
- View/delete and export newsletter signups
- View own logs

*All features can be controlled per account via the permission system*

## Quick start

```
php -S 127.0.0.1:8000 router.php
```

Requires **PHP 8.4+** with the `gd` extension (image cropping/resizing) and
`phar.ini` support for `PharData` (needed for the automatic backups in
`_editor`, not restricted by `phar.readonly`). **No further setup required** -
`config.php` in the project root contains the site's configuration and is
re-read on every request. The router script needed for `_editor`/`_admin`,
the webserver configuration, and the full path to go-live are described in
**[docs/setup.md](docs/setup.md)**.

`text/`, `templates/` and `elements/` ship **empty** - there is no starting
content until `/_install`'s Setup step assembles some from its library, or
you add it by hand. First time on a fresh checkout? Visit `/_install` for a
graphical wizard through the initial setup (environment checks, content
setup, bulk base text, admins, _admin password) - see
**[docs/_install.md](docs/_install.md)**. It only runs until a real `/_admin`
password is set, and works just as well by hand instead.

## Structure

```
index.php        Main entry point of the website
config.php        Site configuration: routes, modules, locales, form settings

_nino/           Kernel - Nino.php (backend), Nino.js/Nino.css/Nino.ui.js (frontend core)
_editor/           Admin dashboard - content editors (values), users, backups, activity log
_admin/             Developer tools - element/text/image "schema" editors, config editor, restore
_install/         First-run setup wizard - checks, content setup (library/), bulk base
                  text, admins, _admin password
_templates/       Template builder - visual editor for templates/*.tpl, block library
                  (library/), gated by _admin's password

elements/         Content of the element types (one .php file per type) - empty by default
text/             Texts per locale + global.php (incl. design tokens) + blacklist.php - empty by default
templates/        .tpl page/section templates - empty by default
assets/           Project's own style.css/script.js
images/           Uploaded images (admin-managed, generated on demand)
docs/             _editor.md, _admin.md, _install.md, _templates.md, design.md, development.md,
                  and a .de.md counterpart for each
tests/            Dependency-free smoke tests (php tests/*.php)
data/             Runtime data (newsletter subscribers, form submissions,
                  logs) - created on demand, never tracked in git
```

## Documentation

- **[docs/development.md](docs/development.md)**
- **[docs/development.de.md](docs/development.de.md)** (German)
Everything for the **backend developer**. How the kernel is put together, the AppData concept, the callback system, the init → request → output pipeline, templates/shortcodes/textfills, the permission system, writing your own modules. The architecture of `_editor`/`_admin`, testing conventions.
---
- **[docs/design.md](docs/design.md)**
- **[docs/design.de.md](docs/design.de.md)** (German)
Everything for the **frontend designer/developer**. The HTML rendering flow, the architecture/naming conventions of the built-in CSS framework, building your own frontend elements and shortcodes.
---
- **[docs/_editor.md](docs/_editor.md)**
- **[docs/_editor.de.md](docs/_editor.de.md)** (German)
A **user guide** for the _editor administration interface.
---
- **[docs/_admin.md](docs/_admin.md)**
- **[docs/_admin.de.md](docs/_admin.de.md)** (German)
A **user guide** for the _admin developer interface.
---
- **[docs/_install.md](docs/_install.md)**
- **[docs/_install.de.md](docs/_install.de.md)** (German)
A **user guide** for the _install setup wizard.
---
- **[docs/_templates.md](docs/_templates.md)**
- **[docs/_templates.de.md](docs/_templates.de.md)** (German)
A **user guide** for the _templates template builder.

## Tests

```
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/editor-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
php tests/concurrency-smoke.php
```

Each file is a **standalone script** (no PHPUnit) that runs against an isolated sandbox directory and prints a pass/fail summary. CI runs all five on every push.

## License
[MIT](LICENSE)
