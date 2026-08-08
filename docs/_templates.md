# Nino — Template Builder Handbook
*[Deutsch](_templates.de.md)*

**Links:**
[README](../README.md) · [Design-Handbook](design.md) · [Developer-Handbook](development.md) · [_editor-Handbook](_editor.md) · [_admin-Handbook](_admin.md) · [_install-Handbook](_install.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

A graphical, developer-only editor for the project's own `templates/*.tpl` files, at `/_templates`. Optional - a `.tpl` is plain HTML and can just as well be written by hand (see `docs/design.md`) - but it turns "which grid classes does this column carry" into a form instead of a lookup in the design handbook.

## Where it lives

Its own top-level folder, `_templates/`, not a module inside `/_admin` - the same reasoning `/_install` has one: it's a development-time tool, so a project is free to delete it (`rm -rf _templates`) once the design is settled, and `_admin/Admin.php` is large enough already.

The password is `/_admin`'s, though. `_templates/index.php` requires `_admin/Admin.php` and reuses its session flag (`./nino/admin/authed`) rather than introducing a second password to keep in sync - the same dependency `/_install` already has. Practically: log into `/_admin` and the builder is open too, and `/_admin`'s header carries a "Template Builder" link. Visiting `/_templates` while logged out shows `_admin`'s own login form, which posts to `/_admin` and reloads back here.

Deleting `_admin` therefore also disables `_templates`. That is deliberate - an unauthenticated template editor writes executable-adjacent markup into every page of the site.

## The core idea

**A block's identity is its HTML tag plus its CSS classes, and its properties are those same CSS classes.**

There is no separate data model sitting next to the markup, no JSON sidecar, no `data-*` attributes the builder needs in order to understand a file. `<div class="ui-grid-100 ui-grid-l-50 ui-mb-3">` *is* a grid column with width 100, width-at-large 50 and margin-bottom 3 - the builder reads those three settings off the class list and writes them back to it.

Two consequences worth stating plainly:

- **It works on templates that predate it.** Every `.tpl` the project already has opens in the builder with its structure recognized, without being touched or migrated first.
- **What it saves stays hand-editable.** The output carries nothing a hand-written template wouldn't. You can keep editing the same file in an editor afterwards, and the builder will still understand it.

The alternative - writing a `data-nino-builder="grid-col"` marker onto every element - would make recognition trivially unambiguous, at the cost of an extra attribute on practically every tag in the delivered frontend HTML. The tradeoff went the other way. A block whose class signature is genuinely ambiguous can still declare an `attrs` match of its own (see "Library format" below); none of the shipped blocks needs to.

## The interface

Three rails:

- **Templates** (left) - every `templates/*.tpl` on disk. Names shown dimmed are not editable (see below).
- **Canvas** (middle) - the opened template as a tree of nested boxes. Click one to select it.
- **Settings + Blocks** (right) - the selected block's own properties on top, the library below, grouped by each manifest's own `category`.

Nothing reaches the filesystem until **Save**. Every edit changes the in-memory tree and redraws; the header shows "unsaved changes" and the Save button enables. Switching template or leaving the page with unsaved work asks first.

### What the canvas shows

Deliberately **not** a preview. Only the two things that genuinely can't be judged from a list of class names are drawn to scale:

- a **grid column's width** - its box is as wide as its `width` setting says, and a grid row lays its columns out side by side, exactly as the markup will
- **vertical spacing** - `ui-mt-*`/`ui-mb-*`/`ui-pt-*`/`ui-pb-*` are drawn as real margin and padding

Everything else is a labelled box: the block's name, its HTML tag, and a short preview of its text, link target or shortcode arguments. Colours, fonts and real typography are the theme's job (`docs/design.md`); reproducing them here would mean maintaining a second renderer that is wrong in a different way on every project.

**Nesting depth is drawn too** - each level sits on a slightly stronger tint than the one above it, so the layers of a deep grid read apart instead of as one wall of boxes. The tint is mixed against the editor theme's own tokens (`color-mix()` on a `--tb-depth` custom property set per box), so it comes out right in light and dark alike, and it caps after six levels rather than fading to unreadable.

Note that a column showing 100% is showing the truth: `ui-grid-100 ui-grid-l-50` *is* full width until the `l` breakpoint. The canvas draws the base width, and the breakpoint values sit in the settings.

### Markup the library doesn't know

Anything without a matching block still gets a box, labelled with its tag, id and classes (`<div id="hero" class="my-thing">`) and drawn with a dotted border. Those are structural only: the builder has no model for what their properties mean, so it offers no fields for them - but it never hides them, never drops them, and their children are still parsed and shown normally. A recognized block nested inside an unrecognized wrapper works exactly as it would anywhere else.

### The `#id` warning

A node whose `id` is targeted by a rule in the project's own stylesheets gets an orange badge.

This matters because of the core idea above: the builder presents an element's CSS classes as its properties, and a rule bound to an id overrides those classes in the real page while being completely invisible here. `#hero { padding: 0 }` beats `ui-pt-4` in the cascade, so the canvas would draw padding the browser will not render. The badge says "the class list is not the whole truth for this node" rather than quietly showing something wrong.

The scan reads every `.css` file listed in any of `config.php`'s `/nino/html/assets` bundles and collects the ids their selectors mention. It is a selector scan, not a full CSS parser - a false positive costs one unnecessary badge, which is the right way for it to be wrong.

## Editing

**Selecting.** Click a box. The click selects the innermost box it landed in, not every ancestor it bubbled through; clicking the canvas background deselects. Selection is by node id, so it survives the redraw every edit triggers.

**Settings.** The inspector renders one control per setting the block's manifest declares - there is no per-block form code anywhere, and adding a setting to a manifest is enough to make it editable. Which control appears follows from the setting's type: a select for `classenum`/`classgroup`/`tag`, a checkbox for `classtoggle`/`attrtoggle`, a text field for `attr` and `text`. A responsive setting's breakpoint variants appear indented under their base control, so five fields called "Width" read as one setting with four variants rather than five settings.

Changes apply immediately to the tree and redraw the canvas. A text field additionally updates its box's preview while you type - a full redraw per keystroke would move focus out of the field.

**Inserting.** Click a block in the palette. Where it lands follows from the selection: *inside* it if that block takes children, *next to* it if it doesn't, and at the end of the document if nothing is selected. The block's own `block.tpl` is parsed **on the server, by the same parser documents go through** (`library/parse`), so a block's starting markup is written exactly like template markup and there is no second code path that could disagree about what a template means.

The library currently has **76 block definitions**: 51 insertable palette entries cover the `Nino.css` catalogue (tables, pricing, accordions, galleries, tabs, modals, sliders, timelines, badges, alerts, breadcrumbs, lists, logo strips, video and their supporting pieces), while 25 nested helpers only recognize and expose settings for markup such as table cells, tab panels and modal close buttons. Those helpers set `palette => false`: they remain fully editable in the canvas without cluttering the top-level palette.

**Actions.** The inspector's footer carries the selected block's own actions - move up/down, duplicate, remove - filtered by what its manifest's `actions` allows. An unrecognized element gets the structural ones only, since those need no model of what it is.

**Indentation.** Every structural edit carries its own whitespace, because the tree keeps the exact text between tags (that is what makes the round-trip byte-exact). Rather than deriving indentation from a depth counter, an insert *copies the whitespace its new neighbour already has* - whatever the file uses, tabs or spaces, at whatever depth. A remove takes its own indentation with it, so edits don't slowly accumulate blank lines. The one case with no neighbour to copy from, a first child going into an empty element, derives one level from the parent. `tests/templates-js-smoke.js` covers each of those cases.

## Which templates can be edited

Only `page-*` and `section-*`. Everything else is listed but opens read-only.

The reason is `html-header.tpl` and `html-footer.tpl`: they are **one structure split across two files**. The header opens `<head>`, `<header>` and `<main>`; the footer closes `</main>` and adds `<footer>`. Neither is a well-formed fragment on its own. An HTML parser handed the header alone "corrects" it by closing the tag it sees left open - and saving that result would silently destroy the page frame of every page on the site. The same applies to the `mail-header`/`mail-footer` pair, and `sitemap-xml.tpl` isn't HTML at all.

A template can also open read-only for a second reason: it did not round-trip (see below). That means it holds markup the tree has no faithful representation for, and the builder says so instead of reformatting it.

Both cases are shown as a notice at the top of the canvas, with the reason.

## The round-trip guarantee

**Opening a template and saving it unchanged reproduces the file byte for byte.**

This is the property everything else rests on. A builder whose first save reformats indentation, reorders attributes and rewrites `required` as `required=""` is one developers stop opening. So the node tree keeps everything: attribute order (including where `class` sat among the others), the exact whitespace between tags, comments, and the original spelling of every HTML entity.

`tests/templates-smoke.php` asserts it against every `page-*` template the library ships, plus a set of individual cases (boolean attributes, void elements, `&shy;`, bare vs. encoded ampersands, attribute order, whitespace). A parser change that breaks one shows up as a failing test rather than as a mangled site.

Some mechanics behind it, in case you touch the parser:

- **Shortcodes are structure, not text.** `[elements /services limit="3"]…[/elements]` wraps HTML, so it has to be a node with children. Before parsing, every shortcode call is rewritten into a `<nino-sc name args>` placeholder element; the serializer turns it back. That lets PHP 8.4's real HTML5 parser (`\Dom\HTMLDocument`) do all the actual work. A shortcode sitting inside a tag (in an attribute value) is deliberately left alone - injecting an element into the middle of an attribute would corrupt the document.
- **Text fills need no handling at all.** `[[/key]]` is ordinary text, in text nodes and attribute values alike, including nested ones like `[[/webpage[[/nino/http/response/uri]]/title]]`.
- **Entities are swapped for a placeholder before parsing.** The parser decodes `&shy;` to a soft hyphen and there is no way to guess afterwards which of `&shy;`, `&#173;` or a literal character the source had. Each one is carried through the tree between two Private Use Area characters (U+E000/U+E001) holding its own name, so restoring needs no lookup table.

## Library format

`_templates/library/` has one directory per block, each with a `manifest.php` and optionally a `block.tpl` - the same directory-plus-manifest convention `_install/library` uses. Adding a block is one new directory; no code changes anywhere.

```
_templates/library/
  grid-col/
    manifest.php      category, tag, name, match, children, use, settings, actions, palette
    block.tpl         the markup inserting this block produces
  section/
  button/
  …
```

A `manifest.php` returns a plain array:

| Key | Meaning |
|---|---|
| `category` | Palette group (`Grid`, `Sections`, `Content`, `Media`, `Forms`, `Shortcodes`, …). Free-form - the palette groups by whatever it finds |
| `tag` | What kind of thing this is (`wrap`, `title`, `text`, `image`, `link`, `loop`, `include`, `meta`). Shown next to the name; also what a future `children` filter matches against |
| `name` | Shown in the palette and as the box's label |
| `match` | How to recognize this block in an existing template - see below |
| `children` | `[ '*' ]` if the block accepts children, omitted if it doesn't |
| `use` | Shared setting groups to fold in: `spacing`, `align`, `vpa`. Saves repeating the same twenty lines in forty manifests; a block's own `settings` win a name collision |
| `settings` | The editable properties - see below |
| `actions` | Which actions the block offers. Defaults to `remove`, `duplicate`, `moveup`, `movedown` |
| `palette` | `false` for a recognition-only nested helper. It still labels markup and supplies settings/actions in the canvas, but is not offered as a top-level insert. Defaults to `true` |
| `html` | Inline starting markup, for a one-liner not worth its own `block.tpl` |

### `match`

```php
'match' => [
    'tag'        => 'div',                    // or 'tags' => [ 'h2', 'h3', 'h4' ]
    'classes'    => [ 'ui-grid-row' ],        // all of these must be present
    'classesAny' => [ 'ui-grid-25', … ],      // at least one of these
    'attrs'      => [ 'name' => 'elements' ], // exact values (shortcode blocks)
    'not'        => [ 'ui-atf-title' ],       // rules the node out
],
```

Every block whose `match` a node satisfies is scored by how specific that match was - a required class or attribute counts 10, `classesAny` counts 5, matching on the tag alone counts 1 - and the highest score wins. That is what lets "Section Title" (`h3.ui-section-title`) and the generic "Heading" (any `h1`-`h6`) both exist without the generic one ever swallowing the specific one.

### `settings`

Seven types, all of them a two-way mapping between one form control and the node's own class list, attributes, tag or text:

| Type | Maps to | Declaration |
|---|---|---|
| `classenum` | one value out of a list, through a printf pattern | `'pattern' => 'ui-grid-%s'`, `'values' => [ '25', '50', … ]`, optionally `'bpPattern' => 'ui-grid-%b-%s'` + `'breakpoints' => [ 's','m','l','xl' ]` |
| `classgroup` | one class out of an explicit list (variants sharing no pattern) | `'options' => [ '' => 'Default', 'ui-btn--primary' => 'Primary', … ]` |
| `classtoggle` | a single class, on or off | `'class' => 'ui-section--fullwidth'` |
| `attr` | a plain HTML attribute | `'attr' => 'href'`, optionally `'values' => [ … ]` for a fixed set |
| `attrtoggle` | a boolean HTML attribute, present or absent | `'attr' => 'required'`; rendered as a checkbox and serialized bare (`required`, not `required=""`) |
| `tag` | the element name itself (`h2` vs `h3`) - the one property that is neither class nor attribute | `'values' => [ 'h2', 'h3', 'h4' ]` |
| `text` | the node's own direct text content, text fills included | — |

A responsive `classenum` contributes one value per breakpoint on top of its base, keyed `width@m`, `width@l` and so on.

**The mapping itself runs client-side**, in `_templates/assets/blocks.js`, and only there. The server ships the declarations (`Library`) and moves tags, attributes and an ordered class list around (`Parser`/`Serializer`), but never interprets a class. One implementation of the mapping means nothing can read a class one way and write it back another - which is what keeps the round-trip guarantee true through an edit as well.

Changing a setting replaces its class **at the index it already sat at** rather than removing and appending, so editing one property never reshuffles the rest of an element's `class` attribute.

## Saving

`documents/save` serializes the posted tree and writes it atomically (temp file + `rename()`, same as `/_install`'s password rewrite) - a half-written page template is a broken site.

Before writing, it refuses:

- a template outside `page-*`/`section-*` (403)
- an empty tree (400) - an accident, never an intention
- any tag outside the allowlist (400), `<script>` above all, whose content is not markup and must never be reformatted or re-escaped as if it were
- any `on*` event-handler attribute (400)

This area sits behind `_admin`'s password, the same trust level as its Config module, which edits `config.php` as raw JSON. "Trusted" and "well-formed" are different questions, though, and a tree carrying a tag the builder would never have produced is a bug worth refusing rather than writing to disk.

There is no backup step of its own - `/templates` is git-tracked project source, and `_editor`'s automatic backups already cover it.

## Testing

Two suites, because the builder is split across two languages:

- `php tests/templates-smoke.php` - the 76-definition library loader, every insertable block's `Parser`/`Serializer` round-trip (plus every shipped page template), the `#id` scan, and `documents/list`/`load`/`save` including every refusal.
- `node tests/templates-js-smoke.js` - the block mapping (`blocks.js`) and the tree edits (`tree.js`). Both modules are deliberately dom-free, which is what lets them run in plain node against a two-line `window` stub - no test framework, no browser. The harness hands them a `document` that throws on any property access beyond the two their IIFE dereferences, so a module that started using the dom would fail the suite rather than quietly become untestable.

Those two files hold the parts that can silently corrupt a template: a setting written back differently than it was read reshuffles class attributes, and an insert that mishandles whitespace leaves the file a little more crooked on every edit. What neither suite covers is the rendering itself (`canvas.js`, `inspector.js`), which is dom-bound.

## Roadmap

Delivered (patches 1-3):

- the `/_templates` app, its `_admin`-gated route, and the link from `/_admin`
- `Parser`/`Serializer` with the byte-exact round-trip guarantee
- the `library/<key>` block format, its loader and 76 definitions covering the `Nino.css` component catalogue; recognition-only helpers can stay out of the palette
- seven generated setting types, including `attrtoggle` for native boolean attributes such as `required`, `open`, `controls` and `allowfullscreen`
- the `#id` scan and warning
- `documents/list`/`load`/`save` and `library/blocks`/`parse`, including every refusal above
- the canvas with depth tinting and selection, the palette, the generated settings inspector, insert/move/duplicate/remove, and save with unsaved-changes guards

Still to come:

- a "new template" action, and `section-*` extraction from an existing selection
