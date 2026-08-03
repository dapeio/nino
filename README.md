# Nino

*[Deutsch](README.de.md)*

## **Hi, I am Nino.**
Nino is a lightweight PHP framework for websites. It works **independently of packages**, requires **no database**, and still provides all the essential features for developing and running modern web applications.
The framework includes a predefined file structure, a **kernel** for dynamically generating and rendering the page (_nino), and an **optional** graphical interface for developers (_dev) and operators (_admin).

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

## _dev / _admin
The Nino core _nino is **solely responsible for rendering the page**. In parallel, _dev (for developers) and _admin (for administrators) complement the frontend as graphical management tools.
Both are **entirely optional** and can be removed completely if needed. They enable efficient, fast development and then give operators and administrators a simple, pleasant way to maintain ongoing data - **with no technical know-how required**.
A well-thought-out **permission system** ensures administrator accounts only get the access they need. Compact logging, an integrated backup, and a clean security concept round out the toolset for everyday use.

### _dev UI features
- Create/edit/delete element types
- Create/edit/delete textfills
- Scan for undefined textfills
- Set up editable image slots for administrators
- Create/edit/delete administrators
- Granular permission system for admin accounts
- Backup restore
- Edit the most important config values

### _admin UI features
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
`_admin`, not restricted by `phar.readonly`). **No further setup required** -
`config.php` in the project root contains the site's configuration and is
re-read on every request. The router script needed for `_admin`/`_dev`,
the webserver configuration, and the full path to go-live are described in
**[docs/setup.md](docs/setup.md)**.

## Structure

```
index.php        Main entry point of the website
config.php        Site configuration: routes, modules, locales, form settings

_nino/           Kernel - Nino.php (backend), Nino.js/Nino.css/Nino.ui.js (frontend core)
_admin/           Admin dashboard - content editors (values), users, backups, activity log
_dev/             Developer tools - element/text/image "schema" editors, config editor, restore

elements/         Content of the element types (one .php file per type)
text/             Texts per locale + global.php (incl. design tokens) + blacklist.php
templates/        .tpl page/section templates
assets/           Project's own style.css/script.js
images/           Uploaded images (admin-managed, generated on demand)
docs/             _admin.md, _dev.md, design.md, development.md,
                  _admin.de.md, _dev.de.md, design.de.md, development.de.md,
tests/            Dependency-free smoke tests (php tests/*.php)
data/             Runtime data (newsletter subscribers, form submissions,
                  logs) - created on demand, never tracked in git
```

## Documentation

- **[docs/development.md](docs/development.md)**
- **[docs/development.de.md](docs/development.de.md)** (German)
Everything for the **backend developer**. How the kernel is put together, the AppData concept, the callback system, the init → request → output pipeline, templates/shortcodes/textfills, the permission system, writing your own modules. The architecture of `_admin`/`_dev`, testing conventions.
---
- **[docs/design.md](docs/design.md)**
- **[docs/design.de.md](docs/design.de.md)** (German)
Everything for the **frontend designer/developer**. The HTML rendering flow, the architecture/naming conventions of the built-in CSS framework, building your own frontend elements and shortcodes.
---
- **[docs/_admin.md](docs/_admin.md)**
- **[docs/_admin.de.md](docs/_admin.de.md)** (German)
A **user guide** for the _admin administration interface.
---
- **[docs/_dev.md](docs/_dev.md)**
- **[docs/_dev.de.md](docs/_dev.de.md)** (German)
A **user guide** for the _dev developer interface.

## Tests

```
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/dev-smoke.php
```

Each file is a **standalone script** (no PHPUnit) that runs against an isolated sandbox directory and prints a pass/fail summary. CI runs all three on every push.

## License
[MIT](LICENSE)
