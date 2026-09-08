# Nino repository guide for AI coding agents

This file is the operational specification for AI agents that modify Nino. It
is intentionally explicit and repetitive enough for small models. Follow it
before copying patterns from memory or from another framework. The seven
extension recipes under `docs/recipes/` are part of it - section 7 lists them.

Nino is a filesystem-based PHP website framework. It has no database, Composer
dependency, JavaScript build step, component framework, or plugin manager.
Prefer readable project files and the public Nino APIs over new abstractions.

## 1. Rule strength and source of truth

The words **MUST**, **MUST NOT**, **SHOULD**, and **MAY** are normative.

1. The current user request and repository instructions have priority.
2. This file defines repository-wide defaults.
3. Existing source code and tests define the current runtime contract.
4. Human manuals in `docs/` explain intent and operation.
5. If documentation and source disagree, inspect the tests and implementation,
   follow the current behavior, and update stale documentation in the same
   change when that is within scope.

Do not invent an API because its name seems plausible. Search for the actual
method, its signature, and at least one current call site.

## 2. Required workflow for every change

Before editing:

1. Read the complete user request and list its observable requirements.
2. Run `git status --short --branch`. Preserve all unrelated user changes.
3. Find the nearest implementation, test, and documentation with `rg`.
4. Read complete functions or classes around every line that will change.
5. Decide which extension type in the next section actually applies.
6. Identify authentication, CSRF, validation, persistence, escaping,
   concurrency, locale, and backward-compatibility consequences.
7. Define a test that fails for the old behavior and passes for the new one.

While editing:

- Make the smallest coherent change.
- Follow local formatting and naming. Do not reformat unrelated code.
- Use public APIs; methods beginning with `_` are internal unless the class
  itself is being maintained.
- Keep PHP, JavaScript, CSS, templates, tests, and documentation synchronized.
- Do not add a runtime dependency or build step without explicit approval.
- Do not silently delete, rename, or migrate existing project data.
- Never trust request, file, template, element, or external-service data.

After editing:

1. Review `git diff --check` and the complete diff.
2. Run syntax checks for every changed PHP and JavaScript file, then the
   static analysis: `phpstan analyse` and `npx eslint .`.
3. Run the targeted smoke test and all tests named in the relevant recipe.
4. Run the complete suite for shared kernel, security, filesystem, installer,
   or cross-interface changes.
5. Report changed files, behavior, tests, and any remaining limitation.

If the user requests a patch-only handoff, do not commit. Generate a plain
`git diff --binary` patch against the requested base and verify it with
`git apply --check` in a clean checkout of that exact base.

Repository-owner delivery policy: do not create a commit and do not push.
Deliver the changes as a named `.patch` file unless the user explicitly asks
only for an inline answer. A request to “implement”, “finish”, or “apply” is not
permission to commit.

## 3. Choose the correct extension type

Do not use “page”, “template”, “module”, and “admin module” interchangeably.

| Goal | Correct extension |
| --- | --- |
| Add a screen to the workbench `/_admin` | Panel - answered by a runtime module's `adminPanels()`, or a module directory under `_admin/Nino/Modules/` |
| Add behavior to public requests or a new shortcode | Runtime module |
| Make a kernel or project module selectable and copyable during the setup wizard | Installer module package |
| Package an installable feature - a module with a manifest, settings and a version, switched on in the workbench after setup | Feature - `features/<Name>/` with `feature.php`, see the [feature recipe](docs/recipes/feature.md) |
| Add an insertable visual building block to the Templates panel | Section preset |
| Add a complete starting page to the installation library | Page library unit |
| Add reusable HTML+ included by other templates | Reusable `.tpl` template |
| Map a public HTTP URL to output | Route |
| Store repeated structured content | Element type and Elements |
| Store a short editable value | Textfill |

These pieces can cooperate but remain separate:

- A **route** maps an HTTP method and public URI to a response.
- A **page template** is a `templates/page-*.tpl` file rendered by a route.
- A **section preset** is source copied into a page template by the Template
  Builder. It has no runtime identity after insertion.
- A **runtime module** registers PHP behavior at boot.
- An **installer module package** selects, copies, and configures a runtime
  module in the setup wizard. It is not the runtime module itself.
- A **feature** is one directory below `features/` with a `feature.php`
  manifest: a runtime module, its panel and its install unit, versioned and
  switched on in the workbench's Features panel - never a wizard unit.
  `\Nino\Features` reads the manifest and applies the unit add-only.
- A **panel** is one screen of the workbench `/_admin`. It is not an entry in
  `/nino/modules`; a runtime module answers `adminPanels()` with it, and an
  account sees it only with the permission the panel declares.

When a feature spans types, implement each layer explicitly. For example, a
catalog can need a runtime module, an installer package, an element type, a
reusable template, and an `/_admin` panel. One of those does not automatically
create the others - though a module's directory is where all of its own live.

## 4. Repository map and ownership

Important source directories:

| Path | Ownership |
| --- | --- |
| `_nino/Nino.php` | Boot: `\Nino\init()`, `request()`, `output()` and the autoloader. `Nino\*` resolves below `_nino/` alone, every other namespace below `app/` (or `NINO_APP_DIR`) alone; `Nino\Modules\*` is a merged view over four roots in this order: `_nino/`, `_admin/`, `features/` (or `NINO_FEATURES_DIR`, where the `Nino/Modules/` prefix is the directory itself), `app/` |
| `_nino/Nino/<Class>/<Class>.php` | The kernel classes and public core APIs: AppData, Auth, Callbacks, Catalogue, Csrf, Features, Fetch, Filesystem, Backup, RotatingLog, Elements, Html, Http, Images, Locales, Text, Mail, Modules, Runtime |
| `_nino/Nino/Catalogue/Catalogue.php`, `_nino/Nino/Fetch/Fetch.php` | The feature catalogue: `Fetch` is the kernel's one http client (https only, no redirects, a byte cap, stubbed in tests through `./nino/fetch/stub`); `Catalogue` fetches `catalogue.json` and its detached ECDSA signature, verifies it against `PUBLIC_KEY` or `/nino/catalogue/key`, parses format 1, answers `offers()` per key, and `install()`s an archive: re-fetched catalogue, sha256 and size, staging below `data/.features/`, every entry validated, manifest matched, directory replaced. Nothing is fetched unless the Features panel asks. Contract test `tests/catalogue-smoke.php`, no network |
| `_nino/Nino/Features/Features.php` | The feature contract: discovery below `features/`, manifest validation, version constraints, settings, `activate()`, `deactivate()`, and `applyUnit()` - the unit application the wizard shares (overwrite on there, add-only for a feature). Contract test `tests/features-smoke.php` against `tests/fixtures/features/` |
| `_nino/Nino/Modules/<Name>/<Name>.php` | Kernel runtime modules: the always-on ones every project needs (Assets, Cache, Csrf, Elements, Images, Jstext, Template) and the optional ones a project switches on or off in `/nino/modules` (`Form`, `Navigation`, `Localepicker`, `Design`, `Templates`). Replaced wholesale with `_nino/` |
| `_nino/Nino/Modules/<Name>/Admin/Admin.php`, `assets/`, `text/`, `templates/`, `install/` | A kernel module's own workbench panel class with its scripts, stylesheets, fills and (for a template panel) its markup, and its installer unit - everything the module brings, in one directory |
| `features/<Name>/` | An installed feature: `feature.php` (the manifest - key, name, version, the `nino` constraint, `requires`, `settings`, `data`), `<Name>.php` (the class `\Nino\Modules\<Name>`, derived from the directory), `Admin/Admin.php` (its panel), `install/` (the unit `\Nino\Features::activate()` applies add-only), `text/`, `assets/`, `tests/<key>-smoke.php`. A checkout ships none: the published ones - `Newsletter`, `Search` - come from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) and are copied in. Denied by `features/.htaccess` and `router.php`; relocated by `NINO_FEATURES_DIR` |
| `app/<Namespace>/<Class>/<Class>.php` | Project-owned PHP classes and runtime modules; defaults to this root unless `NINO_APP_DIR` is defined before loading the kernel |
| `_nino/Nino.js` | Shared browser helpers, workbench and public site alike - so it is in the public script bundle, and anything only one audience needs belongs beside it rather than in it |
| `_admin/Admin.php` | The workbench: `\Nino\Admin\Admin` (shell, routes, bundles, fills, dispatch), `\Nino\Admin\Panels` (the panel registry: reads a panel class, orders the panels, renders navigation and panes) and `\Nino\Admin\Recovery` (the recovery secret) |
| `_admin/Nino/Modules/<Name>/` | The workbench's own screens, one module each, in the same shape and the same namespace `_nino/Nino/Modules` and `features/<Name>/` use: `Admin/Admin.php` is the panel, `<Tab>/<Tab>.php` a tab of it, `assets/` its scripts and stylesheet, `text/` its words. `Admin::modules()` reads the directory - there is no list to keep. Take the directory away and `/_admin` is a login and an empty rail, which is the point: `_admin` is the base, the modules fill it |
| `_admin/assets/style.css` | The workbench's one stylesheet: the design system in the `nino.system` layer (classes only, `nino-admin-*`), the workbench's own rules in `nino.tool` below it |
| `_admin/assets/Nino.admin.js` | The behaviour half of the design system: `Nino.adminUi`'s DOM primitives and the pure table model. Loaded after `Nino.js`, by the workbench only |
| `_admin/assets/script.js`, `login.js`, `html-editor.js` | The shell script (router, theme, rail fold, panel switching), the login screen's, and the rich-text primitive a panel names in its own `assets()`. No panel script lives here; no bundler - `Admin::init()` builds `/_admin/.cache/` from the registry |
| `_admin/templates/page-index.tpl` | The shell; navigation, panes and panel assets are rendered into it from the registry |
| `_admin/recovery.php`, `templates/page-recovery.tpl`, `assets/recovery.js` | The recovery page: restore a backup, reset a password, with the recovery secret |
| `_admin/install/Install.php` | The setup wizard - the workbench's first-run mode, served by the same route while `Admin::isInstalled()` says no; deletable after setup |
| `_admin/install/library/base/`, `modules/`, `pages/<slug>/` | The wizard's library: always-applied base, units without a runtime class, installable page units |
| `_admin/install/library/themes/<slug>/` | Appearance themes: manifest, preview, and installable assets. Setup material read by the wizard and, while the directory is deployed, by the Design panel; applying one copies it into the project |
| `_admin/install/library/header/<slug>/`, `footer/<slug>/` | Interchangeable page frames: a `template.tpl` plus an optional `style.css`, no manifest. Installed as `templates/theme.header.tpl` / `theme.footer.tpl`, which the base html templates include |
| `_nino/Nino/Modules/Design/Design.php`, `Admin/`, `Appearance/`, `Tokens/`, `Preview/` | The Design module: the settings and the generated stylesheet's place in the bundle (module class), the panel, the catalogue units, the token palette solver, the live preview the wizard borrows |
| `_nino/Nino/Modules/Design/templates/preview-example.tpl` | The page both pickers preview against. Framework classes only - `Design\Preview` wraps it in a document with the generated tokens and serves it to a sandboxed iframe |
| `_nino/Nino/Modules/Templates/Templates.php`, `Admin/`, `Documents/`, `Library/`, `Content/`, `Composer/`, `SectionDocument/`, `AreaComposer/` | The Template Builder module: the CSP hook and the panel (a workspace), the page files, the presets, the native content, the section compiler, the document parser and the named-area manifest-v3 normalizer |
| `_nino/Nino/Modules/Templates/assets/area-composer.js` | Named-area Design/Data editor layered on the composer dialog |
| `_nino/Nino/Modules/Templates/library/<slug>/` | Section preset library |
| `public/` | The project's public half — everything a browser loads directly: `images/`, `favicon/`, `fonts/`, and the generated `.cache/` bundles. Reached through `Filesystem::path()` on disk and `Filesystem::url()` (or the `[[/nino/public]]` fill) for urls. Never build a public url by hand from `[[/nino/dir]]`. `assets/` is *not* here — the bundle sources are private, see below |
| project root | `index.php`, `router.php`, `_nino/`, `_admin/`, `app/` and `features/`. `Filesystem::getPath()`. The workbench serves its own js/css from here, so a tool file's url uses the plain project dir, not the public prefix |
| `private/` | The project's private half — never served, only read by PHP: `config.php` (the accounts among it), `templates/`, `text/`, `elements/`, `data/`, `assets/` (the stylesheet and script sources `Modules\Assets` concatenates into `public/.cache/` — nothing ever requests one directly), plus `.auth/` (the recovery secret, the login throttle, the backup key), `.logs/` and `.backups/`. Reached through `Filesystem::path()` (which resolves `PRIVATE_DIRS` against it) or the virtual `Filesystem::CONTENT_DIR` prefix (`/private`); moved by `NINO_CONTENT_DIR`. Never write project state into a tool folder or into `config.php` — the first breaks updates, the second is rolled back by a Restore. **A checkout ships none of this**: the wizard creates the directory, brings its own deny rule (`_admin/install/library/base/private/.htaccess`) and writes the first `config.php` at the end of Setup. Framework defaults live in `\Nino\AppData::DEFAULTS` and sit *under* that file, so config.php holds only what this project decided |
| `tests/*-smoke.php` | Standalone PHP contract tests |
| `tests/harness.php` | The shared bootstrap of a smoke test: `check()`, `ninoSandbox()`, `ninoSandboxDir()`, `ninoWarnings()`, `ninoDone()`. A feature's own test under `features/<Name>/tests/` loads it from the checkout three levels up, or from `NINO_ROOT` |
| `tests/fixtures/features/` | The fixture features the contract test runs against; `Sample/` exercises every settings type, an install unit, a panel and `upgrade()` |
| `tests/*-js-smoke.js` | Standalone Node/browser-logic tests |
| `phpstan.neon`, `phpstan-baseline.neon`, `eslint.config.mjs`, `.editorconfig` | Static analysis and editor defaults. The baseline lists the findings that were open when the check arrived: remove an entry when its finding is fixed, never add one to silence a new finding |
| `docs/` | Human manuals in English and German |
| `docs/recipes/` | The seven extension recipes of this guide, English only |

Project content and generated destinations:

| Path | Data |
| --- | --- |
| `config.php` | Stable application configuration and routes |
| `templates/*.tpl` | Runtime HTML+ templates |
| `text/global.php` | Locale-independent textfills |
| `text/<locale>.php` | Translated textfills |
| `text/blacklist.php` | Technical fills hidden from normal editing |
| `elements/<type>.php` | Element schema plus shared and localized records |
| `images/` | Project and uploaded images |
| `data/` | Runtime records, locks, logs, caches; normally not versioned |
| `data/index-<type>.php` | Derived locale-grouped Elements search index; recreated from the type file, never source content |

A fresh checkout may not yet contain all generated destination directories.
Do not “fix” their absence by adding empty placeholders. The setup wizard creates and
populates them.

Library files are sources, not live project files. Editing
`_admin/install/library/pages/home/templates/page-home.tpl` changes future
installations; it does not update an already generated
`templates/page-home.tpl`. Modify the correct ownership layer.

## 5. Core runtime model that every extension must preserve

Every request follows:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

- `$appData` is application state, configuration, callbacks, caches, and
  runtime state.
- `$request` is one normalized request and its response.
- Both are passed by reference.
- Keys under `/...` are stable configuration/data space.
- Keys under `./...` are request-lifecycle-only state.
- A `/...` value is not persistent merely because it is in `$appData`. A
  writer must explicitly save it.

### Response rules

Runtime handlers MUST NOT call `echo`, `header()`, `http_response_code()`, or
`exit`. They MUST modify `$request['/nino/http/response']` or use:

```php
\Nino\Http::ok( $request, [ 'items' => $items ] );
\Nino\Http::fail( $request, 400, 'invalid request' );
```

Do not expose stack traces, local paths, tokens, email-existence checks, or raw
exception messages in a public response.

### Route rules

Routes live under `/nino/http/routes` and are keyed
`METHOD://public-uri`:

```php
'GET://services' => [
	'uri'  => '/services',
	'body' => '[template /templates/page-services]',
],
```

The key is the public request; `uri` is the stable internal response identity.
Route-specific callbacks use the internal identity:

```text
/nino/http/response/GET://services
```

Technical endpoints owned by a module MAY be registered in its `init()`.
Normal visitor pages SHOULD be persisted as page routes instead.

CSRF protection is active by default. A state-changing browser route MUST keep
it active and its form MUST render `[csrf]`. Use `'csrf' => false` only when a
documented alternative verifier, such as a cryptographic webhook signature, is
implemented and tested.

### Persistence and concurrency rules

Never implement a read-modify-write cycle as separate read and write calls.
Two requests can read the same state and one will overwrite the other. Use:

```php
$written = \Nino\Filesystem::mutate(
	$appData,
	'/data/catalog.php',
	function( mixed $state, array &$appData ) use ( $entry ): array {
		$rows = is_array( $state ) ? $state : [];
		$rows[] = $entry;
		return $rows;
	},
	[]
);
```

Returning `null` from the callback aborts the mutation and releases its lock.
Check the boolean result where failure affects the response.

To persist selected top-level configuration keys already changed in
`$appData`, use:

```php
\Nino\AppData::writeContentData( $appData, [
	'/project/catalog',
	'/nino/http/routes',
] );
```

Do not serialize all of `$appData`. It contains runtime values and may overwrite
concurrent changes.

### Rendering and escaping rules

HTML+ is processed as textfills, then shortcodes, then final render callbacks.

- Absolute textfill: `[[/page-home/main-hero/title]]`
- Reusable template: `[template /templates/html-header]`
- Image slot: `[image /page-home/main-hero/image alt=""]`
- Element-local field: `[[title]]` only inside `[element]` or `[elements]`
- CSRF field: `[csrf]`

Escape at the output context:

- HTML text/attribute: `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE,
  'UTF-8')`.
- Rich element text: declare `'html' => true` in its model and rely on
  `\Nino\Html::sanitizeHtml()` when accepting it.
- Browser DOM: insert untrusted text with `textContent`, not `innerHTML`.
- URL/path/class/ID: validate against a strict allowlist before escaping.
- JSON: return arrays through Nino; do not concatenate JSON by hand.

Shortcode output is rendered again. If untrusted content can contain `[`, it can
otherwise become a new fill or shortcode on the next rendering pass. Escape or
neutralize it for the intended context.

### Callback rules

Callbacks receive `$appData` and the argument by reference. A non-`null` return
value replaces the argument for following callbacks. Keep callback names and
argument shapes backward compatible. Use a project namespace for project
events; `/nino/*` is reserved for Nino.

## 6. Code and interface conventions

### PHP

- PHP is 8.4+ and new files MUST contain `declare(strict_types=1);`.
- Keep the namespace and one public class per autoloaded module file.
- Match nearby tabs, spaces, braces, docblocks, and explicit types.
- Validate array shape before reading nested user data.
- Catch failures only when the caller can recover safely; do not hide bugs.
- Do not call internal `_method()` APIs across class boundaries.

### JavaScript

- There is no module bundler or transpiler.
- Admin files use an IIFE and attach behavior below `window.Nino`.
- Use `const`/`let`, explicit event listeners, and existing HTTP helpers.
- Do not add inline handlers or depend on globally leaked DOM IDs.
- Keep pure model functions separately testable where practical.
- Use `textContent` for all server- or user-derived strings.

### CSS and management UI

See "Designing an admin frontend" below before writing any markup or CSS for
the workbench - the shell, a panel, the setup wizard or the recovery page.

- Maintain keyboard focus, labels, error text, small viewports, coarse pointers,
  and `prefers-reduced-motion`.
- Do not encode meaning only by color or hover.

## 6a. Designing an admin frontend

The `nino.system` half of `_admin/assets/style.css` is the design system for
the workbench and every panel in it, the setup wizard and the recovery page
included. It is not a stylesheet one screen happens to share - it defines the
vocabulary, and a panel's own stylesheet - `assets/admin.css` beside its
class, whether the module is the workbench's own or a project's - supplies only
what is genuinely local to it. The `nino.tool` half of the same file is the
shell's own: the rail, the panel switching, the login screen.
`_admin/assets/Nino.admin.js` is the same idea for behaviour: a component that assembles
markup (`Nino.adminUi.switchField()`, `.table()`, `.selectField()`) lives there,
never as a per-tool copy. It extends the namespace `Nino.js` creates, so it loads
after it - and it is deliberately *not* in the public script bundle, which is why
it is a file of its own rather than more of `Nino.js`.

**A panel ships its own stylesheet and nothing else.** A panel's `assets()`
join the workbench's bundle after the shell's own files, and a module panel's
template is a fragment that links nothing. The old tools used to load each
other's complete stylesheets for a handful of classes; copy the class you need
into the panel that needs it, or promote it to the design system - never
restate the shell. Every rule of a panel stylesheet is loaded on every screen
of the workbench, so scope every one of them to the panel's own root
(`#pd-app .pd-canvas`, `#theme-page-wrap .theme-tile`) - a bare `body {}` or
`code {}` in a panel file lands on the whole workbench.

### The five rules

1. **The shared file contains no id selectors.** An id names one element in one
   tool. A tool's own stylesheet may use ids freely - that is where one-off
   layout belongs.
2. **Shared classes are namespaced `nino-admin-*`.** If a class does not carry
   that prefix it belongs to one tool, and the shared file must not style it.
3. **`.nino-admin` is the scope root**, on the outermost element of any admin
   surface. Add `.nino-admin-shell` for a full-height application surface,
   `.nino-admin-shell--rail` if it is the usual rail+pane layout, or
   `.nino-admin-auth` for a centered login card.
4. **A class name describes a role, never a place.** `.nino-admin-btn-primary`
   is "the primary action", not "the button under a list". A class carrying the
   margins of wherever it first appeared breaks the moment it is reused - which
   is exactly how the wizard's "New Route" button once inherited a stray
   `margin-top` from another screen's list class.
5. **Cascade layers decide who wins before specificity.** Every stylesheet of
   the workbench opens with `@layer nino.system, nino.tool, nino.local;`. The
   design system lives in `nino.system`; normal screen and component rules
   live in the later `nino.tool` layer, so they can refine the low-specificity
   `:where(.nino-admin)` baseline without selector escalation. Reserve
   `nino.local` for a deliberate final override, not every normal component
   difference.

### When adding a screen

Reach for an existing class first; only invent one when no role fits.

| Need | Class |
|---|---|
| Surface root | `.nino-admin` + `.nino-admin-shell` (+ `--rail`) |
| Sidebar, its brand block, its nav | `.nino-admin-rail`, `.nino-admin-rail-head`, `.nino-admin-nav` |
| Scrolling content column | `.nino-admin-pane` |
| Sticky top row (back link, locale switch) | `.nino-admin-contextbar` via `Nino.adminUi.contextBar()` |
| Back link inside that row | `.nino-admin-back-link` |
| Fixed bottom actions | `.nino-admin-actionbar` via `Nino.adminUi.actionBar()` |
| Fixed actions above a list | `.nino-admin-list-actions` via `Nino.adminUi.listActions()` |
| Status text in a bottom bar | `.nino-admin-actionbar-status` |
| Primary / destructive button | `.nino-admin-btn-primary`, `.nino-admin-btn-danger` |
| Raised panel | `.nino-admin-card` (a `fieldset` is one already) |
| Clickable drill-down list | `.nino-admin-list` (+ `.nino-admin-list-copy` per row) |
| Many rows of the same record, with search/sort/paging | `Nino.adminUi.table()` — see below |
| List of rows that are read, not opened | `.nino-admin-list-dense` |
| List whose rows are `button`s | `.nino-admin-list-buttons` |
| Dashboard tiles | `.nino-admin-tiles`, `.nino-admin-tile` |
| Share-of-total bar under a label | `.nino-admin-meter-row` + `-label`/`-count`, `.nino-admin-meter-track` + `-fill` |
| Labelled form field | `.nino-admin-field` (`.nino-admin-field-wide` opts out of the two-column desktop grid) |
| Container whose fields share that grid | `.nino-admin-fieldgrid` |
| Run of checkbox rows | `.nino-admin-checklist` |
| Rich-text editor mount | `.nino-admin-richtext` |
| Explanatory text / screen intro | `.nino-admin-hint`, `.nino-admin-hint-lead` |
| Nothing here yet (a list without entries, a scan that found nothing) | `.nino-admin-empty` via `Nino.adminUi.emptyState()` |
| Error text | `.nino-admin-error` |

**Every word is a fill.** A panel script never renders a literal English
sentence: the workbench's strings are `_admin/text/<locale>.php`, a module's are
the `text()` directory beside its panel, and `[jstext]` hands all of them to
`Nino.content.getText()`. A placeholder is `%s`, `%d` or `%n`, filled with
`.replace()`. A label the backend sends - a schema's `label` and `hint`, a
dashboard tile's or a permission's label - may be a fill key or literal text;
`Nino.adminUi.text()` resolves it either way - which is also how a value that
is both shown and compared stays translatable: send a slug and name it on the
client (see the Template Builder's `includeKind()` and `categoryLabel()`), never
compare against a word a locale file can change.
A workspace panel's markup (`templates/panel.tpl`) is the other place a
sentence hides, and reads ordinary `[[fills]]`. A fill's *value* is substituted
into the page before the shortcode pass runs, and `[jstext]` ships the same
stored value, so a value must never contain a live shortcode token: escape the
bracket (`&#91;template]`) in a value that is only ever markup, and put a `%s`
in one a script renders, filling it from the script's own source - a `.js` file
is a static asset and is never rendered.
`tests/admin-lists-js-smoke.js` fails on a literal sentence in a panel script
or in that markup, and `tests/admin-system-smoke.php` twice over: statically on
a key a panel's scripts or markup ask for that either language lacks, and end to
end - it renders the whole workbench in both interface languages and fails on any
`[[fill]]` that came out the other side unresolved, which is the only version a
key its regexes do not recognise cannot slip past. A schema two surfaces share - the Design
knobs, which the setup wizard renders as well and which reaches no fills -
keeps its English as the fallback, and the panel looks for a fill of its own
first (`design.js`'s `_knobText()`). The recovery page and the setup wizard are English by design, as
is the section library's own preset content (a manifest's `name`, `description`,
`category` and area labels) - the panel renders all of it through
`Nino.adminUi.text()`, so a manifest may use fill keys instead.

### The shared data table

`Nino.adminUi.table()` renders a sortable, searchable, paged table of records.
Use it where the values themselves are what the screen is for; the grouped list
stays right for a handful of rows you only drill into.

It owns **no strings**. Everything that can be a number or a glyph is one - the
pager arrows, the row range, a boolean cell - so a caller supplies only
`labels: { search, empty, noMatch }`. That is deliberate: the type editor labels
a field by its raw model key, the element form translates through
`Nino.content`, and a component that hardcoded one word would be half-translated
in the other.

Pass the **whole set** and let it page. An element type is one file read whole
on every request (`\Nino\Elements::queryElements`), so asking for one page costs
exactly what asking for everything costs, and searching locally is instant
instead of a round trip per keystroke. 1000 rows of eight columns is 14 KB
gzipped. Roughly 1000 elements per type is also where the storage model itself
stops being the right tool — past that, use SQLite or MySQL.

A column may carry `render(value, row)` returning an Element, for a cell that
holds a link or a per-row action; sorting and searching still use the plain
value, so behaviour never depends on how a cell is drawn.

The pure half (`Nino.adminUi.tableModel`) is filtering, sorting, paging and cell
formatting as plain functions — extend and test there, not in the renderer.
`tests/nino-ui-table-js-smoke.js` covers it.

### Two traps

- **Never assign `className` on an element that carries a design-system class.**
  The shells carry `.nino-admin` plus their `show-<panel>` state, so switching
  panels with `el.className = 'show-x'` silently deletes the entire shared
  layout. Use `Nino.adminUi.setStateClass()`, or `classList` for anything else.
- **A panel stylesheet must scope every `nino-admin-*` rule to its own root**
  (`#pd-app .nino-admin-btn-primary`, not `.nino-admin-btn-primary`) - see the
  bundling note above.
- **Restating a shared component in a panel is not "refining" it.** A panel copy
  sits in the later `nino.tool` layer, so it wins over the design system whole
  and the two drift apart silently. The old editor carried a second copy of every
  token, button, input, field, tile, card and rich-text rule this way. Delete
  the copy; if the shared version is genuinely wrong for every screen, fix it in
  the design-system half of `style.css`.

### Templates

- `.tpl` files contain HTML+, never PHP.
- Use valid semantic HTML and accessible names.
- Use real `button` controls for actions and real `a` elements for navigation.
- Every form control needs a label; every content image needs suitable alt text.
- Do not add inline event handlers.
- Do not assume JavaScript is available for essential content.

## 7. Recipes

The seven extension recipes live under `docs/recipes/`. They are part of this
specification: every rule above applies inside them, and a change to a
contract they describe updates the recipe in the same commit. Section 3
decides which one applies.

| Recipe | Use it for |
| --- | --- |
| [Add a panel to the workbench](docs/recipes/admin-panel.md) | a new screen in `/_admin`, as a module panel or a workbench panel |
| [Add a runtime module](docs/recipes/runtime-module.md) | PHP behaviour at boot, a shortcode, a state-changing endpoint, module configuration |
| [Add an installer module package](docs/recipes/installer-package.md) | a module the setup wizard can select, copy and configure |
| [Add a Section Library preset](docs/recipes/section-preset.md) | an insertable building block for the Templates panel |
| [Write templates and installable page units](docs/recipes/templates-and-pages.md) | page and reusable `.tpl` templates, header/footer slots, installable page units |
| [Define Element types for repeated content](docs/recipes/element-types.md) | repeated structured content: model fields, uris, rendering, search |
| [Package a feature](docs/recipes/feature.md) | a module delivered as one directory under `features/`, with a manifest, settings, a version and its own test, switched on in the Features panel |

## 8. Security review required for every extension

Security is not a separate cleanup pass. Apply the following questions while
designing the behavior and assert important boundaries in tests.

### 8.1 Trust boundaries

Treat all of these as untrusted:

- `$_GET`, `$_POST`, headers, cookies, request body, uploaded bytes, and IP
  forwarding headers;
- Admin and Editor payloads, even after login;
- values loaded from editable `config.php`, text, Elements, and templates;
- filenames and IDs supplied by a browser;
- imported translation JSON;
- external API, webhook, feed, and mail data;
- and HTML returned from a project callback.

Authentication proves who sent data. It does not make the data structurally
valid or safe for HTML, a path, a header, or a class name.

### 8.2 Authentication and authorization

- Every panel API method calls `Admin::guardPerm()` with the panel's own
  permission; `Admin::guard()` alone is for the one screen every account may
  use.
- Runtime management endpoints check a specific
  `Auth::checkPermission()` value.
- Read and write permissions are distinct when their risk differs.
- Do not accept a username in the request and check that user's permission as
  authorization for the current caller.
- Do not expose workbench actions through a new public route.
- Session fixation protection and cookie policy remain in the kernel; do not
  implement parallel ad-hoc auth cookies.

### 8.3 CSRF

- Keep core CSRF active on state changes.
- Render `[csrf]` in browser forms.
- Respect `./nino/csrf/blocked` before runtime write behavior.
- Admin guards also respect an already failed response.
- Do not exempt JSON requests merely because their content type is JSON.
- A signed webhook exemption validates raw bytes, timestamp/replay window,
  algorithm, and secret using constant-time comparison.

### 8.4 Identifiers, paths, classes, and URLs

- Define a full-match allowlist regex for IDs and slugs.
- Reject `..`, slashes where a flat segment is expected, null/control
  characters, and empty normalized values.
- Resolve project paths through `Filesystem` or a fixed owned base directory. Never concatenate onto `Filesystem::getPath()` for a private path — that is the public, webserver-facing root. Use `Filesystem::path( $appData, '/text' )`, which resolves `Filesystem::PRIVATE_DIRS` against the private root instead.
- Never pass a request-derived class string to autoloading or
  `method_exists()`.
- Never let a request choose an arbitrary callback method.
- Validate schemes for links. Reject `javascript:` and unexpected data schemes
  where the value can enter an `href` or `src`.
- For outbound HTTP, allowlist destinations where possible, set time/size
  limits, and prevent redirects to local/private addresses. The kernel's one
  client is `\Nino\Fetch::get()` - https only, no redirects, a timeout and a
  byte cap, stubbed in tests - and the catalogue is its only caller; a
  feature that needs the network goes through it rather than calling curl
  itself, and never on a page request.

Escaping does not make path traversal safe. Validate structure before resolving
or escaping.

### 8.5 HTML and browser DOM

- Plain strings become text with `htmlspecialchars` or `textContent`.
- Rich text passes `Html::sanitizeHtml()` on the server.
- Do not trust a rich-text editor's client filtering.
- Do not concatenate request data into `innerHTML`.
- Do not concatenate strings into inline scripts/styles.
- Preserve CSP nonces and existing security headers.
- Do not add `unsafe-inline` or a remote CSP source merely to make one widget
  work.
- Neutralize Nino bracket syntax when untrusted data enters a recursively
  rendered shortcode result.
- Attribute, URL, HTML, CSS, JS, JSON, mail header, and shell contexts require
  different handling.

### 8.6 Files and uploads

- Use Nino's image processing API for uploads.
- Enforce size, decoded format, width/height, and an owned destination.
- Store generated relative filenames, not a browser filename or absolute path.
- Do not delete a previous image until the new value was successfully
  persisted; restore/cleanup on veto as current image flows do.
- Delete only files proven to be owned by the exact record/slot.
- Never recursively delete a path assembled from a request.
- Use atomic mutation for array files.
- Keep secrets and runtime records out of versioned templates/text.

### 8.7 Privacy, enumeration, and retention

- Public login, reset, newsletter, and account-like flows return generic
  responses that do not reveal whether a record exists.
- Rate-limit authentication, public mail, signup, and expensive endpoints.
- Bound log and submission retention.
- Do not log passwords, CSRF tokens, session IDs, authorization/cookie headers,
  backup keys, full request arrays, or unnecessary personal data.
- Export only the requested content scope.
- Translation import is merge-only and schema/path validated; unknown keys and
  technical/global/image fields remain untouched.

### 8.8 Failure behavior

- Use `400` for malformed input, `401` for missing authentication, `403` for
  insufficient permission, `404` for an intentionally public missing resource,
  `409` for conflicts, `429` for rate limits, and `500` for an internal write
  failure where the operation did not complete.
- Do not report success before persistence succeeds.
- Do not turn a recoverable logging failure into loss of a successfully handled
  public request.
- Do not swallow programming errors under a broad `catch(Throwable)`.
- Test the failure branch, not only the happy path.

## 9. Common incorrect approaches and their correction

| Incorrect | Correct |
| --- | --- |
| Edit a generated `templates/` file when changing future installs | Edit the owning `_admin/install/library/...` source |
| Assume a page template creates a route | Add a route separately |
| Call a route “the template” | Keep public route, internal URI, and template body distinct |
| Register a workbench panel in a list | Nothing to register - put the module under `_admin/Nino/Modules/<Name>/`, `Admin::modules()` reads the directory |
| Edit the shell template or a tab list to add a screen | Write a panel class; a runtime module answers `adminPanels()`, a workbench module is a directory under `_admin/Nino/Modules/` |
| Rely only on dispatcher auth | Guard every API method |
| Use direct `echo`/`header()` | Modify `$request` / use `Http` |
| Read then write a shared PHP array | Use `Filesystem::mutate()` |
| Serialize all `$appData` | Persist selected keys with the owning writer |
| Use `innerHTML` for an API label | Construct DOM and use `textContent` |
| Validate only in JavaScript | Repeat strict validation server-side |
| Make image fields required | Create the Element, then upload |
| Save one merged Element object to every locale | Split global and locale fields |
| Invent a Section token | Use only the seven supported token forms |
| Put an Elements image into `{{image:image}}` | Use local `[[image]]` in the loop |
| Put `limit` in preset `allow` | Set its default; Composer clamps `1..12` |
| Choose any globally valid layout | Choose one valid for the content module |
| Put several top-level sections in `section.tpl` | Exactly one complete root section |
| Add `nino-vpa` behavior to preview | Let preview strip VPA and stay visible |
| Enable scripts/remote forms in preview | Keep the sandbox deterministic and inert |
| Prefix reusable includes with `page-` | Reserve `page-*` for full page templates |
| Add metadata below markup | Put both metadata comments at byte zero |
| Remove copied installer files on deselect | Update selection only; preserve edited files |
| Put an installable feature under `app/Nino/Modules/` or offer it in the wizard | One directory under `features/<Name>/` with `feature.php`; the Features panel activates it after setup |
| Declare a feature's class in its manifest | The class is `\Nino\Modules\<Directory>`, derived; a `module` entry saying anything else is refused |
| Let a feature's unit overwrite a project file on update | Activation and update are add-only; migrate the feature's own data in `upgrade()` |
| Declare a page-unit file that does not exist | Keep `files` paths unit-relative and test the copied output |
| Put translated words in IDs/fill keys | Use stable semantic slugs |
| Update only English or German behavioral docs | Keep both manuals synchronized |
| Test for a new file but not behavior | Assert observable response/data/DOM contracts |

When an existing pattern appears to violate this guide, inspect the current
runtime and tests. A stale pattern is not authority for new code.

## 10. Test and validation matrix

Every test is a standalone script. It creates or should create its own isolated
temporary project and must not rely on a previously installed working tree.

| Changed area | Minimum targeted tests |
| --- | --- |
| Kernel, callbacks, rendering, runtime module | `tests/kernel-smoke.php` |
| Shared writes, locks, logs | `tests/concurrency-smoke.php` plus owner test |
| Workbench shell, accounts, content panels | `tests/admin-smoke.php` |
| Structure/system panels, registry contract, recovery | `tests/admin-system-smoke.php` |
| Workbench frontend | relevant `tests/admin-*-js-smoke.js` |
| Setup wizard behavior/library | `tests/install-smoke.php` and relevant install JS test |
| Design module/panel | `tests/design-smoke.php` and `tests/design-js-smoke.js`, plus `tests/install-smoke.php` when the wizard's application changes |
| Section preset/Template Builder PHP | `tests/templates-smoke.php` |
| Template Builder browser behavior | `tests/templates-js-smoke.js` |
| The feature contract (`\Nino\Features`, the wizard's unit application) | `tests/features-smoke.php` |
| The catalogue (`\Nino\Catalogue`, `\Nino\Fetch`, the panel's catalogue and install actions) | `tests/catalogue-smoke.php` - a keypair, signed catalogues and archives built in the test, the network stubbed |
| A feature (a new one, or one of the catalogue's such as Newsletter or Search) | its own `features/<Name>/tests/<key>-smoke.php`, plus `tests/features-smoke.php`; CI's `features` job runs the catalogue's features against every push |
| Shared public UI slider/tabs | corresponding `tests/nino-ui-*-js-smoke.js` |
| Shared management UI/CSS structure | owner tests plus `tests/admin-lists-js-smoke.js` |
| Multi-element reference control | `tests/nino-ui-elementlist-js-smoke.js` plus both element forms' own tests |

Complete suite:

```bash
php tests/kernel-smoke.php
php tests/admin-smoke.php
php tests/admin-system-smoke.php
php tests/install-smoke.php
php tests/design-smoke.php
php tests/templates-smoke.php
php tests/features-smoke.php
php tests/catalogue-smoke.php
for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done
php tests/demo-catalogue-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

Syntax and diff checks:

```bash
find . -name '*.php' -not -path './data/*' -print0 \
	| xargs -0 -n1 php -l
find . -name '*.js' -not -path './data/*' -print0 \
	| xargs -0 -n1 node --check
phpstan analyse
npx eslint .
git diff --check
```

PHPStan and ESLint MUST report nothing new. A finding listed in
`phpstan-baseline.neon` may be fixed and its entry removed; a new finding is
never added to the baseline.

Do not claim a check passed if the required interpreter, extension, browser
capability, or fixture was unavailable. Report it as not run and explain why.

### 10.1 What a useful smoke assertion tests

Prefer:

- exact HTTP status and safe response shape;
- exact persisted data after a fresh read;
- atomic survival of two independent changes;
- route ownership and unrelated-route survival;
- permission/CSRF denial;
- sanitized rendered output;
- lossless source reconstruction;
- real component/preset behavior;
- and stable UI state after a request.

Avoid:

- asserting only that a method exists;
- snapshotting an entire unstable HTML page;
- checking a CSS class with no behavioral reason;
- calling private methods directly instead of the public flow;
- or passing because errors were globally suppressed.

### 10.2 Patch verification

For patch-only delivery:

```bash
git diff --binary --no-ext-diff > nino-change.patch
# git diff does not include untracked files. Append each new file explicitly:
git diff --no-index --binary -- /dev/null AGENTS.md \
	>> nino-change.patch || test $? -eq 1
git diff --check
git diff --no-index --check -- /dev/null AGENTS.md || test $? -eq 1
```

In a clean checkout at the intended base:

```bash
git apply --check /path/to/nino-change.patch
git apply /path/to/nino-change.patch
git diff --check
```

Then run the relevant tests in that checkout. A patch that applies only on a
dirty or older tree is not a valid handoff.

## 11. Definition of done by artifact

For every artifact: the syntax checks, PHPStan and ESLint pass with nothing
new, and the tests named in section 10 pass.

### Panel done

- [ ] The class answers `actions()`, `nav()` and `perm()`; a runtime module's panel is returned by `adminPanels()`, a workbench one is `_admin/Nino/Modules/<Name>/Admin/Admin.php`.
- [ ] Actions carry the panel's slug and every method guards itself with `Admin::guardPerm()` and the panel's permission.
- [ ] Payload, IDs, enums, lengths, and paths are validated.
- [ ] Persistence is atomic and failures are returned.
- [ ] `assets()` names project-relative `.js`/`.css` only (through `Panels::relative()` for a module); the JS namespace equals the nav uri; a `template()` is a fragment with no head, link or script of its own.
- [ ] JS uses existing UI primitives and safe DOM construction.
- [ ] Empty, loading, error, list, form, and saved states are usable.
- [ ] Backend and JS tests cover denial, mutation, and - for a module panel - absence while the module is off.

### Runtime module done

- [ ] Class and autoload path match exactly.
- [ ] `init()` registers behavior without output or incidental writes.
- [ ] Technical routes have one owner and correct callback identity.
- [ ] Shortcode/response output is context-safe.
- [ ] Writes enforce CSRF/auth/validation/rate limit as applicable.
- [ ] Shared files use atomic mutation and bounded retention.
- [ ] Activation, API, shortcode, failure, and concurrency are tested.

### Installer module package done

- [ ] The unit is the module's own `install/` directory and its key is a unique slug.
- [ ] Runtime class exists independently of the manifest.
- [ ] Manifest uses recognized keys and installer slugs for requirements.
- [ ] Templates/files/types/text land in exact paths.
- [ ] Config defaults preserve existing custom values.
- [ ] Reapply is idempotent for selection/config.
- [ ] Deselect does not destructively delete copied files.
- [ ] Direct selection and page auto-require paths are tested.

### Feature done

- [ ] One directory `features/<Name>/` whose name is the class name; `feature.php` returns a valid manifest (`\Nino\Features::manifest()` answers an array without a warning) and the class file lies beside it.
- [ ] `key` is a unique slug, `version` is `major.minor.patch`, `nino` names the constraint it was written against, `requires` lists feature keys, `data` lists the `/data/` files it owns.
- [ ] Every setting has a known type, a validating default where it has one, no default on a `secret`; the class reads settings through `\Nino\Features::setting()` with a default.
- [ ] The install unit is add-only safe: nothing relies on overwriting, no uninstall is promised, runtime routes live in `init()`.
- [ ] `upgrade()` migrates only the feature's own data and is idempotent for the versions it handles; a `data/` owner registers `/nino/admin/restore`.
- [ ] `features/<Name>/tests/<key>-smoke.php` loads `tests/harness.php` (three levels up or `NINO_ROOT`) and covers manifest, activation, the unit's files, settings, the panel's presence and absence, update and deactivation; `php tests/features-smoke.php` still passes.

### Section preset done

- [ ] Slug, name, description, category, tags, version, and shell are valid.
- [ ] All defaults are complete and content/layout compatible.
- [ ] `allow` exposes only intended variations.
- [ ] Generic renderer is used unless custom DOM is necessary.
- [ ] Custom template has one root and only resolvable tokens.
- [ ] Native fields, image slots, and Element schema are exact.
- [ ] Preview is deterministic, visible, inert, and representative.
- [ ] Composer, invalid-choice, preview, and search tests pass.

### Page/template unit done

- [ ] Route and template are separate and have one owner each.
- [ ] `page-*.tpl` filename, display metadata, VPA, and slots are valid.
- [ ] Sections have unique semantic IDs and stable fill paths.
- [ ] Includes omit `.tpl` and resolve to shipped/copied files.
- [ ] Required text, modules, image slots, and Element types are supplied.
- [ ] Locale suggestions and actual content are separated.
- [ ] Install/reapply/render and Template Builder losslessness are tested.

## 12. Completion report format

At the end of an implementation, report:

1. **Outcome:** one sentence describing the observable result.
2. **Changed:** exact files grouped by backend, frontend, template/library,
   tests, and docs.
3. **Security/data:** auth, CSRF, validation, escaping, persistence, migration,
   and deletion behavior that mattered.
4. **Validation:** every command actually run and its result.
5. **Not run:** unavailable or deliberately omitted checks.
6. **Handoff:** patch filename and exact base revision when patch delivery was
   requested.
7. **Limitations:** only real remaining constraints; do not hide them in the
   implementation narrative.

Never say “done” when only the happy path was implemented or when the generated
artifact was not checked against its consumer.

## 13. High-value source references

Read these before designing a new implementation:

| Need | Start with |
| --- | --- |
| Runtime lifecycle/APIs | `docs/development.md`, `_nino/Nino.php` and `_nino/Nino/<Class>/<Class>.php` |
| Simple shortcode module | `_nino/Nino/Modules/Navigation/Navigation.php` |
| Public validated form | `_nino/Nino/Modules/Form/Form.php` |
| Privacy-sensitive public flow | the catalogue's [features/Newsletter/Newsletter.php](https://github.com/dapeio/nino-features/blob/main/features/Newsletter/Newsletter.php) |
| Feature contract and manifests | `_nino/Nino/Features/Features.php`, `tests/fixtures/features/Sample/`, `tests/features-smoke.php`; the published manifests in the catalogue, [features/Newsletter/feature.php](https://github.com/dapeio/nino-features/blob/main/features/Newsletter/feature.php) and [features/Search/feature.php](https://github.com/dapeio/nino-features/blob/main/features/Search/feature.php) |
| A feature's own test | `tests/harness.php`, and the catalogue's [features/Search/tests/search-smoke.php](https://github.com/dapeio/nino-features/blob/main/features/Search/tests/search-smoke.php) |
| Workbench shell, registry, recovery | `_admin/Admin.php` |
| Workbench panel backend patterns | `_admin/Nino/Modules/Elements/Admin/Admin.php`, `Routes/Admin/Admin.php`, `Users/Admin/Admin.php` |
| Panel list/form JS | `_admin/Nino/Modules/Elements/assets/types.js` and `admin.js`, `_admin/Nino/Modules/Routes/assets/admin.js`; the shell `_admin/assets/script.js` |
| Panel contract | `\Nino\Admin\Panels` in `_admin/Admin.php`; smallest panel `tests/fixtures/features/Sample/Admin/Admin.php` (the catalogue's `features/Search/Admin/Admin.php` is the smallest published one); fills and a tile `_nino/Nino/Modules/Form/Admin/Admin.php`; own template `_nino/Nino/Modules/Design/Admin/Admin.php`; workspace `_nino/Nino/Modules/Templates/Admin/Admin.php` |
| Ordered Admin relationships | `\Nino\Modules\Navigation\Admin` and `_nino/Nino/Modules/Navigation/assets/admin.js` |
| Shared Admin UI | the `nino.system` half of `_admin/assets/style.css` and `_admin/assets/Nino.admin.js` |
| Installer package shape | `_nino/Nino/Modules/*/install/manifest.php`; a feature's `features/*/install/manifest.php` has the same shape |
| Setup wizard semantics | `_admin/install/Install.php` and `tests/install-smoke.php` |
| Generic Section presets | `_nino/Nino/Modules/Templates/library/*/manifest.php` |
| Section preset with several layouts | `_nino/Nino/Modules/Templates/library/feature-split/` |
| Composer/parser contracts | `_nino/Nino/Modules/Templates/Composer/Composer.php`, `SectionDocument/SectionDocument.php`, `AreaComposer/AreaComposer.php` and `tests/templates-smoke.php` |
| Basic page unit | `_admin/install/library/pages/home/` |
| Module-dependent page | `_admin/install/library/pages/contact/` |
| Locale-structural page | `_admin/install/library/pages/legal/` |
| Element file example | `_admin/install/library/pages/.demo-elements/demo-services.php` |

Human behavior changes normally require matching updates in both English and
German manuals. This AI guide and its recipes under `docs/recipes/` stay in
canonical English only, so agents do not receive two divergent machine
instructions.
