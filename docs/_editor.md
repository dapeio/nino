# `/_editor` — User Manual

**Language:** English · [Deutsch](_editor.de.md)

**Last updated:** August 8, 2026 · **Nino version:** 0.12.0-beta

This manual explains the daily work with released texts, elements, images, users, and operational data under `/_editor`. If you need full technical and content access, read the [`/_admin` Operation Manual](_admin.md); the structural editing of templates is described in the [`/_templates` Operation](_templates.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Getting Started](getting-started.md) · [`/_install` Reference](_install.md) · [`/_admin` Operation](_admin.md) · [`/_templates` Operation](_templates.md) · [`/_editor` Operation](_editor.md) · [`/_design` Operation](_design.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

**Security:** Which areas you see and are allowed to use depends on the permissions of your account. A missing menu item is therefore often intentional and not a display error.

## Login and Interface

Open `https://your-domain.example/_editor` and log in with your email address and password. Accounts are created during setup or later under `/_admin`.

On the login page, you can choose the language of the interface. After logging in, you will find at the top or side:

- your email address;
- **Logout**;
- a gear icon for interface language and light or dark color scheme;
- the areas released for your account.

The last selected language remains for the session and is also used by the language switches in **Texts** and **Elements**.

After repeated incorrect credentials, Nino may temporarily lock the account. Contact an administrator if you can no longer log in despite the correct password.

## Permissions and Visible Areas

`/_editor` hides areas without appropriate permissions. The API checks the same permissions again when saving or loading.

| Area | Permission |
|---|---|
| Elements | `/_editor/elements/manage` |
| Texts | `/_editor/text/manage` |
| Images | `/_editor/images/manage` |
| Requests | `/_editor/submissions/view` |
| Newsletter | `/_editor/newsletter/manage` |
| Log | `/_editor/logs/view` |
| Other Users and Permissions | `/_editor/users/manage` |
| All Areas | `/*` |

Each account can independently edit its own profile under **Users**.

## Dashboard

The **Dashboard** shows the operational status relevant to you. Depending on your permissions, this includes:

- number of elements by type;
- new or saved requests;
- newsletter subscribers;
- date of the last backup;
- last logged activities.

The tiles lead to the respective areas. Values from non-released areas are not displayed.

## Global and Translated Content

Nino distinguishes between two types of fields:

- **Global** applies identically in all languages, such as price, date, or an internal identifier.
- **Translation** has a separate value for each language, such as title or description.

The developer defines this assignment in `/_admin`. In `/_editor`, you only edit the resulting fields.

You can switch between languages within a form. Unsaved inputs from the previously selected language remain in the browser during this time. After all changes, click **Save** and wait for confirmation before leaving the area or reloading the page.

## Maintain Texts

The **Texts** area contains individual textfills such as headings, descriptions, contact details, or labels. The keys are grouped by their first path segment; you edit one complete group at a time.

![Global and translated text values in `/_editor`](assets/screenshots/editor-text.webp)

1. Open **Texts**.
2. Select the appropriate group.
3. Edit global values and select the desired language for translated values.
4. For formatted fields, use only the offered functions for bold, italic, highlighting, inline code, and links.
5. Click **Save** and wait for the **Saved** message.

Character counters show the maximum length intended by the developer. A text key that does not appear here may be intentionally hidden in `/_admin` or technically reserved.

The project-wide JSON translation workflow is intentionally located under **Translations** in `/_admin`. It combines native Text and Elements content; `/_editor` remains focused on changing individual released values.

## Maintain Elements

Elements are recurring structured content such as team members, services, events, or references. The developer defines the available types and fields in `/_admin`.

![Editing an approved element in `/_editor`](assets/screenshots/editor-elements.webp)

### Create a new element

1. Open **Elements** and choose the required type.
2. Select **New element**.
3. Enter a unique URI using lowercase letters, digits, hyphens, and underscores, for example `open-workshops`.
4. Complete all required fields.
5. Maintain global values and the required translations.
6. Save the element.

The URI is the permanent technical identifier. Keep it short and meaningful; it cannot be changed through the form later.

### Edit an existing element

Open the type and then the required entry. Global fields are stored once, while translated fields are stored per language. Depending on the model, inputs may appear as text, number, date, selection, yes/no value, list, or image.

A field that references other elements appears either as a single selection list or, when the developer allowed several, as a list of the entries you have chosen. Add one through the search field below the list, order the list with the ↑ and ↓ buttons, and take an entry back out with ✕. The order matters: it is the order the website renders the referenced entries in. Where the developer set a maximum, the count above the search field says how many of it are used, and nothing more is offered once it is reached. An entry whose target has been deleted is shown as *missing* rather than dropped, so nothing disappears without you seeing it.

An image field becomes available only after a new element has been saved once. Nino then processes the uploaded file to the dimensions specified by the developer.

**Delete** removes the element in every language. Images used exclusively by its image fields are deleted as well. Only a backup can restore the operation.

## Replace Images

The **Images** area contains fixed image slots defined by the developer in `/_admin`. They are grouped by URI area and show their label, shortcode, and target dimensions.

1. Open the appropriate group.
2. Choose an image file for the required slot.
3. Check the specified target dimensions.
4. Start the upload and wait for **Saved**.

Nino validates and processes the file. Invalid or oversized images are rejected. A successful upload replaces the current image immediately; there is no separate publishing step.

Image slots cannot be created or deleted in `/_editor`. This is done in `/_admin` so that templates and content use the same technical structure.

## User Profile and Accounts

Under **Users**, every account can change its own email address and password. Changes to your own account require the current password. A new password must contain at least eight characters; leaving the field empty keeps the existing password.

**Log out everywhere** terminates all active sessions for the selected account. Use it after a password has been exposed, a device has been lost, or unauthorized access is suspected.

### Manage other users

Accounts with `/_editor/users/manage` can also see other existing users. They can change an email address or password, terminate sessions, grant known permissions, and assign full access `/*`.

Users cannot extend their own permissions. Creating and deleting accounts remains a task for `/_admin`.

Grant only the permissions needed for the actual role. Full access should remain limited to a small number of responsible people.

## Manage Submissions

**Submissions** lists stored form entries when the form module is used and the account has read permission.

The list shows date, category, sender, and message. Long content can be expanded and collapsed. Selecting an email address opens the local mail client; Nino does not send a reply automatically.

**Export as CSV** downloads the current entries. The view is deliberately read-only: submissions cannot be changed or deleted here.

## Manage Newsletter Subscribers

The **Newsletter** area shows stored subscriptions. You can open individual email addresses, copy all addresses as a BCC line, export the list as CSV, or delete a subscription after confirmation.

Before sending, verify that the mail client actually uses BCC so recipients cannot see one another’s addresses.

A deleted address is additionally recorded as removed. This prevents restoring an older backup from silently undoing the unsubscribe operation.

## View the Log

**Log** shows the `/_editor` activity log, including logins and successful changes. Entries are retained for 14 days and are read-only.

This is not the PHP error log. Error logging is configured separately through `config.php` or `/_admin`.

## Saving, Backups, and Publishing

Changes in `/_editor` are written directly to the file-based project data. There is no draft state or separate publish button. Check changes immediately in the frontend and in every affected language.

By default, Nino creates an encrypted backup on the first authenticated editor access of each day and retains daily backups for 14 days. Restoration is performed in `/_admin`.

These backups protect against many editing mistakes but do not replace an external backup of the complete project. Images, configuration, texts, elements, and operational data belong in the same backup plan.

## Recommended Roles

| Role | Suggested permissions |
| --- | --- |
| Editor | Texts, elements, and optionally images |
| Communications | Submissions and newsletter; optionally texts |
| Site management | Content, log, and user management |
| Technical owner | Full access `/*`; only for a few accounts |

Start with narrow permissions and expand them only for a concrete need.

## Troubleshooting

| Problem | Check |
| --- | --- |
| An area is missing | Check the account permissions under **Users** or in `/_admin`. |
| Saving fails | Check required fields, character limits, and server write permissions. |
| Image upload is unavailable | Save a new element once before uploading its image. |
| An image is rejected | Check format, file size, and target dimensions; contact the developer if necessary. |
| Changes do not appear in the frontend | Check the selected language, entry, and browser cache. |
| Login fails | Check credentials and a possible temporary lock; reset the account through `/_admin` if necessary. |
| No backup date appears | Ask a developer to check backups and write permissions. |

## Next Steps

- [`/_admin` Operation](_admin.md) explains full project administration.
- [`/_templates` Operation](_templates.md) describes the optional template builder in Alpha status.
- [Getting Started](getting-started.md) guides through the initial setup.
- [Deployment](deployment.md) describes web server configuration, security, and go-live.
