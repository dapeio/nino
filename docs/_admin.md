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
| Template missing in **Routes** | Only existing `templates/page-*.tpl` files are offered. |
| Texts or images missing in a scan | Dynamic keys and images are not statically recognizable. |
| Backup list is empty | Backups are switched off, or no authenticated request has happened today. **Back up now** writes the first archive at once. |
| Website broken after **Config** | Restore the last Git state or backup. |
| No developer password works any more | `/_admin/recovery.php` with the recovery password. |
