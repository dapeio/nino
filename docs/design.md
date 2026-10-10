# Design Manual — WIP

**Language:** English · [Deutsch](design.de.md)

**Last updated:** August 8, 2026 · **Status:** **Work in Progress**

> **Note:** This manual is currently being revised. Until completion, please use the information from:
> - **[Developer Manual](development.md)** (for backend integration)
> - **[Builder](https://github.com/dapeio/nino-features/tree/main/features/Builder)** (a feature from the catalogue, for template structure)
> - **[Main Repository `dapeio/nino`](https://github.com/dapeio/nino)** (for current frontend conventions)

## Hooks in `Nino.css`

A design layer - the delivered `assets/theme.css`, the catalogue's Design feature, a project's own `assets/style.css` - changes the look by setting custom properties in `:root`, after `Nino.css` in the bundle. These hold the faces, the sizes and the numbers that scale them. Every default is what the rules had before the property existed, so a page that sets none of them looks as it did. One case differs: a subtitle class, or `.nino-code`, on an element the browser makes bold - an `h1` to `h6`, or anything inside a `strong` - takes the weight of its role, `normal`, where it was bold.

| Property | Default | Read by |
|---|---|---|
| `--fontfamily-title`, `-subtitle`, `-text`, `-button`, `-code` | the system stack; `-button` is `var(--fontfamily-text)` | the family of the five font roles |
| `--font-<role>-weight` | `bold` for `title` and `button`, `normal` for the rest | the weight of a role |
| `--font-<role>-transform` | `none` | the `text-transform` of a role |
| `--font-<role>-size` | `title` `var(--text-4)`, `subtitle` `var(--text-2)`, `text` `1rem`, `button` `var(--fontsize-btn)`, `code` `.875rem` | the size of a role |
| `--hero-title-scale`, `--hero-text-scale` | `1.2` and `1.2`; from 768px `1.25` and `10 / 9` | the size of `.nino-hero-title` and `.nino-hero-subtitle`: the size of the title and of the subtitle role times the number |
| `--article-title-scale` | `5 / 6`; from 768px `25 / 32` | the size of `.nino-article-title`: the size of the title role times the number |
| `--scale-quiet`, `--scale-loud` | `1` | the `--quiet` and the `--loud` variants of a section, an article and a hero: the number multiplies the step of the scale they stand on |
| `--grid-gutter` | `var(--space-2)`; from 768px `var(--space-3)` | the horizontal padding of `.nino-grid-row`, the space between the viewport and a row |
| `--shadow-card` | `none` | the `box-shadow` of `.nino-article` and `.nino-pricing-item` |

- **The roles.** Title, subtitle, text, button and code. The title role is read by `.nino-section-title`, `.nino-article-title` and `.nino-hero-title`, and by every `h1` to `h6` for its weight and its transform alone: a heading without a class keeps the face of the text and the size the browser gives it. The subtitle role is read by the three subtitles, the text role by the page (weight and transform), the button role by `.nino-btn` and its `--small` and `--big` forms (and by the filter button, for the size) and the code role by `.nino-code`. A `code`, `kbd`, `samp` or `pre` with no class of its own belongs to no role: the browser sets it smaller than the text around it, and the page does not read the text size on `body`, so that it stays so.
- **The scale.** `--text-1` is `--font-text-size`; `--text-2` to `--text-6` are `--font-text-size` times `1.125`, `1.25`, `1.5`, `1.8` and `2.5`, and from 768px `--text-4` to `--text-6` times `1.6`, `2` and `3`. **The delivered `assets/theme.css` assigns `--text-1` to `--text-6` itself**, from its own raster, and what it assigns wins over `Nino.css`: on a project made from it the scale follows `--font-text-size` once those six lines are gone.
- **The hero, the article title and the variants.** The leading of a hero moves with its size. An article title is five sixths of the title role (`25 / 32` from 768px), which is the `--text-3` it stood on where the other titles stand on `--text-4`. The two factors that fall from 768px (`--article-title-scale`, `5 / 6` to `25 / 32`, and `--hero-text-scale`, `1.2` to `10 / 9`) give back the sizes the rules had before there were roles only while the size of the role they multiply is a step of the scale: a design layer that fixes `--font-title-size` or `--font-subtitle-size` in rem sets these two factors as well, or the article title and the hero subtitle get smaller in rem from 768px. A `--quiet` or `--loud` variant stands on a step of the scale - a section title on `--text-3` or `--text-5`, an article title on `--text-2` or `--text-4` - and is that step times `--scale-quiet` or `--scale-loud`.
- **Where to set them.** On `:root`. The size of a role is resolved there - `--font-title-size` is the `--text-4` of the root - so a `--text-n` or a `--fontsize-btn` that a section or a column sets for itself does not reach the base title, subtitle and button classes in it. To size those inside one section, set the `--font-<role>-size` there. The `--quiet` and `--loud` variants and the text classes `.nino-section-text` and `.nino-article-descr` stand on the scale and read the step themselves, so they do follow a `--text-n` that a section or a column sets for itself. A hero title or subtitle that carries its base class next to the variant follows it below 768px only: from there the base class states its size again.
- **A card.** `.nino-article` (and with it a `.nino-bento-item` that carries it, as the catalogue draws them) and `.nino-pricing-item` read `--shadow-card`. A `.nino-article--borderless` has none, and the middle card of `.nino-pricing-row--feature-middle` and the toast keep the shadows they have.

## Planned Content

| Section | Status | Description |
|---|---|---|
| **Frontend Architecture** | Planned | Structure of the CSS framework, naming conventions, grid system |
| **Design Tokens** | Planned | Colors, spacing, typography (the `--nino-*` properties in `assets/theme.css`) |
| **Template Work** | Planned | Practical guide for `page-*.tpl` and `section-*.tpl` |
| **Shortcodes & Components** | Planned | Integration of Nino core components (e.g., navigation, locale picker) |
| **Custom Frontend Elements** | Planned | Development of individual blocks and shortcodes |
| **Responsive Design** | Planned | Breakpoints, media queries, mobile optimization |
