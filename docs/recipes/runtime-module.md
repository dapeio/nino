# Recipe: Add a runtime module

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Templates Panel](../templates.md)

One of the six extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


Use a runtime module when public requests need new PHP behavior: a shortcode,
an API, a webhook, a form handler, a render hook, or feature-owned runtime
state. Do not create a module for static markup that a `.tpl` can express.

## Autoload layout

Nino's autoloader is intentionally small. It converts the full class name to a
path and appends the class basename as the filename:

```text
Class: Project\Catalog\Catalog
Path:  app/Project/Catalog/Catalog/Catalog.php
```

```text
Class: Nino\Modules\Form
Path:  _nino/Nino/Modules/Form/Form.php   (looked for first)
       app/Nino/Modules/Form/Form.php     (where it is delivered)
```

The lookup roots are deliberately different:

- `Nino\*` is kernel-owned and resolves only below `_nino/`. A project cannot
  shadow a kernel class from its application root.
- `Nino\Modules\*` is the one opening: looked for below `_nino/` first, then
  below the application root. Nino's optional modules - `Form`, `Newsletter`,
  `Navigation`, `Search`, `Localepicker`, `Design`, `Templates` - are delivered
  at `app/Nino/Modules/<Name>/`, where a project deletes the ones it does not
  need and updates the ones it keeps itself; `_nino/` stays replaceable
  wholesale. A kernel module of the same name would win.
- Every other namespace resolves below `app/` and nowhere else. If
  `NINO_APP_DIR` was defined as an absolute directory path before
  `_nino/Nino.php` was loaded, that directory replaces `app/` - for the
  delivered optional modules too, so a relocated app dir takes them along.

Within whichever root applies, the exact formula is:

```text
<root>/<namespace-and-class-as-path>/<class-basename>.php
```

Therefore:

- the class basename and PHP filename MUST match;
- every namespace segment becomes a directory;
- a leading backslash in the configured class string is accepted;
- class names MUST never be built from request data;
- do not add manual `require` calls for a correctly located module.

Nino's own modules use namespace `Nino\Modules`. Project-specific modules
MUST use a project namespace so future Nino classes cannot collide.

Everything a module brings lives in its directory, below the class file:

```text
app/Project/Catalog/Catalog/
├── Catalog.php            the runtime module, class Project\Catalog\Catalog
├── Admin/Admin.php        optional workbench panel, answered by adminPanels() (7.)
├── assets/                the panel's own .js/.css
├── text/<locale>.php      the panel's fills
├── templates/panel.tpl    the panel's markup, when it answers template() (see the panel recipe)
└── install/               optional installer unit, found by Setup::units() (9.)
```

## Minimal complete module

Create
`app/Project/Catalog/Catalog/Catalog.php`:

```php
<?php
declare(strict_types=1);

namespace Project\Catalog;

class Catalog {

	private const string STORAGE = '/data/catalog.php';

	public static function init( array &$appData ): void {

		\Nino\Html::addShortcode(
			$appData,
			'catalog-count',
			[ self::class, 'doCountShortcode' ]
		);

		// This technical endpoint belongs to the module. Assignment is
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

	public static function doCountShortcode(
		array &$appData,
		array $args
	): string {

		$items = self::items( $appData );

		return '<span class="project-catalog-count">'
			. count( $items )
			. '</span>';
	}

	public static function callbackCatalogResponse(
		array &$appData,
		array &$request
	): void {

		$publicItems = [];

		foreach( self::items( $appData ) as $id => $item ) {
			if( is_array( $item ) === false )
				continue;

			$publicItems[] = [
				'id'    => (string) $id,
				'title' => (string) ( $item['title'] ?? '' ),
				'uri'   => (string) ( $item['uri'] ?? '' ),
			];
		}

		\Nino\Http::ok( $request, [
			'items' => $publicItems,
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

Then activate the full class string under `/nino/modules`:

```php
'/nino/modules' => [
	// Existing modules...
	'\\Project\\Catalog\\Catalog',
],
```

Important properties of the example:

- `init()` only registers behavior; it prints and persists nothing.
- The module owns its technical API route.
- The callback uses the route's internal `uri`.
- The JSON response contains an explicit public-field projection. It does not
  expose a storage record wholesale.
- The shortcode returns deterministic markup and a safe integer.
- Internal lookup is private and stays inside the class.

If the requested route is an ordinary page, put it in project configuration or
a page-library manifest instead. Feature-owned callback endpoints such as
`/.newsletter` or `/.form` belong to the runtime module.

## Shortcode contract

Register:

```php
\Nino\Html::addShortcode(
	$appData,
	'catalog',
	[ self::class, 'doShortcode' ]
);
```

Handle:

```php
public static function doShortcode(
	array &$appData,
	array $args
): string {
	$limit = min( 24, max( 1, (int) ( $args['limit'] ?? 6 ) ) );
	$content = (string) ( $args['content'] ?? '' );

	// Build and return markup. Never echo it.
	return $content;
}
```

Arguments can be positional, named, or enclosed as `content`. Treat all of them
as untrusted even when the shortcode normally comes from a developer-authored
template; templates and stored HTML can be edited.

When placing any dynamic string into returned HTML:

```php
private static function escapeHtml( string $value ): string {
	$value = htmlspecialchars(
		$value,
		ENT_QUOTES | ENT_SUBSTITUTE,
		'UTF-8'
	);

	// Shortcode output is rendered again; prevent bracket syntax injection.
	return str_replace(
		[ '[', ']' ],
		[ '&#91;', '&#93;' ],
		$value
	);
}
```

Do not sanitize an arbitrary string once and reuse it in HTML, JavaScript,
CSS, a URL, and a header. Those are different output contexts.

## State-changing runtime endpoint

A write handler MUST:

1. use POST or another appropriate non-GET method;
2. keep CSRF active for browser-originated requests;
3. return immediately if the earlier CSRF callback blocked the request;
4. authenticate and authorize where the operation is not public;
5. parse only the intended input representation;
6. validate type, shape, length, enum membership, and identifiers;
7. use `Filesystem::mutate()` or the owning public persistence API;
8. avoid leaking whether protected records exist;
9. apply a suitable abuse/rate limit to public endpoints;
10. return a small documented response.

The beginning of a browser write callback normally includes:

```php
public static function callbackSave(
	array &$appData,
	array &$request
): void {

	if( ( $request['./nino/csrf/blocked'] ?? false ) === true )
		return;

	if(
		\Nino\Auth::checkPermission(
			$appData,
			'/project/catalog/manage'
		) === false
	) {
		\Nino\Http::fail( $request, 403, 'forbidden' );
		return;
	}

	// Validate input, mutate owned state, return through Http.
}
```

For an anonymous form such as contact or newsletter signup, authorization may
not apply, but CSRF, honeypot/rate limiting, length caps, generic responses, and
bounded storage do.

Do not set `'csrf' => false` merely because an API client cannot send the
current token. Design an explicit authentication/signature mechanism first.

## Module configuration

Use a namespaced top-level key:

```php
'/project/catalog' => [
	'pageSize' => 12,
	'public'   => true,
],
```

Read with defaults and validate because `config.php` is editable:

```php
$config = is_array( $appData['/project/catalog'] ?? null )
	? $appData['/project/catalog']
	: [];
$pageSize = min( 100, max( 1, (int) ( $config['pageSize'] ?? 12 ) ) );
```

Do not write defaults during every `init()`. Defaults belong in the shipped
`config.php` or the installer package. Persist settings only as the result of
an intentional write operation.

## Module tests

Add focused coverage to `tests/kernel-smoke.php` or a dedicated standalone
smoke script consistent with the suite. Test:

- autoloading from the exact default or `NINO_APP_DIR` path, including the
  kernel namespace guard and the `Nino\Modules\*` second root;
- activation through `/nino/modules`;
- repeated `init()` behavior where relevant;
- route registration and internal callback identity;
- successful and rejected responses;
- malformed/missing input;
- CSRF and permission denial for writes;
- shortcode output and bracket/HTML injection;
- storage failure behavior;
- concurrent updates for module-owned files;
- and absence of direct output.

Run at least:

```bash
php -l app/Project/Catalog/Catalog/Catalog.php
php tests/kernel-smoke.php
php tests/concurrency-smoke.php
```

If the module adds frontend JavaScript, also run `node --check` and add a Node
smoke test for its behavior rather than testing only for the file's presence.

## Panels, the installer unit and Restore

A feature is one directory: add it and everything appears, remove it and
everything is gone. Three optional hooks make that true for the management
tools and the installer:

- `adminPanels( array &$appData ): array` returns panel class names (recipe
  7). The kernel asks through `\Nino\Modules::collect()`, in `/nino/modules`
  order and only while the module is active, so the screens come and go with
  the module.
- `install/manifest.php` beside the class makes the module selectable in
  the setup wizard (the [installer package recipe](installer-package.md)). No wizard file lists it. A module that is a
  panel and nothing else - Design, Templates - has no unit: the wizard's
  Setup step lists `Install\Setup::TOOL_MODULES` in `/nino/modules` whenever
  their class exists.
- `'/nino/admin/restore'` (args `dataDir`, `staging`) is the callback a module
  registers in `init()` when it keeps its own files under `data/` that a
  backup carries: the Backups panel (and the recovery page) call it with the
  staged backup and the live data directory, and the module merges what is
  its own - `Newsletter::callbackRestore()` is the reference. Restore itself
  knows no module.
