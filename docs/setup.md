# The Setup Wizard — Reference Manual

This manual explains the decisions and writing processes of the six steps of the setup wizard - the first-run mode of the [`/_admin` workbench](_admin.md). The shortest path from checkout to a configured website is the [Quick Start](../README.md#quick-start); the later production operation is covered in [Deployment](deployment.md).

**Important:** The wizard creates the first functional project state from a fresh Nino checkout. It is necessary: before its execution a checkout carries neither `private/` nor `public/`, so none of the project directories inside them - `private/templates/`, `private/text/`, `private/elements/`, `public/images/` - exists yet.

## When the Wizard Runs

Until its last step has been completed, `/_admin` answers with the wizard instead of the login. It lives in `_admin/install/` and is intended for one-time initial setup. It:

- checks PHP and write permissions;
- sets languages and modules;
- creates the project directories from its library, the site's look among them;
- records central website information;
- creates the first developer accounts of the workbench;
- offers the applications of Nino's official catalogue and, when asked, places and switches on their features;
- creates the starter pages and sets the recovery password.

The wizard is not an update tool for a running project. After successful completion, it locks itself out for good and `_admin/install/` can be removed from production delivery.

**Security:** Until completion, the wizard has no access protection at all. Perform the setup locally or in another protected environment, not on an openly accessible domain.

## Navigation and Saving

Each step loads the already saved state and displays it again. As long as the assistant is not completed, you can return to an earlier step, change settings, and reapply them.

Three different rules apply:

| Data Type | Behavior When Reapplying |
|---|---|
| Languages and modules | the visible selection replaces the previously managed state by the assistant; a feature an application switched on stays on |
| Applications | the features of the applications picked are placed and switched on; what is on already is left as it is, and nothing is switched off by picking less |
| Templates, texts, and element types | are supplemented or updated but not automatically deleted |
| The look - `assets/theme.css` and the two frame templates | files with the same name are overwritten |

This distinction protects your own changes. Deselecting a module may remove its configuration; automatically deleting a template file that has been edited in the meantime would not be safe.

## 1. Environment

The first step checks only the environment and writes no files. The following are controlled:

- the running PHP version;
- the required PHP extensions (`dom`, `gd`, `mbstring`, `session`, `xmlwriter` and the `PharData` class);
- the writability of the project root and already existing runtime paths.

Project directories that do not yet exist are expected. The decisive factor is that PHP is allowed to create them later. "Recheck" only repeats the same diagnoses.

Fix failed checks before continuing. Without sufficient write permissions, the assistant cannot reliably create either configuration or content.

## 2. Languages

The step is named for what it asks about, and it is what the wizard applies the base unit and the always-on units in - so it creates the project's foundation at the same time.

**Available Locales** determines the available languages. **Native Locale** is the default language and must be part of this selection. Technically, it serves as a fallback as long as no language is yet determined for a visitor, and in terms of content, it forms the "mother tongue" of the website.

When reapplying, the visible language selection replaces the previous state. The default language is retained as long as it is still selected; otherwise, Nino uses the first selected language.

The wizard offers two languages and names each one it installs in its own tongue (`Deutsch`, `English (US)`) for the language switch of the site. A third - or a second one the wizard did not list - is added later, in the workbench's **Language** panel: **Add a language** creates its text file with every key of the native language, all empty, names the language the same way (`Français`; its code where Nino has no name for it, which the **Text** panel changes), and the language is **not** switched on yet. Translate it first, under **Text** - the select lists it marked as inactive - or hand a file over under **Language › Translations**, which exports the native texts as JSON for a translator and imports the translated document into a language that is ticked or not. Tick the language when it is ready. Until then, and for every text it still lacks afterwards, the site shows the text of the native language and says in which language it is (`lang`); the Dashboard counts what is still to translate in each language.

### Modules

Navigation, language selection (the locale picker), the contact form and the legal texts are no longer a choice: `\Nino\Install\Setup::ALWAYS_MODULES` names their unit keys, and every Setup run applies all four units and lists all four classes in `/nino/modules`, exactly as it would for a module actually picked. `Maintenance`, the developer tool that ships as a kernel module, has no unit to apply and is listed on every run as well.

The list that remains offers every *other* module that ships an installer unit: nothing, in a fresh checkout, plus any module a project has added below `app/`, and any unit in an `_admin/install/library/modules/` that a checkout adds for itself - the wizard scans that directory when it exists, and none is shipped. Features - the catalogue's Newsletter and Search, for instance - are not offered here either: a feature is copied into `features/` from [dapeio/nino-features](https://github.com/dapeio/nino-features) and switched on in the workbench's [Features panel](_admin.md#features) after setup. If a selected module requires another module, the assistant automatically includes this dependency in the selection - and finds it already present when that dependency happens to be one of the four always-on ones. A used page template can also pull in required modules; a contact page, for example, works because the contact form's own module is always there.

The **legal texts** - the module `Legal`, see [Developer Manual](development.md#legal) - are the one always-on unit with content of its own: the imprint and the privacy policy as elements of the types `legal` and `privacy`, one element per section in German and English, two pages that show them, and a third navigation, `legal`, that the footer of the base frame outputs. Practically every website owes its visitors an imprint and a privacy policy, and a link to them that was forgotten is the dearer mistake; whoever does not need them hides sections under **Elements** or takes the module out of `/nino/modules`.

**Important:** The imprint and privacy policy Nino ships are a starting point, not legal advice. They are not tailored to any particular website and have not been legally reviewed. The operator of a website is responsible for having them checked by a qualified person before publication and for adapting them: to what the website actually processes, to the operator's legal form, and to further mandatory details such as a commercial register entry, a VAT identification number or a person responsible for journalistic content. The project gives no warranty that the texts are correct, complete or up to date.

The step writes:

- available and native language to `config.php`;
- the activated module classes - the kernel's always-on modules (`\Nino\AppData::DEFAULTS`), the four always-applied units' classes, `Maintenance`, and whatever else was picked - to `/nino/modules`;
- the routes provided by the base and every applied module to `/nino/http/routes`;
- templates to `templates/`;
- global and language-dependent texts to `text/`;
- provided element types to `elements/` - and the elements a unit brings in its `elements` key, which are only ever added to a type, never replaced: the sections of the legal texts stay as an editor left them when the step is applied again;
- the element type `/greetings`, the sentences the Dashboard greets an account with, added the same way - see [The Greeting of the Dashboard](_admin.md#the-greeting-of-the-dashboard);
- the menus a unit asks for in its `navs` key - a menu the project does not have yet, with its first entries by Element URI (`legal` with the imprint and the privacy policy) - to `/nino/html/navs` and `/nino/html/navroutes`; where the project has the menu already, a second run leaves it and its entries as the editors set them;
- other declared files to their project paths.

A file a unit cannot copy - a target that is not writable - ends the step with a 500 naming the file, and nothing is written to `config.php` for that run; once the target is writable, applying again picks up whole. Languages, the picked *other* modules, and the routes this step manages are replaced on a later reapply; the four always-on units and the routes/templates/text they bring are never removed by it. Manually or by other areas created routes remain preserved. Templates, texts, and element types that have already been copied are not deleted by later deselection.

### The Look

Not a choice, and not a step: the base unit delivers one theme, and every project starts from it. Three files, copied like any other unit file:

| File | What it is |
|---|---|
| `assets/theme.css` | the whole look in one stylesheet: the design tokens, the roles they are assigned to, the three webfaces, and the css for both frames below - what the **Design** feature compiles at its defaults, under a header of the kernel's own |
| `templates/frame-header.tpl` | the site's `<header>`, included by `html-header.tpl` through `[template /templates/frame-header]` |
| `templates/frame-footer.tpl` | the site's `<footer>`, included the same way |

The page templates include the two frames rather than carrying their markup, so either can be rewritten without touching the page frame around it. A missing include resolves to an empty string, which is why the base unit lists both files: a delivery that forgot one would ship a site with no header, silently.

The delivery has no logo of its own. It declares two **image slots** (500 × 100, the shape of a wordmark), `/logo`, the picture for a light ground, and `/logo/dark`, the picture for a dark ground, and the base unit declares them empty - `imageSlots`, see below. The header and the navigation draw them with `[logo]`, which puts both in one set and shows the one that suits the ground the logo stands on (the ground says which, see `[logo]` in the [development manual](development.md)); the Open Graph and Twitter tags in `html-header.tpl` and the mail header of the Form module ask `/logo` alone with `[image /logo]`. Upload the logo under **Images** in the workbench; a project with one picture uploads `/logo` and leaves `/logo/dark` empty, and the header then shows that one on every ground. Until there is one, the header and the navigation show no picture - the link of the logo in the header writes the name of the site instead, so that it has something to see and to focus - the mail header none, and the page head carries no `og:image` and no `twitter:image`, instead of a broken or an empty one. An upload is cut to the slot's shape, so a logo shaped otherwise needs the slot's size changed on the **Image Slots** tab first.

The order in the css bundle is the whole contract, and each layer owns one slot in it:

```
_nino/Nino.css              framework defaults
features and modules        the stylesheets they add, in the order they add them
assets/theme.css            the look - tokens, roles, fonts, both frames
assets/style.css            the project's own, shipped empty
```

Setup seeds `/nino/html/assets`' bundle with the two project entries if they are not in it yet, and appends rather than replaces - whatever a project added itself keeps its place. The stylesheets of the features and modules are not in that list: they are put into the bundle at every boot, in front of the project's files (see [Assets](features.md#assets)). `assets/style.css` is written once, empty, and never touched again, so a rule put there overrules everything above it, a feature's stylesheet and the theme included. A file of the project that a module adds later is listed behind `style.css`. A feature SHOULD NOT build on where its stylesheet stands in relation to the theme.

Up to Nino 1.1 the wizard asked four questions here - a theme from a catalogue of ten, a header and a footer from thirteen frames, and the design values compiled out of them. It does not any more. The look is changed afterwards by the **Design** feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features/blob/main/features/Design/feature.php), which compiles its own `assets/theme.css` and frame templates over the delivered ones - and asks before it replaces a file it did not write. The delivered `assets/theme.css` is the body of what that compiler writes at its defaults, regenerated from it with each kernel release, so the look a project starts from and the first look Design compiles are one: its file carries the kernel's header and no stamp of the compiler's, which is why Design asks.

## 3. Personal Information

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

These textfills are used in templates, meta tags, and possibly in the footer or contact forms. `/project/website/general/url` is the site's address without the protocol - `www.example.com`, and `www.example.com/shop` for a site served from a directory, which the address has to carry -: the base unit's templates put it behind `https://` for the canonical link, the Open Graph and Twitter tags, the JSON-LD block, `sitemap.xml` and `robots.txt`, and the Form module's mails name it, so set it before the site goes live. The technical ones the base unit blacklists - `/project/website/html/charset`, `/project/website/html/lang` - are deliberately left out here and are edited on the workbench's Text Keys tab afterwards.

The links to the site's profiles elsewhere - Instagram, YouTube and the like - are not asked for here: they are the catalogue feature [Social links](https://github.com/dapeio/nino-features/blob/main/features/Social/feature.php), an element type the editors keep under Elements once it is switched on.

## 4. Accounts

This step creates the root account of the workbench: the **Developer** role, full access over `/*`, the account you sign in to `/_admin` with. Submit again for a second one, then continue. Editor accounts with fewer rights are created later, in the workbench's **Users** panel, from the **Editor** role - both roles are written by the Setup step and edited on the Users panel's roles tab.

Provide:

- a valid **email address**;
- a **password** with at least 8 characters, entered twice - the form names the rule in the field's label.

Both can be changed later under **Users**. The accounts live in `config.php` under `/nino/auth/user`.

Each account is signed in to your browser as you create it. Whether that sign-in outlives the wizard is decided by the last step: with exactly one account, finishing opens the workbench as that account; with several, finishing ends the session and `/_admin` asks which one you are.

## 5. Applications

An **application** is a short, named set of features from Nino's [catalogue](_admin.md#the-catalogue) with a place to continue in: *Website*, *Blog*, later others - each one only worth having as a whole. The step is the one place the wizard reaches the network, and only when you reach it, which is not before the first account exists: an index is fetched and a package unpacked once somebody has proved to be able to log in.

*Without an application* is the default, and the way ahead whatever the catalogue answers. What the step offers is the applications of the **official** catalogue - the address and the key built into the kernel - that fit this version of Nino, each with its name, what it is for and the features it switches on. A project whose `config.php` names another catalogue address or key, or switches the catalogue off (`/nino/catalogue/url`, `/nino/catalogue/key`), gets none, and the step says so in a sentence; the Features panel is where such a catalogue is used, and its [Applications tab](_admin.md#the-panel) sets an application of it up after the installation. Nothing a request carries changes which catalogue the step reads. The wizard is English, and so are the names and descriptions of the applications and the names of their features it shows, whatever language the project has: the catalogue gives every text in English, and a text that lacks it falls back as everywhere in the catalogue. The Features panel shows them in the language of the workbench.

An application says who provides the start page of the site (`start` in the index): `starter` - the starter pages the wizard creates, see [Finish](#6-finish) -, `own` - the application brings its own through a feature - or nothing. Those that say something exclude each other and are a radio group (*None*, then one of them); the others are checkboxes, and any number of them goes with the one chosen above. The Website and the vCard, say, cannot both own the start page, and a Website and a CRM can share one instance.

Once a start application is set up the choice is taken: coming back to the step, the group is fixed on it and disabled, with a sentence that says its features can be switched off, and others switched on, later in the Features panel; the applications of the checkboxes that are set up are checked and fixed, the others stay open. The server holds the same line: a plan, an apply or the end of the wizard with another start application than the one that is set up - or with none - is a 409 (`install_start_applied`). Where the catalogue cannot be reached, a step back or a new load still shows what is set up: the answer carries the applications of the catalogue that was kept (`data/catalogue.php`) when one of them is in, the group is fixed on the start application, nothing further can be chosen - the step says the catalogue is not reachable - and *Next* and the end of the wizard go on with what is set up. Without a kept catalogue or an application that is in, it is a sentence and *Try again* as ever. Until one is complete the choice stays open, and so does *None* and *Continue without an application* after a failure. A larger application being applied, a blog on top of the website's features, has the smaller one on half way; that is no other choice.

The index it fetched is kept in `data/catalogue.php`, where the Features panel finds it as well; the last step reads what it needs to know about the applications picked from there and asks nobody for the network.

Before it loads anything the step checks what loading needs and names what is missing instead of failing in the middle: the `openssl` extension (the signature), `Phar` and `zlib` (unpacking the archives), `curl` or `allow_url_fopen` (the download), and a `features/` directory the web server can write to. A catalogue that cannot be reached, or whose signature does not hold, is a sentence and *Try again*; the way without an application stays open.

*Next* applies what is picked: the plan lists the features of the chosen applications, each once - the applications in the order of the catalogue, the features of each in the order it lists them - and each of them is downloaded, checked against the signed catalogue (size and SHA-256 of the archive), placed below `features/` and switched on in a request of its own, so that every one shows as it happens. The features a feature requires come along, as in the Features panel. Each feature is placed and switched on under the same lock as the Features panel's changes ([the panel](_admin.md#the-panel)): a feature that finds it taken is a 409, `install_busy`, and the wizard stays where it is. Where one fails, the wizard stays in the step: it names the feature and says why, *Try again* goes on from there - what is switched on is skipped -, and *Continue without an application* leaves what is on, on, and says so. Pages a feature brings through its install unit are kept as they are.

Going back to the Languages step and applying it again leaves the features that are switched on switched on; picking fewer applications later switches nothing off - that is done in the Features panel, where an application that was not picked can be set up later, too: the same features, the same order and the same placing, from the catalogue the project reads.

## 6. Finish

The last step creates the starter pages, sets the **recovery password** and locks the wizard. It is not a login: `/_admin/recovery.php` asks for it when the accounts themselves are what is broken - to restore a backup, set a password or create an account with full access - and the workbench asks for it in one place only, the Recovery password tab of Users, which changes it (see [Recovery](_admin.md#recovery)).

Provide:

- a **password** with at least 8 characters, entered twice - the form names the rule in the field's label.

Its hash is written to `private/.auth/pw.php` and the project is marked installed via `/nino/install/completed` in `config.php`. Either of those alone keeps the wizard locked, so losing the password file does not hand it back. Neither lives in a tool folder, which is what lets an update replace `_nino/`, `_admin/` and the modules wholesale.

If completion fails, check the write permissions of the `private/` directory. After this step the wizard is locked and cannot be reopened short of clearing `/nino/install/completed` and removing the stored secret.

Where it continues depends on the applications that went through. The end takes only what is really applied: an application reported whose features are not all on is a 409 (`install_not_applied`), like a key the catalogue does not list. The wizard names the button that ends it after what it leads to: with exactly one application that went through and has an entry point (`next`, a path of the project, an anchor allowed) the button is *Set up <name>* - the name as the step shows it - and a setup with one account continues straight into that entry point, signed in as that account; without one it ends the wizard and continues into the workbench. With several applications that have an entry point the button stays *Finish installation* and the final page offers a *Set up <name>* link for each of them among the next steps; the same holds where the session is ended because there are several accounts, and `/_admin` then serves the login. The entry point is a path of the project: a scheme, a second leading slash or a control character makes the catalogue drop it.

### The starter pages

There is no step for pages: a project builds its structure in the workbench's **Routes** panel, which reads and writes the same routes and `/_nino/webpage<uri>/*` text keys. What the wizard does create, when it ends, is the **starter pages** - Start, Contact and the 404 page, the page units of `_admin/install/library/pages/` whose manifest claims a `'preset'` position, in the order of that position. It does so silently and only when the project has no page route yet (a `GET` route whose body is a `templates/page-*.tpl` template) and no application that went through says `start` is `own`, in which case the application's start page is the only one.

Whatever the project has, the empty page template `templates/page-blank.tpl` is put in place - the template alone, no route, never over a file that is there: it is the one the Routes panel starts **New route** on. Where an applied application provides the start page itself, the 404 page is the one starter page that is made, and only where no route answers with the status 404 yet: no start page, no contact page, no menu entry. Its link to the start page is the fill `/_nino/webpage/home/uri`, which the starter page *Start* would write; here it is written as `/` (blacklisted like the others), unless something has written it.

A starter page is created like the page unit of a module, with the unit's own suggestions: its route is keyed by the unit's suggested HTTP URI (`GET://` for `/`, `GET://404` for `/404`) and carries the Element URI `/<unit folder>` as its `uri`, the unit's `statusCode` and - where the Navigation module is on - the menus the unit's route proposes, with the page's position in the list as its priority. The unit's `suggest` entry gives the HTTP URI, the name, title and description in **every** active language, a string or one string per language each; they are no text keys - a `/_nino/webpage<uri>/*` key is the system's, written under the Element URI the page is mounted at, together with `uri` (the page's reachable path) once in `text/global.php`, blacklisted as a technical value. A language the unit has no wording for falls back to English. Templates, `elementTypes`, the unit's text and unit-relative `files` are copied the way a module's unit is; a file or a seed the unit names and does not carry fails the step by name, before a route is written, and the wizard stays open.

A unit that shows a picture declares it as an **image slot** with
`imageSlots`, keyed by the slot uri - `'/template/page-home/hero/background'
=> [ 'label' => [ 'en_US' => 'Home – hero image', 'de_DE' => 'Startseite –
Titelbild' ], 'width' => 1920, 'height' => 1080, 'filename' =>
'template/page-home/hero/background.svg' ]` - and its template shows it with
`[image /template/page-home/hero/background alt=""]` instead of a literal
`<img>`. The starter site's home hero is seeded with a neutral placeholder
drawing, a small SVG Nino ships; it carries the slot's own name, so an upload
replaces it and **Remove image** deletes it, and the library keeps the source.
A `filename` is a file the unit ships under `files` and is checked once the files
are copied; without one the slot starts empty, which is how the base unit
declares `/logo` and `/logo/dark`. The label is one string for the project, in its native
language. The starter pages - and the Setup step, for the base unit - only **add** slots: a slot
the project already has - label, size and image - is left as it is, and the
first unit to name a uri wins. A seed file that cannot be copied or a slot that
cannot be declared fails the call by name before any route is written.

The one unit of that folder that claims no position, `blank`, is not applied by the wizard: only its template is put in place, as above. The Routes panel only wires an existing `templates/page-*.tpl` to a path.

## Verify the Result and Remove the Wizard

After completion, open the frontend and the workbench. Check at least the start page, every configured language, the login to `/_admin` with the root account, and that the header, the footer and the webfonts the theme declares are all there. Then create the editor accounts under Users and check what they see.

The wizard is intended only for initial setup. Once it is complete and checked, remove `_admin/install/` from the production delivery: nothing outside it reads its library - it is setup material, not a runtime feature - and everything it copied already lives in the project and stays where it was written. An incomplete installation does not become valid by deleting its wizard.

## Library Format

Everything the wizard copies from is one-time installer source, in one of four shapes:

| Path | Purpose |
|---|---|
| `_admin/install/library/base/` | always-applied routes, templates, texts, and assets |
| `_nino/Nino/Modules/<Module>/install/`, `app/…/<Module>/install/` | a module's own unit, beside the class it activates - applied on every run for Nino's own (`ALWAYS_MODULES`), a choice for a project's |
| `_admin/install/library/modules/<key>/` | a selectable unit without a runtime class of its own |
| `_admin/install/library/pages/<key>/` | one concrete page; the units that claim a `'preset'` position are the starter pages |

Everything below `_admin/install/` is removed together with the wizard after completion; a module's `install/` directory stays with its module, and only the wizard reads it. The library is setup material, not a runtime plugin system.

Module units are found, not listed: the wizard scans `_nino/Nino/Modules/*/install/` - Nino's own optional modules - and then the whole application directory (`app/`, or `NINO_APP_DIR`) up to four levels deep, plus `_admin/install/library/modules/`. A unit's key - what the picker posts and what `requiresModules` names - is the manifest's `key` or, without one, the module directory's lowercased name; it must be a slug and unique, and the first unit to claim a key keeps it, so Nino's own modules keep theirs.

The manifest keys of a unit - what each does - are listed in the [installer package recipe](recipes/installer-package.md), which is also the step-by-step for writing one. The two keys that carry content, `elements` and `navs`, are applied add-only by the same `applyUnit()` that a feature's activation uses; see [Legal](development.md#legal) for `\Nino\Elements::seed()`.

Not scanned: `features/`. A feature carries an `install/` unit of the same shape, but `\Nino\Features::activate()` applies it when the feature is switched on - in the workbench, or by the wizard's Applications step - through the same `applyUnit()` the wizard uses for a module, with overwrite on here and add-only there, so that the unit application survives the removal of `_admin/install/`. See [the install unit of a feature](features.md#the-install-unit).

`Maintenance` ships with the kernel and has no unit to pick: `\Nino\Install\Setup` lists it in `/nino/modules` on every run, so its panel is in the workbench from the first `config.php` on.

## What the Wizard Deliberately Does Not Do

The wizard creates the first working project state, but it does not replace project-specific development. Templates, content models, callbacks, integrations, detailed design, and production configuration remain part of the implementation.

It also does not create `private/` or `public/`, nor any of the project directories inside them, before setup begins. These directories and their initial content are generated from the selected installation library during the process.
