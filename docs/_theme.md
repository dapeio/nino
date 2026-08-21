# `/_theme` — User Manual

**Language:** English · [Deutsch](_theme.de.md)

**Last updated:** August 21, 2026 · **Nino version:** 0.11.0-beta.1

This manual explains the design layer under `/_theme`: the colors every stylesheet in a project reads from, where they come from, and what changing them does. Structural page composition is described in the [`/_templates` Operation Manual](_templates.md); day-to-day content maintenance in the [`/_editor` Operation Manual](_editor.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [`/_theme` Operation](_theme.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Security:** `/_theme` uses the same login, password, lock status, and session as `/_admin`. If `_admin/` is removed from a delivery, `/_theme` is unavailable too.

## What `/_theme` Is For

A stylesheet asks for a color by name — `var(--nino-alt)` for a section background, `var(--nino-on-alt)` for the text on it. `/_theme` decides what those names are worth.

You supply a brand color and a handful of preferences. `/_theme` calculates the whole palette and the size raster from them and writes both to one file. Nothing about the layout changes; only the values behind the names.

The point is the pairing. Every background comes with the text color that belongs on it, and that pair is measured against the WCAG contrast formula before it is written out. You cannot pick a brand color that produces unreadable text, because the text color is not something you pick — it is solved for the background you chose.

## Login and Interface

Open `https://your-domain.example/_theme` and log in with the `/_admin` password. You will find:

- **Primary** — the brand color. Everything else is derived from it.
- **Secondary** — an optional second accent. Left empty, it follows Primary.
- **Contrast** — `Soft`, `Default`, or `High`.
- **Colors** — `Clean`, `Default`, or `Vibrant`.

**Preview** recalculates without saving. **Save** writes the stylesheet.

## The Settings

### Primary and Secondary

Two hex colors. Primary becomes the `origin` surface and tints the neutral greys — a grey biased toward the brand hue reads as chosen rather than as an accident. Secondary becomes the `vibrant` surface. With no Secondary set, `vibrant` and `origin` are identical, which is a valid single-accent design.

Your brand color is not used verbatim as a background. It is moved along the perceptual lightness axis until the text on it passes the contrast target — the hue and the saturation are what stay recognizable. A brand blue may come out slightly darker as a surface than it is in your logo; it is still the same blue.

### Contrast

Sets the ratio that has to be met, nothing else.

| Setting | Body text | Secondary text |
|---|---|---|
| Soft | 4.5:1 | 3.0:1 |
| Default | 4.5:1 | 4.5:1 |
| High | 7.0:1 | 4.5:1 |

4.5:1 is the WCAG AA requirement for body text (SC 1.4.3), 7.0:1 is AAA. `Soft` does not go below AA for body text — it only relaxes the subordinate tier. Borders and focus rings always target 3:1 (SC 1.4.11) regardless of the setting.

### Colors

Scales saturation only: `Clean` mutes to 45%, `Vibrant` boosts to 150%. Lightness stays with Contrast. Keeping the two knobs on separate axes is what makes them predictable — otherwise both would be fighting over the same value and neither would do what its label says.

The status colors ignore this setting. Red has to stay red, so the brand knobs cannot turn a danger surface into something reassuring.

## The Size Raster

The same split applied to sizes: `/_theme` publishes a fixed set of steps, the theme decides which step a component uses. Numbered rather than named, because a size has no meaning of its own - `--nino-space-3` is a step, `--nino-alt` is a surface.

| Token | Steps |
|---|---|
| `--nino-text-1` … `--nino-text-6` | the type scale, body copy to display |
| `--nino-space-1` … `--nino-space-6` | the spacing scale |
| `--nino-radius-1` … `--nino-radius-3` | corner radii |
| `--nino-radius-full` | a pill; the same answer at every setting |
| `--nino-line-height` | the vertical rhythm |

Three settings shape it.

**Volume** decides how far the type scale fans out. It is anchored at body copy: step 1 stays where it is at every setting, and the display end moves. A scale gets bigger by fanning out at the top, not by pushing the size you actually read up with it.

**Spacing** scales the gaps and moves the line height with them. It bites hardest on the small steps - the large ones are already section-sized, and doubling those just adds scrolling.

**Shaping** sets the three radii. Absolute values rather than a multiplier, because a radius has a floor at 0 and a ceiling at half the box that a multiplier would sail past.

The default of every setting reproduces `Nino.css`'s own scale exactly. Turning the design layer on must not move a project that has not asked for anything.

`--nino-text-4` through `--nino-text-6` grow again at 768px, in a media query of the raster's own - mirroring the breakpoint `Nino.css` already uses. The raster has no light and dark variant, so unlike the palette it is written once.

## The Surfaces

A surface is a background plus everything readable on it. There are nine:

| Surface | Use |
|---|---|
| `default` | the page ground |
| `alt` | a barely-there step off the ground, for alternating sections |
| `dark` | a deliberate dark block; the same in light and dark mode |
| `black` | the deepest step, for footers and heavy sections |
| `origin` | the brand surface |
| `vibrant` | the second accent |
| `success`, `warning`, `danger` | status, at fixed hues |

Each publishes ten values:

| Token | Meaning |
|---|---|
| `--nino-<surface>` | the background |
| `--nino-on-<surface>` | body text on it |
| `--nino-on-<surface>-muted` | secondary text on it |
| `--nino-<surface>-link` | link color on it |
| `--nino-<surface>-border` | a border against it, at 3:1 |
| `--nino-<surface>-focus` | the focus ring, at 3:1 |
| `--nino-<surface>-hover` | the background one interaction step up |
| `--nino-<surface>-active` | the background while pressed |
| `--nino-<surface>-disabled` | text that is deliberately unreadable |
| `--nino-<surface>-shadow` | a shadow that works on this ground |

Address a surface, never a ramp step. `var(--nino-alt)` says what you mean; a numbered step says only where it sits in a list that may be renumbered.

## Light and Dark

The file is written three times over: once for light, once inside `@media (prefers-color-scheme: dark)`, and once for `:root[data-nino-mode="dark"]`. The media block is guarded so an explicit light choice wins over the system preference, and the attribute block is last so an explicit dark choice wins over everything.

The practical effect: the same token name gives the right value in all three states — system light, system dark, and a manual override in either direction.

## Where the File Goes

`/_theme` writes `/assets/style.design.css` and places it in the css bundle immediately after `_nino/Nino.css`:

```
_nino/Nino.css            framework
assets/style.design.css   ← generated here
assets/style.theme.*.css  the theme
assets/style.css          your own overrides
```

The order is the rule that makes the whole thing work: Design supplies the values, the theme decides what to do with them, your own stylesheet has the last word. Anything later in the list can override anything earlier.

**Do not edit `style.design.css`.** Every save rewrites it completely. Put your changes in `assets/style.css`, which is never touched.

## Where the Settings Come From

`/_install`'s Themes step writes them first: a theme's manifest declares the `design` block it was drawn with, and the installer applies either that or whatever the operator picked. `/_theme` then edits the same stored settings afterwards - it is the same `Theme::write()` on both sides, so there is one generator and one stylesheet, not an install-time copy and a runtime one.

## Changing the Design Later

`/_theme` stays available after the installation. Recoloring a live project is a matter of picking a new Primary and saving; no reinstall, no content migration. Templates, sections, and elements are unaffected — they only ever referred to the token names.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Colors do not change after saving | Browser cache. Reload with cache bypass; the bundle is regenerated on write. |
| The brand color looks different as a surface | Expected. Lightness was moved to reach the contrast target; hue and saturation are preserved. |
| Secondary elements look identical to primary ones | No Secondary set — `vibrant` falls back to `origin`. Set one. |
| `style.design.css` keeps losing manual edits | It is generated. Use `assets/style.css`. |
| `/_theme` shows the login page repeatedly | Shared `/_admin` session. Check the `/_admin` login and any lock. |

## Current Limitations

- **One theme converted so far.** `agency` is rewritten as a mapping layer - every colour role assigned to a `--nino-*` token - and declares the `design` block `/_install` starts it from. The other seven still carry hand-tuned literal colours and ignore these settings until they are converted the same way.

## Next Steps

- [`/_templates` Operation](_templates.md) composes pages from sections that use these surfaces.
- [`/_admin` Operation](_admin.md) covers the technical project management this tool shares its login with.
- [Developer Manual](development.md) documents the token contract for stylesheet authors.
