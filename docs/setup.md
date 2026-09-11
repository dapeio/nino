# The Setup Wizard — Reference Manual

**Language:** English · [Deutsch](setup.de.md)

**Last updated:** September 11, 2026 · **Nino version:** 1.2.0-beta

This manual explains the decisions and writing processes of the six steps of the setup wizard - the first-run mode of the [`/_admin` workbench](_admin.md). If you instead want to take the shortest path from checkout to a configured website, start with [Getting Started](getting-started.md); the later production operation is covered in [Deployment](deployment.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Recipes](recipes/README.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Features](features.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Important:** The wizard creates the first functional project state from a fresh Nino checkout. It is necessary: before its execution, the actual project directories such as `templates/`, `text/`, `elements/`, and `images/` do not yet exist.

## When the Wizard Runs

Until its last step has been completed, `/_admin` answers with the wizard instead of the login. It lives in `_admin/install/` and is intended for one-time initial setup. It:

- checks PHP and write permissions;
- sets languages and modules;
- creates the project directories from its library, the site's look among them;
- sets up the first web pages;
- records central website information;
- creates the first developer accounts of the workbench;
- sets the recovery password.

The wizard is not an update tool for a running project. After successful completion, it locks itself out for good and `_admin/install/` can be removed from production delivery.

**Security:** Until completion, the wizard has no access protection at all. Perform the setup locally or in another protected environment, not on an openly accessible domain.

## Navigation and Saving

Each step loads the already saved state and displays it again. As long as the assistant is not completed, you can return to an earlier step, change settings, and reapply them.

Three different rules apply:

| Data Type | Behavior When Reapplying |
|---|---|
| Languages, modules, generated routes, and page list | the visible selection replaces the previously managed state by the assistant |
| Templates, texts, and element types | are supplemented or updated but not automatically deleted |
| The look - `assets/theme.css` and the two frame templates | files with the same name are overwritten |

This distinction protects your own changes. Deselecting a module may remove its configuration; automatically deleting a template file that has been edited in the meantime would not be safe.

## 1. Environment

The first step checks only the environment and writes no files. The following are controlled:

- the running PHP version;
- the required PHP extensions;
- the writability of the project root and already existing runtime paths.

Project directories that do not yet exist are expected. The decisive factor is that PHP is allowed to create them later. "Recheck" only repeats the same diagnoses.

Fix failed checks before continuing. Without sufficient write permissions, the assistant cannot reliably create either configuration or content.

## 2. Setup

Setup sets languages and creates the basis of the project.

### Languages

**Available Locales** determines the available languages. **Native Locale** is the default language and must be part of this selection. Technically, it serves as a fallback as long as no language is yet determined for a visitor, and in terms of content, it forms the "mother tongue" of the website.

When reapplying, the visible language selection replaces the previous state. The default language is retained as long as it is still selected; otherwise, Nino uses the first selected language.

### Modules

Navigation, language selection (the locale picker) and the contact form are no longer a choice: `\Nino\Install\Setup::ALWAYS_MODULES` names their unit keys, and every Setup run applies all three units and lists all three classes in `/nino/modules`, exactly as it would for a module actually picked. A developer tool that ships as a module is handled the same way it always was - listed whenever its class exists (`TOOL_MODULES`), no unit to apply. `Maintenance` is the one Nino still ships.

The list that remains offers every *other* module that ships an installer unit: nothing, in a fresh checkout, plus any module a project has added below `app/`, or a fork below `_admin/install/library/modules/`. Features - the catalogue's Newsletter and Search, for instance - are not offered here either: a feature is copied into `features/` from [dapeio/nino-features](https://github.com/dapeio/nino-features) and switched on in the workbench's [Features panel](features.md) after setup. If a selected module requires another module, the assistant automatically includes this dependency in the selection - and finds it already present when that dependency happens to be one of the three always-on ones. A used page template can also pull in required modules; a contact page, for example, works because the contact form's own module is always there.

Setup writes:

- available and native language to `config.php`;
- the activated module classes - the always-on three, any developer tool whose class exists, and whatever else was picked - to `/nino/modules`;
- the routes provided by the base and every applied module to `/nino/http/routes`;
- templates to `templates/`;
- global and language-dependent texts to `text/`;
- provided element types to `elements/`;
- other declared files to their project paths.

Languages, the picked *other* modules, and the routes managed by Setup are replaced on a later reapply; the three always-on units and the routes/templates/text they bring are never removed by it. Manually or by other areas created routes remain preserved. Templates, texts, and element types that have already been copied are not deleted by later deselection.

### The Look

Not a choice, and not a step: the base unit delivers one theme, and every project starts from it. Three files, copied like any other unit file:

| File | What it is |
|---|---|
| `assets/theme.css` | the whole look in one stylesheet: the design tokens, the roles they are assigned to, the three webfaces, and the css for both frames below |
| `templates/theme.header.tpl` | the site's `<header>`, included by `html-header.tpl` through `[template /templates/theme.header]` |
| `templates/theme.footer.tpl` | the site's `<footer>`, included the same way |

The page templates include the two frames rather than carrying their markup, so either can be rewritten without touching the page frame around it. A missing include resolves to an empty string, which is why the base unit lists both files: a delivery that forgot one would ship a site with no header, silently.

The order in the css bundle is the whole contract, and each layer owns one slot in it:

```
_nino/Nino.css              framework defaults
assets/theme.css            the look - tokens, roles, fonts, both frames
assets/style.css            the project's own, shipped empty
```

Setup seeds `/nino/html/assets`' bundle with the two project entries if they are not in it yet, and appends rather than replaces - whatever a project added itself keeps its place. `assets/style.css` is written once, empty, and never touched again, so a rule put there overrules everything above it.

Up to Nino 1.1 the wizard asked four questions here - a theme from a catalogue of ten, a header and a footer from thirteen frames, and the design values compiled out of them. It does not any more. The catalogue is parked in [`design-library/`](https://github.com/dapeio/nino-features/tree/main/design-library) of the feature repository, waiting for the **Design** feature, which will compile its own stylesheet over the delivered one.

## 3. Routes

This step creates the public page structure. The list can be supplemented, edited, deleted, and sorted. With "Continue", the entire visible list is applied as the new state.

Each page requires:

- an **Element URI** as a stable internal identity;
- an **HTTP URI** as the publicly accessible path;
- a **Template** from the page library, or the route's existing project template;
- **Navigation Name**, **Page Title**, and **Description** for each active language;
- one checkbox per navigation registered in `/nino/html/navs`.

The step keeps no list of its own: it writes `/nino/http/routes` and the `/webpage<uri>/*` text keys — `name`, `title` and `description` per locale, plus `uri` (the page's reachable path) once in `text/global.php`, blacklisted as a technical value — and reads the list back out of them the next time it runs. Menu membership goes onto the page's own route as `'navs' => [ 'main' => 1, ... ]`, using the page's position in the list as its priority — sorting the list here is what orders the menus.

The Element URI is the anchor for page texts like `/webpage<uri>/title`. The HTTP URI is the path visible in the browser. This separation allows the internal identity to remain stable even if the public path changes.

A new page starts from the selected library template's own suggestions: its HTTP URI, plus Navigation Name, Page Title, and Description in **every** active language, read from the template's `text/<locale>.php` files. Switching the template only updates fields that are still untouched — anything typed by hand survives the switch. A field left empty still falls back to the generic placeholder ("Page", "Page Title").

A page unit may also declare unit-relative `files`. They are copied to the same
virtual project paths, so `images/demo.jpg` becomes the project's public
`images/demo.jpg`.

A route on the **Blank** template gets its own copy of that template, named after its Element URI: a `/team` route is created as `templates/page-team.tpl` and rendered by `[template /templates/page-team]`. Blank is the empty starting point, so every route picking it needs a page of its own — a shared file would mean editing one blank page rewrote all of them. A nested Element URI flattens into a single name (`/jobs/open` → `page-jobs-open.tpl`), because that is the shape the template pickers list. An existing file is never overwritten, so re-running this step leaves work already done in such a page alone. From then on the route owns its template and reads back as its own page rather than as the Blank unit — the same thing a page created in `/_admin` is. Every other template is a finished page and stays shared.

The reserved path `/_admin` cannot be used as a public page.

## 4. Personal Information

This step records central company and website values as textfills. The values are stored globally and can be edited later in the workbench's Text panel.

The following keys are typically created:

- `/company/name`
- `/company/address`
- `/company/email`
- `/company/phone`
- `/website/name`
- `/website/description`
- `/website/keywords`

These textfills are used in templates, meta tags, and possibly in the footer or contact forms.

## 5. Accounts

This step creates the root account of the workbench: the **Developer** role, full access over `/*`, the account you sign in to `/_admin` with. Submit again for a second one, then continue. Editor accounts with fewer rights are created later, in the workbench's **Users** panel, from the **Editor** role - both roles are written by the Setup step and edited on the Users panel's roles tab.

Provide:

- a valid **email address**;
- a **password** with at least 8 characters.

Both can be changed later under **Users**. The accounts live in `config.php` under `/nino/auth/user`.

## 6. Finish

The last step sets the **recovery password** and locks the wizard. It is not a login: `/_admin/recovery.php` asks for it when the accounts themselves are what is broken - to restore a backup or to reset a password - and nothing in the workbench ever asks for it (see [Recovery](_admin.md#recovery)).

Provide:

- a **password** with at least 8 characters.

Its hash is written to `private/.auth/pw.php` and the project is marked installed via `/nino/install/completed` in `config.php`. Either of those alone keeps the wizard locked, so losing the password file does not hand it back. Neither lives in a tool folder, which is what lets an update replace `_nino/`, `_admin/` and the modules wholesale.

If completion fails, check the write permissions of the `private/` directory. After this step, `/_admin` serves the login; the wizard cannot be reopened short of clearing `/nino/install/completed` and removing the stored secret.

## Verify the Result and Remove the Wizard

After completion, open the frontend and the workbench. Check at least the start page, every configured language, the login to `/_admin` with the root account, and that the header, the footer and the webfonts the theme declares are all there.

The wizard is intended only for initial setup. Remove `_admin/install/` from the production delivery: nothing outside it reads its library, and what it already copied stays where it was written. See [Deployment](deployment.md#the-wizard-after-setup).

## Library Format

Everything the wizard copies from is one-time installer source, in one of four shapes:

| Path | Purpose |
|---|---|
| `_admin/install/library/base/` | always-applied routes, templates, texts, and assets |
| `_nino/Nino/Modules/<Module>/install/`, `app/…/<Module>/install/` | a module's own unit: the selectable functional addition, beside the class it activates |
| `_admin/install/library/modules/<key>/` | a selectable unit without a runtime class of its own |
| `_admin/install/library/pages/<key>/` | starting point for one concrete page |

Everything below `_admin/install/` is removed together with the wizard after completion; a module's `install/` directory stays with its module, and only the wizard reads it. The library is setup material, not a runtime plugin system.

Module units are found, not listed: the wizard scans `_nino/Nino/Modules/*/install/` - Nino's own optional modules - and then the whole application directory (`app/`, or `NINO_APP_DIR`) up to four levels deep, plus `_admin/install/library/modules/`. A unit's key - what the picker posts and what `requiresModules` names - is the manifest's `key` or, without one, the module directory's lowercased name; it must be a slug and unique, and the first unit to claim a key keeps it, so Nino's own modules keep theirs.

Not scanned: `features/`. A feature carries an `install/` unit of the same shape, but `\Nino\Features::activate()` applies it when the feature is switched on in the workbench - through the same `applyUnit()` the wizard uses, with overwrite on here and add-only there, so that the unit application survives the removal of `_admin/install/`. See [Features](features.md).

A developer tool that ships as a module has no unit to pick: the Setup step lists it in `/nino/modules` whenever its class exists, so its panel is in the workbench from the first `config.php` on.

## What the Wizard Deliberately Does Not Do

The wizard creates the first working project state, but it does not replace project-specific development. Templates, content models, callbacks, integrations, detailed design, and production configuration remain part of the implementation.

It also does not create `images/`, `templates/`, `text/`, or `elements/` before setup begins. These directories and their initial content are generated from the selected installation library during the process.

## Next Steps

- [Getting Started](getting-started.md) guides through the necessary initial setup.
- [`/_admin` Workbench](_admin.md) explains the panels, the accounts and the recovery page.
- The **Template Builder** - page templates composed from whole sections - is a feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features); its [manual](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md) is there too.
- [Deployment](deployment.md) describes web server configuration, security, and go-live.
