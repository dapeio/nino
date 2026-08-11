# `/_templates` — Template Builder (Alpha)

**Language:** English · [Deutsch](_templates.de.md)

**Status:** 11 August 2026 · **Nino version:** Unreleased

The Template Builder is the fast path from a `page-*.tpl` file to a filled page. It treats a template as an ordered sequence of complete HTML sections and reusable `[template]` sections instead of exposing every nested DOM node.

[README](../README.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [Developer Manual](development.md)

> **Alpha:** Page files remain ordinary HTML+ and therefore do not depend on the tool at runtime. The preset library and composer workflow can still change.

## Purpose and scope

Use `/_templates` to:

- open existing `templates/page-*.tpl` files;
- create a new `page-*.tpl` with its real filename, display name, header, footer and VPA default;
- choose a complete section by appearance from a searchable, tagged visual library;
- insert a standalone `[template /templates/<name>]` section directly through **Add section**, then move, replace or duplicate it;
- select the page’s header and footer from compatible non-page `.tpl` files, or set either slot to **None**;
- assign a stable section ID;
- configure surface, background, heading, content module, action, layout and viewport motion;
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
3. In **Choose**, search or filter the fullscreen gallery and select a preset from its real-markup preview. Reusable non-page `.tpl` files appear in the **Templates** category and are inserted without an unnecessary configuration step.
4. Continue to **Configure & fill**, give the section a meaningful ID such as `main-hero` or `services-overview`, and adjust only the settings that matter. Less common spacing and border controls stay under **Advanced**.
5. Fill the generated **Native content** in the same step. For a repeatable module, choose an Elements collection; if it does not exist, keep automatic schema creation enabled.
6. Compare the live preview, insert the section and create any recommended text keys, Element Type or image-slot definitions in the same operation.
7. Open image slots or individual Elements entries in `/_admin` when needed.
8. Reorder the HTML and template-section cards and save the page template.
9. Check the real page in the browser. Complete translations afterwards through the existing JSON batch workflow or `/_admin`.

Native quick fill creates new keys in the project’s native locale and changes only that locale for per-language keys. A pre-existing global key deliberately remains global. Existing translated buckets are never cleared or replaced.

## Page and section settings

**Name**, **Header**, **Footer**, **VPA** and **Delete** live in Template Settings. The header/footer selects show real `.tpl` filenames, list non-page templates known to the project and also offer **None**. The selected value is still written as an ordinary `[template /templates/<name>]` shortcode; the controls only prevent shell includes from being mistaken for movable page content. **Delete** removes exactly the loaded file revision after explicit confirmation; recovery requires version control or another external backup.

**VPA** at template level supplies the default for sections whose motion is set to **Page**. Changing it recomposes managed sections, updates their `js-vpa` class and remains persisted even while a template is still empty. **On** or **Off** on an individual section overrides that default.

The composer groups settings by intent:

| Group | Examples |
|---|---|
| Identity | section ID and resulting `/page-<page>/<section>/…` prefix |
| Background & heading | surface, image/parallax background, heading depth, alignment |
| Content module | text, media split, articles, lists, sliders, tabs, testimonials, team, stats, features, pricing, tables, badges, forms, gallery, video, notices |
| Content source | native textfill or Elements collection |
| Action | none, link, primary button, or primary plus outline button |
| Advanced | padding, margin and border |

Curated presets expose only compatible choices. **Blank Section** exposes the full composer when a project needs a combination not represented by a focused preset.

## Source safety and the HTML+ escape hatch

On load, the backend scans top-level `<section>` elements without serializing the surrounding source. A standalone `[template /templates/<name>]` line outside a section becomes a first-class template-section card; the shortcode remains part of its parent when it occurs inside a section. Marked header/footer shortcodes become fixed settings slots instead. Other source is returned as locked raw segments. On save:

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
_templates/library/<preset>/manifest.php
```

A manifest supplies name, description, category, tags, version, defaults and allowed setting values. Omitted axes are locked to their default, which keeps a curated preset focused. The `blank` preset explicitly allows the full composer.

An optional `section.tpl` beside the manifest creates a code-authored preset. It may use these tokens:

```text
{{section:id}}
{{section:classes}}
{{section:meta}}
{{content:prefix}}
{{elements:type}}
{{text:<suffix>}}
{{image:<suffix>}}
```

The generated source is copied into the page. Removing the preset later does not break the public website; only reopening that section in the visual composer is no longer possible.

## Current limitations

- Library and configuration previews render generated markup with `/.cache/style.css` and fixture content. They are sandboxed and intentionally do not execute project JavaScript, submit forms or follow links.
- Visual content units are top-level `<section>` elements and standalone `[template /templates/<name>]` lines; marked header/footer slots live in Template Settings instead.
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
