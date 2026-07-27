# Frontend Design System

Catalog of the frontend building blocks Nino ships built-in, no external
libraries. Companion to `_nino/Nino.css` / `_nino/Nino.js` /
`_nino/Nino.ui.js`.

## Architecture

```
_nino/Nino.css         Core: reset, grid, sections, buttons, ui-* styling.
                         Design tokens live in a :root { --token: value; }
                         block at the top.
_nino/Nino.js          Core: client detection, cookies, events, xhr, auth,
                         jstext. Used by _admin/_dev AND the public site.
_nino/Nino.ui.js       Core, public-site only: cover, parallax, vpa, slider,
                         forms, ... - split out so _admin/_dev stay lean.

assets/style.theme01.css Theme: per-project overrides via its own :root block -
assets/script.js         loads after Nino.css, so the overrides win the
                         cascade. Wired up in config.php's /nino/html/assets.

text/global.php          The few /ui/* fills that need to be _admin-editable
                         or that a stylesheet-less context (mail templates)
                         needs as literal values. Blacklisted from the admin
                         Text panel via text/blacklist.php.
```

There is deliberately no multi-theme layer - a project has exactly one
active look, set in its own `assets/style*.css`.

## Naming convention

- `ui-<block>` / `ui-<block>--<modifier>` / `ui-<block>-<element>` -
  BEM-ish, for anything CSS-only.
- `ui-<property>-<value>` - utilities (spacing, text-align, opacity).
- `js-<block>` - JS behavior hooks, separate from `ui-*` styling, so a
  theme can restyle freely without breaking behavior.
- `sc-<block>` - kernel-shortcode-generated markup (`[navigation]`,
  `[localepicker]`), fixed by `_nino/Nino.php` itself.

## Building blocks

Live, working versions of everything below: `/.demo-elements` (single
blocks) and `/.demo-sections` (ready-to-copy full sections).

### Frame / structural

| Block | Notes |
|---|---|
| Grid (`ui-grid-row`, `ui-grid-25/33/50/66/75/100`, `-s-/-m-/-l-/-xl-`) | Breakpoints 640/768/1024/1280px |
| Navigation (burger + regular) | `[navigation]` shortcode, `sc-nav-*` markup. A content line without `:` renders as a plain `<div>` (eg. for a logo) |
| ATF / Hero (`ui-atf(--fullscreen)`) | `--fullscreen` is the CSS-only fullscreen hero; `js-cover`/`js-parallex` use `data-cover-height="100"` instead. `main`'s default top padding is cancelled automatically when a cover/fullscreen hero is the first child |
| Sections (`ui-section`, `--dark/--black/--primary/--alt/--fullwidth`, `--border-1/2/3`) | |

### JS-driven behavior (`Nino.ui.js`)

| Block | Notes |
|---|---|
| Viewport animation (`js-vpa`, `--repeat`, effects `--zoom/--zoom-out/--slide-left/--slide-right/--blur/--flip` × `-soft/-medium/-hard`, `--speed-*`) | Effect/speed variants are pure CSS custom properties. Demo: `/.demo-vpa` |
| Autoheight (`ui-autoheight`, `data-autoheight-group`) | |
| Cover (`js-cover(--dim)`, `data-cover-height/-width`) | `--dim` adds a dark scrim for bright photos |
| Parallax (`js-parallex(--dim)`) | |
| Preloader (`js-preloader`) | Removed on `window.load`; markup in `html-footer.tpl` |
| Slider (`js-slider`) | Buttons/dot pagination are created by JS |
| Scroll header (`js-scroll-header`, `body.js-scroll-atf/-btf/-up/-down`) | Body classes are set unconditionally - other blocks (back-to-top) hook into them |
| Back-to-top (`js-back-to-top`) | Visibility via `body.js-scroll-btf`, CSS-only |
| Hero scroll-down arrow (`ui-atf-arrowdown`, `data-arrow-target`) | Chevron is a `currentColor` SVG background - no icon markup needed |
| Stat counter (`js-stat-counter`, `data-stat-counter-to/-suffix/-duration`) | Counts up once scrolled into view |
| Form handling (`ui-form`, posts to `/`; `js-newsletter-form`, posts to `/.newsletter`) | `ui-form` targets the contact form endpoint, `js-newsletter-form` the newsletter double opt-in signup |
| Tabs (`js-tabs`, `data-tabs-target`) | |
| Modal / Lightbox / Video (`js-modal`, `--lightbox`, `--video`, `js-modal-trigger`, `data-modal-target`) | Native `<dialog>` - focus trap, Escape and backdrop come from the browser |
| Toast (`js-toast-trigger`, `data-toast-message/-type`, or `Nino.ui.toast()`) | Inline `onclick` is CSP-blocked - this is the declarative way |

### Content components

| Block | Notes |
|---|---|
| Article/Card (`ui-article`, `--alt/--fullwidth`, `-cols(-s/m/l/xl)`, `-price`) | Always populated via the `elements` shortcode, never hardcoded |
| Buttons (`ui-btn`, `--primary/--outline/--light/--dark/--big/--small`) | |
| Icons (`ui-icon`, `.small`) | |
| Form field skins (`ui-form-input/-textarea/-select/-message`) | |
| Localepicker (`[localepicker]`, `sc-localepicker-*`) | |
| Badge (`ui-badge`, `--pill/--primary/--success/--error`) | |
| Breadcrumbs (`ui-breadcrumbs`) | |
| Alert (`ui-alert`, `--success/--error/--info`) | |
| Video embed (`ui-video`, `--4-3`) | |
| Table (`ui-table-wrap` > `ui-table`, `--striped/--bordered`) | Wrapper scrolls horizontally on narrow viewports |
| List (`ui-list`, `--check/--numbered`) | |
| Pricing table (`ui-pricing-row`, `ui-pricing-item(--featured)`) | Wraps an `[elements /pricelist ...]` call |
| Accordion (`ui-accordion`) | Native `<details>`/`<summary>`, same `name` = exclusive group |
| Pagination (`ui-pagination`) | Links only, no JS |
| Logo strip (`ui-logos`, `-item`) | |
| Gallery/Mosaic (`ui-gallery`, `-item`, `--wide/--tall`) | |
| Feature list (`ui-feature-list`, `-item`) | |
| Process timeline (`ui-timeline`, `-step`, `-number`) | |
| Video poster (`ui-video-poster`, `-play`) | Opens a `js-modal--video` lightbox |
| Cookie banner (`js-cookie-banner`) | `Nino.ui.cookieConsent.get()/.isAccepted()/.set()` is the API to gate tracking scripts on; `.set()` fires a `nino:cookieconsent` event |

### Utilities

`ui-m-/-mt-/-mb-/-ml-/-mr-/-p-/-pt-/-pb-0..6` (spacing),
`ui-text-left/center/right` (+ breakpoint prefixes), `ui-font-small/-big`,
`ui-opacity-10..90`, `ui-hidden`/`ui-invisible`/`ui-sr-only`/
`ui-hidden-*`/`ui-visible-*`, `ui-img-cover`/`ui-img-background(--dim)`.

## SEO / Social / AI

All driven by the same textfill mechanism content already uses - every key
is editable via `_admin`'s Text panel.

- `<title>`/`og:*`/`twitter:*` and `<meta name="description">` in
  `templates/html-header.tpl` resolve via
  `[[/webpage[[/nino/http/response/uri]]/title|description]]` - **every
  routed page needs both keys** in each locale file, there's no fallback
  (a missing key renders as the literal `[[...]]` string).
- `og:image`/`twitter:image` default to `/images/logo.png` - swap for a
  real 1200×630 share image per project.
- `<link rel="canonical">`/`og:url` use `[[/nino/http/request/uri]]` (the
  public-facing path), not the internal route target.
- JSON-LD `LocalBusiness` structured data is built from the `/company/*`
  fills.
- `robots.txt`/`sitemap.xml`/`llms.txt` are hand-listed templates routed in
  `config.php` with a `Content-Type` header override. `robots.txt` allows
  the major AI crawlers by default - flip any to `Disallow: /` to opt out.

## Demo pages

- `/.demo-elements` - every building block, one at a time.
- `/.demo-sections` - realistic, ready-to-copy full sections combining
  those blocks (heroes, grids, pricing, FAQ, gallery, timeline, contact/
  newsletter forms, ...).
- `/.demo-vpa` - the viewport-animation variants.

Dev-only reference tooling, not meant to ship to a real project. Demo
photography lives in `images/.demo/`.

## Markup essentials

The full markup for every block is best copied live from
`/.demo-elements`/`/.demo-sections` (`templates/.demo-*.tpl`). The
patterns that carry rules worth knowing:

**Headings:** `h1` is the hidden site title in `html-header.tpl` - never
add a second one. Every page gets exactly one `h2` (usually the
`ui-atf-title` of the opening hero). Section titles are `h3`, content
below nests without skipping levels.

**Grid:**

```html
<div class="ui-grid-row">
  <div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25">...</div>
</div>
```

**Hero (CSS-only fullscreen / cover with image):**

```html
<section class="ui-atf ui-atf--fullscreen ui-section--fullwidth ui-section--dark ui-text-center">
  <div class="ui-grid-row"><div class="ui-grid-100">
    <h2 class="ui-atf-title">...</h2>
    <p class="ui-atf-subtitle">...</p>
  </div></div>
</section>

<section class="ui-atf ui-section--fullwidth js-cover js-cover-center js-cover--dim" data-cover-height="100" style="color:var(--color-primary-text);">
  <img src="...">
  <div class="js-cover-content">
    <h2 class="ui-atf-title">...</h2>
  </div>
</section>
```

**Article/Card - always via `elements`, grid class on a wrapper `<div>`,
never on `<article>` itself:**

```html
[elements /portfolio]
<div class="ui-grid-100 ui-grid-m-33">
  <article class="ui-article">
    <div class="ui-article-content">
      <h4 class="ui-article-title">[[title]]</h4>
      <p class="ui-article-descr">[[description]]</p>
    </div>
  </article>
</div>
[/elements]
```

**Contact / newsletter forms:**

```html
<form class="ui-form">          <!-- posts to /, Shortcodes\Form -->
  [csrf]
  <input type="text" name="name" class="ui-form-input" required>
  <textarea name="message" class="ui-form-textarea" required></textarea>
  <p class="ui-form-message"></p>
  <button type="submit" class="ui-btn ui-btn--primary ui-form-submit">...</button>
</form>

<form class="ui-form js-newsletter-form" action="/.newsletter">  <!-- double opt-in signup -->
  [csrf]
  <input type="email" name="email" class="ui-form-input" required>
  <p class="ui-form-message"></p>
  <button type="submit" class="ui-btn ui-btn--primary ui-form-submit">...</button>
</form>
```

## Starter pages

`page-home.tpl`, `page-404.tpl`, `page-legal.{de_DE,en_US}.tpl` and
`page-newsletter.tpl` ship as real routes - placeholder-content starting
points meant to be edited (texts via `text/*.php`, sections copied from
`/.demo-sections`) rather than built from scratch. A section used on more
than one page belongs in its own `templates/section-*.tpl` partial,
included via `[template /templates/section-*]`.

## Gotcha: shortcode-shaped text in comments

Any text shaped like `[shortcodename ...]` gets parsed as a real shortcode
call - even inside a `/* CSS comment */` or `<!-- HTML comment -->`, in
every `.tpl` and in everything the asset pipeline bundles. Some shortcodes
(`elements`) error on garbage args and break the whole page render. Write
"the elements shortcode" in prose instead of bracket syntax. If a page
suddenly 500s after an edit, grep the file you touched for `[` first.