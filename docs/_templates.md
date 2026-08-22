# `/_templates` — Template Builder (Alpha)

**Language:** English · [Deutsch](_templates.de.md)

**Status:** 22 August 2026 · **Nino version:** Unreleased

The Template Builder is the fast path from a `page-*.tpl` file to a filled page. It treats a template as an ordered sequence of complete HTML sections and reusable `[template]` sections instead of exposing every nested DOM node.

[README](../README.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [Developer Manual](development.md)

> **Alpha:** Page files remain ordinary HTML+ and therefore do not depend on the tool at runtime. The preset library and composer workflow can still change.

## Purpose and scope

Use `/_templates` to:

- open existing `templates/page-*.tpl` files;
- create a new `page-*.tpl` with its real filename, display name, header, footer and VPA default;
- choose a complete section by appearance from a searchable, tagged visual library;
- insert reusable `[template]` shortcodes as ordered components where a preset permits them;
- select the page’s header and footer from compatible non-page `.tpl` files, or set either slot to **None**;
- assign a stable section ID;
- configure the Section frame and each preset-defined Area's ordered components, Style and Data bindings;
- reorder, duplicate or remove every content item while the header and footer remain fixed outside the canvas;
- fill the generated textfills in the native locale;
- select an existing Elements collection or create the module’s recommended Element Type;
- edit one section as HTML+ when the composer is intentionally not enough.

The Template Builder does not create routes, edit the contents of included header/footer files, translate every language or manage individual Elements entries. Its sandboxed previews use the current project stylesheet and deterministic fixture content, but do not run the final page’s JavaScript. Use `/_admin`, the frontend and code for those tasks. The former DOM-oriented builder has been replaced by this section-first workflow.

## Access and security

Open `https://your-domain.example/_templates`. The tool shares password, lock state and session with `/_admin`. Its top rail carries the common **Admin / Builder / Theme** bridge; only complete tool directories in the current delivery appear, and **Builder** marks the current surface. It depends on `_admin/Admin.php`; removing `/_admin` also removes its authentication backend.

The tool writes to:

- `templates/page-*.tpl` through **Save template**, **New page template** or **Delete**; deleted templates cannot be restored inside the builder;
- `text/<native-locale>.php` when native content is saved;
- `elements/<type>.php` when automatic Element Type creation is confirmed;
- the project configuration when a missing image-slot definition is created automatically. Image uploads themselves remain in `/_admin`.

Use HTTPS, keep the technical password private and work from a recoverable project state. Remove developer tools from production delivery when they are not required there.

## Main workflow

1. Select a page template in the left rail or create one with **New page template**. The dialog asks for the complete filename, display name, header, footer and VPA default.
2. Choose **Add section**.
3. In **Choose**, search or filter the fullscreen gallery and select a named-area preset from its real-markup preview. The library intentionally contains only the current version-3 contract. Reusable `.tpl` files do not appear as pseudo-sections; a supporting preset can expose them as a **Template** component inside one of its Areas.
4. Continue to **Configure & fill** and give the section a meaningful ID such as `main-hero` or `services-overview`. This deliberately reduced Add view contains Structure, Background, collection choice and one combined component/data list.
5. Add, reorder or remove components directly beside their initial bindings, then compare the live preview and insert the section. Recommended text keys, an Element Type and image-slot definitions can be created in the same operation.
6. After checking the real frontend, reopen the section with **Edit** when visual fine-tuning is needed. Edit exposes dimensions and spacing plus each Area's separate **Design** and **Data** views.
7. Open image slots or individual Elements entries in `/_admin` when needed.
8. Reorder the HTML and template-section cards and save the page template.
9. Complete translations afterwards through the existing JSON batch workflow or `/_admin`.

Native quick fill creates new keys in the project’s native locale and changes only that locale for per-language keys. A pre-existing global key deliberately remains global. Existing translated buckets are never cleared or replaced.

## Page and section settings

**Name**, **Header**, **Footer** and **VPA** share one labeled Template Settings row. **Delete** and **Save template** stay together at the right of the topbar; **Add section** remains in the document toolbar even after content has been inserted. The header/footer selects show real `.tpl` filenames, list non-page templates known to the project and also offer **None**. The selected value is still written as an ordinary `[template /templates/<name>]` shortcode; the controls only prevent shell includes from being mistaken for movable page content. **Delete** removes exactly the loaded file revision after explicit confirmation; recovery requires version control or another external backup.

**VPA** at template level supplies the default for sections whose motion is set to **Page**. Changing it recomposes managed sections, updates their `nino-vpa` class and remains persisted even while a template is still empty. **On** or **Off** on an individual section overrides that default.

Add and Edit intentionally expose different depths of the same version-3 metadata:

| View | Controls |
|---|---|
| Add Section | ID, Layout, Background, optional background-image settings, collection source, component order and initial data bindings |
| Edit Section → Section | ID, Layout, height, width, spacing, Background and optional background-image settings |
| Edit Section → Area / Design | visual Area Style plus the ordered component stack and Component Styles |
| Edit Section → Area / Data | native Text/Image/Template bindings or an Elements collection with explicit field mappings |

Add omits Section height/width/margin/padding, Area Style and Component Style. It is meant to produce a useful first version before the page is judged in its real frontend. Edit keeps the complete graphical fine-tuning model; both views compile the same metadata. Each manifest decides which Areas, components, Styles and Layouts are compatible. HTML+ remains the explicit route for arbitrary source changes.

## Source safety and the HTML+ escape hatch

On load, the backend scans top-level `<section>` elements without serializing the surrounding source. An existing standalone `[template /templates/<name>]` line outside a section remains a first-class canvas card; new reusable includes are chosen through a preset's Template component. Marked header/footer shortcodes become fixed settings slots instead. Other source is returned as locked raw segments. On save:

- every raw segment must still be byte-identical;
- every editable HTML segment must contain exactly one complete top-level section;
- every template segment must contain exactly one valid standalone `[template]` shortcode;
- exactly one header and one footer marker must wrap all canvas components; either marker may intentionally have no shortcode for **None**;
- duplicate non-empty section IDs are rejected;
- an optimistic SHA-256 revision prevents overwriting an external edit;
- the final file is replaced atomically.

Comments, nested sections and section-like text inside `script`, `style` or `textarea` bodies are handled without turning them into separate cards.

Shell slots use inert comments:

```html
<!-- nino:template-slot header -->
[template /templates/html-header]
```

New page templates contain both markers. For compatibility, an exact leading `html-header` and trailing `html-footer` pair is recognized in memory; the first deliberate save adds the markers. Other `[template]` shortcodes remain ordinary canvas components.

Inert template metadata also lives at the start of the file and never appears in the canvas:

```html
<!-- nino:template-name Error 404 -->
<!-- nino:template-vpa off -->
```

The name drives the left-rail label and search; the second marker persists the VPA default. Existing files without metadata receive a display name derived from their filename and, when available, inherit the previous value from a managed section. The first deliberate save writes both markers.

Managed sections carry a comment such as:

```html
<!-- nino:section {"preset":"hero-centered","version":1,...} -->
```

This metadata lets the composer reopen its settings. It is inert HTML and does not add a runtime dependency. Choosing **HTML+** deliberately removes the metadata when the custom source is accepted. The section then becomes code-authored, so a later composer or page-default change cannot overwrite it.

## Section Library

System presets live under:

```text
_templates/library/<preset-key>/
├── manifest.php
└── one or more .tpl layout files
```

Preset keys and directory names match `^[a-z0-9][a-z0-9-]*$`. Invalid
manifests are not exposed. Generated HTML+ is copied into the page template;
the public request never reads the preset library.

The library ships two kinds of preset. The first manages its content: every
repeatable part reads an Elements collection, and every line of copy is a
textfill.

| Preset | What it is | Layouts |
|---|---|---|
| Fullscreen image | A hero stage with a scripted screen cover | Static cover · Parallax |
| Banner — Text over a background image | A calm full-bleed image, no scroll effect | Text on the image · Text in a card |
| Content — Flexible section | Heading, body and action for editorial copy | Single |
| Media / Text — Flexible split | Image and copy side by side | Image left · Image right |
| Features — Checklist and image | A checked list next to an image | Image right · Image left |
| Articles — Responsive grid | Repeatable image cards | Single (2/3/4 columns as a Style) |
| Filterable grid — Services or portfolio | Repeatable cards behind a client-side category filter | Single (2/3/4 columns as a Style); filter buttons need one manual HTML+ step, see below |
| Process — Numbered steps | An ordered process, numbered by the list itself | Connected timeline · Stacked steps |
| Pricing — Plan cards | One card per plan | Equal · Middle highlighted · Four · Four below one wide card · Four above one wide card |
| Partners — Logo bar | A quiet row of logos | Caption above · Caption beside |
| Call to action — Banner | One clear next step | Message above · Message beside |
| Insert reusable template | One `.tpl` include inside a managed section | Single |

The second kind ships a finished block of markup and expects HTML+ for the rest
— see [Static blocks](#static-blocks) below.

| Preset | What it is | Layouts |
|---|---|---|
| Table — Static block | A real table between intro and outro | Plain · Striped, each with demo rows or an elements loop |
| List — Static block | A checked or numbered list | Checked · Numbered, each with demo items or an elements loop |
| FAQ — Static accordion | Native `details` questions, no JavaScript | Demo questions · Elements loop |
| Newsletter — Signup form | The working double-opt-in form | Form below the intro · Intro beside the form |
| Contact — Form | The project contact form | Centered · Details beside the form |

### Static blocks

Everything in a Layout `.tpl` that is not an `[[area:…]]` token is copied into
the section as it stands. A static preset uses that deliberately: an **Intro**
area with the usual title and subtitle, a finished block of markup, and an
**Outro** area that starts empty and renders nothing until you add a button or
a note to it.

The block itself is not editable in the composer — that is the point. Insert
the section, then open it in **HTML+** and shape the rows, questions or fields
there. The moment you accept a source change, the section drops its metadata
and becomes code-authored, so nothing you write by hand can be overwritten by
the Builder later.

Each variant comes twice:

- **demo** ships three example rows or items to overwrite;
- **elements** ships a hand-written `[elements /example-rows limit="10"]` loop
  instead. Point it at one of your own collections in HTML+ — until that
  collection exists the loop simply renders nothing.

`[[section:id]]` is resolved inside a static block, which is how the FAQ keeps
its `name="faq-<section>"` grouping and the forms keep unique field IDs when the
same preset is inserted twice on one page.

A static block can also loop the *distinct values* one field takes across a
collection instead of the records themselves — the button row a client-side
category filter needs. `[elementvalues /services key="category"]…
[/elementvalues]` renders one iteration per value the "category" field
carries (with a usage count), the companion to `[elements]` looping records.
**Filterable grid** in the table above is a complete worked example: a static
filter block sits next to an ordinary, composer-editable Elements Area, and a
click on a button shows or hides matching cards without a page reload.

Both loops have to read one collection, and a preset must not spell that slug
out: a new Area is named `<page>-<section>-<area>` when the section is
inserted, and **Edit Section → Data** can point it somewhere else later. The
Layout writes `[elementvalues /[[section:collection:services]] …]` instead —
a compile token that resolves to whatever the named Area is bound to, so the
button row follows it on the first insert and after every rebind.

### Named-area contract (manifest version 3)

Version 3 describes one Section frame and one or more semantic Areas. It does
not use a universal Intro/Content/Outro structure. A Layout owns the actual
composition and contains every declared `[[area:<key>]]` token exactly once.
The Area editor then controls a finite, ordered component list and its data
bindings.

Use **Layout** only for genuinely different markup. Put two-, three- or
four-column choices, alignment, density and other class-only differences into
an Area **Style**. This keeps Structure and Style independent.

The shared Section frame offers:

| Setting | Values |
|---|---|
| Height | Auto, Off, 50, 75, 90 or 100 percent screen cover |
| Content position | Auto, top, middle or bottom |
| Width | Auto, default, narrow or wide |
| Margin / padding | Auto, none, small, default or big |
| Background | Auto, default, alt, primary, dark, black, cover or parallax |
| Overlay | Auto, none, soft, medium or strong |
| Image focus | Auto or positions 1–9 |
| Background image | A new image slot, an existing one, or a fixed URL |

A cover or parallax background binds one image. **New image slot** generates
`/page-<page>/<section>/background` and creates it on insert, **Existing image
slot** points at a slot that is already there, and **Fixed value** writes the
URL straight into the section — a project path such as
`[[/nino/public]]/images/hero.jpg`, an ordinary relative path or an `https`
URL — and creates no slot at all. The last one is for an image the project
already ships and nobody needs to swap in Admin.

Every select that offers **Auto** names the value it currently resolves to —
`Auto (Dim)`, `Auto (Wide)`, `Auto (100)` — so the choice can be read without
composing the section first. What it resolves to is the Layout's recommendation,
then the preset's, then the safe fallback.

Semantics such as an additional ARIA label, a different heading level or
project-specific classes remain deliberate HTML+ work. A preset may recommend
frame values globally and override them for a particular Layout. The user can
still keep `auto`, so a future recommendation is adopted when the section is
recomposed.

Every Area defines:

- a stable semantic key and human label;
- `source: single` or `source: elements`;
- allowed component types and a maximum component count;
- one or more safe Styles;
- a recommended Style and ordered component list;
- a safe container or collection-item tag/class;
- optional per-component tag, class, style and image-size overrides;
- optional `data-*` attributes for the elements it generates;
- an Element model and shortcode defaults for a repeatable Area.

The fixed component catalog is `title`, `subtitle`, `description`,
`text`, `image`, `button`, `price`, `number` and `template`. Every text
component offers the same three styles — **Auto**, **Quiet** and **Loud** —
which compile to a modifier of whatever class the component carries:
`nino-section-title--loud` in a content section, `nino-atf-title--loud` in a hero,
`nino-article-title--loud` in a card. Each modifier states its own `rem` size, so
a step never fights the size the class sets for itself.
Components can be added, reordered, styled or removed, but the Builder never
accepts arbitrary markup through the visual editor.

While adding a section, component order and Data live in one compact list.
After insertion, **Design** controls Area Style, Component Style and component
order, while **Data** owns the same bindings. Every non-image property has an
explicit source:

- a single Area can create a generated key such as
  `/page-home/services/title`, reference an existing textfill or store a fixed
  value directly in the compiled section;
- an Elements Area creates a new collection or selects an existing one. Each
  non-image property can independently use a collection field, an existing
  shared textfill or a fixed value. Images remain compatible model-field
  mappings;
- a Template component selects an existing non-page `.tpl` and writes a
  normal `[template /templates/<name>]` shortcode at that exact position.

The Builder lists ordinary textfills under **Content textfills** and keys from
`text/blacklist.php` under **Technical values** — that is where a page's own
`/webpage/<page>/uri` shows up, so a button can point at another page instead of
carrying a hardcoded path. Blacklisting controls normal
editor visibility; it does not make route URIs or other technical values
invalid template bindings. Existing and technical bindings are referenced only
and are never rewritten by inserting a section.

Several Elements Areas are independent. Each has its own collection ID,
shortcode arguments and mappings. The insert operation creates only requested
new textfills, image slots and Element Types; existing bindings are referenced,
not overwritten.

The persisted component node records the choice per property in
`bindingSources`. Manifests may use the same contract for recommendations:

```php
[
    'id' => 'action',
    'type' => 'button',
    'bindings' => [
        'label' => 'Contact us',
        'href' => '/webpage/contact/uri',
    ],
    'bindingSources' => [
        'label' => 'fixed',
        'href' => 'textfill',
    ],
]
```

For Single Areas the valid sources are `new`, `textfill` and `fixed` (or
`new`/`image` for images). For Elements Areas they are `field`, `textfill` and
`fixed`, while images accept `field` only. Template properties use `template`.
Fixed output is escaped, bracket tokens are neutralized and fixed URLs accept
only ordinary relative URLs or the `http`, `https`, `mailto` and `tel` schemes.

### Complete Articles example

```php
<?php return [
    'name' => 'Articles — Responsive grid',
    'description' => 'A heading, repeatable cards and optional action.',
    'category' => 'Cards',
    'tags' => [ 'articles', 'cards', 'grid' ],
    'version' => 3,
    'recommend' => [
        'layout' => 'default',
        'frame' => [ 'background' => 'alt', 'container' => 'wide' ],
    ],
    'layouts' => [
        'default' => [
            'label' => 'Heading, articles and action',
            'template' => 'section.tpl',
        ],
    ],
    'areas' => [
        'heading' => [
            'label' => 'Title area',
            'source' => 'single',
            'allowed' => [ 'title', 'subtitle', 'description' ],
            'container' => [ 'class' => 'nino-grid-100 nino-mb-3' ],
            'styles' => [
                'left' => [ 'label' => 'Left', 'class' => 'nino-text-left' ],
                'center' => [ 'label' => 'Centered', 'class' => 'nino-text-center' ],
            ],
            'recommend' => [
                'style' => 'center',
                'components' => [
                    [ 'id' => 'title', 'type' => 'title' ],
                    [ 'id' => 'subtitle', 'type' => 'subtitle' ],
                ],
            ],
            'render' => [
                'title' => [ 'tag' => 'h2', 'class' => 'nino-section-title' ],
            ],
        ],
        'articles' => [
            'label' => 'Articles',
            'source' => 'elements',
            'allowed' => [ 'image', 'title', 'description', 'button' ],
            'item' => [ 'tag' => 'article', 'class' => 'nino-article' ],
            'styles' => [
                'two-columns' => [ 'label' => '2 columns', 'class' => 'nino-grid-m-50' ],
                'three-columns' => [ 'label' => '3 columns', 'class' => 'nino-grid-m-33' ],
                'four-columns' => [ 'label' => '4 columns', 'class' => 'nino-grid-m-25' ],
            ],
            'recommend' => [
                'style' => 'three-columns',
                'components' => [
                    [ 'id' => 'image', 'type' => 'image',
                      'bindings' => [ 'src' => 'image', 'alt' => 'title' ] ],
                    [ 'id' => 'title', 'type' => 'title',
                      'bindings' => [ 'text' => 'title' ] ],
                    [ 'id' => 'action', 'type' => 'button', 'style' => 'link',
                      'bindings' => [ 'label' => 'linkLabel', 'href' => 'link' ] ],
                ],
            ],
            'typeTitle' => 'Articles',
            'model' => [
                'title' => [ 'type' => 'string', 'locale' => true ],
                'linkLabel' => [ 'type' => 'string', 'locale' => true ],
                'link' => [ 'type' => 'string' ],
                'image' => [ 'type' => 'image', 'width' => 1200, 'height' => 800 ],
            ],
            'shortcode' => [
                'locale' => '', 'callback' => '', 'limit' => 6, 'query' => '',
            ],
        ],
    ],
];
```

`section.tpl` stays deliberately small:

```html
[[area:heading]]
[[area:articles]]
```

### Multiple Layouts and Template components

A fullscreen preset can offer truly different cover and parallax markup:

```php
'layouts' => [
    'cover' => [
        'label' => 'Static cover image',
        'template' => 'section-cover.tpl',
        'frame' => [ 'screen' => '100', 'background' => 'cover' ],
    ],
    'parallax' => [
        'label' => 'Parallax image',
        'template' => 'section-parallax.tpl',
        'frame' => [ 'screen' => '100', 'background' => 'parallax' ],
    ],
],
```

To allow a reusable include, add `template` to a single Area's `allowed`
list. It can be recommended empty:

```php
'recommend' => [
    'components' => [
        [
            'id' => 'form',
            'type' => 'template',
            'bindings' => [ 'path' => '' ],
        ],
    ],
],
```

Template components are forbidden in Elements Areas because a collection item
must not dynamically select arbitrary project templates.

### Safe manifest overrides

Allowed container/item tags and component tags are selected from a finite
allowlist. CSS classes accept letters, numbers, spaces, underscores and
hyphens only. Component IDs and Area keys are lowercase slugs; model fields
follow Nino's Element-field naming rules. Text, image and template paths are
validated, traversal and double separators are rejected, shortcode arguments
are bounded, image dimensions are clamped, and collection mappings must match
image versus non-image field types.

A Layout file:

- contains no PHP;
- contains every declared Area token once;
- contains no undeclared or duplicate Area token;
- uses `[[section:id]]` only for an escaped local identifier when required;
- leaves all component HTML to the central renderer.

The compiled section contains one inert comment:

```html
<!-- nino:section {"version":3,"preset":"articles-grid","areas":{...}} -->
```

This comment is the complete round-trip model. It is ignored at runtime and
removed intentionally when the section is accepted through HTML+.

### Data attributes

The shared frontend script reads its parameters from `data-*` attributes:
`nino-autoheight` equalizes the cards sharing one `data-autoheight-group`,
`nino-slider` sizes itself from `data-slider-width`, `nino-vpa` delays itself by
`data-vpa-delay`. A preset that puts such a class on a generated element
declares the matching attributes next to it:

```php
'areas' => [
	'services' => [
		'item' => [ 'tag' => 'article', 'class' => 'nino-article' ],
		'render' => [
			'title' => [
				'tag' => 'h3',
				'class' => 'nino-article-title nino-autoheight',
				'data' => [
					'autoheight-group' => 'services-title-[[section:id]]',
					'autoheight-mobile' => 'skip',
				],
			],
			'button' => [ 'class' => 'nino-modal-trigger', 'data' => [ 'modal-target' => 'contact-modal' ] ],
		],
	],
],
```

A `data` map at the top level belongs to the `<section>`, one inside a Layout
overrides that per name, `container` and `item` carry an Area's wrapper and its
repetition, and `render.<type>` carries every component of that type. Names are
written without the `data-` prefix — a written prefix is accepted and dropped —
values are short single-line literals, and `[[section:id]]` is replaced with the
section ID so two copies of the same preset stay independent on one page.
`data-cover-height` stays with the frame. There is no editor control for this:
data attributes are a preset decision, and anything beyond them is HTML+ work.

A `container`/`item`/`render.<type>` value may also be `[[fieldname]]` — a
collection field, not just `[[section:id]]`. Compiling leaves it as plain
text; the ordinary `[elements]` rendering that already fills `[[title]]`
into the same repeated item substitutes it per record on every request, so
a card can carry its own field value in a `data-*` attribute without any
runtime change at all. **Filterable grid** uses exactly this to stamp each
card with `data-filter-item="[[category]]"`.

Pick a short field for it: the 240-character limit applies to the written
`[[category]]`, not to the value that replaces it, so a long description
would ship in full on every card. A rich (`'html' => true`) field is refused
outright — its value is sanitized for element content, which keeps `"`
usable, and inside an attribute that would end the attribute rather than
stay in it.

Choose the element that actually carries the class. The `<section>` is the one
with `nino-cover`/`nino-parallex`, so `data-cover-width` belongs in a top-level or
Layout map. `nino-vpa` is written onto the generated `nino-grid-row`, which no `data`
map reaches — motion timing stays in the Layout `.tpl`. A collection `item` is a
direct flex child of that row and already stretches to the height of its row
line, so equalizing heights belongs on the boxes inside the card via
`render.<type>` — which is how the Articles preset lines up its cards' calls to
action.

Cover height remains a percentage of the viewport. Cover width is a percentage
of the actual containing content box, so a persistent side navigation can make
`<main>` narrower without the cover overflowing by the navigation width.

### Version contract

The Section Library loads explicit `version => 3` manifests only. A missing,
older or unknown version is ignored instead of being guessed into another UI.
Existing generated HTML remains valid runtime source and can still be edited
through HTML+, but adding or graphically reopening a managed preset requires a
maintained v3 manifest.

## Current limitations

- Library and configuration previews render generated markup with fixture content. The backend refreshes the configured `/.cache/style.css` bundle and returns its contents in the authenticated library payload; the client embeds it in each isolated `srcdoc`, avoiding a separate public dot-directory request. Script tags and inline handlers are removed, CSP denies scripts/network actions, forms cannot submit and links cannot be followed.
- Visual content units are top-level `<section>` elements. Existing standalone `[template]` lines remain losslessly editable; new includes are inserted through an Area component. Marked header/footer slots live in Template Settings.
- The builder can create `page-*.tpl` files; route-to-template assignment remains in `/_admin` or code.
- Native quick fill is plain text input. Rich, translated and batch content remains in the established content tools.
- Missing image-slot definitions can be created with recommended dimensions; choosing and uploading the actual image remains in `/_admin`.
- Existing custom sections are never reverse-engineered into guessed composer settings.

## Troubleshooting

| Problem | Check |
|---|---|
| Page is marked read-only | Look for an unmatched opening or closing `<section>` tag. |
| Save reports an external change | Reload the page and deliberately merge the change; the tool will not overwrite it. |
| Duplicate section ID | Give every section a unique semantic ID. |
| Header or footer is missing | Choose the intended include in **Template Settings → Header/Footer**. `html-header`, `html-footer` and **None** are always available. |
| Elements collection is missing | Create it from the inspector if the section is managed, or in `/_admin` for custom code. |
| A section opens only as HTML+ | Its metadata is absent, invalid, or refers to a preset no longer installed. |
| Final page differs from the live preview | The preview uses current CSS but inert fixture content and no project JavaScript; the real frontend remains authoritative. |
