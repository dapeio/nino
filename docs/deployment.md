# Deployment and Go-Live

**Language:** English · [Deutsch](deployment.de.md)

**Last updated:** September 11, 2026 · **Nino version:** 1.2.0-beta

This manual guides a fully developed Nino website into production. If you instead want to set up a fresh project, start with [Getting Started](getting-started.md); technical extensions are covered in the [Developer Manual](development.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Recipes](recipes/README.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Features](features.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

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

The included `.htaccess` sets these baseline rules, provided the server allows `AllowOverride` for the project:

- Requests that resolve to neither a file nor a directory are forwarded to `index.php`, and `DirectoryIndex index.php` is what answers `/` and `/_admin/`. Without the first, every address but the homepage is the server's own 404; without the second, `/` finds no index file on a host whose PHP configuration does not add one to Apache's list, falls through to the directory listing, and is refused by the rule below. `DirectoryIndex` needs `AllowOverride Indexes`, one class more than the rest of the file - a server that grants the others but not that one answers every request with a 500, so remove that line there and set the directive in the vhost.
- Files with a leading dot are not delivered directly.
- Directories without an index file do not show a file list.
- The HTTP `Authorization` header reaches PHP so that the `/_admin` login can read its Basic credentials. Apache normally hides this header from CGI/FastCGI scripts. Two rules cover that, and the file ships both: `CGIPassAuth On`, and - for where that is not enough - a `RewriteRule` that copies the header into an environment variable. The directive needs Apache 2.4.13 or newer; an older one answers every request with a 500 because it does not know it, so remove that one line there and rely on the rewrite.
- `SetEnv NINO_HTACCESS 1`, which is how you find out whether any of the above is applied at all.

#### The login form is refused and the password is right

The symptom is always the same: `/_admin` answers `401` for credentials that are correct. The cause is that the credential pair never reached PHP. This probe says which of the three places it did not arrive in:

```php
<?php
// Drop next to index.php, call it as `curl -u test:secret https://…/probe.php`,
// and delete it again afterwards.
header( 'Content-Type: text/plain' );
echo 'SAPI:                        ', PHP_SAPI, "\n";
echo 'NINO_HTACCESS:               ', var_export( $_SERVER['NINO_HTACCESS'] ?? null, true ), "\n";
echo 'PHP_AUTH_USER:               ', var_export( $_SERVER['PHP_AUTH_USER'] ?? null, true ), "\n";
echo 'HTTP_AUTHORIZATION:          ', var_export( $_SERVER['HTTP_AUTHORIZATION'] ?? null, true ), "\n";
echo 'REDIRECT_HTTP_AUTHORIZATION: ', var_export( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null, true ), "\n";
```

Read it like this:

| | |
| --- | --- |
| `NINO_HTACCESS` is `NULL` | the `.htaccess` is not applied at all - `AllowOverride` is off for this directory. Fix that first: the same file is what keeps dotfiles, `.git/` and the whole `private/` tree from being served, so this is a disclosure question before it is a login question |
| `PHP_AUTH_USER` is set | nothing to do - this is `mod_php`, and the login works |
| `HTTP_AUTHORIZATION` is set | `CGIPassAuth` did its job; the login works |
| `REDIRECT_HTTP_AUTHORIZATION` is set | the rewrite fallback did its job, and `\Nino\Http` reads that variable - the login works |
| all three are `NULL`, SAPI is `cgi` or `cgi-fcgi` | neither rule reached the script. Either the `.htaccess` is not applied (see the first row), or `mod_rewrite` is off, or the host runs PHP through a wrapper that drops the header before Apache's own rules apply - ask the host to pass `Authorization` through, or set `CGIPassAuth On` in the vhost |


A separate protection rule lives in `private/.htaccess` and denies that directory outright. It is the one that matters most: `private/` holds `config.php`, the templates, the text and elements they render from, the data your visitors produce, and the stylesheet and script sources the asset bundle is built out of. Without it, a request for `private/templates/page-home.tpl` returns the template source as plain text.

Additionally, check in the hosting configuration how non-existent paths are passed to `index.php`. An `.htaccess` ignored by the server has no protective effect — and for `private/` that is not a hardening detail but a disclosure. If you cannot rely on `.htaccess`, point `NINO_PRIVATE_DIR` in `index.php` at a directory outside the webroot instead; then no server rule is needed at all.

#### The homepage is refused while `/index.php` answers

`/` returns `403` and `/index.php` returns Nino's `404` page. Neither is Nino refusing anything, and together they say exactly where the request stops.

`/index.php` is not a registered route, so the project answers it with its own 404 page - which is the proof that PHP runs and the project boots. The `403` never reached PHP at all: Apache looked for an index file in the directory, found none, and refused to list the directory instead.

Nino answers no `GET` with a `403`. The CSRF guard leaves the safe methods alone, and an address it does not know is a `404`. A `403` on a plain page request is therefore the server's, not the project's, and one look at the headers settles it - every Nino response carries a `Content-Security-Policy`, an Apache error page carries none:

```bash
curl -sSI https://…/ | grep -i 'content-security-policy\|content-type'
```

`DirectoryIndex index.php` is the fix and the shipped `.htaccess` carries it. If adding it changes nothing, the file is not being applied at all - check that first with the probe above, because the same file is what keeps `private/` from being delivered.

**nginx answers the same `403` for the same reason**, and `.htaccess` is never read there at all. So if the probe above says `NINO_HTACCESS` is `NULL` on a host that shows this symptom, the first question is not `AllowOverride` but whether this is Apache: `curl -sSI https://…/` names the server, and `$_SERVER['SERVER_SOFTWARE']` does too. On nginx a directory with no `index` match is a `403` because `autoindex` is off by default, and nothing in the project's own files can change that - the whole configuration is the server's, and the next section is the one that applies.

### Nginx and Other Web Servers

Transfer the same behavior explicitly to the server configuration:

- deliver existing public assets directly;
- forward normal website routes to `index.php`;
- route `/_admin` to `_admin/index.php` and leave `/_admin/recovery.php` to its own file;
- deny access to dotfiles and dot directories;
- **deny `private/` entirely** — it is never requested by a browser, only read by PHP;
- **deny `app/` and `features/` entirely** — the project's own classes and the installed features are server-side source, never requested by a browser; each ships its own `.htaccess` for Apache;
- **deny `_admin/install/library/` entirely** — it is what the wizard copies a project out of, server-side source with nothing public in it; the same goes for the section presets under `features/Templates/library/`, where a project that installed the Template Builder keeps them;
- disable directory listing;
- forward the HTTP `Authorization` header to PHP. With nginx/PHP-FPM this normally requires `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` in the PHP location;
- do not deliver PHP source and data files as text.

For nginx that is one `server` block. Only the PHP-FPM socket is yours to fill in - everything else is the same on every host:

```nginx
# What answers "/" - and "/_admin/", a directory with an index.php of its own.
# Without it nginx has no index to serve, autoindex is off by default, and "/"
# answers 403 while /index.php answers normally.
index index.php;

# Denied before routed: ^~ short-circuits the regex locations below, so
# nothing under these four trees is ever handed to PHP.
location ^~ /private/                { deny all; return 404; }
location ^~ /app/                    { deny all; return 404; }
location ^~ /features/               { deny all; return 404; }
location ^~ /_admin/install/library/ { deny all; return 404; }

# Dotfiles and dot directories, with .cache/ (the generated bundles), .demo/
# (the demo images) and .well-known/ as the exceptions. Only for paths that
# resolve on disk: /.form and /.newsletter are routes rather than files and
# have to keep falling through - the same line router.php draws.
location ~ /\.(?!cache/|demo/|well-known/) {
	if ( -e $request_filename ) { return 403; }
	try_files $uri $uri/ /index.php$is_args$args;
}

# An existing file is delivered; everything else is a route and belongs to Nino.
location / {
	try_files $uri $uri/ /index.php$is_args$args;
}

location ~ \.php$ {
	try_files     $uri =404;
	include       fastcgi_params;
	fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
	# The workbench login sends its credentials as an HTTP Basic header
	fastcgi_param HTTP_AUTHORIZATION $http_authorization;
	fastcgi_pass  unix:/run/php/php8.4-fpm.sock;   # yours
}
```

Or avoid the question for `private/` by moving the directory out of the webroot with `NINO_PRIVATE_DIR`; `NINO_APP_DIR` and `NINO_FEATURES_DIR` do the same for the other two - then each of those three blocks protects a directory that is not there any more, which is the stronger arrangement.

The paths and PHP-FPM settings of a specific hosting cannot be guessed reliably, and a panel-generated configuration usually already has a PHP location of its own to merge this into rather than to paste beside. So after setup, check both the routes you want and the direct accesses you do not: the section [Test Forbidden Direct Access](#test-forbidden-direct-access) is that list.

## Write Permissions

Before initial setup, PHP must be able to create directories and files in the project root. The still missing project paths are created by the wizard or, if needed, by the kernel and are not a manually required prerequisite.

During operation, Nino only needs write permissions for actually changeable content. Depending on usage, this includes `private/config.php`, `private/text/`, `private/elements/`, `private/data/`, `private/.logs/`, `private/.backups/`, `private/assets/`, `public/images/`, and `public/.cache/`. The Templates panel additionally needs `private/templates/`; it can create native text keys, Element Types, and image-slot definitions in the configuration. The installer library under `_admin/install/library/` itself remains read-only, as does `_admin/.cache/`'s content once built - the workbench writes its bundles there, so that one directory inside the tool folder needs write access. The project root and PHP source can otherwise remain read-only after installation. Installing a feature from the catalogue in the Features panel is the one exception: it writes `features/` and stages the download below `private/data/.features/`. Without write access to `features/` the panel offers the archive for a manual copy instead, so the directory can stay read-only where features are deployed with the project.

Grant these permissions to the user under which PHP is executed. World-writable permissions such as `0777` are not a suitable permanent solution. After deployment, the kernel and other PHP source code should not be generally writable.

## Configuration and Application Source Outside the Webroot

By default, the complete private tree including `config.php` lives in `private/`. `NINO_PRIVATE_DIR` moves that complete tree to an existing, writable directory outside the webroot. `NINO_CONFIG_DIR` can additionally point only `config.php` at a different existing, writable directory.

```php
define('NINO_PRIVATE_DIR', '/path/outside/the/webroot/nino-private');
// Or, to move config.php alone:
// define('NINO_CONFIG_DIR', '/path/outside/the/webroot');
```

Enter either definition before loading `_nino/Nino.php` - in every entry point. `index.php`, `_admin/index.php` and `_admin/recovery.php` each boot the kernel on their own, and a constant defined in one of them is not in force for the others: with it in the site's `index.php` alone, the workbench looks for `config.php` under the default path, finds none, and offers the setup wizard on a live site. The three files carry the lines commented out. An invalid explicit path stops boot; Nino never silently falls back to an in-project directory. Moving the complete tree with `NINO_PRIVATE_DIR` removes the need to protect `private/` through the webserver. Moving only `config.php` does not: the remaining private files must still not be delivered directly.

Separately, project-owned PHP classes load from `app/` by default and the
installed features from `features/`. `NINO_APP_DIR` and `NINO_FEATURES_DIR`
point the autoloader at other absolute source directories and must also be
defined before loading the kernel - in every entry point, like the two above.
Each replaces its directory as a whole: a project that points the features
root elsewhere moves its features along, or the kernel skips a module it can
no longer load without a word:

```php
define('NINO_APP_DIR', '/path/outside/the/webroot/nino-app');
define('NINO_FEATURES_DIR', '/path/outside/the/webroot/nino-features');
```

These source overrides do not move configuration or runtime data and do not
need write access in production - a feature's settings live in `config.php`,
its data under `data/`. A project-owned class is looked for in the app root
and nowhere else, a feature's class in the features root - `_nino/` is not a
second location for either. Classes in the kernel-owned `Nino\` namespace,
Nino's own modules among them, continue to load exclusively from `_nino/`.

## Settings for Production

Check in `config.php` or via the workbench's Config panel at least the following keys:

| Key | Production Value | Effect |
|---|---|---|
| `/nino/error/display` | `false` | suppresses technical error details in the browser |
| `/nino/error/log` | `true` | writes errors for later diagnosis to the log |
| `/nino/session/force-secure-cookie` | `true` if TLS terminates before PHP | enforces secure session cookies behind an HTTPS proxy |
| `/nino/admin/backups` | according to operational decision | controls the workbench's daily encrypted backup |
| `/nino/admin/logs` | according to operational decision | controls the workbench's activity log |
| `/nino/catalogue/url` | the default, or `''` where nothing is to be installed from the catalogue | where the Features panel loads the feature catalogue from - on request only, never on its own; empty switches the catalogue off |

Error messages should not expose file paths, configuration values, or stack traces in the browser. After switching, check that errors still arrive in a protected log and remain accessible to the operator.

## Secure the Workbench

Before go-live, the accounts must work and have strong passwords:

- `/_admin` is the one management interface - developers and editors sign in with their own accounts. A **Developer** account holds `/*`, an **Editor** account the Content permissions only; the [`/_admin` manual](_admin.md#login-accounts-and-roles) lists every permission.
- `/_admin/recovery.php` asks for the recovery password set in the wizard's last step and offers a restore and a password reset. Keep that password where the developer accounts' passwords are not.

Grant editor permissions as narrowly as practically possible; the accounts the wizard creates are developers, and additional accounts usually do not need that scope. Keep the number of developer accounts small.

HTTPS protects not only login data but also session cookies and all editorially transmitted content. Permanently redirect HTTP requests to HTTPS and only test login via the final public address.

Additional web server protection for `/_admin` - such as IP allowances or HTTP authentication - can form a useful second barrier under suitable operating conditions. It does not replace the accounts. The Template Builder is a feature, so its whole directory can go from `features/` - which is the point of it being one; the workbench itself stays, because the editors work in it.

## The Wizard After Setup

Complete the wizard fully. The last step sets the recovery password and locks the wizard. Then remove the `_admin/install/` directory from production delivery.

That takes the installer library under `_admin/install/library/` with it. This is deliberate: the library is setup material, not a runtime feature. Everything it copied already lives in the project - the theme as `assets/theme.css`, the two frames as `templates/theme.header.tpl` and `templates/theme.footer.tpl` - editable by hand and, for the frames, through the Templates panel. Nothing at runtime reads the library.

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
php tests/features-smoke.php
php tests/catalogue-smoke.php
for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done
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
- access and unchanged round-trip in the Template Builder, where the feature is installed;
- writing and reloading editorial content;
- behavior behind CDN, proxy, or cache, if used.

## Test Forbidden Direct Access

A successful call to the homepage does not yet prove that sensitive files are protected. Check with unauthenticated requests that the following categories, in particular, are not accessible as source code or directory content:

- dotfiles and dot directories;
- `config.php` and PHP data files;
- hidden log and backup directories;
- internal files from `_admin/`, `app/` and `features/` that are not intended as public assets - the panel templates, the section presets and a feature's install unit among them;
- any file below `_admin/install/library/`;
- `_admin/install/`, after it has been removed.

The expected response may be `403` or `404` depending on the server. The decisive factor is that neither content nor directory list is delivered.

## Updates and Rollback

Treat a Nino update like a change to the specific website project, not like blindly updating an interchangeable CMS core.

1. Secure the current production state outside the webroot.
2. First transfer the change to a development or staging environment.
3. Keep project-owned PHP classes in `app/` (or `NINO_APP_DIR`) and compare only deliberate kernel changes with the new state. `_nino/` can then be replaced wholesale - Nino's optional modules under `_nino/Nino/Modules/` included, since a project switches them on or off in `/nino/modules` rather than editing them - and so can `_admin/`: the workbench holds no project state - the accounts live in `config.php`, the recovery secret in `private/.auth/pw.php`. A feature is updated on its own: press **Update** on the Available tab of the workbench's Features panel, or replace its directory under `features/` with the new release by hand and press **Update** on the Active or Inactive tab, wherever it sits. The feature's install unit adds what is new and overwrites nothing the project has, and the feature migrates its own data before the new version is recorded; see [Features](features.md#updating).
4. Run smoke tests and project-specific acceptance.
5. Transfer the tested state and keep the previous version for rollback.

For an update that cannot happen invisibly, switch the System panel's Maintenance on beforehand and back off once step 4 has passed on the live state - a signed-in account still sees the site throughout, so the check itself does not need the switch off first.

Nino uses one project layout: private files belong in `private/`, browser-facing
files in `public/`, project-owned PHP source in `app/`, and installed features
in `features/`. It does not migrate alternative directory layouts during a
request. `NINO_PRIVATE_DIR` can move the complete private tree, `NINO_APP_DIR`
can replace the project application root and `NINO_FEATURES_DIR` the features
root. A non-`Nino\` class resolves against the app root and nowhere else -
`_nino/` holds the kernel and nothing of the project's own.

Nino is in the beta phase. Security fixes appear on `main`; there is currently no separate LTS line. Therefore, plan updates as active project maintenance and check `SECURITY.md` and the changelog before an update.

## Go-Live Checklist

- [ ] PHP version and extensions meet the requirements.
- [ ] Public routes are correctly forwarded to Nino.
- [ ] Dotfiles, dot directories, and PHP data files are not directly accessible.
- [ ] On Apache: the `.htaccess` is actually applied — `$_SERVER['NINO_HTACCESS']` is `1`. Everything below that relies on `.htaccess` is worth nothing if it is not.
- [ ] `app/` and `features/` are not served — each carries its own `.htaccess`; verify with a request for a file of an installed feature, e.g. `/features/Newsletter/install/templates/mail-header.tpl` once the catalogue's Newsletter feature is in place - a checkout ships no feature, so there has to be one to ask for.
- [ ] `private/` is not served — its own `.htaccess` denies it, and each PHP file inside carries a 403 stub; verify both apply on your webserver, or move the directory out of the webroot with `NINO_PRIVATE_DIR`. The templates and the asset sources are not PHP and have only the server rule.
- [ ] Directory listing is disabled.
- [ ] The setup wizard was able to create the project directories from the writable project root itself — a checkout ships neither `private/` nor `public/`, so the first step of the wizard is where that is confirmed.
- [ ] Write permissions are limited to the required paths after setup.
- [ ] The setup wizard was fully completed and `_admin/install/` subsequently removed from production.
- [ ] Developer and editor accounts are tested, and the recovery password is stored safely.
- [ ] The Template Builder is either removed from `features/` or consciously kept, and only developer accounts reach it.
- [ ] Editor accounts only have the necessary permissions.
- [ ] HTTPS and secure session cookies work at the final address.
- [ ] Error display is disabled and error logging is checked.
- [ ] Smoke tests and browser acceptance are successful.
- [ ] Backups are running, additionally stored externally, and can be restored.
- [ ] Every feature the site needs is activated in the Features panel and shows no pending update; every feature it does not need is deactivated.
- [ ] The previous project state is available for rollback.

## Next Steps

- [Getting Started](getting-started.md) describes the necessary initial setup.
- [`/_admin` Workbench](_admin.md) explains every panel, the accounts, backups and the recovery page.
- The **Template Builder** - page templates composed from whole sections - is a feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features); its [manual](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md) is there too.
- [Concepts](concepts.md) explains the technical structure behind the deployed project.
