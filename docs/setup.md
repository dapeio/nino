# The Setup Wizard — Reference Manual

This manual explains the decisions and writing processes of the six steps of the setup wizard - the first-run mode of the [`/_admin` workbench](_admin.md). The shortest path from checkout to a configured website is the [Quick Start](../README.md#quick-start); the later production operation is covered in [Deployment](deployment.md).

**Important:** The wizard creates the first functional project state from a fresh Nino checkout. It is necessary: before its execution a checkout carries neither `private/` nor `public/`, so none of the project directories inside them - `private/templates/`, `private/text/`, `private/elements/`, `public/images/` - exists yet.

## When the Wizard Runs

Until its last step has been completed, `/_admin` answers with the wizard instead of the login. It lives in `_admin/install/` and is intended for one-time initial setup. It speaks German and English, German first: the choice stands at the top right, holds for every step and over a reload, and is kept in the browser alone (one entry of its storage, no cookie), so a project that is not yet there has nothing to ask for it. Every sentence is in one table with both languages (`install/assets/text.js`), and what the server refuses comes back as a code that table words in the language chosen. It:

- checks PHP and write permissions;
- sets the language of the website and the modules;
- creates the project directories from its library, the stylesheet of the site among them;
- records central website information;
- creates the first developer accounts of the workbench;
- offers the applications of Nino's official catalogue and, when asked, places and switches on their features;
- creates the starter pages - with the header and the footer they are drawn with - and sets the recovery password.

The wizard is not an update tool for a running project. After successful completion, it locks itself out for good and `_admin/install/` can be removed from production delivery.

**Security:** The wizard is open until its first account exists, and asks for that account's session from then on. Until the account is made, whoever reaches the address can use every step - including the one that makes the first account, and whoever makes it is signed in as it - so perform the setup locally or in another protected environment, not on an openly accessible domain, and make the account at once. See [Who may do what](#who-may-do-what-before-and-after-the-first-account).

## Who May Do What, Before and After the First Account

| | Before an account that can sign in exists | From the first account on |
|---|---|---|
| The page of the wizard | the same for everybody, but for the address of the account of the session that draws it (the page's own to know) | the same |
| Environment, language, details (read and write) | open to a visitor with the token of the page | the session of an account whose rights are not narrower than the Developer's, and the token of that session |
| Applications and the end of the wizard | refused with 409: an account comes first | the same session; 401 for a visitor |
| The account step | makes the first account and signs its maker in | changes the own account of the session: its address and its password |

From the first account on the wizard is the work of the developer as a whole. Every action of every step - the reading ones too - asks for a session that holds everything (`/*`, `Accounts::rootPerms()`) - what the first account holds through the Developer role or, where the project stores a narrower role under that name, of its own - and for the token of that session. An account with fewer rights - the Editor role, an account that may only manage users or change the language - is refused with 403 (`install_not_allowed`) at every action; the wizard writes roles, routes, modules and accounts, which no right of one panel stands for. More accounts and accounts with fewer rights are made in the Users panel after the setup.

Without a session the answer is the workbench's: 401 with the code `session`, or 403 with the code `csrf` for a token that is not the one of the session. Before the first account the actions that need an account answer 409 (`install_no_account`) and after it 401: the wizard tells whether an account exists, as the login page of a fresh installation does, and no more than that. The page does what the workbench does: it asks which session this is and what its token is (`GET /_admin/?session=1`, which answers the account's mail and the token, nothing else), and where nobody is signed in it asks for the account in a dialog and sends the request again, so the step that was on goes on. That is what happens after the cookie is gone or the sign-in has run out; a reload keeps the cookie and needs nothing. A request that was refused is never sent again as another account: where a second tab of the browser signed in as another one, the page is loaded anew.

The first account is made under a lock of `config.php`: whether an account exists is asked again from the file once the lock is held, so that of requests that arrive together one makes the account and is signed in, and the others are told there is one (401) and get no session. The step signs its account in without asking for the password it has just set, so that an address that is cooling down from earlier attempts cannot leave the account made and its maker outside. Once it exists, a visitor can neither add an account nor replace one.

The own account is changed under that lock as well: an address that another request gave to an account meanwhile, in the same or in other case, is refused 400 (`install_mail_in_use`) and nothing is written; an account that was deleted, switched off or logged out everywhere while the request was on the way is answered 401 with the code `session`, as a request without a session is; and what another request set on any account meanwhile - a role, a name - stays. A current password that was confirmed against a hash another request of the session has replaced meanwhile is refused as a wrong one, and no attempt is counted.

## Navigation and Saving

Each step loads the already saved state and displays it again. As long as the assistant is not completed, you can return to an earlier step, change settings, and reapply them.

Three different rules apply:

| Data Type | Behavior When Reapplying |
|---|---|
| Language and modules | the visible selection replaces the previously managed state by the assistant; a feature an application switched on stays on |
| Applications | the features of the applications picked are placed and switched on; what is on already is left as it is, and nothing is switched off by picking less |
| Templates, texts, and element types | are supplemented or updated but not automatically deleted |
| The scaffold, the stylesheet of the site and the frame templates | files with the same name are overwritten |

This distinction protects your own changes. Deselecting a module may remove its configuration; automatically deleting a template file that has been edited in the meantime would not be safe.

## 1. Environment

The first step checks only the environment and writes no files. The following are controlled:

- the running PHP version;
- the required PHP extensions (`dom`, `gd`, `mbstring`, `session`, `xmlwriter` and the `PharData` class);
- the writability of the project root and already existing runtime paths.

Project directories that do not yet exist are expected. The decisive factor is that PHP is allowed to create them later. "Recheck" only repeats the same diagnoses.

Fix failed checks before continuing. Without sufficient write permissions, the assistant cannot reliably create either configuration or content.

## 2. Language

The step is named for what it asks about, and it is what the wizard applies the base unit and the always-on units in - so it creates the project's foundation at the same time.

It asks for **one language**, the language of the website, as one select of the languages the library has texts in (`Deutsch`, `English (US)`). That language is the only one of the project and its native one: technically the fallback as long as no language is yet determined for a visitor, and in terms of content the "mother tongue" of the website. A project that has not answered the step yet starts on the language of the page the wizard is read in, where the library has texts in it; one that has keeps its own. Under the select, the API takes what it always took - `locales`, a list, and `native`, which must be one of them -, so a script or a test can set up a project in several languages at once; the page sends a list of one.

When reapplying, the language that is selected replaces the previous state: the project has that one language and no other.

The wizard names the language it installs in its own tongue (`Deutsch`, `English (US)`) for the language switch of the site. A second language - or a third, or one the wizard did not list - is added later, in the workbench's **Language** panel: **Add language** creates its text file with every key of the native language, all empty, names the language the same way (`Français`; its code where Nino has no name for it, which the **Text** panel changes), and the language is **not** switched on yet. Translate it first, under **Text** - the select lists it marked as inactive - or hand a file over under **Language › Translations**, which exports the native texts as JSON for a translator and imports the translated document into a language that is ticked or not. Tick the language when it is ready. Until then, and for every text it still lacks afterwards, the site shows the text of the native language and says in which language it is (`lang`); the Dashboard counts what is still to translate in each language.

What stays to be done by hand on the html starter site when a second language is added there, because the starter pages are plain templates and the texts of a language are files: the texts of the starter pages and of the header and the footer - the `/template/page-...` and `/template/frame-...` keys, which the **Text** panel lists with the new language empty - are translated or imported there; the page names, titles and descriptions of Start, Contact and 404 (`/_nino/webpage/<page>/name`, `title`, `description`) are the ones of the **Routes** panel; the words the legal pages have in the library's own languages come with the language (see Legal), and the menu needs nothing, because it is a list of links in the header and the footer whose words are the page names. No page, route or template is written again.

### Modules

Navigation, language selection (the locale picker), the contact form and the legal texts are no longer a choice: `\Nino\Install\Setup::ALWAYS_MODULES` names their unit keys, and every Setup run applies all four units and lists all four classes in `/nino/modules`, exactly as it would for a module actually picked. `Maintenance`, the developer tool that ships as a kernel module, has no unit to apply and is listed on every run as well.

The list that remains offers every *other* module that ships an installer unit: nothing, in a fresh checkout, plus any module a project has added below `app/`, and any unit in an `_admin/install/library/modules/` that a checkout adds for itself - the wizard scans that directory when it exists, and none is shipped. Features - the catalogue's Newsletter and Search, for instance - are not offered here either: a feature is copied into `features/` from [dapeio/nino-features](https://github.com/dapeio/nino-features) and switched on in the workbench's [Features panel](_admin.md#features) after setup. If a selected module requires another module, the assistant automatically includes this dependency in the selection - and finds it already present when that dependency happens to be one of the four always-on ones. A used page template can also pull in required modules; a contact page, for example, works because the contact form's own module is always there.

The **legal texts** - the module `Legal`, see [Developer Manual](development.md#legal) - are the one always-on unit with content of its own: the imprint and the privacy policy as elements of the types `legal` and `privacy`, one element per section in German and English, two pages that show them, and a third navigation, `legal`, which a footer may output with `[navigation nav="legal"]` (the footer of the starter site links the two pages itself). Practically every website owes its visitors an imprint and a privacy policy, and a link to them that was forgotten is the dearer mistake; whoever does not need them hides sections under **Elements** or takes the module out of `/nino/modules`.

**Important:** The imprint and privacy policy Nino ships are a starting point, not legal advice. They are not tailored to any particular website and have not been legally reviewed. The operator of a website is responsible for having them checked by a qualified person before publication and for adapting them: to what the website actually processes, to the operator's legal form, and to further mandatory details such as a commercial register entry, a VAT identification number or a person responsible for journalistic content. The project gives no warranty that the texts are correct, complete or up to date.

The step writes:

- available and native language to `config.php`;
- the activated module classes - the kernel's always-on modules (`\Nino\AppData::DEFAULTS`), the four always-applied units' classes, `Maintenance`, and whatever else was picked - to `/nino/modules`;
- the routes provided by the base and every applied module to `/nino/http/routes`;
- templates to `templates/`;
- global and language-dependent texts to `text/`;
- provided element types to `elements/` - and the elements a unit brings in its `elements` key, which are only ever added to a type, never replaced: the sections of the legal texts stay as an editor left them when the step is applied again;
- the menus a unit asks for in its `navs` key - a menu the project does not have yet, with its first entries by Element URI (`legal` with the imprint and the privacy policy) - to `/nino/html/navs` and `/nino/html/navroutes`; where the project has the menu already, a second run leaves it and its entries as the editors set them;
- other declared files to their project paths.

A file a unit cannot copy - a target that is not writable - ends the step with a 500 naming the file, and nothing is written to `config.php` for that run; once the target is writable, applying again picks up whole. Languages, the picked *other* modules, and the routes this step manages are replaced on a later reapply; the four always-on units and the routes/templates/text they bring are never removed by it. Manually or by other areas created routes remain preserved. Templates, texts, and element types that have already been copied are not deleted by later deselection.

### The Look

Not a choice, and not a step: the starter site is plain html with a stylesheet of its own, drawn by the kernel alone, and every project starts from that. No feature is switched on for it - Basis, a feature of the catalogue, is not part of it: a project gets it in the **Features** panel or with an application. The base unit copies an empty stylesheet, the last step the stylesheet of the site with the two frames and the pages:

| File | What it is |
|---|---|
| `assets/style.css` (base unit) | the project's own stylesheet, which starts empty: a comment that says it is this site's own stylesheet and no rule |
| `assets/style.css` (frame unit, last step) | the stylesheet of the site, which replaces the empty one, about 6.9 KB: the header and its menu, the title area, the sections and the cards, the footer and the print view, written against the tokens of `Nino.css` and with classes of its own; the classes the frames and the pages use are defined in it |
| `templates/frame-header.tpl` (frame unit, last step) | the site's header: a `<header>` with the brand, the menu and the contact button, put into the `[[header]]` of `html-header.tpl`, as the frame a page chose with `[frame /templates/frame-header-<word>]` or as the standard - see development.md, The frame of a page |
| `templates/frame-footer.tpl` (frame unit, last step) | the site's footer: the name and description of the company, the menu again, the year and the links to the imprint and the privacy policy, included the same way |

The frames are written with `[link]` and `[imagevalues]` of the kernel and nothing else (see [Shortcodes](development.md#shortcodes)). The menu is a list of links, fixed in the template: `[link]...[/link]` for the start page and `[link /_nino/webpage/contact/uri]...[/link]` for a page, the address being the text the page has in each language, and the page that is shown carries `aria-current="page"`. Under 768 px the list is a `<details>` with a button under the header, with no script; from there on it stands open in the line - in a browser that knows `::details-content`; one that does not keeps the button and the list under the header at every width, which works as well. The brand is a link to the start page that holds the light logo and the dark logo where there are any, the one there is where there is one, and the name of the company as text where there is none: `[imagevalues /logo]...[else]...[/imagevalues]`. The links to the imprint and the privacy policy are `[link /_nino/webpage/legal/imprint/uri]` and `/privacy/uri`, the addresses the Legal module registers for its pages in each language, so the legal check counts them as a way to the pages (see [Legal](development.md#legal)); a footer that outputs the menu `legal` or has the components of Basis' `footer-legal` does as well.

The frames are the unit `_admin/install/library/frame/`, which the starter pages write ahead of the pages - only where the project has no page route and no application provides the start page. The frame unit holds the stylesheet of the site as well, and the last step writes it over the empty one the Setup step put there - so a stylesheet the operator changed in between is replaced where the starter pages are written, as a frame is, and stays where an application provides the start page - until the step "Language" is sent again, which writes the base unit over it, the empty stylesheet, as it writes every file of that unit. A site an application gives its own start page has no header and no footer and no rule of the starter site - its bundle is the reset, the look of the form and the empty `style.css` - until the application brings them; every page is drawn all the same, the scaffold with nothing in the places of the header and the footer.

The page templates include the two frames through the scaffold rather than carrying their markup, so either can be rewritten without touching the page frame around it. A missing frame resolves to an empty string - and that is the way a site without them is meant to be drawn.

The delivery has no logo of its own. It declares two **image slots** (500 × 100, the shape of a wordmark), `/logo`, the picture for a light ground, and `/logo/dark`, the picture for a dark ground, and the base unit declares them empty - `imageSlots`, see below. The header of the starter site draws them as above, and the style shows the one that fits the colour scheme of the page; the Open Graph and Twitter tags in `html-header.tpl` and the mail header of the Form module ask `/logo` alone with `[imagevalues /logo]`. Upload the logo under **Images** in the workbench; a project with one picture uploads `/logo` and leaves `/logo/dark` empty, and the header then shows that one on every ground. Until there is one, the header shows the name of the company, the mail header no picture, and the page head carries no `og:image` and no `twitter:image`, instead of a broken or an empty one. An upload is cut to the slot's shape, so a logo shaped otherwise needs the slot's size changed on the **Image Slots** tab first.

The order in the css bundle is the whole contract, and each layer owns one slot in it:

```
_nino/Nino.css              the reset: the tokens --brand-... and --page-..., the elements at rest
features and modules        the stylesheets they add, in the order they add them - the standard parts of a feature among them, where it is on
assets/style.css            the project's own: empty from the step "Language" on, the stylesheet of the starter site where the starter pages are written
```

Setup seeds `/nino/html/assets`' bundle with the project's entry if it is not in it yet, and appends rather than replaces - whatever a project added itself keeps its place. The stylesheets of the features and modules are not in that list: they are put into the bundle at every boot, in front of the project's files (see [Assets](features.md#assets)). A fresh starter site's bundle is the reset, the look of the form and `assets/style.css`: 21.4 KB - and the bundle of a site whose start page an application provides, the same without the rules of the site, where the bundle with the standard parts of Basis is 46.9 KB. `assets/style.css` is the project's from the first day on - the Setup step writes it empty with the base unit, the last step fills it with the stylesheet of the site, and a project edits it - so a rule put there overrules everything above it, a feature's stylesheet and a theme included. A file of the project that a module adds later is listed behind `style.css`. A feature SHOULD NOT build on where its stylesheet stands in relation to the theme.

The wizard asks no question about the look. A project turns it afterwards: `assets/style.css`, in the layer `project`, sets the tokens of the brand and of the page that `Nino.css` declares (`:root { --brand-color-1: #0a7d5a; }`) and holds the rules of the starter site - delete what the pages you keep do not use -, and a design a project keeps in `assets/theme.css` - a file of its own, none is delivered - stands in the layer `nino.theme`, between the standard parts of the features and `style.css`. A rule of either overrules a rule of `Nino.css` or of a feature with no more than a plain selector ([Assets](features.md#assets)). The classes of the site's templates (`site-header`, `hero`, `card` and the like) belong to the site: a library template uses a class of the kernel (`nino-*`) or one `style.css` defines.

## 3. Personal Information

This step records central company and website values as textfills. It edits nothing else: of the keys the base unit ships, only those under `/project/company/` and `/project/website/general/` - the technical `/project/website/html/` values and the look of the mails are outside both - each labelled by its category and its name ("Company › Address", "Website › URL"; "Unternehmen › Adresse" in German), in the language the wizard is read in. All of them can be edited later in the workbench's Text panel.

Language-independent, in the order the form shows them:

- `/project/website/general/url`
- `/project/company/general/name`
- `/project/company/contact/email`
- `/project/company/contact/phone`
- `/project/company/contact/address`
- `/project/website/general/author`
- `/project/website/general/host`

`/project/company/contact/country` and `/project/company/general/description` are stored per language: the form shows them in the language of the website, under their own heading, and saves them for it. A key a library fork adds under either of those prefixes is offered too, appended after these.

These textfills are used in templates, meta tags, and possibly in the footer or contact forms. `/project/website/general/url` is the site's address without the protocol - `www.example.com`, and `www.example.com/shop` for a site served from a directory, which the address has to carry -: the base unit's templates put it behind `https://` for the canonical link, the Open Graph and Twitter tags, the JSON-LD block, `sitemap.xml` and `robots.txt`, and the Form module's mails name it, so set it before the site goes live. The technical ones the base unit blacklists - `/project/website/html/charset`, `/project/website/html/lang` - are deliberately left out here and are edited on the workbench's Text Keys tab afterwards.

The links to the site's profiles elsewhere - Instagram, YouTube and the like - are not asked for here: they are the catalogue feature [Social links](https://github.com/dapeio/nino-features/blob/main/features/Social/feature.php), an element type the editors keep under Elements once it is switched on.

## 4. Accounts

This step creates the root account of the workbench: the **Developer** role, full access over `/*`, the account you sign in to `/_admin` with. **Create account** makes it; what is typed into the form when **Next** is pressed is made first, as Enter in a field does. From then on the same form changes the own account of the session - its address and its password, with the current password asked, as the Users panel asks it for the own account - and makes no other. More accounts, Editor accounts with fewer rights among them, are created later, in the workbench's **Users** panel, from the **Editor** role - both roles are written by the Setup step and edited on the Users panel's roles tab.

Provide:

- a valid **email address**;
- a **password** with at least eight characters and at most 72 bytes (a letter outside the plain Latin alphabet takes two to four of them), entered twice - the rule stands behind the (?) of the field; a password that is too short or a repeat that differs is said under its field, with the focus on it, before anything is sent, and a password that is too long or holds a null byte is said there when the server answers.

An address that differs only in upper and lower case from an entry that exists is refused as already in use, for the first account as well; an entry at exactly that address that cannot sign in (a deactivated account, one with no password) is replaced by the new account.

Both can be changed later under **Users**. The accounts live in `config.php` under `/nino/auth/user`.

The account is signed in to your browser as it is made. Whether that sign-in outlives the wizard is decided by the last step: with exactly one account, finishing opens the workbench as that account; with several (an account added by hand to `config.php`), finishing ends the session and `/_admin` asks which one you are.

Once the first account exists, this step is asked for a session like every other (see [Who may do what](#who-may-do-what-before-and-after-the-first-account)): a visitor cannot add an account or replace the password of the one there is, and the session itself changes only its own account - an address that belongs to another account is refused.

## 5. Application

An **application** is a short, named set of features from Nino's [catalogue](_admin.md#the-catalogue) with a place to continue in: *Website*, *Blog*, later others - each one only worth having as a whole. The step is the one place the wizard reaches the network, and only when you reach it, which is not before the first account exists: an index is fetched and a package unpacked once somebody has proved to be able to log in.

*Without an application* is the default, and the way ahead whatever the catalogue answers. What the step offers is the applications of the **official** catalogue - the address and the key built into the kernel - that fit this version of Nino, each with its name, what it is for and the features it switches on. A project whose `config.php` names another catalogue address or key, or switches the catalogue off (`/nino/catalogue/url`, `/nino/catalogue/key`), gets none, and the step says so in a sentence; the Features panel is where such a catalogue is used, and its [Applications tab](_admin.md#the-panel) sets an application of it up after the installation. Nothing a request carries changes which catalogue the step reads. The names and descriptions of the applications and the names of their features it shows are the catalogue's own: it gives every text in English, the wizard asks for them in German where its page speaks German, and a text that lacks that falls back to English as everywhere in the catalogue. The Features panel shows them in the language of the workbench.

An application says who provides the start page of the site (`start` in the index): `starter` - the starter pages the wizard creates, see [Finish](#6-finish) -, `own` - the application brings its own through a feature - or nothing. Those that say something exclude each other and are a radio group (*None*, then one of them); the others are checkboxes, and any number of them goes with the one chosen above. The Website and the vCard, say, cannot both own the start page, and a Website and a CRM can share one instance.

Once a start application is set up the choice is taken: coming back to the step, the group is fixed on it and disabled, with a sentence that says its features can be switched off, and others switched on, later in the Features panel; the applications of the checkboxes that are set up are checked and fixed, the others stay open. The server holds the same line: a plan, an apply or the end of the wizard with another start application than the one that is set up - or with none - is a 409 (`install_start_applied`). Where the catalogue cannot be reached, a step back or a new load still shows what is set up: the answer carries the applications of the catalogue that was kept (`data/catalogue.php`) when one of them is in, the group is fixed on the start application, nothing further can be chosen - the step says the catalogue is not reachable - and *Next* and the end of the wizard go on with what is set up. Without a kept catalogue or an application that is in, it is a sentence and *Try again* as ever. Until one is complete the choice stays open, and so does *None* and *Continue without an application* after a failure. A larger application being applied, a blog on top of the website's features, has the smaller one on half way; that is no other choice.

The index it fetched is kept in `data/catalogue.php`, where the Features panel finds it as well; the last step reads what it needs to know about the applications picked from there and asks nobody for the network.

Before it loads anything the step checks what loading needs and names what is missing instead of failing in the middle: the `openssl` extension (the signature), `zlib` (unpacking the archives, in memory by `\Nino\Archive`), `curl` or `allow_url_fopen` (the download), and a `features/` directory the web server can write to. A catalogue that cannot be reached, or whose signature does not hold, is a sentence and *Try again*; the way without an application stays open.

*Next* applies what is picked: the plan lists the features of the chosen applications, each once - the applications in the order of the catalogue, the features of each in the order it lists them - and each of them is downloaded, checked against the signed catalogue (size and SHA-256 of the archive), placed below `features/` and switched on in a request of its own, so that every one shows as it happens. The features a feature requires come along, as in the Features panel. Each feature is placed and switched on under the same lock as the Features panel's changes ([the panel](_admin.md#the-panel)): a feature that finds it taken is a 409, `install_busy`, and the wizard stays where it is. Where one fails, the wizard stays in the step: it names the feature and says why, *Try again* goes on from there - what is switched on is skipped -, and *Continue without an application* leaves what is on, on, and says so. Pages a feature brings through its install unit are kept as they are.

Going back to the Languages step and applying it again leaves the features that are switched on switched on; picking fewer applications later switches nothing off - that is done in the Features panel, where an application that was not picked can be set up later, too: the same features, the same order and the same placing, from the catalogue the project reads.

## 6. Finish

The last step creates the starter pages, sets the **recovery password**, makes the project's backup key and locks the wizard. The recovery password is not a login: `/_admin/recovery.php` asks for it when the accounts themselves are what is broken - to restore a backup, set a password or create an account with full access - and the workbench asks for it in one place only, the Recovery password tab of Users, which changes it (see [Recovery](_admin.md#recovery)). The backup key is shown once as the **recovery key**: a button copies it, another saves it as a text file made in the browser, and the wizard goes on only after "I have saved the recovery key" is ticked. Nothing shows it again; keep it apart from the backups (see [Backups and Restoration](deployment.md#backups-and-restoration)).

Provide:

- a **password** with at least eight characters and at most 72 bytes, entered twice - the rule stands behind the (?) of the field, and what is wrong is said under the field.

The one main button of the bar names itself for what it leads to: **Finish installation**, or - where exactly one application that was set up has an entry point - **Set up <name>**. Its hash is written to `private/.auth/pw.php` and the project is marked installed via `/nino/install/completed` in `config.php`. Either of those alone keeps the wizard locked, so losing the password file does not hand it back. Neither lives in a tool folder, which is what lets an update replace `_nino/`, `_admin/` and the modules wholesale.

If completion fails, check the write permissions of the `private/` directory. After this step the wizard is locked and cannot be reopened short of clearing `/nino/install/completed` and removing the stored secret.

Where it continues depends on the applications that went through. The end takes only what is really applied: an application reported whose features are not all on is a 409 (`install_not_applied`), like a key the catalogue does not list. The wizard names the button that ends it after what it leads to: with exactly one application that went through and has an entry point (`next`, a path of the project, an anchor allowed) the button is *Set up <name>* - the name as the step shows it - and a setup with one account continues straight into that entry point, signed in as that account; without one it ends the wizard and continues into the workbench. With several applications that have an entry point the button stays *Finish installation* and the final page offers a *Set up <name>* link for each of them among the next steps; the same holds where the session is ended because there are several accounts, and `/_admin` then serves the login. The entry point is a path of the project: a scheme, a second leading slash or a control character makes the catalogue drop it. An application whose index entry says `menu: folded` leads into a workbench with its menu folded: the wizard sets the state the workbench keeps in the browser (the menu's pin, the one the fold button writes) to folded when it leads to that application - straight away, or when its link among the next steps is opened - and only where the browser has no pin yet, so a choice somebody made stays theirs and unfolding the menu afterwards is kept like any other. An application that says nothing leaves the menu to the workbench, which folds it for panels laid out as workspaces only.

### The starter pages

There is no step for pages: a project builds its structure in the workbench's **Routes** panel, which reads and writes the same routes and `/_nino/webpage<uri>/*` text keys. What the wizard does create, when it ends, is the **starter pages** - Start, Contact and the 404 page, the page units of `_admin/install/library/pages/` whose manifest claims a `'preset'` position, in the order of that position. It does so silently and only when the project has no page route yet (a `GET` route whose body is a `templates/page-*.tpl` template) and no application that went through says `start` is `own`, in which case the application's start page is the only one. While the wizard switches the features of an application that says `start` is `starter` on, the route of the start page (`GET://`) counts as the project's own: the install unit of a feature that would take `/` where the project has none leaves it out, and the starter page *Start* gets it when the wizard ends.

After the starter pages, the last step applies once more, add-only, the install units of the features of every applied application whose `start` is not `starter`: a route such a unit left out while `/` was reserved in an earlier round comes with it, what the project has stays, and the wizard never ends on a project without a route for `/`.

Whatever the project has, the empty page template `templates/page-blank.tpl` is put in place - the template alone, no route, never over a file that is there: it is the one the Routes panel starts **New route** on. Where an applied application provides the start page itself, the 404 page is the one starter page that is made, and only where no route answers with the status 404 yet: no start page, no contact page, no menu entry, and no header and footer. Its button to the start page is `[link class="button"]...[/link]`, the link that names no address, which is the address of the site wherever the start page comes from, so nothing is written for it.

The frames come first: the unit `_admin/install/library/frame/` - `frame-header.tpl`, `frame-footer.tpl` and the words of the header's button and of the menus' labels - is copied the way a module's unit is, before the pages, and only where the pages are made at all. A starter page is created like the page unit of a module, with the unit's own suggestions: its route is keyed by the unit's suggested HTTP URI (`GET://` for `/`, `GET://404` for `/404`) and carries the Element URI `/<unit folder>` as its `uri`, the unit's `statusCode` and - where the Navigation module is on - the menus the unit's route proposes, with the page's position in the list as its priority. The starter pages of the library propose none - their templates link the pages by hand, so a menu in the **Navigations** panel would change nothing on the site - and the menus `main` and `footer` stand empty where a template of the project outputs them with `[navigation nav="main"]`; the menu `legal` has the two pages of the Legal module, which the module's own unit asks for. The unit's `suggest` entry gives the HTTP URI, the name, title and description in **every** active language, a string or one string per language each; they are no text keys - a `/_nino/webpage<uri>/*` key is the system's, written under the Element URI the page is mounted at, together with `uri` (the page's reachable path) once in `text/global.php`, blacklisted as a technical value. A language the unit has no wording for falls back to English. Templates, `elementTypes`, the unit's text and unit-relative `files` are copied the way a module's unit is; a file or a seed the unit names and does not carry fails the step by name, before a route is written, and the wizard stays open.

A unit that shows a picture declares it as an **image slot** with
`imageSlots`, keyed by the slot uri - `'/template/page-home/hero/background'
=> [ 'label' => [ 'en_US' => 'Home – hero image', 'de_DE' => 'Startseite –
Titelbild' ], 'width' => 1920, 'height' => 1080, 'filename' =>
'template/page-home/hero/background.svg' ]` - and its template shows it with
`[imagevalues /template/page-home/hero/background]<img src="[[src]]" ...>[/imagevalues]` instead of a literal
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

After completion, open the frontend and the workbench. Check at least the start page, every configured language, the login to `/_admin` with the root account, and that the header, the footer and the fonts of the brand are all there. Then create the editor accounts under Users and check what they see.

The wizard is intended only for initial setup. Once it is complete and checked, remove `_admin/install/` from the production delivery: nothing outside it reads its library - it is setup material, not a runtime feature - and everything it copied already lives in the project and stays where it was written. An incomplete installation does not become valid by deleting its wizard.

## Library Format

Everything the wizard copies from is one-time installer source, in one of four shapes:

| Path | Purpose |
|---|---|
| `_admin/install/library/base/` | always-applied routes, the scaffold templates, texts, the stylesheet of the site and the favicons |
| `_admin/install/library/frame/` | the header and the footer of the starter site and the stylesheet they are written against, written with the starter pages |
| `_nino/Nino/Modules/<Module>/install/`, `app/…/<Module>/install/` | a module's own unit, beside the class it activates - applied on every run for Nino's own (`ALWAYS_MODULES`), a choice for a project's |
| `_admin/install/library/modules/<key>/` | a selectable unit without a runtime class of its own |
| `_admin/install/library/pages/<key>/` | one concrete page; the units that claim a `'preset'` position are the starter pages |

Everything below `_admin/install/` is removed together with the wizard after completion; a module's `install/` directory stays with its module, and only the wizard reads it. The library is setup material, not a runtime plugin system.

Module units are found, not listed: the wizard scans `_nino/Nino/Modules/*/install/` - Nino's own optional modules - and then the whole application directory (`app/`, or `NINO_APP_DIR`) up to four levels deep, plus `_admin/install/library/modules/`. A unit's key - what the picker posts and what `requiresModules` names - is the manifest's `key` or, without one, the module directory's lowercased name; it must be a slug and unique, and the first unit to claim a key keeps it, so Nino's own modules keep theirs.

The manifest keys of a unit - what each does - are listed in the [installer package recipe](recipes/installer-package.md), which is also the step-by-step for writing one. The two keys that carry content, `elements` and `navs`, are applied add-only by the same `applyUnit()` that a feature's activation uses; see [Legal](development.md#legal) for `\Nino\Elements::seed()`.

Not scanned: `features/`. A feature carries an `install/` unit of the same shape, but `\Nino\Features::activate()` applies it when the feature is switched on - in the workbench, or by the wizard's Application step - through the same `applyUnit()` the wizard uses for a module, with overwrite on here and add-only there, so that the unit application survives the removal of `_admin/install/`. See [the install unit of a feature](features.md#the-install-unit).

`Maintenance` ships with the kernel and has no unit to pick: `\Nino\Install\Setup` lists it in `/nino/modules` on every run, so its panel is in the workbench from the first `config.php` on.

## What the Wizard Deliberately Does Not Do

The wizard creates the first working project state, but it does not replace project-specific development. Templates, content models, callbacks, integrations, detailed design, and production configuration remain part of the implementation.

It also does not create `private/` or `public/`, nor any of the project directories inside them, before setup begins. These directories and their initial content are generated from the selected installation library during the process.
