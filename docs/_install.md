# Nino — Install Handbook
*[Deutsch](_install.de.md)*

**Links:**
[README](../README.md) · [Design-Handbook](design.md) · [Developer-Handbook](development.md) · [_editor-Handbook](_editor.md) · [_admin-Handbook](_admin.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

A graphical, developer-only wizard for a fresh checkout's initial setup at `/_install`. Optional - a project can just as well be set up by hand (see `docs/development.md`) - but it walks through the same steps in a browser instead of the shell.

The project ships with `text/`, `templates/` and `elements/` **pre-filled with a working starter site** - `config.php` ships as if `/_install`'s Setup (native locale only, structural modules, the "agency" theme) and Webpages steps (`/home` at `/`, `/404`, `/legal` and `/contact`) had already been run once, so a fresh checkout renders a real four-page site with no setup at all. `/_install` stays available afterward (and safe to re-run - see "Whatever's checked/listed when you hit Next is the whole picture" below) to add locales/modules/pages or reshape the defaults before deleting it.

## When it runs

`/_install` only works while `_admin/Admin.php` still ships its placeholder `PASSWORD_HASH` (the value that matches no real password). The wizard's own last step - setting a real `/_admin` password - is what locks it back out, permanently: once a real hash is in place, `/_install` refuses to run at all, on every request, with no login screen and no way back in short of hand-editing `_admin/Admin.php`'s `PASSWORD_HASH` constant again.

That also means `/_install` needs no password of its own. Before a real `/_admin` password exists, the project isn't meaningfully secured yet either way - the wizard's job is to get it there.

Like `_admin` and `_editor`, `_install` is entirely optional and safe to remove: once finished, delete the whole folder (`rm -rf _install`). A project that never wants the wizard or its content library at all can delete `_install/` from the start (kept optional exactly like `_admin`/`_editor`) and set up `text/`/`templates/`/`elements/`/`config.php` by hand instead.

## The wizard

A strictly linear flow: "Back"/"Next" at the bottom move between the six steps below, committing whatever the current step needs to save before advancing (Setup applies its picker, Webpages applies its page list, Personal Infos saves its fields, Admins just checks an account exists first - Environment and Finish have nothing to commit). The step list at the top is a progress display only, not a menu - it isn't clickable, there's no jumping ahead or skipping a step.

### 1. Environment

Read-only diagnostics: the running PHP version against the `>= 8.4` the kernel requires, the extensions the README documents (`gd` for image cropping/resizing, `mbstring`, `session`, `json`, and `Phar`/`PharData` for `_editor`'s automatic backups and `_admin`'s Restore), and whether the directories Nino writes to at runtime (project root, `text/`, `images/`, `data/`, `.cache/`) are writable. "Recheck" re-runs the same probes - nothing here is ever written to.

### 2. Setup

Picks Available Locales, a Native Locale among them, "modules" (Navigation, Localepicker, Forms, Newsletter - each their own text and/or mail templates) and a theme, and assembles them into the real project: `config.php`'s locales/modules/routes/theme, `/templates` and `/text`. Pages have their own step (below) - Setup never touches one.

**Native Locale** is a `<select>` that only ever offers whichever Available Locales are currently checked above - checking/unchecking one updates it live. It maps to `/nino/locales/native` (the fallback used wherever no locale is otherwise known, eg. a fresh visitor with no locale cookie yet). Posting one that's actually among this call's own picked locales always wins; anything else (nothing posted, or a stale value no longer checked) falls back to keeping the current native if it's still picked, else the first picked locale - the same rule `/_install` always applied here, just now also directly choosable instead of only ever inherited.

**Theme** is a plain `<select>` listing every `assets/style.theme.<key>.css` file found on disk (eight ship out of the box). Picking one swaps the single theme entry in `config.php`'s `/nino/html/assets['/.cache/style.css']` bundle - it never adds or removes anything else in that array, and leaves it alone entirely if nothing recognizable is selected.

Picking a module that declares `requiresModules` pulls that in automatically - the response after applying lists the full, resolved selection, not just what you checked.

The picker shows the current selection, pre-checked, every time you land on this step - because applying replaces it. Whatever's checked when you hit "Next" is the whole picture: unchecking a locale/module and re-applying actually removes it, the same way any settings form works. Routes are always rebuilt from what's actually persisted in `config.php`, never from the current request's own in-memory state - a module's self-registered runtime routes (eg. `POST://.form`) and `/_install`'s own route never end up written to `config.php`, and a route you added by hand outside the library (eg. via `_admin`'s Config module) is left alone regardless of what you pick here.

Templates and text fragments are the exception: those are only ever added, never removed by a later apply, even for a module you un-pick - deleting a file you may have since hand-edited is a much riskier kind of "undo" than toggling a config array. Remove those by hand if you no longer want them.

### 3. Webpages

Builds the project's actual pages: a free-form, ordered list you manage directly, rather than a fixed checkbox per `_install/library/pages/<key>` bundle. Ships with four default entries (`/home` at `/`, `/404`, `/legal`, `/contact` - see "When it runs" above); click a row to open its editor, or "New Webpage" for a blank one - same list-plus-drill-down-form shape `_admin`'s Pages module uses once `_install` is gone, so the two feel like one tool rather than two differently-built ones. Each entry has:

- **Element-URI** - a stable identifier, yours to pick, that never has to look like a real path (eg. `/home`). It namespaces this entry's own `/webpage<uri>/*` text meta and becomes the route's own `uri` data field (what `[[/nino/http/response/uri]]` resolves to) - it does **not** decide where the page is actually reachable.
- **Http-URI** - the real browser path (eg. `/`). This alone drives the `/nino/http/routes` array key: `\Nino\Http::requestRoute()` matches a route by looking up `'<METHOD>:/'.$uri` as a literal array key, not by scanning for a route whose own `uri` field matches, so only the Http-URI is ever actually clickable. Keeping it separate from the Element-URI is what lets the home page keep a stable `/home` identifier while really living at `/`.
- **template** - which `_install/library/pages/<key>` bundle supplies the route body, template file and any deeper page content. The same template can be used more than once, at different uris (eg. two differently-purposed pages both starting from the "home" bundle).
- **name/title/description**, per active locale - this entry's own nav label, `<title>` and meta description. Left blank, each falls back to a generic placeholder ("Page"/"Page Title"/"Page description.") rather than whatever wording the template's manifest ships - that wording is a per-instance choice now, not baked into the template.
- **Show in main navigation** - only shown once the Navigation module (step 2) is active. Checked entries feed the generated main menu, see "Navigation" below.

Both uris go through the same safety normalization (leading slash, no `..`, plain path characters) and must be unique within the list - independently, so two entries can't share either an Element-URI or an Http-URI, but one entry's Element-URI can equal another's Http-URI without conflict.

The list's own ↑/↓ buttons reorder it in place; "Delete page" lives inside an entry's own editor. Everything here - add, edit, delete, reorder - only ever touches the in-memory list; nothing reaches the server until "Next" batch-generates routes/templates/text/blacklist from the whole list in one go - same replace semantics as Setup: the posted list is the complete, authoritative picture, and revisiting this step always shows the current, persisted list rather than starting blank.

Templates and deeper page content stay additive, same as Setup - dropping a uri from the list doesn't delete the template file or content it already wrote.

The list is persisted at `config.php`'s `/nino/install/webpages` as `{ uri, httpUri, template, libraryKey, nav, statusCode, body, text }` - the same array `_admin`'s Pages module reads and writes. `libraryKey` is this step's own field (which `_install/library/pages` unit the entry came from); `template`, `statusCode` and `body` describe the route that unit produced, so the Pages module can still work with the entry once `_install` is gone. An entry the Pages module created carries no `libraryKey` at all - this step passes it through its replace untouched rather than rejecting it, and its template select shows it as "already set up".

**Navigation.** Once the Navigation module is active, every entry with "Nav" checked feeds `[[/website/navigation/main]]` - a generated `httpUri:name` list per active locale (the real, clickable path - not the Element-URI), in the exact shape `\Nino\Modules\Navigation`'s `[navigation]...[/navigation]` shortcode expects (see `_install/library/modules/navigation/templates/html-header-nav.tpl`/`html-footer-nav.tpl`). Unlike the rest of Webpages' output, this key is regenerated - not merged - on every apply: it's fully derived from the current list, not something you're expected to hand-edit before finishing the wizard.

### 4. Personal Infos

Bulk-fills the handful of `/company/*` and `/website/*` text keys every project has regardless of what steps 2/3 picked - each with a friendly label instead of its raw key, same shape as `_editor`'s Text panel: every **global** key (Company Name, Company Email, Company Phone, Company Adress, Website Author, Website Host) in one fieldset on top, every **per-locale** key (Company Country, Company Description) below behind a locale dropdown, with an in-memory snapshot preserving unsaved edits across a locale switch. Every field is a single-line input except Company Adress and Company Description, which stay multi-line. Saving writes the global fields plus whichever locale is currently selected - switch locales and save again to fill in the rest.

Everything else - technical/design-token keys (`text/blacklist.php`), a webpage's own name/title/description (Webpages already covers that), and any deeper module/page content - is left out here on purpose: it's fine as the library's own generic default, and if it isn't, edit it via `_editor`'s Text panel (or `_admin`, for technical keys) afterward instead.

### 5. Admins

Creates the first `/_editor` account(s) with full permissions (`/*`). The shipped placeholder account (`changeme@domain.com`, whose password hash matches nothing) is dropped automatically the moment a real admin is created. Submit the form again to set up more than one admin, or delete a row to remove one again - the list is allowed to end up empty, "Next" is what actually requires at least one account before the wizard continues.

### 6. Finish

Sets the real `/_admin` password. Make sure at least one admin account exists first (step 5) - without one, `/_editor` login won't be possible once the wizard locks itself out. This step rewrites `_admin/Admin.php`'s `PASSWORD_HASH` constant on disk (the only step that touches PHP source rather than a data file) and is what ends `/_install`'s own access for good.

Routes aren't reviewable in the wizard itself - use `_admin`'s Config module (`/_admin` → Config → `/nino/http/routes`) for that, before or after finishing here.

## Library format

`_install/library/` has three kinds of units, each a directory with a `manifest.php`:

```
_install/library/
  base/               always applied, regardless of selection
    manifest.php        routes (robots.txt/sitemap.xml/llms.txt), templates, blacklist
    templates/           html-header.tpl, html-footer.tpl, mail-header.tpl, ...
    text/global.php, text/de_DE.php, text/en_US.php
  modules/<key>/      selectable in step 2's "Modules" list
    manifest.php        moduleClass, templates (eg. mail templates), requiresModules
    templates/, text/global.php, text/<locale>.php
  pages/<key>/        selectable as a Webpages entry's "template"
    manifest.php        one route, templates, elementTypes, blacklist, requiresModules
    templates/, text/<locale>.php
```

A `manifest.php` returns a plain array, only the keys it needs:

| Key | Meaning |
|---|---|
| `label` | Shown in the picker |
| `moduleClass` | Modules only - added to `/nino/modules` when picked (a module can be template-only - nothing but its own text/mail templates - and skip this) |
| `routes` | Pages only, exactly one entry: `[ 'body' => ..., 'statusCode' => ... ]` (no `uri` - the Webpages entry using this template supplies both uris, see step 3 above). `\Nino\Http::requestRoute()` matches a route by looking up `'<METHOD>:/'.$httpUri` as a literal array key, not by scanning for a route whose own `uri` field matches - so a template can only ever occupy the single Http-URI a Webpages entry assigns it, never more than one. A page needing per-locale content picks it inside its own body instead, via the same `[[/nino/http/response/locale]]` fill html-header.tpl already uses for the page title (see `pages/legal` for an example) |
| `templates` | `'file.tpl' => locale-or-null` - copied from this unit's own `templates/` into the project's `/templates`; `null` (or a plain, unkeyed list entry) means "always", a locale code gates it the same way a route's `'locale'` does |
| `elementTypes` | Pages only. Filenames copied from the unit's own root into `/elements` |
| `blacklist` | Keys appended to `/text/blacklist.php` (see `_editor`'s Text panel / `docs/_editor.md`) |
| `requiresModules` | Other module keys auto-selected alongside this one (Setup) or this template (Webpages) |

Text fragments (`text/global.php`, `text/<locale>.php`) are plain `'[[/key]]' => 'value'` arrays, merged into the real `/text/global.php`/`/text/<locale>.php` the same shape `_admin`'s Text editor already uses. A page's own fragment should **not** declare `/webpage/<name>/*` keys - Webpages writes those itself, keyed by whichever Element-URI the entry actually picked (`/webpage<uri>/name`, `.../title`, `.../description`), not by the template's folder name or its Http-URI; anything under that prefix a fragment still ships is filtered out defensively.

### Known limitations (v1)

- **`sitemap.xml`/`llms.txt` aren't assembled from the Webpages list** - they ship with just the site root; add an entry per page by hand.
- The footer's legal link/cookie banner (`[[/website/legal/uri]]`/`[[/website/legal/name]]`) and the localepicker call assume a "legal"-templated entry and 2+ locales respectively. `[[/website/legal/uri]]` mirrors that entry's Http-URI (the real, clickable path), not its Element-URI. Those two keys are only ever *set*, never cleared - if you drop the "legal" entry from the Webpages list later, the footer link keeps pointing at its old uri until you either add a new "legal" entry or remove the block from `templates/html-footer.tpl` by hand.
- No images are provided - `<img>` tags in the shipped templates (logo, hero) reference paths you fill in yourself (or manage via `_editor`'s image slots, see `docs/_editor.md`).

## Notes

- Every step's underlying data (texts, routes, modules, admin accounts) can also be edited by hand or through `_admin` afterward - the wizard is a convenience, not the only way in.
- Nothing here requires `_admin` or `_editor` to be deleted or kept - `/_install` works alongside both, and only ever touches `_admin/Admin.php` in its very last step.
