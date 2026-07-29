# Nino — Design Handbook
*[Deutsch](design.de.md)*

**Links:**
[README](../README.md) · [Developer Handbook](development.md) · [_admin Handbook](_admin.md) · [_dev Handbook](_dev.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

A catalogue of the built-in frontend building blocks; no external libraries.
Complements `_nino/Nino.css`/`_nino/Nino.js`/`_nino/Nino.ui.js` as well as
`docs/development.md` (how the rendering pipeline works under the hood —
this document assumes that knowledge and describes the design side of it:
which mechanisms a template author actually touches, and which building
blocks the built-in UI kit ships with).

## Part 1: Design-relevant mechanisms

Everything visible on a Nino page comes out of four mechanisms that can be
combined. `docs/development.md` describes them from the framework's point of
view (classes, callbacks, data flow); here the focus is on what they mean in
practice for a template author.

### Text fills — `[[key]]`

Fixed pieces of text, manageable via `/_admin` → "Texts". A fill is replaced
by a simple `str_replace()` against the text set of the current locale — no
escaping, no Markdown, no logic. A fill can itself contain `[[anotherKey]]`
(the replacement loop resolves that recursively); this is the pattern that
`text/global.php` uses for design tokens that reference one another (e.g. an
accent colour reused in several places).

**Two nuances that frequently surprise people when building templates:**

- A fill that exists in no `text/*.php` file is **not** replaced — it stays
  in the rendered HTML as a visible `[[/webpage/xyz/description]]` string.
  There is no fallback mechanism. Every newly routed page needs its `/title`
  and `/description` fills in **all** active locale files, otherwise the meta
  description breaks visibly.
- Purely technical values (colours, dimensions, `/ui/*` tokens) also arrive
  through the fill mechanism, but deliberately do **not** appear in the
  `/_admin` text panel — they live in `text/blacklist.php` and are thereby
  protected from accidental editing by editors, while remaining editable for
  developers in `text/global.php`.

### Shortcodes — `[name arg]content[/name]`

Anything that needs more than plain text (a loop over content, conditional
rendering, an embedded sub-template) is a shortcode. Unlike fills, shortcodes
are **callback invocations** — every shortcode name is ultimately
`Callbacks::doCallbacks( $appData, '/pino/html/shortcode/<name>', $args )`,
and the handler's return value is **rendered recursively again** (so fills and
further shortcodes inside it are resolved as well). That is why `[template x]`
works without `x.tpl` itself having to be pushed through the rendering
pipeline manually again — and at the same time the reason why a `[template x]`
*inside* `x.tpl` runs into an infinite loop (see the "Gotcha" warning below for
a related trap).

### Elements — recurring content

An element type (e.g. `/services`, `/team`, `/pricelist`) is a field model
defined by the developer in `/_dev`; the per-language data is maintained by an
editor via `/_admin` → "Elements". In the template, elements are never
hardcoded but always included via the shortcodes `[element uri="..."]` (a
single one) or `[elements uri="..." query="..."]` (a filtered list, rendered
once per match) — the enclosed content is a small, local mini-template in
which `[[title]]`, `[[description]]` etc. are the field values of **that
specific element**, not global text fills. This similarity in syntax
(`[[key]]` looks identical whether it is a global text fill or an element
field) is intentional, but the resolution happens at completely different
points in the pipeline — see the templating section of `docs/development.md`
for the exact order.

**Rule of thumb for this repository itself:** articles/cards are always built
via the `elements` shortcode, never with content hardcoded in the template —
even on the demo pages. The services and portfolio sections of `page-home.tpl`
follow this too (backed by the `services`/`portfolio` element types) instead of
hand-written `<article>` blocks.

### Locales — multilingual content

Every route can carry its own locale (`config.php`'s `/pino/http/routes`, key
`locale`); `[[key]]` fills always resolve against the *current* locale, and
elements carry their field data separately per locale within the same file.
The language switch itself runs through two widgets, both of which use the
same safe redirect mechanism (see the lifecycle section of
`docs/development.md` for the details on why this must not be a simple
`header('Location: ...')` call):

- `[localepicker]` — a ready-made language switcher widget
  (`sc-localepicker-*` classes, see below)
- The `/_nino/locales/current` query parameter, which `Locales::request()`
  evaluates directly — for a custom language switcher not styled by the kernel

Both find the *equivalent* route in the target language via
`Http::findRouteUri()` (not simply the same URL with a different locale flag) —
so `/legal` and `/rechtliches` can be different URLs for the same page in
different languages.

## Part 2: Core content in detail

### Architecture

```
_nino/Nino.css       Core: reset, grid, sections, buttons, ui-* behaviour
                        support CSS. Design tokens (colour/spacing/typography/
                        radius) live in a :root { --token: value; } block at the
                        very top of the file — never as hardcoded values
                        scattered across the rest of the file.
_nino/Nino.js         Core: client detection, cookies, DOM ready/resize/scroll
                        events, XHR, auth, jstext. Used by _admin/_dev as well
                        as by the public site.
_nino/Nino.ui.js      Core, public site only: cover, parallax, VPA, autoheight,
                        slider, scroll header, generic .ui-form handling. Never
                        referenced by _admin/_dev — deliberately split out so
                        that their bundle stays lean.

assets/style.theme01.css  Theme: project-specific overrides. A project overrides
assets/script.js           the tokens it needs in its own :root { ... } block
                            (see the "00 Theme" section of _nino/Nino.css) — CSS
                            custom properties resolve at computation time via
                            the cascade, so no separate load-order or build-step
                            logic is needed, only loading after Nino.css. Which
                            theme file is active per project is controlled by
                            config.php (/pino/html/assets).

text/global.php         Contains only the handful of /ui/* fills that either
                        must remain _admin-editable, or that a context without
                        its own stylesheet (e.g. templates/mail-header.tpl, a
                        self-contained email document) needs as a literal value.
                        A token can be declared as --token: [[/pino/path]]; in a
                        :root block instead of a literal value at any time, as
                        soon as a project wants it admin-editable — the same
                        fill mechanism that text content uses. Every remaining
                        /ui/* key is hidden from the admin text panel by default
                        (text/blacklist.php), since these are developer design
                        tokens, not page content.
```

Load order matters: `_nino/Nino.css` before `assets/style.theme01.css`, so that
a project's `:root` overrides win the cascade. Registered in `config.php`'s
`/pino/html/assets`.

There is deliberately no multi-theme/theme-switching layer — a project has
exactly one active look, set directly in the `:root` block of its own
`assets/style*.css` (plus `text/global.php` for the rare token that should
remain _admin-editable). An earlier version of this framework had one (a
`/theme/<name>/` directory + a `/pino/theme/fills` config key, merged before
`global.php`), removed before the alpha release: it was developer tooling
disguised as a feature — no admin-side switch, never a real second theme
shipped — and sitting there unused looked more unfinished than flexible. A
developer who wants to compare two looks can still do so by hand (two copies of
`assets/style*.css`, swap and diff), with no framework support for it at all.

### Naming convention

- `ui-<block>` / `ui-<block>--<modifier>` / `ui-<block>-<element>` — BEM-like,
  for everything purely CSS-related (sections, buttons, articles, grid).
- `ui-<property>-<value>` — utilities (spacing, text alignment, opacity).
- `js-<block>` — JS behaviour hooks, separate from `ui-*` styling, so that a
  theme can restyle freely without ever risking behaviour, and core JS never
  relies on a name the theme might rename. **Implemented**: `cover`,
  `parallex`, `slider`, `vpa` are `js-*` (`Nino.ui.js` looks for
  `.js-cover`/`.js-parallex`/`.js-slider`/`.js-vpa`, and CSS matches the same
  classes). `autoheight` and the generic contact form handling deliberately
  stayed `ui-*` (`.ui-autoheight`, `.ui-form`) — they are markup/styling
  concerns rather than behaviour toggles, so they were not renamed.
- `sc-<block>` — markup generated by kernel shortcodes (`[navigation]`,
  `[localepicker]`), since this HTML ships hardwired with `_nino/Nino.php`
  itself and is not project-customisable. Both the PHP templates of these two
  shortcodes and their CSS use this prefix (`sc-nav-wrap`, `sc-nav-content`,
  `sc-localepicker-wrap`, ...).

### Building blocks

Every building block below is fully wired up and ready to use.

**Frame/structure**

| Block | Notes |
|---|---|
| Grid (`ui-grid-row`, `ui-grid-25/33/50/66/75/100`, `-s-/-m-/-l-/-xl-` breakpoints) | Breakpoints at 640/768/1024/1280px |
| Reset | System font stack as the default (`html { font-family: ... }` in `_nino/Nino.css`) — no font file needs to ship per project. A theme stylesheet can still bring its own `@font-face`/`font-family` rules (`assets/style.theme01.css` does exactly that) |
| Navigation (burger + regular) | The output of `Navigation::doShortcode()` and the CSS both use the `sc-*` prefix (`sc-nav-wrap`, `sc-nav-burger`, `sc-nav-regular`, `sc-nav-content`, `sc-nav-bg`). A `[navigation]` content line without a `:` renders as a plain `<div>` instead of a nav `<li>` (`Navigation::$html['div']`) — this allows arbitrary markup (e.g. a logo image) to be mixed in between `uri:title` link lines. Within the burger variant these additional `<div>`s are hidden as long as the overlay is closed |
| ATF/hero (`ui-atf(--fullscreen)`, `ui-cover`) | `--fullscreen` (`min-height:100vh`, flex-centred) is the pure-CSS fullscreen hero variant for an image-less `ui-atf`; `js-cover`/`js-parallex` achieve the same effect via `data-cover-height="100"`, since they are dimensioned by JS anyway |
| Sections (`ui-section`, `--dark/--black/--primary/--alt/--fullwidth`, `--border-1/2/3`) | Every colour variant additionally sets a normal `a:not(.ui-btn)` link colour — `a { color: inherit }` alone is not specific enough to reliably win against other `a` rules in the cascade |

**JS-driven behaviour (`Nino.ui.js`)**

| Block | Notes |
|---|---|
| Viewport animation (`js-vpa`, `--repeat`, effect `--zoom`/`--zoom-out`/`--slide-left`/`--slide-right`/`--blur`/`--flip` × `-soft`/`-medium`/`-hard`, speed `--speed-fast`/`-medium`/`-slow`) | Effect/speed variants are pure CSS (custom properties); no JS change needed. Demo: `/demo-vpa` |
| Autoheight (`ui-autoheight`, `data-autoheight-group`) | Deliberately kept the `ui-*` prefix (see naming convention) — more of a markup/styling concern than a behaviour toggle |
| Cover (`js-cover(--dim)`, `data-cover-height/-width`) | `data-cover-height="100"` for a fullscreen hero. `--dim` lays a dark scrim over it (see Utilities/image tools' `ui-img-background--dim` for the same idea on a static image) — necessary as soon as a hero uses a real (bright) photo instead of a dark placeholder, so that the white `ui-atf-title`/`-subtitle` text stays legible |
| Parallax (`js-parallex(--dim)`) | The same `--dim` scrim as `js-cover`, for the same reason |
| Preloader (`js-preloader`) | Fullscreen spinner overlay, removed on `window.load`. The markup sits in `templates/html-footer.tpl` directly before `[jstext]` |
| Slider (`js-slider`, touch swipe, prev/next `js-slider-button`s) | Prev/next buttons and dot pagination (`js-slider-points`) are generated by JS, not hand-written |
| Scroll header (`js-scroll-header`, `body.js-scroll-atf/-btf/-up/-down`) | Shows/hides the header depending on scroll position/direction. The scroll state classes on `body` are set unconditionally (not only when `.js-scroll-header` exists), so that other elements — e.g. back-to-top — can hook into them as well |
| Back-to-top button (`js-back-to-top`) | Visibility is pure CSS, driven by `body.js-scroll-btf` (appears after scrolling past the fold); JS only handles the click (`window.scrollTo` smooth) |
| Hero scroll arrow (`ui-atf-arrowdown`, `data-arrow-target`) | The chevron is a `currentColor` SVG used as a `background-image` (see `_nino/Nino.css`), so it needs nothing beyond the class and a `data-arrow-target` CSS selector attribute — no icon markup, no separate asset |
| Stat counter (`js-stat-counter`, `data-stat-counter-to/-suffix/-duration`) | Counts up from 0 once scrolled into the viewport (the same `getBoundingClientRect` check as `js-vpa`), animated via `requestAnimationFrame` |
| Generic form handling (`ui-form`, posts to `/`) | Kept `ui-*` (see naming convention) — automatically targets the `POST /` endpoint of `Shortcodes\Form`; no shortcode needed |
| Tabs (`js-tabs`, `js-tabs-nav`/`-tab`, `js-tabs-panel`, `data-tabs-target`) | On switching, the panel fades in via `@starting-style`/`transition-behavior:allow-discrete` (pure CSS) — falls back to an instant show/hide in browsers without support |
| Modal / lightbox / video (`js-modal`, `--lightbox`, `--video`, `js-modal-trigger`, `js-modal-close`, `data-modal-target`) | Built on the native `<dialog>` element (`showModal()`/`.close()`) instead of hand-rolled JS — focus trapping, escape-to-close and the backdrop click area therefore come for free from the browser |
| Toast (`js-toast-trigger`, `data-toast-message`/`-type`, or `Nino.ui.toast(message, type)` from your own JS) | Inline `onclick` does not work here — the site sends a `Content-Security-Policy: script-src 'self' 'nonce-...'` header that blocks inline event handlers without a matching nonce, and there is no generally available nonce fill for templates. `js-toast-trigger` is the CSP-safe declarative way to fire a toast from static markup |

**Content components**

| Block | Notes |
|---|---|
| Article/card (`ui-article`, `--alt`/`--fullwidth`, `-cols`/`-cols-s/m/l/xl`, `-price`) | `-price` is intended for a priced item shown as a regular article grid card — different from `ui-pricing-price` (pricing table, below), which is for a dedicated price table |
| Buttons (`ui-btn`, `--primary/--outline/--light/--dark/--big/--small`) | `--big`/`--small` scale padding/font size/radius via `calc()` from the same base, instead of separate literal values |
| Icons (`ui-icon`, `.small`) | |
| Form field skins (`ui-form-input/-textarea/-select/-message`) | Styling only — see the contact form below for the sending mechanism |
| Locale picker | The kernel shortcode `[localepicker]` and the CSS both use the `sc-*` prefix (`sc-localepicker-wrap`, `sc-localepicker-bg`) |
| Contact form | No dedicated shortcode — write a `<form class="ui-form">` with the right field names directly in the template (`page-home.tpl` does exactly that). The generic `.ui-form` handling in `Nino.ui.js` already targets the `POST /` endpoint of `Shortcodes\Form`. One shared mailbox/config per project |
| Badge/pill (`ui-badge`, `--pill/--primary/--success/--error`) | |
| Breadcrumbs (`ui-breadcrumbs`) | `›` separator via `::after` content, the last `li` styled as the current page |
| Alert/inline feedback (`ui-alert`, `--success/--error/--info`) | A static banner for arbitrary page messages — different from `.ui-form-message`, the contact form's own submit feedback paragraph |
| Video embed wrapper (`ui-video`, `--4-3`) | Responsive iframe/video wrapper, 16:9 by default. Uses `aspect-ratio` instead of the classic `padding-top` percentage hack, since that percentage resolves against the width of the *containing block*, not against the element's own `max-width`-constrained width |
| Table (`ui-table-wrap` > `ui-table`, `--striped`, `--bordered`) | `ui-table-wrap { overflow-x:auto }` around the `<table>`, so that a wide table scrolls horizontally on a narrow viewport instead of forcing the whole page wide — the same logic as the grid. `--striped` also tints `tbody` rows with `--color-section-alt-bg` — put a striped table on a plain `ui-section`, not on `ui-section--alt`, otherwise the tint disappears into the section background |
| List (`ui-list`, `--check`, `--numbered`) | Different from breadcrumbs/pagination (structural nav lists with a fixed shape). The `--check`/`--numbered` markers are `::before` (a Unicode `✓` and a CSS `counter()` respectively), not an `<img>`/SVG |
| Pricing table (`ui-pricing-row`, `ui-pricing-item(--featured)`, `-title`, `-price`) | No fixed markup/shortcode — card styling intended to wrap an `[elements type="pricelist" content="..."]` call |
| Accordion (`ui-accordion`, `-trigger`, `-panel`) | Pure `<details>`/`<summary>`, no JS — several `<details>` sharing the same `name="..."` attribute behave natively as an exclusive group in current browsers |
| Pagination (`ui-pagination`) | Page number links only, no JS — which page is active and how many there are is a template/backend concern. Different from the slider dot pagination (`js-slider-points`), which is JS-driven |
| Logo/partner strip (`ui-logos`, `-item`) | Text placeholders stand in for real logo SVGs/PNGs — greyscale, full colour on hover |
| Gallery/mosaic grid (`ui-gallery`, `-item`, `--wide`, `--tall`) | CSS grid, fixed `grid-auto-rows`, individual tiles widened/heightened via the modifiers |
| Feature list (`ui-feature-list`, `-item`) | Icon + heading + text rows, intended for one half of the existing 50/50 grid pattern (image in the other half) |
| Process timeline (`ui-timeline`, `-step`, `-number`) | Numbered circles connected by a line (`::before` on every step except the first) — the line only renders from the `m` breakpoint upwards |
| Video poster (`ui-video-poster`, `-play`) | Static poster image with a play icon; opens the real `.ui-video` embed in a `js-modal--video` lightbox on click |
| Cookie consent banner (`js-cookie-banner`, `-actions`) | Bottom bar, not a fullscreen overlay. State lives in `Nino.cookie` (`Nino.js`) under a `pino_consent` key; `Nino.ui.cookieConsent.get()`/`.isAccepted()`/`.set()` is the public API that a custom analytics/tracking script should gate against before loading anything non-essential |

**Utilities**

| Block |
|---|
| Spacing (`ui-m-/-mt-/-mb-/-ml-/-mr-/-p-/-pt-/-pb-0..6`) |
| Text alignment (`ui-text-left/center/right`, `-s-/-m-/-l-/-xl-`) |
| Font size (`ui-font-small/-big`) |
| Opacity (`ui-opacity-10..90`) |
| Visibility (`ui-hidden`, `ui-invisible`, `ui-sr-only`, `ui-hidden-*`/`ui-visible-*`) |
| Image tools (`ui-img-cover`, `ui-img-background(--dim)`, `-content`) |

### SEO / social / AI

All of it goes through the same text fill mechanism that content uses (see
above) — no separate config interface; every key below is automatically
editable through the existing text panel of `_admin` as soon as it exists in
`global.php`/`{locale}.php`.

- `<title>`/`og:title`/`twitter:title` and `<meta
  name="description">`/`og:description`/`twitter:description` in
  `templates/html-header.tpl` all resolve via
  `[[/webpage[[/pino/http/response/uri]]/title|description]]` — the same
  per-page lookup pattern that `/title` already used, extended by a sibling
  `/description` key. **Every routed page needs both keys** — unlike a missing
  template variable, there is no fallback: `Html::_renderFills()` is a plain
  `str_replace()` against known keys, so an undefined one stays in the output
  as a literal `[[/webpage/.../description]]` string instead of raising an
  error.
- `og:image`/`twitter:image` fall back to `/images/logo.png` by default (with
  `https://` + `/website/url` + `/pino/dir` as the prefix, since social
  platforms expect an absolute URL) — swap in a real 1200×630 share image per
  project.
- `<link rel="canonical">`/`og:url` use `[[/pino/http/request/uri]]` (the path
  that actually came in, e.g. `/kontakt`), not `/pino/http/response/uri` (the
  internal routing target, e.g. `/contact` internally for the same request) —
  the two differ on locale-routed pages, and canonical needs the publicly
  visible variant.
- JSON-LD `LocalBusiness` structured data (`templates/html-header.tpl`) — read
  by search engines and increasingly by AI assistants/agents for structured
  facts, built from the same `/company/*` fills that the footer already
  renders.
- `robots.txt`/`sitemap.xml`/`llms.txt`
  (`templates/robots.tpl`/`sitemap-xml.tpl`/`llms-txt.tpl`, routed in
  `config.php` like any other page, only with a `header` → `Content-Type`
  override instead of the usual `text/html`). By default `robots.txt` allows
  the major AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Google-Extended,
  CCBot) so that the site can be found/cited via AI search — switch individual
  ones to `Disallow: /` to exclude that bot. `sitemap.xml` lists only the
  site's native locale (`/pino/locales/native`), no locale alternatives.

### Demo pages

- `/demo-elements` (`templates/.demo-elements.tpl`) — every single building
  block from the tables above, individually. The living version of the
  "Markup reference" section below.
- `/demo-sections` (`templates/.demo-sections.tpl`) — realistic,
  copy-paste-ready, complete sections combining these building blocks
  (fullscreen hero with and without an image, parallax quote, 50/50
  image+text grids in both directions plus a full-bleed variant, article
  grids, statistics row, pricing table, comparison table, feature checklist,
  contact form, CTA banner, FAQ accordion, logo/partner strip, image
  gallery/mosaic, feature split, process timeline, video poster + lightbox,
  full-bleed text banner). The photos come from `/images` — dummy stock photos
  (`<id>-<width>x<height>.jpg`, five aspect ratios: 16:9, 4:3, 1:1, 3:4, 21:9),
  to be swapped for real project photos as soon as a section moves from the
  demo onto a real page.

Both pages link to each other. Neither is intended for live operation of a real
project — both are pure developer reference tooling and are deliberately
omitted from `sitemap.xml`.

`.ui-logo` (`templates/html-header.tpl`/`html-footer.tpl`) references
`images/logo.png`, which does not yet exist in the repository by default — both
places already point at it (including an `alt` attribute), so dropping a
`logo.png` into `/images` is the only remaining step once one is available.

### Markup reference

A living, working version of everything below is available at `/demo-elements`
(`templates/.demo-elements.tpl`) — keep it in sync with `.demo-elements.tpl`
whenever either one changes.

**Grid**

```html
<div class="ui-grid-row">
  <div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25">...</div>
  <div class="ui-grid-100 ui-grid-m-50 ui-grid-l-25">...</div>
</div>
```

50/50 image+text, with the image running to the viewport edge instead of
sitting inside the row padding:

```html
<section class="ui-section ui-section--fullwidth">
  <div class="ui-grid-row ui-grid--fullwidth ui-grid-middle">
    <div class="ui-grid-100 ui-grid-m-50 ui-img-cover">
      <img src="..." style="height:480px;">
    </div>
    <div class="ui-grid-100 ui-grid-m-50">
      <article class="ui-article">
        <div class="ui-article-content">...</div>
      </article>
    </div>
  </div>
</section>
```

**ATF/hero + cover/parallax**

```html
<!-- h2 if this hero is the page's own headline, h3 when the page has
     several sections -->
<section class="ui-atf ui-atf--fullscreen ui-section--fullwidth ui-section--dark ui-text-center">
  <div class="ui-grid-row">
    <div class="ui-grid-100">
      <h2 class="ui-atf-title">...</h2>
      <p class="ui-atf-subtitle">...</p>
    </div>
  </div>
</section>
```

Cover/parallax, fullscreen via `data-cover-height="100"`, `--dim` for a dark
scrim over a real (bright) photo:

```html
<section class="ui-atf ui-section--fullwidth js-cover js-cover-center js-cover--dim" data-cover-height="100" style="color:var(--color-primary-text);">
  <img src="...">
  <div class="js-cover-content">
    <h2 class="ui-atf-title">...</h2>
    <p class="ui-atf-subtitle">...</p>
  </div>
</section>

<section class="js-parallex js-parallex--dim" style="color:var(--color-primary-text);">
  <img src="...">
  <div class="js-cover-content">...</div>
</section>
```

The inline `color` is the framework-guaranteed way of keeping hero text legible
over a `--dim` scrim — `_nino/Nino.css` itself only styles the scrim
(`::before`), not the text.

**Sections**

```html
<section class="ui-section ui-section--alt"> <!-- or --dark / --black / --fullwidth -->
  <h3 class="ui-section-title">...</h3>
  <p class="ui-section-subtitle">...</p>
</section>
```

**Buttons / icons**

```html
<a href="#" class="ui-btn ui-btn--primary">...</a> <!-- --outline / --light / --dark / --big -->
<svg class="ui-icon small" ...>...</svg>
```

**Article/card (always via `elements`)**

**Never put grid width classes directly on `<article>`** — wrap it in a plain
`<div>` carrying the grid class; `<article>` only carries `ui-article` and its
modifiers:

```html
[elements /portfolio]
<div class="ui-grid-100 ui-grid-m-33">
  <article class="ui-article"> <!-- ui-article--alt for the alt variant -->
    <div class="ui-article-content">
      <h4 class="ui-article-title">[[title]]</h4>
      <p class="ui-article-descr">[[description]]</p>
    </div>
  </article>
</div>
[/elements]
```

**Pricing table (via `elements`)**

```html
<div class="ui-pricing-row">
  [elements /pricelist query="cat=standard"]
  <div class="ui-pricing-item">
    <h4 class="ui-pricing-title">[[title]]</h4>
    <p class="ui-pricing-price">[[price]] &euro;</p>
    <span class="ui-badge">[[cat]]</span>
  </div>
  [/elements]
</div>
```

**Form**

```html
<form class="ui-form">
  <label for="name">Name</label>
  <input type="text" id="name" name="name" class="ui-form-input" required>
  <textarea name="message" class="ui-form-textarea" required></textarea>
  <p class="ui-form-message"></p>
  <button type="submit" class="ui-btn ui-btn--primary ui-form-submit">Send</button>
</form>
```

**Toast**

```html
<!-- declarative, from static markup -->
<button type="button" class="js-toast-trigger" data-toast-message="Saved." data-toast-type="success">Save</button>
```

```js
// or imperative, e.g. after a custom XHR call — from assets/script.js,
// not from an inline <script> tag (the CSP nonce is internal to the
// jstext shortcode, so inline script tags in templates will not run)
Nino.ui.toast( 'Saved.', 'success' );
```

For further building blocks (badge, breadcrumbs, alert, list, table, video
embed, accordion, tabs, modal/lightbox, slider, viewport animation, autoheight,
stat counter, back-to-top, preloader, scroll header, cookie banner, pagination)
see the living reference at `/demo-elements` — the markup and class names
follow exactly the pattern of the building block tables above.

### Site structure: starter pages

`page-home.tpl`, `page-services.tpl`, `page-about-me.tpl`, `page-contact.tpl`,
`page-404.tpl` and `page-legal.{de_DE,en_US}.tpl` ship as real routes
(`config.php`'s `/pino/http/routes`) — a starting point with placeholder
content for a new project rather than empty stubs, meant to be edited (text via
`text/*.php`, sections swapped in/extended from `/demo-sections`) rather than
built from scratch.

A section that appears identically on more than one page is extracted into its
own `templates/section-*.tpl` partial and included via
`[template /templates/section-*]`, the same include mechanism that
`[template /templates/html-header]`/`html-footer` use — `section-contact.tpl`
(address + contact form) is the first of these, shared between
`page-contact.tpl` and the closing CTA section of `page-home.tpl`. Prefer this
over copying a section's markup onto a second page.

## Part 3: Developing your own design shortcodes

A design shortcode is structured exactly like any other module from the
"Developing your own modules" section of `docs/development.md` — there is no
separate "frontend shortcode API". A minimal custom shortcode for, say, a team
member grid with context-dependent markup:

```php
<?php
declare(strict_types=1);

namespace MyProject\Modules {

    class TeamGrid {

        public static function init( array &$appData ): void {
            \Nino\Html::addShortcode( $appData, 'teamgrid', [ self::class, 'doShortcode' ] );
        }

        public static function doShortcode( array &$appData, array $args ): string {

            $columns = $args['columns'] ?? '3';
            $html    = '<div class="ui-grid-row ui-team-grid" data-columns="'. htmlspecialchars( $columns ). '">';
            $html   .= '[elements uri="/team"]'. $args['content']. '[/elements]';
            $html   .= '</div>';

            return $html;
        }
    }
}
```

Once registered in `config.php`'s `/pino/modules`, `[teamgrid
columns="4"]<article class="ui-article">...</article>[/teamgrid]` is usable from
the next request onwards. Important points to keep in mind, derived directly
from the templating pipeline (see `docs/development.md`):

- **The return value is rendered again automatically.** The handler above
  returns a raw `[elements uri="/team"]...[/elements]` fragment, not finished
  HTML — after the call, the shortcode dispatcher calls `renderHtml()` on the
  return value again by itself, thereby resolving the embedded `[elements]` too.
  A custom design shortcode can therefore be composed of other shortcodes
  without ever calling `Html::renderHtml()` itself.
- **`$args['content']` is the enclosed content raw and unprocessed.** Anyone
  embedding it directly (as above) gets the automatic post-render pass for
  free; anyone manipulating it (e.g. extracting a subset) remains responsible
  for ensuring the result is still valid shortcode/fill markup.
- **Always escape user data in generated markup** (`htmlspecialchars()`, as
  with the `columns` argument above) — a shortcode argument can in principle
  originate from an admin-editable text source, if the template itself inserts
  values from `[[key]]` into an argument.
- **Choose CSS classes according to the naming convention** (see part 2 above):
  `js-*` only for actual behaviour, `ui-*` for styling, no custom `sc-*` —
  that prefix is reserved for the kernel's own shortcodes.
- **A custom shortcode can bring its own CSS/JS** — simply add it to
  `assets/style.theme01.css`/`assets/script.js` (or add your own files to the
  `/pino/html/assets` bundle in `config.php`); no separate build step is
  needed, see "Architecture" above.

## Gotcha: shortcode-shaped text in comments, everywhere

Any comment or string that looks like `[shortcodename ...]` is parsed as a real
shortcode call — `Html::renderHtml()` neither knows nor cares whether it sits
inside a `/* CSS comment */` or an `<!-- HTML comment -->`, it simply scans the
entire content by regex. `Assets::_createCachefile()` therefore pushes the
**entire** concatenated CSS/JS content through it (fills **and** shortcodes)
before it is cached, and every `.tpl` template goes through it on every render,
without exception.

This has already happened twice in this project, both times in a comment that
mentioned the `elements` shortcode in passing with square brackets. Both times
`elements` ran with an empty/broken type argument and triggered a
`trigger_error()`, which broke the **entire** page rendering, not just the
commented section. `[navigation]`/`[localepicker]` appear harmlessly in
existing section header comments in `Nino.css`, because those two shortcodes
simply do nothing silently when their args are broken or missing —
`elements`/`element` do not, so "it worked in this other comment" is not a
reliable signal.

**Avoid `[word ...]` bracket syntax in comments in any `.tpl`/`.css`/`.js`
file that passes through the asset pipeline or the template renderer** — write
"the elements shortcode" in prose instead of `[elements ...]`. If a page
suddenly 500s with a `trigger_error` dump instead of rendering, search the
most recently edited file for `\[` first.
