# Nino — Editor Handbook
*[Deutsch](_editor.de.md)*

**Links:**
[README](../README.md) · [Design-Handbook](design.md) · [Developer-Handbook](development.md) · [_admin-Handbook](_admin.md) · [_install-Handbook](_install.md) · [_templates-Handbook](_templates.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

Precise instructions for the day-to-day maintenance of the website via `/_editor` — no programming knowledge required.
For technical background: `docs/development.md`.

### Signing in

Sign in at `/_editor` with an email address and password. After too
many failed attempts the account is temporarily locked
(default: 5 attempts, followed by a 1-hour cooldown — configured via
`/nino/auth/maxtries`/`/nino/auth/cooldown`, see
`docs/development.md`).
( The error message deliberately does not distinguish between
"email unknown" and "wrong password" — both show the same generic
message, so that no valid email addresses can be guessed from the
error message itself. )

## 1. The admin area

A successful login rotates the PHP session ID — after a login you may
need to reload the contents of any other open tab.

### a) Dashboard

The most important information at a glance. The most recent form
submissions, all newsletter sign-ups, the last backup, and the most
recent changes made in the admin area.

### b) Elements

Recurring content building blocks (e.g. offerings, references, price
lists, team members) with forms predefined by the developer (field
model, created via `/_admin` → "Element Types", see section 11).

**Procedure:**
1. Pick an element type from the list (the overview shows the number of
   existing entries for each type).
2. Open an existing element or create a new one.
3. Every field can be filled in separately per language (language tabs
   at the top of the form) — leaving a field empty in one language is
   valid and simply renders as an empty fill in the template.
4. Image fields upload through their own upload window; the system
   crops automatically (centred) and scales to the target format
   specified by the developer (the same processing as for image slots,
   see section 4 — the only difference is whether the image is attached
   to a fixed slot or to a specific element).
5. Saving writes immediately to the corresponding `elements/<type>.php`.

**Deleting** removes an element including all its images
**irreversibly** — no recycle bin, no confirmation step beyond the
delete dialog itself. The only safeguard is the automatic backup
(section 8) — an accidental deletion can only be undone via `/_admin` →
"Restore", not from within `/_editor` itself.

**Permission:** `Elements::MANAGE_PERM` (the "Elements" checkbox in
user management, see section 5).

### c) Texts

Fixed pieces of text on the website, grouped by area/category (e.g. all
the texts of one page together). A category shows all text entries for
the currently selected language together; changes across several fields
are saved collectively in one step (batch save) rather than being
submitted field by field.

For values that allow formatting (bold, italic, links — detected via
`Html::containsHtml()`), a small formatting bar appears automatically;
the formatting entered is reduced on save to a small, safe tag
whitelist via `Html::sanitizeHtml()` — pasted `<script>` or a
`javascript:` link will not survive the save.

Purely technical values (design tokens such as colours and spacing
under `/ui/*`) deliberately do **not** appear here — they live in
`text/blacklist.php` and are intentionally accessible only to
developers via the raw files (see the architecture section of
`docs/design.md`).

**Export/import** exists for backing up or migrating individual text
sets outside the regular automatic backup (e.g. to reconcile text
between two environments).

**Permission:** its own text permission (the "Texts" checkbox).

### d) Images

Every image position is a fixed **slot** with a target format specified
by the developer (created via `/_admin` → "Images", see section 11). An
upload replaces the previous image at exactly that position — cropping
(centred) and target size are handled automatically by the system via
`gd`; the upload itself is fully re-encoded (the uploaded bytes are
never taken over as-is), including verification of the actual image
data (not just the stated file extension), an 8 MB size limit, and an
8000-pixel source resolution limit. An upload disguised as an image but
actually executable therefore cannot slip through.

**A distinction that is often confused:** an image **slot** (this
section) is a fixed, one-off position in the template (e.g. the hero
image of a page); an image **field** on an element (section 2) belongs
to a single element record and can occur any number of times (one photo
per team member). Technically both end up in the same `images/`
directory and run through the same processing, but they are separate
administratively.

**Permission:** its own image permission (the "Images" checkbox).

### e) Users

**Your own account (every signed-in user):**
- Change your own password/email, each confirmed with the current
  password.
- "Sign out everywhere" ends all active sessions of your own account on
  all devices — use immediately if you suspect a compromise (see the
  security notes, section 12).

**With management rights (the "Full access"/Manager checkbox),
additionally:**
- Set other users' permissions by checkbox: Elements, Texts, Images,
  Requests, Newsletter, Log — or "Full access" for everything at once.
  The available checkboxes are fixed server-side (a fixed list of known
  permission strings) — this form can never set a permission that the
  interface itself does not know about.
- **Nothing about your own account can be changed this way** — a
  deliberate safeguard against accidentally revoking your own
  management rights.
- "Sign out everywhere" can also be triggered for other users.

**Creating new users or deleting users** is deliberately reserved for
developers via `/_admin` and is not possible through `/_editor` (see
section 11) — the separation ensures that creating a first account
never depends on an already existing `/_editor` account.

**If no one with management rights is left** (the last manager account
was deleted, or you accidentally locked yourself out via the raw
`/_admin` permissions), only `/_admin` helps — contact the developer, who
has access to the `_admin` password.

**Permission:** `Users::MANAGE_PERM` for the management part; changing
your own password/email and "Sign out everywhere" for yourself require
no separate permission, only an active login.

### f) Requests

All messages received through the public contact form, newest first —
as a safety net in addition to the automatically sent email (in case it
ends up in spam, gets lost, or mail sending is currently subject to the
rate limit — see the `Mail` section of `docs/development.md`).
Read-only; no deleting or editing via `/_editor`. 90 days of history,
after which entries are cleaned up automatically (3 months,
`Form::RETENTION_MONTHS`).

**Permission:** its own requests permission (the "Requests" checkbox).

### g) Newsletter

A list of all newsletter sign-ups. The sign-up itself runs as a
**double opt-in**: an entry is initially only "pending" and becomes
"active" only once the confirmation link from the automatically sent
email has been clicked — merely filling in the sign-up form does not
actually subscribe anyone.

Subscribers can unsubscribe themselves at any time via an unsubscribe
link (included in every outgoing newsletter email as soon as the
template embeds it — see the `Newsletter` section of
`docs/development.md` for `getUnsubscribeLink()`); in addition, every
entry can be deleted manually here (e.g. on request, or to remove an
obviously mistaken test entry).

**Permission:** its own newsletter permission (the "Newsletter"
checkbox).

### h) Log

A record of recent logins and admin-side changes (who edited what and
when) — every mutating action via `/_editor` (saving, deleting,
permission changes, ...) creates an entry; purely display/read
operations (opening a list without changing anything) deliberately do
not appear, to keep the log readable. Read-only, 14 days of history.

**Permission:** its own log permission (the "Log" checkbox).

## 2. The developer area (_admin)

### a) Automatic backups

Once a day, triggered by the first login of the day (not as a separate
cron job — it runs in the background of the first `/_editor` request a
signed-in user makes that day), an encrypted backup is created of all
content editable via `/_editor` (elements, texts, images, user
accounts). 14 days of history; older backups are deleted automatically.

The backup files themselves are AES-256-GCM encrypted and are stored
under a one-off, nowhere-linked random name — they serve exclusively
for restoration via `/_admin` (section 9) and cannot be viewed directly
through a browser request (a direct request returns only a 403 status
code, no data).

A developer can disable backups (and, independently of that, the
activity log, section 8b) project-wide via `config.php`'s
`/nino/editor/backups`/`/nino/editor/logs`.


### b) Backup restoration (via `/_admin` only)

The state of any day within the last 14 days can be restored via
`/_admin` → "Restore". The current (possibly faulty) state is
automatically saved beforehand as its own safety snapshot — a
restoration is therefore itself reversible, by immediately restoring
that snapshot afterwards.

`/_admin` has its own password, completely independent of the `/_editor`
accounts (known only to the developer) — which is why restoration still
works even when the `/_editor` user accounts are precisely the damaged
part.

### c) First access & passwords (`/_admin`)

Without a single existing `/_editor` account there is no way to sign in
there — the very first access therefore always comes through `/_admin` →
"Users", with the management rights checkbox ticked, so that this first
account can subsequently manage all other accounts and permissions from
`/_editor`. Creating new `/_editor` accounts or deleting existing ones
remains a `/_admin` task permanently thereafter (see section 5).

`/_admin` itself has no form login with an email — only a single,
project-wide password, set via the command line (`php _admin/Admin.php
<password>`, with the resulting hash entered into the `PASSWORD_HASH`
constant in `_admin/Admin.php`). The placeholder hash shipped with the
project deliberately matches no real password — your own must be set
before first real use, otherwise `/_admin` is unusable for anyone, not
even for the developer.

## d) Further `/_admin` areas (brief overview for operators)

These areas are pure developer tooling and are mentioned here only for
completeness — for details see `docs/development.md`:

- **Element Types** — defines the field model for element types (which
  fields an "offering" or "team member" has), before `/_editor` →
  "Elements" (section 2) can maintain data with them.
- **Images** — creates image slots (fixed positions with a target
  format) before `/_editor` → "Images" (section 4) can fill them; also
  contains a scan function that searches templates for slots that are
  referenced but not yet created.
- **Texts** — creates new text keys, renames them, and contains the
  same scan function for `[[key]]` placeholders used in templates but
  not yet defined — before `/_editor` → "Texts" (section 3) can fill
  them.
- **Configuration** — raw view/editing of selected, released
  `config.php` keys (locales, error display/logging, asset bundles,
  routes) — not everything in `config.php` is editable here, only a
  deliberately limited, type-checked subset.
- **Users** — see sections 5/10.
- **Restore** — see section 9.

## 3. Security notes

- Do not share passwords, not even within the team — every `/_editor`
  account is individually traceable via the log (section 8b), and a
  shared password destroys that traceability.
- If you suspect a compromise (password accidentally passed on,
  suspicious log entries), use "Sign out everywhere" (section 5)
  immediately and change the password afterwards — in that order, so
  that an already running foreign session is not left running by a mere
  password change.
- The log shows at any time who last changed what — in the case of an
  unexplained content change, look there first before considering a
  restoration (section 9).
- An automatic backup is no substitute for deliberate care: deletions
  (section 2) take effect immediately, the backup only catches them at
  the next daily run, and after that the previous state is only
  reachable through the 14-day history.
