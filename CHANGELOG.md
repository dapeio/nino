# Changelog

All notable changes to Nino are documented in this file.

## Unreleased

### Added

- Added an `element` model field type: a reference from one element to another.
  Defining the field asks which element type it may point at; editing an
  element then offers that type's entries as a select. The stored value is the
  referenced element's full uri (`/<type>/<slug>`) — what
  `\Nino\Elements::getElement()` already takes, so nothing has to re-join it
  with the model. The kernel rejects a value pointing outside the declared
  type, and `/_admin` refuses to save a reference to a type that does not
  exist rather than leaving an unusable, permanently empty select behind. A
  referenced element deleted later keeps its reference, shown as *missing* in
  both element forms. References stay out of Translations exports: a uri is a
  choice, not translatable text.
- Added a `private/` directory for this project's own state, reached through
  `\Nino\Filesystem::getContentPath()` and movable with `NINO_CONTENT_DIR`
  (the generalisation of `NINO_CONFIG_DIR`). It ships an Apache deny rule,
  and every file inside carries a 403 stub, so it stays unreadable even where
  that rule does not apply. Paths under it are addressed by the virtual
  prefix `\Nino\Filesystem::CONTENT_DIR`, so `mutate()` and its locking work
  there unchanged and no call site knows where the directory really is.
- Added a **Navigations** area to `/_admin`: create, rename, and delete the
  menus registered in `/nino/html/navs`, and set each menu's whole running
  order with ↑/↓, a remove button, and a picker that adds any `GET` route at
  the end. Priorities stay dense (`1..n` per menu), a rename follows the key
  into the registry and onto every member route, and a delete takes the
  membership off every route in it. Templates are deliberately left alone —
  their `[navigation nav="…"]` argument is yours to update.
- Added a section-first Template Builder for creating and composing
  `page-*.tpl` files with a searchable, visual preset library and a two-step
  Section Composer. The gallery and live configuration view render real
  generated markup with the current project stylesheet instead of a schematic.
- Added 43 curated presets covering heroes, introductions, article and offer
  grids, media/text splits, testimonials, profiles, stats, feature layouts,
  check and numbered lists, tabs, accordions, pricing, comparison and data
  tables, forms, badges, logos, galleries, inline and lightbox video, media
  sliders, notices, timelines, image banners, and calls to action.
- Added first-class `[template]` canvas sections. Standalone includes can be
  inserted, replaced, duplicated, and reordered. Header and footer remain
  ordinary `[template]` shortcodes but are selected through fixed Template
  Settings; inert markers let the builder hide them from the content canvas,
  and exact legacy `html-header`/`html-footer` frames migrate automatically.
- Added the CTA variant **primary button + outline button**, backed by four
  native textfills for the two labels and destinations.
- Added direct native-locale textfill editing inside the configuration step,
  Elements collection selection, recommended Element Type and image-slot
  creation, plus Admin deep links within the section workflow.
- Added page-level viewport-motion defaults, section reordering, duplication,
  removal, sandboxed real-markup previews, and an explicit HTML+ source escape
  hatch.
- Added explicit page-template metadata for a human display name and persistent
  VPA default, richer new-template settings (filename, name, header, footer,
  VPA), and revision-checked template deletion.
- Added starter wording to the **Webpages** step of `/_install`: a new page
  now prefills its HTTP URI and its name, title, and description in every
  active language from the selected library template, instead of leaving
  every language nobody typed by hand on the generic placeholder.
- Added a pinned form toolbar to `/_admin` carrying the "Back to list" link
  and the language switch, so both stay reachable inside long forms.
- Added a versioned **Translations** workflow to `/_admin`: one JSON export
  combines native, public Text values with localized Element fields and can
  be safely merged into any configured target locale. Global/technical data,
  images, structure, and unknown import paths stay untouched.
- Added inline `<code>` formatting to the shared rich-text editor and its
  server-side HTML sanitizer.

### Changed

- Reworked `_nino/Nino.admin.css` into an actual design system for the four tool
  frontends. It carried 68 id selectors and named the same concept up to five
  ways (`.editor-danger`, `.admin-danger-btn`, `.pd-danger-button`, `button.red`,
  `.newsletter-entry-delete` were one button), so nothing in it could be reused
  by a new screen without copying a tool's ids along. It is now class-only,
  namespaced `nino-admin-*`, scoped to a `.nino-admin` root, and half the size
  (1482 -> 775 lines); the tool-specific blocks moved into the stylesheet of the
  tool that owns them. `/_admin`, `/_editor`, `/_install` and `/_templates` use
  the shared classes throughout.
- Ordered the admin stylesheets into cascade layers: each tool declares
  `@layer nino.tool, nino.system, nino.local;` and wraps its own rules in
  `nino.tool`, so a tool's id rule can no longer silently outrank a
  design-system class, and a deliberate override says so in its own
  `nino.local` section. Documented in AGENTS.md, and enforced by
  `tests/admin-lists-js-smoke.js`.
- Renamed `/_install`'s "New Webpage" action to "New Route", matching the step
  itself.
- Changed the template a new route starts on from whichever library unit sorted
  first - which was "404", so a new route began life as an error page, status
  code and all - to "Blank".
- The shipped `/contact` route now sits in the footer menu as well as the main
  one. The footer nav is registered and rendered out of the box, and a contact
  link is what a footer menu is for.

- Renamed `/_install`'s fourth step from **Webpages** to **Routes**, throughout
  its wizard label, its controls and its messages. What the step writes are
  routes — an Element URI, a public HTTP URI and a template — and calling them
  pages made the one field that is not a page (the HTTP URI) read like an
  afterthought.
- Changed the **Blank** page template into a per-route starting point: a route
  picking it gets its own copy, named after its Element URI (a `/team` route
  becomes `templates/page-team.tpl`, rendered by
  `[template /templates/page-team]`). Every blank route used to share the single
  `page-blank.tpl`, so building a second blank page silently rewrote the first.
  An existing file is never overwritten, so re-running the step leaves work
  already done in such a page alone. Opt-in per library unit through the
  manifest's `templatePerRoute` — a finished unit (home, contact, legal, …) is a
  one-off and keeps sharing its template.
- Added **Save and back** and **Save and new** next to Save in `/_admin`'s
  element editor. Both act only on a completed save, so a rejected one leaves
  the form where it is instead of navigating away from values that never
  landed. Desktop only: the fixed bottom bar also carries Delete and the status
  message, and both buttons are shortcuts for something Save plus one click
  already does.
- Changed the element list's row label to the element's `title` field, falling
  back to its own uri. It used to try `title`, then `label`, then `name`, then
  the first string field in model order — so two types with the same content
  labelled their rows differently, and reordering a model silently relabelled
  every row in it.
- Moved the project's public half into `public/`: `images/`, `assets/`,
  `favicon/`, `fonts/` and the generated `.cache/` no longer sit beside the
  code that runs the site. Urls gain a `/public` segment, built from the new
  `[[/nino/public]]` fill (or `\Nino\Filesystem::url()`) rather than from
  `[[/nino/dir]]` by hand — a tool's own bundle, like `/_editor/.cache/`,
  still resolves next to the tool. Image shortcodes and Admin's template
  scanners use the same public prefix. The document root does not change, so
  no deployment has to be reconfigured.
- Moved the project's private half into `private/`: `config.php`, `templates/`,
  `text/`, `elements/` and `data/` now sit beside the state the management
  tools already kept there, leaving the public root holding only what a
  webserver should serve. Until now a request for `templates/page-home.tpl`
  returned the template source as plain text — the shipped `.htaccess` only
  blocked dotfiles, and `.tpl` is not a PHP extension. `private/` denies
  itself, `router.php` refuses it outright, and `NINO_CONTENT_DIR` moves it
  out of the webroot entirely for a setup that cannot rely on either. Nino
  uses this layout exclusively and does not detect alternative project
  directory structures during a request.
- Separated the project's public root from its private one in the kernel.
  `\Nino\Filesystem::getPath()` remains the project/code root, while
  `getPublicPath()` identifies the browser-facing root (images, assets and
  the generated cache). Everything a webserver must never serve —
  `config.php`, `templates/`, `text/`, `elements/`, `data/`, listed in
  `Filesystem::PRIVATE_DIRS` — resolves through `Filesystem::path()` against
  the private root. No call site concatenates a private path onto the public
  root.
- Moved the `/_admin` password hash out of `_admin/Admin.php` into
  `private/.auth/pw.php`. `/_install` no longer rewrites PHP source, so the
  tool folders are pure code again and an update may replace them wholesale.
  Previously, copying a new `_admin/Admin.php` over the old one restored the
  shipped placeholder hash — which logged the operator out **and** re-opened
  `/_install` on a live site, because that placeholder was exactly what the
  installer's lock was reading. The source-file hash and its migration path
  are gone; only the private credential file is read.
- Moved the `/_admin` login throttle to `private/.auth/lockout.json` and the
  `/_editor` activity log to `private/.logs/`. Both used to live inside the
  tool folders, which is what made those folders un-replaceable. Nino now
  reads and writes these exclusively under `private/`.
- Moved the encrypted backup archives to `private/.backups/` and the archive
  key's out-of-config copy to `private/.auth/backup-key.php`. With that,
  `_admin/` and `_editor/` hold no project state at all. Archives and keys are
  read exclusively from their private locations.
- Removed the generated `/nino/backup/dir` and `/nino/logs/dir` keys. Neither
  the archives nor the activity log need an unguessable directory name any
  more: under the private directory both are denied by that directory's own
  rule, on top of the 403 stub their files always carried.
- Changed the `/_install` lock to a persisted `/nino/install/completed`
  marker alongside the stored password, so deleting the password file locks
  `/_admin` instead of handing the installer back. The hash is deliberately
  not in `config.php`: a Restore rewrites that file, and the credential that
  authorises restoring has to survive it. It is not part of the backup
  manifest either — a backup recovers content, not access.
- Replaced the DOM-oriented `/_templates` builder with the section-first
  Template Builder under the same route and removed the obsolete block
  library and nested-DOM editing client.
- Moved reusable `[template]` insertion into the visual **Add section** gallery,
  and aligned the Template Builder brand/back navigation with `/_admin`.
- Reserved `/_templates` as a runtime-owned route in `/_install` and
  `/_admin` page management.
- Changed `/_admin`'s save row to stay anchored at the bottom of the
  viewport while a form is open, matching `/_install`'s Back/Next bar.
- Changed `/_admin`'s form density from 768px up: fields, buttons, and
  fieldsets are more compact, and a text key's name, value, and flags share
  two rows instead of three. Below 768px the layout is unchanged.
- Unified `/_admin`'s drill-down lists into the compact grouped-row design
  used by Config. Element Types, Elements, Text, Routes, Images, and Config now
  share the same right-arrow affordance; Text's template scan sits above its
  list.
- Renamed the visible `/_admin` **Pages** area to **Routes** while preserving
  its internal API and storage identifiers, and moved the site-wide JSON
  translation workflow out of `/_editor` into `/_admin`.
- Removed the `/nino/install/webpages` config key. `/_install`'s **Webpages**
  step and `/_admin`'s **Routes** area no longer keep a page list beside the
  routes: both derive the list they show from `/nino/http/routes` plus the
  `/webpage<uri>/*` text keys on every request, and both write only those.
  A page route hand-written in `config.php` is therefore a first-class entry
  in both tools, and neither tool can drift against the routes it renders.
- Changed menu membership to be numbered densely. A page joins a navigation
  at its own position in the routes list (`'navs' => [ 'main' => 1, ... ]`)
  instead of at a fixed default priority, and a membership added in
  `/_admin` starts behind everything already in that menu.

### Fixed

- Fixed `/_install`'s "New Route" button carrying a stray `margin-top`. It used
  `/_editor`'s list-button class, which is a full-width block under a list
  there, so in the wizard's fixed bottom bar it sat pushed down and stretched.
  The class is now a role (`.nino-admin-btn-primary`) with no layout of its own.
- Fixed the tool shells dropping their design-system classes whenever a panel
  was switched: all three assigned `className` outright to set `show-<panel>`,
  which deleted `.nino-admin` and `.nino-admin-shell` along with it. They go
  through `Nino.adminUi.setStateClass()` now.

- Fixed `/_admin`'s and `/_editor`'s element type overview keeping the element
  count it had on page load. Creating or deleting an element and going back to
  the overview left a type that visibly has entries in it reading `(0)`: the
  list is rendered once at load, and going back only unhid it.
- Fixed "Back to list" in `/_install`'s Routes step discarding the open form.
  The wizard's own Back/Next already folded that entry into the list, so the
  same gesture kept an edit or threw it away depending on which control you
  reached for — and nothing on this step is written to disk before "Next"
  anyway, so there was no save to weigh it against.
- Fixed `/_admin`'s Element Types editor dropping a field's **max. characters**
  and **unit/suffix** on save. Both controls were offered, filled in and read
  back off the row, and the server has always accepted them — they simply never
  made it into the posted model, so setting either did nothing at all.
- Fixed backups omitting nested Element image paths by including the complete
  public image tree instead of top-level files only.
- Fixed failed or unserializable file writes becoming visible through the
  in-request cache even though those values were never persisted.
- Fixed Element type files acquiring double-slash cache/lock aliases, type
  creation hiding write failures, a reset-to-default leaving an old override,
  and partial callback-driven URI renames discarding omitted fields.
- Hardened route/query and locale handling against malformed or array-shaped
  request values, including non-string locale values stored in a session.
- Removed the requirement that the complete project/code root remain writable
  during normal operation and now validate explicit private/config overrides.
- Kept Template Builder gallery previews on the project origin so locally
  hosted fonts can load inside their script-disabled sandbox, matching the
  already sandboxed detail preview.
- Removed viewport-animation classes from Template Builder preview HTML. The
  sandbox intentionally runs without scripts, so `js-vpa` otherwise kept
  motion-enabled sections permanently transparent.
- Fixed Elements image markup generated by the Template Builder pointing to
  `/uploads`; Nino stores and serves those files from `/images`.
- Kept native vertical page scrolling and pinch zoom available when a
  touch-enabled slider fills the mobile viewport, while preserving horizontal
  swipe navigation.
- Restored explicit drag-selection and a text cursor inside the shared
  contenteditable rich-text field, including formatted inline descendants.
- Fixed `/_admin`'s **Elements** module showing an outdated schema after a
  type was saved in **Element Types**. It read every type's model once per
  page load, so an added, renamed, or removed field only appeared after a
  full page reload; saving a type now invalidates that cache.
- Fixed an image field marked as required making its element type impossible
  to add an element to. The file is uploaded only once the element exists, so
  the field is empty on the very save that would have to satisfy it.
  **Element Types** no longer offers the flag for image fields and drops it
  when saving a model, and both editors ignore it on one.
- Fixed the image preview in `/_admin` and `/_editor` pointing at a
  `/uploads` directory. Uploaded images are stored under `/images`, so a form
  re-rendered from a stored filename showed a broken preview — only the one
  shown right after an upload, which uses the server's own url, was correct.

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

- Added `/_templates`, an optional graphical structure editor for
  `page-*.tpl` and `section-*.tpl` files.
- Added a manifest-driven block library covering the Nino.css component
  catalogue.
- Added structural block selection, insertion, reordering, duplication,
  removal, and responsive settings to the Template Builder.
- Added support for editing CSS classes, attributes, boolean attributes,
  HTML tags, and text through block manifests.
- Added structural visualization of nested blocks, grids, spacing, and
  responsive properties.
- Added the **Themes** step to `/_install`, including self-contained theme
  library units with previews, stylesheets, assets, and fonts.
- Added page management to `/_admin`.
- Added complete management of element types, elements, multilingual texts,
  image slots, pages, routes, users, configuration, and restoration to
  `/_admin`.
- Added English and German documentation for:
  - concepts and architecture;
  - development;
  - installation;
  - administration;
  - editorial content management;
  - template editing;
  - deployment.
- Added screenshots for the frontend, `/_install`, `/_admin`, `/_editor`,
  and `/_templates`.
- Added language links between all German and English manuals.

### Changed

- Renamed the former `/_dev` developer area to `/_admin`.
- Renamed the former `/_admin` content backend to `/_editor`.
- Updated directories, routes, namespaces, sessions, JavaScript namespaces,
  CSS identifiers, configuration keys, documentation, and tests to use the
  new names.
- Changed fresh checkouts to require `/_install` before the website can run.
- Changed `/_install` from six to seven steps:
  1. Environment
  2. Setup
  3. Themes
  4. Webpages
  5. Personal information
  6. Admins
  7. Finish
- Changed project directories such as `templates/`, `text/`, `elements/`,
  `images/`, and `assets/` to be created and populated by `/_install`
  instead of being included as an already configured project.
- Changed themes from loose stylesheets to self-contained installation
  library units.
- Changed theme selection from a setup field to a dedicated visual step.
- Changed `/_admin` from a collection of technical configuration tools to
  complete technical and content project management.
- Kept `/_editor` focused on permission-controlled day-to-day content
  maintenance.
- Changed the shared webpage model used by `/_install` and `/_admin` to
  distinguish:
  - the installation library key;
  - the template file;
  - the internal page URI;
  - the public HTTP URI;
  - the response status;
  - the rendered route body.
- Changed the Template Builder to derive block identity and settings from
  ordinary HTML tags and CSS classes.
- Changed Template Builder inserts to use the same server-side parser as
  existing template documents.
- Changed responsive block settings to appear below their base setting
  instead of as unrelated fields.
- Changed article modifiers from mutually exclusive settings to independent
  toggles where Nino.css allows combinations.
- Changed boolean HTML attributes such as `required`, `open`, `multiple`,
  `controls`, and `allowfullscreen` to use the dedicated `attrtoggle`
  setting type.
- Standardized documentation structure, metadata, navigation, terminology,
  language links, and maturity labels.
- Documented `/_templates` consistently as Alpha while the overall project
  remains Beta.

### Fixed

- Fixed `/nino/install/webpages` being interpreted differently by
  `/_install` and `/_admin`.
- Fixed pages created during installation appearing as unknown templates in
  `/_admin`.
- Fixed saving the shipped 404 page resetting its response status to `200`.
- Fixed saving localized pages replacing their locale-dependent template
  expression with a single locale.
- Fixed custom route bodies being overwritten when they cannot be expressed
  by the template selector.
- Fixed manually added routes being removed when installation selections
  were applied again.
- Fixed account creation and account updates failing because a temporary
  value was passed to a by-reference callback parameter.
- Fixed theme stylesheets referencing a font directory that did not exist.
- Removed unused font faces from the shared installation content.
- Fixed edited CSS classes being removed and appended again, which changed
  their original order.
- Fixed the first child inserted into an empty template element receiving
  incorrect indentation.
- Fixed removed template blocks leaving unnecessary blank lines behind.
- Fixed generic button definitions conflicting with specialized modal and
  toast controls.
- Fixed Template Builder block matching where a generic definition could
  override a more specific component.
- Fixed template inserts deriving indentation solely from nesting depth
  instead of the surrounding source formatting.

### Security

- The Template Builder writes only supported `page-*.tpl` and
  `section-*.tpl` files.
- Header, footer, email, and other technical templates remain read-only in
  the Template Builder.
- Template writes reject unsupported HTML tags.
- Template writes reject event-handler attributes such as `onclick` and
  other `on*` attributes.
- Empty template trees cannot be written.
- Template changes are written atomically.
- `/_templates` reuses the protected `/_admin` session and password instead
  of introducing a separate authentication mechanism.
- Unknown markup remains visible and is preserved instead of being silently
  removed.
- Existing templates are accepted for editing only when a byte-exact
  parse-and-serialize round trip can be guaranteed.
- The Template Builder stores no proprietary sidecar files or builder
  attributes in project templates.
- Reducing third-party runtime dependencies and avoiding an open plugin
  system continues to limit supply-chain and update risks.

### Tests

- Added `tests/templates-smoke.php`.
- Added the dependency-free `tests/templates-js-smoke.js` suite.
- Added tests for the complete Template Builder block catalogue.
- Added tests for block manifest parsing and validation.
- Added tests for byte-exact template round trips.
- Added tests for structural insert, move, duplicate, and remove actions.
- Added tests for responsive settings.
- Added tests for `attrtoggle` and bare boolean attributes.
- Added tests for block matching specificity.
- Added tests for theme installation units and their assets.
- Expanded installation smoke tests for the new Themes step.
- Expanded administration smoke tests for pages, elements, texts, and
  shared installation data.

### Technical details

- `/_templates` uses one directory per block under
  `_templates/library/<key>`.
- Each block consists of a manifest and, where needed, a `block.tpl`
  containing its initial markup.
- Block manifests define:
  - matching rules;
  - available actions;
  - editable settings;
  - palette visibility.
- The block library contains 76 definitions, including:
  - grids and columns;
  - tables;
  - pricing cards;
  - accordions;
  - galleries;
  - tabs;
  - modals;
  - sliders;
  - timelines;
  - badges;
  - alerts;
  - breadcrumbs;
  - content lists;
  - pagination;
  - logo strips;
  - feature lists;
  - video embeds and posters;
  - image backgrounds;
  - form controls;
  - counters;
  - toast triggers.
- Structural helper blocks remain recognizable and editable in the canvas
  while staying hidden from the top-level palette.
- The Template Builder supports:
  - `classenum`;
  - `classgroup`;
  - `classtoggle`;
  - `attr`;
  - `attrtoggle`;
  - `tag`;
  - `text`.
- Unknown HTML remains represented as a structural block and survives
  saving unchanged.
- Elements targeted by project CSS through an ID selector are marked because
  the canvas cannot fully reproduce that part of the CSS cascade.
- Templates retain their original:
  - attribute order;
  - whitespace;
  - comments;
  - indentation;
  - entity spelling.
- Opening and saving an unchanged template therefore remains a no-op.
- Template changes remain normal readable HTML+ and can still be edited
  directly.
- Theme units live below `_install/library/themes/` and contain their own:
  - manifest;
  - stylesheet;
  - preview;
  - referenced fonts;
  - additional declared assets.
- Applying a theme replaces its entry in the configured CSS bundle without
  changing the position of the stylesheet in the cascade.
- Existing bundle entries outside the selected theme remain untouched.
- The selected theme is persisted under `/nino/install/theme`.
- Additional themes can be added as new library directories without changing
  the installer code.
- `/_install` replaces selected locales, modules, pages, and the theme with
  the submitted configuration while preserving manually defined routes
  outside the installation library.
- Templates and text content are added but are not automatically deleted by
  a later installation pass.
- `/_admin` and `/_install` now share the persisted webpage list instead of
  maintaining separate models.
- Page ordering in `/_admin` also determines the generated main navigation
  order.

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
