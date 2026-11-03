# `/_admin` — The Workbench

This manual is about the one management interface of a Nino project: `/_admin`, the workbench. Developers set the project up and build its structure and appearance in it; editors maintain its content in it. What an account sees is what its permissions allow, and the screens explain themselves; what they cannot say is here - who may do what, how to get back in, and what to check when something does not work. The wizard that turns a fresh checkout into a project is the workbench's first-run mode and has its own reference, the [Setup Wizard](setup.md).

**Security Note:** Every panel writes directly to configuration and project files. A developer account can change routing, data models, templates and the visible website; an editor account can change content. Work from a current Git state or another reliable backup, use HTTPS only, and give every account exactly the role it needs.

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
| Builder (the Builder feature) | `/_admin/builder/manage` |
| Routes | `/_admin/routes/manage` |
| Navigations | `/_admin/navs/manage` |
| Users (own profile) | none – every account |
| Users (other accounts), User roles (tab of Users) | `/_admin/users/manage` |
| Login protection (tab of Users) | `/_admin/lockout/manage` |
| Recovery password (tab of Users) | `/_admin/recoverypw/manage` |
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
| Change one text key | `/_admin/text/update/template/page-home/hero/title` |
| Every key of a template | `/_admin/text/update/template/page-home/*` |
| The page details of a route | `/_admin/text/update/_nino/webpage/home/*` |

A whole page takes two of them - its template and its page details - because the details of a page, its name in the menu, its title and its description, are the system's keys under `/_nino`, not words of the template. These are ordinary permission strings, matched by the same `/*` rule as everything else, so a role is described as coarsely or as finely as it needs to be. The panel permission is still required: `/_admin/elements/manage` is what makes the panel appear at all, and the finer ones say what may be done in it.

**They are opt-in.** A role holding none of them keeps exactly what its panel permission has always meant – every action on every type and key. Giving a role its first finer permission for a panel is what says "describe this one in detail"; from then on that panel allows what the role names and nothing else. Existing roles are therefore unaffected until you change them.

The list of them is unbounded - it grows with every type, field and text key a project has - so the **User roles** tab does not offer it as one list: below the permission picker, three lists that depend on each other pick one - **Area** (a type, a text group), **Action** (add, change, delete, or everything in the area) and, where the action has fields, **Field** (all fields, or one). **Add permission** puts it into the picker; nothing is typed, so only what the panels list can be added. A field whose name has a space or an umlaut in it cannot be part of a permission and is not listed - it stays under its type's blanket. Adding the first finer permission of a panel asks first, because that panel then allows only what the role names, and so does removing the last one; a summary under the picker - *This role may …* - says in words what the role does.

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

## Two Languages

The workbench has two languages, and nothing ties them together. The **interface language** is the one the screens, the rail and the messages are written in: the picker in the rail's settings menu sets it, so does the login page's, so does `?locale=xx` on the address, and it is kept for the session. The **working language** is the one a panel edits in: the select above the screens of Elements and Text (and of the Keys tab) names it, Routes names its pages in it where they have a name in it, and it is a value of the session of its own. Changing it changes what the panels open on and nothing else - the workbench goes on speaking the interface language after a reload, and the website in the same browser keeps the language its own visitor picker chose, since the workbench never writes that one. The Design preview of the catalogue draws the site as a visitor of this browser sees it, in the language the website shows them (`\Nino\Locales::getVisitorLocale()`), and the workbench stays in its own language; a Design that does not ask for it draws in the language the request is in, the interface language.

## Languages of the Website

A language is added under **Language**: **Add a language** creates its text file, every key of the native language in it, all empty but for the language subtag (`fr` for `fr_FR`) the pages of the language say in their `lang` attribute and, where the Legal module is on, the names of its two pages; the language is not switched on. It is translated under **Text** first: the select above the texts lists every language that has a text file, and one the website does not serve yet is marked **Inactive**; the Keys tab and the Translations tab (a JSON document for a translator, imported into any language that has a file) work on it the same way, and an import never lets an empty value overwrite a text. Tick the language and save when it is ready. The select names the **working language**, so a new key made on the Keys tab starts with its value in the working language and empty in every other language of the site.

What a language does not have yet falls back to the **native language**, in the order the site lists its languages and then the other text files: a text that is missing or empty (whitespace is empty) is shown in the native language's words, on the pages of the site and in the mails alike, a post that exists in German only is listed on the English page in German, and the block that shows it on a page says so with a `lang` attribute. The **Dashboard** counts the texts that are empty in a language of the site while another language has one, the native language included, and each notice leads to the Text panel (it shows to an account that may use it). A feature that is switched on brings its words in every language the site serves - a language it has no words of gets the keys, empty - so the count and the fallback cover its texts as well, and an update of the kernel merges the words of the base unit once, at the first login after it (see [Updates and Rollback](deployment.md#updates-and-rollback)).

## Addresses

Every screen of the workbench has an address, the part after the `#`: `#users` is the Users panel, `#roles` its tab **User roles**. The address of a panel with tabs is the panel's own screen, its first tab - a link to the panel, an address typed by hand and the back link of a panel all open it there, whichever tab was open last - and the address of a tab opens that tab. The rail is the one way in that remembers: its links, and the select that stands in for it on a phone, open the tab that was open last in that panel until the page is loaded again, and the address then names that tab. What a panel shows deeper than its tab - an element, a text, an account - is written after the name (`#elements/<type>/<element>`), so that a reload, a copied link and the browser's Back and Forward return to it.

## The Mode

The workbench says which of two modes the project is in, in a chip under the brand in the rail: **Production**, a quiet one, and **Development**, in the warning colour. It is a link to the **Config** panel, where the mode is a select in a group of its own above the rest; an account without that panel sees the chip without the link, and a folded rail, like a screen narrower than 38 rem, keeps its first letter, the whole word in its title. The mode is `/nino/mode` in `config.php`, `production` where the file does not name one.

Nothing in the kernel reads the mode while it runs. Switching it in the Config panel *writes* four settings, over what the form holds for them: **Development** shows errors on the page (`/nino/error/display`), logs them (`/nino/error/log`), switches the page cache off (`/nino/cache/status`) and gives it a lifetime of a minute (`/nino/cache/ttl`); **Production** hides the errors, logs them, switches the cache on and gives it an hour. Each of the four stays a field of its own that can be changed afterwards without changing the mode, and saving the mode as it is writes only what the form holds. The workbench's own log (`/nino/admin/logs`) is not part of it.

What the workbench reads the mode for is how much it asks. A question has one of three weights: *routine*, what is done all day; *big*, what reaches visitors or many records at once - renaming an element type, importing translations into the native language, a role's scope, deactivating an account or a feature, logging an account out everywhere, narrowing the format of a text key; and *destructive*, what cannot be taken back - deleting, removing a picture, restoring a backup. In **Development** only a destructive action asks. In **Production** all three ask, and a big one adds a line that the website is live and not in maintenance, unless the [maintenance page](#maintenance) is up. The weights and the rule are the shell's: how a panel asks is in the [panel recipe](recipes/admin-panel.md#saying-what-happened).

## Unsaved Input

A form that holds input nobody has saved asks before it is left, and every way out asks the same question - **Save**, **Discard** or **Cancel**: the back link of the form, a move through the rail (its links, and the select that stands in for it on a phone), the interface language, a log out. **Cancel** stays where you are and puts the rail back as it was - the open link, the select and the address - and **Discard** forgets the input and leaves the form: one with a Back link is left the way that link leaves it, and is drawn again from what is stored when it is opened next; one that is the whole screen of its panel shows what is stored again. What was left behind is a clean form and not one with input nobody will find again. A move from one tab of a pane to another leaves nothing and asks nothing. The one exit that is not the workbench's is a reload or a closed tab: the browser asks that itself. **Ctrl+S** (**Cmd+S** on a Mac) saves what is on screen where it holds unsaved input, as the Save button of the form does; the browser's own answer to the key, saving the page to disk, is taken away in the workbench.

## The Manual: the ?

Every pane ends its head with a **?**, a button that opens the manual of the panel in a dialog: what the panel is for in a sentence, then one entry for each thing done in it. The manual of a panel of the workbench is written for the people who work with it, in both languages; the manual of a feature's panel is the one its manifest carries, which the Features panel shows to the people who develop with the feature (a feature that says nothing about its use has no **?**). The Dashboard, which has no head to put it in, has the **?** in a row of its own. Escape or the button at the foot closes the dialog and the focus goes back to the **?**. How a panel brings one is in the [panel recipe](recipes/admin-panel.md#the-manual).

## Features

**Features**, a panel of the System group, switches the packages of `features/` on and off, sets them up and installs them from a catalogue; how one is written is the [feature manual](features.md). The setup wizard offers none: a feature is switched on here, after setup.

### The panel

The panel asks for `/_admin/features/manage` on every action, a developer's permission. It sorts every directory below `features/` that carries a valid manifest into three tabs - **Active**, **Inactive** and **Available** - each with a count, Active first, and a search box (name, key, description, category) and a category select narrow all three at once. A row shows name and description in the interface language - they come from the manifest, because the texts of a feature that is off are not loaded - the version, the category, and whatever stands in the way of an activation: a Nino version the feature was not written for, a PHP extension that is missing, a required feature that is not in the directory.

The seven actions behind it are `features/list`, `features/activate`, `features/deactivate`, `features/settings`, `features/remove`, `features/catalogue` and `features/install`; behind the first five stand `\Nino\Features::all()`, `activate()`, `deactivate()`, `saveSettings()` and `remove()`, behind the last two `\Nino\Catalogue::fetch()` and `install()`:

- **Activate** switches a feature on: the features it requires first, then its install unit, applied without overwriting anything your project has, the class listed in `/nino/modules` and the version recorded under `/nino/features`. A dialog names everything that was switched on - the feature asked for first, the requirements after it - before the workbench reloads; an update says the same where the new version added a requirement that was off.
- **Update** is offered for an active feature whose manifest names another version than the recorded one, after its directory was replaced with a new release. It is the same action: the unit adds what is new and the feature migrates its own data.
- **Deactivate** removes the class from the list and nothing else - settings, data and copied templates stay, and activating again finds everything as it was. It is refused while another active feature requires this one. A dialog then lists the places that still contain the feature's shortcodes (templates, texts, elements), because a shortcode of a feature that is off stays the text it is written as; which shortcodes are the feature's is read from what is registered (`\Nino\Features::shortcodes()`), opening tags in raw brackets only - one the Text panel stored as `&#91;...&#93;` was never rendered and is not reported; the panel changes nothing there. Before the switch it asks, naming what stays - the shortcodes, the sections of the privacy policy and what the feature installed - and declining does nothing. The dialog also names the sections of the privacy policy that still describe the feature - the Dashboard keeps saying so while the feature stays installed - and an editor hides them under **Elements › Privacy policy** if they no longer apply. There is no uninstall, deliberately: deleting a file the project may have edited in the meantime would not be safe, and what a feature leaves behind, a developer removes knowingly and by hand.
- **Remove** deletes the directory of an inactive feature, the one step deactivating leaves out. What the feature kept stays, so putting it back finds its settings; an active feature is refused, since a directory deleted from under the autoloader is a fatal on the next request.
- **Settings** are on the screen an active feature's row opens, in two tabs: **Description**, the sentence the manifest describes the feature with and under it its manual, and **Settings**, the form the manifest describes. Both tabs are built at once, so what was typed into a setting survives a look at the Description, and the one Save collects the whole form whichever tab is on; every setting is validated before any is written, an error names the setting, and the values are stored under `/nino/features` in `config.php`. A feature that declares no setting has the one tab it has and no strip over it. A feature that has a panel of its own and settings gets them there instead, as a **Settings** tab of that panel on the same actions and the same permission, and its screen here links to that tab - an account that holds only the feature's own permission does not see them, and an account that holds only `/_admin/features/manage` gets that panel in its rail, opened on the Settings tab alone.

A panel a feature brings appears with the next load of the workbench after the activation and goes with the next one after the deactivation; the panel reloads the workbench itself. It lands in the rail's **Features** group whatever its own `nav()` names and offers its permission on the roles tab of the Users panel under that group's label - the **Editor** role the wizard wrote does not receive it by itself, grant it there.

### The catalogue

A feature that is not copied in by hand comes from a catalogue: a `catalogue.json` published over https beside the archives it lists, and a detached signature `catalogue.json.sig`. Nino's own is `https://catalogue.getnino.dev/catalogue.json`, built and signed by [dapeio/nino-features](https://github.com/dapeio/nino-features); the kernel side is `\Nino\Catalogue` and the one http client `\Nino\Fetch`, both checked without a network by `tests/catalogue-smoke.php`.

The **Available** tab shows what the catalogue offers that this installation does not already have current. Only **Refresh catalogue** fetches: the tab fills from the cache, `data/catalogue.php` (when it was fetched, under which url, the parsed document - ignored when another url is configured now), a status line says when it was last read, and the offers are matched against the features on disk every time. Loaded, it offers **Install** for a feature that is not in the directory, **Update** for one there in an older version, and greys out, with what it asks for, one no version of which fits.

Install downloads the archive, checks it against the signed catalogue and puts the directory in place; then it switches the feature on in two of three cases. A feature the project did not have is switched on - pressing Install says the project wants it. An active one is activated again, which applies the update in a second request the panel makes on its own, since the first booted with the previous version's class. One that is there and switched off stays off: a newer version of it is not somebody changing their mind. Requirements come along: the whole set is worked out first, checked against this kernel and placed deepest first, so the feature asked for arrives last; one already on disk stays as it is, whatever its version, and one the catalogue cannot serve refuses the install with nothing placed. A dialog names what came along; where the files are placed but the activation fails, the panel says exactly that. Where `features/` is not writable, the tab links the archive instead, to unpack by hand.

The signature is the trust: ECDSA (P-256) over SHA-256 of the exact bytes of the document, DER and base64 (`openssl dgst -sha256 -sign key.pem catalogue.json | base64`), checked against `\Nino\Catalogue::PUBLIC_KEY`, or against the PEM in `/nino/catalogue/key` for a catalogue of your own, found at `/nino/catalogue/url`; an empty key falls back to Nino's, and a url of `''` switches the catalogue off - the tab says so, there is no Refresh button and no request. An installation fetches the catalogue again rather than trusting what the panel showed, downloads the archive within the byte cap of its entry, refuses one whose size or SHA-256 differs, unpacks it below `data/.features/` after looking at every entry (one directory, plain files and directories only, nothing outside it, bounded in count and size), requires the key and version the catalogue promised, and only then moves the directory in, putting the old one back if the move fails.

It needs the `curl` extension or `allow_url_fopen`, `openssl`, `phar` and a writable `features/`. Every request goes over https to the catalogue's host and nowhere else - no redirect followed, the certificate verified, the user agent `Nino` and nothing more - and only when someone presses **Refresh catalogue** or **Install**: no check in the background, no telemetry, nothing sent. A directory copied into `features/` is a feature like any other.

#### A catalogue of your own

A catalogue is an address, a key and a directory of features; `bin/build.php` of the [catalogue repository](https://github.com/dapeio/nino-features) publishes the `catalogue.json`, its signature and one `.tar.gz` per feature from such a directory. The document (format 1) holds `format` (`1`), `generated` and `features`, one entry per published version; a catalogue that is wrong anywhere is refused as a whole, `\Nino\Catalogue::parse()` naming the entry and the field.

| Field | Meaning |
| --- | --- |
| `key`, `name`, `description`, `version`, `nino`, `php.ext`, `requires` | what the feature's manifest says, see [the manifest](features.md#the-manifest) |
| `category`, `maturity` | optional, the same two the manifest has; a reader drops one it cannot use without refusing the catalogue |
| `directory` | the directory the archive holds - `Newsletter`, the feature's class name segment |
| `archive` | the https url of the `.tar.gz` |
| `sha256`, `size` | the digest and the byte length of exactly that file |
| `released` | the date |

An archive is a `.tar.gz` of exactly that one directory - what lands below `features/`, nothing beside it; a feature's `tests/` are not published.

## Maintenance

**Maintenance**, a panel of the System group, is one switch: while it is on, every visitor who is not signed in to the workbench gets a 503 answer instead of the site - the page itself and a module endpoint such as the contact form's `/.form` alike - and a signed-in account sees the site as it is, with a banner. `/_admin` and its login keep working throughout, so switching it off never depends on the switch itself.

The page a visitor gets is the project's own `templates/page-maintenance.tpl` where it has one - copied in by hand from the module's own `install/` directory, with `/module/maintenance/page/title` and `/module/maintenance/page/text` as its two texts, from then on editable in the Text panel like any other key - and otherwise the module's plain page in the site's native language, so the switch works from the moment the module exists. Nothing in the setup wizard installs the styled version on its own.

## Recovery

`/_admin/recovery.php` is the way back in when the accounts themselves are what is broken: every developer password forgotten, or a restore gone wrong. It asks for the **recovery password** set in the wizard's last step – not a login; the workbench asks for it in one place only, the **Recovery password** tab of Users, which changes it – and offers three things:

- **Restore a backup**, from the list of dates, after snapshotting the current state;
- **Set a password** for an account that exists, picked from a list: it gets the new password, is logged out everywhere, and is unlocked and activated again if it was locked or deactivated - so a recovered developer account gets in. A typo cannot name an account, there is none to name;
- **Create a full-access account** - for when no account is left. It is its own action, asks to confirm before anything is written, and refuses an address that already has an account.

Five wrong attempts lock it for an hour, the wrong current passwords typed into the Recovery password tab among them. The secret's hash lives in `private/.auth/pw.php` – outside `config.php`, so a restore cannot roll it back, and outside every tool directory, so an update cannot take it along. Only the wizard's last step and the Recovery password tab write that file. A secret that is forgotten, so that the tab has no current password to ask for, is written by hand - it is a php stub that refuses to be served, with the hash inside it:

```bash
php -r 'echo "<?php http_response_code(403); exit; return \x27", password_hash( $argv[1], PASSWORD_DEFAULT ), "\x27;\n";' -- '<password>' > private/.auth/pw.php
```

Do this in a protected local environment only – a password on a command line may be visible in the shell history or the process list.

The **Recovery password** tab of the Users panel changes the recovery password: the current one, the new one - at least eight characters - and the new one again. A wrong current password counts against the same five attempts `recovery.php` has. The tab cannot set a first password: where `private/.auth/pw.php` is missing, the one-liner above writes it. The tab needs `/_admin/recoverypw/manage`, a permission of its own that the Developer role holds through full access and the Editor role does not - whoever holds it can lock `recovery.php`, and with the recovery password restore a backup over the whole project.

## With the Keyboard

The first Tab stop of the workbench is a link that skips the rail and lands on the pane. A panel opened from the rail starts at its name; a row that opens a form takes the focus into it, and *Back* returns the focus to the row that was opened. The action bar at the foot of the screen never covers the field that has the focus. The settings menu of the rail - the theme, the language of the interface - closes with Escape and gives the focus back to its button. **Ctrl+S**, or **Cmd+S**, saves the open form (see [Unsaved Input](#unsaved-input)); it does nothing while a save is running or a question is open, and nothing where the screen holds nothing unsaved.

## Recommended Workflow

1. Build the structure under **Element Types** (a tab of Elements), **Text Keys** (Text), **Image Slots** (Images), **Routes** and **Navigations**.
2. Compose the pages by hand or, where the feature is installed, in the Builder; adjust `assets/theme.css` where the delivered look is not the one you want, and check the result in the browser.
3. Fill the content under **Elements**, **Text** and **Images**; hand a language over under **Language › Translations**.
4. Check the Dashboard and the two scans for missing definitions.
5. Create the editor accounts under **Users** with the Editor role - add a role on the **User roles** tab where the two are not enough - and test what they see.
6. Check the frontend, every language, the forms and the responsive layout.
7. Commit the project files.

## If Something Does Not Work

| Problem | Check |
|---|---|
| Login locked after several attempts | Wait out the lockout duration (an hour by default), or lift an account's lock under **Users › Login protection**; an address lock is not listed there and still has to expire; the lock is per account and per address. Behind a reverse proxy, set **Config › Reverse proxies in front of this site**, or every visitor shares one address and one lock. |
| A panel or a tab is missing | The account lacks its permission, or its module is not active. |
| Saving fails | The message says why where Nino has a sentence for it (a value out of range, an address already in use, a file over PHP's upload limit). Otherwise: write permissions of the affected file or directory. |
| A login dialog appears over a form | The session ended; log in again and the request is sent on. *Another account is logged in in another tab* means exactly that: reload the page. |
| Template missing in **Routes** | Only existing `templates/page-*.tpl` files are offered. One a panel of the workbench has written (the Builder creates them) is offered the next time Routes is shown, with no reload; one put on the disk by hand is offered after a reload of the page. |
| Texts or images missing in a scan | Dynamic keys and images are not statically recognizable. A picture a card has as its background is found as the slot it names, like any other. A slot the scan proposes for a component that names no size has to be given its width and its height before it is made: a slot is never created one pixel wide. |
| A contact form answers visitors with an error and no mail goes out | One address may make the site send mail only so often in an hour: **Config › Mail calls per address**, `/nino/mail/cap`, 5 by default and 0 for none. A call is counted, not a mail, and the mails you send from the workbench count like any other - a site that sends many from a panel needs a higher number. Behind a reverse proxy, set **Config › Reverse proxies in front of this site** first, or every visitor shares one count. |
| Backup list is empty | Backups are switched off, or no authenticated request has happened today. **Back up now** writes the first archive at once. |
| Website broken after **Config** | Restore the last Git state or backup. |
| No developer password works any more | `/_admin/recovery.php` with the recovery password. |
