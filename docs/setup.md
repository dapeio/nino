# Setup & Go-Live

How to take a fresh Nino project from checkout to a deployed site. For how
the framework is put together see `docs/developer.md`.

## Requirements

- PHP 8.4+
- `gd` extension (image crop/resize)
- `PharData` support (used by `_admin`'s automatic backups, not restricted
  by `phar.readonly`)
- No database, no Composer, no build step - plain PHP/HTML/CSS/JS files
  served directly.

## Local development

```
php -S 127.0.0.1:8000 router.php
```

The built-in server needs the shipped `router.php`: it dispatches
`/_admin/*` and `/_dev/*` to those directories' own `index.php`, serves
real static files, and blocks dotfiles (except `.cache/` bundles and
`.demo/` images) so files like `_dev/.lockout.json` are never readable by
direct request. Dot-uris that are routes (`/.newsletter`,
`/.demo-sections`) fall through to `index.php` and resolve normally.
`router.php` is for local development only - a real webserver replaces it
(see below).

## config.php

Lives in the project root, read fresh on every request. Edit by hand or
through `/_dev` → "Konfiguration". Keys worth deciding on before go-live:

- `/nino/modules` - active kernel modules. Only list what the project
  uses (`Form`/`Newsletter` send real mail and write a `/data` directory).
- `/nino/locales/native` / `/nino/locales/available` - the site's
  language(s).
- `/nino/error/display` - **`false` in production** (default). `true`
  prints raw errors including stack traces into the response.
- `/nino/error/log` - `true` in production (default), errors land in
  `/data/logs.<Y-m>.php`.
- `/nino/admin/backups` / `/nino/admin/logs` - `false` disables
  `_admin`'s daily encrypted backups / activity log.
- `/nino/http/routes` - the route table (`'METHOD://uri' => [ 'uri' =>,
  'body' =>, ... ]`). Every page needs an entry; `[template
  /templates/page-x]` as the `body` is the usual shape. Module-owned
  routes (eg. the newsletter's `/.newsletter`) register themselves and
  don't appear here.

## Content

- `elements/*.php` - one file per element type. Create types via `/_dev` →
  "Element Types".
- `text/*.php` - per-locale texts, plus `global.php` (site-wide values/
  design tokens) and `blacklist.php` (keys hidden from the admin panel).
  Edit via `/_admin` → "Texte".
- `templates/*.tpl` - page/section templates, hand-authored (see
  `docs/design-system.md`).
- `assets/style.theme01.css` / `assets/script.js` - project CSS/JS, loaded
  after the kernel's own files.
- `images/` - uploaded images, `_admin`-managed.

Missing directories are created on first write - nothing needs to be
seeded manually.

## Webserver (production)

Point the document root at the project root. `/_admin` and `/_dev` are real
directories with their own `index.php`, so the default "serve `index.php`
for a directory request" behavior is usually all that's needed. After
deploying, request `/_admin` and confirm the login page renders (not a
404, not a directory listing, not raw PHP source).

The shipped `.htaccess` (Apache) denies direct requests for dotfiles and
disables directory listing. On nginx the equivalent is:

```nginx
location ~ /\. {
    deny all;
}
autoindex off;
```

The webserver process needs write access to the project root (content,
uploads, caches, backups and logs are all written at request time).

## First admin user & the `_dev` password

`_dev/Dev.php` ships with a placeholder `PASSWORD_HASH` that matches no
password - generate a real one before deploying:

```
php _dev/Dev.php <your password>
```

Paste the hash into the `PASSWORD_HASH` constant (CLI only - the same file
requested over HTTP never executes that branch). Then log into `/_dev`,
create the first admin account under "Nutzer" with the "Verwaltungsrechte"
checkbox ticked - that account can manage everything else from `/_admin`.

`config.php` and `_dev/Dev.php` are listed in `.gitignore` - treat both as
per-deployment once they hold real credentials/config. `_dev` can stay
deployed: its Restore module is the disaster-recovery path for `_admin`'s
backups, protected by its own password and rate limiting.

## Go-live checklist

- [ ] `/nino/error/display` is `false`, `/nino/error/log` is `true`
- [ ] `_dev/Dev.php`'s `PASSWORD_HASH` is a real, project-specific hash
- [ ] `/_admin` renders a login page when requested directly
- [ ] The project root is writable by the webserver process
- [ ] The shipped `changeme@domain.com` account in `config.php` was replaced (new password via `/_dev` → Users) or removed entirely
- [ ] The first manager account exists and can log into `/_admin`
- [ ] Dotfiles (eg. `_dev/.lockout.json`) 404/403 when requested directly
- [ ] `php tests/*.php` all pass against the deployed `config.php`