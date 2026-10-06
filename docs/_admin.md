# `/_admin` — The Workbench

**Language:** English · [Deutsch](_admin.de.md)

**Last updated:** September 22, 2026 · **Nino version:** 1.3.1

This manual explains the one management interface of a Nino project: `/_admin`, the workbench. Developers set the project up, build its structure and appearance here; editors maintain its content here. What an account sees is what its permissions allow. The wizard that turns a fresh checkout into a project is the workbench's first-run mode and has its own reference, the [Setup Wizard](setup.md); so does the [Template Builder](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md), which is a feature from the catalogue rather than part of Nino.

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Recipes](recipes/README.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Features](features.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Security Note:** Every panel writes directly to configuration and project files. A developer account can change routing, data models, templates and the visible website; an editor account can change content. Work from a current Git state or another reliable backup, use HTTPS only, and give every account exactly the role it needs.

## Purpose and Scope

One login, one navigation, every screen a panel. The panels are grouped by what they change:

| Group | Panels | Who |
|---|---|---|
| **Content** | Dashboard, Elements (Element Types), Text (Text Keys), Images (Image Slots), Submissions, Log | editors and developers |
| **Structure** | Routes, Navigations | developers |
| **Features** | whatever the active features bring | whoever holds the feature panel's own permission |
| **System** | Users (User roles, Login protection), Language (Translations), Backups, Config, Features, Maintenance | developers – and every account for its own profile under Users |

A screen in brackets is a **tab** of the panel before it: the Elements panel opens on the entries and carries Element Types as its second tab, so the shape of the content sits right beside the content. A tab is a screen of its own – with its own permission, so an editor sees Elements without Element Types, and its own deep link, `#types`.

Every panel but the Dashboard opens with the same head: its name at the top left, the tabs beside the name where it has any, and at the right end of the row the buttons a panel keeps over its screen. The Dashboard is the tiles alone.

Submissions, Navigations and Maintenance belong to optional kernel modules and are present while their module is active, switched on or off in `/nino/modules`. A **feature** - an installable package under `features/`, copied in from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) and switched on in the Features panel - brings its panel the same way - the catalogue's Newsletter feature adds a Newsletter panel, its Forms feature a Forms panel, its Template Builder a Templates panel, and a checkout ships none of them; every one of them lands in the rail's own Features group. The panels above that are in neither list are the workbench's own: `_admin` holds the shell, and every screen in it is a module under `_admin/Nino/Modules/<Name>/`, brought and taken away one directory at a time. A module a project adds, or a feature it installs, can bring a panel of its own the same way; see the [Developer Manual](development.md#panels-of-the-workbench) and [Features](features.md).

There is no second tool. `/_editor`, `/_install`, `/_design` and `/_templates` of earlier versions are gone: what survived of them is a panel here or, for the Template Builder, a feature from the catalogue, and a reserved path of theirs is an ordinary page path now.

Every panel label follows the interface language of the account. This manual names the English labels.

## First Run: the Setup Wizard

A fresh checkout has no project yet. Until the wizard's last step is completed, `/_admin` shows the wizard instead of the login: six steps from the environment check to the accounts and the recovery password. The [Setup Wizard](setup.md) reference explains every step and what it writes.

The wizard lives in `_admin/install/`. Once it has locked itself out, that directory can be removed from a production delivery: nothing outside it reads its library, and everything it copied stays where it wrote it.

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
| Log | `/_admin/logs/view` |
| Templates (the Template Builder feature) | `/_admin/templates/manage` |
| Routes | `/_admin/routes/manage` |
| Navigations | `/_admin/navs/manage` |
| Users (own profile) | none – every account |
| Users (other accounts), User roles (tab of Users) | `/_admin/users/manage` |
| Login protection (tab of Users) | `/_admin/lockout/manage` |
| Language | `/_admin/language/manage` |
| Translations (tab of Language) | `/_admin/translations/manage` |
| Backups | `/_admin/backups/manage` |
| Config | `/_admin/config/manage` |
| Features | `/_admin/features/manage` |
| Maintenance | `/_admin/maintenance/manage` |

A feature's panel brings its permission along - the catalogue's Newsletter feature `/_admin/newsletter/manage`, its Forms feature `/_admin/forms/manage`, and so on - and the roles tab of the Users panel offers it while the feature is active.

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

The list of them is unbounded – it grows with every type, field and text key a project has – so the **User roles** editor does not offer it as one list: below the permission picker, three lists that depend on each other pick one - **Area** (a type, a text group), **Action** (add, change, delete, or everything in the area) and, where the action has fields, **Field** (all fields, or one). **Add permission** puts it into the picker, where it is named by its place in that tree and removed again with the same ✕ as every other permission; nothing is typed, so only what the panels list can be added. A field whose name has a space or an umlaut in it cannot be part of a permission and is not listed - it stays under its type's blanket. A role that already holds a finer permission a panel no longer lists keeps it, visible under *Not offered*. Adding the first finer permission of a panel asks first, because that panel then allows only what the role names, and so does removing the last one with its ✕, because the panel then follows the whole area again (answering No keeps it); a line under the picker names the panels a role has that for, and a summary - *This role may …* - says in words what the role does: full access, the areas it opens, what it may do inside a panel in detail, and a panel it has single permissions for but does not open.

What a role may not do, it is not offered: a field it may not change is shown read-only, "New element" and "Delete" disappear, and a text group it may not write anywhere loses its Save button. The refusal itself is server-side, so a request that goes around the screen is refused too.

A panel an account lacks the permission for is not rendered at all, and its actions answer `403` regardless; a pane shows only the tabs the account holds. A missing menu item or tab is therefore usually intentional, not a display error.

After five failed attempts, an account is locked for an hour – both numbers are the **Login protection** tab of the Users panel, which also lists the accounts locked right now and lifts a lock; the same counter runs per address, so guessing across accounts is throttled too - behind a reverse proxy that address is the proxy for everybody unless **Reverse proxies in front of this site** names it, and then one stranger's wrong guesses lock the whole site out. Accounts and roles live in `config.php`, the login throttle's counters in `private/data/auth-tries.php` – not in `config.php`, so a run of wrong guesses does not rewrite that file on every attempt.

For operation:

- use `/_admin` exclusively via HTTPS;
- create editor accounts with the **Editor** role and add a role only for a concrete need;
- keep the number of developer accounts small;
- sign out via **Logout** after work;
- additionally protect the path via web server, VPN or IP allowances if the hosting allows it.

If the accounts themselves are what is broken – the last developer password forgotten, a bad restore – the [recovery page](#recovery) is the way back in.

## The Shell

The rail on the left carries the brand, your account, the settings gear and the navigation; the pane on the right shows the selected panel. On a phone the rail is a bar across the top, and the panels are one menu in it: a select with the groups as its sections.

- **Groups.** The navigation is divided into Content, Structure, Features and System with a heading each; a group with nothing in it - Features, on a project with none switched on - carries no heading at all. An account that sees one group alone gets a plain list. A heading is a button: it folds its group and opens it again, and the browser remembers which groups are folded. The group of the open panel is always open, and on the folded rail (see *Fold*) the headings are only dividers and nothing is hidden. On a phone the select shows the headings as its sections.
- **Tabs.** A panel with several screens carries a tab bar at the top of its pane – Elements and Element Types, Users, User roles and Login protection – and comes back on the tab you left it on. Every tab is a screen of its own: its permission, its deep link (`#roles`), its state.
- **Fold.** The small chevron beside the brand folds the rail to a column of icons. A panel that needs the whole width – the Template Builder – folds it on its own and takes the reading-width ceiling off the pane; open it again by hand and it stays open, on every panel, until you fold it again. The choice is kept in the browser, not on the server.
- **Deep links.** The address bar follows you: `#elements/team/ada` is the element you are editing, `#types` the Element Types tab. A reload or a bookmark opens exactly that state, and the browser's Back and Forward buttons walk through it: every move you make - a panel from the rail, a tab, a row of a list, a back link, the previous and next buttons - is a step of its own, and what only keeps the address true to the screen (a new element's address after saving it, the arrow keys of a tab bar) is not. The entries in the rail are real links: Ctrl-click or a middle click opens a panel in a new tab, and a copied link leads to it. A deep link survives the login - open `#elements/team/ada` while signed out, sign in, and the element opens - and a change of the interface language.
- **Settings gear.** Interface language and light or dark colour scheme. The language also selects the content locale the Text and Elements forms open with.
- **Switching panels** never resets a panel: the Template Builder keeps its unsaved document, an element form its unsaved values, until you save or leave the page. Leaving one of the workbench's own forms that holds unsaved changes asks first - **Save**, **Discard** or **Cancel** - wherever the way out leads: a back link, the previous and next buttons, a copy of an element, a log out, the interface language, switching a feature on or off or installing one, restoring a backup, ending your own sessions, a change that redraws the form, or the browser's Back and Forward buttons. The browser asks as well when the tab is closed or reloaded, and a form with unsaved input shows *Unsaved changes* at its foot. Cancel stays where you are; a Save that fails brings the form with its errors back on screen. The Templates panel asks with its own OK/Cancel question when it is left, and its unsaved document is not part of the questions about logging out, the interface language or switching a feature on or off.

- **The status line.** The Elements form and the three Users forms (a new account, an account, a role) say what became of the last save at their foot - *Saving …*, *Saved at 09:41.*, *Unsaved changes* as soon as you type again, or why it failed. Typing while a save is on its way leaves *Unsaved changes* behind it, not *Saved*. A refusal names the field it is about and marks it; the next thing you type into the field takes the mark off. The error is said in the interface language, with the limits and values in it (*The password needs at least 8 characters.*) - only a failure Nino has no sentence for shows the server's own message, with its status number in front. A request that never reached the server says so instead of printing a number. The other forms still print a plain *Saved.*, and below a width of about 38 rem a bottom bar hides its status line - an error there shows only after the bar is wide enough again; below that width the bar shows *Unsaved changes* in its place.
- **A session that ends under an open form.** A session ends after a long idle time or a log out in another tab. The page then asks for a login again, in a dialog over everything you have typed - nothing is lost. Once you are in, what you were doing is sent again. *Close* gives the page back without a login: the requests that waited report their failure in their panel and the forms can be used again, so that what you typed can be copied out before you reload. If another account has logged in in another tab of the same browser in the meantime, the dialog only offers to reload: a form filled in for one account must not be saved by another. If the same account has logged in again in another tab of this browser in the meantime, which replaces the page's token, the page mends itself the same way, without asking for anything.

Whatever panel is open, saving writes the project files immediately. There is no draft state and no separate publish step; check the frontend and every affected language afterwards.

## Content

### Dashboard

The **Dashboard** is the first panel and summarizes what the account may see: a tile per panel that has something to count – elements by type, submissions, users, element types, routes, text keys and image slots still missing, active features, and whatever a feature's panel counts, subscribers for the catalogue's Newsletter – plus the date of the latest backup, the most recent log entries and, while the site is switched off, that maintenance is on. Every tile leads to the panel it counts for when clicked; the dashboard itself changes nothing.

### Elements

Elements are recurring structured content – team members, services, references – whose fields a developer defines on the **Element Types** tab of this panel. Editors and developers maintain the entries in the same panel; the tab is the developer's.

1. Choose a type. Its entries are a table: one column per field a cell can show (not images, lists or rich text), the uri first, the cells in the translation the workbench is set to – empty where an entry has none yet. Search, sort by a column, page.
2. Open an entry (its row) or select **New element**. A type that numbers its elements states the uri it is about to create; every other type asks for a slug of lowercase letters, digits, hyphens and underscores.
3. Fill the global fields once and the translated fields per language – the language switch is inside the form, and unsaved values survive the switch.
4. **Save**. A required field carries an asterisk. A save that finds one empty stops, marks every such field with a sentence under it, moves the focus to the first and names the languages with open fields in the language switch (*de_DE – 2 open*); nothing is sent until they are filled. A list of texts is edited as rows – type into a row, move it, remove it, add one – and any other list or object field is entered as JSON, where text that is not valid JSON stops the save the same way and stays in the field. Only the languages edited since the form was opened are written and checked, and, if none was, the one on screen. An image field becomes available only after a new element has been saved once; Nino then processes the upload to the dimensions the type declares.

A field that references other elements is a select or, where the type allows several, an ordered list with a search field, move buttons and a maximum the type may set. A referenced element that has been deleted is shown as *missing* rather than dropped.

**Raw storage**, at the foot of the form, shows the buckets the entry is stored in: `*` for the global fields and one per language. It is read-only and meant for diagnosis and migrations.

**‹ Previous element** and **Next element ›**, at the right of the form's context bar, step through the type's entries in the order of the list without returning to it. Like the back link, they ask first when the form holds unsaved changes.

**Duplicate** takes every value of the open entry into a new element – all languages, all fields, except the uri and the images, which belong to the entry they were uploaded for. Nothing is written yet: give the copy a uri and save it.

An image field uploads on its own, as soon as the file is chosen, and says when the picture is smaller than the field's target size and was scaled up. **Remove image** takes it out of the saved element, immediately: the field is emptied and the file this entry's own upload wrote is deleted. A file name that was written by hand stays on disk.

**Delete** removes the entry in every language, and the images only its image fields used. Only a backup brings it back.

### Text

**Text** holds the individual textfills of the site – headings, descriptions, contact details, labels – grouped by the first segment of their key: `/home/intro/title` sits in the `home` group. Open a group, edit the global values and the translated ones in the selected language, and **Save**. Formatted fields offer bold, italic, highlight, inline code and links; character counters show the length the developer intended. **Ctrl** or **Cmd** with **B** and **I** make bold and italic, as the two buttons do; **U** does nothing, because underline is not one of the formats.

What a field holds beyond that is its **format**, which the developer sets on the **Text Keys** tab (for an element field, in the type): *Formatted* is one line with the tags above, *Line breaks* adds Enter as a line break, and *Paragraphs and lists* adds paragraphs and bulleted and numbered lists. There Enter starts a new paragraph or list item and **Shift+Enter** is a line break inside it; **Enter** in an empty item ends the list; **Backspace** at the start of a paragraph or item joins it with the one before. Pasted and dropped text is always plain text: in the two wider formats a line break becomes a break, and in *Paragraphs and lists* a blank line starts a new paragraph. The editor does its own splitting, joining and list changes, so **Ctrl+Z** undoes typing but not those steps – check a larger change before saving.

A key that does not appear here is either hidden from editing or technical. Creating, renaming and deleting keys is the **Text Keys** tab's job; the project-wide translation hand-off is the **Translations** tab of the Language panel.

### Images

**Images** lists the image slots the developer defined on the **Image Slots** tab, grouped by uri area, with label, shortcode and target dimensions. Choose a file for a slot and start the upload; Nino validates and processes it, rejects an invalid or oversized file, and replaces the current image immediately.

Every upload control says what the server will take, before you choose: *Up to 2 MB and 20 megapixels.* The size is the smallest of three limits - Nino's own 8 MB, PHP's `upload_max_filesize` and PHP's `post_max_size` (0 there means none) - and the pixels are Nino's 20 megapixels: decoding a picture needs about four bytes per pixel, which is what a shared host's memory allows. A file above the limit is refused in the browser, with the reason, before it is sent; a file the browser cannot judge (a format it cannot decode) is left to the server, which names the reason too - larger than 8 MB, more than 20 megapixels, not a JPEG, PNG, WebP or GIF, or larger than PHP lets through (*PHP upload limit: 2 MB*). The last one is a setting of the server, not of Nino: raise `upload_max_filesize` and `post_max_size` in `php.ini` or ask the host.

A photograph keeps the way up its camera recorded: the JPEG's EXIF orientation is read from its header and applied before the picture is cut to the target, so an upright portrait is not stored on its side. Pictures uploaded before that stay as they were stored and have to be uploaded again.

A picture smaller than the slot's target size is saved all the same - and the line under the control says so, with its size (*Saved – but your image is only 300 × 150 px, smaller than the target size, and was scaled up. It may look blurry.*). The size meant is the one the picture is shown at, so a photograph stored on its side is measured upright. **Remove image** takes the image out of a slot after asking: the file is deleted and the website shows no image there until a new one is uploaded; the slot itself stays. The record is written first and the file deleted after it, and only a file the slot owns goes - a file name that was written into `config.php` by hand (a picture a template includes literally) is cleared from the slot and stays on disk.

Under each slot stands where it is used - *Used on: Home (/)* - read from the templates the site's pages render, `[template]` includes followed. A slot no page shows says so (*Not included anywhere*): an image uploaded there does not appear on the website. Below that, one **Alt text** input per language: it describes what the image shows for people who cannot see it, and `[image]` writes it as the `alt` attribute in the language of the page. Empty means decorative (`alt=""`), unless the template gives an alt text of its own. The order is the stored text of the language, then the template's `alt="…"`, then none; the slot's label is never used as one. An alt text is stored with the slot in `config.php`, not in a `text/<locale>.php` file, so it is not part of a Translations export or import.

The site's logo is such a slot too, `/logo`: the header, the navigation, the Open Graph and Twitter tags and the Form module's mails show whatever is uploaded there, and nothing - no broken image, no empty tag - until something is. An upload is cut to the slot's target shape (500 × 100 to begin with), so a logo of another shape needs the slot's size changed on the **Image Slots** tab first.

### Submissions

**Submissions** lists the stored entries of every form while the Form module is active, most recent first. It knows no field names of its own: a card shows the date, which form the inquiry came from, the address to answer at as a mailto link, and every value the entry carries under the label it was collected under - so a project that defines forms of its own (see [Forms](development.md#forms)) sees their fields here without configuring anything. A value whose field the form has since lost still shows, under its own name: a form that dropped a field must not take the answers with it.

A select narrows to one form and a search box searches the values and their labels, both above the list; the export writes what the two of them left, so an export taken while one form is selected is that form's. A card expands to its full text, and once open offers **Delete** for that one submission - the request a person makes about their own inquiry, and the panel's only write. An entry recorded before submissions carried an id offers none, because there is nothing to address it by that survives a deletion. Selecting an address opens your mail client; Nino does not reply on its own.

How long entries stay and whether they are written at all is `/nino/form/retention` and `/nino/form/store` in `config.php` - the catalogue's Forms feature offers both in its own panel.

### Newsletter

**Newsletter** belongs to the Newsletter feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) and is there while that feature is copied into `features/` and switched on in the Features panel. The feature's own README documents the panel: the subscription list, its exports, and the deletion an older backup cannot undo.

### Log

**Log** shows the activity log: logins and every successful change, with the account that made it. Entries are kept for 14 days and are read-only. This is not the PHP error log, which **Config** switches.

## Structure

### Templates

**Templates** belongs to the Template Builder feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) and is there while that feature is copied into `features/` and switched on in the Features panel. It is a workspace panel: the rail folds, and its three columns sit side by side. The feature's own [manual](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md) documents the panel - what it composes, its source safety rules and the preset library's manifest contract.

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
| `array` | a list of texts, edited as rows; any other list or structured value is entered as JSON, and invalid JSON blocks the save at the field |
| `date` | a date |
| `datetime` | date and time |
| `image` | an image with fixed target dimensions |
| `element` | a reference to an element of another type |

Depending on the type, a field is *per translation* or global, required or optional, rich text – with **Paragraphs and lists** if its text is more than a line, or, for a plain text field, **Keep line breaks** so that the line breaks of its text reach the page as `<br>` – limited to fixed values, given dimensions, a unit or suffix, or a number of rows its input opens with. An `image` field may name the **Alt text field**: a plain `string` field of the same type that is written per translation. Both forms then say so, the image's alt text is written per language there, and `alt="[[<field>]]"` in the template is empty - a decorative picture - for an element that has no text yet. A link to a global field, a rich-text field, the image itself or a field that is not there is dropped when the type is saved. An `element` field names the type it references and may hold several elements, ordered, with **Max. elements** as its ceiling (`0` for none); the kernel enforces that ceiling on save. A deleted target stays as *missing* rather than being dropped, and references are not part of a Translations export.

Switching a field between global and per translation migrates the existing values; check the result in every language. Saving a type does not delete existing entries, but a removed field disappears from the form.

**Renaming a field** is a change of its name in the field's row: the row says that the stored values move to the new name when the type is saved, and the save asks first. Every value of every entry moves – the global ones, every language, also one the project no longer offers, and the defaults a new element starts from – so a rename is not a removal and a new field. Several renames in one save are read against the type as it was, so two fields can swap names. A rename is refused, naming the field, when two fields would become one, when the new name is still held by a field that is not renamed, when it still holds the values of a field removed earlier (they would turn up in entries that never had them), and for an `image` field that would take over the name of another one – an upload's file is named after its field, and the pictures would overwrite each other. A picture itself moves with its field: its value is the file name, and the file keeps it - so do not give a new image field the name an image field had before it was renamed, because its uploads would be named the same and overwrite the old file. An image's **Alt text field** follows the rename of the field it names. What a rename does not change, it reports after saving: templates that still fill `[[<old name>]]` (they would show it as it stands), roles granted `/_admin/elements/<type>/update/<old name>`, and a label text kept for the field. Change those by hand.

**Duplicate type**, above the deletion, creates a new type under a new uri and an optional title from the form as it stands – its fields and whether it numbers its elements, saved or not – exactly as if you had entered them in a new type, but with no elements. What is not in the form stays with the original: a default or a callback written into the type file by hand, and the values a new element starts from. A type that numbers its elements starts again at `00001`. The form asks about unsaved input first and opens the copy when it is made. An element field that points at the original keeps pointing at it, and a role whose rights are limited to the original type has none on the copy.

**Number the elements of this type**, in the type's *Element URIs* group, replaces the slug with a counter for entries that have no name worth putting in a url – a gallery image, a price row: `/gallery/00001`, `/gallery/00002`. Numbers are never reused, switching it on later is safe, and the counter lives in the type file under the same lock as the element.

**Delete element type**, at the foot of the type form, removes the type file, every element in it and the images those elements hold. It is the one control in the panel that destroys content, so it is reached only by typing the type's own uri into the field next to the button – a single click cannot get there. A type another type's `element` field points at is refused outright, naming the field, because deleting it would leave that reference pointing at nothing. There is no undo; the way back is a backup.

### Routes

**Routes** manages the page routes – the ones the wizard created, the ones added here, and the ones written by hand into `config.php`; they are one list, derived from `/nino/http/routes` and the `/webpage<uri>/*` text keys on every request.

A page has two uris: the **Element URI** is its stable identity, the anchor of its page texts like `/webpage<uri>/title`, and saving it also writes `/webpage<uri>/uri`, the reachable path, so a template links with `[[/webpage/site-contact/uri]]` instead of repeating a path; the **HTTP URI** is that reachable path. A page can be `/about` inside and `/ueber-uns` in the browser.

A new page starts from an empty form: no URIs, no texts. Only the template is proposed - `page-blank` when `templates/` has it, and nothing otherwise, so that a template has to be chosen; never another page template, which would publish a copy of a finished page under the new path. A page needs both URIs, a template, and a **name** and **title** in every active language; the description is optional and stays empty if left so. The form checks this itself and marks what is missing, and the server refuses a save without it and writes nothing - a missing key would show as the raw `[[/webpage<uri>/name]]` on the page and in the menus. The wizard's own Webpages step is unchanged.

Besides these a page has an HTTP status code and one checkbox per navigation registered in `/nino/html/navs`. Membership is stored on the route as `'navs' => [ 'main' => 1, ... ]`, the value a priority; a membership added here starts behind everything already in the menu and a priority tuned by hand is never reset. The arrows swap two page routes in `config.php`.

The list shows each page by its name - in the content language last chosen in Elements or Text (the native one before that), else in the first language that has one - over its path, and **↗** opens the page in a new tab.

`/_admin` is reserved and cannot be a public page. A route that selects its template at runtime shows its existing body and keeps it. Deleting a page removes its route and asks first. The question names what stays: the page's texts - `/webpage<uri>/name|title|description` in the languages that hold a value, and `/webpage<uri>/uri` - and its template file, with how many other routes use it. For a route that picks its template at runtime it names the body instead.

### Navigations

**Navigations** is the other half of what Routes edits: one menu at a time, in its running order. It belongs to the Navigation module.

Opening a menu shows its entries as they render, with ↑ / ↓ to move one, × to take it out (the route stays), and a picker that adds any `GET` route at the end. The picker starts on an empty choice and **Add** waits for a route. These change a working copy in the browser and write nothing: **Save** writes the whole running order in one request, under the lock on `config.php`, and the status line says that there are unsaved changes until then. A route that is left out loses its membership; priorities are kept dense, `1..n` per menu. The back link - like logout and the interface language - asks before dropping unsaved changes, and showing the panel again reads the routes again but leaves a changed copy alone. A route without a `/webpage<uri>/name` is marked, because `[navigation]` skips it rather than rendering an empty link.

A route that exists only at runtime - a feature's `/blog`, which has no entry in `config.php` - can be picked as well. Its membership is stored under `/nino/html/navroutes`, e.g. `'GET://blog' => [ 'main' => 3 ]`, and `[navigation]` reads it for a route that is live: a feature that is switched off takes its menu entry with it, and the next save of that menu drops what was stored. Of the runtime routes, wildcard routes (`/blog/*`) and the workbench are not offered; technical routes - `robots.txt`, `/.search` - are.

**Creating** a menu registers its id; **renaming** checks the id is free in the registry and on every route, then follows it into both; **deleting** removes it everywhere. Neither touches the `[navigation nav="…"]` argument in your templates, which is content – update it yourself.

### Text Keys

**Text Keys**, a tab of the Text panel, is its technical side: every key of every group, with

- global or per-language storage, switchable with migration of the existing values;
- **new keys** and **renaming**, again with migration;
- hiding a key from the Text panel;
- its **format** and **limit**;
- deleting a key from every language – check its use in templates, mails and modules first.

**Format** says what the value may hold, and with it which editor the Text panel offers: *Plain text* (a textarea), *Formatted* (bold, italic, highlight, code, links), *Line breaks* (the same, with Enter as a break) and *Paragraphs and lists*. *Automatic* – the default – reads it from the stored values, so a key that holds a `<br>`, like the closing of a mail, is edited as line breaks and no longer loses it on the next save. **Limit** is the number of characters the counter counts to; empty means automatic, a little above the longest text the key holds. Both are applied with **Apply**, which saves them together with the two checkboxes of the key and then comes back to the category. They are kept in `text/meta.php`, one entry per key, and move with the key when it is renamed. A format that holds less than the one before – paragraphs to plain text, line breaks to formatted – converts every stored text of the key, in every language, and asks first, naming what becomes of the text; line breaks and the ends of paragraphs and items stay as lines in plain text, and become a space in *Formatted*. Widening converts the other way: in plain text every newline becomes a break. A limit below the longest text the key holds is refused. Changes in the open category that are not saved yet are asked about before the page reloads. Keep in mind where a key is used: a break in a value is a `<br>` wherever the fill stands, so a key in *Line breaks* does not belong in an attribute or a `[json …]` shortcode that expects plain text. Wrap a key in *Paragraphs and lists* in an element with the class `nino-richtext` to restore the paragraph gaps and list markers the stylesheet's reset takes away.

The starting value of a new key – also one made by the scan – is held to the format its value shows, like any value saved from the workbench: tags that format does not have and shortcodes are removed.

**Scan templates for missing keys** finds static textfills like `[[/home/intro/title]]` in the `.tpl` files that no key answers to and offers each one three answers, so a long list can be worked through in several sittings:

- **a starting value** creates the key with that text in every language;
- **an empty field** is passed over this once – the key comes back on the next scan;
- **Ignore permanently** retires the key: it leaves the scan, the Dashboard tile and the Text panel, and is listed here as a hidden key. Unticking *hidden* on it – or deleting it – brings it back into the scan.

Dynamically composed keys are beyond a static scan.

**Save** in a category writes the global values once and every language edited since the category was opened, one language after another; the confirmation appears after the last. A failure stops there, names the key and the language, and leaves the languages not yet written marked as unsaved.

### Image Slots

**Image Slots** is a tab of the Images panel. An image slot connects a technical uri (`/home/hero`) with a label and fixed target dimensions; editors fill it under **Images**. **Scan templates for missing image slots** finds local `<img src="…">` references under `images/` without a slot. Deleting a slot deletes the image stored in it. Each row also says when no page shows the slot - *not included anywhere* - and, if a template mentions it that no route renders, which template. A slot the scan creates shows that until its template uses `[image <uri>]` instead of a literal `<img>`.

## System

### Users

Every account can change its own email address and password under **Users**; a change to your own account asks for the current password. **Log out everywhere** ends every session of the account – after a lost device or a suspected exposure. The panel sits under System, but this first tab is every account's.

An account with `/_admin/users/manage` also sees the other accounts and can:

- **create** one, with an address, a password of at least eight characters and a role;
- change its address, set a new password and give it **another role**, or none, in **one Save** – the password only on an account that holds no permission your own does not (whoever sets a password can sign in with it), and a role only when it differs from the stored one: changing just the address of an account wider than your own role stays possible, a different, wider role does not - and never your own: log out and ask another manager;
- end its sessions;
- **deactivate** it, and activate it again. A deactivated account cannot log in and its sessions are ended at once; deactivating asks first. Your own account and the last active account with full access cannot be deactivated;
- **delete** it. Your own account and the last active account with full access – its own or its role's – cannot be deleted, and the last full access cannot be handed away through a role change either. A deactivated account does not count as full access.

The list says of each account its role, whether it is deactivated, until when it is locked out and when it logged in last - or that it never did. The time of the last login is stored in the account's own record in `config.php`, beside its sessions.

**User roles**, the second tab, is where the roles come from. A role has an identifier (a slug, fixed once created), a name, a **Full access** switch that stands for all permissions at once including those of future modules, and – below it – the permissions themselves in the same picker a multi-element field uses: the ones the role holds as a short list with a ✕ each, everything else behind one search field. Every entry is named after the panel or tab it opens, in the navigation's own group order. A permission this installation holds but no panel is offering right now – a module switched off, a module deleted, a permission written by hand – is listed too, under **Not offered** and by its own string: it is in force, so it stays visible, keepable and removable rather than disappearing from the form and being dropped the next time the role is saved. The two roles the wizard wrote, Editor and Developer, are ordinary roles here. A role that accounts hold cannot be deleted; a change that would take *Manage users* away from your own account is refused, and so is one that would leave no active account with full access. Nobody can widen their own rights here. The finer permissions inside Elements and Text are added below the picker, see [Finer permissions inside a panel](#finer-permissions-inside-a-panel).

**Login protection**, the third tab, holds the throttle in front of the login: **Failed logins before lockout** (`/nino/auth/maxtries`, 1–100) and **Lockout duration** (`/nino/auth/cooldown`, 60–604800 seconds). Both used to be a group of Config and keep its validation. Below them, **Locked accounts** lists every account that is locked out right now, with the time the lock ends, and **Lift lock** lets it log in again at once; the counter of that account starts from zero. A locked address is not an account and is not listed - it still has to run out. The tab needs `/_admin/lockout/manage`.

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

A module that keeps files of its own under `data/` merges them during a restore through the `/nino/admin/restore` callback (the catalogue's Newsletter feature does). The daily backup is a safety net for editorial mistakes, not a replacement for an external backup of the whole project.

### Config

**Config** edits a deliberately limited selection of `config.php` as a form. Every value is typed and validated, and the page is written in one go.

| Group | Setting | Key | Control |
|---|---|---|---|
| Errors and diagnostics | Write errors to a log | `/nino/error/log` | switch |
| Errors and diagnostics | Show errors in the frontend | `/nino/error/display` | switch |
| Errors and diagnostics | Always set the session cookie as secure | `/nino/session/force-secure-cookie` | switch |
| Errors and diagnostics | Reverse proxies in front of this site | `/nino/http/proxies` | one address or cidr range per line |
| Workbench | Daily encrypted backup | `/nino/admin/backups` | switch |
| Workbench | Record an activity log | `/nino/admin/logs` | switch |
| Page cache | Cache rendered pages | `/nino/cache/status` | switch |
| Page cache | Lifetime of a cached page | `/nino/cache/ttl` | number, 10–2592000 seconds |
| Page cache | Never cache these | `/nino/cache/blacklist` | one uri per line |

The login throttle is the Users panel's **Login protection** tab, the languages are the **Language** panel. Routes, navigations and the asset bundles are not edited here either: the first two have their panels, the bundle order is load-bearing for the CSS cascade and stays a deliberate file edit.

**The page cache.** With **Cache rendered pages** on, `Modules\Cache` stores a finished page and serves it again without rendering. Never cached: anything but a plain `GET` with a `200`, anything with query vars, any uri under `/_` or `/.`, every request of a signed-in visitor, any page whose route has a handler of its own (a module endpoint, the catalogue's Posts pages), and anything a wildcard route answers - there the addresses are the visitor's to invent, and one page per invented address is disk a stranger decides the size of. **Never cache these** adds your own exclusions; a trailing `/*` covers a subtree. The `[csrf]` token and the `[jstext]` nonce are re-stamped per response. Any save in the workbench drops the whole cache; responses carry `X-Nino-Cache: hit` or `miss`.

**Who a visitor is.** Every per-ip rule in the site - the login cooldown, the mail send cap, a form's rate limit, the address a session is listed under - counts the address PHP is talking to. Behind a reverse proxy that address is the proxy, for every visitor alike, so all of them share one bucket. **Reverse proxies in front of this site** ends that: with the proxy's address (or CIDR range) in the list, the visitor is read from `X-Forwarded-For` instead. Only put proxies in there that you operate or pay for - the header is one any client can write, and an entry that is not a proxy is what makes a forged address believable. Empty is the safe value and the default. A line that is neither an address nor a CIDR range is refused rather than stored, because it would match nothing while the form reads as configured.

In production, `/nino/error/display` must be off.

### Maintenance

**Maintenance** is one switch: while it is on, every visitor who is not signed in to the workbench gets a 503 answer instead of the site - the page itself, and a module endpoint such as the contact form's `/.form` alike, since the site is down for both. The pane says which of the two states the site is in, the switch itself, and how many seconds to send as the `Retry-After` header so a well-behaved browser or bot waits before trying again. A signed-in account still sees the site as it is - open it in another browser, or log out, to check a change before switching maintenance back off. `/_admin` keeps working throughout, so switching it back off never depends on the switch itself.

The maintenance page wears the site's own header and footer where a project has a `templates/page-maintenance.tpl` - copied in by hand from the module's own `install/` directory, with `/maintenance/title` and `/maintenance/text` as its two texts, from then on editable in the Text panel like any other key. A project without one still gets a plain, self-contained page with the same two texts, so the switch works from the moment the module exists; nothing in the setup wizard installs the styled version on its own. The full-page cache is taken out of the loop for as long as this is on, so it neither serves an old page over the 503 nor stores the 503 itself.

### Features

**Features** sorts every feature in the `features/` directory - an installable package with a `feature.php` manifest, copied in from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features); a checkout ships none - into three tabs, **Active**, **Inactive** and **Available**, each labelled with a count - Active first, and the one a panel opens on. A filter beside the tabs narrows every tab at once by name, key, description or category, and a category select beside it narrows to one category alone; the counts follow both, so a search says which tab the match is on. The select offers only the categories the features and offers on screen actually carry - see [Categories](features.md#categories) - and the tabs and both filters stay at the top while the list scrolls. Within a tab, features are sorted by the name they are shown under. Wherever a feature sits, it shows its name, description and version, and whatever stands in the way of switching it on: a Nino version it was not written for, a missing PHP extension, a required feature that is not there. Activating a feature applies its install unit without overwriting anything the project already has, lists its class in `/nino/modules` and records its version; deactivating removes the class and nothing else, and is refused while another active feature requires it. Each feature is one row. An **active** one is a row you step into: its own screen opens with **How to use it**, the short manual the feature's manifest carries - how to place its shortcode, which attribute does what - and under it the settings its manifest declares, the update waiting for it where its directory has been replaced with a newer release, and **Deactivate**, so the list itself stays one line per feature. The manual is open when the screen opens and closes with a click on its heading; a long one scrolls inside its box rather than pushing the settings down the page, and a feature whose manifest carries none gets no box. An **inactive** one keeps its **Activate** in the row, beside a **Remove** that deletes its directory - the one step deactivating leaves out; what the feature kept stays, so putting it back finds its settings again - and an offer keeps its **Install**, since there is nothing to step into for either. Installing a feature the project does not have switches it on in the same request, so Install is one press rather than two; a feature that is there and switched off is left off, because somebody switched it off and a newer version is not them changing their mind. Installing also resolves what a feature requires: the whole set is worked out first, checked against this kernel, and placed deepest first, so one Install can bring more than one feature and the answer says which - in a dialog, because an offer that is installed is not an offer any more and the list that follows leaves its row out. Settings are stored under `/nino/features` in `config.php`, and every value is validated before any is written.

**Available** lists what the catalogue offers that is not already installed in its current version - not in the directory at all, or there in an older release. Above the tabs, a **Refresh catalogue** button reads the catalogue - `https://catalogue.getnino.dev/catalogue.json` by default; `/nino/catalogue/url` in `config.php` names another, `''` switches it off and takes the button away - and a status line says when it was last read, or that it has not been read yet; the workbench otherwise never contacts the catalogue on its own. What a read finds is kept under `data/catalogue.php` and shown from there on every later opening of the panel, so Available fills the moment it opens without a request of its own. Loaded, it offers **Install** for a feature not in the directory, **Update** for one there in an older version, and greys out, with what it asks for, one no version of which fits. An installation downloads the archive, checks it against the signed catalogue, places the directory and switches the feature on; updating an active feature places the files and applies the update in a second request the panel makes on its own, and a feature the project switched off stays off. Where `features/` is not writable, the tab links the archive to unpack by hand instead. How the catalogue is verified, and how the cache is kept and invalidated, is in [The Catalogue](features.md#the-catalogue).

A panel a feature brings appears with the next load of the workbench after activating, and goes with the next load after deactivating. The workbench does that load itself: anything that switches a feature on or off - Activate, Deactivate, and the Install that switches on what the project did not have - ends by building the page again, keeping the address on this panel, because the rail, a feature's assets and its words are all rendered before the browser gets the page. It lands in the rail's own **Features** group regardless of what its own `nav()` names, and its permission is offered on the roles tab of the Users panel under that group - it has to be granted to the Editor role there, since the wizard wrote that role before the feature existed. The manifest, the settings schema and the lifecycle are in the [Features](features.md) manual.

### Search

**Search** belongs to the Search feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features) and is there while that feature is copied into `features/` and switched on in the Features panel; its one action, **Create searchindex**, rebuilds the indexes configured under `/nino/elements/index`. The feature's own README documents the panel and the index configuration; see also [Elements Search Index](development.md#elements-search-index).

## Recovery

`/_admin/recovery.php` is the way back in when the accounts themselves are what is broken: every developer password forgotten, or a restore gone wrong. It asks for the **recovery password** set in the wizard's last step – not a login, and nothing in the workbench ever asks for it – and offers exactly two things:

- **Restore a backup**, from the list of dates, after snapshotting the current state;
- **Reset an account**: an existing address gets the new password and is logged out everywhere; an address without an account becomes one with full access.

Five wrong attempts lock it for an hour. The secret's hash lives in `private/.auth/pw.php` – outside `config.php`, so a restore cannot roll it back, and outside every tool directory, so an update cannot take it along. Nothing in the workbench writes that file except the wizard's last step, so a new secret is written by hand - it is a php stub that refuses to be served, with the hash inside it:

```bash
php -r 'echo "<?php http_response_code(403); exit; return \x27", password_hash( $argv[1], PASSWORD_DEFAULT ), "\x27;\n";' -- '<password>' > private/.auth/pw.php
```

Do this in a protected local environment only – a password on a command line may be visible in the shell history or the process list.

## Recommended Workflow

1. Run the wizard, then delete `_admin/install/` from the production delivery.
2. Build the structure under **Element Types** (a tab of Elements), **Text Keys** (Text), **Image Slots** (Images), **Routes** and **Navigations**.
3. Compose the pages under **Templates**, adjust `assets/theme.css` where the delivered look is not the one you want, and check the result in the browser.
4. Fill the content under **Elements**, **Text** and **Images**; hand a language over under **Language › Translations**.
5. Check the Dashboard and the two scans for missing definitions.
6. Create the editor accounts under **Users** with the Editor role – add a role on the **User roles** tab where the two are not enough – and test what they see.
7. Check the frontend, every language, the forms and the responsive layout.
8. Commit the project files.

## If Something Does Not Work

| Problem | Check |
|---|---|
| Login locked after several attempts | Wait out the lockout duration (an hour by default), or lift an account's lock under **Users › Login protection**; an address lock is not listed there and still has to expire; the lock is per account and per address. Behind a reverse proxy, set **Config › Reverse proxies in front of this site**, or every visitor shares one address and one lock. |
| A panel or a tab is missing | The account lacks its permission, or its module is not active. |
| Saving fails | The message says why where Nino has a sentence for it (a value out of range, an address already in use, a file over PHP's upload limit). Otherwise: write permissions of the affected file or directory. |
| A login dialog appears over a form | The session ended; log in again and the request is sent on. *Another account is logged in in another tab* means exactly that: reload the page. |
| Template missing in **Routes** | Only existing `templates/page-*.tpl` files are offered. |
| A page cannot be saved in **Templates** | Reload after an external edit, check unique section ids and unmatched `<section>` tags; see the Template Builder's [manual](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md). |
| Texts or images missing in a scan | Dynamic keys and images are not statically recognizable. |
| Backup list is empty | Backups are switched off, or no authenticated request has happened today. |
| Search returns no elements | The catalogue's Search feature in `features/` and switched on in the Features panel, `/nino/elements/index` in `config.php`, then **Create searchindex**. |
| Website broken after **Config** | Restore the last Git state or backup. |
| No developer password works any more | `/_admin/recovery.php` with the recovery password. |

## Next Steps

- [Setup Wizard](setup.md) documents the six first-run steps and the library format.
- The **Template Builder** - page templates composed from whole sections - is a feature from the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features); its [manual](https://github.com/dapeio/nino-features/blob/main/features/Templates/docs/templates.md) is there too.
- [Developer Manual](development.md) describes APIs, modules, panels and direct work on project files.
- [Deployment](deployment.md) covers web server, security, backups and go-live.
