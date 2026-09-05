# Deployment and Go-Live

**Language:** English · [Deutsch](deployment.de.md)

**Last updated:** September 5, 2026 · **Nino version:** 0.13.0-beta

This manual guides a fully developed Nino website into production. If you instead want to set up a fresh project, start with [Getting Started](getting-started.md); technical extensions are covered in the [Developer Manual](development.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Templates Panel](templates.md) · [Design Panel](appearance.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## Target System Requirements

Nino requires neither a database server nor a Composer installation on the target system. While this simplifies deployment, it makes the project files all the more important: Configuration and editorial data are stored directly in the file system and must be fully considered when transferring, securing, and authorizing.

The production system requires:

- PHP 8.4 or newer;
- the `gd`, `mbstring`, `session`, and `json` extensions plus the `PharData` class provided by `Phar`;
- a web server that delivers public files directly and forwards dynamic requests to Nino;
- HTTPS for all publicly accessible management interfaces;
- a writable project root before setup so that the setup wizard can create the still missing project directories.

Run the environment check of the wizard on an environment that corresponds to the later hosting. A locally successful installation does not yet prove that the web hosting plan provides the same PHP extensions and write permissions.

## Choose Deployment Model

For small projects, there are two sensible approaches:

1. The website is set up in a protected target environment and only then switched to public.
2. The website is fully set up locally and then transferred as a complete project state.

In both cases, the setup wizard must convert a fresh checkout into a valid project state once. If you transfer an already configured project, the generated project directories must be fully included in the deployment. Details on the initial setup are in [Getting Started](getting-started.md); the wizard is not an update tool for running projects.

**Security:** If the setup is done on the target system, `/_admin` must be protected by a front-end access control, internal network, or a not yet public environment until the wizard is completed - it has no access protection of its own until then.

## Webroot and Routing

The entry point of the public website is `index.php`. The workbench `/_admin` has its own (`_admin/index.php`), which serves the setup wizard until the project exists; `/_admin/recovery.php` is a third. The web server must deliver existing static files directly and forward all other website requests to Nino.

For local development, `router.php` takes over this behavior:

```bash
php -S 127.0.0.1:8000 router.php
```

This is a development server, not a production configuration.

### Apache

The included `.htaccess` sets two basic protection rules, provided the server allows `AllowOverride` for the project:

- Files with a leading dot are not delivered directly.
- Directories without an index file do not show a file list.

A third rule lives in `private/.htaccess` and denies that directory outright. It is the one that matters most: `private/` holds `config.php`, the templates, the text and elements they render from, the data your visitors produce, and the stylesheet and script sources the asset bundle is built out of. Without it, a request for `private/templates/page-home.tpl` returns the template source as plain text.

Additionally, check in the hosting configuration how non-existent paths are passed to `index.php`. An `.htaccess` ignored by the server has no protective effect — and for `private/` that is not a hardening detail but a disclosure. If you cannot rely on `.htaccess`, point `NINO_PRIVATE_DIR` in `index.php` at a directory outside the webroot instead; then no server rule is needed at all.

### Nginx and Other Web Servers

Transfer the same behavior explicitly to the server configuration:

- deliver existing public assets directly;
- forward normal website routes to `index.php`;
- route `/_admin` to `_admin/index.php` and leave `/_admin/recovery.php` to its own file;
- deny access to dotfiles and dot directories;
- **deny `private/` entirely** — it is never requested by a browser, only read by PHP;
- deny direct access to `_admin/install/library/` except `_admin/install/library/themes/<key>/preview.svg` — the remaining files are server-side appearance source; the same goes for the section presets under `app/Nino/Modules/Templates/library/`;
- disable directory listing;
- do not deliver PHP source and data files as text.

For nginx that last-but-two point is one block:

```nginx
location ^~ /private/ { deny all; return 404; }
```

Or avoid the question by moving the directory out of the webroot with `NINO_PRIVATE_DIR`.

A general example configuration cannot reliably guess the paths and PHP-FPM settings of a specific hosting. Therefore, after setup, check both desired routes and deliberately forbidden direct accesses.

## Write Permissions

Before initial setup, PHP must be able to create directories and files in the project root. The still missing project paths are created by the wizard or, if needed, by the kernel and are not a manually required prerequisite.

During operation, Nino only needs write permissions for actually changeable content. Depending on usage, this includes `private/config.php`, `private/text/`, `private/elements/`, `private/data/`, `private/.logs/`, `private/.backups/`, `private/assets/`, `public/images/`, and `public/.cache/`. The Templates panel additionally needs `private/templates/`; it can create native text keys, Element Types, and image-slot definitions in the configuration. Applying appearance variants in the Design panel needs `private/templates/`, `private/assets/`, `public/fonts/`, and any other destination declared by a Theme manifest. The appearance catalogue under `_admin/install/library/` itself remains read-only, as does `_admin/.cache/`'s content once built - the workbench writes its bundles there, so that one directory inside the tool folder needs write access. The project root and PHP source can otherwise remain read-only after installation.

Grant these permissions to the user under which PHP is executed. World-writable permissions such as `0777` are not a suitable permanent solution. After deployment, the kernel and other PHP source code should not be generally writable.

## Configuration and Application Source Outside the Webroot

By default, the complete private tree including `config.php` lives in `private/`. `NINO_PRIVATE_DIR` moves that complete tree to an existing, writable directory outside the webroot. `NINO_CONFIG_DIR` can additionally point only `config.php` at a different existing, writable directory.

```php
define('NINO_PRIVATE_DIR', '/path/outside/the/webroot/nino-private');
// Or, to move config.php alone:
// define('NINO_CONFIG_DIR', '/path/outside/the/webroot');
```

Enter either definition before loading `_nino/Nino.php` - in every entry point. `index.php`, `_admin/index.php` and `_admin/recovery.php` each boot the kernel on their own, and a constant defined in one of them is not in force for the others: with it in the site's `index.php` alone, the workbench looks for `config.php` under the default path, finds none, and offers the setup wizard on a live site. The three files carry the lines commented out. An invalid explicit path stops boot; Nino never silently falls back to an in-project directory. Moving the complete tree with `NINO_PRIVATE_DIR` removes the need to protect `private/` through the webserver. Moving only `config.php` does not: the remaining private files must still not be delivered directly.

Separately, project-owned PHP classes load from `app/` by default.
`NINO_APP_DIR` can point the autoloader at another absolute source directory
and must also be defined before loading the kernel - in every entry point, like
the two above. It replaces `app/` as a whole, and Nino's own optional modules -
Design, Templates, Form, Newsletter, Navigation, Localepicker, Search - live
under `app/Nino/Modules/`: a project that points the root elsewhere moves them
along, or the kernel skips a module it can no longer load without a word:

```php
define('NINO_APP_DIR', '/path/outside/the/webroot/nino-app');
```

This source override does not move configuration or runtime data and does not
need write access in production. A project-owned class is looked for there and
nowhere else - `_nino/` is not a second location. Classes in the kernel-owned
`Nino\` namespace continue to load exclusively from `_nino/`.

## Settings for Production

Check in `config.php` or via the workbench's Config panel at least the following keys:

| Key | Production Value | Effect |
|---|---|---|
| `/nino/error/display` | `false` | suppresses technical error details in the browser |
| `/nino/error/log` | `true` | writes errors for later diagnosis to the log |
| `/nino/session/force-secure-cookie` | `true` if TLS terminates before PHP | enforces secure session cookies behind an HTTPS proxy |
| `/nino/admin/backups` | according to operational decision | controls the workbench's daily encrypted backup |
| `/nino/admin/logs` | according to operational decision | controls the workbench's activity log |

Error messages should not expose file paths, configuration values, or stack traces in the browser. After switching, check that errors still arrive in a protected log and remain accessible to the operator.

## Secure the Workbench

Before go-live, the accounts must work and have strong passwords:

- `/_admin` is the one management interface - developers and editors sign in with their own accounts. A **Developer** account holds `/*`, an **Editor** account the Content permissions only; the [`/_admin` manual](_admin.md#login-accounts-and-roles) lists every permission.
- `/_admin/recovery.php` asks for the recovery password set in the wizard's last step and offers a restore and a password reset. Keep that password where the developer accounts' passwords are not.

Grant editor permissions as narrowly as practically possible; the accounts the wizard creates are developers, and additional accounts usually do not need that scope. Keep the number of developer accounts small.

HTTPS protects not only login data but also session cookies and all editorially transmitted content. Permanently redirect HTTP requests to HTTPS and only test login via the final public address.

Additional web server protection for `/_admin` - such as IP allowances or HTTP authentication - can form a useful second barrier under suitable operating conditions. It does not replace the accounts. The two developer panels that ship as modules, Templates and Design, can be removed from a production delivery by deleting `app/Nino/Modules/Templates/` and `app/Nino/Modules/Design/`; the workbench itself stays, because the editors work in it.

## The Wizard After Setup

Complete the wizard fully. The last step sets the recovery password and locks the wizard. Then remove the `_admin/install/` directory from production delivery.

That takes the appearance catalogue under `_admin/install/library/` with it. This is deliberate: the catalogue is setup material, not a runtime feature. An applied theme or frame already lives in the project as files under `assets/` and `templates/`, editable by hand and through the Templates panel. Inside the Design panel the Design tab keeps working unchanged — it generates the palette and the raster rather than copying files — while the three catalogue-backed tabs have nothing left to list and say so. Keep the locked `_admin/install/` deployed if you want Theme, Header, and Footer to stay switchable.

The order is essential:

1. create the developer account(s) in the Accounts step;
2. set the recovery password and complete the wizard;
3. check the frontend and the workbench with a developer account, then create the editor accounts and check what they see;
4. remove `_admin/install/` from the production system.

An incomplete installation does not become valid by deleting its wizard.

## Backups and Restoration

With activated backups, the workbench automatically creates an encrypted backup on the first authenticated request of the day. The daily backups rotate over 14 days and are stored under `private/.backups/`; the archives are encrypted with AES-256-GCM, the key lives under `private/.auth/`.

Restoration is done in the Backups panel, or - when no account works any more - on `/_admin/recovery.php`. Before restoring, Nino creates an additional backup of the current state so that an accidentally incorrect restoration does not immediately destroy the previous state.

These backups protect against many editorial errors but are not a complete hosting backup. A reliable backup strategy additionally copies the entire project, including configuration, texts, elements, images, and the keys necessary for restoration, to a separate location regularly. Test the restoration before it is needed in an emergency.

## Tests Before Go-Live

Run the included smoke tests with the same PHP major version that will later run in production:

```bash
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/admin-system-smoke.php
php tests/install-smoke.php
php tests/design-smoke.php
php tests/templates-smoke.php
php tests/search-smoke.php
php tests/demo-catalogue-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

The smoke tests do not replace project-specific acceptance testing. Additionally, check in the browser:

- all public routes and the error page;
- every active language and language switching;
- responsive display and used images;
- forms including validation, sending, and error messages;
- login, logout, and the permissions of an editor account in `/_admin`;
- a developer account's access to the Structure and System panels;
- all four tabs of the Design panel, including one frame preview, if the module is delivered;
- access and unchanged round-trip in the Templates panel, if the module is delivered;
- writing and reloading editorial content;
- behavior behind CDN, proxy, or cache, if used.

## Test Forbidden Direct Access

A successful call to the homepage does not yet prove that sensitive files are protected. Check with unauthenticated requests that the following categories, in particular, are not accessible as source code or directory content:

- dotfiles and dot directories;
- `config.php` and PHP data files;
- hidden log and backup directories;
- internal files from `_admin/` and `app/` that are not intended as public assets - the panel templates and the section presets among them;
- files below `_admin/install/library/` other than `_admin/install/library/themes/*/preview.svg`;
- `_admin/install/`, after it has been removed.

The expected response may be `403` or `404` depending on the server. The decisive factor is that neither content nor directory list is delivered.

## Updates and Rollback

Treat a Nino update like a change to the specific website project, not like blindly updating an interchangeable CMS core.

1. Secure the current production state outside the webroot.
2. First transfer the change to a development or staging environment.
3. Keep project-owned PHP classes in `app/` (or `NINO_APP_DIR`) and compare only deliberate kernel changes with the new state. `_nino/` can then be replaced wholesale, and so can `_admin/`: the workbench holds no project state - the accounts live in `config.php`, the recovery secret in `private/.auth/pw.php`. Nino's optional modules under `app/Nino/Modules/` are the project's to update: compare each directory you kept with the new release's copy and take the changes over, or replace it wholesale when you never changed it.
4. Run smoke tests and project-specific acceptance.
5. Transfer the tested state and keep the previous version for rollback.

Nino uses one project layout: private files belong in `private/`, browser-facing
files in `public/`, and project-owned PHP source in `app/`. It does not migrate
alternative directory layouts during a request. `NINO_PRIVATE_DIR` can move the
complete private tree, while `NINO_APP_DIR` can replace the project application
root. A non-`Nino\` class resolves against that root and nowhere else - `_nino/`
holds the kernel and nothing of the project's own.

Nino is in the beta phase. Security fixes appear on `main`; there is currently no separate LTS line. Therefore, plan updates as active project maintenance and check `SECURITY.md` and the changelog before an update.

## Go-Live Checklist

- [ ] PHP version and extensions meet the requirements.
- [ ] Public routes are correctly forwarded to Nino.
- [ ] Dotfiles, dot directories, and PHP data files are not directly accessible.
- [ ] `app/` is not served — its own `.htaccess` denies it; verify with a request for a module's install template, e.g. `/app/Nino/Modules/Newsletter/install/templates/mail-header.tpl`.
- [ ] `private/` is not served — its own `.htaccess` denies it, and each PHP file inside carries a 403 stub; verify both apply on your webserver, or move the directory out of the webroot with `NINO_PRIVATE_DIR`. The templates and the asset sources are not PHP and have only the server rule.
- [ ] Directory listing is disabled.
- [ ] The setup wizard was able to create the project directories from the writable project root itself — a checkout ships neither `private/` nor `public/`, so the first step of the wizard is where that is confirmed.
- [ ] Write permissions are limited to the required paths after setup.
- [ ] The setup wizard was fully completed and `_admin/install/` subsequently removed from production.
- [ ] If `_admin/install/` is deployed to keep Theme/Header/Footer switchable, it is locked and only its catalogue's Theme previews are directly accessible.
- [ ] Developer and editor accounts are tested, and the recovery password is stored safely.
- [ ] The Design and Templates modules are either removed or consciously delivered as Alpha, and only developer accounts reach them.
- [ ] Editor accounts only have the necessary permissions.
- [ ] HTTPS and secure session cookies work at the final address.
- [ ] Error display is disabled and error logging is checked.
- [ ] Smoke tests and browser acceptance are successful.
- [ ] Backups are running, additionally stored externally, and can be restored.
- [ ] The previous project state is available for rollback.

## Next Steps

- [Getting Started](getting-started.md) describes the necessary initial setup.
- [`/_admin` Workbench](_admin.md) explains every panel, the accounts, backups and the recovery page.
- [Templates Panel](templates.md) describes the optional template builder in Alpha status.
- [Design Panel](appearance.md) describes the four appearance editors.
- [Concepts](concepts.md) explains the technical structure behind the deployed project.
