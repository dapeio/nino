# `/_admin` — Operation Manual

**Language:** English · [Deutsch](_admin.de.md)

**Last updated:** August 22, 2026 · **Nino version:** 0.11.0-beta.1

This manual explains the complete technical and content project management under `/_admin`. If you instead only want to maintain released content on a daily basis, read the [`/_editor` Operation Manual](_editor.md); fast section-based page composition is described in the [`/_templates` Operation](_templates.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [`/_theme` Operation](_theme.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Security Note:** `/_admin` is intended for developers. Changes are written directly to configuration and project files and can affect routing, data models, content, and the visible website. Therefore, work with a current Git state or another reliable backup.

## Purpose and Scope

`/_admin` provides full access for development, diagnosis, and corrections. `/_editor` forms the released workspace for editors and operators. The separation thus no longer fundamentally runs between structure and content but between technical full access and role-based daily maintenance.

| Area | `/_admin` | `/_editor` |
|---|---|---|
| Element Types | Define fields and data types | No structural changes |
| Elements | Edit all entries, languages, and storage buckets | Maintain released entries within permissions |
| Texts | Fully manage keys, values, languages, and editor visibility | Maintain released values |
| Translations | Export native Text/Elements content and import a target language | No batch workflow |
| Images | Define image slots and target dimensions | Upload and replace images |
| Routes | Manage page routes, templates, and menu membership | Maintain page texts |
| Navigations | Create menus and set their running order | No access |
| Appearance | Link to the four-dialog `/_theme` workspace | No access |
| Page templates | Link to the section-first `/_templates` Template Builder | No access |
| Users | Create, delete, and technically manage accounts | Manage profile data and released permissions |
| Configuration | Edit selected technical values and rebuild Elements search indexes | No access |
| Backups | Restore existing backups | Trigger daily backup automatically |

The menu items in `/_admin` are predominantly in English in the current beta state. This manual uses the German terms and names the visible menu items accordingly.

## Login and Secure Operation

Open `https://your-domain.example/_admin` and log in with the technical password set in the last step of `/_install`. This access is independent of the email accounts under `/_editor`.

After five failed login attempts, `/_admin` locks the shared access for one hour. The lock applies project-wide, not just to the browser used.

For operation, note:

- use `/_admin` exclusively via HTTPS;
- do not share the technical password with editors;
- log out via **Logout** after work;
- additionally protect the area if needed via web server, VPN, or IP allowances;
- remove `_admin/`, `_theme/`, and `_templates/` from delivery if the interfaces are not needed in live operation.

`/_theme` and `/_templates` use the same password, lock status, and session as `/_admin`. All three authenticated surfaces therefore carry the same compact **Admin / Builder / Theme** bridge. It is assembled from the complete tool entry points found in the delivery: a tool that was omitted or only partially copied is not linked, and the current surface is highlighted. No separate configuration switch is involved. Without `_admin/`, neither optional tool can be used.

The password can be rehashed outside the installer with `php _admin/Admin.php <password>`. The output is a complete file — write it to `private/.auth/pw.php`, replacing whatever is there. It is deliberately not in `_admin/Admin.php` (a tool folder must stay replaceable on an update) and not in `config.php` (a restore rewrites that file, and the credential that authorises restoring has to survive it). Perform this process only in a protected local environment; a password entered as a command-line argument may be visible in shell history or process list.

## Dashboard

The **Dashboard** summarizes the technical project state:

- number of element types, routes, and editor accounts;
- date of the last automatic backup;
- fields per element type;
- text keys and image slots used in templates but not yet defined.

The tiles and notes lead directly to the respective area. The dashboard itself does not change data.

## Element Types: Define Element Types

Element types describe recurring content such as services, team members, or references. Each type corresponds to a file under `elements/`; its entries are then fully maintained under **Elements** or within the granted permissions in `/_editor`.

### Create an Element Type

1. Open **Element Types** and select **New type**.
2. Assign a technical URI. It starts with a lowercase letter and may then contain lowercase letters, digits, hyphens, and underscores, e.g., `team` or `service_items`.
3. Assign a understandable title for display in `/_editor`.
4. Add the required fields with **Add field**.
5. Save the type.

The URI becomes the filename `elements/<uri>.php` and cannot be changed via the interface after creation.

### Field Types and Options

| Type | Suitable For |
|---|---|
| `string` | single or multi-line texts; optionally as rich text or fixed selection list |
| `integer` | whole numbers |
| `double` | decimal numbers |
| `boolean` | yes/no values |
| `array` | simple lists or structured values |
| `date` | a date |
| `datetime` | date and time |
| `image` | an image with fixed target dimensions |
| `element` | a reference to an element of another type |

Depending on the type, additional properties are available:

- **per translation** stores a separate value for each language;
- **Required field** makes the field mandatory;
- **Rich text** activates bold, italic, highlight, inline-code, and link formatting for the `string` type;
- fixed values limit a text field to a predefined selection;
- width and height determine the target dimensions for images;
- a unit or suffix adds, for example, `€`, `km`, or `%` in the input;
- **References element type** determines, for the `element` type, which type this field may point at.

An `element` field links one element to another. When defining the field you pick the element type it references; when editing an element you then pick one of that type's entries from a select. The stored value is the referenced element's full uri (`/<type>/<slug>`), which templates hand straight to `\Nino\Elements::getElement()`. A field may only be saved once the referenced type exists, and its value may only point into that type. If the referenced element is deleted later, the reference is kept and shown as *missing* — nothing is silently dropped. References are not part of a Translations export, since a uri is a choice rather than translatable text.

When switching between global and language-dependent fields, Nino migrates existing values. Still, check the result in each language. Saving a type model does not delete existing entries; however, removed fields no longer appear in `/_editor`.

### Named or Numbered Element URIs

Every element is addressed by a uri of the form `/<type>/<slug>`. By default the
element form asks for that slug, which is right whenever the entry has a name
worth putting in a url — `/team/ada`, `/service/website-relaunch`.

Some types have entries with no such name: an image in a gallery, a row in a
price list. Asking for one anyway is how a project ends up with `bild-2`,
`bild-2-neu`, `bild-2-final`. For those, switch on **Number the elements of this
type** in the type's *Element URIs* group. The element form then stops asking for
a uri and states the one it is about to create instead — `/gallery/00001`,
`/gallery/00002`, and so on. The number is assigned when the element is saved.

- **Numbers are never reused.** Deleting the newest entry does not hand its
  number to the next one. A uri is a public address that ends up in links,
  sitemaps and bookmarks, so a gap in the numbering is better than silently
  pointing an old address at a different element.
- **Switching it on later is safe.** Existing entries keep the uris they have,
  and numbering starts past the highest number the type already uses — including
  one that was written by hand.
- **Switching it off** returns the type to asking for a slug. The entries that
  were numbered keep their numbers.
- The counter lives in the type file itself (`elements/<uri>.php`), next to its
  title, and is allocated under the same lock as the element, so two people
  saving at the same moment cannot be handed the same number.

Element types cannot be deleted via `/_admin` on purpose. This prevents a careless click from destroying all associated content.

## Elements: Fully Edit Content

The **Elements** area supplements the type definition with the actual entries. It offers the same basic editing scope as `/_editor` but is not restricted by editor permissions or hidden areas.

![Full element editing in `/_admin`](assets/screenshots/admin-elements.webp)

1. Select an element type.
2. Open an existing entry or create a new one.
3. Edit global fields once and language-dependent fields in the desired language.
4. Save a new entry first before uploading images into its image fields.

Additionally, `/_admin` shows the underlying storage buckets: `*` for global values and one bucket per language. This technical view helps with migrations and diagnostics but should not lead to normal content maintenance.

Rich text values are cleaned when saving; required fields and field types are checked by the kernel.

When deleting, Nino removes the entry in all languages and the associated element images. Secure the project state if the data cannot be restored otherwise.

## Text: Manage Text Keys and Values

The **Text** area provides full access to keys and values and shows all textfills grouped by the first segment of their key. A key like `/home/intro/title` thus appears in the `home` group.

![Global and language-dependent text values in `/_admin`](assets/screenshots/admin-text.webp)

Here you can:

- maintain values globally or per language;
- create new text keys;
- rename keys;
- switch between global and language-dependent storage;
- hide keys from `/_editor`;
- delete keys completely.

When renaming and when switching the storage form, Nino migrates existing values. When deleting, the value disappears from all languages. Therefore, check before deletion whether the key is still used in templates, emails, or project-specific modules.

### Find Missing Keys

**Scan templates for missing keys** searches the public `.tpl` files for static textfills like `[[/home/intro/title]]`. Found keys can be ignored individually or created together.

The scan cannot fully recognize dynamically composed keys. A flawless scan therefore does not replace testing all pages and languages.

## Translations: Translate the Native Content Layer

**Translations** is the project-wide hand-off for translating a site after its native content has been completed. The export always uses the configured native locale and combines:

- non-global, nontechnical Text values;
- locale-scoped Element fields which have an actual value in the native bucket.

Global values, blacklisted technical text, images, element URIs, ordering, and structural data are not exported. The JSON includes instructions for translation tools: translate values only and preserve keys, JSON value types, HTML, URLs, placeholders, shortcodes, and identifiers.

1. Download the native JSON package.
2. Translate its values without changing the object structure.
3. Choose a configured target language.
4. Upload the `.json` file or paste its content and select **Import into selected language**.
5. Check the imported and skipped counters.

Import is merge-only: matching target-language values are overwritten, while values absent from the document are not deleted. The server validates every path against a fresh native export, sanitizes text and rich-text values, and skips unknown, global, technical, and image fields. Selecting the native locale as the target is possible but overwrites source content and should therefore be deliberate.

## Routes: Manage Page Routes

The **Routes** area manages the page routes created via `/_install`, including which menus each page belongs to. The order those menus run in is set under **Navigations**. There is no separate page list: the list you see is derived from `/nino/http/routes` and the `/webpage<uri>/*` text keys on every request, so a route written here, in `/_install`, or by hand in `config.php` is the same page everywhere.

A page has two different URIs:

- **Element URI** is the stable internal identity to which page texts like `/webpage<uri>/title` are attached. Saving a page also writes `/webpage<uri>/uri` — its reachable path, as one global technical value — so a template can link to the page with `[[/webpage/site-contact/uri]]` instead of repeating a path this form can change;
- **HTTP URI** is the actually reachable path in the browser.

Thus, a page can internally be called `/about` and publicly be reachable under `/ueber-uns`, for example.

When creating or editing, you also determine:

- an existing template from `templates/page-*.tpl`;
- an HTTP status code;
- navigation name, page title, and description for each active language;
- one checkbox per navigation registered in `/nino/html/navs`.

Membership is stored on the page's own route as `'navs' => [ 'main' => 1, ... ]`, where the value is a priority (lower first). A membership added here starts behind everything already in that menu; a priority you tuned by hand is never reset by a later save. A route added to `config.php` by hand joins a menu the same way, without any tool, and is rendered by `[navigation nav="main"]`.

The arrows in the page list swap two page routes in `config.php`; every other route keeps its own slot. Menu entries of equal priority follow route order. Reserved paths such as `/_admin`, `/_editor`, `/_install`, `/_templates`, and `/_theme` cannot be used as public pages.

Some routes select their template at runtime. In this case, `/_admin` shows the existing route body and leaves it unchanged when saving.

Deleting a page removes its route. Its page texts and its template file remain — nothing on disk is deleted for you.

## Navigations: Menus and Their Running Order

The **Navigations** area is the other half of what **Routes** edits. There a page ticks the menus it belongs to, one page at a time; here one menu is opened and its whole running order is set.

The list shows every menu registered in `/nino/html/navs`, with the number of entries it holds. Opening one shows its entries in the order they render, with:

- ↑ / ↓ to move an entry within this menu;
- × to take it back out — the route itself stays, only its membership goes;
- a picker that adds another route, which joins at the end.

Every `GET` route can be a menu entry, not just the pages **Routes** manages — a menu entry is only ever a path with a name. That name is the page's own `/webpage<uri>/name` key; a route without one is marked accordingly, because `[navigation]` skips it rather than rendering an empty link.

Priorities are kept dense, `1..n` per menu, so the number stored on each route reads as its position. A route added to `config.php` by hand joins a menu the same way and appears here like any other.

**Creating** a menu registers its id; **renaming** one checks that the id is free — in the registry *and* on every route — and then follows it into both. **Deleting** removes it from the registry and from every route in it.

Neither renaming nor deleting touches your templates: the `[navigation nav="…"]` argument in a template is content, so update it yourself. Until you do, the renamed menu renders nowhere and the deleted one renders empty.

There is no language switch here: a menu has nothing per-language about it, and the same running order serves every language.

## Images: Define Image Slots

An image slot connects a technical URI with a understandable designation and fixed target dimensions. Editors then see these slots under **Images** in `/_editor` and can upload the actual file there.

For a new slot, the following are required:

- URI, e.g., `/home/hero`;
- designation, e.g., `Homepage — Hero Image`;
- width and height in pixels.

**Scan templates for missing image slots** searches public templates for local `<img src="...">` references under `images/` for which no image slot yet exists. Dynamic images and external URLs are not captured.

When deleting an image slot, the image stored there is also removed. Use this action only if neither template nor content still need the slot.

## Users: Editor Accounts and Permissions

Under **Users**, you manage the accounts for `/_editor`. The technical `/_admin` password is not managed here.

### Create Account

Provide a valid email address and a password with at least eight characters. A regular new account receives the usual content permissions. The **Administration** option grants full access over `/*` and should only be used for trusted responsible parties.

The email address and password of an existing account are then changed in `/_editor`. `/_admin` is responsible for creating, deleting, and technical access to permissions.

### Edit Permissions

The **Permissions** link opens the permissions as a JSON array. This editor is intentionally not limited to known permissions and is thus a technical emergency and development tool. For normal role changes, the checkbox view under `/_editor` is safer.

Examples:

```json
["/_editor/text/manage", "/_editor/images/manage"]
```

```json
["/*"]
```

Invalid or overly broad permissions can lock users out or inadvertently grant access. Back up `config.php` before manually changing permissions here.

Deleting an account terminates its access. Check beforehand whether at least one managing account remains.

## Restore: Restore Backup

`/_editor` automatically creates an encrypted backup on the first authenticated access of the day by default. Daily backups are kept for 14 days. Under **Restore**, `/_admin` shows the available data.

Select the desired date and confirm **Restore**. Before restoration, Nino automatically backs up the current state again. Then test at least:

- frontend and all languages;
- login and permissions in `/_editor`;
- pages, texts, elements, and images;
- forms and newsletter data.

The automatic backup is a safety net for editorial changes but does not replace an external backup of the entire project.

## Config: Technical Configuration

The **Config** area edits a deliberately limited selection from `config.php` as a form. Every value is typed and validated before it is written, and the whole page is saved in one write.

| Group | Setting | Key | Control |
|---|---|---|---|
| Errors and diagnostics | Write errors to a log | `/nino/error/log` | switch |
| Errors and diagnostics | Show errors in the frontend | `/nino/error/display` | switch |
| Errors and diagnostics | Always set the session cookie as secure | `/nino/session/force-secure-cookie` | switch |
| Login protection | Failed logins before lockout | `/nino/auth/maxtries` | number, 1–100 |
| Login protection | Lockout duration | `/nino/auth/cooldown` | number, 60–604800 seconds |
| Editor features | Daily encrypted backup | `/nino/editor/backups` | switch |
| Editor features | Record an audit trail | `/nino/editor/logs` | switch |
| Page cache | Cache rendered pages | `/nino/cache/status` | switch |
| Page cache | Lifetime of a cached page | `/nino/cache/ttl` | number, 10–2592000 seconds |
| Page cache | Never cache these | `/nino/cache/blacklist` | one uri per line |
| Languages | Languages | `/nino/locales/available` | checklist |
| Languages | Native language | `/nino/locales/native` | select |

The language list shows every locale the project knows about: the ones `config.php` lists, plus every `text/<locale>.php` found on disk. Each row reports whether that file exists and how many keys it holds — a language switched on without one renders every per-locale fill as a raw `[[key]]`, so the panel says so before you save.

**Adding a language** takes its id (`fr_FR`) and writes `text/fr_FR.php` immediately, as a skeleton: every key the native language has, in its order, with empty values. That is the file `/_editor`'s and `/_admin`'s Text panels then list as work to do — values are left empty on purpose, because a key filled with the native language's own sentence reads as finished and is what ends up shipping.

The new language is **not switched on** by that. Its keys are empty, so activating it would put a language that serves blank pages one Save away. Translate it under **Text** (or import it under **Translations**), then tick it here and save. An existing `text/<locale>.php` is never overwritten — adding a language that already has one just reports its key count.

The native language can only be one of the languages currently ticked. Both keys are therefore saved together — saving them separately would allow a moment in which `config.php` names a native language the project does not have.

### Elements search indexes

**Create searchindex** is a separate action next to **Save**. It does not save
the form and does not change `config.php`; every click recreates all valid types
listed under `/nino/elements/index`. The confirmation reports the number of
index files and distinct Elements written. With no valid configuration, it
reports that no search indexes are configured.

The index definition and `\Nino\Modules\Search` activation are deliberate,
manual `config.php` work; see [Elements Search Index in the Developer
Manual](development.md#elements-search-index). Activation keeps configured
types current after successful Element writes, but does not create the initial
files. Missing or malformed indexes are never repaired by a search request, so
use this button after adding the configuration, changing indexed fields,
editing Element files by hand, or diagnosing a failed index write.

Three keys this area used to edit are deliberately gone:

| Key | Edit it instead under |
|---|---|
| `/nino/http/routes` | **Routes** |
| `/nino/html/navs` | **Navigations** |
| `/nino/html/assets` | by hand in `config.php`, or by re-running `/_install`'s theme step |

Asset bundles stay a deliberate file edit because their **order** is load-bearing for the CSS cascade, which a form does not show. The other two have had real editors for a while, and a second, unvalidated way to write the same data is a way to corrupt it.

### The page cache

With **Cache rendered pages** on, `Modules\Cache` stores a finished page and serves it again without rendering. It is off in a fresh project on purpose — switch it on once the site is finished, not while building it.

Never cached, whatever the configuration says: anything but a `GET`, anything carrying query vars, any uri under `/_` or `/.`, any response that is not a plain 200, and every request from a signed-in visitor. **Never cache these** adds your own exclusions, one uri per line; a trailing `/*` covers a subtree, so `/blog/*` matches `/blog` and everything below it.

Two values in a stored page belong to whoever asks for it rather than to whoever it was rendered for, and both are re-stamped on the way out: the `[csrf]` token, so every visitor submits with their own, and the `[jstext]` nonce, so it stays unguessable and keeps matching the `Content-Security-Policy` header of that one response.

Any save through `/_admin`, `/_editor`, `/_theme`, `/_templates` or `/_install` drops the whole cache immediately — a single textfill or appearance change can change every page, so there is no smaller useful unit. The lifetime above only bounds how long a page survives without any edit at all.

Responses carry `X-Nino-Cache: hit` or `miss`, which is the quickest way to see whether the cache is doing anything.

In production, `/nino/error/display` must be off.

## Recommended Workflow

> **WIP:** This section will be supplemented with details on modules, asset bundles, and advanced route configuration.

1. Create the required structure under **Element Types**, **Text**, **Routes**, and **Images**.
2. Maintain or correct full texts and elements directly in `/_admin`.
3. Compose page templates from complete HTML and template sections via [`/_templates`](_templates.md); use the HTML+ escape hatch or code for lower-level structure work, then check the result in the browser.
4. Check dashboard and template scans for missing definitions.
5. Create suitable accounts with the smallest possible permissions under **Users**.
6. Test the released content and permissions in `/_editor`.
7. Check frontend, languages, forms, and responsive display.
8. Commit the resulting project files in Git.

## If Something Does Not Work

| Problem | Check |
|---|---|
| Login locked after several attempts | Wait one hour; the lock is project-wide. |
| Saving fails | Check write permissions for the affected file or directory. |
| Template missing in **Routes** | Only existing files `templates/page-*.tpl` are offered. |
| Page cannot be saved in Template Builder | Reload after an external edit, check unique section IDs and unmatched `<section>` tags; see [`/_templates` Operation](_templates.md). |
| Texts or images missing in scan | Dynamic keys and images are not reliably statically recognized. |
| Backup list is empty | First log in with an editor account and check if backups are enabled and write permissions exist. |
| Search returns no Elements | Check `\Nino\Modules\Search` and `/nino/elements/index` in `config.php`, then press **Create searchindex** under **Config**. |
| Website does not work after **Config** | Restore last Git state or backup and check JSON and key structure. |

## Next Steps

- **[Design Manual](design.md):** Frontend, design system, and CSS **(WIP)**
- [`/_templates` Operation](_templates.md) explains section-first page composition in Alpha status.
- [`/_editor` Operation](_editor.md) explains daily, permission-controlled content maintenance.
- [Developer Manual](development.md) describes APIs, modules, and direct work on project files.
- [Deployment](deployment.md) covers access protection, backups, and secure production operation.
