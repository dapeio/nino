# Features — Manual and Contract

**Language:** English · [Deutsch](features.de.md)

**Last updated:** September 7, 2026 · **Nino version:** 1.0.0-beta

This manual explains what a feature is, how an operator switches one on in the workbench's **Features** panel, and what a developer has to deliver for a directory to be one: the manifest, the settings schema, the lifecycle and the tests. The kernel contract behind it is `\Nino\Features` in `_nino/Nino/Features/Features.php`; `tests/features-smoke.php` checks it against the fixtures under `tests/fixtures/features/`. To build a feature of your own step by step, follow the [feature recipe](recipes/feature.md).

**Additional Links:**
[README](../README.md) · [Concepts](concepts.md) · [Developer Manual](development.md) · [Recipes](recipes/README.md) · [Getting Started](getting-started.md) · [Setup Wizard](setup.md) · [`/_admin` Workbench](_admin.md) · [Templates Panel](templates.md) · [Design Panel](appearance.md) · [Features](features.md) · [Deployment](deployment.md) · [Security Policy](https://github.com/dapeio/nino/blob/main/SECURITY.md) · [Changelog](https://github.com/dapeio/nino/blob/main/CHANGELOG.md)

## What a Feature Is

A feature is an installable package: one directory below `features/` that brings a module - its runtime class, a workbench panel when it has one, an install unit with templates and texts, and a manifest `feature.php` that says what it is, which Nino version it was written for and which settings it offers. A feature is not installed: it is dropped into the directory - as a copy, later a download - and switched on in the workbench. A checkout ships no feature at all; `features/` holds nothing but its `.htaccess`. The features Nino publishes - `Newsletter` and `Search` among them - live in the catalogue repository [dapeio/nino-features](https://github.com/dapeio/nino-features), one directory each below `features/` there with its manifest, its tests, a README and a changelog, and arrive in a project by copying that directory into its own `features/`.

A Nino project has three kinds of modules, and the difference is who owns the directory:

| Kind | Where | Who switches it on |
|---|---|---|
| **Kernel module** | `_nino/Nino/Modules/<Name>/` - the always-on ones (`Assets`, `Cache`, `Csrf`, `Elements`, `Images`, `Jstext`, `Template`) and the optional ones (`Form`, `Navigation`, `Localepicker`, `Design`, `Templates`, `Maintenance`) | the setup wizard, or by hand in `/nino/modules` |
| **Feature** | `features/<Name>/`, one directory per feature, with `feature.php` | the Features panel |
| **Project module** | `app/<Vendor>/…` under a namespace of its own | by hand in `/nino/modules` |

"Module" remains the technical term for the class listed in `/nino/modules` whose `init()` the kernel calls; "feature" is the package that delivers such a class. A kernel module belongs to Nino and is replaced wholesale with `_nino/`. A project module belongs to the project and carries its own namespace. A feature sits between the two: it comes from outside, but the project holds it - with a version number, with settings in `config.php` and with files under `data/` that belong to the project, not to the feature.

**Why?** Before this split, Nino's optional modules lived under `app/Nino/Modules/`, beside the project's own classes, and an update had to compare them one directory at a time. Now `app/` belongs to the project alone, `_nino/` is replaceable wholesale again, and a package a project takes on has a place of its own with a contract of its own.

## The `features/` Directory

Every feature is exactly one directory, named after the module class it brings - take the catalogue's Newsletter feature, once copied in: `features/Newsletter/Newsletter.php` is `\Nino\Modules\Newsletter`, `features/Newsletter/Admin/Admin.php` is `\Nino\Modules\Newsletter\Admin`. The directory name must be a class name segment (`Newsletter`, not `newsletter`), because it *is* the class name - a manifest cannot declare a different class, see [The Manifest](#the-manifest-featurephp).

The autoloader resolves `Nino\Modules\*` as a merged view over four roots, in this order: `_nino/`, `_admin/`, `features/`, `app/`. Below the features root the `Nino/Modules/` prefix is the directory itself - `features/<Name>/<Name>.php`, not `features/Nino/Modules/<Name>/`. The order is what each root may do to the others: `_nino/` first, so a shipped module can never be shadowed; `_admin/` before `features/`, so a feature cannot replace a workbench screen; `features/` before `app/`, so a project cannot replace an installed feature by dropping a file next to its own modules; `app/` last, which can only add. The details are in the [Developer Manual](development.md#directory-and-autoloading).

`NINO_FEATURES_DIR` relocates the directory, the way `NINO_APP_DIR` does for `app/`. The constant is defined before `_nino/Nino.php` is loaded - in every entry point, that is `index.php`, `_admin/index.php` and `_admin/recovery.php`, all three of which carry the line commented out. The autoloader and `\Nino\Features::dir()` read the same constant, so a relocated directory serves the classes too. It is replaced as a whole: a project that points it elsewhere takes its features along, or loses them without a word.

**Security:** Everything in `features/` is server-side source - classes, panels, mail and page templates, text files. `features/.htaccess` denies the tree the way `app/.htaccess` denies its own, and `router.php` does the same for the built-in server. A web server that does not read `.htaccess` needs the equivalent rule; see [Deployment](deployment.md#nginx-and-other-web-servers). A feature's browser-facing assets are read off disk and bundled into `public/.cache/` or `_admin/.cache/` like everyone else's - nothing in the tree is ever requested directly. For the website, the class adds them in `init()` to the project's bundles: `\Nino\Html::addAsset( $appData, '/.cache/style.css', '/features/<Name>/assets/site.css' )`, and the same for `/.cache/script.js`; `\Nino\Filesystem::path()` resolves `/features/...` against the features directory, relocated or not. A panel names its workbench assets through `assets()` instead, see [The Panel](#the-panel).

## The Features Panel

The panel sits in the workbench's System group and asks for `/_admin/features/manage` on every action - a developer's permission. It sorts every directory below `features/` that carries a valid manifest into three tabs - **Available**, **Inactive**, **Active** - each labelled with a count. Wherever a feature sits, it shows name and description in the interface language, the version from the manifest, and whatever stands in the way of an activation: a Nino version the feature was not written for, a PHP extension that is missing, a required feature that is not in the directory. The names come from the manifest itself, not from text fills, because the fills of a feature that is not active are not loaded.

The panel's four actions are `features/list`, `features/activate`, `features/deactivate` and `features/settings`; behind them stand `\Nino\Features::all()`, `activate()`, `deactivate()` and `saveSettings()`:

- **Activate** switches a feature on. Required features are activated first, the feature's install unit is applied without overwriting anything your project already has, the class is listed in `/nino/modules` and the version recorded under `/nino/features`.
- **Deactivate** removes the class from the list - and nothing else. Settings, data and copied templates stay, and switching the feature back on finds everything as it was. A feature another active feature requires cannot be deactivated.
- **Update** is offered for an active feature whose manifest names a different version than the recorded one - after its directory has been replaced with a new release. The update is the same action as activating: the unit adds what is new, and the module gets to migrate its own data before the new version is recorded.
- **Settings** shows the form the manifest describes and stores it under `/nino/features` in `config.php`. Every setting is validated before any is written; an error names the setting, and nothing is saved.

The **Available** tab is the catalogue: what it offers that this installation does not already have current. Above the tabs, an action bar's **Refresh catalogue** button posts `features/catalogue`, behind which stand `\Nino\Catalogue::fetch()` and `offers()` (`features/install` stands behind `install()`), and a status line names when the catalogue was last read, or that it has not been read yet. Nothing is fetched on its own: `features/list` answers whatever `\Nino\Catalogue::cached()` last left under `data/catalogue.php`, so Available fills the moment the panel opens without a request of its own; only Refresh calls `fetch()` again. Loaded, Available offers **Install** for a feature not in the directory, **Update** for one there in an older version, and greys out, with what it asks for, one no version of which fits - a feature already current does not appear here at all. Installing downloads the archive, checks it against the signed catalogue, and puts the directory in place; an update replaces the directory and, for an active feature, applies the update in the same step. The offers themselves are always recomputed against the features on disk now, so an install is reflected on Available without a new fetch either. Where `features/` is not writable, the tab links the archive instead, to unpack by hand. See [The Catalogue](#the-catalogue).

**Important:** A panel a feature brings appears only with the next load of the workbench after activating, and goes only then after deactivating - the rail is built once per page load from the panel registry. Reload the page. It lands in the rail's own **Features** group regardless of what its own `nav()` names - see [Panels of the Workbench](development.md#panels-of-the-workbench) - and offers its permission on the roles tab of the Users panel under that same group label; the **Editor** role the wizard wrote before the activation does not receive it by itself - grant it there.

## The Manifest `feature.php`

The manifest lives beside the class file and returns an array. The example is the feature of the [feature recipe](recipes/feature.md), a catalogue with a public JSON endpoint and a panel:

```php
<?php
// features/Catalog/feature.php - what the Features panel reads. The class is
// not declared here: features/Catalog/ can only ever serve \Nino\Modules\Catalog.
return [
	'key'					=> 'catalog',
	'name'				=> [ 'en_US' => 'Catalog', 'de_DE' => 'Katalog' ],
	'description'	=> [
		'en_US' => 'A product catalogue with a public JSON endpoint and a workbench panel.',
		'de_DE' => 'Ein Produktkatalog mit öffentlichem JSON-Endpunkt und einem Panel der Workbench.',
	],
	'version'			=> '1.1.0',
	'nino'				=> '^1.0',
	'php'					=> [ 'ext' => [ 'json' ] ],
	'requires'		=> [],
	// The files under data/ this feature owns - what a backup carries
	'data'				=> [ '/data/catalog.php' ],
	'settings'		=> [
		'title'		=> [ 'type' => 'string', 'label' => [ 'en_US' => 'Title', 'de_DE' => 'Titel' ], 'required' => true, 'maxlength' => 60, 'default' => 'Catalog' ],
		'pageSize'	=> [ 'type' => 'int', 'label' => 'Items per page', 'min' => 1, 'max' => 100, 'unit' => 'items', 'default' => 12 ],
		'public'	=> [ 'type' => 'bool', 'label' => 'Public API', 'hint' => 'Whether GET /api/catalog answers at all', 'default' => true ],
		'layout'	=> [ 'type' => 'select', 'label' => 'Layout', 'options' => [ 'list' => 'List', 'grid' => [ 'en_US' => 'Grid', 'de_DE' => 'Raster' ] ], 'default' => 'list' ],
	],
];
```

| Key | Meaning |
|---|---|
| `key` | the feature's slug (`/^[a-z][a-z0-9-]*$/`): what `requires` names, what `/nino/features` is keyed by and what `\Nino\Features::setting()` asks for. Without one, the lowercased directory name |
| `name` | a string or a `locale => string` map; required |
| `description` | a string or a `locale => string` map; optional |
| `version` | `major.minor.patch`, optionally with a pre-release suffix (`1.0.0-beta.2`); required. What the panel shows and `activate()` records |
| `nino` | the Nino version the feature was written for, as a constraint; `*` without one |
| `php` | `[ 'ext' => [ … ] ]`: PHP extensions that must be loaded |
| `requires` | keys of other features that must be active first; the feature's own key and duplicates are dropped |
| `settings` | `name => schema`, see [The Settings Schema](#the-settings-schema); a name is a lowerCamel identifier (`/^[a-z][a-zA-Z0-9]*$/`) |
| `data` | paths below `/data/` the feature owns - what a backup carries and a restore callback merges; `..` is refused |

A localized value - `name`, `description`, a `label`, a `hint`, an option of a `select` - is read through `\Nino\Features::localized( $value, $locale )`: the locale asked for, else `en_US`, else the first entry, else an empty string.

The class is not declared but derived: `\Nino\Modules\<Directory>`. A `module` entry that names the same class is accepted; one that says anything else is refused. The autoloader serves that one class from `features/<Name>/` and no other, so a manifest promising something else would be wrong.

The version constraint under `nino` understands enough of the composer vocabulary to write a manifest with:

| Constraint | Satisfied by |
|---|---|
| `*` | every version |
| `1.2.3` | exactly that version; `1.2` by any `1.2.x`, `1` by any `1.x.y` |
| `>=1.2`, `<=1.2`, `>1.2`, `<1.2`, `!=1.2.3` | the comparison |
| `^1.0` | the same major version from `1.0.0` on (`^0.13`: the same minor version from `0.13.0` on) |
| `~1.2` | the same major version from `1.2.0` on; `~1.2.3` the same minor version from `1.2.3` on |
| `>=1.0 <2.0`, `>=1.0, <2.0` | all parts at once |
| `2.0 \|\| ^1.0` | one of the alternatives |

A pre-release kernel counts as the release it precedes: a feature written against `^1.0` runs on `1.0.0-beta`. `\Nino\Features::satisfies( $constraint, $version )` is the function behind it, with the running kernel's version as the default.

A manifest that is not valid as a whole is not applied halfway: the directory is skipped with a warning naming the file and the reason - a directory name that is not a class name, a missing class file, a key that is not a slug, a version that is not `major.minor.patch`, a setting of an unknown type, a default that fails its own schema. A second directory claiming a key an earlier one holds is skipped too. `\Nino\Features::all()` reads the directory once per request and answers, per feature, with the normalized manifest plus `dir`, `module`, `active`, `installed` (the version last recorded, `null` before the first), `update` (active, and the recorded version differs from the manifest's) and `problems`.

## The Settings Schema

Every setting is a form control the panel can render and the kernel can validate. These keys apply to every type:

| Key | Meaning |
|---|---|
| `type` | one of `bool`, `int`, `string`, `text`, `email`, `url`, `select`, `secret`, `lines`; required |
| `label` | the caption, a string or `locale => string`; the setting's name without one |
| `hint` | the explanation under the control, a string or `locale => string` |
| `required` | `true` when an empty value is refused; default `false` |
| `default` | what `settings()` answers while nothing is stored; validated against the setting's own schema. A `secret` cannot have one |

And these per type:

| Type | Value | Own keys | Validation |
|---|---|---|---|
| `bool` | `true`/`false` | - | a form may send `'true'`/`'false'`, `1`/`0` and `'1'`/`'0'`; anything else is refused, never reinterpreted |
| `int` | a whole number | `min`, `max` (ints, `min` not above `max`), `unit` (the value's unit, for display) | a posted string of digits becomes the number; `5.5` is not one; the bounds apply |
| `string` | one line | `maxlength` (1 to 1000, default 1000), `pattern` (a regular expression the value has to match - anchor it yourself) | trimmed; an empty optional value passes the pattern by |
| `text` | several lines | `maxlength` (1 to 10000, default 10000) | trimmed, line breaks kept |
| `email` | an address | `maxlength` as `string` | `FILTER_VALIDATE_EMAIL` |
| `url` | an address | `maxlength` as `string` | `FILTER_VALIDATE_URL` and an `http` or `https` scheme; `javascript:` is refused |
| `select` | one option's value | `options`: `value => label`, not empty, every label a string or `locale => string` | exactly one of the values; an empty value unless `required` |
| `secret` | a string the form never shows again | `maxlength` as `string` | `''` keeps the stored value, `null` clears it, anything else replaces it |
| `lines` | a list of strings | - | a textarea value is split at line breaks, a list is taken as it is; every line trimmed, empty lines and duplicates dropped, at most 1000 characters per line and 200 lines |

The values live under `/nino/features` in `config.php`, per feature as `key => { version, settings }`:

```php
'/nino/features' => [
	'catalog' => [
		'version'  => '1.1.0',
		'settings' => [ 'pageSize' => 24, 'public' => false ],
	],
],
```

A feature reads its settings through the kernel, never out of the array itself:

```php
$pageSize = \Nino\Features::setting( $appData, 'catalog', 'pageSize', 12 );
$all      = \Nino\Features::settings( $appData, 'catalog' );
```

`settings()` answers every setting the schema declares - with the stored value, else the `default`, else the type's zero value (`false`, `min` or `0`, `[]`, `''`) - and only those: a stray key in `config.php` is not a setting. A stored value that no longer validates - after a schema that tightened, after a hand edit - is not handed to the feature; the default is. `setting()` answers the `$default` you pass for a setting the schema does not declare.

A form posts strings and gets the real types back: `'25'` becomes `25` for an `int`, `'false'` becomes `false` for a `bool`. `\Nino\Features::validateSettings( $schema, $posted, $current )` checks every setting before any is accepted and answers with `values` and `errors` (`name => message`); a setting the form did not send keeps its current value. `saveSettings( $appData, $key, $posted )` validates that way and then writes the `/nino/features` key once - or not at all while anything is wrong.

## The Lifecycle

There is no install step. A feature lies in the directory, and everything after that is an action of the panel, or a call of `\Nino\Features`.

### Activating

`\Nino\Features::activate( $appData, $key )` answers `true`, or the reason why not. In order:

1. A feature with `problems` is refused, and the answer names all of them.
2. The features under `requires` are activated first - a cycle is detected and stops rather than running forever. A required feature that is in the directory but not active is no obstacle; one that is missing is.
3. If an `install/` directory lies beside the class, its unit is applied - **without overwriting anything**. A route whose key `config.php` already knows stays; a template `templates/` already has stays; a file that is there stays; a text key present in `text/<locale>.php` or `text/global.php` stays. Only what is missing is added. A `config` default of the unit is set only where the project has nothing. The unit's blacklist entries are merged into `text/blacklist.php`.
4. The class is listed in `/nino/modules` and the manifest's version recorded under `/nino/features/<key>/version`; stored settings stay as they are. `/nino/modules`, `/nino/features` and - when the unit added any - `/nino/http/routes` are written as targeted keys.

The unit is applied against the *persisted* routes of `config.php`, never the live ones: the live array carries this request's runtime routes - the workbench's, the ones an active module registers in `init()` - and those must not be written into `config.php`. The wizard's Setup step follows the same rule.

Activating an active feature again, with its version unchanged, changes nothing.

### Updating

A feature is updated by replacing its directory with the new release and activating it again in the panel - **Update** is the same action. The unit adds what is new and leaves everything the project has edited since the first activation as it is: a template you changed is not replaced by the new release's. What a feature keeps under `data/` may change its shape with the version, though, and there is a hook in the module class for that:

```php
public static function upgrade( array &$appData, string $fromVersion ): bool
```

`activate()` calls it when the feature is active, the recorded version differs from the manifest's and the class has the method - with the recorded version as `$fromVersion`, after the unit has been applied and before the new version is recorded. The module migrates its own data in there. Returning `false` refuses the update, and the recorded version stays the old one. A class without `upgrade()` is updated without a migration.

### Deactivating

`\Nino\Features::deactivate( $appData, $key )` removes the class from `/nino/modules` and writes that key - and nothing else. The settings and the recorded version under `/nino/features` stay, the files under `data/` stay, copied templates and texts stay, the unit's routes stay. Activating again therefore finds the feature as it was. The action is refused while another active feature names this one under `requires`; deactivating an inactive feature is harmless.

There is no uninstall, deliberately: deleting a file the project may have edited in the meantime would not be safe. What a feature leaves behind, a developer removes knowingly and by hand.

### Data, Backups and Restore

What a feature writes under `data/` belongs to the project: the workbench's daily backup carries it, and `data` in the manifest documents which files those are. A feature that needs a restore to merge rather than overwrite registers the callback `'/nino/admin/restore'` in `init()` - the way a module always has. The Backups panel and the recovery page call it with `{ dataDir, staging }`, the live `data/` directory and the extracted backup, and the feature rewrites the files that are its own in the extracted copy before it is copied over the live one. `Newsletter::callbackRestore()` in the catalogue's Newsletter feature is the reference: an address someone removed stays removed however old the restored backup is. The callback runs only while the feature is active.

### The Wizard and the Units

The setup wizard's Setup step offers no features. It knows the kernel modules that ship a unit - navigation, language selection, contact form - a project's own modules under `app/`, and the units under `_admin/install/library/modules/`; a feature is switched on in the Features panel after setup. Both apply their units through the same method, `\Nino\Features::applyUnit()`: the wizard with overwrite on, because a unit applied again is meant to replace what it copied before there; an activation with it off. That is why the application lives in the kernel and not in the wizard - `_admin/install/` may be deleted after setup, and a feature still has to activate afterwards.

## The Catalogue

A feature that is not copied in by hand comes from a catalogue: a `catalogue.json` published over https beside the archives it lists, and beside it a detached signature `catalogue.json.sig`. Nino's own is `https://catalogue.getnino.dev/catalogue.json`, built and signed by the catalogue repository [dapeio/nino-features](https://github.com/dapeio/nino-features) from the same directories a hand copy comes from. The kernel side is `\Nino\Catalogue` in `_nino/Nino/Catalogue/Catalogue.php` and the kernel's one http client, `\Nino\Fetch`; `tests/catalogue-smoke.php` checks both without a network.

### What the Catalogue Says

Format 1 is one JSON document: `format` (`1`), `generated` (when), and `features`, a list of entries - one per published version:

| Field | Meaning |
| --- | --- |
| `key`, `name`, `description`, `version`, `nino`, `php.ext`, `requires` | what the feature's manifest says, see [The Manifest](#the-manifest-featurephp) |
| `directory` | the directory the archive holds - `Newsletter`, the feature's class name segment |
| `archive` | the https url of the `.tar.gz` |
| `sha256`, `size` | the digest and the byte length of exactly that file |
| `released` | the date |

An archive is a `.tar.gz` holding exactly that one directory - what lands below `features/`, nothing beside it; a feature's `tests/` are not published. A catalogue that is wrong anywhere is refused as a whole: `\Nino\Catalogue::parse()` names the entry and the field.

### Trust

The signature is the trust. It is an ECDSA signature (curve P-256) over SHA-256 of the document's exact bytes, DER-encoded and base64 - what `openssl dgst -sha256 -sign key.pem catalogue.json | base64` writes. The public half of Nino's key ships with the kernel as `\Nino\Catalogue::PUBLIC_KEY`; `/nino/catalogue/key` in `config.php` replaces it with another key, PEM, for a catalogue of your own, and `/nino/catalogue/url` names that catalogue. An empty key verifies nothing, so no catalogue is accepted at all until a key is configured - the kernel's constant is empty until Nino's first key exists. `/nino/catalogue/url` set to `''` switches the catalogue off: the Available tab says so and offers no Refresh button, and Nino makes no request.

Nothing is believed before the signature holds: the document is fetched, its signature is fetched, and only a document the key signed is parsed. An installation fetches the catalogue again rather than trusting what the panel showed, downloads the archive with the byte cap the entry names, and refuses an archive whose size or SHA-256 differs from the entry. The archive is unpacked below `data/.features/` - never in `features/` itself - after every entry was looked at: one directory named as the entry says, plain files and directories only, no path outside it, bounded in count and size; what came out is read as a feature and has to be the key and the version the catalogue promised, and to fit this kernel as the entry did. Only then is the directory moved into `features/`, replacing what was there; a move that fails half way puts the old directory back. The staging directory is removed either way.

### The Cache

A successful fetch is kept under `data/catalogue.php` - `\Nino\Catalogue::cached()` reads it back without ever making a request, and the panel's Available tab fills from it the moment it opens. The file holds when it was fetched, the url it was fetched under, and the parsed document; a url that no longer matches the one configured now - including the catalogue having been switched off since - makes `cached()` answer null again, the same as a file that does not hold what a fetch writes. Only the panel's own Refresh action calls `fetch()` a second time, and a successful one overwrites the cache whole; the offers computed from a cached document are always matched against the features actually on disk, so an install or activation since the last fetch shows up on Available without a new one.

### What Install Does Not Do

Install places files, nothing more. A newly installed feature is switched on in the panel like one copied in by hand, with everything [Activating](#activating) says. An update of an active feature is followed by that activation in one step from the panel, so the unit adds what is new and the module gets to migrate its data - see [Updating](#updating) - but `\Nino\Catalogue::install()` itself activates nothing. Requirements are not resolved by the catalogue either: a feature that `requires` another is installed after it, on its own, and the panel says what is missing at activation.

### Requirements and Privacy

An installation needs the `curl` extension or `allow_url_fopen`, the `openssl` extension, `phar` for the archive, and a writable `features/` directory - where it is not writable, the panel links the archive, to unpack by hand as before. Every request goes over https to the catalogue's host and nowhere else: no redirect is followed, no other scheme fetched, the certificate is verified, the user agent says `Nino` and nothing more - not the version, not the site. Nino makes these requests when someone presses **Refresh catalogue** or **Install** and at no other time; there is no check for updates in the background, no telemetry, nothing sent. The manual way stays: a directory copied into `features/` is a feature like any other.

## Writing a Feature

### Directory Layout

```text
features/Catalog/
├── feature.php              the manifest
├── Catalog.php              the runtime class \Nino\Modules\Catalog
├── Admin/Admin.php          the panel \Nino\Modules\Catalog\Admin, answered by adminPanels()
├── assets/admin.js          the panel's script
├── text/<locale>.php        the panel's fills, while the feature is active
├── install/                 the unit activate() applies
│   ├── manifest.php
│   ├── templates/
│   └── text/
└── tests/catalog-smoke.php  the feature's own test
```

Only `feature.php` and `<Name>.php` are required. Everything else is there when the feature needs it, and a feature that is nothing but a class is a complete feature.

### The Class

The class is an ordinary runtime module in the `Nino\Modules` namespace: `init()` registers shortcodes, routes and callbacks and outputs nothing; the rules of the [Developer Manual](development.md#developing-a-custom-module) apply unchanged. Three methods are feature-specific, all of them optional:

| Method | Purpose |
|---|---|
| `adminPanels( array &$appData ): array` | the panel classes the feature brings; the kernel asks every active module (`\Nino\Modules::collect()`) |
| `upgrade( array &$appData, string $fromVersion ): bool` | migrate the feature's own data when a new version is activated; `false` refuses the update |
| `callbackRestore( array &$appData, array &$args ): void` | registered under `'/nino/admin/restore'`: merge the feature's own `data/` files in the extracted copy |

The class reads its settings through `\Nino\Features::setting()` - with a default, so it keeps working when the schema does not know a setting yet.

### The Panel

A panel is a class with `actions()`, `nav()` and `perm()`, like every panel of the workbench; the [Developer Manual](development.md#panels-of-the-workbench) lists the whole contract, the [panel recipe](recipes/admin-panel.md) walks through a complete panel including its frontend. A feature panel names its files from where its class is - `\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' )` - so they move with the directory, and guards every action with `\Nino\Admin\Admin::guardPerm()`. A uri or an action name one of the workbench's own panels already owns is never handed to a feature. Whatever group its own `nav()` names, the registry places it in the rail's **Features** group - a panel's class file lying below `\Nino\Features::dir()` is what the registry checks, not the value the panel wrote - so an editor granted that one group sees every active feature's panel and nothing a kernel or `app/` module placed there instead.

### The Install Unit

`install/manifest.php` has the same shape as a kernel module's unit in the wizard - see the [Library Format](setup.md#library-format) and the [installer recipe](recipes/installer-package.md). `activate()` reads `routes`, `templates`, `files`, `elementTypes`, `blacklist` and `config` from it, plus `text/global.php` and `text/<locale>.php` for every available locale; `key`, `label`, `moduleClass`, `requiresModules` and `preset` are the wizard's and are not read by an activation - the feature manifest carries them in its own form. Everything the unit copies belongs to the project from then on and is not touched by an update.

### Text

There are two kinds of text, and they live in different places. The panel's words - the navigation label, captions, messages - live under `text/<locale>.php` beside the class and are merged into the workbench's fills through the panel's `text()` while the feature is active. The words the website needs - labels in a template the unit copies - live in `install/text/` and are written into the project's `text/` files once, at activation, where the editors maintain them from then on. The feature's own name and description are in the manifest, because the Features panel shows them even when none of that is loaded.

### Tests

`tests/harness.php` is the shared bootstrap of every smoke test: it loads the kernel and the workbench shell and provides `check( $label, $condition )`, `ninoSandbox( $name )` (a fresh, isolated project directory in `$appData`, two locales, no modules), `ninoSandboxDir()`, `ninoWarnings()` (the warnings recorded since the last call - a test that expects one reads them here rather than seeing them on the console) and `ninoDone( $appData )` (the summary, the sandbox removed, the exit status).

A feature's test travels with it, under `features/<Name>/tests/<key>-smoke.php`, and loads the harness from the checkout three levels up - or from the directory `NINO_ROOT` points to, which is how the same test runs against another Nino version:

```php
$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
require $root. '/tests/harness.php';
```

CI runs `php tests/features-smoke.php` - the contract test against `tests/fixtures/features/` - and then every feature's own test: `for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done`, a loop that has to survive an empty glob because a checkout ships no feature. A second job clones the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features), copies every feature of it into the checkout and runs the features' own tests against it, so a kernel change that breaks a published feature fails here; the catalogue's CI does the reverse against Nino's `main` and its latest tag. PHPStan analyses `features/` along with the rest but leaves `features/*/tests/*` out - a test is a standalone script over the harness, not product code.

`tests/fixtures/features/Sample/` is the reference feature: a manifest with every settings type, a class with `upgrade()`, a panel, an install unit. Wherever the contract is unclear, read what `tests/features-smoke.php` asserts about it there.

## Outlook

The features Nino publishes come from the catalogue repository [dapeio/nino-features](https://github.com/dapeio/nino-features): one directory per feature below `features/` there, each with its manifest, its tests, a README and a changelog - copied into a project's `features/` by hand, or installed from the signed catalogue the repository publishes at getnino.dev, see [The Catalogue](#the-catalogue). What the catalogue does not do yet is resolve requirements on its own: a feature that `requires` another is installed after it, each on its own. And a catalogue of your own is a matter of a url and a key - the format is small enough to publish from a directory of features with the repository's `bin/build.php`.

## Further Manuals

- [Developer Manual](development.md) explains modules, autoloading, panels and the tests.
- [Feature recipe](recipes/feature.md) builds a feature step by step up to a passing test.
- [`/_admin` Workbench](_admin.md) describes the Features panel beside the other panels.
- [Setup Wizard](setup.md) documents the library format an install unit shares.
- [Deployment](deployment.md) names the server rule for `features/` and how an update is applied.
