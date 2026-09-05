# `/_admin` — The Workbench

**Language:** English · [Deutsch](_admin.de.md)

**Last updated:** September 5, 2026 · **Nino version:** 0.13.0-beta

This manual explains the one management interface of a Nino project: `/_admin`, the workbench. Developers set the project up, build its structure and appearance here; editors maintain its content here. What an account sees is what its permissions allow. The wizard that turns a fresh checkout into a project is the workbench's first-run mode and has its own reference, the [Setup Wizard](setup.md); the two large developer panels have theirs as well: [Templates](templates.md) and [Design](appearance.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Templates Panel](templates.md) · [Design Panel](appearance.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Security Note:** Every panel writes directly to configuration and project files. A developer account can change routing, data models, templates and the visible website; an editor account can change content. Work from a current Git state or another reliable backup, use HTTPS only, and give every account exactly the role it needs.

## Purpose and Scope

One login, one navigation, every screen a panel. The panels are grouped by what they change:

| Group | Panels | Who |
|---|---|---|
| **Content** | Dashboard, Elements (Element Types), Text (Text Keys), Images (Image Slots), Submissions, Newsletter, Log | editors and developers |
| **Structure** | Templates, Design, Routes, Navigations | developers |
| **System** | Users (User roles, Login protection), Language (Translations), Backups, Config, Search | developers – and every account for its own profile under Users |

A screen in brackets is a **tab** of the panel before it: the Elements panel opens on the entries and carries Element Types as its second tab, so the shape of the content sits right beside the content. A tab is a screen of its own – with its own permission, so an editor sees Elements without Element Types, and its own deep link, `#types`.

Submissions, Newsletter, Navigations and Search belong to optional modules and are present while their module is active. Templates and Design are modules too: they ship with every checkout, and a project that does not want them deletes their directory. So are the panels above that are not in that list - `_admin` holds the shell, and every screen in it is a module under `_admin/Nino/Modules/<Name>/`, brought and taken away one directory at a time. A module a project adds can bring a panel of its own the same way; see the [Developer Manual](development.md#panels-of-the-workbench).

There is no second tool. `/_editor`, `/_install`, `/_design` and `/_templates` of earlier versions are all here, and a reserved path of theirs is an ordinary page path now.

Every panel label follows the interface language of the account. This manual names the English labels.

## First Run: the Setup Wizard

A fresh checkout has no project yet. Until the wizard's last step is completed, `/_admin` shows the wizard instead of the login: ten steps from the environment check to the accounts and the recovery password. The [Setup Wizard](setup.md) reference explains every step and what it writes.

The wizard lives in `_admin/install/`. Once it has locked itself out, that directory can be removed from a production delivery; the Design panel then loses its Theme, Header and Footer catalogue and says so, and nothing else changes. Keep it deployed if those three are to stay switchable.

## Login, Accounts and Roles

Open `https://your-domain.example/_admin` and sign in with the email address and password of your account. The first account is created by the wizard's **Accounts** step; every further one in the **Users** panel.

There are no separate passwords for developer and editor work any more. An account holds a **role**, and a role is a named set of permissions, kept in `config.php` under `/nino/auth/roles`. The wizard writes two; the **User roles** tab of the Users panel changes them or adds more:

| Role | Permissions | Sees |
|---|---|---|
| **Editor** | every Content panel's permission, for the panels that exist when the wizard runs | the Content group, and its own profile under System |
| **Developer** | `/*` | everything |

A permission is one string per panel or tab; `/*` matches every path below it, so `/_admin/*` would also open every panel, and `/*` opens every panel of every future module as well.

| Panel | Permission |
|---|---|
| Dashboard | none – every account |
| Elements | `/_admin/elements/manage` |
| Element Types (tab of Elements) | `/_admin/types/manage` |
| Text | `/_admin/text/manage` |
| Text Keys (tab of Text) | `/_admin/keys/manage` |
| Images | `/_admin/images/manage` |
| Image Slots (tab of Images) | `/_admin/slots/manage` |
| Submissions | `/_admin/submissions/view` |
| Newsletter | `/_admin/newsletter/manage` |
| Log | `/_admin/logs/view` |
| Templates | `/_admin/templates/manage` |
| Design | `/_admin/design/manage` |
| Routes | `/_admin/routes/manage` |
| Navigations | `/_admin/navs/manage` |
| Users (own profile) | none – every account |
| Users (other accounts), User roles (tab of Users) | `/_admin/users/manage` |
| Login protection (tab of Users) | `/_admin/lockout/manage` |
| Language | `/_admin/language/manage` |
| Translations (tab of Language) | `/_admin/translations/manage` |
| Backups | `/_admin/backups/manage` |
| Config | `/_admin/config/manage` |
| Search | `/_admin/search/manage` |

### Finer permissions inside a panel

The permissions above are doors: they say which panels an account may open. Inside Elements and Text, a role can also be described action by action and field by field – for editors who may change texts but not add entries, or who own one type and see the rest.

| What | Permission |
|---|---|
| Add an element of a type | `/_admin/elements/services/insert` |
| Change one field of it | `/_admin/elements/services/update/title` |
| Every field of it | `/_admin/elements/services/update/*` |
| Delete one | `/_admin/elements/services/delete` |
| Everything on that one type | `/_admin/elements/services/*` |
| Change one text key | `/_admin/text/update/page-home/atf/title` |
| Every key of a group | `/_admin/text/update/page-home/*` |

These are ordinary permission strings, matched by the same `/*` rule as everything else, so a role is described as coarsely or as finely as it needs to be. The panel permission is still required: `/_admin/elements/manage` is what makes the panel appear at all, and the finer ones say what may be done in it.

**They are opt-in.** A role holding none of them keeps exactly what its panel permission has always meant – every action on every type and key. Giving a role its first finer permission for a panel is what says "describe this one in detail"; from then on that panel allows what the role names and nothing else. Existing roles are therefore unaffected until you change them.

The list of them is unbounded – it grows with every type, field and text key a project has – so the **User roles** editor does not offer it as a list: below the permission picker there is a field to type one into. It is checked for shape, added to the picker, and removed again with the same ✕ as every other permission.

What a role may not do, it is not offered: a field it may not change is shown read-only, "New element" and "Delete" disappear, and a text group it may not write anywhere loses its Save button. The refusal itself is server-side, so a request that goes around the screen is refused too.

A panel an account lacks the permission for is not rendered at all, and its actions answer `403` regardless; a pane shows only the tabs the account holds. A missing menu item or tab is therefore usually intentional, not a display error.

After five failed attempts, an account is locked for an hour – both numbers are the **Login protection** tab of the Users panel; the same counter runs per address, so guessing across accounts is throttled too. Accounts and roles live in `config.php`, the login throttle's counters under `private/.auth/`.

For operation:

- use `/_admin` exclusively via HTTPS;
- create editor accounts with the **Editor** role and add a role only for a concrete need;
- keep the number of developer accounts small;
- sign out via **Logout** after work;
- additionally protect the path via web server, VPN or IP allowances if the hosting allows it.

If the accounts themselves are what is broken – the last developer password forgotten, a bad restore – the [recovery page](#recovery) is the way back in.

## The Shell

The rail on the left carries the brand, your account, the settings gear and the navigation; the pane on the right shows the selected panel. On a phone the rail is a bar across the top and the panels are tabs.

- **Groups.** The navigation is divided into Content, Structure and System with a heading each. An account that sees one group alone gets a plain list.
- **Tabs.** A panel with several screens carries a tab bar at the top of its pane – Elements and Element Types, Users, User roles and Login protection – and comes back on the tab you left it on. Every tab is a screen of its own: its permission, its deep link (`#roles`), its state.
- **Fold.** The small chevron beside the brand folds the rail to a column of icons. A panel that needs the whole width – the Template Builder – folds it on its own and takes the reading-width ceiling off the pane; open it again by hand and it stays open, on every panel, until you fold it again. The choice is kept in the browser, not on the server.
- **Deep links.** The address bar follows you: `#elements/team/ada` is the element you are editing, `#design/header` the Header editor. A reload or a bookmark opens exactly that state, and the browser's back button steps through it.
- **Settings gear.** Interface language and light or dark colour scheme. The language also selects the content locale the Text and Elements forms open with.
- **Switching panels** never resets a panel: the Template Builder keeps its unsaved document, an element form its unsaved values, until you save or leave the page. Leaving with unsaved changes in the Templates or Design panel asks first.

Whatever panel is open, saving writes the project files immediately. There is no draft state and no separate publish step; check the frontend and every affected language afterwards.

## Content

### Dashboard

The **Dashboard** is the first panel and summarizes what the account may see: a tile per panel that has something to count – elements by type, submissions, newsletter subscribers, users, element types, routes, text keys and image slots still missing – plus the date of the latest backup and the most recent log entries. Every tile leads to the panel it counts for when clicked; the dashboard itself changes nothing.

### Elements

Elements are recurring structured content – team members, services, references – whose fields a developer defines on the **Element Types** tab of this panel. Editors and developers maintain the entries in the same panel; the tab is the developer's.

1. Choose a type.
2. Open an entry or select **New element**. A type that numbers its elements states the uri it is about to create; every other type asks for a slug of lowercase letters, digits, hyphens and underscores.
3. Fill the global fields once and the translated fields per language – the language switch is inside the form, and unsaved values survive the switch.
4. **Save**. An image field becomes available only after a new element has been saved once; Nino then processes the upload to the dimensions the type declares.

A field that references other elements is a select or, where the type allows several, an ordered list with a search field, move buttons and a maximum the type may set. A referenced element that has been deleted is shown as *missing* rather than dropped.

**Raw storage**, at the foot of the form, shows the buckets the entry is stored in: `*` for the global fields and one per language. It is read-only and meant for diagnosis and migrations.

**Duplicate** takes every value of the open entry into a new element – all languages, all fields, except the uri and the images, which belong to the entry they were uploaded for. Nothing is written yet: give the copy a uri and save it.

**Delete** removes the entry in every language, and the images only its image fields used. Only a backup brings it back.

### Text

**Text** holds the individual textfills of the site – headings, descriptions, contact details, labels – grouped by the first segment of their key: `/home/intro/title` sits in the `home` group. Open a group, edit the global values and the translated ones in the selected language, and **Save**. Formatted fields offer bold, italic, highlight, inline code and links; character counters show the length the developer intended.

A key that does not appear here is either hidden from editing or technical. Creating, renaming and deleting keys is the **Text Keys** tab's job; the project-wide translation hand-off is the **Translations** tab of the Language panel.

### Images

**Images** lists the image slots the developer defined on the **Image Slots** tab, grouped by uri area, with label, shortcode and target dimensions. Choose a file for a slot and start the upload; Nino validates and processes it, rejects an invalid or oversized file, and replaces the current image immediately.

### Submissions

**Submissions** lists the stored entries of the contact form while the Form module is active: date, category, sender and message, expandable, exportable as CSV. It is deliberately read-only. Selecting an address opens your mail client; Nino does not reply on its own.

### Newsletter

**Newsletter** lists the subscriptions while the Newsletter module is active. Copy every address as a BCC line, export the list as CSV, or delete a subscription after confirmation. A deleted address is also recorded as removed, so restoring an older backup cannot silently undo the unsubscribe.

### Log

**Log** shows the activity log: logins and every successful change, with the account that made it. Entries are kept for 14 days and are read-only. This is not the PHP error log, which **Config** switches.

## Structure

### Templates

The **Templates** panel is the Template Builder: it composes the project's `page-*.tpl` files from complete sections – a searchable library of section presets, reusable `[template]` sections, the page's header and footer, and a native quick fill of the text a section brings. It is a workspace panel: the rail folds, and the template list, the section canvas and the inspector sit side by side.

Everything it can do, its source safety rules and the preset library's manifest contract are in the [Templates Panel](templates.md) reference. The panel is a module (`app/Nino/Modules/Templates/`) and disappears with its directory.

### Design

The **Design** panel keeps the site's four appearance decisions editable after the wizard: **Theme** installs a complete visual baseline, **Header** and **Footer** replace one frame each, **Design** generates the colour palette and the size raster from a handful of settings and writes `assets/style.design.css`. The four are tabs inside the panel; the action button at the foot changes with the active tab.

Theme, Header and Footer read the wizard's catalogue under `_admin/install/library/` and say so when it has been removed; Design generates rather than copies and keeps working either way. The settings, the token contract and the bundle order are in the [Design Panel](appearance.md) reference. The panel is a module (`app/Nino/Modules/Design/`) and disappears with its directory.

### Element Types

Element types describe recurring content. Each type is a file under `elements/`; its entries are maintained under **Elements** – the panel this screen is a tab of, reached from the strip at the top of its pane or with `#types`.

1. Select **New type**.
2. Give it a technical uri – a lowercase letter first, then lowercase letters, digits, hyphens and underscores, e.g. `team` or `service_items`. It becomes `elements/<uri>.php` and cannot be changed afterwards.
3. Give it a title for the Elements panel.
4. Add the fields with **Add field** and save.

| Field type | Suitable for |
|---|---|
| `string` | single or multi-line text; optionally rich text or a fixed selection |
| `integer` | whole numbers |
| `double` | decimal numbers |
| `boolean` | yes/no |
| `array` | simple lists or structured values |
| `date` | a date |
| `datetime` | date and time |
| `image` | an image with fixed target dimensions |
| `element` | a reference to an element of another type |

Depending on the type, a field is *per translation* or global, required or optional, rich text, limited to fixed values, given dimensions, a unit or suffix. An `element` field names the type it references and may hold several elements, ordered, with **Max. elements** as its ceiling (`0` for none); the kernel enforces that ceiling on save. A deleted target stays as *missing* rather than being dropped, and references are not part of a Translations export.

Switching a field between global and per translation migrates the existing values; check the result in every language. Saving a type does not delete existing entries, but a removed field disappears from the form.

**Number the elements of this type**, in the type's *Element URIs* group, replaces the slug with a counter for entries that have no name worth putting in a url – a gallery image, a price row: `/gallery/00001`, `/gallery/00002`. Numbers are never reused, switching it on later is safe, and the counter lives in the type file under the same lock as the element.

**Delete element type**, at the foot of the type form, removes the type file, every element in it and the images those elements hold. It is the one control in the panel that destroys content, so it is reached only by typing the type's own uri into the field next to the button – a single click cannot get there. A type another type's `element` field points at is refused outright, naming the field, because deleting it would leave that reference pointing at nothing. There is no undo; the way back is a backup.

### Routes

**Routes** manages the page routes – the ones the wizard created, the ones added here, and the ones written by hand into `config.php`; they are one list, derived from `/nino/http/routes` and the `/webpage<uri>/*` text keys on every request.

A page has two uris: the **Element URI** is its stable identity, the anchor of its page texts like `/webpage<uri>/title`, and saving it also writes `/webpage<uri>/uri`, the reachable path, so a template links with `[[/webpage/site-contact/uri]]` instead of repeating a path; the **HTTP URI** is that reachable path. A page can be `/about` inside and `/ueber-uns` in the browser.

Creating or editing a page also sets the template from `templates/page-*.tpl`, an HTTP status code, navigation name, page title and description per active language, and one checkbox per navigation registered in `/nino/html/navs`. Membership is stored on the route as `'navs' => [ 'main' => 1, ... ]`, the value a priority; a membership added here starts behind everything already in the menu and a priority tuned by hand is never reset. The arrows swap two page routes in `config.php`.

`/_admin` is reserved and cannot be a public page. A route that selects its template at runtime shows its existing body and keeps it. Deleting a page removes its route; its texts and its template file stay.

### Navigations

**Navigations** is the other half of what Routes edits: one menu at a time, in its running order. It belongs to the Navigation module.

Opening a menu shows its entries as they render, with ↑ / ↓ to move one, × to take it out (the route stays), and a picker that adds any `GET` route at the end. A route without a `/webpage<uri>/name` is marked, because `[navigation]` skips it rather than rendering an empty link. Priorities are kept dense, `1..n` per menu.

**Creating** a menu registers its id; **renaming** checks the id is free in the registry and on every route, then follows it into both; **deleting** removes it everywhere. Neither touches the `[navigation nav="…"]` argument in your templates, which is content – update it yourself.

### Text Keys

**Text Keys**, a tab of the Text panel, is its technical side: every key of every group, with

- global or per-language storage, switchable with migration of the existing values;
- **new keys** and **renaming**, again with migration;
- hiding a key from the Text panel;
- deleting a key from every language – check its use in templates, mails and modules first.

**Scan templates for missing keys** finds static textfills like `[[/home/intro/title]]` in the `.tpl` files that no key answers to and offers each one three answers, so a long list can be worked through in several sittings:

- **a starting value** creates the key with that text in every language;
- **an empty field** is passed over this once – the key comes back on the next scan;
- **Ignore permanently** retires the key: it leaves the scan, the Dashboard tile and the Text panel, and is listed here as a hidden key. Unticking *hidden* on it – or deleting it – brings it back into the scan.

Dynamically composed keys are beyond a static scan.

### Image Slots

**Image Slots** is a tab of the Images panel. An image slot connects a technical uri (`/home/hero`) with a label and fixed target dimensions; editors fill it under **Images**. **Scan templates for missing image slots** finds local `<img src="…">` references under `images/` without a slot. Deleting a slot deletes the image stored in it.

## System

### Users

Every account can change its own email address and password under **Users**; a change to your own account asks for the current password. **Log out everywhere** ends every session of the account – after a lost device or a suspected exposure. The panel sits under System, but this first tab is every account's.

An account with `/_admin/users/manage` also sees the other accounts and can:

- **create** one, with an address, a password of at least eight characters and a role;
- change its address or password and end its sessions;
- give it **another role**, or none – never your own: log out and ask another manager;
- **delete** it. Your own account and the last account with full access – its own or its role's – cannot be deleted, and the last full access cannot be handed away through a role change either.

**User roles**, the second tab, is where the roles come from. A role has an identifier (a slug, fixed once created), a name, a **Full access** switch that stands for all permissions at once including those of future modules, and – below it – the permissions themselves in the same picker a multi-element field uses: the ones the role holds as a short list with a ✕ each, everything else behind one search field. Every entry is named after the panel or tab it opens, in the navigation's own group order. A permission this installation holds but no panel is offering right now – a module switched off, a module deleted, a permission written by hand – is listed too, under **Not offered** and by its own string: it is in force, so it stays visible, keepable and removable rather than disappearing from the form and being dropped the next time the role is saved. The two roles the wizard wrote, Editor and Developer, are ordinary roles here. A role that accounts hold cannot be deleted; a change that would take *Manage users* away from your own account is refused, and so is one that would leave no account with full access. Nobody can widen their own rights here.

**Login protection**, the third tab, holds the throttle in front of the login: **Failed logins before lockout** (`/nino/auth/maxtries`, 1–100) and **Lockout duration** (`/nino/auth/cooldown`, 60–604800 seconds). Both used to be a group of Config and keep its validation; the tab needs `/_admin/lockout/manage`.

### Language

**Language** is the two locale settings of `config.php` as one form, saved together, with the translation hand-off as its second tab.

| Setting | Key | Control |
|---|---|---|
| Languages | `/nino/locales/available` | checklist |
| Native language | `/nino/locales/native` | select |

The language list shows every locale the project knows – the ones `config.php` lists plus every `text/<locale>.php` on disk – and whether that file exists and how many keys it holds. **Adding a language** writes `text/<locale>.php` as a skeleton with empty values and does *not* switch the language on; translate it under Text or import it on the Translations tab, then tick it and save. The native language can only be one of the ticked ones, so both are saved together.

### Translations

**Translations**, the second tab of the Language panel, is the project-wide hand-off for translating a site after its native content is done. The export uses the native locale and combines the non-global, non-technical text values with the locale-scoped element fields that hold a native value; global values, technical text, images, element uris and ordering are not part of it. The JSON carries instructions for translation tools: translate values only, keep keys, types, HTML, urls, placeholders, shortcodes and identifiers.

1. Download the native package.
2. Translate its values without changing the structure.
3. Choose the target language.
4. Upload or paste the JSON and select **Import into selected language**.
5. Check the imported and skipped counters.

Import is merge-only: matching values are overwritten, values absent from the document are left alone. Every path is validated against a fresh native export; text and rich text are sanitized; unknown, global, technical and image fields are skipped. Importing into the native language is possible but overwrites source content.

### Backups

With backups switched on, the first authenticated request of a day writes an encrypted backup of everything the workbench can write – configuration, texts, elements, images, data – under `private/.backups/`, and daily backups are kept for 14 days. The archives are encrypted with AES-256-GCM; the key lives under `private/.auth/`, so the archives alone are unreadable.

**Backups** lists the available dates and restores one. Before a restore, the current state is backed up once more, so a wrong pick can itself be undone. Afterwards test at least the frontend in every language, the login and the permissions, pages, texts, elements, images, and the form and newsletter data.

A module that keeps files of its own under `data/` merges them during a restore through the `/nino/admin/restore` callback (the Newsletter module does). The daily backup is a safety net for editorial mistakes, not a replacement for an external backup of the whole project.

### Config

**Config** edits a deliberately limited selection of `config.php` as a form. Every value is typed and validated, and the page is written in one go.

| Group | Setting | Key | Control |
|---|---|---|---|
| Errors and diagnostics | Write errors to a log | `/nino/error/log` | switch |
| Errors and diagnostics | Show errors in the frontend | `/nino/error/display` | switch |
| Errors and diagnostics | Always set the session cookie as secure | `/nino/session/force-secure-cookie` | switch |
| Workbench | Daily encrypted backup | `/nino/admin/backups` | switch |
| Workbench | Record an activity log | `/nino/admin/logs` | switch |
| Page cache | Cache rendered pages | `/nino/cache/status` | switch |
| Page cache | Lifetime of a cached page | `/nino/cache/ttl` | number, 10–2592000 seconds |
| Page cache | Never cache these | `/nino/cache/blacklist` | one uri per line |

The login throttle is the Users panel's **Login protection** tab, the languages are the **Language** panel. Routes, navigations and the asset bundles are not edited here either: the first two have their panels, the bundle order is load-bearing for the CSS cascade and stays a deliberate file edit.

**The page cache.** With **Cache rendered pages** on, `Modules\Cache` stores a finished page and serves it again without rendering. Never cached: anything but a plain `GET` with a `200`, anything with query vars, any uri under `/_` or `/.`, and every request of a signed-in visitor. **Never cache these** adds your own exclusions; a trailing `/*` covers a subtree. The `[csrf]` token and the `[jstext]` nonce are re-stamped per response. Any save in the workbench drops the whole cache; responses carry `X-Nino-Cache: hit` or `miss`.

In production, `/nino/error/display` must be off.

### Search

**Search** belongs to the Search module. **Create searchindex** recreates every valid index listed under `/nino/elements/index` and reports how many index files and elements it wrote. The index definition is deliberate `config.php` work, see [Elements Search Index](development.md#elements-search-index); use the button after adding it, after changing indexed fields, or after editing element files by hand.

## Recovery

`/_admin/recovery.php` is the way back in when the accounts themselves are what is broken: every developer password forgotten, or a restore gone wrong. It asks for the **recovery password** set in the wizard's last step – not a login, and nothing in the workbench ever asks for it – and offers exactly two things:

- **Restore a backup**, from the list of dates, after snapshotting the current state;
- **Reset an account**: an existing address gets the new password and is logged out everywhere; an address without an account becomes one with full access.

Five wrong attempts lock it for an hour. The secret's hash lives in `private/.auth/pw.php` – outside `config.php`, so a restore cannot roll it back, and outside every tool directory, so an update cannot take it along. To set a new one outside the wizard:

```bash
php _admin/Admin.php <password>
```

The output is the complete file; write it to `private/.auth/pw.php`. Do this in a protected local environment only – a password on a command line may be visible in the shell history or the process list.

## Recommended Workflow

1. Run the wizard, then delete `_admin/install/` from the production delivery or keep it locked for the appearance catalogue.
2. Build the structure under **Element Types** (a tab of Elements), **Text Keys** (Text), **Image Slots** (Images), **Routes** and **Navigations**.
3. Compose the pages under **Templates**, settle the look under **Design**, and check the result in the browser.
4. Fill the content under **Elements**, **Text** and **Images**; hand a language over under **Language › Translations**.
5. Check the Dashboard and the two scans for missing definitions.
6. Create the editor accounts under **Users** with the Editor role – add a role on the **User roles** tab where the two are not enough – and test what they see.
7. Check the frontend, every language, the forms and the responsive layout.
8. Commit the project files.

## If Something Does Not Work

| Problem | Check |
|---|---|
| Login locked after several attempts | Wait out the lockout duration (an hour by default, see **Users › Login protection**); the lock is per account and per address. |
| A panel or a tab is missing | The account lacks its permission, or its module is not active. |
| Saving fails | Write permissions of the affected file or directory. |
| Template missing in **Routes** | Only existing `templates/page-*.tpl` files are offered. |
| A page cannot be saved in **Templates** | Reload after an external edit, check unique section ids and unmatched `<section>` tags; see [Templates Panel](templates.md). |
| **Design** says no variants are available | `_admin/install/library/` was removed; the Design tab keeps working. |
| Texts or images missing in a scan | Dynamic keys and images are not statically recognizable. |
| Backup list is empty | Backups are switched off, or no authenticated request has happened today. |
| Search returns no elements | `/nino/elements/index` and the Search module in `config.php`, then **Create searchindex**. |
| Website broken after **Config** | Restore the last Git state or backup. |
| No developer password works any more | `/_admin/recovery.php` with the recovery password. |

## Next Steps

- [Setup Wizard](setup.md) documents the ten first-run steps and the library format.
- [Templates Panel](templates.md) explains the Template Builder and the section preset contract.
- [Design Panel](appearance.md) explains the four appearance editors and the token contract.
- [Developer Manual](development.md) describes APIs, modules, panels and direct work on project files.
- [Deployment](deployment.md) covers web server, security, backups and go-live.
