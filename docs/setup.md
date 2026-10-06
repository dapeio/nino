# The Setup Wizard — Reference Manual

**Language:** English · [Deutsch](setup.de.md)

**Last updated:** September 22, 2026 · **Nino version:** 1.3.1

This manual explains the decisions and writing processes of the six steps of the setup wizard - the first-run mode of the [`/_admin` workbench](_admin.md). If you instead want to take the shortest path from checkout to a configured website, start with [Getting Started](getting-started.md); the later production operation is covered in [Deployment](deployment.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Recipes](recipes/README.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Features](features.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Important:** The wizard creates the first functional project state from a fresh Nino checkout. It is necessary: before its execution a checkout carries neither `private/` nor `public/`, so none of the project directories inside them - `private/templates/`, `private/text/`, `private/elements/`, `public/images/` - exists yet.

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

## 2. Languages

The step is named for what it asks about, and it is what the wizard applies the base unit and the always-on units in - so it creates the project's foundation at the same time.

**Available Locales** determines the available languages. **Native Locale** is the default language and must be part of this selection. Technically, it serves as a fallback as long as no language is yet determined for a visitor, and in terms of content, it forms the "mother tongue" of the website.

When reapplying, the visible language selection replaces the previous state. The default language is retained as long as it is still selected; otherwise, Nino uses the first selected language.

### Modules

Navigation, language selection (the locale picker), the contact form and the legal texts are no longer a choice: `\Nino\Install\Setup::ALWAYS_MODULES` names their unit keys, and every Setup run applies all four units and lists all four classes in `/nino/modules`, exactly as it would for a module actually picked. A developer tool that ships as a module is handled the same way it always was - listed whenever its class exists (`TOOL_MODULES`), no unit to apply. `Maintenance` is the one Nino still ships.

The list that remains offers every *other* module that ships an installer unit: nothing, in a fresh checkout, plus any module a project has added below `app/`, or a fork below `_admin/install/library/modules/`. Features - the catalogue's Newsletter and Search, for instance - are not offered here either: a feature is copied into `features/` from [dapeio/nino-features](https://github.com/dapeio/nino-features) and switched on in the workbench's [Features panel](features.md) after setup. If a selected module requires another module, the assistant automatically includes this dependency in the selection - and finds it already present when that dependency happens to be one of the four always-on ones. A used page template can also pull in required modules; a contact page, for example, works because the contact form's own module is always there.

The **legal texts** - the module `Legal`, see [Developer Manual](development.md#legal) - are the one always-on unit with content of its own: the imprint and the privacy policy as elements of the types `legal` and `privacy`, one element per section in German and English, two pages that show them, and a third navigation, `legal`, that the footer of the base frame outputs. Practically every website owes its visitors an imprint and a privacy policy, and a link to them that was forgotten is the dearer mistake; whoever does not need them hides sections under **Elements** or takes the module out of `/nino/modules`.

**Important:** The imprint and privacy policy Nino ships are a starting point, not legal advice. They are not tailored to any particular website and have not been legally reviewed. The operator of a website is responsible for having them checked by a qualified person before publication and for adapting them: to what the website actually processes, to the operator's legal form, and to further mandatory details such as a commercial register entry, a VAT identification number or a person responsible for journalistic content. The project gives no warranty that the texts are correct, complete or up to date.

The step writes:

- available and native language to `config.php`;
- the activated module classes - the always-on four, any developer tool whose class exists, and whatever else was picked - to `/nino/modules`;
- the routes provided by the base and every applied module to `/nino/http/routes`;
- templates to `templates/`;
- global and language-dependent texts to `text/`;
- provided element types to `elements/` - and the elements a unit brings in its `elements` key, which are only ever added to a type, never replaced: the sections of the legal texts stay as an editor left them when the step is applied again;
- the menus a unit asks for in its `navs` key - a menu the project does not have yet, with its first entries by Element URI (`legal` with the imprint and the privacy policy) - to `/nino/html/navs` and `/nino/html/navroutes`; where the project has the menu already, a second run leaves it and its entries as the editors set them;
- other declared files to their project paths.

A file a unit cannot copy - a target that is not writable - ends the step with a 500 naming the file, and nothing is written to `config.php` for that run; once the target is writable, applying again picks up whole. Languages, the picked *other* modules, and the routes this step manages are replaced on a later reapply; the four always-on units and the routes/templates/text they bring are never removed by it. Manually or by other areas created routes remain preserved. Templates, texts, and element types that have already been copied are not deleted by later deselection.

### The Look

Not a choice, and not a step: the base unit delivers one theme, and every project starts from it. Three files, copied like any other unit file:

| File | What it is |
|---|---|
| `assets/theme.css` | the whole look in one stylesheet: the design tokens, the roles they are assigned to, the three webfaces, and the css for both frames below |
| `templates/frame-header.tpl` | the site's `<header>`, included by `html-header.tpl` through `[template /templates/frame-header]` |
| `templates/frame-footer.tpl` | the site's `<footer>`, included the same way |

The page templates include the two frames rather than carrying their markup, so either can be rewritten without touching the page frame around it. A missing include resolves to an empty string, which is why the base unit lists both files: a delivery that forgot one would ship a site with no header, silently.

The delivery has no logo of its own. The header, the navigation, the Open Graph and Twitter tags in `html-header.tpl` and the mail header of the Form module all ask the **image slot `/logo`** (500 × 100, the shape of a wordmark) with `[image /logo]`, and the base unit declares it empty - `imageSlots`, see below. Upload the logo under **Images** in the workbench; until then the header and the navigation show no picture, the mail header none, and the page head carries no `og:image` and no `twitter:image`, instead of a broken or an empty one. An upload is cut to the slot's shape, so a logo shaped otherwise needs the slot's size changed on the **Image Slots** tab first.

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

The step keeps no list of its own: it writes `/nino/http/routes` and the `/_nino/webpage<uri>/*` text keys — `name`, `title` and `description` per locale, plus `uri` (the page's reachable path) once in `text/global.php`, blacklisted as a technical value — and reads the list back out of them the next time it runs. Menu membership goes onto the page's own route as `'navs' => [ 'main' => 1, ... ]`, using the page's position in the list as its priority — sorting the list here is what orders the menus.

The Element URI is the anchor for page texts like `/_nino/webpage<uri>/title`. The HTTP URI is the path visible in the browser. This separation allows the internal identity to remain stable even if the public path changes.

A new page starts from the selected library template's own suggestions: its HTTP URI, plus Navigation Name, Page Title, and Description in **every** active language, read from the unit's `suggest` entry in its manifest - a string or one string per language each, `'uri'`, `'name'`, `'title'` and `'description'`. They are no text keys: a `/_nino/webpage<uri>/*` key is the system's, and the step writes it under the Element URI the page is mounted at. Switching the template only updates fields that are still untouched — anything typed by hand survives the switch. A field left empty still falls back to the generic placeholder ("Page", "Page Title").

A page unit may also declare unit-relative `files`. They are copied to the same
virtual project paths, so `images/template/page-home/fullscreen-image/background.svg`
becomes the project's public `images/template/page-home/fullscreen-image/background.svg`.

A unit that shows a picture declares it as an **image slot** with
`imageSlots`, keyed by the slot uri - `'/template/page-home/fullscreen-image/background'
=> [ 'label' => [ 'en_US' => 'Home – hero image', 'de_DE' => 'Startseite –
Titelbild' ], 'width' => 1920, 'height' => 1080, 'filename' =>
'template/page-home/fullscreen-image/background.svg' ]` - and its template shows it with
`[image /template/page-home/fullscreen-image/background alt=""]` instead of a literal
`<img>`. The starter site's home hero is seeded with a neutral placeholder
drawing, a small SVG Nino ships; it carries the slot's own name, so an upload
replaces it and **Remove image** deletes it, and the library keeps the source.
A `filename` is a file the unit ships under `files` and is checked once the files
are copied; without one the slot starts empty, which is how the base unit
declares `/logo`. The label is one string for the project, in its native
language. The Routes step - and the Setup step, for the base unit - only **adds** slots: a slot
the project already has - label, size and image - is left as it is, and the
first unit to name a uri wins. A seed file that cannot be copied or a slot that
cannot be declared fails the step by name before any route is written.

A route on the **Blank** template gets its own copy of that template, named after its Element URI: a `/team` route is created as `templates/page-team.tpl` and rendered by `[template /templates/page-team]`. Blank is the empty starting point, so every route picking it needs a page of its own — a shared file would mean editing one blank page rewrote all of them. A nested Element URI flattens into a single name (`/jobs/open` → `page-jobs-open.tpl`), because that is the shape the template pickers list. An existing file is never overwritten, so re-running this step leaves work already done in such a page alone. From then on the route owns its template and reads back as its own page rather than as the Blank unit — the same thing a page created in `/_admin` is. Every other template is a finished page and stays shared. One name it cannot take: an Element URI whose template name a library page already owns — `/home`, `/contact` and the other finished pages — is refused, naming the page that has it, because both would write the same `templates/page-*.tpl`.

The reserved path `/_admin` cannot be used as a public page. Neither can the two Element URIs `/legal/imprint` and `/legal/privacy`: the Legal module owns them with their routes, their names and their menu entries, and a page of your own with one of them is refused with a 409 - whatever unit or template it picks. A page on the Blank template whose name would write the module's own `page-legal-imprint.tpl` is refused too, naming the unit `legal`.

The step offers every registered navigation as a checkbox, `legal` the third beside `main` and `footer`; the starter pages are in none of them for it. The imprint and the privacy policy are not part of this list: the module registers their routes at runtime, one per language, and the Legal pages are in the navigation `legal` from the first run on.

## 4. Personal Information

This step records central company and website values as textfills. It edits nothing else: of the keys the base unit ships, only those under `/project/company/` and `/project/website/general/` - the technical `/project/website/html/` values and the look of the mails are outside both - each labelled by its category and its name ("Company › Address", "Website › URL"), in English like the whole wizard. All of them can be edited later in the workbench's Text panel.

Language-independent, in the order the form shows them:

- `/project/website/general/url`
- `/project/company/general/name`
- `/project/company/contact/email`
- `/project/company/contact/phone`
- `/project/company/contact/address`
- `/project/website/general/author`
- `/project/website/general/host`

`/project/company/contact/country` and `/project/company/general/description` are stored per language, so step through every active language and save its values. A key a library fork adds under either of those prefixes is offered too, appended after these.

These textfills are used in templates, meta tags, and possibly in the footer or contact forms. `/project/website/general/url` is the site's address without the protocol - `www.example.com` -: the base unit's templates put it behind `https://` for the canonical link, the Open Graph and Twitter tags, the JSON-LD block, `sitemap.xml` and `robots.txt`, and the Form module's mails name it, so set it before the site goes live. The technical ones the base unit blacklists - `/project/website/html/charset`, `/project/website/html/lang` - are deliberately left out here and are edited on the workbench's Text Keys tab afterwards.

The links to the site's profiles elsewhere - Instagram, YouTube and the like - are not asked for here: they are the catalogue feature [Social links](https://github.com/dapeio/nino-features/blob/main/features/Social/README.md), an element type the editors keep under Elements once it is switched on.

## 5. Accounts

This step creates the root account of the workbench: the **Developer** role, full access over `/*`, the account you sign in to `/_admin` with. Submit again for a second one, then continue. Editor accounts with fewer rights are created later, in the workbench's **Users** panel, from the **Editor** role - both roles are written by the Setup step and edited on the Users panel's roles tab.

Provide:

- a valid **email address**;
- a **password** with at least 8 characters, entered twice - the form names the rule in the field's label.

Both can be changed later under **Users**. The accounts live in `config.php` under `/nino/auth/user`.

## 6. Finish

The last step sets the **recovery password** and locks the wizard. It is not a login: `/_admin/recovery.php` asks for it when the accounts themselves are what is broken - to restore a backup, set a password or create an account with full access - and the workbench asks for it in one place only, the Recovery password tab of Users, which changes it (see [Recovery](_admin.md#recovery)).

Provide:

- a **password** with at least 8 characters, entered twice - the form names the rule in the field's label.

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

Two manifest keys carry content and are applied add-only by the same `applyUnit()`: `elements` - `[ '<type>' => '<file of the unit>' ]`, a file in the shape of an element type file whose elements are *added* to the type, in the languages that are applied, and never replace a value, a title or a model the project has (the type itself is created from the file only where it does not exist) - and, for the wizard alone, `navs` - `[ '<key>' => [ '<Element URI>', ... ] ]`, a menu the project does not have yet with its first entries. `\Nino\Features::activate()` reads `elements` and not `navs`: a feature brings no menu. The second way the wizard applies a module unit, the one a page unit pulls in through `requiresModules`, reads neither - it knows only `templates`, `blacklist`, `config` and `text`, as it never knew `elementTypes`, `files` or `routes`. See [`\Nino\Elements::seed()`](development.md#legal) and the recipe [installer-package](recipes/installer-package.md).

Not scanned: `features/`. A feature carries an `install/` unit of the same shape, but `\Nino\Features::activate()` applies it when the feature is switched on in the workbench - through the same `applyUnit()` the wizard uses, with overwrite on here and add-only there, so that the unit application survives the removal of `_admin/install/`. See [Features](features.md).

A developer tool that ships as a module has no unit to pick: `\Nino\Install\Setup` lists it in `/nino/modules` whenever its class exists, so its panel is in the workbench from the first `config.php` on.

## What the Wizard Deliberately Does Not Do

The wizard creates the first working project state, but it does not replace project-specific development. Templates, content models, callbacks, integrations, detailed design, and production configuration remain part of the implementation.

It also does not create `private/` or `public/`, nor any of the project directories inside them, before setup begins. These directories and their initial content are generated from the selected installation library during the process.

## Next Steps

- [Getting Started](getting-started.md) guides through the necessary initial setup.
- [`/_admin` Workbench](_admin.md) explains the panels, the accounts and the recovery page.
- The **Template Builder** - page templates composed from whole sections - is a feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features); its [manual](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md) is there too.
- Have the imprint and the privacy policy checked before the site goes live, and adapt them to what the website really processes - see the note in [Modules](#modules) above.
- [Deployment](deployment.md) describes web server configuration, security, and go-live.
