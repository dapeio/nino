# Deployment and Go-Live

**Language:** English · [Deutsch](deployment.de.md)

**Last updated:** August 21, 2026 · **Nino version:** 0.11.0-beta.1

This manual guides a fully developed Nino website into production. If you instead want to set up a fresh project, start with [Getting Started](getting-started.md); technical extensions are covered in the [Developer Manual](development.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [`/_design` Operation](_design.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## Target System Requirements

Nino requires neither a database server nor a Composer installation on the target system. While this simplifies deployment, it makes the project files all the more important: Configuration and editorial data are stored directly in the file system and must be fully considered when transferring, securing, and authorizing.

The production system requires:

- PHP 8.4 or newer;
- `gd`, `mbstring`, `session`, `json`, `Phar`, and `PharData`;
- a web server that delivers public files directly and forwards dynamic requests to Nino;
- HTTPS for all publicly accessible management interfaces;
- a writable project root before setup so that `/_install` can create the still missing project directories.

Run the environment check of `/_install` on an environment that corresponds to the later hosting. A locally successful installation does not yet prove that the web hosting plan provides the same PHP extensions and write permissions.

## Choose Deployment Model

For small projects, there are two sensible approaches:

1. The website is set up in a protected target environment and only then switched to public.
2. The website is fully set up locally and then transferred as a complete project state.

In both cases, `/_install` must convert a fresh checkout into a valid project state once. If you transfer an already configured project, the generated project directories must be fully included in the deployment. Details on the initial setup are in [Getting Started](getting-started.md); `/_install` is not an update tool for running projects.

**Security:** If the setup is done on the target system, `/_install` must be protected by a front-end access control, internal network, or a not yet public environment until completion.

## Webroot and Routing

The entry point of the public website is `index.php`. The areas `/_editor`, `/_admin`, `/_design`, `/_templates`, and during setup `/_install` have their own entry points. The web server must deliver existing static files directly and forward all other website requests to Nino.

For local development, `router.php` takes over this behavior:

```bash
php -S 127.0.0.1:8000 router.php
```

This is a development server, not a production configuration.

### Apache

The included `.htaccess` sets two basic protection rules, provided the server allows `AllowOverride` for the project:

- Files with a leading dot are not delivered directly.
- Directories without an index file do not show a file list.

A third rule lives in `private/.htaccess` and denies that directory outright. It is the one that matters most: `private/` holds `config.php`, the templates, the text and elements they render from, and the data your visitors produce. Without it, a request for `private/templates/page-home.tpl` returns the template source as plain text.

Additionally, check in the hosting configuration how non-existent paths are passed to `index.php`. An `.htaccess` ignored by the server has no protective effect — and for `private/` that is not a hardening detail but a disclosure. If you cannot rely on `.htaccess`, point `NINO_CONTENT_DIR` in `index.php` at a directory outside the webroot instead; then no server rule is needed at all.

### Nginx and Other Web Servers

Transfer the same behavior explicitly to the server configuration:

- deliver existing public assets directly;
- forward normal website routes to `index.php`;
- route `/_editor`, `/_admin`, `/_design`, `/_templates`, and possibly `/_install` to their own entry points;
- deny access to dotfiles and dot directories;
- **deny `private/` entirely** — it is never requested by a browser, only read by PHP;
- deny direct access to `_install/library/` except `_install/library/themes/<key>/preview.svg` — the remaining files are server-side appearance source;
- disable directory listing;
- do not deliver PHP source and data files as text.

For nginx that last-but-two point is one block:

```nginx
location ^~ /private/ { deny all; return 404; }
```

Or avoid the question by moving the directory out of the webroot with `NINO_CONTENT_DIR`.

A general example configuration cannot reliably guess the paths and PHP-FPM settings of a specific hosting. Therefore, after setup, check both desired routes and deliberately forbidden direct accesses.

## Write Permissions

Before initial setup, PHP must be able to create directories and files in the project root. The still missing project paths are created by `/_install` or, if needed, by the kernel and are not a manually required prerequisite.

During operation, Nino only needs write permissions for actually changeable content. Depending on usage, this includes `private/config.php`, `private/text/`, `private/elements/`, `private/data/`, `private/.logs/`, `private/.backups/`, `public/images/`, and `public/.cache/`. The Template Builder under `/_templates` additionally needs `private/templates/`; it can create native text keys, Element Types, and image-slot definitions in the configuration. Applying appearance variants in `/_design` needs `private/templates/`, `public/assets/`, `public/fonts/`, and any other public destination declared by a Theme manifest. The appearance catalogue under `_install/library/` itself remains read-only. `/_admin` writes, depending on the function, configuration, texts, elements, and images, among others. The project root and PHP source can otherwise remain read-only after installation.

Grant these permissions to the user under which PHP is executed. World-writable permissions such as `0777` are not a suitable permanent solution. After deployment, the kernel and other PHP source code should not be generally writable.

## Configuration and Application Source Outside the Webroot

By default, the complete private tree including `config.php` lives in `private/`. `NINO_CONTENT_DIR` moves that complete tree to an existing, writable directory outside the webroot. `NINO_CONFIG_DIR` can additionally point only `config.php` at a different existing, writable directory.

```php
define('NINO_CONTENT_DIR', '/path/outside/the/webroot/nino-private');
// Or, to move config.php alone:
// define('NINO_CONFIG_DIR', '/path/outside/the/webroot');
```

Enter either definition in `index.php` before loading `_nino/Nino.php`. An invalid explicit path stops boot; Nino never silently falls back to an in-project directory. Moving the complete tree with `NINO_CONTENT_DIR` removes the need to protect `private/` through the webserver. Moving only `config.php` does not: the remaining private files must still not be delivered directly.

Separately, project-owned PHP classes load from `app/` by default.
`NINO_APP_DIR` can point the autoloader at another absolute source directory
and must also be defined before loading the kernel:

```php
define('NINO_APP_DIR', '/path/outside/the/webroot/nino-app');
```

This source override does not move configuration or runtime data and does not
need write access in production. Classes in the kernel-owned `Nino\` namespace
continue to load exclusively from `_nino/`.

## Settings for Production

Check in `config.php` or via the Config module of `/_admin` at least the following keys:

| Key | Production Value | Effect |
|---|---|---|
| `/nino/error/display` | `false` | suppresses technical error details in the browser |
| `/nino/error/log` | `true` | writes errors for later diagnosis to the log |
| `/nino/session/force-secure-cookie` | `true` if TLS terminates before PHP | enforces secure session cookies behind an HTTPS proxy |
| `/nino/editor/backups` | according to operational decision | controls automatic backups of the editor |
| `/nino/editor/logs` | according to operational decision | controls logging of the editor |

**Important:** Use the keys under `/nino/editor/*` for editor backups and logs. Older configuration states or texts may still mention `/nino/admin/*`; the current editor reads the editor keys.

Error messages should not expose file paths, configuration values, or stack traces in the browser. After switching, check that errors still arrive in a protected log and remain accessible to the operator.

## Secure Management Areas

Before go-live, both accesses must work and have separate, strong passwords:

- `/_admin` is the full technical and content management for developers.
- `/_design` edits Theme, Design, Header, and Footer and uses the password, lock status, and session of `/_admin`.
- `/_templates` is the section-first Alpha tool and uses the password, lock status, and session of `/_admin`.
- `/_editor` has individual user accounts and permissions for operators and editors.

Grant editor permissions as narrowly as practically possible. The first account created during `/_install` has full permissions over `/*`; additional editors usually do not need this scope.

HTTPS protects not only login data but also session cookies and all editorially transmitted content. Permanently redirect HTTP requests to HTTPS and only test login via the final public address.

Additional web server protection for `/_admin`, `/_design`, and `/_templates`—such as IP allowances or HTTP authentication—can form a useful second barrier under suitable operating conditions. It does not replace the Nino password. If the tools are not needed after development and acceptance, all three can be removed from production delivery.

## `/_install` After Setup

Complete the assistant fully. The last step sets the real password for `/_admin` and locks the installer. Then remove the `_install/` directory from production delivery.

That takes the appearance catalogue under `_install/library/` with it. This is deliberate: the catalogue is setup material, not a runtime feature. An applied theme or frame already lives in the project as files under `assets/` and `templates/`, editable by hand and through `/_templates`. Inside `/_design` the Design dialog keeps working unchanged — it generates the palette and the raster rather than copying files — while the three catalogue-backed dialogs have nothing left to list. Keep the locked `_install/` deployed if you want Theme, Header, and Footer to stay switchable.

The order is essential:

1. create at least one working user for `/_editor`;
2. set the password for `/_admin` and complete the assistant;
3. check frontend, `/_editor`, and `/_admin`;
4. remove `_install/` from the production system.

An incomplete installation does not become valid by deleting its installer.

## Backups and Restoration

With activated backups, `/_editor` automatically creates an encrypted backup on the first login of the day. The intended backups rotate over 14 days and are stored in a randomly named, hidden directory within `_editor/`. The archives are encrypted with AES-256-GCM.

Restoration is done via `/_admin`. Before restoring, Nino creates an additional backup of the current state so that an accidentally incorrect restoration does not immediately destroy the previous state.

These backups protect against many editorial errors but are not a complete hosting backup. A reliable backup strategy additionally copies the entire project, including configuration, texts, elements, images, and the keys necessary for restoration, to a separate location regularly. Test the restoration before it is needed in an emergency.

## Tests Before Go-Live

Run the included smoke tests with the same PHP major version that will later run in production:

```bash
php tests/kernel-smoke.php
php tests/editor-smoke.php
php tests/admin-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

The smoke tests do not replace project-specific acceptance testing. Additionally, check in the browser:

- all public routes and the error page;
- every active language and language switching;
- responsive display and used images;
- forms including validation, sending, and error messages;
- login, logout, and permissions in `/_editor`;
- technical access to `/_admin`;
- all four appearance dialogs in `/_design`, including one frame preview;
- access and unchanged round-trip in `/_templates`, if the Alpha builder is delivered;
- writing and reloading editorial content;
- behavior behind CDN, proxy, or cache, if used.

## Test Forbidden Direct Access

A successful call to the homepage does not yet prove that sensitive files are protected. Check with unauthenticated requests that the following categories, in particular, are not accessible as source code or directory content:

- dotfiles and dot directories;
- `config.php` and PHP data files;
- hidden log and backup directories;
- internal files from `_admin/`, `_design/`, `_templates/`, and `_editor/` that are not intended as public assets;
- files below `_install/library/` other than `_install/library/themes/*/preview.svg`;
- `_install/`, after it has been removed.

The expected response may be `403` or `404` depending on the server. The decisive factor is that neither content nor directory list is delivered.

## Updates and Rollback

Treat a Nino update like a change to the specific website project, not like blindly updating an interchangeable CMS core.

1. Secure the current production state outside the webroot.
2. First transfer the change to a development or staging environment.
3. Keep project-owned PHP classes in `app/` (or `NINO_APP_DIR`) and compare only deliberate kernel changes with the new state. `_nino/` can then be replaced wholesale. The management interfaces themselves no longer hold project state: the `/_admin` password lives in `private/.auth/pw.php`, so `_admin/` can likewise be replaced without losing the login or re-opening `/_install`.
4. Run smoke tests and project-specific acceptance.
5. Transfer the tested state and keep the previous version for rollback.

Nino uses one project layout: private files belong in `private/`, browser-facing
files in `public/`, and project-owned PHP source in `app/`. It does not migrate
alternative directory layouts during a request. `NINO_CONTENT_DIR` can move the
complete private tree, while `NINO_APP_DIR` can replace the project application
root. Non-`Nino\` classes still have a compatibility fallback below `_nino/`,
but that fallback is for existing projects rather than new code.

Nino is in the beta phase. Security fixes appear on `main`; there is currently no separate LTS line. Therefore, plan updates as active project maintenance and check `SECURITY.md` and the changelog before an update.

## Go-Live Checklist

- [ ] PHP version and extensions meet the requirements.
- [ ] Public routes are correctly forwarded to Nino.
- [ ] Dotfiles, dot directories, and PHP data files are not directly accessible.
- [ ] `private/` is not served — its own `.htaccess` denies it, and each file inside carries a 403 stub; verify both apply on your webserver, or move the directory out of the webroot with `NINO_CONTENT_DIR`.
- [ ] Directory listing is disabled.
- [ ] `/_install` was able to create the project directories from the writable project root itself.
- [ ] Write permissions are limited to the required paths after setup.
- [ ] `/_install` was fully completed and subsequently removed from production.
- [ ] If `_install/` is deployed to keep Theme/Header/Footer switchable, it is locked and only its catalogue's Theme previews are directly accessible.
- [ ] `/_admin` and `/_editor` have tested, separate accesses.
- [ ] `/_design` is either removed together with `/_admin` or protected by and tested with that admin access.
- [ ] `/_templates` is either removed or protected with the admin access and consciously released as Alpha.
- [ ] Editor users only have the necessary permissions.
- [ ] HTTPS and secure session cookies work at the final address.
- [ ] Error display is disabled and error logging is checked.
- [ ] Smoke tests and browser acceptance are successful.
- [ ] Backups are running, additionally stored externally, and can be restored.
- [ ] The previous project state is available for rollback.

## Next Steps

- [Getting Started](getting-started.md) describes the necessary initial setup.
- [`/_admin` Operation](_admin.md) explains restoration, full content, accounts, and technical configuration.
- [`/_templates` Operation](_templates.md) describes the optional template builder in Alpha status.
- [`/_editor` Operation](_editor.md) describes the released content maintenance, operational data, and daily backups.
- [Concepts](concepts.md) explains the technical structure behind the deployed project.
