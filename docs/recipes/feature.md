# Recipe: Package a feature

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Features](../features.md)

One of the seven extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


Use a feature when a runtime module - with or without a panel and an install
unit - is meant to arrive in a project as one directory, be switched on in the
workbench, carry a version and settings there, and be updated by replacing the
directory. A feature is not the right shape for project-specific code (that is
a project module under `app/` with its own namespace, the [runtime module
recipe](runtime-module.md)), nor for a screen every project has regardless of
its modules (a workbench module under `_admin/Nino/Modules/`, the [panel
recipe](admin-panel.md)). The contract this recipe packages against is
`\Nino\Features` and the [Features manual](../features.md);
`tests/fixtures/features/Sample/` is the checkout's reference feature, the one
the contract test exercises everything on, and `Newsletter` and `Search` in
the catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features)
are the published ones - a checkout ships no feature of its own.

The example is the catalogue the other recipes build, delivered as a feature:
`features/Catalog/`, class `\Nino\Modules\Catalog`, key `catalog`.

## What a feature is made of

```text
features/Catalog/
├── feature.php              the manifest (1.)
├── Catalog.php              the runtime class \Nino\Modules\Catalog (2.)
├── Admin/Admin.php          the panel \Nino\Modules\Catalog\Admin (3.)
├── text/
│   ├── en_US.php            the panel's fills, merged while the feature is active
│   └── de_DE.php
├── install/                 the unit activate() applies, add-only (4.)
│   ├── manifest.php
│   ├── templates/section-catalog.tpl
│   └── text/{global,en_US,de_DE}.php
└── tests/catalog-smoke.php  the feature's own test (9.)
```

The directory name is the class name: the autoloader serves
`\Nino\Modules\Catalog` from `features/Catalog/Catalog.php` and
`\Nino\Modules\Catalog\Admin` from `features/Catalog/Admin/Admin.php` - below
the features root the `Nino/Modules/` prefix is the directory itself. It MUST
match `/^[A-Z][A-Za-z0-9]*$/`. Only `feature.php` and `Catalog.php` are
required; everything else is there when the feature needs it.

`_nino/` is searched before `_admin/`, `_admin/` before `features/`,
`features/` before `app/`: a feature can never shadow a kernel module or a
workbench screen, and a project cannot replace a feature from `app/`. Do not
name a feature after a class one of the earlier roots already holds.

## 1. The manifest

Create `features/Catalog/feature.php`:

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
	/*	The card the panel opens the feature's screen with: one section per kind
		of thing a feature adds, each a handle and one line. Always the same
		sections, always in the same order, the empty ones included - what a
		developer does with this is look something up. See
		docs/features.md#the-manual; the README is the other one	*/
	'manual'			=> [
		'shortcodes'	=> [
			'[catalog]' => [ 'en_US' => 'The list. `limit` and `sort` narrow it.', 'de_DE' => 'Die Liste. `limit` und `sort` schränken sie ein.' ],
		],
		'markup'			=> [],
		'routes'			=> [ '/api/catalog' => 'The public JSON endpoint, while "Public API" is on.' ],
		'panel'				=> [ 'Catalog' => 'The products themselves.' ],
		'callbacks'		=> [],
		'install'			=> [ 'templates/page-catalog.tpl' => 'The list page.' ],
	],
	// What it is for, one of \Nino\Features::CATEGORIES - what the panel
	// groups and filters by. See docs/features.md#categories
	'category'		=> 'content',
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

Rules `\Nino\Features::manifest()` enforces - a manifest that breaks one is
skipped with a warning naming the file and the reason, never applied halfway:

- `key` is a slug (`/^[a-z][a-z0-9-]*$/`); without one it is the lowercased
  directory name. It is what `requires` names, what `/nino/features` is keyed
  by, and what `\Nino\Features::setting()` asks for.
- `name` is required; `name`, `description`, every `label`, `hint` and select
  option label are a string or a `locale => string` map. The panel shows
  features that are not active, whose fills are not loaded, so these words
  travel in the manifest.
- `version` is `major.minor.patch`, optionally with a pre-release suffix.
- `nino` is a version constraint (`*`, exact, `>=`/`<=`/`>`/`<`/`!=`, `^1.0`,
  `~1.2`, `~1.2.3`, parts joined by comma or space, `||` alternatives);
  default `*`. A pre-release kernel such as `1.0.0-beta` counts as `1.0.0`.
- `php` => `ext` lists extension names; `requires` lists feature keys (the
  feature's own is dropped); `data` lists paths below `/data/` without `..`.
- A setting name is lowerCamel (`/^[a-z][a-zA-Z0-9]*$/`); `type` is one of
  `bool`, `int`, `string`, `text`, `email`, `url`, `select`, `secret`,
  `lines`; a `default` has to validate against its own schema; a `secret`
  cannot have one; a `select` needs non-empty `options`; `maxlength` is at
  most 1000 (`text`: 10000); a string `pattern` must be a valid regular
  expression; int `min`/`max` are ints with `min` not above `max`.
- A `module` entry MAY name `\Nino\Modules\Catalog`; naming anything else is
  refused. The class file `Catalog.php` MUST exist beside the manifest.

Do not add decorative keys and assume the panel uses them. The [Features
manual](../features.md#the-manifest-featurephp) has the full reference,
including every setting type's own keys and validation.

## 2. The class

Create `features/Catalog/Catalog.php`. It is an ordinary runtime module in the
`Nino\Modules` namespace: `init()` registers behaviour and outputs nothing,
the technical route belongs to the module, responses go through `Http`. Three
methods are feature-specific: `adminPanels()` brings the panel (3.),
`upgrade()` migrates data when a new version is activated (7.), and the
settings are read through `\Nino\Features::setting()` with a default.

```php
<?php
declare(strict_types=1);

namespace Nino\Modules;

class Catalog {

	private const string STORAGE = '/data/catalog.php';

	public static function init( array &$appData ): void {

		\Nino\Html::addShortcode(
			$appData,
			'catalog-count',
			[ self::class, 'doCountShortcode' ]
		);

		// This technical endpoint belongs to the feature. Assignment is
		// deliberate so a stale persisted entry cannot shadow its behavior.
		$appData['/nino/http/routes']['GET://api/catalog'] = [
			'uri' => '/api/catalog',
		];

		\Nino\Callbacks::registerCallback(
			$appData,
			'/nino/http/response/GET://api/catalog',
			[ self::class, 'callbackCatalogResponse' ]
		);
	}

	// The panel comes and goes with the feature: the kernel asks every
	// active module, in /nino/modules order (see \Nino\Modules::collect())
	public static function adminPanels( array &$appData ): array {

		return [ \Nino\Modules\Catalog\Admin::class ];
	}

	// Called by \Nino\Features::activate() when this feature is active and
	// the recorded version differs from the manifest's - after the unit has
	// been applied, before the new version is recorded. False refuses.
	public static function upgrade( array &$appData, string $fromVersion ): bool {

		// 1.0.x kept the items as a plain list; from 1.1.0 on they are keyed by id
		if( version_compare( $fromVersion, '1.1.0', '<' ) === false )
			return true;

		return \Nino\Filesystem::mutate( $appData, self::STORAGE, function( mixed $items ): array {

			$keyed = [];

			foreach( is_array( $items ) ? $items : [] as $id => $item )
				if( is_array( $item ) === true )
					$keyed[ (string) ( $item['id'] ?? $id ) ] = $item;

			return $keyed;
		}, [] );
	}

	public static function doCountShortcode(
		array &$appData,
		array $args
	): string {

		return '<span class="catalog-count">'
			. count( self::items( $appData ) )
			. '</span>';
	}

	public static function callbackCatalogResponse(
		array &$appData,
		array &$request
	): void {

		if( \Nino\Features::setting( $appData, 'catalog', 'public', true ) !== true ) {
			\Nino\Http::fail( $request, 404, 'not found' );
			return;
		}

		$pageSize = (int) \Nino\Features::setting( $appData, 'catalog', 'pageSize', 12 );
		$publicItems = [];

		foreach( array_slice( self::items( $appData ), 0, $pageSize, true ) as $id => $item ) {
			if( is_array( $item ) === false )
				continue;

			$publicItems[] = [
				'id'    => (string) $id,
				'title' => (string) ( $item['title'] ?? '' ),
				'uri'   => (string) ( $item['uri'] ?? '' ),
			];
		}

		\Nino\Http::ok( $request, [
			'title'  => (string) \Nino\Features::setting( $appData, 'catalog', 'title', 'Catalog' ),
			'layout' => (string) \Nino\Features::setting( $appData, 'catalog', 'layout', 'list' ),
			'items'  => $publicItems,
		] );
	}

	private static function items( array &$appData ): array {

		$items = \Nino\Filesystem::getFileContent(
			$appData,
			self::STORAGE,
			[]
		);

		return is_array( $items ) ? $items : [];
	}
}
```

What the settings replace: the runtime module recipe reads
`/project/catalog` from `config.php` and clamps it by hand. A feature declares
the same values as settings and gets them back validated and typed -
`pageSize` is an int within `1..100`, or the default. `setting()` still takes a
default, so the class keeps working against a schema that does not declare
the setting yet.

Everything in the [runtime module recipe](runtime-module.md) about
shortcodes, state-changing endpoints, escaping and CSRF applies unchanged. A
feature that keeps files under `data/` registers `'/nino/admin/restore'` in
`init()` as well (8.).

## 3. The panel

Create `features/Catalog/Admin/Admin.php`, the smallest complete panel: an
action map, a navigation entry, a permission, the fills. The frontend half -
the script that renders into the pane - is the [panel
recipe](admin-panel.md); the panel below is what the test in 9. can see.

```php
<?php
declare(strict_types=1);

namespace Nino\Modules\Catalog;

class Admin {

	public const string MANAGE_PERM = '/_admin/catalog/manage';

	public static function perm(): string {
		return self::MANAGE_PERM;
	}

	public static function actions(): array {
		return [ 'catalog/list' => [ self::class, 'apiList' ] ];
	}

	public static function nav(): array {
		return [ 'catalog', '/_admin/nav/catalog', 60, 'content' ];
	}

	// The panel's own words, one <locale>.php per interface language -
	// named from where this class is, so they move with the directory
	public static function text(): string {
		return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
	}

	public static function apiList( array &$appData, array &$request ): void {

		if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
			return;

		$items = \Nino\Filesystem::getFileContent( $appData, '/data/catalog.php', [] );

		\Nino\Http::ok( $request, [
			'items'    => is_array( $items ) ? $items : [],
			'settings' => \Nino\Features::settings( $appData, 'catalog' ),
		] );
	}
}
```

The `nav()` above names `content`, but the registry never actually places it
there: a panel whose class file lies below `\Nino\Features::dir()` always
lands in the rail's own **Features** group, between Structure and System,
whatever it names - and the roles tab offers its permission under that same
group. Naming `content` (or leaving the fourth entry off, since it defaults
to `content`) costs nothing and reads naturally if the class is ever moved
out of `features/`.

`features/Catalog/text/en_US.php`:

```php
<?php
return [
	'[[/_admin/nav/catalog]]' => 'Catalog',
];
```

`features/Catalog/text/de_DE.php`:

```php
<?php
return [
	'[[/_admin/nav/catalog]]' => 'Katalog',
];
```

Rules:

- Every action method guards itself with `Admin::guardPerm()` and the panel's
  own permission.
- The uri (`catalog`) names the link, the pane, the action prefix and the JS
  namespace `Nino.admin.catalog`. A uri or action name a workbench panel
  already owns is never handed to a feature - pick another.
- A content panel's permission appears as a checkbox on the Users panel's
  roles tab. The **Editor** role the wizard wrote before the feature was
  activated does not receive it by itself; the operator grants it there.
- The panel is registered only while the feature is active, and the
  workbench renders its rail once per page load - after activating or
  deactivating, the panel appears or goes with the next load.

## 4. The install unit

Create `features/Catalog/install/manifest.php`. The unit has the same shape as
a kernel module's in the wizard ([installer package recipe](installer-package.md),
[Library Format](../setup.md#library-format)):

```php
<?php
declare(strict_types=1);

return [
	'templates' => [
		'section-catalog.tpl',
	],
	'blacklist' => [
		'/catalog/api-uri',
	],
];
```

`features/Catalog/install/templates/section-catalog.tpl`:

```html
<section class="nino-section" id="catalog">
	<h2>[[/catalog/title]]</h2>
	<p>[catalog-count] [[/catalog/label/items]]</p>
</section>
```

`features/Catalog/install/text/global.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/catalog/api-uri]]' => '/api/catalog',
];
```

`features/Catalog/install/text/en_US.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/catalog/title]]'       => 'Catalog',
	'[[/catalog/label/items]]' => 'items in the catalogue',
];
```

`features/Catalog/install/text/de_DE.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/catalog/title]]'       => 'Katalog',
	'[[/catalog/label/items]]' => 'Einträge im Katalog',
];
```

What `\Nino\Features::activate()` reads from the unit: `routes`, `templates`
(locale-keyed entries only for available locales), `files`, `elementTypes`,
`blacklist`, `config`, and `text/global.php` plus `text/<locale>.php` for
every available locale. `key`, `label`, `moduleClass`, `requiresModules`,
`preset` and `active` are the wizard's picker and are not read by an
activation - the feature manifest carries them in its own form (`key`,
`name`, the derived class, `requires`).

The unit is applied **add-only**: a route key `config.php` already has, a
template `templates/` already has, a file that is there, a text key that
exists - each stays exactly as it is, and only what is missing arrives. A
`config` default is written only where the project has no value. That is the
whole difference from the wizard, which applies the same unit through the same
`\Nino\Features::applyUnit()` with overwrite on. Therefore:

- a unit MUST NOT rely on being re-applied to fix a file - an update never
  replaces a project's copy;
- a unit MUST NOT promise an uninstall - deactivation removes the class from
  `/nino/modules` and nothing else;
- a route the feature owns at runtime (`GET://api/catalog` above) belongs in
  `init()`, not in the unit; a visitor page the feature ships belongs in the
  unit's `routes`.

## 5. Activate it

Drop the directory into `features/`, sign in to `/_admin`, open **Features**
in the System group (`/_admin/features/manage`) and activate the feature. Or
from a script, which is what the test does:

```php
$result = \Nino\Features::activate( $appData, 'catalog' );  // true, or why not
```

`activate()` refuses a feature with problems (a `nino` constraint the running
kernel does not satisfy, a missing extension, a required feature that is not in
the directory), activates the features under `requires` first, applies the
unit add-only against the routes persisted in `config.php`, lists
`\Nino\Modules\Catalog` in `/nino/modules`, records `1.1.0` under
`/nino/features/catalog/version`, and writes `/nino/modules`,
`/nino/features` and - when the unit added any - `/nino/http/routes` as
targeted keys. Activating an active, current feature again changes nothing.

`\Nino\Features::deactivate( $appData, 'catalog' )` removes the class and
writes `/nino/modules` - settings, data, copied templates and texts stay. It
is refused while another active feature lists `catalog` under `requires`.

## 6. Settings

Read them in the class through the kernel, never from `$appData['/nino/features']`:

```php
$pageSize = \Nino\Features::setting( $appData, 'catalog', 'pageSize', 12 );
$all      = \Nino\Features::settings( $appData, 'catalog' );
```

`settings()` answers every declared setting - the stored value, else the
default, else the type's zero value - and only declared ones; a stored value
that no longer validates falls back to the default. The panel's settings
action goes through `\Nino\Features::saveSettings( $appData, 'catalog',
$posted )`, which validates every setting before any is written and answers
`name => message` for every rejected one, `[]` when saved. A form posts
strings and gets the real types back; a `secret` posted as `''` keeps the
stored value and as `null` clears it. The stored shape in `config.php`:

```php
'/nino/features' => [
	'catalog' => [
		'version'  => '1.1.0',
		'settings' => [ 'pageSize' => 24, 'public' => false ],
	],
],
```

## 7. Updates and the upgrade hook

A feature is updated by replacing its directory with the new release and
activating it again - the panel offers that as **Update** for an active
feature whose manifest version differs from the recorded one. The unit adds
what is new and touches nothing the project has; then, if the class implements
it, `upgrade( $appData, $fromVersion )` runs with the recorded version, and
only after it returns `true` is the new version recorded. Returning `false`
refuses the update and leaves the record as it was.

Rules for `upgrade()`:

- migrate only what the feature owns - its `data/` files, its own config keys;
- use `Filesystem::mutate()` for the files, and return its result;
- be idempotent for the versions it handles: a refused update is retried;
- bump `version` in the manifest with every release that a project should be
  able to tell apart - the panel can only offer an update it can see.

## 8. Data and restore

`data` in the manifest documents the files under `/data/` the feature owns.
The workbench's daily backup carries `data/` as a whole; a feature whose
restore must merge rather than overwrite - a removal that has to survive an
older backup, a counter that must not go backwards - registers
`'/nino/admin/restore'` in `init()`:

```php
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/restore', [ self::class, 'callbackRestore' ] );
```

The Backups panel and the recovery page call it with `{ dataDir, staging }`,
the live `data/` directory and the extracted backup; the callback rewrites its
own files in the staged copy before that copy is copied over the live
directory. `Newsletter::callbackRestore()` in the catalogue's
[features/Newsletter/Newsletter.php](https://github.com/dapeio/nino-features/blob/main/features/Newsletter/Newsletter.php)
is the reference. The callback runs only while the feature is active.

## 9. The feature's own test

Create `features/Catalog/tests/catalog-smoke.php`. A feature's test travels
with it and loads the shared harness from the checkout three levels up - or
from the checkout `NINO_ROOT` names, which is how the same test runs against
another Nino version:

```php
<?php
declare(strict_types=1);

/**
 *	Nino
 *	catalog-smoke.php	Contract test for the Catalog feature: the manifest,
 *										activation with the unit applied, the shortcode, the
 *										API, the panel, settings, the upgrade hook and
 *										deactivation. Travels with the feature and runs against
 *										the checkout three levels up, or the one NINO_ROOT names
 *										(see tests/harness.php there).
 *
 *	Usage: php features/Catalog/tests/catalog-smoke.php
 */

// Before the kernel loads: the autoloader and Features::dir() read the same
// constant, so the directory this feature lives in is the one searched -
// wherever NINO_ROOT points
define( 'NINO_FEATURES_DIR', dirname( __DIR__, 2 ) );

$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
require $root. '/tests/harness.php';

$appData = ninoSandbox( 'catalog' );

// The wizard writes the first config.php; here it is written by hand
\Nino\AppData::writeContentData( $appData, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native' ] );
ninoWarnings();

echo "Manifest\n";

$manifest = \Nino\Features::manifest( dirname( __DIR__ ) );
check( 'the manifest validates without a warning', is_array( $manifest ) && ninoWarnings() === [] );
check( 'key, class and version are what the directory says', $manifest['key'] === 'catalog'
	&& $manifest['module'] === '\\Nino\\Modules\\Catalog' && $manifest['version'] === '1.1.0' );
check( 'it is written for this kernel', \Nino\Features::satisfies( $manifest['nino'] ) === true );
check( 'the registry lists it inactive, with nothing in the way', ( static function() use ( &$appData ): bool {
	$feature = \Nino\Features::get( $appData, 'catalog' );
	return $feature !== null && $feature['active'] === false && $feature['installed'] === null && $feature['problems'] === [];
} )() );

echo "\nActivation\n";

$result = \Nino\Features::activate( $appData, 'catalog' );
$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'activation succeeds', $result === true );
check( 'the class is listed and the version recorded', in_array( '\\Nino\\Modules\\Catalog', $stored['/nino/modules'], true ) === true
	&& $stored['/nino/features']['catalog']['version'] === '1.1.0' );
check( 'the unit copied its template and merged its text', is_file( \Nino\Filesystem::path( $appData, '/templates/section-catalog.tpl' ) ) === true
	&& \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] )['[[/catalog/title]]'] === 'Catalog'
	&& in_array( '/catalog/api-uri', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true );
check( 'the settings answer their defaults', \Nino\Features::settings( $appData, 'catalog' ) === [ 'title' => 'Catalog', 'pageSize' => 12, 'public' => true, 'layout' => 'list' ] );

\Nino\Modules::callModules( $appData, 'init' );
check( 'the shortcode renders after boot', \Nino\Html::renderHtml( $appData, '[catalog-count]' ) === '<span class="catalog-count">0</span>' );
check( 'the panel is registered while the feature is active', isset( \Nino\Admin\Admin::panels( $appData )['catalog'] ) === true );

echo "\nSettings and the API\n";

check( 'a posted form is validated and typed', \Nino\Features::saveSettings( $appData, 'catalog', [ 'pageSize' => '24', 'public' => 'false' ] ) === []
	&& \Nino\Features::setting( $appData, 'catalog', 'pageSize' ) === 24 && \Nino\Features::setting( $appData, 'catalog', 'public' ) === false );
check( 'a value outside its bounds is refused and nothing is written', \Nino\Features::saveSettings( $appData, 'catalog', [ 'pageSize' => '500' ] ) === [ 'pageSize' => 'must be at most 100' ]
	&& \Nino\Features::setting( $appData, 'catalog', 'pageSize' ) === 24 );

$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Catalog::callbackCatalogResponse( $appData, $request );
check( 'the API is off while public is false', $request['/nino/http/response']['statusCode'] === 404 );

\Nino\Features::saveSettings( $appData, 'catalog', [ 'public' => 'true' ] );
$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Catalog::callbackCatalogResponse( $appData, $request );
check( 'the API answers the title, the layout and a page of items', $request['/nino/http/response']['body'] === [ 'title' => 'Catalog', 'layout' => 'list', 'items' => [] ] );

echo "\nUpdate\n";

// The registry is read once per request; a test that rewrites the record by
// hand drops the cached read, the way tests/features-smoke.php does
$appData['/nino/features']['catalog']['version'] = '1.0.0';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );
check( 'a recorded version behind the manifest reads as an update', \Nino\Features::get( $appData, 'catalog' )['update'] === true );
check( 'activating again runs the upgrade hook and records the new version', \Nino\Features::activate( $appData, 'catalog' ) === true
	&& \Nino\Features::get( $appData, 'catalog' )['installed'] === '1.1.0' && \Nino\Features::get( $appData, 'catalog' )['update'] === false );

echo "\nDeactivation\n";

check( 'deactivation removes the class and keeps the settings', \Nino\Features::deactivate( $appData, 'catalog' ) === true
	&& in_array( '\\Nino\\Modules\\Catalog', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'], true ) === false
	&& \Nino\Features::setting( $appData, 'catalog', 'pageSize' ) === 24 );
check( 'the panel is gone with it', isset( \Nino\Admin\Admin::panels( $appData )['catalog'] ) === false );
check( 'the copied template survives deactivation', is_file( \Nino\Filesystem::path( $appData, '/templates/section-catalog.tpl' ) ) === true );

ninoDone( $appData );
```

The harness gives a test `check()`, `ninoSandbox()` (a fresh isolated project
directory with two locales and no modules), `ninoSandboxDir()`,
`ninoWarnings()` (the warnings recorded since the last call - assert on a
warning you expect rather than scrolling past it) and `ninoDone()` (the
summary, the sandbox removed, the exit status). Test the visible contract: the
manifest validates, activation lists the class and records the version, the
unit's files land add-only, the shortcode and the endpoint answer, the panel
is present while the feature is active and absent after, settings are typed
and bounded, the upgrade hook runs, deactivation keeps what the project has.

Run:

```bash
php -l features/Catalog/feature.php
php -l features/Catalog/Catalog.php
php -l features/Catalog/Admin/Admin.php
php -l features/Catalog/install/manifest.php
php features/Catalog/tests/catalog-smoke.php
php tests/features-smoke.php
phpstan analyse
```

CI runs `php tests/features-smoke.php` - the contract itself, against
`tests/fixtures/features/` - and then every feature's own test:
`for test in features/*/tests/*-smoke.php; do [ -e "$test" ] || continue; php "$test" || exit 1; done`,
guarded because a checkout ships no feature and the glob may match nothing.
A second job copies every feature of the catalogue
[dapeio/nino-features](https://github.com/dapeio/nino-features) into the
checkout and runs their tests the same way. PHPStan analyses `features/`
and excludes `features/*/tests/*`. To run the
test against another checkout, point `NINO_ROOT` at it:

```bash
NINO_ROOT=/path/to/other/nino php features/Catalog/tests/catalog-smoke.php
```

## What a feature does not do

- It does not install itself: there is no install step, and the wizard does
  not offer features. A feature is dropped in and activated.
- It does not uninstall: deactivation removes the class from `/nino/modules`
  and nothing else. Copied files, texts, routes, settings and data stay.
- It does not overwrite: neither activation nor an update replaces a file, a
  route key or a text key the project already has.
- It does not download itself: a feature is copied into `features/` by hand,
  or installed there from the signed catalogue by the Features panel - the
  published ones come from the catalogue repository [dapeio/nino-features](https://github.com/dapeio/nino-features),
  see [The Catalogue](../features.md#the-catalogue). Nino makes no outbound
  request unless someone presses **Refresh catalogue** or **Install** there.
