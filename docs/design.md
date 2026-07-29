# Nino — Design Handbook
*[Deutsch](design.de.md)*

**Links:**
[README](../README.md) · [Developer Handbook](development.md) · [_admin Handbook](_admin.md) · [_dev Handbook](_dev.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

## Introduction

Everything that becomes visible on a Nino page comes from three combinable mechanisms - text fills, shortcodes, elements - plus a fixed set of CSS classes.
`docs/development.md` describes these mechanisms from a developer's point of view (classes, callbacks, data flow).
Here we look at it from the perspective of frontend design.

### Basic concept of the Nino design system
Nino works with plain HTML, extended by three mechanisms. Logic is deliberately kept separate from design. Dynamic rendering becomes possible through these additional mechanisms:
#### Text fills
Every Nino website has a collection of texts following the pattern
`[[key]] => value`.
This is split into **global values** (the same across all languages) and **local values** (different per language). Developers and administrators can edit this text collection at any time via `_dev` and `/_admin`.
The developer creates text building blocks for this in `_dev` - via `_admin` they can then be filled in, depending on the setting, either globally or for all allowed languages.
Text fills can be **any length**. They should, however, **not contain HTML markup**.
In templating, this lets you enter **language-relevant, recurring text** as a fill that gets replaced with the matching client locale on every render.

The convention behind this is - instead of:
`<h2 class="ui-atf-title">Welcome to: www.example.com.</h2>`
you write:
`<h2 class="ui-atf-title">[[/page-home/atf/title]]: [[/company/website]]</h2>`
A **base set** of global and local text fills already ships in the **starter repo**. Developers/designers are **free to define and create** further text fills as they see fit.
There is, however, a small number of text fills reserved by Nino:

**Text fills - kernel / website**
| Fill | e.g. | Value / defined in | Usage |
|---|---|---|---|
| `[[/nino/http/request/uri]]` | `/kontakt` | computed by Nino | the URI actually requested |
| `[[/nino/http/response/uri]]` | `/contact` | computed by Nino | the internally resolved route URI |
| `[[/nino/http/response/locale]]` | `de_DE` | computed by Nino | the resolved locale of the response |
| `[[/nino/auth/user]]` | `changeme@domain.com` | computed by Nino | email of the logged-in user, otherwise `''` |
| `[[/nino/dir]]` | `/www/nino` | computed by Nino | directory prefix of the installation |
| `[[/date/year]]` | `2026` | computed by Nino | current year |
| `[[/website/lang]]` | `de` | `text/{locale}.php` | `<html lang="...">` |
| `[[/website/charset]]` | `UTF-8` | `text/global.php` | `<meta charset>`/Content-Type (header, mail) |
| `[[/website/author]]` | `Max Mustermann` | `text/global.php` | `<meta name="author">`, imprint |
| `[[/website/host]]` | `Meinhost Gbr` | `text/global.php` | hosting details in the imprint |
| `[[/website/url]]` | `www.max-mustermann.com` | `text/global.php` | base domain for all absolute URLs (canonical, og:url, sitemap.xml, llms.txt, mail subjects) |

#### Text fills - individual webpage
Every webpage *(e.g. `/kontakt`)* additionally needs a set of fixed local (!) text fills in order to be fully integrated. These are used in the Nino navigation and in the default HTML head.

**Note:
For text content on the page (e.g. `hero-title`), the recommended convention is
`[[/page-<uri>/category/element]]`
e.g. `[[/page-home/hero/title]]`**

| Fill | Example | Value / defined in | Usage |
|---|---|---|---|
| `[[/webpage/<uri>/uri]]` | `/contact` | `text/{locale}.php`<br>per page key | route URI of the page (links, navigation, sitemap.xml) |
| `[[/webpage/<uri>/name]]` | `Contact us!` | `text/{locale}.php`<br>per page key | link text (navigation, footer, llms.txt) |
| `[[/webpage/<uri>/title]]` | `Contact us - Contact form` | `text/{locale}.php`<br>per page key | `<title>`/og:title/twitter:title, via `[[/webpage[[/nino/http/response/uri]]/title]]` |
| `[[/webpage/<uri>/description]]` | `Our contact data and contact form.` | `text/{locale}.php`<br>per page key | meta/OG/Twitter description, same nesting pattern |

`<uri>` always stands for the `uri` key from `$appData['/nino/http/routes']`
*(for `/contact`, e.g. `[[/webpage/contact/uri]]`)*

#### Shortcodes
Nino shortcodes are the **connection between design and logic**. Via PHP, developers can register a shortcode following the convention
`[myshortcode]` with a callback - its return value is then automatically substituted in the template.
For example, `[localepicker]`
automatically becomes, during HTML rendering,
`<div class="sc-localepicker-wrap">.....</div>`

Nino already ships a small range of modules with shortcodes.
*(See below, or the `\Nino\Modules` chapter of the [Developer Handbook](development.md))*. To use them, the modules must be enabled by the developer in `config.php`. Details on that are in the handbook.
Further shortcodes can easily be added via PHP modules.

#### Elements
Elements are Nino's solution for **recurring data** following a fixed data model.
This is suited, for example, to services, partners, news, blog posts, etc. The developer creates an **element type** via `_dev` with a URI *(e.g. /services)* and fields *(e.g. title, descr, eventdate, price)*. Any number of **elements** can then be created via `_admin`. Every element likewise gets a URI *(e.g. /webdesign)* that combines with the type URI *(/services/webdesign)*.

The template engine provides two shortcodes for rendering elements:

`[element /services/webdesign]...[/element]`
outputs a **specific element** by its URI. The HTML enclosed by the shortcode tag serves as the template for rendering it. Inside it, the element's fields are **automatically substituted as text fills**. *(e.g. `[[title]]` becomes `Webdesign`)*

`[elements /services limit="4"..]...[/elements]`
works on the same concept - except that **several elements** of the type at the given URI are shown. The syntax for controlling this is in the [Developer Handbook](development.md).
In this case, the enclosed HTML is repeated for **every element** and filled in accordingly.

**Recommendation for Nino development:** articles/cards are always built via the `elements` shortcode, never with content hardcoded in the template - not even on the demo pages. A services or portfolio section follows the same rule (backed by the `services`/`portfolio` element types) instead of hand-written `<article>` blocks.

### Template rendering

The technical implementation of this simple template engine sits in the kernel method `\Nino\Html::renderHtml( $appData, $html )`.
This runs **automatically** every time a template is loaded, and in fixed order always does three things:

1. **Resolve text fills** - every `[[key]]` placeholder is replaced via `str_replace()` against the current text set.
2. **Run shortcodes** - every `[name arg]content[/name]` call is picked up by a regex and passed to the registered callback of that shortcode.
3. **`/nino/html/render` callbacks** - any further, project-owned callbacks receive the finished HTML string for modification.

After that, `renderHtml()` is **called again, recursively** - regardless of what the shortcode returned - until every bit of content has been resolved and nothing more changes in the HTML.
There is no compile step and no cache build at development time - every request renders live against the current state of the `.tpl`, `text/*.php` and `elements/*.php` files.

**Important in practice:** a fill or shortcode name that doesn't exist breaks nothing - it simply stays in the delivered HTML as a visible `[[key]]` or `[name]` string. There is no fallback and no error message at this point.
This means you can work in the design with placeholder text fills, e.g. `[[/page-home/hero/title]]`, or `[my_shortcode]`, and fill them in afterwards.

#### Template files (.tpl)
To clearly set them apart from `.html`, Nino uses `.tpl` files. These all (!) live in `/templates` and are **plain text files with HTML content** - no PHP, no logic, no syntax of their own beyond text fills (`[[key]]`) and shortcodes (`[name]...[/name]`). A `.tpl` file gets pulled into delivery in two ways:

- **1. As a route body** - `config.php`'s `/nino/http/routes` references a `.tpl` file directly via the `Template` shortcode, e.g. `'body' => '[template /templates/page-home]'`.
- **2. As an include inside another template** - the same `[template ...]` shortcode, e.g. `[template /templates/html-header]` at the top of every page.

For including other template files inside a `.tpl`, use the
**shortcode:**
`[template /path/file]`
The example includes `/path/file.tpl` (given without extension) and renders it. For technical reasons, `Template` internally calls `Callbacks::doCallbacks( $appData, '/nino/html/render', $html )` directly instead of a full `renderHtml()` pass.

Every page follows the same **convention**:

```
[template /templates/html-header]

... page content (sections) ...

[template /templates/html-footer]
```

`html-header.tpl` opens `<head>`/`<header>`/`<main>`, `html-footer.tpl` closes `</main>` and adds `<footer>`, the cookie banner, the preloader and the asset/text-fill scripts. See "HTML Structure" below for the full layout.

### How assets are included

CSS and JS are **not** included individually via `<link>`/`<script>`, but through a bundle defined in `config.php`:

```php
'/nino/html/assets' => [
  '/.cache/style.css' => [ '/_nino/Nino.css', '/assets/style.css' ],
  '/.cache/script.js' => [ '/_nino/Nino.js', '/_nino/Nino.ui.js', '/assets/script.js' ],
],
```

Each key is the path of the **generated cache file**, the value the list of source files in include order. `[assets /.cache/style.css]` automatically renders the matching `<link>` or `<script>` tag from that, depending on the file extension. On the first request the bundle is built once: source files are concatenated, in the process **also pushed through the text-fill/shortcode rendering pipeline** (which is why `[[/ui/color-primary]]` also works inside a `.css` file), minified for `.min` filenames, and stored under the cache path - every following request then serves the cache file directly.

**Order is the only rule:** `_nino/Nino.css` must come before the project's own stylesheet, so that its `:root` overrides win in the cascade (see the CSS section below). There is no build step, no bundler tooling, no compilation - just this one list plus filesystem concatenation.

A custom shortcode can bring its own CSS/JS along by simply adding the file to this array - no separate registration is needed.

## HTML Structure

### The Nino structure convention

Every page follows the same nesting, visible in the example `templates/.demo-sections.tpl`:

```
[template /templates/html-header]      Opens <head>, <header>, <main>

  <section class="ui-section ...">     One content section
    <div class="ui-grid-row ...">      Flex container, max-width + padding
      <div class="ui-grid-100 ui-grid-m-50 ...">   One column
        ... content (heading, text, button, article, ...) ...
      </div>
    </div>
  </section>

  <section class="ui-section ...">     Next section, same pattern
    ...
  </section>

[template /templates/html-footer]      Closes </main>, adds <footer>
```

**Fixed rules of this convention:**

1. **`<section>` always carries `ui-section`** (or `ui-atf` for an above-the-fold page opener) plus, optionally, exactly one colour variant (`--dark`/`--black`/`--primary`/`--alt`) and optionally `--fullwidth`. Sections are never nested inside one another.
2. **`<div class="ui-grid-row">` is always the direct child of `<section>`** - never text or an `<article>` directly inside `<section>`, except for the JS-driven fullscreen variants (`js-cover`, `js-parallex`), which instead expect `<img>` + a `.js-cover-content` wrapper directly inside `<section>` (see CSS Classes below).
3. **Grid width classes (`ui-grid-100`, `ui-grid-m-50`, ...) always sit on their own `<div>`**, never directly on `<article>`. An `<article class="ui-article">` sits *inside* a grid div and itself only carries `ui-article` and its modifiers.
4. **Recurring content always comes via `[elements]`**, never hardcoded - see above. The grid div sits *inside* the `[elements]...[/elements]` loop, so that every element gets its own column:
   ```
   <div class="ui-grid-row">
      [elements /demo-services limit="3"]
         <div class="ui-grid-100 ui-grid-m-33">
            <article class="ui-article">
		       <h4 class="ui-article-title">[[title]]</h4>
		       <p class="ui-article-descr">[[description]]</p>
		    </article>
         </div>
      [/elements]
   </div>
   ```
5. **A section that appears identically on more than one page can be extracted into its own `templates/section-*.tpl` partial** and included via `[template /templates/section-*]` - the same include mechanism as for `html-header`/`html-footer`. `section-contact.tpl` (address + contact form) is one possible example of this.

### Working with all shortcodes in a template

A realistic page excerpt that shows the shortcodes from the introduction working together:

```
[template /templates/html-header]

<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim" data-cover-height="100">
  <img src="[[/nino/dir]]/images/.demo/demo-01.jpg">
  <div class="js-cover-content">
    <div class="ui-grid-row">
      <div class="ui-grid-100">
        <h2 class="ui-atf-title">[[/page-home/hero/title]]</h2>
        <p class="ui-atf-subtitle">[[/page-home/hero/subtitle]]</p>
        <a href="[[/webpage/contact/uri]]" class="ui-btn ui-btn--primary">[[/global/cta]]</a>
      </div>
    </div>
  </div>
</section>

<section class="ui-section">
  <div class="ui-grid-row">
    <div class="ui-grid-100 ui-text-center ui-mb-3">
      <h3 class="ui-section-title">[[/page-home/services/title]]</h3>
    </div>
    [elements /services limit="3"]
    <div class="ui-grid-100 ui-grid-m-33">
      <article class="ui-article">
        [image [[.uri]]/teaser alt="[[title]]"]
        <div class="ui-article-content">
          <h4 class="ui-article-title">[[title]]</h4>
          <p class="ui-article-descr">[[description]]</p>
        </div>
      </article>
    </div>
    [/elements]
  </div>
</section>

[template /templates/section-contact]

[template /templates/html-footer]
```

This order - a hero via `js-cover`, then an `elements` grid, then an extracted section partial - already covers `Elements`, `Images` and `Template` in combination with text-fill syntax. `Navigation`, `Localepicker`, `Jstext` and `Csrf` are already wired up in `html-header.tpl`/`html-footer.tpl` (see the structure convention above) and normally don't need to be called again on individual pages - `Csrf` shows up again directly in a form of its own, see the newsletter example in the CSS section below.

## CSS

### `var()` handling and the concept behind `Nino.css`

All design values - colours, spacing, font sizes, radii - live as native CSS custom properties in a single `:root { --token: value; }` block right at the top of `_nino/Nino.css` (the "00 Theme" section).
The rest of the file references **exclusively** `var(--token)`, never a hardcoded colour or spacing value anywhere. This has one single, but important, effect: a project never needs to change a single rule in `Nino.css` to get its own look - it's enough to override the same token name in a `:root { }` block that is **loaded later**. The browser resolves `var()` only at computation time, via the normal CSS cascade - no build step, no preprocessor variables, no load-order logic beyond "project stylesheet after `Nino.css`".

```css
/* assets/style.css - example of the most important tokens */
:root {
  --color-primary: #4faae8;
  --color-primary-text: #ffffff;
  --color-text: #333333;
  --color-background: #ffffff;
  --fontfamily-text: 'Inter', sans-serif;
  --fontfamily-title: 'Inter', sans-serif;
  --space-1: .75rem;
  --space-2: 1.5rem;
  --radius: .5rem;
}
```

As soon as a token also needs to stay editable via `/_admin` (e.g. an accent colour the operator should be able to adjust themselves), it is declared as a text fill instead of a literal - `--color-primary: [[/ui/color-primary]];` - and thereby lands in the same fill mechanism as ordinary page content. All remaining `/ui/*` values are deliberately **not** visible in the `/_admin` text panel (`text/blacklist.php`) - they are developer design tokens, not editorial content.

> **Important: `_nino/Nino.css` is not written to - it gets overridden.**
> `_nino/Nino.css` is kernel code, exactly like `_nino/Nino.php`. It is **never edited directly** in a project - every adjustment (colours, spacing, custom web fonts, additional components) belongs in a separate, project-owned stylesheet file under `assets/` (per the README convention, `assets/style.css`), which is entered in `config.php`'s `/nino/html/assets` **after** `_nino/Nino.css` into the same bundle:
> ```php
> '/.cache/style.css' => [ '/_nino/Nino.css', '/assets/style.css' ],
> ```
> This order is the entire mechanism: the project stylesheet's own `:root { }` wins in the cascade against the defaults from `Nino.css`, without a single line there ever being touched. This repository itself wires up three swappable theme files for demonstration purposes (`assets/style.theme01/02/03.css`) - the principle stays the same either way: **a separate file, loaded after the kernel stylesheet**, never a change to `Nino.css` itself. This keeps updating the kernel (replacing `_nino/Nino.css`) conflict-free at all times.

There is deliberately **no multi-theme/theme-switching layer** - a project has exactly one active look, set directly in the `:root` block of its own stylesheet.

### Naming convention

Three prefixes, strictly separated by responsibility:

| Prefix | Meaning |
|---|---|
| `ui-<block>` / `ui-<block>--<modifier>` / `ui-<block>-<element>` | BEM-like, for everything purely CSS-related (sections, buttons, articles, grid) |
| `ui-<property>-<value>` | Utilities (spacing, text alignment, opacity) |
| `js-<block>` | JS behaviour, strictly separate from `ui-*` styling - a theme can restyle freely without ever risking behaviour, and the kernel's own JS never relies on a name a theme might rename |
| `sc-<block>` | Markup that a kernel shortcode generates itself (`[navigation]`, `[localepicker]`) - this HTML is hardwired to `_nino/Nino.php` and not project-customisable |

This split is also the guideline for custom shortcodes/components: custom behaviour → `js-*`, custom styling → `ui-*`, `sc-*` stays reserved for the kernel's own shortcodes.

### The Nino CSS classes

#### Frame & structure

| Class | Note |
|---|---|
| `ui-grid-row`, `ui-grid-25/33/50/66/75/100` | Flexbox grid, mobile-first. Breakpoints `-s-`/`-m-`/`-l-`/`-xl-` at 640/768/1024/1280px, `ui-grid--fullwidth` removes the row padding |
| `ui-grid-top/-middle/-bottom`, `-center` | Vertical/horizontal alignment within the row |
| `header`, `.ui-logo`, `footer`, `.ui-footer-main/-legal/-title/-logo/-getintouch/-localepicker` | Frame of the header/footer area - see HTML Structure above for the full nesting |
| `js-scroll-header`, `body.js-scroll-atf/-btf/-up/-down` | Header fades in/out depending on scroll position/direction; the scroll-state classes always land on `body`, so that other elements (e.g. back-to-top) can hook into them too |
| `ui-atf`, `ui-atf--fullscreen` | Page opener (hero). `--fullscreen` (`min-height:100vh`) is the pure-CSS variant without an image; `js-cover`/`js-parallex` achieve the same effect image-based, via JS |
| `ui-section`, `--dark/--black/--primary/--alt/--fullwidth`, `--border-1/2/3` | Every colour variant also sets a matching link colour (`a:not(.ui-btn)`) |

**Example - 50/50 grid, image running edge-to-edge to the viewport:**

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

#### Navigation

| Class | Note |
|---|---|
| `sc-nav-wrap`, `-burger`, `-regular`, `-content`, `-bg` | Output of `[navigation]` - the burger variant is a fullscreen overlay, `regular` renders inline (e.g. in the footer) |
| `ui-headernav-logo` | Logo image inside the burger overlay |
| `sc-localepicker-wrap`, `-bg` | Output of `[localepicker]` |

```html
[navigation burger]
  [[/webpage/home/uri]]:[[/webpage/home/name]]
  [[/webpage/contact/uri]]:[[/webpage/contact/name]]
[/navigation]
```

#### ATF/hero + cover/parallax

| Class | Note |
|---|---|
| `js-cover(--dim)`, `data-cover-height`/`-width` | `data-cover-height="100"` for a fullscreen hero. `--dim` lays a dark scrim over the image - needed as soon as a hero uses a real (bright) photo instead of a dark placeholder, so that `ui-atf-title`/`-subtitle` in white stays legible |
| `js-parallex(--dim)` | Same `--dim` scrim, parallax scroll effect instead of a fixed cover |
| `ui-atf-title`, `-subtitle`, `-arrowdown` | Hero typography; `-arrowdown` is a `currentColor` SVG used as a `background-image` - so it automatically matches the text colour, no icon markup needed |

```html
<section class="ui-atf ui-section--fullwidth js-cover js-cover--dim" data-cover-height="100" style="color:var(--color-primary-text);">
  <img src="...">
  <div class="js-cover-content">
    <h2 class="ui-atf-title">...</h2>
    <p class="ui-atf-subtitle">...</p>
  </div>
</section>
```

The inline `color` is the guaranteed way to keep hero text legible over a `--dim` scrim - `Nino.css` only styles the scrim itself (`::before`), not the text.

#### Article/card (always via `elements`)

| Class | Note |
|---|---|
| `ui-article`, `--alt`, `--fullwidth`, `-cols`/`-cols-s/m/l/xl`, `-price` | `-price` for a priced product shown as a regular article-grid card - different from `ui-pricing-price` (dedicated pricing table, see below) |
| `ui-article-content`, `-title`, `-subtitle`, `-descr`, `-img`, `-img--maxheight` | |

**Never put grid width classes directly on `<article>`** - see HTML Structure, rule 3:

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

#### Buttons / icons

| Class | Note |
|---|---|
| `ui-btn`, `--primary/--outline/--light/--dark/--big/--small` | `--big`/`--small` scale padding/font size/radius via `calc()` from the same base |
| `ui-icon`, `.small` | |

```html
<a href="#" class="ui-btn ui-btn--primary">Get started</a>
<svg class="ui-icon small" ...>...</svg>
```

#### Form

| Class | Note |
|---|---|
| `ui-form-input/-textarea/-select`, `-message` | Field styling only |
| `ui-form`, `.error/.success/.existing/.pending` | Generic handling in `Nino.ui.js` - posts automatically to `POST /` (`Modules\Form`), no shortcode needed |
| `js-newsletter-form` | Its own submit handler instead of the generic `ui-form` handling (new/already-subscribed need different messages), posts to `/.newsletter` |

```html
<form class="ui-form">
  [csrf]
  <label for="name">Name</label>
  <input type="text" id="name" name="name" class="ui-form-input" required>
  <textarea name="message" class="ui-form-textarea" required></textarea>
  <p class="ui-form-message"></p>
  <button type="submit" class="ui-btn ui-btn--primary ui-form-submit">Send</button>
</form>
```

#### Badge, alert, breadcrumbs, list

| Class | Note |
|---|---|
| `ui-badge`, `--pill/--primary/--success/--error` | |
| `ui-alert`, `--success/--error/--info` | A static banner for arbitrary page messages - different from `.ui-form-message` (the form's own feedback) |
| `ui-breadcrumbs` | `›` separator via `::after`, the last `li` styled as the current page |
| `ui-list`, `--check`, `--numbered` | `--check`/`--numbered` markers are `::before` (a Unicode checkmark resp. CSS `counter()`), not an image/SVG |

```html
<span class="ui-badge ui-badge--primary">New</span>
<div class="ui-alert ui-alert--info">Some note</div>
<ul class="ui-breadcrumbs">
  <li><a href="#">Home</a></li>
  <li>Current page</li>
</ul>
<ul class="ui-list ui-list--check">
  <li>Free initial consultation included</li>
</ul>
```

#### Table, pricing table, accordion, pagination

| Class | Note |
|---|---|
| `ui-table-wrap` > `ui-table`, `--striped`, `--bordered` | `-wrap` scrolls a wide table horizontally instead of forcing the whole page wide. `--striped` tints rows with `--color-section-alt-bg` - so use it on a plain `ui-section`, not `--alt` |
| `ui-pricing-row`, `ui-pricing-item(--featured)`, `-title`, `-price` | No fixed markup/shortcode - card styling meant to wrap an `[elements]` loop |
| `ui-accordion`, `-trigger`, `-panel` | Pure `<details>`/`<summary>`, no JS - several `<details>` sharing the same `name="..."` behave natively as an exclusive group |
| `ui-pagination` | Page-number links only, no JS - different from the slider's dot pagination (`js-slider-points`) |

```html
<div class="ui-pricing-row">
  [elements /pricelist query="cat=standard"]
  <div class="ui-pricing-item">
    <h4 class="ui-pricing-title">[[title]]</h4>
    <p class="ui-pricing-price">[[price]] &euro;</p>
  </div>
  [/elements]
</div>

<details class="ui-accordion" name="faq" open>
  <summary class="ui-accordion-trigger">Question?</summary>
  <div class="ui-accordion-panel"><p>Answer.</p></div>
</details>
```

#### Logo strip, gallery, feature list, timeline, video poster

| Class | Note |
|---|---|
| `ui-logos`, `-item` | Text placeholders stand in for real logo SVGs/PNGs - greyscale, full colour on hover |
| `ui-gallery`, `-item`, `--wide`, `--tall` | CSS grid, fixed `grid-auto-rows`, individual tiles widened/heightened via the modifiers |
| `ui-feature-list`, `-item` | Icon + heading + text, meant for one half of the 50/50 grid (image in the other half) |
| `ui-timeline`, `-step`, `-number` | Numbered circles, connected by a line (only from the `m` breakpoint up) |
| `ui-video-poster`, `-play` | Static poster image with a play icon, opens the real `.ui-video` embed in a `js-modal--video` lightbox |
| `ui-video`, `--4-3` | Responsive iframe/video wrapper, 16:9 by default |

```html
<div class="ui-gallery">
  <div class="ui-gallery-item ui-gallery-item--wide">
    <button type="button" class="js-modal-trigger" data-modal-target="gallery-1" aria-label="Enlarge image">
      <img src="..." loading="lazy">
    </button>
  </div>
</div>
```

#### JS-driven behaviour (`Nino.ui.js`)

| Class | Note |
|---|---|
| `js-vpa`, `--repeat`, effect `--zoom(-out)/-slide-left/-right/-blur/-flip` × `-soft/-medium/-hard`, `--speed-fast/-medium/-slow` | Viewport animation. Effect/speed are pure CSS (custom properties `--vpa-*`) - no JS change needed, every class only sets these variables and can therefore also be inherited by setting it on `<body>`. Demo: `/.demo-vpa` |
| `ui-autoheight`, `data-autoheight-group` | Deliberately kept `ui-*` (a styling/markup concern, not a behaviour toggle) |
| `js-slider`, touch swipe, `js-slider-button`, `js-slider-points` | Prev/next buttons and dot pagination are generated by JS, not hand-written |
| `js-preloader` | Fullscreen spinner overlay, removed on `window.load` |
| `js-tabs`, `-nav`, `-tab`, `-panel`, `data-tabs-target` | Panel switch fades in via `@starting-style` (pure CSS), falls back to an instant show/hide in older browsers |
| `js-modal`, `--lightbox`, `--video`, `js-modal-trigger`, `-close`, `data-modal-target` | Built on the native `<dialog>` (`showModal()`/`.close()`) - focus trapping, escape-to-close and the backdrop click area therefore come from the browser |
| `js-toast-trigger`, `data-toast-message`/`-type`, or `Nino.ui.toast(message, type)` | Inline `onclick` doesn't work (CSP `script-src 'self' 'nonce-...'`) - `js-toast-trigger` is the declarative, CSP-safe way to fire one from static markup |
| `js-stat-counter`, `data-stat-counter-to/-suffix/-duration` | Counts up from 0 once scrolled into the viewport |
| `js-back-to-top` | Visibility is pure CSS (`body.js-scroll-btf`), JS only handles the click (`scrollTo` smooth) |
| `js-cookie-banner`, `-actions` | A bottom bar instead of a fullscreen overlay. State lives in `Nino.cookie` under `nino_consent`; `Nino.ui.cookieConsent.get()`/`.isAccepted()`/`.set()` is the public API a custom analytics script should check against before loading anything |

```html
<div class="js-tabs">
  <div class="js-tabs-nav">
    <button type="button" class="js-tabs-tab active" data-tabs-target="tab-1">Services</button>
    <button type="button" class="js-tabs-tab" data-tabs-target="tab-2">Pricing</button>
  </div>
  <div class="js-tabs-panel active" id="tab-1"><p>...</p></div>
  <div class="js-tabs-panel" id="tab-2"><p>...</p></div>
</div>

<button type="button" class="js-modal-trigger" data-modal-target="video-modal">Play video</button>
<dialog class="js-modal js-modal--video" id="video-modal">
  <button type="button" class="js-modal-close" aria-label="Close">&times;</button>
  <div class="ui-video"><iframe src="..." allowfullscreen></iframe></div>
</dialog>

<button type="button" class="js-toast-trigger" data-toast-message="Saved." data-toast-type="success">Save</button>
```

```js
// or imperatively, e.g. after a custom XHR call - from assets/script.js,
// not from an inline <script> tag (the CSP nonce belongs to the jstext shortcode)
Nino.ui.toast( 'Saved.', 'success' );
```

#### Utilities

| Class |
|---|
| `ui-m-/-mt-/-mb-/-ml-/-mr-/-p-/-pt-/-pb-0..6` (spacing, `--space-1..6`) |
| `ui-text-left/-center/-right`, breakpoint prefixes `-s-/-m-/-l-/-xl-` |
| `ui-font-small`/`-big` |
| `ui-opacity-10..90` |
| `ui-hidden`, `ui-invisible`, `ui-sr-only`, `ui-hidden-*`/`ui-visible-*` (breakpoint-dependent) |
| `ui-img-cover`, `ui-img-background(--dim)`, `-content` |

```html
<div class="ui-mt-3 ui-p-2 ui-text-center ui-font-big">...</div>
<div class="ui-img-background ui-img-background--dim ui-text-center">
  <img src="...">
  <div class="ui-img-background-content">...</div>
</div>
```

## Shortcode Reference

#### `Elements`
Includes one or several elements in a template.
**Shortcode:**
`[element /type/uri locale="xx" callback="..."]...[[key]]...[/element]`
Renders the enclosed content once for a single element; `[[key]]` placeholders inside it are replaced with that element's field values (`locale`/`callback` optional).
`[elements /type limit="N" query="key=value" locale="xx" callback="..."]...[[key]]...[/elements]`
Repeats the content for every element of a type - optionally filtered by `query` (`key=value`, `&`-separated for several conditions), capped by `limit`. On top of the field values, the loop also exposes `[[.id]]` (a running index) and `[[.uri]]` (the element's full uri).

#### `Template`
`[template /path/file]` includes another `.tpl` file.

#### `Images`
Includes a developer-defined image slot.
**Shortcode:**
`[image slotUri alt="..."]`
Renders an `<img>` tag for an image slot, provided an image has already been uploaded via `/_admin` - otherwise the output stays empty. The available slots (position, target format) are defined in `/_dev`.

#### `Assets`
Includes a CSS/JS bundle.
**Shortcode:**
`[assets /.cache/style.css]`
Renders the files collected for this bundle via `config.php`'s `/nino/html/assets` as a bundled (and, for `.min` filenames, minified) `<link>` or `<script>` tag.

#### `Navigation`
Renderer for navigation menus (burger or regular variant) from a line-based mini syntax.
**Shortcode:**
`[navigation id="..." class="..." callback="..." burger]uri:Title:Attribute[/navigation]`
Builds a `<ul>` navigation from line-by-line `uri:title:attribute` entries in the content. A content line **without** a `:` is instead rendered as a raw `<div>` - which lets you mix in, say, a logo between the link lines (inside the burger menu this `<div>` stays hidden as long as the overlay is closed). The `burger` flag (without `=`) renders the fullscreen burger variant instead of the regular one; the currently active uri automatically gets `class="active"`.

#### `Localepicker`
A ready-made language switcher including redirect handling.
**Shortcode:**
`[localepicker callback="..."]`
Renders the language-selection UI with all available locales, current locale marked as the active entry (`callback` optional). The switcher itself works via the `/_nino/locales/current` query parameter, which `Localepicker::callbackResponse()` checks on every response and then - through the internal response mechanism instead of a direct `header('Location: ...')` call - redirects to the equivalent route in the target language (`Http::findRouteUri()`, not the same URL with a different locale flag - `/legal` and `/rechtliches` can therefore be different URLs for the same page in different languages).

#### `Jstext`
Makes the current text fills available to frontend JavaScript as JSON as well.
**Shortcode:**
`[jstext]`
Renders a `<script>` tag (CSP-nonce protected) that exposes all current text fills as a `NinoJstext` object. Belongs once per page, directly before the asset includes in `html-footer.tpl`.
**Javascript:**
`Nino.content.getText( key )`
Reads a text provided that way (`window.NinoJstext`), e.g. `Nino.content.getText('/form/info/success')`. Returns `''` if the key doesn't exist.

#### `Csrf`
CSRF protection for forms via a session token.
**Shortcode:**
`[csrf]`
Renders a hidden `_csrf` input field with the current session token - belongs in every form that is checked server-side via the Csrf callback (e.g. the newsletter form, see HTML Structure above).

These eight shortcodes cover everything a template author writes directly in square brackets. Kernel modules without their own shortcode (`Form`, `Newsletter`) are instead addressed through a fixed CSS class (`ui-form` resp. `js-newsletter-form`) - see `docs/development.md`'s module reference as well as the CSS section above.
