# Changelog

All notable changes to Nino are documented in this file.

## Unreleased

### Added

- `Modules\Cache`, an optional full-page cache. A hit skips the render -
  routing, template reads, the fill and shortcode passes - and answers from a
  stored copy; a miss renders as before and stores the result. Off by default,
  switched on in `/_admin`'s Config along with its lifetime and a blacklist of
  uris (`/blog/*` covers a subtree).

  It deliberately does not serve before `\Nino\init()`. A stored page carries
  two values that belong to whoever asks for it rather than to whoever it was
  rendered for, and neither is knowable before the session is up: the `[csrf]`
  token, where serving one visitor's to everybody would leak it and 403 every
  other form submission, and the `[jstext]` nonce, which the
  `Content-Security-Policy` header whitelists for exactly one response and
  which must therefore not survive in a cache. Both are stored as markers and
  stamped with the current request's values on the way out.

  Never cached regardless of configuration: anything but a GET, anything with
  query vars, any uri under `/_` or `/.`, any response that is not a plain 200,
  and every request from a signed-in visitor. A successful write through
  `/_admin`, `/_editor`, `/_templates` or `/_install` drops the whole cache -
  seen from the outside as an ordinary request, so no tool needed a hook of its
  own. Responses carry `X-Nino-Cache: hit|miss`.
- Element types can number their own elements instead of asking for a uri.
  `/_admin`'s Element Types editor gains an **Element URIs** switch; a type with
  it on carries one extra key in its type file - `'autoincrement' => <next
  number>` - and the element form in both `/_admin` and `/_editor` stops asking
  for a slug, stating the uri it is about to create instead (`/gallery/00001`,
  zero-padded to `Elements::AUTOINCREMENT_PAD`).

  Some types have entries with no name worth putting in a url - an image in a
  gallery, a row in a price list. Asking for one anyway is how a project ends up
  with `bild-2`, `bild-2-neu`, `bild-2-final`.

  The number is allocated inside `_writeElementData()`'s existing `mutate()`, so
  the counter is read and written under the same lock as the element and two
  simultaneous inserts cannot be handed the same one. It is stored rather than
  derived from the entries that exist: deleting the newest one does not hand its
  number to the next, because a uri is a public address that ends up in links,
  sitemaps and bookmarks, and a gap in the numbering is better than silently
  repointing an old address at a different element. Switching numbering on for a
  type that was named by hand until now seeds the counter past the highest number
  it already uses - including a hand-written or imported one, which is re-checked
  on every allocation - so existing elements keep their uris and nothing can be
  overwritten. An explicit slug still works at the API level: numbering is what
  the editing tools offer, not a restriction on the kernel, so an import or a
  migration can still name an entry.
- The design system's boolean switch is a shared component,
  `Nino.adminUi.switchField()`. Config had built it inline; the Element Types
  editor needs the same control, and a second hand-rolled copy is how the written
  on/off state - the one signal a low-vision reader has besides knob position -
  gets dropped from one of them. It owns no strings, same rule as the shared
  table: `/_admin` is English and `/_editor` translates.
- A `/nino/http/output` callback, fired by `\Nino\output()` with the finished
  response. It is the only point where a module can see the bytes that are
  about to be sent - every other hook runs before `Html::response()` has
  rendered the body - and `Modules\Cache` is its first consumer.
- `/_admin`'s **Config** is a typed form instead of a list of raw json
  textareas. Booleans are switches, the two login-throttle values are bounded
  number fields, and the language list and native language are one group that
  is saved together. Every value is validated against a schema
  (`Config::FIELDS`) before anything is written, and the whole page is one
  write rather than one per key — which the two locale keys require, since a
  native language has to be one of the available ones and saving them
  separately allows a config.php that contradicts itself.
- A boolean control in the admin design system: `.nino-admin-switch` with its
  track, copy and written on/off state. The condition is stated in words next
  to the knob, since knob position is otherwise the only signal and it is the
  one a low-vision reader loses. The real `<input type=checkbox>` stays in the
  markup, clipped rather than hidden, so the label association and keyboard
  behaviour are the browser's rather than reimplemented.
- Adding a language in Config writes its `text/<locale>.php` there and then, as
  a skeleton of every key the native language has, in its order, with empty
  values — the state `/_editor`'s Text panel lists as work to do. Values stay
  empty on purpose: a key carrying the native language's own sentence reads as
  finished and is what ends up shipping. The language is deliberately left
  switched off, since a language of empty keys would otherwise be one Save away
  from serving blank pages, and an existing text file is never overwritten.
  Before this there was no way to bootstrap a locale from the interface at all —
  `Text::saveBatch()` validates against keys that already exist, so there was
  nothing to save into an empty language.
- The Config language list reports what actually exists on disk per locale:
  every `text/<locale>.php` found is offered even when `config.php` does not
  list it (a translated language that is merely switched off), and a language
  switched on without one is flagged before saving — that case renders every
  per-locale fill as a raw `[[key]]`.

- Added a shared admin data table — `Nino.adminUi.table()` plus the
  `nino-admin-table` primitives — with search across all visible columns,
  type-aware sorting and 50/100/150 paging. `/_admin`'s Elements list uses it,
  and `/_editor`'s Newsletter list is the second consumer, which is what
  surfaced the per-column `render` hook a cell holding a link or a per-row
  action needs. The component owns no strings: everything that can be a number
  or a glyph is one, so a caller supplies only `{ search, empty, noMatch }` —
  otherwise the same table would read half-English in the localized tool.
  Its pure half (`Nino.adminUi.tableModel`) is covered by
  `tests/nino-ui-table-js-smoke.js`.
- Elements' list endpoint now returns each row's displayable field values
  (`values`), the column keys it chose (`columns`) and a `total`, and accepts a
  `locale` so the table can show one translation at a time. Image, array and
  rich-text fields are left out — a cell renders text, and shipping a large
  html body to a list that would never draw it is waste. Nested under `values`
  rather than merged into the row on purpose: a model is free to name a field
  "label" or "uri", and flattening let such a field overwrite the row's own
  identity.

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
- Added the manifest-v3 named-area Template Builder. Presets can define
  semantic Areas with Design/Data views, ordered safe components,
  independent Text, Image, Template and Elements bindings, optional real Layout
  templates, Area Styles and multiple collection sources. **Articles** is the
  collection reference; **Fullscreen image** demonstrates Cover and Parallax
  Layouts. One inert versioned metadata comment preserves graphical editing,
  while HTML+ remains the explicit escape hatch.
- Refined the Template Builder workspace: Delete and Save now stay in the
  topbar, Template Settings keep Name/Header/Footer/VPA on one labeled row,
  Add Section remains the final workspace control for empty and populated
  templates, and dialog close controls use consistent SVG icons. The composer
  now presents compact horizontal Area tabs above a full-width Design/Data
  workspace, designed component rows, binding groups and SVG row actions. Flexible
  content, media split and reusable-template presets expand the focused set
  without duplicating every cosmetic combination.
- Added a section-first Template Builder for creating and composing
  `page-*.tpl` files with a searchable, visual preset library and a two-step
  Section Composer. The gallery and live configuration view render real
  generated markup with the current project stylesheet instead of a schematic.
- Added five maintained named-area references covering responsive articles,
  flexible content, fullscreen images, semantic media splits and reusable
  template insertion.
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
- Type size is a modifier of the class it changes instead of a utility laid over
  it. `ui-font-small` and `ui-font-big` are gone: both set an `em` font-size,
  which *replaces* the `rem` size a class states for itself rather than scaling
  it - so "loud" on a 1.5rem section title rendered it at 1.25 × the body size,
  visibly smaller than the title it was supposed to emphasize.

  Every typography class the Builder can put on a component now carries its own
  `--quiet` and `--loud`: `ui-section-title`, `ui-section-subtitle`,
  `ui-section-text`, `ui-atf-title`, `ui-atf-subtitle`, `ui-article-title`,
  `ui-article-descr`, `ui-pricing-title` and `ui-pricing-price`. Each states a
  `rem` size one step down or up the existing scale, and moves the line height
  with it. The compiler picks the modifier from whichever class the component
  ends up with, so the same Title component compiles to
  `ui-section-title--loud` in a content section, `ui-atf-title--loud` in a hero
  and `ui-article-title--loud` in a card - the manifest restyles the component,
  and the step follows.

  `ui-section-text` is new: the third member of the section's typography family
  next to title and subtitle, and the class the body-copy components had been
  missing since the `nino-*` cleanup took `nino-area-description` away. The
  style vocabulary is the same everywhere now - auto, quiet, loud - so `lead`
  is gone; the two presets that used it say `loud`.

  The `--fontsize-small` and `--fontsize-big` theme variables stay: tables,
  badges and article prices read them. Only the two utility classes went, along
  with their last three users - the demo testimonial byline is a `<small>`, the
  utility catalog demonstrates that instead, and the shipped home page was
  recomposed.
- Every **Auto** option in the Template Builder names the value it resolves to:
  `Auto (Dim)`, `Auto (Wide)`, `Auto (100)`. "Auto" on its own told nobody what
  the section would actually do, and the answer differs per preset and per
  Layout - the same Height select means `Auto (Off)` in an articles grid and
  `Auto (100)` in a fullscreen hero. The label resolves the way the compiler
  does, Layout recommendation before preset recommendation before fallback, and
  the two selects that already showed their default (Layout, Area style) now
  read the same way instead of using a separator of their own.

  Two client-side frame fallbacks still said `overlay: 'medium'` after the scrim
  became a single `dim`, so a section whose preset recommends nothing would have
  offered a value the compiler no longer knows. Both now say `dim`.
- The Template Builder writes the design system's classes and no longer any of
  its own. Every `nino-*` class is gone - from the compiler, from all sixteen
  presets, and from `_nino/Nino.css`, which loses its whole Template Builder
  block and 109 lines with it.

  The namespace had grown into a second design system that duplicated the first.
  `nino-area--left` was `ui-text-left` with an extra rule, `nino-split-image`
  was `ui-img-cover`, `nino-component--quiet` was `ui-opacity-70`, and
  `nino-area`, `nino-area-description` and `nino-fullscreen-stage` were hooks
  with no rule at all. Those are now the `ui-` class that already existed, or
  nothing:

  | was | is |
  |---|---|
  | `nino-area--left/center/right` | `ui-text-left/center/right` |
  | `nino-area--heading` / `--action` | `ui-mb-3` / `ui-mt-3` |
  | `nino-component--quiet` | `ui-opacity-70` |
  | `nino-component--loud` / `--lead` | `ui-font-big` |
  | `nino-component--cover`, `nino-split-image` | `ui-img-cover` |
  | `nino-article-grid-item` | `ui-article--grid` |
  | `nino-article--borderless` | `ui-article--borderless` |
  | `nino-timeline--*`, `nino-pricing-row--*`, `nino-form*` | the same modifier in its own `ui-` family |
  | `nino-area`, `nino-area-*`, `nino-fullscreen-stage*` | dropped, they styled nothing |

  What the design system genuinely lacked was added to it rather than beside it:
  `ui-grid-row--narrow/--wide` next to the row they resize, `ui-img-focus--1…9`
  next to the image helpers, `js-cover-top/-bottom` next to `js-cover-center`,
  `ui-mx-auto` next to the other margin utilities, and the three `ui-text-*`
  rules that reach into a heading which centers itself - without them
  "left" moved everything except the title. `ui-form--inline` and
  `ui-form-trap` replace the inline `style` attributes the demo markup carried.

  The scrim is one decision now instead of three: `overlay` is `none` or `dim`,
  and the class follows the image layer the background actually uses -
  `js-cover--dim`, `js-parallex--dim` or `ui-img-background--dim`, each of which
  the design system already shipped. Sections written against the old
  `soft`/`medium`/`strong` keep composing; the three levels resolve to `dim`.
  Two style options went with the cleanup, `rounded` and `narrow`, because
  neither had a `ui-` counterpart and both are one class of work in HTML+.

  The shipped `page-home.tpl` was recomposed from its own metadata, so the
  starter page ships the same markup the Builder writes today.
- Every preview card is scaled to one viewport (1200×760) instead of a height
  the manifest chose. A gallery of tiles that are all the same size compares
  presets; one of tiles in six heights compares tile heights. The
  `previewHeight` manifest key is gone.
- Five presets whose content is a finished block of markup rather than a
  composed one: **Table — Static block**, **List — Static block**, **FAQ —
  Static accordion**, **Newsletter — Signup form** and **Contact — Form**. Each
  has the same shape - an Intro area with the usual title and subtitle, the
  block itself, and an Outro area that starts empty and renders nothing until
  somebody adds a button to it.

  Not everything worth inserting is worth making editable. A table, a
  `details` accordion or a form is markup a project shapes once and then leaves
  alone; offering a graphical editor for it would mean inventing components for
  cells, questions and fields that nothing else needs. The Builder puts the
  correct house-style markup at the right position, and HTML+ - which drops the
  section's metadata and hands ownership to the source - takes it from there.

  The table, list and accordion ship each variant twice: once with demo rows to
  overwrite, once with a hand-written `[elements /example-rows limit="10"]` loop
  to point at a collection of one's own. A loop over a type that does not exist
  renders nothing, so the second variant is safe to insert first. `[[section:id]]`
  is resolved inside the block, which is what keeps the FAQ's `name` grouping
  and the forms' field IDs unique when the same preset is inserted twice on one
  page. The forms carry `[csrf]`, the honeypot and the label keys their modules
  ship, and no preset writes a `style=""` attribute any more - three `nino-*`
  classes replaced the ones the demo markup carried inline.

  Pricing grew the four-column Layouts: four equal cards, and four with one
  full-width card above or below them, read from the first or last entry of the
  collection. The process timeline stopped storing its step number: an ordered
  list is numbered by nature, so the badge is a CSS counter now and the ordinal
  lives with the markup that already carries it. **Table — Two columns**, the
  Elements-managed table from the previous entry, gave way to the static one.

  An Area with no components no longer leaves its blank line behind in the
  generated source. A deliberately empty outro is normal now, and the section is
  read by hand afterwards.
- Seven presets, so the Section Library covers the page shapes the design
  system already had markup for: **Process — Numbered steps** (connected
  timeline or stacked), **Pricing — Plan cards** (equal or middle-emphasized),
  **Table — Two columns** (striped or bordered, with editable column
  headings), **Features — Checklist and image** (image left or right),
  **Partners — Logo bar** (caption above or beside), **Call to action —
  Banner** (message above or beside the buttons) and **Banner — Text over a
  background image** (text on the image or inside a card).

  Everything repeatable reads an Elements collection rather than being typed
  into the page: steps, plans, table rows, checklist lines and logos are all
  content, edited afterwards in `/_admin`. Where a second Layout is offered it
  changes the composition - a different wrapper, a different column order, a
  head row - and never merely restyles the item, which is what Area Styles are
  for. Three of the seven honestly have only one sensible composition and say
  so by shipping one Layout.

  `AreaComposer::TAGS` grew the tags those presets need for correct markup:
  `ul`, `ol`, `li`, `tr`, `th` and `td`. They are structural and inert; nothing
  that loads, submits or scripts joined them, and `section` stays out because
  the document parser reserves it. A list or table wrapper still belongs in the
  Layout `.tpl` - only the repeated row is a manifest tag.
- A page's own Http-URI is now a textfill. `/_install`'s Webpages step and
  `/_admin`'s Routes form already wrote `/webpage<uri>/name`, `/title` and
  `/description` when they saved a page; they now write `/webpage<uri>/uri` next
  to them, valued with the path the page is actually reachable at.

  It closes a gap that had been open on both ends. The library page units still
  ship a `[[/webpage/<folder>/uri]]` in their text fragments, and templates use
  it - `page-newsletter.tpl`'s way back to the start page, the three demo pages'
  cross-links - but `_withoutWebpageMeta()` strips every `/webpage/*` key a unit
  fragment carries, and nothing wrote one back. An unresolved fill is left
  standing by `_renderFills()`, so those links rendered with the literal
  `[[/webpage/home/uri]]` as their href. The same absence was visible in the
  Template Builder, where "Existing textfill" could not offer a page uri that
  never existed.

  Written into `text/global.php` rather than per locale, because an entry has
  exactly one Http-URI for every locale, and blacklisted like `/website/url`:
  it is a route, not wording, so `/_editor`'s Text panel keeps ignoring it while
  the Builder still offers it under **Technical values**. Menus keep coming from
  `[navigation]`, which reads the routes themselves - this key is for the
  deliberate single link. It follows a page whose Http-URI changes, and like
  every other `/webpage<uri>/*` key it is never deleted on its own.
- A section's cover or parallax background can be a **fixed value**: the URL is
  typed into the composer and written straight into the section as a plain
  `<img>`, with no image slot created and nothing to fill in `/_admin`
  afterwards. The two existing choices - generate a slot, or point at one that
  exists - are unchanged and now sit next to it in the same control.

  It is for the image a project already ships: a theme photo, something copied
  in by an installer package, a file on a CDN. Creating a managed slot for one
  of those means an editor screen that exists only to hold a value nobody will
  ever change. A fixed value may start with `[[/nino/public]]` or
  `[[/nino/dir]]` so the path survives a sub-directory install, and is otherwise
  an ordinary relative path or an `https` URL; quotes, spaces, angle brackets,
  traversal, protocol-relative addresses, other shortcode brackets and every
  scheme but `http`/`https` are refused rather than escaped into something that
  looks like it works.

  Which of the three a section uses is now stored as
  `frame.backgroundImageSource` in the section metadata, because a key alone
  cannot tell a generated slot from a chosen one from a literal path. Sections
  written before that field keep inferring their original behavior.
- Section presets can declare the `data-*` attributes of the elements they
  generate. `Nino.ui.js` takes its parameters from exactly there -
  `ui-autoheight` equalizes the cards of one `data-autoheight-group`,
  `js-slider` reads `data-slider-width`, `js-vpa` reads `data-vpa-delay` - so a
  preset could put the class on a card but never the value that makes it do
  anything, and the whole behavior stayed a manual HTML+ edit after every
  insert.

  A `data` map is accepted next to the existing `tag`/`class` keys and belongs
  to one generated element each: at the top level the `<section>`, inside a
  Layout the same section (overriding the preset default per name), in an Area's
  `container` or `item` the wrapper or one repetition, and in `render.<type>`
  every component of that type. Names are written without the `data-` prefix and
  are lowercase slugs; values are single-line literals of at most 240 characters
  and are escaped for the attribute context. `[[section:id]]` is substituted, so
  `'autoheight-group' => 'services-[[section:id]]'` keeps two copies of one
  preset on the same page from equalizing against each other.
  `data-cover-height` is written by the frame itself and stays reserved.

  The manifest is the only source. Nothing is read from the request: the
  composer has no data-* control, a `data` key smuggled into posted section
  metadata is ignored, and anything past a configured attribute is still an
  HTML+ decision rather than a second Advanced panel.

### Changed

- The Template Builder's Data view puts one binding on one line. A property's
  source and the value it feeds were two full-width fields stacked on top of
  each other, so a button with a label and an address read as four unrelated
  controls in a column; they are now one row each, source left and value right.
  A link's target moved with them: it was a two-option select sitting above
  fields it says nothing about (and a second copy of itself in the Design view),
  and is now the design system's boolean switch - "Target _blank" - at the end
  of the component, after the address it applies to.
- The Articles preset equalizes the title and the description of its cards per
  section, so the calls to action below them line up. A card is a direct flex
  child of the grid row and was already stretched to the height of its row line;
  what was not aligned is everything *inside* it, because a one-line and a
  two-line title push the text below them to different heights. Both boxes now
  carry `ui-autoheight` with a group of their own
  (`nino-article-title-<section id>`, `nino-article-descr-<section id>`), which
  is the one thing the flex row cannot do by itself. The group carries the
  section ID, so two Articles grids on one page do not equalize against each
  other, and `data-autoheight-mobile` opts out where the cards stack anyway.
  Without `Nino.ui.js` - and in the Builder's script-free preview - the markup
  behaves exactly as before.
- Split the Template Builder's Section Composer by intent. **Add Section** now
  keeps only the initial ID/Layout/Background choices and combines component
  ordering with Data bindings; **Edit Section** retains frame spacing and the
  separate Design/Data fine-tuning views. Component properties can now choose
  generated/collection data, an existing shared textfill, or a safe fixed
  value as appropriate. Authenticated binding selects include blacklisted
  route and other technical textfills in their own group without rewriting
  them on insert.
- `private/config.php` ships `/nino/editor/backups` and `/nino/editor/logs`
  instead of `/nino/admin/backups` and `/nino/admin/logs`. The keys `_editor`
  actually reads have always been the `/nino/editor/*` pair
  (`Backup::maybeRun()`, `Logs::record()`), so the shipped `false` never took
  effect: the key those two look for was absent and their `?? true` default
  won, which means the daily backup and the audit trail have been running in
  every installation regardless of what config.php said. Both now ship as
  `true`, which is what every project has actually been doing — no behaviour
  changes, the file simply stops contradicting the code. An existing project
  still holding a `/nino/admin/*` key has its value read by Config's panel, so
  what its owner intended is what the form shows; the stale key is left in
  place rather than deleted.
- Config no longer edits `/nino/http/routes`, `/nino/html/navs` or
  `/nino/html/assets`. The first two have had dedicated editors for a while
  (Routes, Navigations) and a second, unvalidated way to write the same data is
  a way to corrupt it. Asset bundles stay a deliberate config.php edit because
  their order is load-bearing for the css cascade, which a form does not show.

- Reduced the Template Builder library to explicit manifest-v3 presets. The
  Classic switch and all bundled version-1 recipes are gone; manifests with a
  missing or different version are ignored instead of opening a second editing
  model.
- Reworked the complete `pd-config-pane` hierarchy into consistent step
  panels, numbered Area tabs, Design/Data controls, component stacks,
  collection-source panels and labeled binding groups. These rules now live in
  the normal `nino.tool` layer, so the shared admin baseline remains easy to
  refine without emergency specificity.
- Made the locale switch a design-system component. `/_admin`, `/_editor` and
  `/_install` each carried their own id rule for it, two of them byte-identical,
  so the one control that says "this is the translation you are looking at"
  drifted per tool. It is `.nino-admin-locale-select` now, with placement kept
  separate from the role: `--corner` pins it into a fieldset's corner (Personal
  Infos), `.nino-admin-contextbar-select` puts it in the pinned toolbar.
- Moved the select indicator into the design system. It was painted by a bare
  `select` rule in `/_editor`'s stylesheet, which - since `/_admin` and
  `/_templates` load that file - was quietly the baseline for three tools.

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
  `@layer nino.system, nino.tool, nino.local;`. Shared foundations remain a
  low-specificity baseline, normal tool components can refine them without
  selector escalation, and `nino.local` is reserved for deliberate final
  overrides. Documented in AGENTS.md, and enforced by
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

- A bound alternative text left its own shortcode's tail standing in the
  Builder's preview: `Media / Text — Flexible split` renders
  `[image /page-…/image alt="[[/page-…/image-alt]]"]`, and the preview matched
  an image shortcode by reading up to the first `]` - which is the one that
  closes the fill inside `alt`, not the one that closes the shortcode. The
  replacement stopped there and a literal `]"]` was left behind in the frame.
  Shortcode arguments are now matched with their quoted values consumed whole,
  for `[image]`, `[elements]` and the leftover sweep alike, so a `]` inside a
  fill or a query argument no longer cuts a match short.
- The Template Builder ignored the background image slot a section actually
  chose. "Existing image slot" was offered, stored in the draft and shown in the
  composer, but the compiler never read `frame.backgroundImage` back out of the
  posted metadata - every cover and parallax section was compiled against a
  freshly generated `/page-<page>/<section>/background` key instead, and the
  chosen slot was silently dropped on insert.
- Fixed named-area composer controls rendering with the shared default style:
  all dialogs now remain inside `#pd-app`, which is the scope their component
  rules and design tokens use.
- Fixed Template Builder previews requesting `/.cache/style.css` from servers
  that answer public dot-directory URLs with HTML. The authenticated library
  response now refreshes the configured asset bundle, carries its generated CSS
  and lets each inert `srcdoc` embed it; preview markup is stripped of scripts
  and inline handlers, CSP still denies active content, and the opaque sandbox
  avoids one browser-extension warning per preview frame.
- Fixed vertical Area navigation narrowing and clipping the Design/Data body in
  the Template Builder's already narrow configuration pane. Areas now use one
  compact, horizontally scrolling tab row above the full-width editor body.
- Fixed long options running underneath a select's chevron. The indicator lived
  in `/_editor`'s stylesheet while the padding that keeps text off it came from
  `Nino.admin.css`, and once the shared file won by layer its symmetric
  `.62rem` replaced the roomier `.8rem` the indicator needed. Both halves are
  one rule now, sized from a single `--nino-admin-select-indicator` token, and
  `select` is deliberately out of the shared input group - `:is()` takes its
  most specific argument, so that group weighs (0,1,1) and would outrank a
  plain `select` rule.
- Fixed the last `/_admin` form regressions after the shared-style refactor:
  Config fieldsets no longer apply card padding a second time over the space
  reserved for their legends, the language input and its Add button stay in one
  in-flow row instead of borrowing the fixed list-action role, and ordinary
  field labels once again occupy their own line above their controls.

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
