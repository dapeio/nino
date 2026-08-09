# `/_templates` — Template Builder (Alpha)

**Language:** English · [Deutsch](_templates.de.md)

**Last updated:** August 8, 2026 · **Nino version:** 0.11.0-beta.1

This manual explains the structural editing of page and section templates under `/_templates`. If you instead want to fully manage texts, elements, pages, or configuration, read the [`/_admin` Operation Manual](_admin.md); direct work with HTML+, rendering, and shortcodes is covered in the [Developer Manual](development.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

> **Status: Alpha.** The template builder is usable and secured by smoke tests but is expected to change significantly. Interface, block library, and workflows are not yet stable contracts. However, the saved files remain normal, readable `.tpl` markup and can be edited directly at any time.

## Purpose and Scope

`/_templates` is a graphical structure editor for selected templates under `templates/`. It helps to insert, nest, sort, and configure areas via classes or attributes.

The builder deliberately does not show the finished website. Instead of colors, fonts, and real content, the workspace primarily displays:

- grid widths;
- vertical spacing;
- nested containers;
- known block types;
- existing, still unknown markup.

Final visual control therefore always takes place in the frontend. Theme design and CSS remain direct project work in the current state. The planned `/_themes` is intended to later provide its own graphical design system for theme templates and will initially also appear as Alpha.

## Access and Security

Open `https://your-domain.example/_templates`. The area uses the same technical password, lock status, and session as `/_admin`.

You can also open the builder via **Template Builder** in the header of `/_admin`. Without active admin login, its login appears first; after successful login, Nino redirects back to the builder.

For operation, note:

- use `/_templates` exclusively via HTTPS;
- only pass access to developers and trusted designers with technical understanding;
- work with a current Git state;
- check every change in the frontend and in all relevant viewports;
- remove `_templates/` from production delivery after development if the builder is not needed there.

`/_templates` depends on `/_admin`. If `_admin/` is removed, the template builder is no longer functional.

## Interface

The workspace consists of three columns:

| Area | Task |
|---|---|
| **Templates** | select existing templates and recognize their write status |
| **Canvas** | select, nest, and reorder structure |
| **Settings / Blocks** | edit properties of the selected block or insert new blocks |

When opening a template, the builder reads its HTML structure. Changes initially remain in the browser and are only written to the file with **Save**.

> **Important:** Switching templates or leaving the page can discard unsaved changes. The builder warns about this, but this warning does not replace a conscious save operation.

## Which Templates Can Be Edited

Only the following can be edited:

- `templates/page-*.tpl`;
- `templates/section-*.tpl`.

Other templates are displayed in the list but remain write-protected. This particularly affects files that separate headers or footers, do not contain an HTML tree, or cannot be reliably and byte-accurately read and output.

This limit prevents the builder from inadvertently rewriting technical includes or unknown special cases. Such files continue to be edited directly in the editor or development environment.

## Editing a Template

1. Select an editable template on the left.
2. Click on the block in the canvas that you want to edit.
3. Change the offered settings on the right.
4. Add a block from **Blocks** if needed.
5. Arrange, duplicate, or remove selected blocks.
6. Save with **Save**.
7. Open the affected page in the frontend and check the result.

The available settings are derived from the block definition. Depending on the block, they can control, among other things, CSS classes, responsive grid widths, spacing, attributes, tags, or directly editable text.

When inserting, the current selection determines the position:

- with a suitable container, the new block is inserted into it;
- with a leaf element, it is inserted next to it;
- without a suitable selection, it lands at the end of the document.

The actions for moving up or down, duplicating, and removing always refer to the selected block.

## Structure Instead of Metadata

The builder does not save any additional project file and does not provide the template with proprietary builder attributes. The identity and settings of a block are derived from its HTML tag and its CSS classes.

This results in two important properties:

1. Existing, hand-written templates basically remain editable.
2. A saved template remains normal HTML+ and can subsequently be changed by hand.

If a template is opened and saved unchanged, the content must remain byte-accurate. If the builder cannot reliably ensure this round-trip, it only offers the file in a write-protected manner.

## Unknown Markup

Not every element needs to be known in the block library. Unknown markup appears as a dashed structure block and is preserved when saving. Known child blocks within it can still be selected and edited.

## Block Library

The block library contains predefined components that can be inserted into templates. Each block has:

- a **name** for selection;
- a **description** for clarification;
- **settings** that can be adjusted in the interface;
- **HTML markup** that is inserted into the template.

The library is extensible. Developers can add their own blocks by defining them in the project.

## Saving and Protection Rules

The builder checks the template structure before saving and issues warnings for:

- unclosed tags;
- invalid nesting;
- blocks with missing required settings;
- potential data loss during round-trip.

If critical errors are found, the template cannot be saved until they are resolved.

## Limits of the Current Alpha Version

The template builder is an Alpha tool with the following current limitations:

- Only `page-*.tpl` and `section-*.tpl` are editable.
- The visual representation is structural, not a pixel-perfect preview.
- Complex CSS or JavaScript interactions are not simulated.
- The block library is not yet complete.

These limitations are expected to be reduced in future updates.

## Troubleshooting

| Problem | Check |
| --- | --- |
| Login is shown again | Sign in with the `/_admin` password; check lock state and session cookies. |
| Template is read-only | Check its filename, HTML round trip, and supported file type. |
| A setting is missing | The block may be unknown or its library definition may not provide that option yet. |
| Canvas differs from the frontend | The canvas shows structure, not the complete theme; check CSS, content, and ID selectors in the browser. |
| Saving is rejected | Check for an empty tree, unsupported tags, `on*` attributes, and write access to `templates/`. |
| A change disappeared | Browser-only changes were not saved; repeat them and save before switching templates. |

## Next Steps

- [`/_admin` Operation](_admin.md) explains full project administration.
- [`/_editor` Operation](_editor.md) describes the released content maintenance.
- [Developer Manual](development.md) covers direct template work and shortcodes.
- [Deployment](deployment.md) describes web server configuration and go-live.
