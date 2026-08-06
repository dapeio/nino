# Nino — Admin Handbook
*[Deutsch](_admin.de.md)*

**Links:**
[README](../README.md) · [Design-Handbook](design.md) · [Developer-Handbook](development.md) · [_editor-Handbook](_editor.md) · [_install-Handbook](_install.md) · [_templates-Handbook](_templates.md) · [Security Policy](../SECURITY.md) · [Changelog](../CHANGELOG.md)

## Work in progress.

One module is documented ahead of the rest below, since it interacts directly with `/_install`'s own Webpages step.

### Pages

Create, edit and delete the site's actual page routes without hand-editing `/nino/http/routes` as raw json (`_admin`'s Config module still covers everything this doesn't - routes this doesn't manage, or any other whitelisted "soft" config value). A friendlier continuation of `/_install`'s Webpages step for once `_install` has been deleted: the template `<select>` only ever offers a `templates/page-*.tpl` file that already exists on disk - no copying, no library units, just wiring an existing template up to a uri.

Each entry has:

- **Element URI** - a stable identifier, yours to pick, that never has to look like a real path. It namespaces this entry's own `/webpage<uri>/*` text meta and becomes the route's own `uri` data field.
- **Http URI** - the real, reachable browser path. This alone drives the `/nino/http/routes` array key - see `docs/_install.md`'s Webpages section for the full reasoning (`\Nino\Http::requestRoute()` matches by exact key, not by scanning for a route whose own `uri` field matches).
- **Template** - restricted to whatever `templates/page-*.tpl` files exist on disk.
- **Status Code** - defaults to 200; set eg. 404 for a not-found-style page.
- **Nav** - only shown once the Navigation module is active; regenerates `[[/website/navigation/main]]` the same way `/_install`'s Webpages step does.
- **Name/Title/Description**, per active locale - written to `/webpage<uri>/*` text meta, same fallback-to-a-generic-placeholder behavior as Webpages.

Both uris are validated and deduped independently. Deleting a page removes its route but leaves its `/webpage<uri>/*` text meta in place - same additive-only rule every apply/save in this codebase follows.

The list's own ↑/↓ buttons swap an entry with its neighbor and re-persist the new order - that order is exactly what `[[/website/navigation/main]]` gets generated in, so reordering the list is how you reorder the generated main menu once `/_install` (whose own Webpages step has the same list-plus-editor shape and the same ↑/↓ buttons, for the same reason) is no longer around.

The persisted list lives at `config.php`'s `/nino/install/webpages` key - the exact same array `/_install`'s Webpages step manages, kept in sync since either tool writes the whole shape (`{ uri, httpUri, template, libraryKey, nav, statusCode, body, text }`) back to it. That means the two tools can coexist: editing a page here and later revisiting `/_install`'s Webpages step (before deleting it) shows the current, real list, not something stale.

Two of those fields belong to the other tool and are only ever carried through here, never edited:

- **`libraryKey`** - which `_install/library/pages` unit the entry started from. It means nothing once `_install` is gone, but a save made here keeps it, so revisiting the Webpages step beforehand can still re-apply that unit.
- **`body`** - the route body verbatim. Normally it's just `[template /templates/<template>]` and the template `<select>` decides it. A library unit can ship something more than that, though - `/_install`'s "legal" unit resolves its template file per locale via `[[/nino/http/response/locale]]` - and a `<select>` of concrete filenames has no way to spell that. For those entries the select is disabled and shows the body instead; saving keeps the body as it stands rather than flattening the page onto whichever single locale's file happened to be preselected.

`statusCode` matters for the same reason: it lives in the route otherwise, where this module couldn't see it, and a page created as eg. a 404 would silently come back as a 200 the first time it was saved here.
