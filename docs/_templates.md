# Nino — Template Builder Handbook
*[Deutsch](_templates.de.md)*

**Links:**
[README](../README.md) · [Design-Handbook](design.md) · [Developer-Handbook](development.md) · [_editor-Handbook](_editor.md) · [_admin-Handbook](_admin.md) · [_install-Handbook](_install.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

A graphical, developer-only editor for the project's own `templates/*.tpl` files, at `/_templates`. Optional - a `.tpl` is plain HTML and can just as well be written by hand (see `docs/design.md`) - but it turns "which grid classes does this column carry" into a form instead of a lookup in the design handbook.

> **Status.** This is the foundation: the builder **reads** templates, recognizes blocks, draws the structure and lists the library. Selecting, inserting and editing blocks land in the next patch - see "Roadmap" at the end.

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
- **Canvas** (middle) - the opened template as a tree of nested boxes.
- **Blocks** (right) - the library, grouped by each manifest's own `category`.

### What the canvas shows

Deliberately **not** a preview. Only the two things that genuinely can't be judged from a list of class names are drawn to scale:

- a **grid column's width** - its box is as wide as its `width` setting says, and a grid row lays its columns out side by side, exactly as the markup will
- **vertical spacing** - `ui-mt-*`/`ui-mb-*`/`ui-pt-*`/`ui-pb-*` are drawn as real margin and padding

Everything else is a labelled box: the block's name, its HTML tag, and a short preview of its text, link target or shortcode arguments. Colours, fonts and real typography are the theme's job (`docs/design.md`); reproducing them here would mean maintaining a second renderer that is wrong in a different way on every project.

Note that a column showing 100% is showing the truth: `ui-grid-100 ui-grid-l-50` *is* full width until the `l` breakpoint. The canvas draws the base width, and the breakpoint values sit in the settings.

### Markup the library doesn't know

Anything without a matching block still gets a box, labelled with its tag, id and classes (`<div id="hero" class="my-thing">`) and drawn with a dotted border. Those are structural only: the builder has no model for what their properties mean, so it offers no fields for them - but it never hides them, never drops them, and their children are still parsed and shown normally. A recognized block nested inside an unrecognized wrapper works exactly as it would anywhere else.

### The `#id` warning

A node whose `id` is targeted by a rule in the project's own stylesheets gets an orange badge.

This matters because of the core idea above: the builder presents an element's CSS classes as its properties, and a rule bound to an id overrides those classes in the real page while being completely invisible here. `#hero { padding: 0 }` beats `ui-pt-4` in the cascade, so the canvas would draw padding the browser will not render. The badge says "the class list is not the whole truth for this node" rather than quietly showing something wrong.

The scan reads every `.css` file listed in any of `config.php`'s `/nino/html/assets` bundles and collects the ids their selectors mention. It is a selector scan, not a full CSS parser - a false positive costs one unnecessary badge, which is the right way for it to be wrong.

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
    manifest.php      category, tag, name, match, children, use, settings, actions
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

Six types, all of them a two-way mapping between one form control and the node's own class list, attributes, tag or text:

| Type | Maps to | Declaration |
|---|---|---|
| `classenum` | one value out of a list, through a printf pattern | `'pattern' => 'ui-grid-%s'`, `'values' => [ '25', '50', … ]`, optionally `'bpPattern' => 'ui-grid-%b-%s'` + `'breakpoints' => [ 's','m','l','xl' ]` |
| `classgroup` | one class out of an explicit list (variants sharing no pattern) | `'options' => [ '' => 'Default', 'ui-btn--primary' => 'Primary', … ]` |
| `classtoggle` | a single class, on or off | `'class' => 'ui-section--fullwidth'` |
| `attr` | a plain HTML attribute | `'attr' => 'href'`, optionally `'values' => [ … ]` for a fixed set |
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

## Roadmap

Delivered here (patch 1 of the builder):

- the `/_templates` app, its `_admin`-gated route, and the link from `/_admin`
- `Parser`/`Serializer` with the byte-exact round-trip guarantee and its test suite
- the `library/<key>` block format, its loader and 21 core blocks
- the `#id` scan and warning
- `documents/list`/`load`/`save`, including every refusal above
- the canvas (read), the palette (list) and the class↔setting mapping in `blocks.js`

Still to come:

- selecting a node, and the settings inspector the mapping already supports
- inserting from the palette (click + ↑/↓ to move, matching `/_admin`'s Pages and `/_install`'s Webpages), remove and duplicate
- the remaining ~25 blocks of the `Nino.css` catalogue (table, pricing, accordion, gallery, tabs, modal, slider, timeline, badge, alert, breadcrumbs, list, logo strip, video, …)
- a "new template" action, and `section-*` extraction from an existing selection
