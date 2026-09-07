# Recipe: Add a Section Library preset

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Templates Panel](../templates.md) · [Features](../features.md)

One of the seven extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


A Section Library preset is a discoverable compile-time recipe used by the
Templates panel. Generated HTML+ is copied into a page template and must remain
independent of the library at public runtime.

Every library preset MUST use the named-area manifest contract, version `3`.
`Library::presets()` deliberately ignores manifests without that explicit
version. Do not add a parallel Classic gallery, alternate-version loader,
migration branch or Intro/Content/Outro compiler. A managed section whose
preset is unavailable remains editable through HTML+ as ordinary section
source.

## Directory, slug, and files

```text
_nino/Nino/Modules/Templates/library/services-grid/
├── manifest.php
└── section.tpl
```

A preset with genuinely different markup can provide several Layout files:

```text
_nino/Nino/Modules/Templates/library/fullscreen-image/
├── manifest.php
├── section-cover.tpl
└── section-parallax.tpl
```

The directory slug MUST match `^[a-z0-9][a-z0-9-]*$`. Layout filenames must
be local safe `.tpl` basenames; traversal and external paths are forbidden.

## Mental model

A v3 preset owns:

- one shared Section frame;
- one or more real Layout templates;
- semantic named Areas such as `heading`, `articles`, and `action`;
- the safe component vocabulary allowed in every Area;
- recommendations, not hidden mandatory UI state;
- and any Elements model/shortcode contract used by a repeatable Area.

A Layout contains every declared `[[area:<key>]]` token exactly once. Use a
second Layout only when the source composition differs. Two-, three-, and
four-column choices, alignment, density, and similar class-only changes belong
in Area Styles. Do not create the Layout × Style cross-product.

Add Section deliberately uses a reduced composer: Section ID, Layout,
Background, collection choice, component order, and initial bindings share one
flow. It omits dimensions/spacing, Area Style, and Component Style. Edit Section
opens the complete model: frame fine tuning plus Design and Data views per Area.
Design changes Style and ordered components. Data connects single components to
Text/Image/template bindings or maps repeatable components to an Elements model.

The Add Section library contains version-3 named-area presets only. Add a
focused semantic preset instead of duplicating it into several class-only
cards; Layouts are for real source changes and Area Styles are for visual
variants.

HTML+, not another general-purpose Advanced panel, is the escape hatch for
arbitrary HTML, heading hierarchy, additional ARIA attributes, custom classes,
icons, nested structures, or project-specific behavior.

## Complete manifest shape

```php
<?php return [
	'name' => 'Services — Responsive grid',
	'description' => 'A heading, repeatable service cards and optional action.',
	'category' => 'Services',
	'tags' => [ 'services', 'cards', 'grid', 'elements' ],
	'version' => 3,
	'recommend' => [
		'layout' => 'default',
		'frame' => [
			'background' => 'alt',
			'container' => 'wide',
			'padding' => 'default',
		],
	],
	'layouts' => [
		'default' => [
			'label' => 'Heading, services and action',
			'template' => 'section.tpl',
		],
	],
	'areas' => [
		'heading' => [
			'label' => 'Title area',
			'help' => 'The non-repeating introduction.',
			'source' => 'single',
			'allowed' => [ 'title', 'subtitle', 'description' ],
			'container' => [
				'tag' => 'div',
				'class' => 'nino-grid-100 nino-mb-3',
			],
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
		'services' => [
			'label' => 'Services',
			'source' => 'elements',
			'allowed' => [ 'image', 'title', 'description', 'button' ],
			'item' => [ 'tag' => 'article', 'class' => 'nino-article' ],
			'styles' => [
				'two-columns' => [ 'label' => '2 columns', 'class' => 'nino-grid-m-50' ],
				'three-columns' => [ 'label' => '3 columns', 'class' => 'nino-grid-m-33' ],
			],
			'recommend' => [
				'style' => 'three-columns',
				'components' => [
					[ 'id' => 'image', 'type' => 'image',
					  'bindings' => [ 'src' => 'image', 'alt' => 'title' ] ],
					[ 'id' => 'title', 'type' => 'title',
					  'bindings' => [ 'text' => 'title' ] ],
					[ 'id' => 'description', 'type' => 'description',
					  'bindings' => [ 'text' => 'description' ] ],
					[ 'id' => 'action', 'type' => 'button', 'style' => 'link',
					  'bindings' => [ 'label' => 'linkLabel', 'href' => 'link' ] ],
				],
			],
			'typeTitle' => 'Services',
			'model' => [
				'title' => [ 'type' => 'string', 'locale' => true, 'required' => true ],
				'description' => [ 'type' => 'string', 'locale' => true, 'html' => true ],
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

`section.tpl`:

```html
[[area:heading]]
[[area:services]]
```

Generated elements MAY declare `data-*` attributes. `Nino.ui.js` is configured
through them - `nino-autoheight` reads `data-autoheight-group`, `nino-slider` reads
`data-slider-width`, `nino-vpa` reads `data-vpa-delay` - so a preset that owns the
class MUST be able to own its parameters. A `data` map is accepted in five
places, each attached to exactly one generated element:

| Manifest key | Element |
| --- | --- |
| top-level `data` | the `<section>` |
| `layouts.<key>.data` | the `<section>` of that Layout; overrides the preset default per name |
| `areas.<key>.container.data` | a Single Area's wrapper |
| `areas.<key>.item.data` | one repetition of an Elements Area |
| `areas.<key>.render.<type>.data` | every component of that type in the Area |

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

A name is the attribute without its `data-` prefix and matches
`^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$`; a written prefix is accepted and dropped, and
uppercase is lowercased exactly as the browser would. A value is a single-line
scalar of at most 240 characters, escaped for the attribute context.
`[[section:id]]` is substituted, so a value such as a group name stays unique
when the same preset is inserted twice. `data-cover-height` belongs to the frame and
is rejected. A `template` component compiles to a shortcode and therefore
carries no attributes at all.

`item.data` and `render.<type>.data` on an Elements Area MAY also carry a
per-record model field as `[[fieldname]]`. Compiling only escapes the literal
value for the attribute context; it does not resolve `[[fieldname]]`, because
that is not a compile-time token - the `item` markup this map attaches to sits
inside the compiled `[elements ...]...[/elements]` block, so the field is
substituted per record by the ordinary `[elements]` render pass at request
time, exactly like `[[title]]` inside the same item. This is how a card can
carry its own field value in a `data-*` attribute (a client-side filter
matching a card's category against a clicked button, say) with no runtime
change at all.

Two limits apply, and neither is the 240-character rule above: that one
measures the compile-time literal (`[[category]]`, twelve characters), never
the value substituted into it, so a 2000-character `description` in a `data-*`
map compiles happily and ships in full on every card. Keep such a binding to a
field that is genuinely short. The second is enforced: the field MUST NOT be
`'html' => true`. A rich value is sanitized for element *content*, which leaves
`"` intact - inside an attribute it would close it and turn editor content into
a live event handler - so the compiler rejects a data value referencing one.

Attach the map to the element that really carries the class. The `<section>` is
the element with `nino-cover`/`nino-parallex`, so `data-cover-width` belongs in a
top-level or Layout map. `nino-vpa` is written onto the generated `nino-grid-row`,
which no `data` map targets - motion timing belongs in the Layout `.tpl`. An
Elements `item` is a direct flex child of that row and already stretches to its
row line, so equalizing heights (`nino-autoheight`) belongs on the boxes inside the
card through `render.<type>`, not on the item.

At runtime, `data-cover-height` is a percentage of the viewport height;
`data-cover-width` is a percentage of the cover's containing content box. Keep
that distinction: a header may reserve a persistent side rail and leave
`<main>` narrower than the viewport, so a cover width based on `100vw` would
overflow by precisely that rail width.

Only the manifest decides this. Nothing is read from the request: the composer
has no data-* control, a `data` key inside posted section metadata is ignored,
and arbitrary attributes remain HTML+ work.

## Static blocks

Everything in a Layout that is not an `[[area:<key>]]` token is copied into the
section verbatim, so a preset MAY ship a finished block of markup instead of
composing it from components. Use that shape when the content is not a
collection and not a line of copy - a table, a `details` accordion, a form:

```text
[[area:intro]]     an ordinary Single Area, title and subtitle
<static markup>    copied as it stands, refined in HTML+ afterwards
[[area:outro]]     a Single Area with no recommended components
```

An Area whose components are all removed renders nothing and its line is
dropped, so the outro costs nothing until somebody adds a button to it. Ship
each variant twice where it makes sense: once with demo rows to overwrite, once
with a hand-written `[elements /example-rows limit="10"]` loop that a project
points at its own collection. A loop over a type that does not exist renders
empty, so the second variant is safe to insert before the collection is there.

`[[section:id]]` is resolved inside the block. Use it for every identifier that
must stay unique when the preset is inserted twice on one page - a `details`
group `name`, a form field `id` and its `for`, a modal target.

`[[section:collection:<areaKey>]]` is resolved there too, and compiles to the
collection slug that Elements Area is bound to. A static block that loops
beside a managed Area - a filter's `[elementvalues]` button row next to the
`[elements]` cards it filters - MUST use it rather than a literal slug: a new
Area is named `<page>-<section>-<area>` at insert time and can be rebound
afterwards, so a hand-written slug is wrong immediately and stays wrong. The
key must name a declared Elements Area of the same preset.

A static preset MUST NOT ship `style=""` attributes; give the block a `nino-*`
class and put the rule next to the other preset classes in `_nino/Nino.css`.
Forms MUST keep the pieces the runtime expects: `[csrf]`, the honeypot input,
`.nino-form-message`, and the label keys the Form or Newsletter module ships.

## Component and binding contract

The finite catalog is `title`, `subtitle`, `description`, `text`, `image`,
`button`, `price`, `number`, and `template`. A manifest may restrict that
list, override allowlisted tags/classes/styles and image dimensions, and set a
maximum component count. It MUST NOT supply arbitrary component HTML.

A preset MUST write the design system's own classes. The frontend has exactly
one namespace, `nino-*` - the same class carries structure, appearance and, where
`Nino.ui.js` looks for a hook, behaviour; `nino-is-*` marks a transient state that
JS or a module sets and removes. A preset MUST NOT invent a class namespace of its
own: a rule that only the Builder's output uses is a second design system nobody
maintains, and it drifts from the `nino-` class that already does the same thing.
What a preset needs and the design system lacks belongs in `_nino/Nino.css` next
to the family it extends (`nino-timeline--…` by `nino-timeline`, `nino-form-…` by
`nino-form`), named like its family.

Container, item and component tags come from `AreaComposer::TAGS`: `div`,
`header`, `footer`, `article`, `aside`, `nav`, `h2`, `h3`, `h4`, `p`, `span`,
`strong`, `ul`, `ol`, `li`, `tr`, `th`, `td`. They are structural and inert;
anything that loads, submits or scripts (`img`, `iframe`, `form`, `button`,
`script`, `style`) stays out, and so does `section`, which the document parser
reserves for the section itself. A list wrapper (`<ol class="nino-timeline">`) or
a table (`<table><tbody>`) belongs in the Layout `.tpl`; only the repeated row
or item is a manifest tag.

Each property persists an explicit `bindingSources` value. Single non-image
properties allow `new`, `textfill`, or `fixed`; Single images allow `new` or
`image`. Generated keys use
`/page-<pageId>/<sectionId>/<component-suffix>`. Elements non-image properties
allow `field`, `textfill`, or `fixed`, while Elements images remain `field`.
Template properties use `template`, accept only `/templates/<safe-name>`, and
compile to a normal `[template]` shortcode. Template components are forbidden
in Elements Areas. Every persisted v3 component MUST declare a source for every
property; missing `bindingSources` are invalid rather than inferred. Manifest
recommendations may omit them because preset normalization expands those
author-owned defaults before they reach the editor.

The authenticated Builder key list includes blacklisted keys, grouped as
technical values in the UI. A Text blacklist is an editor-visibility rule, not
an invalidation of route URIs or other reusable bindings. Existing textfills are
reference-only during insertion. Fixed values MUST be bounded and escaped;
neutralize shortcode brackets and allow only safe relative URL values or the
`http`, `https`, `mailto`, and `tel` schemes.

Elements Areas independently choose `new` or `existing`, a safe type slug,
shortcode arguments, and mappings. Every mapped field must exist for a new
model and must match image versus non-image type. Several Elements Areas in
one preset MUST remain independent during creation, mapping, preview, and save.

## Frame recommendations

Valid frame axes come from `AreaComposer::FRAME_CHOICES`: screen, vertical,
background, container, padding, margin, focus, and overlay. A preset-level
recommendation may be overridden by a Layout recommendation. User `auto`
resolves Layout → preset → safe fallback. Never persist an invalid value
silently as a custom class.

Cover and parallax backgrounds bind one background image, and the choice is
stored as `frame.backgroundImageSource`: `new` generates the slot
`/page-<pageId>/<sectionId>/background`, `image` references an existing slot,
and `fixed` writes a plain `<img>` with a literal URL and creates no slot at
all. A fixed value may start with `[[/nino/public]]` or `[[/nino/dir]]` and is
otherwise a relative path or an `http`/`https` URL - every other bracket,
quote, space and scheme is refused. Persisted metadata MUST carry the source
explicitly. Focus is positions 1–9 and overlay is `auto`, `none`, or `dim`.
Mobile and reduced-motion behavior must remain
meaningful without preview JavaScript.

## Validation and output

A v3 Layout MUST contain no PHP, every declared Area exactly once, no unknown
Area tokens, and only optional `[[section:id]]` outside Area tokens. The central
renderer validates slugs, classes, tags, data attribute names and values, model
fields, paths, dimensions, component count, styles, target behavior, and
shortcode bounds.

Composition MUST produce exactly one complete top-level `<section>`. It stores
one inert round-trip comment:

```html
<!-- nino:section {"version":3,"preset":"services-grid","areas":{...}} -->
```

The comment is ignored at runtime. HTML+ deliberately removes graphical
ownership. Never add a public runtime dependency on the manifest.

## Preview and tests

Preview remains inert: no scripts, active forms, network iframes, or project
callbacks. It strips VPA's hidden state, uses deterministic text and image
fixtures, and renders the number of collection items implied by 1/2/3/4-column
Styles where possible.

Extend `tests/templates-smoke.php` and `tests/templates-js-smoke.js`. Test:

- every bundled manifest loads without a private Layout source leak;
- v3 defaults compose and contain no unresolved Area token;
- metadata preserves ordered components and independent collections;
- component add/move/remove helpers do not mutate unrelated state;
- new/existing/fixed Text, Image, Template, and Elements bindings, including
  blacklisted technical textfills and rejection of incomplete source metadata;
- declared `data-*` attributes on the section, Layout, Area container,
  collection item and component, their escaping and `[[section:id]]`
  substitution, the reserved frame attribute, and posted metadata that tries to
  add an attribute of its own;
- invalid slugs, paths, tags, classes, styles, mappings, and Layouts;
- exact collection schema and all shortcode arguments;
- preview placeholders, column count, VPA visibility, and script isolation;
- Content endpoints reject resources not declared by the selected preset/Area;
- and unsupported manifest versions are not accepted.

Run:

```bash
php -l _nino/Nino/Modules/Templates/AreaComposer/AreaComposer.php
php -l _nino/Nino/Modules/Templates/library/services-grid/manifest.php
php tests/templates-smoke.php
node tests/templates-js-smoke.js
```

Finally inspect the real library card and preview at small and large widths.
String assertions do not establish that a visual preset is useful.
