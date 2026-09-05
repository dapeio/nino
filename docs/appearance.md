# The Design Panel — Reference Manual

**Language:** English · [Deutsch](appearance.de.md)

**Last updated:** September 5, 2026 · **Nino version:** 0.13.0-beta

This manual explains the four appearance editors of the workbench's **Design** panel: Theme, Design, Header, and Footer. Structural page composition is described in the [Templates panel](templates.md) reference; everything else about the workbench in the [`/_admin` manual](_admin.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Templates Panel](templates.md) · [Design Panel](appearance.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Security:** The panel is part of `/_admin` and asks for `/_admin/design/manage` on every action - a developer's permission, never an editor's. It is the Design module (`app/Nino/Modules/Design/`) and disappears from the workbench with its directory. The panel names its settings in the interface language (`app/Nino/Modules/Design/text/<locale>.php`); the schema they come from stays English, because the setup wizard renders the same settings and has no text system of its own – so a setting added there appears in both, in the schema's words, until a fill names it.

## What the Panel Is For

The Design panel keeps the site's four appearance decisions editable after the one-time wizard has gone:

- **Theme** chooses a complete visual baseline and installs its stylesheet, fonts, recommended Design, Header, and Footer.
- **Design** changes only the generated color palette and size raster.
- **Header** changes only `templates/theme.header.tpl` and `assets/style.header.css`.
- **Footer** changes only `templates/theme.footer.tpl` and `assets/style.footer.css`.

Theme application is deliberately broad: it resets the following three decisions to the selected manifest's recommendations. Design, Header, and Footer are deliberately narrow, so settling one of them never reapplies the Theme or changes either of the others.

The Design half remains based on measured pairs. A stylesheet asks for a color by name — `var(--nino-alt)` for a section background, `var(--nino-on-alt)` for the text on it — and the module calculates the value and verifies its contrast before writing it.

## Login and Interface

Sign in to `/_admin` and open **Design** in the Structure group; `#design/header` opens the Header editor directly. The four editors are tabs at the top of the panel. The fixed action button at the foot changes with the active tab: **Apply Theme**, **Save Design**, **Apply Header**, or **Apply Footer**. Switching to another panel keeps the panel's state; leaving the page with unsaved Design changes asks first.

Theme displays the available catalogue as cards. Header and Footer each show a selector and a tall, sandboxed iframe built from the real frame template, the active Theme, and the saved Design. The iframe is inert and cannot run scripts from a variant.

Design provides Primary, optional Secondary, Contrast, Colors, Volume, Spacing, and Shaping. Changes are recalculated for the on-page specimens without writing project files; **Save Design** commits them.

## Shared Appearance Library

The wizard and the panel read the same catalogue:

| Path | Unit contract |
|---|---|
| `_admin/install/library/themes/<key>/` | `manifest.php`, preview, and every file the manifest installs |
| `_admin/install/library/header/<key>/` | `template.tpl` plus optional `style.css` |
| `_admin/install/library/footer/<key>/` | `template.tpl` plus optional `style.css` |

The catalogue is the wizard's. Removing `_admin/install/` from a deployment — the recommendation for production — leaves the three catalogue-backed tabs with nothing to list, and they say so; the Design tab is unaffected, because it generates rather than copies. Only `_admin/install/library/themes/*/preview.svg` is a public asset. `_admin/install/library/.htaccess` and the development router refuse direct access to manifests, templates, stylesheets, and font sources. The frame previews and the frames around the example page are rendered by the wizard's own code, loaded on demand while it is there.

## The Settings

### Primary and Secondary

Two hex colors. Primary becomes the `brand` surface and tints the neutral greys — a grey biased toward the brand hue reads as chosen rather than as an accident. Secondary becomes the `accent` surface. With no Secondary set, the accent is the brand's own lightness and chroma carried round the wheel to wherever Harmony puts it; under Monochrome that lands back on the brand itself, which is a valid single-accent design.

`brand` and `accent` are the colors the picker returned, byte for byte, in light mode and in dark. Nothing moves them — not the Saturation knob, not the contrast solver, not the mode: an operator who types their corporate hex has to find that hex in the stylesheet. The cost is that they are the one pair without a guaranteed contrast ratio, which is exactly why `brand-safe` and `accent-safe` exist beside them. Those two are the same color moved along the perceptual lightness axis until the text on them passes the target — hue and saturation stay recognizable. A brand blue may come out slightly darker as `brand-safe` than it is in your logo; it is still the same blue.

### Contrast

4.5:1 is the floor at every position, muted text included. The knob decides how far *above* that floor the type sits.

| Setting | Solved ratio (links, brand surfaces) | Body ink on white |
|---|---|---|
| Soft | 4.5:1 | a soft near-black, around 9:1 |
| Default | 4.5:1 | near-black |
| High | 7.0:1 | black |

The two columns move for different reasons. The solved targets — `TARGET_TEXT` and `TARGET_MUTED`, which are the same value at every position — apply where a ratio has to be reached: links, and the lightness a brand surface is moved to so text survives on it. Body text is not solved against a target at all, because solving it would put grey copy on white at exactly 4.5:1 — legal and visibly washed out. It is a lightness instead, and that lightness is what a reader actually feels.

The muted tier used to be allowed down to 3.0:1, which only passes for large type; a muted paragraph is not large type, so it carries the same floor the body does. 4.5:1 is the WCAG AA requirement for body text (SC 1.4.3), 7.0:1 is AAA. Borders target 3:1 (SC 1.4.11), and the focus ring is held at 3:1 at every setting — it is the one value no knob may soften.

### Colors

Scales saturation only: `Clean` mutes to 45%, `Vibrant` boosts to 150%. Lightness stays with Contrast. Keeping the two knobs on separate axes is what makes them predictable — otherwise both would be fighting over the same value and neither would do what its label says.

The status colors ignore this setting. Red has to stay red, so the brand knobs cannot turn a danger surface into something reassuring.

## The Size Raster

The same split applied to sizes: the module publishes a fixed set of steps, the theme decides which step a component uses. Numbered rather than named, because a size has no meaning of its own - `--nino-space-3` is a step, `--nino-alt` is a surface.

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

A surface is a background plus everything readable on it. There are twelve, in the order `Tokens::SURFACES` names them:

| Surface | Use |
|---|---|
| `default` | the page ground |
| `alt` | a barely-there step off the ground, for alternating sections |
| `tint` | the fifth ground: the page ground carrying the brand hue rather than a near-grey |
| `dark` | a deliberate dark block; the same in light and dark mode |
| `black` | the deepest step, for footers and heavy sections |
| `brand` | the primary exactly as picked, never written on |
| `brand-safe` | that same colour, lightness solved until text survives on it |
| `accent` | the second colour exactly as picked or derived |
| `accent-safe` | that same colour, made safe the same way |
| `success`, `warning`, `danger` | status, at fixed hues |

The brand is four roles rather than two. Each answers one of two questions — is it the brand or the second colour, and is it the one that was picked or the one text survives on. Two roles broke as soon as a Secondary colour was set: the safe surface became the *accent* made safe, leaving no contrast-safe brand colour in the palette at all.

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

**Save Design** writes `/assets/style.design.css` and places it in the css bundle immediately after `_nino/Nino.css`:

```
_nino/Nino.css            framework
assets/style.design.css   ← generated here
assets/style.theme.*.css  the theme
assets/style.header.css   the selected Header frame
assets/style.footer.css   the selected Footer frame
assets/style.css          your own overrides
```

The order is the rule that makes the whole thing work: Design supplies the values, the theme decides what to do with them, your own stylesheet has the last word. Anything later in the list can override anything earlier.

**Do not edit `style.design.css`.** Every save rewrites it completely. Put your changes in `assets/style.css`, which is never touched.

## Where the Settings Come From

The wizard's Theme step or the panel's Theme tab writes the baseline first from the `design` block the picked theme was drawn with. The independent Design tab writes the operator's choice afterwards. Every path uses the same `\Nino\Modules\Design::write()`, so there is one generator and one stylesheet, not separate install-time and runtime copies.

## Changing the Appearance Later

The panel stays available after the installation. Recoloring a live project is a matter of changing Design and saving; replacing a Header or Footer copies only that frame's two files. Neither operation needs a reinstall or content migration.

Applying another Theme is the intentional reset operation. It overwrites files named by the new theme manifest, replaces the bundled theme stylesheet, writes the manifest's recommended Design and frames, and leaves files unique to a previous Theme in place. Secure project-specific edits in Git before applying a Theme again.

## Troubleshooting

| Symptom | Cause and fix |
|---|---|
| Colors do not change after saving | Browser cache. Reload with cache bypass; the bundle is regenerated on write. |
| The brand color looks different as a surface | Expected on `brand-safe`, where lightness was moved to reach the contrast target; hue and saturation are preserved. `brand` itself is never moved. |
| Secondary elements look identical to primary ones | No Secondary set and Harmony on Monochrome — the derived accent lands on the brand. Set a Secondary, or pick another Harmony. |
| `style.design.css` keeps losing manual edits | It is generated. Use `assets/style.css`. |
| Theme, Header, or Footer says no variants are available | `_admin/install/` was removed, or its catalogue is incomplete. After the recommended deployment this is the normal state. To switch again, redeploy a locked `_admin/install/` from the same Nino version. |
| A frame preview differs from the live page | The preview uses the current installed includes and text where available, but remains an inert single-frame document. Check the applied template on the full page for request-specific module output. |
| `(403) Request failed.` appears after loading the controls | Either the account lacks `/_admin/design/manage`, or the page's CSRF token is stale after a login, logout, session rotation, or deployment. Reload `/_admin`; sign in again if needed. |
| The panel is missing from the navigation | The account lacks `/_admin/design/manage`, or the Design module is not in `/nino/modules`. |

## Catalogue Contract

All ten supplied themes — Basis, Bureau, Chronicle, Console, Gallery, Market, Midnight, Platform, Poster, and Practice — are mapping layers. Every colour role reads a generated `--nino-*` token, every size role reads the generated raster, and every manifest declares the complete Design and frame baseline the preview represents. A custom catalogue theme must keep that contract or its Design controls cannot reliably recolour and reshape it.

## Next Steps

- [Templates Panel](templates.md) composes pages from sections that use these surfaces.
- [`/_admin` Workbench](_admin.md) covers the rest of the workbench this panel is part of.
- [Developer Manual](development.md) documents the token contract for stylesheet authors.
