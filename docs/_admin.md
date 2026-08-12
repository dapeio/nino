# `/_admin` — Operation Manual

**Language:** English · [Deutsch](_admin.de.md)

**Last updated:** August 11, 2026 · **Nino version:** 0.11.0-beta.1

This manual explains the complete technical and content project management under `/_admin`. If you instead only want to maintain released content on a daily basis, read the [`/_editor` Operation Manual](_editor.md); fast section-based page composition is described in the [`/_templates` Operation](_templates.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

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
| Routes | Manage page routes, templates, and navigation | Maintain page texts |
| Page templates | Link to the section-first `/_templates` Template Builder | No access |
| Users | Create, delete, and technically manage accounts | Manage profile data and released permissions |
| Configuration | Edit selected technical values | No access |
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
- remove `_admin/` and `_templates/` from delivery if the interfaces are not needed in live operation.

`/_templates` uses the same password, lock status, and session as `/_admin`. The **Template Builder** link in the header opens the section-first Alpha tool. Without `_admin/`, the standalone builder cannot be used.

The password can be rehashed outside the installer with `php _admin/Admin.php <password>`. The output hash replaces `PASSWORD_HASH` in `_admin/Admin.php`. Perform this process only in a protected local environment; a password entered as a command-line argument may be visible in shell history or process list.

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

Depending on the type, additional properties are available:

- **per translation** stores a separate value for each language;
- **Required field** makes the field mandatory;
- **Rich text** activates bold, italic, highlight, inline-code, and link formatting for the `string` type;
- fixed values limit a text field to a predefined selection;
- width and height determine the target dimensions for images;
- a unit or suffix adds, for example, `€`, `km`, or `%` in the input.

When switching between global and language-dependent fields, Nino migrates existing values. Still, check the result in each language. Saving a type model does not delete existing entries; however, removed fields no longer appear in `/_editor`.

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

The **Routes** area manages the page routes created via `/_install` and the navigation. There is no separate page list: the list you see is derived from `/nino/http/routes` and the `/webpage<uri>/*` text keys on every request, so a route written here, in `/_install`, or by hand in `config.php` is the same page everywhere.

A page has two different URIs:

- **Element URI** is the stable internal identity to which page texts like `/webpage<uri>/title` are attached;
- **HTTP URI** is the actually reachable path in the browser.

Thus, a page can internally be called `/about` and publicly be reachable under `/ueber-uns`, for example.

When creating or editing, you also determine:

- an existing template from `templates/page-*.tpl`;
- an HTTP status code;
- navigation name, page title, and description for each active language;
- one checkbox per navigation registered in `/nino/html/navs`.

Membership is stored on the page's own route as `'navs' => [ 'main' => 1, ... ]`, where the value is a priority (lower first). A membership added here starts behind everything already in that menu; a priority you tuned by hand is never reset by a later save. A route added to `config.php` by hand joins a menu the same way, without any tool, and is rendered by `[navigation nav="main"]`.

The arrows in the page list swap two page routes in `config.php`; every other route keeps its own slot. Menu entries of equal priority follow route order. Reserved paths such as `/_admin`, `/_editor`, `/_install`, and `/_templates` cannot be used as public pages.

Some routes select their template at runtime. In this case, `/_admin` shows the existing route body and leaves it unchanged when saving.

Deleting a page removes its route. Its page texts and its template file remain — nothing on disk is deleted for you.

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

The **Config** area edits a deliberately limited selection from `config.php` as JSON:

> **Note:** The following sections are not yet fully documented:
> - **Asset-Bundles** (`/nino/html/assets`) — Configuration of CSS/JS bundles.
> - **Routing Table** (`/nino/http/routes`) — Complete route management.
> - **Modules** — Integration and management of optional modules (see [Developer Manual](development.md)).

| Key | Expected Value |
|---|---|
| `/nino/error/log` | `true` or `false` |
| `/nino/error/display` | `true` or `false` |
| `/nino/locales/native` | language code as string |
| `/nino/locales/available` | list of language codes |
| `/nino/html/assets` | asset bundles as object |
| `/nino/http/routes` | complete routing table |

Nino checks JSON syntax and basic type but not every technical dependency. Errors in routes, languages, or asset bundles can make the website or management areas inaccessible. For normal page changes, therefore use **Routes** and for image slots **Images**.

In production, `/nino/error/display` must be `false`.

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
| Website does not work after **Config** | Restore last Git state or backup and check JSON and key structure. |

## Next Steps

- **[Design Manual](design.md):** Frontend, design system, and CSS **(WIP)**
- [`/_templates` Operation](_templates.md) explains section-first page composition in Alpha status.
- [`/_editor` Operation](_editor.md) explains daily, permission-controlled content maintenance.
- [Developer Manual](development.md) describes APIs, modules, and direct work on project files.
- [Deployment](deployment.md) covers access protection, backups, and secure production operation.
