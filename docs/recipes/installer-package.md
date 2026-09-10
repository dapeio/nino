# Recipe: Add an installer module package

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Features](../features.md)

One of the seven extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


An installer package (a *unit*) makes a module selectable in the setup
wizard. It can activate a runtime class, copy templates/assets/element types,
merge text, add owned routes, and supply configuration defaults.

It does not execute as the runtime module. The unit is a directory named
`install/` beside the module's class file, and the wizard finds it there:
`\Nino\Install\Setup::units()` scans `_nino/Nino/Modules/<Name>/install/`
(Nino's own optional modules), then the whole app dir (`app/`, or
`NINO_APP_DIR`) up to four levels deep, and
`_admin/install/library/modules/<key>/` for a unit that has no runtime class.
Nothing in `_admin/install/` lists a module. A runtime class must already be
shipped at its valid autoload path - `_nino/Nino/Modules/<Name>/` for a kernel
module, `app/<Vendor>/...` (or below `NINO_APP_DIR`) for a project's own.

The wizard does not scan `features/`. A feature's `install/` has the same
shape, but `\Nino\Features::activate()` applies it - add-only, through the
same `\Nino\Features::applyUnit()` the wizard calls with overwrite on - when
the feature is switched on in the workbench: the [feature recipe](feature.md).
The catalogue's `Newsletter` and `Search` ([dapeio/nino-features](https://github.com/dapeio/nino-features)) are features and are not offered here.

## Directory shape

```text
app/Project/Catalog/Catalog/
├── Catalog.php
└── install/
    ├── manifest.php
    ├── templates/
    │   └── section-catalog.tpl
    ├── text/
    │   ├── global.php
    │   ├── en_US.php
    │   └── de_DE.php
    ├── catalog-items.php
    └── assets/
        └── catalog/
            ├── catalog.css
            └── catalog.js
```

Nino's own optional modules keep theirs at `_nino/Nino/Modules/<Name>/install/`
the same way, and keep their keys: they are scanned before the app dir. The
unit's key - what the picker posts and what `requiresModules` lists - is the
manifest's `key` or, without one, the module directory's
lowercased name (`Catalog` -> `catalog`). It MUST be a slug
(`/^[a-z][a-z0-9-]*$/`) and unique across every unit: the first unit to claim a
key keeps it, a later one is dropped with a warning naming both directories.
`Modules\Form` declares `'key' => 'forms'` because the page library has always
required `forms`.

## Complete manifest example

```php
<?php
declare(strict_types=1);

return [
	'key' => 'catalog',
	'label' => 'Catalog',
	'moduleClass' => '\\Project\\Catalog\\Catalog',
	'requiresModules' => [],
	'templates' => [
		'section-catalog.tpl',
	],
	'files' => [
		'assets/catalog',
	],
	'elementTypes' => [
		'catalog-items.php',
	],
	'blacklist' => [
		'/project/catalog/internal-label',
	],
	'config' => [
		'/project/catalog' => [
			'pageSize' => 12,
		],
	],
];
```

Supported unit keys in the current installer:

| Key | Meaning |
| --- | --- |
| `key` | Unit key; default is the module directory's lowercased name |
| `label` | Picker label |
| `preset` | `true` pre-checks the unit on a project that has never applied Setup |
| `moduleClass` | Full runtime class added to `/nino/modules` |
| `requiresModules` | Other units' keys |
| `routes` | Installer-owned route map |
| `templates` | Files copied from the unit's `templates/` |
| `files` | Files/directories copied to the same project-relative path |
| `elementTypes` | Unit-root files copied into project `elements/` |
| `blacklist` | Text keys merged into `text/blacklist.php` |
| `config` | Top-level defaults written only when absent |
| `active` | Special always-active picker state; do not use for normal choices |

Do not add decorative manifest keys and assume the installer uses them. If new
metadata is required, implement and test its consumer in `Install.php` and the
frontend.

## Requirements

`requiresModules` contains unit keys, not PHP class names:

```php
'requiresModules' => [ 'forms', 'navigation' ],
```

The Setup step resolves module requirements transitively. A requirement no
unit answers to is skipped with a warning, never applied. Keep the dependency
graph small and acyclic even though the resolver terminates cycles. A cycle
usually indicates mixed responsibilities.

A page unit that requires this module names the same installer slug:

```php
'requiresModules' => [ 'catalog' ],
```

Test installing both through the module picker and through a page that
auto-requires it. The current Webpages auto-require path activates the class and
applies module templates, text, blacklist, and config; it does not apply a
module manifest's own `requiresModules`, `routes`, `files`, or `elementTypes`.
List the complete required-module closure on the page when needed. If the page
depends on the other resources, either put a supported resource in the page
unit, ensure it is shipped independently, or extend `Install.php` symmetrically
and test both application paths. Do not assume the two paths are identical.

## Templates and locale gating

A numeric list copies every listed template:

```php
'templates' => [
	'section-catalog.tpl',
	'mail-catalog.tpl',
],
```

A locale-keyed entry is copied only when that locale is selected:

```php
'templates' => [
	'en_US' => 'page-catalog-en.tpl',
	'de_DE' => 'page-catalog-de.tpl',
],
```

PHP array keys are unique, so the current locale-keyed form can represent one
file per locale. Prefer locale-independent template structure and localized
textfills when possible.

The target is project `templates/<filename>`. Template filenames in the
manifest are source names relative to the unit's `templates/` directory.

## Files and element types

`files` preserves the path relative to the unit:

```php
'files' => [ 'assets/catalog' ],
```

The directory above is copied to project `assets/catalog`. Prefer listing an
owned directory over a broad shared directory. Do not copy `assets`, `_nino`,
or another broad tree when the module owns only one subdirectory.

`elementTypes` has different behavior:

```php
'elementTypes' => [ 'catalog-items.php' ],
```

The source is
`app/Project/Catalog/Catalog/install/catalog-items.php` and the destination is
`elements/catalog-items.php`. Keep these source filenames flat and validate
them in tests.

## Text fragments

Text files return bracketed textfill keys:

`install/text/global.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/project/catalog/api-uri]]' => '/api/catalog',
];
```

`install/text/en_US.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/project/catalog/title]]' => 'Catalog',
	'[[/project/catalog/empty]]' => 'No entries are available.',
];
```

`install/text/de_DE.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/project/catalog/title]]' => 'Katalog',
	'[[/project/catalog/empty]]' => 'Es sind keine Einträge verfügbar.',
];
```

- `text/global.php` is merged into live `text/global.php`.
- `text/<locale>.php` is merged only for selected locales.
- Later applied units win a duplicate key.
- A re-apply may update a shipped key.
- Deselecting the module does not delete copied text.
- Technical/design values that editors should not change belong in
  `blacklist`.

Choose stable, namespaced fill paths. Do not use a translated label as part of
a key.

## Routes and ownership

Most runtime modules SHOULD register their technical routes in `init()` and omit
`routes` from the installer manifest. This keeps feature behavior with the
feature.

Use manifest routes when Setup owns the persisted route choice. Never put an
ordinary visitor page into a module unit merely because it uses the module; use
a page unit.

The Setup step:

- replaces the selected locale and optional-module sets;
- always retains the structural core modules;
- removes and rebuilds only routes owned by base/module library manifests;
- leaves foreign/project/page routes intact;
- copies/merges selected unit files and text;
- does not delete copied templates, element types, assets, or text when a unit
  is later deselected;
- and writes `config` defaults only where the key is absent.

Therefore an installer package MUST NOT promise automatic uninstall. Deleting
potentially edited project files would be destructive.

## Installer package tests

Extend `tests/install-smoke.php`. Test:

- `Setup::units()` finds the unit under its key, and the unit appears in the
  picker with label and requirements;
- direct selection activates `moduleClass`;
- requirements are selected transitively;
- reapplying the complete selection does not duplicate modules;
- templates, files, element types, global text, locale text, and blacklist land
  in the exact target paths;
- unselected locale files do not land;
- config defaults are created but an existing custom value survives reapply;
- deselection updates the module list but does not delete copied project files;
- unrelated routes and config survive;
- and any page requiring the module produces a working installed project.

Run:

```bash
php -l app/Project/Catalog/Catalog/install/manifest.php
php -l app/Project/Catalog/Catalog/install/text/global.php
php -l app/Project/Catalog/Catalog/install/text/en_US.php
php -l app/Project/Catalog/Catalog/install/text/de_DE.php
php -l app/Project/Catalog/Catalog/install/catalog-items.php
php tests/install-smoke.php
node tests/install-script-js-smoke.js
```
