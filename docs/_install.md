# `/_install` — Reference Manual

**Language:** English · [Deutsch](_install.de.md)

**Last updated:** August 21, 2026 · **Nino version:** 0.11.0-beta.1

This manual explains the decisions and writing processes of the ten steps of `/_install`. If you instead want to take the shortest path from checkout to a configured website, start with [Getting Started](getting-started.md); the later production operation is covered in [Deployment](deployment.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [`/_theme` Operation](_theme.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Important:** `/_install` creates the first functional project state from a fresh Nino checkout. The assistant is necessary: Before its execution, the actual project directories such as `templates/`, `text/`, `elements/`, and `images/` do not yet exist.

## When to Use `/_install`

The assistant is intended for one-time initial setup. It:

- checks PHP and write permissions;
- sets languages and modules;
- creates the project directories from its library;
- applies a theme;
- sets the Design, Header, and Footer independently;
- sets up the first web pages;
- records central website information;
- creates accesses for `/_editor` and `/_admin`; the admin access also applies to `/_templates`.

`/_install` is not an update tool for a running project. After successful completion, the assistant locks itself and can be removed from production delivery.

**Security:** Until completion, `/_install` has no individual access protection. Perform the setup locally or in another protected environment, not on an openly accessible domain.

## Navigation and Saving

Each step loads the already saved state and displays it again. As long as the assistant is not completed, you can return to an earlier step, change settings, and reapply them.

Three different rules apply:

| Data Type | Behavior When Reapplying |
|---|---|
| Languages, modules, generated routes, and page list | the visible selection replaces the previously managed state by the assistant |
| Templates, texts, and element types | are supplemented or updated but not automatically deleted |
| Theme and frame files | files with the same name are overwritten; additional files from a previous theme remain |

This distinction protects your own changes. Deselecting a module may remove its configuration; automatically deleting a template file that has been edited in the meantime would not be safe.

## 1. Environment

The first step checks only the environment and writes no files. The following are controlled:

- the running PHP version;
- the required PHP extensions;
- the writability of the project root and already existing runtime paths.

Project directories that do not yet exist are expected. The decisive factor is that PHP is allowed to create them later. "Recheck" only repeats the same diagnoses.

Fix failed checks before continuing. Without sufficient write permissions, the assistant cannot reliably create either configuration or content.

## 2. Setup

Setup sets languages and functional modules and creates the basis of the project.

### Languages

**Available Locales** determines the available languages. **Native Locale** is the default language and must be part of this selection. Technically, it serves as a fallback as long as no language is yet determined for a visitor, and in terms of content, it forms the "mother tongue" of the website.

When reapplying, the visible language selection replaces the previous state. The default language is retained as long as it is still selected; otherwise, Nino uses the first selected language.

### Modules

The library can provide, among other things, demo content, navigation, language selection, forms, and newsletters. If a selected module requires another module, the assistant automatically includes this dependency in the selection. A used page template can also pull in required modules; a contact page, for example, activates its form and mail functions.

Setup writes:

- available and native language to `config.php`;
- the activated module classes to `/nino/modules`;
- the routes provided by the base and modules to `/nino/http/routes`;
- templates to `templates/`;
- global and language-dependent texts to `text/`;
- provided element types to `elements/`;
- other declared files to their project paths.

Languages, modules, and the routes managed by Setup are replaced. Manually or by other areas created routes remain preserved. Templates, texts, and element types that have already been copied are not deleted by later deselection.

## 3. Themes

A theme is a complete visual starting point. It can include stylesheets, fonts, images, and other assets. The preview in the selection grid belongs only to the assistant and is not copied into the project.

Exactly one theme is active. When applying:

1. `/_install` copies the files specified in the manifest into the project;
2. replaces the previous theme stylesheet in the asset bundle `/.cache/style.css`;
3. saves the selected theme key under `/nino/install/theme`;
4. installs the manifest's Design, Header, and Footer defaults as the complete baseline for the following steps.

The position of the stylesheet in the bundle is preserved as much as possible so that the CSS cascade does not change unintentionally. Own additional bundle entries are not removed.

**Important:** Theme files with the same name are overwritten. Files that only the previous theme brought remain. Therefore, secure your own changes via Git before switching or reapplying the theme. After completion, `/_install` locks itself; a later theme change via the interface is therefore not planned and is carried out as a normal project change via files and Git.

## 4. Design

Its own step, because the theme grid already fills a pane and everything here has to be looked at while it is being changed.

The values the theme reads from. `/_theme` generates the `--nino-*` tokens and a theme stylesheet assigns them to roles rather than writing literals.

**Colour** - a primary, an optional secondary, a Contrast step and a Colors step. Every background is generated together with the text colour that belongs on it, measured against the WCAG contrast formula, so a brand colour cannot produce unreadable text. The chips under the controls show the real pairs, not just the backgrounds.

**Size** - Volume (how far the type scale fans out), Spacing (gaps and line height) and Shaping (corner radii). The specimen below them is drawn at the sizes they generate; a list of rem values would be quicker to read and tell you nothing. Every setting's default reproduces `Nino.css`'s own scale, so a project that changes nothing here is not moved.

See [`_theme.md`](_theme.md) for the token names both halves publish.

A theme's manifest declares the design it was drawn with, so picking a theme and pressing Next produces the look its preview promised. The swatch strip under the controls shows the real pairs, not just the backgrounds.

This part of the step is optional: a delivery that ships without `/_theme` installs exactly as before, with the Design block absent.

The order in the css bundle is the whole contract, and each layer owns one slot in it:

```
_nino/Nino.css              framework defaults
assets/style.design.css     Design - the generated values
assets/style.theme.*.css    the theme - which value goes in which role
assets/style.header.css     the frames - styling for the markup they brought
assets/style.footer.css
assets/style.css            the project's own overrides
```

`/_theme` stays available after the installation, so a project can be recolored without reinstalling. See [`_theme.md`](_theme.md).

## 5. Header

The site's `<header>` is an interchangeable unit under `_install/library/header/<key>`, made from a `template.tpl` plus the `style.css` for the markup that template brings. The theme preselects the header it was drawn against; this separate step overrides only that choice after Design has been settled.

Applying copies the unit to `templates/theme.header.tpl` and `assets/style.header.css`, persists its key under `/nino/install/header`, and keeps the frame stylesheets directly after the theme in the CSS bundle. It does not recopy the theme or reset Design.

The taller preview iframe renders the real template against the framework, the just-chosen Design values, the picked theme, and the frame's own stylesheet. A version number says nothing about a layout, and unlike a theme a frame has no preview image to open. The preview stands in for what a project does not have yet: a placeholder mark where the logo goes, sample navigation items, and the library's own text for everything else. It is a sandboxed document of its own because a frame stylesheet uses broad selectors that must not land on the installer.

The base page templates include the installed copy through `[template /templates/theme.header]` rather than carrying the markup themselves, so the header can later be changed by replacing the same two project files.

## 6. Footer

The Footer step follows the same unit contract independently under `_install/library/footer/<key>`. Applying writes `templates/theme.footer.tpl` and `assets/style.footer.css`, persists `/nino/install/footer`, and leaves Theme, Design, and the selected Header untouched.

Its taller preview uses the same final Design and theme context as the Header preview. Applying Footer after Header preserves the canonical bundle order: theme, header stylesheet, footer stylesheet.

The base page templates include it through `[template /templates/theme.footer]`.

## 7. Routes

This step creates the public page structure. The list can be supplemented, edited, deleted, and sorted. With "Continue", the entire visible list is applied as the new state.

Each page requires:

- an **Element URI** as a stable internal identity;
- an **HTTP URI** as the publicly accessible path;
- a **Template** from `templates/page-*.tpl`;
- **Navigation Name**, **Page Title**, and **Description** for each active language;
- one checkbox per navigation registered in `/nino/html/navs`.

The step keeps no list of its own: it writes `/nino/http/routes` and the `/webpage<uri>/*` text keys — `name`, `title` and `description` per locale, plus `uri` (the page's reachable path) once in `text/global.php`, blacklisted as a technical value — and reads the list back out of them the next time it runs. Menu membership goes onto the page's own route as `'navs' => [ 'main' => 1, ... ]`, using the page's position in the list as its priority — sorting the list here is what orders the menus.

The Element URI is the anchor for page texts like `/webpage<uri>/title`. The HTTP URI is the path visible in the browser. This separation allows the internal identity to remain stable even if the public path changes.

A new page starts from the selected library template's own suggestions: its HTTP URI, plus Navigation Name, Page Title, and Description in **every** active language, read from the template's `text/<locale>.php` files. Switching the template only updates fields that are still untouched — anything typed by hand survives the switch. A field left empty still falls back to the generic placeholder ("Page", "Page Title").

A route on the **Blank** template gets its own copy of that template, named after its Element URI: a `/team` route is created as `templates/page-team.tpl` and rendered by `[template /templates/page-team]`. Blank is the empty starting point, so every route picking it needs a page of its own — a shared file would mean editing one blank page rewrote all of them. A nested Element URI flattens into a single name (`/jobs/open` → `page-jobs-open.tpl`), because that is the shape the template pickers list. An existing file is never overwritten, so re-running this step leaves work already done in such a page alone. From then on the route owns its template and reads back as its own page rather than as the Blank unit — the same thing a page created in `/_admin` is. Every other template is a finished page and stays shared.

Reserved paths such as `/_admin`, `/_editor`, `/_install`, and `/_templates` cannot be used as public pages.

## 8. Personal Information

This step records central company and website values as textfills. The values are stored globally and can be edited later via `/_admin` or `/_editor`.

The following keys are typically created:

- `/company/name`
- `/company/address`
- `/company/email`
- `/company/phone`
- `/website/name`
- `/website/description`
- `/website/keywords`

These textfills are used in templates, meta tags, and possibly in the footer or contact forms.

## 9. Access for `/_editor`

This step creates the first user account for `/_editor`. The account receives full permissions over `/*` and can manage all content, users, and settings.

Provide:

- a valid **email address**;
- a **password** with at least 8 characters.

The email address and password can be changed later via `/_editor` or `/_admin`.

## 10. Completion

The last step sets the technical password for `/_admin` and `/_templates` and locks the installer. This password is separate from the editor accounts and should be treated as a technical access with full control.

Provide:

- a **password** for `/_admin` and `/_templates`.

The hash is written to `private/.auth/pw.php` and the project is marked installed via `/nino/install/completed` in `config.php`. Either of those alone keeps `/_install` locked, so losing the password file locks `/_admin` rather than handing the installer back. Neither lives in a tool folder, which is what lets an update replace `_nino/`, `_admin/`, `_editor/`, and `_templates/` wholesale.

If completion fails, check the write permissions of the `private/` directory. After setting the password, `/_install` is locked and can no longer be used. The assistant is then removed from production delivery.

## Verify the Result and Remove the Installer

After completion, open the frontend and both management interfaces. Check at least the start page, every configured language, login to `/_editor`, login to `/_admin`, and the selected theme assets.

The installer is intended only for initial setup. Remove or block `/_install` before the website becomes publicly accessible. Keeping it available unnecessarily increases the exposed surface of the project.

## Library Format

The library in `/_install/library/` contains the files for the initial setup. It is structured as follows:

```
_install/library/
├── locales/
│   ├── de_DE/
│   │   ├── text/
│   │   │   └── global.php
│   │   └── elements/
│   │       └── services.php
│   └── en_US/
│       ├── text/
│       │   └── global.php
│       └── elements/
│           └── services.php
├── modules/
│   ├── navigation/
│   │   ├── module.php
│   │   └── templates/
│   │       └── navigation.tpl
│   └── form/
│       ├── module.php
│       └── templates/
│           └── form.tpl
├── header/
│   └── v1/
│       ├── template.tpl
│       └── style.css
├── footer/
│   └── v1/
│       ├── template.tpl
│       └── style.css
└── themes/
    └── default/
        ├── manifest.php
        ├── preview.svg
        ├── assets/
        │   └── style.theme.default.css
        └── fonts/
            └── text/
                └── regular.woff2
```

Each locale directory contains the text and element files for that language. Modules provide their own files and templates.

A theme's `manifest.php` lists the files to be copied plus what the look was drawn against:

| Key | Meaning |
|---|---|
| `label`, `description`, `preview` | picker only; the preview image is served from the library and never copied |
| `stylesheet` | where the copied stylesheet ends up, and what gets bundled |
| `files` | which of the unit's directories are copied into the project |
| `header`, `footer` | the frame units this theme was drawn against |
| `design` | the `/_theme` settings it was drawn with: `primary`, `secondary`, `contrast`, `colors` |

A frame unit has no manifest - a `template.tpl` and an optional `style.css` are everything it has to declare.

## What `/_install` Deliberately Does Not Do

The wizard creates the first working project state, but it does not replace project-specific development. Templates, content models, callbacks, integrations, detailed design, and production configuration remain part of the implementation.

It also does not create `images/`, `templates/`, `text/`, or `elements/` before setup begins. These directories and their initial content are generated from the selected installation library during the process.

## Next Steps

- [Getting Started](getting-started.md) guides through the necessary initial setup.
- [`/_admin` Operation](_admin.md) explains full project administration.
- [`/_templates` Operation](_templates.md) describes the optional template builder in Alpha status.
- [`/_editor` Operation](_editor.md) explains the subsequent, permission-controlled content maintenance.
- [Deployment](deployment.md) describes web server configuration, security, and go-live.
