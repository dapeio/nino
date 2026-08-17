# `/_templates` — Template Builder (Alpha)

**Language:** English · [Deutsch](_templates.de.md)

**Status:** 17 August 2026 · **Nino version:** Unreleased

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

Open `https://your-domain.example/_templates`. The tool shares password, lock state and session with `/_admin`. It depends on `_admin/Admin.php`; removing `/_admin` also removes its authentication backend.

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

**VPA** at template level supplies the default for sections whose motion is set to **Page**. Changing it recomposes managed sections, updates their `js-vpa` class and remains persisted even while a template is still empty. **On** or **Off** on an individual section overrides that default.

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
- an Element model and shortcode defaults for a repeatable Area.

The fixed component catalog is `title`, `subtitle`, `description`,
`text`, `image`, `button`, `price`, `number` and `template`.
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
`text/blacklist.php` under **Technical values**. Blacklisting controls normal
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
            'container' => [ 'class' => 'ui-grid-100 nino-area--heading' ],
            'styles' => [
                'left' => [ 'label' => 'Left', 'class' => 'nino-area--left' ],
                'center' => [ 'label' => 'Centered', 'class' => 'nino-area--center' ],
            ],
            'recommend' => [
                'style' => 'center',
                'components' => [
                    [ 'id' => 'title', 'type' => 'title' ],
                    [ 'id' => 'subtitle', 'type' => 'subtitle' ],
                ],
            ],
            'render' => [
                'title' => [ 'tag' => 'h2', 'class' => 'ui-section-title' ],
            ],
        ],
        'articles' => [
            'label' => 'Articles',
            'source' => 'elements',
            'allowed' => [ 'image', 'title', 'description', 'button' ],
            'item' => [ 'tag' => 'article', 'class' => 'ui-article' ],
            'styles' => [
                'two-columns' => [ 'label' => '2 columns', 'class' => 'ui-grid-m-50' ],
                'three-columns' => [ 'label' => '3 columns', 'class' => 'ui-grid-m-33' ],
                'four-columns' => [ 'label' => '4 columns', 'class' => 'ui-grid-m-25' ],
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
