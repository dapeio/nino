# Changelog

## Unreleased

- **`/_templates` can now edit, not just read**: click a box in the canvas to
  select it, and the inspector renders one control per setting the block's
  manifest declares - a select for `classenum`/`classgroup`/`tag`, a
  checkbox for `classtoggle`, a text field for `attr`/`text`, with a
  responsive setting's breakpoint variants indented under their base rather
  than standing next to it as five near-identical fields. There is no
  per-block form code anywhere; adding a setting to a manifest is what makes
  it editable. Clicking a palette entry inserts that block - inside the
  selection if it takes children, next to it if it doesn't, at the end of
  the document if nothing is selected - and its `block.tpl` is parsed
  server-side by the same parser documents go through (`library/parse`), so
  a block's markup is written exactly like template markup with no second
  code path. Move/duplicate/remove come from the manifest's own `actions`.
  Nesting depth is now drawn as a per-level tint (`color-mix()` against the
  editor theme's tokens, capped at six levels) so the layers of a deep grid
  read apart. Nothing reaches disk until Save; switching template or leaving
  the page with unsaved work asks first. Structural edits carry their own
  indentation, copied from the neighbour the new markup lands next to rather
  than derived from a depth counter, so an insert lines up with whatever the
  file already uses and a remove doesn't leave a blank line behind. Two
  fixes fell out of testing that: `blocks.js` documented in-place class
  replacement but actually removed-and-appended, which reshuffled the
  `class` attribute of every edited element, and a first child inserted into
  an empty element copied the closing tag's indent and landed one level too
  shallow. The dom-free half of the frontend (`blocks.js`, the new
  `tree.js`) is covered by `tests/templates-js-smoke.js`, a plain-node suite
  in the same dependency-free shape as the php ones - its stub `document`
  throws on use, so a module that started touching the dom fails the suite
  rather than quietly becoming untestable. See `docs/_templates.md`

- **Added `/_templates`, the template builder** (foundation - reads,
  recognizes and draws; selecting/inserting/editing follow). Its own
  top-level folder rather than an `_admin` module, same reasoning `_install`
  has one: a development-time tool a project may delete once the design is
  settled. The password is `_admin`'s, though - it requires
  `_admin/Admin.php` and reuses its session flag instead of introducing a
  second one, the dependency `_install` already has, and `/_admin`'s header
  gained a link. The whole thing rests on one idea: **a block's identity is
  its html tag plus its css classes, and its properties are those same
  classes**. `<div class="ui-grid-100 ui-grid-l-50 ui-mb-3">` *is* a grid
  column with those three settings - there is no data model beside the
  markup, no json sidecar and no `data-*` marker to write, so every `.tpl`
  the project already has opens with its structure recognized without being
  migrated, and what the builder saves stays exactly as hand-editable as
  what it opened. Blocks are one directory per block under
  `_templates/library/<key>` (manifest + `block.tpl`), the same convention
  `_install/library` uses - 21 core blocks ship, adding a 22nd is one new
  directory and no code change. A manifest declares how to recognize the
  block (`match`, scored by specificity so a generic "Heading" never
  swallows a "Section Title") and which classes, attributes, tag or text are
  editable (`settings`, six types); the mapping between control and class
  runs client-side only, in `assets/blocks.js`, so nothing can read a class
  one way and write it back another. The canvas is deliberately not a
  preview: grid widths and spacing are drawn to scale, everything else is a
  labelled box, and markup the library doesn't describe still gets a box -
  read-only, never hidden and never dropped. A node whose `id` is targeted
  by a project css rule is flagged, since such a rule overrides the classes
  the builder presents as that node's properties. Only `page-*`/`section-*`
  are editable: `html-header.tpl`/`html-footer.tpl` are one structure split
  across two files (the header opens `<main>`, the footer closes it), so
  neither is a well-formed fragment and an html parser handed one alone
  would "correct" it into a broken page frame. Underpinning all of it,
  `Parser`/`Serializer` guarantee a **byte-exact round-trip** - attribute
  order, whitespace, comments and the original spelling of every entity are
  preserved, so opening a template and saving it unchanged is a no-op rather
  than a reformat; `tests/templates-smoke.php` asserts that against every
  shipped page template. See `docs/_templates.md`

- **Added `/_install`'s "Themes" step** and the `_install/library/themes/`
  branch behind it: a theme is now a complete, self-contained library unit
  (`manifest.php` + its stylesheet + the webfonts that stylesheet actually
  references + whatever else it lists) rather than one loose
  `style.theme.<key>.css` among eight in `base/assets`. The wizard grew a
  third step for it, between Setup and Webpages: a grid of tiles, one per
  unit, each with the preview image, title and description its own manifest
  declares, and a click on a tile's preview enlarges it in a lightbox. The
  eight shipped themes move into their own directories unchanged, each now
  carrying the three webfonts it uses - which also drops the shared font
  collection out of the `democontent` module, along with the four faces no
  theme ever referenced, and fixes four stylesheets pointing at a
  `/fonts/titel/` directory that never existed (those title fonts silently
  never loaded). Applying copies the unit's `files` exactly the way Setup
  already copies a module's, and swaps the theme entry in `config.php`'s
  `/nino/html/assets['/.cache/style.css']` bundle in place - a stylesheet's
  position there is what decides which `:root` block wins the cascade, so
  it is carried at the old entry's index rather than appended, and every
  other entry in that array is left alone. The picked key is persisted at
  `/nino/install/theme` (a config predating it still resolves by matching
  the bundle against each theme's declared stylesheet). Setup loses its
  theme `<select>` entirely; adding a ninth theme is one new directory, no
  code change. See `docs/_install.md`

- **Renamed both backend areas**: `/_dev` is now `/_admin`, and what used to
  be `/_admin` is now `/_editor`. The developer tooling already carried the
  project's technical surface (element types, text, pages, config, restore)
  while `/_admin` was the content backend an editor logs into every day -
  the names were the wrong way round. Directories, entry stubs, routes, PHP
  namespaces and bootstrap classes (`\Nino\Dev\Dev` -> `\Nino\Admin\Admin`,
  `\Nino\Admin\Admin` -> `\Nino\Editor\Editor`), JS namespaces
  (`Nino.dev` -> `Nino.admin`, `Nino.admin` -> `Nino.editor`), handbooks and
  smoke tests all move with them, as does every identifier the two areas
  own: css custom properties and classes (`--admin-*` -> `--editor-*`,
  `.admin-field` -> `.editor-field`, `.dev-hint` -> `.admin-hint`), element
  ids (`#dev-login-form` -> `#admin-login-form`), the session flag
  (`./nino/dev/authed` -> `./nino/admin/authed`) and `config.php`'s own keys
  (`/nino/admin/backups` -> `/nino/editor/backups`, same for `/logs`).
  `/_install`'s own `\Nino\Install\Admin` - the wizard's Admins step, a
  different class that only shares the name - is untouched, and so is every
  entry in the released sections below, which describe the layout as it was
  at the time. `.gitignore` still names the old paths and needs updating by
  hand - it is left out here so this change applies with a plain `git am`
  (it is the one file in the repository with CRLF line endings, which git's
  mailbox parsing mangles)

- **Fixed `/nino/install/webpages` not actually being a shared list**: the
  two tools that write it disagreed on what `template` meant - `/_install`'s
  Webpages step stored its own `_install/library/pages` unit key (`404`),
  while `/_admin`'s Pages module reads it as the on-disk template file
  (`page-404`). Since the project ships four pages pre-applied, that made
  every page in a fresh checkout unopenable in the Pages module ("unknown
  template"). Entries now carry both, `template` naming the file and
  `libraryKey` the unit, and each entry's `statusCode` and route `body` are
  persisted alongside them rather than left implicit in the route - without
  those, saving the shipped 404 page from `/_admin` reset it to a 200, and
  saving the "legal" page flattened its per-locale template resolution
  (`[template /templates/page-legal.[[/nino/http/response/locale]]]`) onto
  a single locale's file. A body the template `<select>` can't spell now
  disables that select and is kept verbatim on save, and the Webpages step
  carries an entry created in `/_admin` (which has no `libraryKey`) through
  its replace instead of rejecting it

- **Added `/_admin`'s "Pages" module**: create/edit/delete the site's actual
  page routes without hand-editing `/nino/http/routes` as raw json (Config
  still covers everything this doesn't) - a friendlier continuation of
  `/_install`'s Webpages step for once `_install` has been deleted. The
  template `<select>` only ever offers a `templates/page-*.tpl` file that
  already exists on disk - no copying, no library units, just wiring an
  existing template up to a uri. Same Element-URI/Http-URI split as
  Webpages (see below) and the same `/nino/install/webpages` array as its
  persisted list - shared, not duplicated, so either tool sees the other's
  edits. The edit form's fields sit inside `<fieldset>`s so they pick up
  `_editor`'s usual card styling, and the list's own ↑/↓ buttons swap an
  entry with its neighbor and re-persist the new order - the same order
  `[[/website/navigation/main]]` gets generated in, and the same ↑/↓
  pattern Webpages (see below) now uses too. See `docs/_admin.md`
- **Added `/_install`**: a graphical, developer-only setup wizard for a
  fresh checkout, walked through as a strictly linear "Back"/"Next" flow
  across six steps (the step list at the top is a progress display, not a
  menu): Environment checks; Setup (Available Locales, a Native Locale
  among them - its `<select>` only ever offers whichever locales are
  currently checked, live-updated as you check/uncheck them - modules and
  a theme - one of the bundled `assets/style.theme.<key>.css` stylesheets,
  swapped into `config.php`'s asset bundle on apply); Webpages (a
  free-form, ordered list of the project's actual pages, click a row to
  edit it or "New Webpage" for a blank one - same list-plus-drill-down-form
  shape and ↑/↓ list reordering `_admin`'s Pages module uses (see above), so
  the two feel like one tool rather than two differently-built ones -
  everything here stays client-side until "Next" posts the whole list at
  once, unlike Pages' immediate per-entry save; an Element-URI, a stable
  identifier used only for this entry's own `/webpage<uri>/*` text meta
  and the route's own `uri` data field; an Http-URI, the real browser
  path that alone drives the `/nino/http/routes` array key
  (`\Nino\Http::requestRoute()` matches by exact key, not by scanning for
  a route whose `uri` field matches - see `Webpages::_routeKeys()`'s
  docblock); a starting template from `_install/library/pages`; per-locale
  name/title/description defaulting to a generic placeholder rather than
  the template's own wording; and, once the Navigation module is active, a
  "Show in main navigation" checkbox that feeds a generated main menu);
  Personal Infos (same
  shape as `_editor`'s Text panel - every `/company/*`/`/website/*` key
  that's global in one fieldset on top, every locale-scoped key
  (`/company/country`, `/company/description`) below behind a locale
  dropdown, each with a friendly label); Admins (create/delete accounts,
  the list is allowed to end up empty - "Next" is what actually requires
  one); and Finish, which sets the real `_admin` password. Guarded by
  `Admin::hasDefaultPassword()`: only runs while `_admin/Admin.php` still ships
  its placeholder hash, and setting a real one (the wizard's own last step)
  locks it back out for good. See `docs/_install.md`. Entirely optional,
  same as `_admin`/`_editor` - safe to delete (`rm -rf _install`). Picking a
  module/template that declares `requiresModules` (eg. Contact needs
  Forms) pulls those in automatically. Setup's and Webpages' pickers always
  show the current selection pre-checked and applying either replaces the
  whole picture - unchecking a locale/module or removing a page and
  re-applying actually removes it. Routes/modules/locales are always
  rebuilt from what's actually persisted in `config.php`, never from the
  current request's in-memory state - a module's self-registered runtime
  routes (eg. `POST://.form`) and `/_install`'s own route never end up
  written to `config.php`, and a hand-added route outside the library
  survives every apply untouched. Templates/text are the exception and are
  only ever added, never removed by a later apply. Routing isn't
  reviewable in the wizard either - `_admin`'s existing Config module already
  covers that
- **`text/`, `templates/` and `elements/` ship pre-filled with a working
  starter site instead of empty.** `config.php` ships as if `/_install`'s
  Setup (native locale only, structural modules, the "agency" theme) and
  Webpages steps (`/home` at `/`, `/404`, `/legal` and `/contact`, Contact
  auto-pulling Forms) had already been run once - a fresh checkout renders
  a real four-page site out of the box, `/_install` remains available
  (and safe to re-run - see its "replaces, not merges" behaviour above) to
  add locales/modules/pages or reshape the defaults before deleting it.
  Previously shipped demo/starter content (Max Mustermann business-coaching
  copy, the Nino kernel showcase pages) moved into `_install/library/` as
  generic, brand-neutral placeholder content, selectable per locale/module/
  page via `/_install`'s Setup/Webpages steps
- **8 bundled themes** (`assets/style.theme.<agency|correct|glow|kontor|
  marketplace|nighty|solid|wellness>.css`) replace the previous 3
  (`style01`/`02`/`03`) - each a self-contained stylesheet with its own
  `@font-face` declarations, picked in `/_install`'s Setup step (see above)
  or swapped by hand in `config.php`'s `/nino/html/assets` bundle
- **`_install/library/modules/mail` merged into `forms`/`newsletter`.**
  Both content modules now carry their own `mail-header.tpl`/
  `mail-footer.tpl` and mail design-token blacklist entries directly
  instead of depending on a separate `mail` module pulled in via
  `requiresModules` - one fewer opt-in unit for the same content, no
  behaviour change
- **Fix**: `Auth::insertUser()`/`Auth::updateUser()` passed a function call
  straight into `doCallbacks()`'s by-reference parameter, raising an "Only
  variables should be passed by reference" notice that Runtime's own error
  handler treats as fatal on every real request (not just the CLI smoke
  tests, which never boot that handler) - creating or renaming/repasswording
  an account over http 500'd

## 0.10.0-beta

Security and reliability hardening pass, plus a module-system refactor.

- **CSRF**: `\Nino\Csrf` protection is now always active; `\Nino\Modules\Csrf`
  only powers the `[csrf]` shortcode. Tokens are accepted from header or JSON
  body, enforcement matches methods exactly instead of "beyond POST" loosely,
  and it no longer depends on an enable flag
- **Auth/session**: throttled logins per IP, stopped user enumeration,
  session tokens instead of client-IP binding, password hash dropped from
  the PHP session, strict session cookie flags and token lifetime, fixed
  parallel logins cancelling each other out
- **Headers**: valid `X-Frame-Options`, completed CSP, fixed mail header
  injection and sender headers, stopped leaking credentials through the
  error handler
- **Filesystem/locking**: switched `Elements` to `Filesystem::mutate()`
  (closed 8 early-return lock leaks), atomic file writes with correct
  locking, `Filesystem` now mutates its lock primitive on every read,
  config path and rotating-log edge cases fixed
- **Module system**: modules moved to
  `_nino/Nino/Modules/<Name>/<Name>.php` and are now loaded via
  `spl_autoload_register()`; `\Nino\Shortcodes` renamed to `\Nino\Modules`
- **Refactors**: added `Http::fail()`/`Http::ok()` response helpers, moved
  `\Nino\Text` into the kernel (Admin/Dev keep only their delta), reworked
  the rotating log, moved `backupManifest()` to `\Nino\Backup`, trimmed
  comment density across the codebase
- **Fixes**: shortcode recursion guard, element query keys combined with
  AND, uri rename no longer breaks, route locale handling, asset bundle
  rebuilt only when changed, `array_merge_recursive` replaced with a
  scalar-overwrite merge, a dashboard permission leak, response header
  filtering, `Callbacks::doCallbacks()`, `writeContentData()` now checks
  its return value, `Text::saveBatch()` no longer rebuilds entries per call
- **Tests**: added `concurrency-smoke.php` and `dev-smoke.php`, expanded
  `kernel-smoke.php` and `admin-smoke.php`

## 0.9.0-beta

First tagged release. Highlights since the initial drop:

- Fixed the default Content-Security-Policy being wiped by the Jstext
  module's own script-src fragment
- Fixed error display/logging defaults for production (display off, log on)
- Fixed locale-switch redirects, which never actually redirected
- Added `session_regenerate_id()` on login (session fixation defense)
- `/data/` and uploaded images are no longer git-tracked
- Newsletter signup is now double opt-in (confirm link by mail) with a
  self-service unsubscribe link; everything moved under `/.newsletter`
  (routes registered by the `Newsletter` class itself, not `config.php`)
- `router.php` serves the bundled demo images on the local dev server
- Simplified all `docs/*.md` down to plain reference material; removed
  `docs/security.md`