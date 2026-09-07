# Changelog

All notable changes to Nino are documented in this file.

## 1.1.0-beta — 2026-09-07

Features. An installable package is one directory below `features/` with a
`feature.php` manifest, switched on in the workbench rather than picked in
the wizard; Nino's optional modules ship with the kernel again, and `app/`
is the project's alone. The features themselves are published from the
catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) -
a checkout ships none.

### Added

- **`\Nino\Features`** (`_nino/Nino/Features/Features.php`): discovers the
  directories below `features/`, reads and validates their manifests, matches
  the `nino` version constraint (`*`, exact, `>=`/`<=`/`>`/`<`/`!=`, `^1.0`,
  `~1.2`, `~1.2.3`, parts joined by comma or space, `||` alternatives; a
  pre-release kernel counts as its release), answers a feature's settings
  with the schema's defaults, validates a posted settings form, and
  activates, updates and deactivates a feature.
- **The manifest** `feature.php`: `key`, `name` and `description` (a string
  or a `locale => string` map), `version`, `nino`, `php => [ 'ext' ]`,
  `requires`, `settings` and `data`. The class is derived from the directory
  - `\Nino\Modules\<Directory>` - and a `module` entry naming anything else
  is refused; a manifest that does not validate is skipped with a warning,
  and so is a second directory claiming a key.
- **Settings** with the types `bool`, `int`, `string`, `text`, `email`,
  `url`, `select`, `secret` and `lines`, each with `label`, `hint`,
  `required` and a validated `default` (none on a `secret`); `min`, `max` and
  `unit` for an int, `maxlength` and `pattern` for a string, `options` for a
  select. Stored under `/nino/features` in `config.php` as
  `key => { version, settings }`, read through
  `\Nino\Features::setting()` / `settings()`; a form posts strings and gets
  real types back, a secret posted empty keeps the stored one and `null`
  clears it, every setting is validated before any is written.
- **Activation** applies the feature's `install/` unit without overwriting
  anything the project has - an existing route key, template, file or text
  key stays, a unit `config` default fills in only where the project has
  nothing - activates required features first, lists the class in
  `/nino/modules` and records the version. Activating an active feature
  applies an update: the unit adds what is new, and a module implementing
  `upgrade( array &$appData, string $fromVersion ): bool` migrates its own
  data first (`false` refuses). Deactivation removes the class and nothing
  else, and is refused while another active feature requires it.
- **The Features panel** in the workbench's System group (permission
  `/_admin/features/manage`, actions `features/list`, `features/activate`,
  `features/deactivate`, `features/settings`): lists every feature in the
  directory with its state and its problems, activates, deactivates, updates
  and edits settings. A reload of the workbench shows or removes a panel a
  feature brings.
- **`NINO_FEATURES_DIR`** relocates `features/` the way `NINO_APP_DIR` does
  `app/`; `index.php`, `_admin/index.php` and `_admin/recovery.php` carry the
  line. `features/.htaccess` denies the tree like `app/.htaccess`,
  `router.php` mirrors it.
- **`tests/harness.php`**, the shared bootstrap of a smoke test: `check()`,
  `ninoSandbox()`, `ninoSandboxDir()`, `ninoWarnings()`, `ninoDone()`. A
  feature's own test lives in `features/<Name>/tests/<key>-smoke.php` and
  loads it from the checkout three levels up or from `NINO_ROOT`.
  `tests/features-smoke.php` covers the contract against
  `tests/fixtures/features/` (`Sample/` with every settings type, a panel,
  an install unit and `upgrade()`; `Helper/`, `Old/`, `Broken/`). CI runs it
  and then every feature's test; PHPStan analyses `features/` and excludes
  `features/*/tests/*`.
- **A `features` job in CI** clones the catalogue
  [dapeio/nino-features](https://github.com/dapeio/nino-features), copies
  every feature of it into the checkout and runs the features' own tests and
  PHPStan against it on every push - the gate against a kernel change that
  breaks a published feature. The catalogue's CI does the reverse against
  Nino's `main` and latest tag. The checkout's own feature-test loop
  survives an empty `features/`.
- `'/nino/features' => []` in `AppData::DEFAULTS`; `Features` in the kernel
  class list.
- Docs: `docs/features.md` and `docs/features.de.md`, the feature manual and
  contract; `docs/recipes/feature.md`, the seventh recipe; Features in every
  manual's navigation line, in `AGENTS.md`'s map and tables, and in the
  roadmap.

### Changed

- **Nino's optional modules ship with the kernel.** `Form`, `Navigation`,
  `Localepicker`, `Design` and `Templates` moved from `app/Nino/Modules/` to
  `_nino/Nino/Modules/`, beside the always-on kernel modules; a project
  switches them on or off in `/nino/modules`, and `_nino/` is replaceable
  wholesale again. `app/` holds project-owned classes only (`app/.htaccess`
  stays).
- **`Newsletter` and `Search` are features**, each with a manifest, and
  live in the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features)
  rather than in the checkout (see Removed); the wizard's Setup step no
  longer offers them - it offers navigation, language selection and the
  contact form - and a feature is switched on in the Features panel after
  setup. The demo catalogue page unit no longer requires `newsletter` and
  ships its two newsletter labels itself.
- **The autoloader resolves `Nino\Modules\*` over four roots**, in this
  order: `_nino/`, `_admin/`, `features/` (or `NINO_FEATURES_DIR`), then
  `app/` (or `NINO_APP_DIR`). Below the features root the `Nino/Modules/`
  prefix is the directory itself (`features/<Name>/<Name>.php`). A shipped
  module cannot be shadowed, a feature cannot replace a workbench screen, a
  project cannot replace an installed feature, `app/` can only add.
- **The wizard applies its units through `\Nino\Features::applyUnit()`**,
  with overwrite on, so `_admin/install/` may still be deleted after setup
  and a feature's activation - add-only - shares one implementation with it.
  `Setup::units()` scans `_nino/Nino/Modules/*/install/`, the app dir and
  `_admin/install/library/modules/`, never `features/`.
- The Search feature's own test, `features/Search/tests/search-smoke.php`
  in the catalogue, runs over the harness; Nino's suites keep the kernel and
  workbench contract, with `Modules\Form` as the example of a module whose
  panel comes and goes.
- Docs: the READMEs, `AGENTS.md`, the developer, setup, workbench,
  deployment, concepts, templates, design and getting-started manuals and the
  recipes describe the layout above; the test lists name
  `tests/features-smoke.php` and the feature tests.

### Removed

- `app/Nino/Modules/` - nothing of Nino's is delivered below `app/` any more.
- **`Newsletter` and `Search` no longer ship with the checkout.** They live in
  the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features)
  (`features/Newsletter/`, `features/Search/`, each with `feature.php`,
  tests, README and changelog); a project installs one by copying its
  directory into `features/` and activating it in the Features panel.
  `features/` holds nothing but `.htaccess` in a checkout. The Newsletter's
  own tests - signup, confirm and unsubscribe, its panel, its restore merge -
  travel with it. The `newsletter-form` section preset of the Template
  Builder still ships with the kernel and needs the Newsletter feature to
  answer its form.
- `tests/search-smoke.php` - see the Search feature's own test,
  `features/Search/tests/search-smoke.php` in the catalogue.

## 1.0.0-beta — 2026-09-06

The 1.0 baseline: the shape 0.13.0-beta settled on, with static analysis in
CI, a kernel in one file per class, and the extension recipes as
documentation. Nothing a project or a module calls was removed, and no
project file format changed.

### Added

- Static analysis in CI. `phpstan.neon` runs PHPStan at level 5, with
  `phpstan-baseline.neon` holding the findings that were open when the check
  arrived, so only something new fails; `eslint.config.mjs` checks the browser
  scripts for undefined names, unused code and `==`. Both run after the syntax
  lints. An `.editorconfig` states the tabs/LF style the source already uses.
- `docs/recipes/`: the six extension recipes that lived in `AGENTS.md` - panel,
  runtime module, installer package, section preset, templates and page units,
  element types - one file each, with an index.

### Changed

- **The kernel is one file per class.** `_nino/Nino.php` keeps `\Nino\init()`,
  `request()`, `output()` and the autoloader; `AppData`, `Auth`, `Callbacks`,
  `Csrf`, `Filesystem`, `Backup`, `RotatingLog`, `Elements`, `Html`, `Http`,
  `Images`, `Locales`, `Text`, `Mail`, `Modules` and `Runtime` live under
  `_nino/Nino/<Class>/<Class>.php` and are loaded on first use. No public API
  changed.
- `AGENTS.md` holds the rules alone and links the recipes; its sections 13 to 18
  are 8 to 13 now.
- `\Nino\AppData::writeContentData()` returns `bool` instead of `void`.
- This changelog is condensed to release notes. The complete development notes
  of the beta versions remain in the git history before this change.
- `SECURITY.md` no longer names a version.
- Docs: the READMEs, the developer manual and `AGENTS.md` name the static
  analysis and the kernel layout; the recipes sit in every manual's navigation
  line and in the roadmap.

### Fixed

- `Design::saveSettings()` could never see a failed `config.php` write;
  `Elements::_cacheElement()` was handed an int array key as its locale;
  `[elementvalues]` passed an int id to `str_replace()`; the exception handler
  is registered with the signature PHP asks for; `Modules\Routes\Admin::_setText()`
  had no caller and is gone.
- `login.js` compared with `==`, `Nino.ui.js` hid two assignments in
  conditions, `area-composer.js` carried two unused helpers.

## 0.13.0-beta — 2026-09-06

One workbench. `/_admin`, `/_editor`, `/_install`, `/_design` and
`/_templates` were five tools with three logins; they are one now, `/_admin`,
with accounts and roles, and every screen in it is a panel a module can bring.

> No project built on an earlier version carries over: the tool directories,
> their routes, the `/_editor/*` permissions, the `/nino/editor/*` keys and
> the shared `_admin` password are gone, and there is no compatibility path.
> Nino is pre-1.0 and this is the shape it settles on before the first
> release. Read **Removed** before touching a checkout that predates it.

### Added

- **Accounts and roles** (`\Nino\Auth`). An account has a mail, a password and
  a role; a role is a named set of permissions under `/nino/auth/roles`, one
  string per panel or tab, `/_admin/<uri>/manage` by convention. The wizard
  writes **Editor** (every content panel's permission) and **Developer** (`/*`)
  and creates the root account. The Users panel creates and deletes accounts
  and hands out roles; its **User roles** tab edits the roles, its **Login
  protection** tab holds the throttle (`\Nino\Modules\Users\Lockout`). A change
  that would take *Manage users* from your own account or leave no full access
  behind is refused.
- **Scoped permissions** for Elements and Text, e.g.
  `/_admin/elements/services/update/title` or
  `/_admin/text/update/page-home/atf/title`, matched by the existing `/*` rule.
  An account holding none under a panel keeps that panel's full meaning; the
  panels send what the account may do, so a read-only field is locked and
  buttons it may not use disappear. Typed by hand in the roles editor.
- **`/nino/admin/action`**, a callback fired after every dispatched workbench
  action with `{ action, panel, status, user, data }`, for failed actions too.
- **Tabs.** A panel may answer `tabs()` with further panel classes, each with
  its own permission, script and hash prefix. Element Types, Text Keys, Image
  Slots, User roles, Login protection, Translations and the new **Language**
  panel (`\Nino\Modules\Language\Admin`) use it.
- **The panel contract**: `perm()`, `nav()` with a group (`content`,
  `structure`, `system`), and optionally `template()`, `layout()` (`page` or
  `workspace`), `icon()`, `tabs()` and `tab()`; `\Nino\Admin\Panels::relative()`
  turns a `__DIR__` path into the project-relative form.
- **The shell**: a foldable rail, workspace panels, deep links (`#design/header`),
  panels that load on first selection, dashboard tiles, the Elements panel's
  read-only **Raw storage** view.
- **`_admin/recovery.php`**: restore a backup or reset a password with the
  recovery password the wizard's last step sets (`\Nino\Admin\Recovery`, hash
  under `private/.auth/pw.php`, rate-limited).
- **Duplicate an element** from the element form, and **delete an element
  type** with its elements and images from the Element Types form - refused
  while another type's `element` field points at it.
- The text-key scan creates the rows with a value, passes over empty ones and
  ignores a row permanently through `/text/blacklist.php`, in one request
  (`keys/scanapply`).
- The Template Builder and the Design panel speak the interface language: every
  string is a fill (`app/Nino/Modules/Templates/text/<locale>.php`,
  `/_admin/design/knob/<knob>/…`). `tests/admin-lists-js-smoke.js` fails on a
  sentence written straight into the dom, `tests/admin-system-smoke.php` on a
  missing key or an unresolved fill in either interface language.
- **Optional modules live in `app/Nino/Modules/`**: `Form`, `Newsletter`,
  `Navigation`, `Search`, `Localepicker`, `Design` and `Templates`, owned by the
  project from then on. **Design and Templates are modules with a panel each**
  (`\Nino\Modules\Design`, `\Nino\Modules\Templates`), listed in `/nino/modules`
  whenever their directory exists.
- `Setup::units()` scans `_nino/Nino/Modules`, then the app's modules, then the
  rest of the app dir, so a delivered module claims a key before a project unit
  of the same name.

### Changed

- **`NINO_PRIVATE_DIR` replaces `NINO_CONTENT_DIR`**; `NINO_CONFIG_DIR` stays.
  `index.php`, `_admin/index.php` and `_admin/recovery.php` each boot the
  kernel, so a deployment defines its constants in all three. `NINO_APP_DIR`
  replaces `app/` as a whole, Nino's optional modules included.
- **`app/` is denied like `private/`** (`app/.htaccess`, `router.php`).
- **Every workbench screen is a module** under `_admin/Nino/Modules/<Name>/`:
  `Dashboard`, `Elements`, `Text`, `Images`, `Logs`, `Routes`, `Users`,
  `Language`, `Backups`, `Config`. `_admin/` holds the shell alone
  (`\Nino\Admin\Admin`, `\Nino\Admin\Panels`, `\Nino\Admin\Recovery`,
  `_admin/assets/`, `_admin/text/`); `Admin::modules()` reads the directory.
  The autoloader takes `_admin/` as a third root for `Nino\Modules\*`, between
  `_nino/` and `app/`. Both bundles are built from the registry.
- The design system moved into the `nino.system` half of
  `_admin/assets/style.css` and `Nino.admin.js`. `.nino-admin-tools` is gone;
  `.nino-admin-rail-toggle`, `.nino-admin-nav-group`, `.nino-admin-nav-icon`,
  `.nino-admin-nav-label`, `.nino-admin-shell--workspace` and
  `.nino-admin-shell--folded` are new.
- **One language, one empty state.** Every word the workbench renders is a fill
  in the panel's own `text/<locale>.php`; scripts read
  `Nino.content.getText()`, backend labels go through `Nino.adminUi.text()`,
  `Nino.adminUi.emptyState()` is the one empty state. The workbench falls back
  to English for a site language it has no words in.
- The panels merged: **Text** and **Text Keys**, **Images** and **Image
  Slots**, one **Users**, one **Elements**, **Backups**, **Log**. Permissions
  are `/_admin/<uri>/manage` (`/view` for Submissions and Log); the backup and
  log switches are `/nino/admin/backups` and `/nino/admin/logs`.
- The rail groups Content, Structure and System. Config keeps errors, the
  workbench switches and the page cache; the login throttle moved to Users, the
  languages to Language (`config/addlocale` is `language/addlocale`);
  `users/permissions` is `users/role`.
- The setup wizard is the workbench's first-run mode
  (`_admin/install/Install.php`, library `_admin/install/library/`), with the
  steps Environment, Setup, Themes, Header, Footer, Design, Routes, Personal
  Infos, Accounts and Finish.
- `Modules\Cache` drops the cache on any successful `POST` to `/_admin`;
  `router.php` routes `/_admin` and `/_admin/recovery.php` and denies the
  library except the theme previews.
- `Nino.editor.*`, `Nino.design` and `Nino.templates` are `Nino.admin.<uri>`.
- Tests: `editor-smoke.php` is `admin-smoke.php`, the old `admin-smoke.php` is
  `admin-system-smoke.php`. Docs: one workbench manual (`docs/_admin.md`) with
  the references `docs/setup.md`, `docs/appearance.md` and `docs/templates.md`.

### Removed

- `_editor/`, `_install/`, `_design/` and `_templates/` with their entry
  points, routes, login pages and the `[admin-tools]` shortcode
  (`\Nino\Admin\ToolBridge`); `_nino/Nino/Panels/`; `_nino/Nino.admin.css`
  and `_nino/Nino.admin.js`.
- `editorPanels()`; the `/_editor/*` permissions; the shared `_admin`
  password and its login page; the `/nino/editor/*` keys.
- `Users::presets()` and `Users::apiSetPermissions()` - roles are data now;
  the `/_admin/users/role/editor` and `/developer` fills.
- `\Nino\Design\*` and `\Nino\Templates\*` - they are
  `\Nino\Modules\Design\*` and `\Nino\Modules\Templates\*` now, one class
  per file.
- `docs/_editor.md`, `docs/_install.md`, `docs/_design.md`,
  `docs/_templates.md` (and the German versions) - consolidated, see above.

### Fixed

- **The workbench login failed on CGI and FastCGI.** `\Nino\Http::request()`
  decodes the `Authorization: Basic` pair itself where the SAPI does not; the
  shipped `.htaccess` carries `CGIPassAuth On` (Apache 2.4.13 and newer), the
  deployment manuals name the nginx/PHP-FPM equivalent.
- **A password that is not plain ascii could never sign in**; the pair is sent
  utf-8 encoded now.
- **A users manager could give itself full access** through a role it wrote
  and an account it created. Granting is bounded by holding
  (`Roles::notHeld()`).
- **Activity-log lines were forgeable** through a line break in a posted
  value; control characters collapse to a space.
- **The activity log skipped most of the workbench**; each tab and panel now
  answers for its own writes.
- `.github/workflows/ci.yml` ran a deleted suite and never
  `tests/admin-system-smoke.php`; the js loop reported the last file's status
  alone.
- `\Nino\Auth::deleteUser()` ended the current session whoever was deleted.
- The Keys panel's rich-text fields called `Nino.editor.htmlEditor`, which no
  longer existed; the language switcher rendered a nameless option for a
  locale without a Localepicker name; a failed element list stayed hidden.
- A literal `[template]` token inside a text fill was executed rather than
  shown.
- Saving, creating or deleting an element type threw and left the Elements
  pane on stale data: `Nino.admin.elements.invalidate()` is back, with a
  request counter against responses still in flight.
- Stale references: the wizard's hint and a test pointed at `docs/_install.md`,
  `.gitignore` at `_admin/setup/`, a sitemap template at `/_install`, the demo
  catalogue at `/_design`; two English strings survived the i18n pass;
  `Backups\Admin::lastDate()` had no caller left.

## 0.12.0-beta — 2026-08-30

Appearance tooling, project layout, optional search and caching, and the
removal of every pre-1.0 compatibility path.

> Nino remains in beta. The optional Template Builder under `/_templates`
> is released as an alpha feature. Its interface, block library, and
> workflows may still change significantly.
>
> This release is not backward compatible with projects built on an earlier
> version. The frontend speaks one `nino-*` class namespace, the Design
> settings key is `/nino/design/settings`, the project tree lives under
> `private/`, and every legacy path and compatibility fallback has been
> removed. Read **Changed** before updating an existing project.

### Added

- **The generated design layer** (`/_design`). It owns the colour tokens every
  stylesheet reads (`--nino-alt`, `--nino-on-alt`, …) and solves them in OKLCH
  from a primary colour, an optional secondary and ten settings: Contrast,
  Saturation, Harmony, Temperature, Depth, Size, Width, Volume (Headings),
  Spacing and Shaping (Corners). Every background is published together with
  the text colour solved for it against the WCAG formula; nine surfaces publish
  ten values each. Written to `/assets/style.design.css`, spliced into the css
  bundle directly after `_nino/Nino.css`, rewritten in full on every save.
  Settings are steps 1 to 3; at step 2 throughout the stylesheet reproduces
  `Nino.css` exactly.
- **The size raster**: `--nino-text-1` to `-6`, `--nino-space-1` to `-6`,
  `--nino-radius-1` to `-3`, `--nino-radius-full` and `--nino-line-height`,
  which a theme assigns from instead of writing numbers.
- **Four brand roles and a fifth ground**: `--nino-brand`, `--nino-brand-safe`,
  `--nino-accent`, `--nino-accent-safe` and `--nino-tint`. `Nino.css` gains
  `.nino-section--tint`, `.nino-section--brand-alt`, `.nino-btn--brand-alt` and
  the `--color-section-tint-*`, `--color-accent-tint`, `--color-focus-tint` and
  `--color-brand-alt*` roles. `--nino-scrim`, the cover scrim solved per design
  (a theme may darken it, never lighten it below the floor); `.nino-cover--dim`
  and `.nino-img-background--dim` hand the dark ground's ink down. A global
  `:focus-visible` ring (`--color-focus-*` per ground), `--color-disabled`,
  `.nino-alert--warning` and `--radius-large`.
- **A catalogue of ten themes**: Basis, Bureau, Chronicle, Console, Gallery,
  Market, Midnight, Platform, Poster and Practice. Every position of every
  setting and every header and footer frame is used by at least one of them,
  and a test says so. Theme stylesheets are mapping layers - every colour role
  points at a `--nino-*` token - and every manifest declares the `design`
  block it was drawn with; the catalogue tests reject literal colour and size
  roles and resolve every pair in both modes.
- **Frames.** A site's `<header>` and `<footer>` are interchangeable units
  under the library's `header/<key>` and `footer/<key>` (a `template.tpl` and
  its `style.css`), included through `[template /templates/theme.header]`;
  picks persist at `/nino/install/header` and `/footer`.
- **The installer's appearance steps**: Themes (applying a theme installs the
  Header, Footer and Design its manifest declares), then Header, Footer and
  Design, each with a live preview - the frame steps render the real template,
  Design a whole example page inside the project's own frames with the demo
  images and the theme's fonts, scaled into the panel by
  `Nino.adminUi.scaleFrame()`, with a light/dark switch. Design has a dirty
  state, Revert and a leave guard; a half-typed colour is refused out loud.
  Step messages sit in the action bar.
- `/_design` after the installation, with four dialogs - Theme, Design, Header,
  Footer - sharing `/_admin`'s session and CSRF; the catalogue stays under the
  installer's library, of which only the theme `preview.svg` files are public.
- Admin UI components: `Nino.adminUi.buttonRow()`, `selectField()`,
  `switchField()` (`.nino-admin-switch`), `fieldChange()`
  (`.nino-admin-changed`), `.nino-admin-tabs`, and the shared data table
  `Nino.adminUi.table()` with search, type-aware sorting and paging
  (`tests/nino-ui-table-js-smoke.js`). `Nino.adminUi` moved out of the public
  `Nino.js` into `_nino/Nino.admin.js`.
- `Demo: Catalogue`, a page unit showing every section preset in every layout
  and every `nino-*` block `Nino.css` defines, counted by
  `tests/demo-catalogue-smoke.php`.
- **`Modules\Search`**, an optional weighted fuzzy index for Elements:
  `/nino/elements/index` assigns fields to priorities, `Search::getElements()`
  returns complete Elements in score order; rebuilt on element writes and by
  **Create searchindex** in Config; one non-atomic `/data/index-<type>.php` per
  type.
- **`Modules\Cache`**, an optional full-page cache for anonymous `GET`
  requests without query vars, off by default, configured in Config with a
  lifetime and a uri blacklist. `[csrf]` and `[jstext]` values are stamped per
  request; a successful write through a tool drops the cache; responses carry
  `X-Nino-Cache: hit|miss`. A `/nino/http/output` callback carries the finished
  response.
- Element types can number their own elements (**Element URIs**,
  `'autoincrement'` in the type file, `/gallery/00001`), allocated under the
  element lock and never reused.
- An `element` model field type: a reference to another type's entry by its
  full uri; a deleted target shows as *missing*.
- `Elements::queryElementValues()` and `[elementvalues]`, the distinct values
  of one field with their counts (`sort`, `includeEmpty`, `[[.value]]`,
  `[[.count]]`, `[[.id]]`); `nino-filter` in `Nino.ui.js`; the
  `filterable-grid` preset; the `[[section:collection:<areaKey>]]` compile
  token.
- Config is a typed form validated against `Config::FIELDS` and saved as one
  write; adding a language writes its `text/<locale>.php` skeleton, switched
  off; the language list reports what exists on disk.
- A **Navigations** area in `/_admin` (`/nino/html/navs`, dense priorities); a
  versioned **Translations** JSON export and import; inline `<code>` in the
  rich-text editor and its sanitizer; a pinned form toolbar; starter wording in
  the wizard's Webpages step; a page's Http-URI as the textfill
  `/webpage<uri>/uri` in `text/global.php`.
- **The Template Builder**, rebuilt section-first (Alpha): manifest-v3 presets
  with named Areas, Design and Data views, component bindings (generated,
  collection, existing textfill or a fixed value), Layouts, Area Styles,
  `data-*` attributes declared by the manifest, `[template]` canvas sections,
  page metadata (display name, VPA default), sandboxed real-markup previews
  and an explicit HTML+ escape hatch. Presets: Articles, Fullscreen image,
  flexible content, media split, reusable template, Process, Pricing (with
  four-column Layouts), Features, Partners, Call to action, Banner, the CTA
  button pair, and five static blocks (Table, List, FAQ, Newsletter form,
  Contact form). Every **Auto** option names the value it resolves to; preview
  cards share one viewport; a background image may be a fixed value.
- Type size is a modifier of the class it changes (`--quiet`, `--loud` on every
  typography class, plus the new section body-copy class) instead of the two
  font-size utilities, which are gone.

### Changed

- **Every legacy path and compatibility fallback is gone**, roughly 750 lines:
  the pre-v3 section composer, the `--nino-origin` and `--nino-vibrant` tokens,
  the named Design vocabulary (`contrast: 'high'`, `colors: 'clean'`, …), the
  kernel's second autoload root, and the `/nino/theme/design` key - it is
  `/nino/design/settings` now.
- **A checkout ships no project.** There is no `private/` in the repository;
  the wizard creates the directory, its deny rule and the first `config.php`.
  Framework defaults live in `\Nino\AppData::DEFAULTS`, so a first install
  writes four keys. `\Nino\init()` takes one flag; only the installer's entry
  point passes it. A unit's `files` are copied before its `templates`.
- **Project layout.** The private half - `config.php`, `templates/`, `text/`,
  `elements/`, `data/`, the asset sources, `.auth/`, `.logs/`, `.backups/` -
  lives under `private/`, denied by its own `.htaccess`, refused by
  `router.php`, movable with `NINO_CONTENT_DIR`. The public half - `images/`,
  `fonts/`, `favicon/`, the generated `.cache/` - lives under `public/`; urls
  gain a `/public` segment through `[[/nino/public]]` or
  `\Nino\Filesystem::url()`. `Filesystem::getPath()` is the code root,
  `getPublicPath()` the browser-facing one; everything in
  `Filesystem::PRIVATE_DIRS` resolves through `Filesystem::path()`. **An
  existing installation moves `public/assets` to `private/assets`.**
- The `/_admin` password hash, the login throttle, the activity log and the
  backup archives moved out of the tool folders into `private/.auth/`,
  `private/.logs/` and `private/.backups/`, so `_admin/` and `_editor/` hold no
  project state and an update may replace them wholesale. The `/_install` lock
  is `/nino/install/completed` beside the stored password; `/nino/backup/dir`
  and `/nino/logs/dir` are gone.
- Project classes resolve from `app/`, or `NINO_APP_DIR`; `Nino\` stays
  kernel-only.
- **Breaking: the frontend speaks one class namespace, `nino-*`.** `ui-*`,
  `js-*` and `sc-*` are gone; the state classes `.error`, `.success`,
  `.pending`, `.existing`, `.touch` and `.active` are `nino-is-*`;
  `.nino-icon.small` is `.nino-icon--small`. Hand-written markup and custom
  CSS need a manual rename; a compiled section re-emits the current vocabulary
  on save.
- The appearance tool moved from `/_theme` to `/_design` (`Nino\Design\Design`,
  `Nino\Design\Tokens`, `Appearance`). Harmony and Temperature became named
  choices and were renumbered - Temperature is Neutral/Cool/Brand/Warm, Harmony
  is Monochrome/Analogous/Triadic/Complementary - so a Design saved earlier in
  the beta should be reopened and checked. Volume, Shaping and Measure are
  labelled Headings, Corners and Width; the stored keys are unchanged.
- Header and Footer come before Design in the installer. Bundle order is the
  contract: `Nino.css`, the generated design values, the theme, the frames,
  the project's overrides.
- Management APIs expose one schema: Webpages posts `libraryKey` and `navs`,
  Template creation `filename`, navigations come only from `/nino/html/navs`,
  the theme only from `/nino/install/theme`. `/nino/install/webpages` is gone;
  both tools derive the page list from `/nino/http/routes` and
  `/webpage<uri>/*`. Menu membership is numbered densely. Config no longer
  edits routes, navs or asset bundles. The backup and log switches are
  `/nino/editor/backups` and `/nino/editor/logs`.
- `_nino/Nino.admin.css` is a class-only design system (`nino-admin-*` under a
  `.nino-admin` root, 1482 to 775 lines) with the cascade layers
  `nino.system, nino.tool, nino.local`; the locale switch and the select
  indicator are components.
- The Template Builder library is manifest-v3 only; Add Section and Edit
  Section are split by intent; the Data view puts one binding on one line; the
  Articles preset equalizes card titles and descriptions per section;
  `/_templates` is a reserved route.
- Wizard: "New Webpage" is "New Route", the step **Webpages** is **Routes**, a
  new route starts on **Blank**, which is copied per route
  (`templatePerRoute`), and `/contact` sits in the footer menu.
- `/_admin`: **Save and back** and **Save and new**, the element row label is
  the `title` field, an anchored save row, denser forms from 768px, grouped
  drill-down lists, **Pages** is **Routes**, Translations moved from
  `/_editor`.

### Fixed

- Leaving the Design pane and coming back threw away every unsaved setting.
- Saturation moved the brand and nothing else, and turning it up could come
  back less saturated; Contrast named three positions and produced two. All
  three scale now, and every position clears WCAG AA.
- Brand colour written as ink in a dozen `Nino.css` components failed the text
  target on any ground that is not near-white; `--color-accent` per surface is
  the missing role. Sections hand their ink roles down with their background;
  `.nino-article--alt` and the status alerts are surfaces with a solved ink;
  the footer, the locale picker, pagination and the form status message take
  the ink of the band they are in.
- Spacing, alignment, opacity and visibility utilities lost to every component
  written after them; they are the last chapter of the stylesheet now.
- Three target sizes and the footer frame `v7`'s legal link sat under WCAG 2.2
  SC 2.5.8's 24px.
- `.nino-cover-content` hung over its section's right edge; `nino-cover` next
  to a side rail overflowed horizontally; a viewport animation widened the
  page.
- `Design::normalize()` raised a `TypeError` on an array-shaped knob, a 500
  anybody could ask for through `design/save` and `design/apply`.
- The authenticated `/_design` page rendered no CSRF field; header previews
  corrupted UTF-8 and attribute delimiters; two js smoke suites crashed before
  their size assertions; two unclosed `<div>`s in the installer's Design step.
- The Template Builder ignored the background image slot a section chose; a
  `]` inside a fill cut a shortcode match short in the preview; composer
  dialogs rendered outside `#pd-app`; previews requested `/.cache/style.css`
  from servers answering with HTML; Area tabs clipped the editor body; gallery
  previews load project fonts; viewport-animation classes kept preview
  sections transparent; generated image markup pointed at `/uploads`.
- `/_admin`: the element type overview kept its counts from page load; the
  Element Types editor dropped **max. characters** and **unit/suffix** on
  save; the Elements module showed an outdated schema after a type was saved;
  a required image field made a type impossible to add to; the image preview
  pointed at `/uploads`; long options ran under the select chevron; the last
  form regressions after the shared-style refactor.
- `/_install`: "Back to list" in the Routes step discarded the open form; "New
  Route" carried a stray margin; the tool shells dropped their design-system
  classes on every panel switch; the `Demo: Sections` page pointed at moved
  photography and a module the library no longer ships.
- Backups omitted nested Element image paths. Failed or unserializable writes
  were visible through the in-request cache. Element type files acquired
  double-slash cache and lock aliases, type creation hid write failures, a
  reset-to-default left an old override, partial URI renames discarded
  omitted fields. Route, query and locale handling is hardened against
  malformed or array-shaped request values. The project root no longer has to
  stay writable during normal operation. A touch slider filling the viewport
  kept native scrolling; the rich-text field restored drag selection.

### Security

- Template Builder writes only validated `page-*.tpl` paths, keeps locked raw
  source byte-identical, validates both HTML and template sections, rejects
  duplicate section IDs, uses optimistic revisions for external-edit
  conflicts, validates fixed shell-slot topology, and replaces files
  atomically. Real-markup previews run without scripts in sandboxed frames
  whose CSP permits only project-origin styles/assets and generated data images.

## 0.11.0-beta.1 — 2026-08-09

Development tooling, installation workflow, content management, and documentation update.

> Nino remains in beta. The optional Template Builder under `/_templates`
> is released as an alpha feature. Its interface, block library, and
> workflows may still change significantly.

### Added

- `/_templates`, an optional graphical structure editor for `page-*.tpl` and
  `section-*.tpl` files: a manifest-driven block library of 76 definitions
  covering the Nino.css component catalogue, block selection, insertion,
  reordering, duplication, removal and responsive settings, and editing of
  CSS classes, attributes, boolean attributes (`attrtoggle`), tags and text.
  Templates keep their attribute order, whitespace, comments, indentation and
  entity spelling; opening and saving an unchanged template is a no-op.
- The **Themes** step in `/_install`: self-contained theme library units under
  `library/themes/` with manifest, stylesheet, preview, fonts and declared
  assets. Applying one replaces its entry in the css bundle without changing
  the cascade position; the selection persists under `/nino/install/theme`.
- Page management and the complete management of element types, elements,
  multilingual texts, image slots, routes, users, configuration and restoration
  in `/_admin`.
- English and German documentation for concepts, development, installation,
  administration, editorial content management, template editing and
  deployment, with screenshots and language links.

### Changed

- The former `/_dev` developer area is `/_admin`, the former `/_admin` content
  backend is `/_editor`; directories, routes, namespaces, sessions, JavaScript
  namespaces, CSS identifiers, configuration keys, documentation and tests
  follow.
- A fresh checkout requires `/_install` before the website can run; the wizard
  has seven steps (Environment, Setup, Themes, Webpages, Personal information,
  Admins, Finish) and creates `templates/`, `text/`, `elements/`, `images/` and
  `assets/` instead of the checkout shipping a configured project. It replaces
  the selected locales, modules, pages and theme while preserving manually
  defined routes; templates and text are added, never deleted.
- The shared webpage model of `/_install` and `/_admin` distinguishes the
  library key, the template file, the internal page URI, the public HTTP URI,
  the response status and the rendered route body; both tools share one
  persisted list, and the page order in `/_admin` determines the main
  navigation order.
- The Template Builder derives block identity from ordinary HTML tags and CSS
  classes and inserts through the same server-side parser as existing
  documents; responsive settings sit below their base setting; article
  modifiers are independent toggles.
- Documentation structure, metadata, navigation, terminology and maturity
  labels are standardized; `/_templates` is documented as Alpha.

### Fixed

- `/nino/install/webpages` was interpreted differently by `/_install` and
  `/_admin`; pages created during installation appeared as unknown templates;
  saving the shipped 404 page reset its status to `200`; saving localized pages
  lost their locale-dependent template expression; custom route bodies and
  manually added routes were overwritten or removed.
- Account creation and updates failed on a by-reference callback parameter.
- Theme stylesheets referenced a missing font directory; unused font faces
  were removed.
- Template Builder: edited classes changed order, inserted children were
  misindented, removed blocks left blank lines, generic definitions overrode
  specialized modal, toast and component blocks.

### Security

- The Template Builder writes only supported `page-*.tpl` and `section-*.tpl`
  files, keeps header, footer, email and other technical templates read-only,
  rejects unsupported tags and `on*` event-handler attributes, never writes an
  empty tree, writes atomically, reuses the protected `/_admin` session and
  stores no sidecar files or builder attributes in project templates. Existing
  templates are accepted only when a byte-exact round trip is guaranteed.

### Tests

- `tests/templates-smoke.php` and the dependency-free
  `tests/templates-js-smoke.js`; tests for the block catalogue, manifest
  parsing, byte-exact round trips, structural actions, responsive settings,
  `attrtoggle`, block matching specificity and theme units; expanded
  installation and administration smoke tests.

## 0.10.0-beta

Security and reliability hardening pass, plus a module-system refactor.

### Security

- Made `\Nino\Csrf` protection permanently active.
- Limited `\Nino\Modules\Csrf` to rendering the `[csrf]` shortcode.
- Accepted CSRF tokens from request headers and JSON bodies.
- Changed CSRF method matching to use exact HTTP methods.
- Added throttled logins per IP address.
- Prevented user enumeration during login.
- Replaced client-IP session binding with session tokens.
- Removed password hashes from PHP session data.
- Added strict session cookie settings and token lifetimes.
- Fixed parallel logins invalidating each other.
- Fixed `X-Frame-Options`.
- Completed the Content-Security-Policy configuration.
- Fixed mail header injection and sender headers.
- Prevented credentials from leaking through the error handler.

### Changed

- Moved modules to `_nino/Nino/Modules/<Name>/<Name>.php`.
- Added module loading through `spl_autoload_register()`.
- Renamed `\Nino\Shortcodes` to `\Nino\Modules`.
- Added `Http::fail()` and `Http::ok()` response helpers.
- Moved `\Nino\Text` into the kernel.
- Reworked the rotating log.
- Moved `backupManifest()` to `\Nino\Backup`.
- Reduced comment density across the codebase.

### Fixed

- Changed element mutations to use `Filesystem::mutate()`.
- Fixed early-return lock leaks in element operations.
- Added atomic file writes with correct locking.
- Changed `Filesystem` reads to use the locking primitive consistently.
- Fixed configuration path and rotating-log edge cases.
- Added a shortcode recursion guard.
- Fixed element query keys being combined incorrectly.
- Fixed URI renaming.
- Fixed route locale handling.
- Prevented asset bundles from being rebuilt when unchanged.
- Replaced recursive array merging with scalar-overwrite behavior.
- Fixed a dashboard permission leak.
- Fixed response header filtering.
- Fixed `Callbacks::doCallbacks()`.
- Changed `writeContentData()` to check its write result.
- Prevented `Text::saveBatch()` from rebuilding entries for every call.

### Tests

- Added `tests/concurrency-smoke.php`.
- Added the developer-area smoke suite.
- Expanded `tests/kernel-smoke.php`.
- Expanded the administration smoke suite.

## 0.9.0-beta

First tagged release.

### Added

- Added newsletter double opt-in with confirmation by email.
- Added self-service newsletter unsubscribe links.
- Added automatic newsletter routes under `/.newsletter`.
- Added local development support for bundled demo images through
  `router.php`.

### Changed

- Stopped tracking `/data/` and uploaded images in Git.
- Simplified `docs/*.md` to reference documentation.
- Removed `docs/security.md`.

### Fixed

- Fixed the Jstext module clearing the default Content-Security-Policy.
- Fixed production error display and logging defaults.
- Fixed locale-switch redirects.
- Added `session_regenerate_id()` after login to prevent session fixation.
