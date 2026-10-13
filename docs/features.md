# Developing a feature

This manual is for someone who writes a feature: a directory below `features/` that
brings a module - and a panel and an install unit, when it needs them - to a Nino
project. The contract is `\Nino\Features` (`_nino/Nino/Features/Features.php`),
checked by `tests/features-smoke.php` against `tests/fixtures/features/`; the
docblock of each member is its reference. The catalogue's `Hello`
([dapeio/nino-features](https://github.com/dapeio/nino-features)) is the complete
example, written to be copied. The operator's side, the Features panel and the
catalogue, is in [the workbench manual](_admin.md#features).

## What a feature is

A feature is an installable package: one directory below `features/` with the
module's class, a workbench panel when it has one, an install unit with templates
and texts, and a manifest `feature.php`. A checkout ships none: one is dropped in or
installed from the signed catalogue and switched on in the workbench, and the
project holds it with a version, settings in `config.php` and files under `data/`.

| Kind | Where | Switched on by |
|---|---|---|
| **Kernel module** | `_nino/Nino/Modules/<Name>/`, Nino's own | the setup wizard, or `/nino/modules` by hand |
| **Feature** | `features/<Name>/` with `feature.php`: from outside, held by the project | the Features panel |
| **Project module** | `app/<Vendor>/...` under a namespace of its own, the project's | `/nino/modules` by hand |

"Module" is a class listed in `/nino/modules` whose `init()` the kernel calls;
"feature" is the package that delivers one. The directory is the class name:
`features/Hello/Hello.php` is `\Nino\Modules\Hello`,
`features/Hello/Admin/Admin.php` its panel `\Nino\Modules\Hello\Admin`. It matches
`/^[A-Z][A-Za-z0-9]*$/`, and only `feature.php` and the class file are required. The
autoloader serves `Nino\Modules\*` from `_nino/`, `_admin/`, `features/` and `app/`
in this order, so a feature never shadows a kernel module or a workbench screen and
`app/` never replaces a feature ([autoload
layout](recipes/runtime-module.md#autoload-layout)). `NINO_FEATURES_DIR`, defined
before `_nino/Nino.php` loads (the entry points carry the line commented out),
replaces the directory as a whole.

`features/` is server-side source: `.htaccess` and `router.php` deny it
([deployment](deployment.md#nginx-and-other-web-servers)), and a feature's browser
files are read off disk and bundled, never requested. A catalogue publishes one
directory per feature as a `.tar.gz` that its signed catalogue lists by digest, its
`tests/` left out ([a catalogue of your own](_admin.md#a-catalogue-of-your-own)).

## The manifest

`feature.php` lies beside the class file and returns an array;
`\Nino\Features::manifest()` reads and validates it. A manifest that breaks a rule
is skipped whole, with a warning that names the file and the reason, and so is a
second directory that claims a key an earlier one holds. The one of `Hello` is
`features/Hello/feature.php`:

```php features/Hello/feature.php
<?php
// The class is not declared: features/Hello/ can only serve \Nino\Modules\Hello
return [
	'key'					=> 'hello',
	'name'				=> [ 'en_US' => 'Hello World', 'de_DE' => 'Hallo Welt' ],
	'description'	=> [ 'en_US' => 'Greets the name its panel stores.', 'de_DE' => 'Grüßt den Namen, den sein Panel speichert.' ],
	// What the Description tab shows: all six sections, the empty ones too
	'manual'			=> [
		'shortcodes' => [ '[hello name="Ada" shout="yes"]' => 'Greets Ada, in capitals.' ], 'markup' => [],
		'routes' => [ '/hello' => 'The page of the greeting.' ], 'panel' => [ 'Hello World' => 'Who the shortcode greets.' ],
		'callbacks' => [], 'install' => [ 'templates/page-hello.tpl' => 'The page /hello renders.', 'elements/privacy.php' => 'Its section of the privacy policy.' ],
	],
	'category'		=> 'system',				// one of \Nino\Features::CATEGORIES
	'maturity'		=> 'Example',				// the badge beside the name, 24 characters at most
	'version'			=> '1.1.0',
	'nino'				=> '^1.8',					// the kernel whose vocabulary it uses
	'data'				=> [ '/data/hello.php' ],					// what a backup carries
	'assets'			=> [ '/features/Hello/assets/hello.css' ],	// into the site's style bundle
	'settings'		=> [
		'greeting'	=> [ 'type' => 'string', 'label' => 'Greeting', 'default' => 'Hello', 'maxlength' => 24 ],
		'longest'		=> [ 'type' => 'int', 'label' => 'Longest name', 'min' => 1, 'max' => 60, 'unit' => 'characters', 'default' => 40 ],
	],
	// A shortcode with a schema; the class has no componentHello(), so doShortcode() renders it
	'components'	=> [ 'hello' => [ 'label' => 'Greeting', 'source' => 'none', 'attributes' => [
		'name'	=> [ 'type' => 'string', 'default' => '', 'label' => 'Name' ],
		'shout'	=> [ 'type' => 'bool', 'default' => false, 'label' => 'Capitals' ],
	] ] ],
];
```

| Key | Rule |
|---|---|
| `key` | a slug (`/^[a-z][a-z0-9-]*$/D`), the lower-cased directory name where left out; `requires` and `/nino/features` name features by it |
| `name`, `description`, `label`, `hint`, the label of an `options` entry | a string or a `locale => string` map, `name` required: the panel shows features that are not active, whose fills are not loaded, so the words travel in the manifest. `\Nino\Features::localized( $value, $locale )` reads one: the locale asked for, else `en_US`, else the first entry, else `''` |
| `version`, `maturity` | `major.minor.patch`, a pre-release suffix allowed; a string or a `locale => string` map of at most 24 characters each |
| `nino` | the constraint on the kernel, `*` where left out: the floor, the kernel whose vocabulary the feature uses. What [The toolset](#the-toolset) marks **1.8**, the key `assets` and the renderer `doShortcode()` are 1.8: a feature that uses one of them says `^1.8`, and an older kernel refuses it. The vocabulary is that of `\Nino\Features::satisfies( $constraint, $version )`: `*`, a version (`1.2.3`, `1.2`, `1`), a comparison (`>=1.2`, `!=1.2.3`), a caret (`^1.0`: the same major version from there on; `^0.13` the same minor version) or tilde (`~1.2`: the same major version from 1.2; `~1.2.3` the same minor version from 1.2.3), parts joined by a comma or a space (all must hold) and alternatives joined by `\|\|`; a pre-release kernel counts as the release it precedes |
| `php`, `requires`, `data` | `php` => `ext`, the extensions it needs; the feature keys it cannot run without (its own is dropped; an activation activates them first); the files under `/data/` it owns, no `..` - what a backup carries |
| `assets`, `components`, `stacks`, `settings` | the subsections below and the chapter [Settings](#settings) |
| `module` | may name `\Nino\Modules\<Directory>` and nothing else, and the class file must exist |

Keys the kernel does not read are ignored, so a decorative key is not documentation.
`\Nino\Features::all()` answers every manifest by key, with `dir`, `module` and what
this installation knows: `active`, `installed`, `update` and `problems`.

### Assets

Style sheets and scripts for the website are listed under `assets` as virtual paths
below the feature's own `assets/` directory - a `.css` or a `.js` of a plain
relative name, no `..`; an entry that breaks one refuses the manifest. While the
feature is listed in `/nino/modules`, `\Nino\Features::registerAssets()` adds every
`.css` to the style bundle and every `.js` to the script bundle at every boot, ahead
of any module's `init()`, once each and in the order listed. A file for another
bundle goes through `\Nino\Html::addAsset()`.

### Components and stacks

A shortcode a feature brings stays a shortcode, and a builder cannot offer it. Give
it a schema and it is a **component** the Components module knows and a tool can
list; one that loops the elements of a type around its content is a **stack**. The
manifest declares them as `name => schema` ([Components](development.md#components)
has the schema), read by the same `validate()` as `addComponent()`. The renderer is
derived like the class: `component<Name>()` or `stack<Name>()`, the name in studly
caps (`componentNewsletterSignup()`), handed the arguments made ready - every
attribute a string, a bool `'1'` or `'0'`, the default where the shortcode says
nothing, and always `class` ([component recipe](recipes/component.md)). A component
whose class has no `component<Name>()` is rendered by `doShortcode()` where it has
one, so the shortcode by hand and the component are one method, as in `Hello`; a
stack keeps the strict rule, and with neither renderer a component is a warning and
left out. The Components module registers both at every boot while the feature is
listed in `/nino/modules`, before its `init()` (activation lists a feature after
that module). A component is registered one way, by the manifest or by
`addComponent()` in `init()`; [`\Nino\Feature::shortcode()`](#the-toolset) keeps a
plain registration from meeting the manifest's.

### The manual

The `manual` key is what the **Description** tab of the feature's screen shows:
reference, not prose, every feature answering the same questions in the same order.
An entry is a **handle**, what somebody types and never translated, and a **line**,
a string or a `locale => string` map. The sections are
`\Nino\Features::MANUAL_SECTIONS`, drawn in this order and when empty ("no
callbacks" is an answer):

| Section | What belongs there |
|---|---|
| `shortcodes` | what a page writes between brackets, one line per form worth naming |
| `markup` | what a template writes that is no shortcode: an attribute, a class a script looks for, a `<script type="text/plain">` |
| `routes` | the addresses the feature answers |
| `panel` | the workbench screens it brings, by the name in the rail |
| `callbacks` | what it hooks into, by the callback key |
| `install` | what activation puts in the project - a template, an element type, the words it merges |

A plain list item has no handle; a handle is at most 120 characters, a line 1000, a
section 40 entries. The description, the settings and the PHP a feature exposes have
no section: the manifest says the first two, the docblocks the third. The older
form, one string or a `locale => string` map, is still read; do not write it.

### Categories

One category per feature, the coarse "what is this for" the Features panel groups
and filters by (`\Nino\Features::CATEGORIES`). Where a feature could sit in two,
take the first that fits: `security` (it protects access or touches personal data),
`marketing` (being found or measured), `communication` (messages with people),
`content` (it brings content or makes it reachable), `ui` (it only changes how
existing things look); what is left is `system`. Any slug passes
(`/^[a-z][a-z0-9-]{0,23}$/`), so a feature written for a newer catalogue stays
installable; a manifest without `category` is valid.

## The class

The class is an ordinary runtime module in `Nino\Modules`: `init()` registers
behaviour and outputs nothing, a response goes through `\Nino\Http`, and the
technical route belongs to the module (the [runtime module
recipe](recipes/runtime-module.md) applies unchanged). The kernel calls these
methods on a feature's classes:

| Method | Called | The kernel does with the answer |
|---|---|---|
| `init( array &$appData ): void` | by `\Nino\Modules::callModules( $appData, 'init' )` at every boot, for each class in `/nino/modules` in its order, after the manifests' assets are registered | nothing |
| `adminPanels( array &$appData ): array` | by `\Nino\Modules::collect()` whenever the workbench builds its registry | merges the panel classes into it ([The panel](#the-panel)) |
| `upgrade( array &$appData, string $fromVersion ): bool` | by `\Nino\Features::activate()` ([Updates](#updates-and-the-upgrade-hook)) | `false` refuses the update, anything else goes on |
| `component<Name>()`, `doShortcode()`, `stack<Name>()` | by the Components module when the shortcode is rendered | renders it ([Components and stacks](#components-and-stacks)) |
| a callback of `'/nino/admin/restore'` | by the Backups panel and the recovery page, with `{ dataDir, staging }` | none ([Data and restore](#data-and-restore)) |
| on a panel class: `log( $action, $data )`, `summary( &$appData )`, `scopes( &$appData )` | after an action that completed; by the Dashboard; by the roles tab | the line of the activity log (`''` for none); a tile `{ value, label }`; the scoped permissions |

There is no install, activate, deactivate or uninstall hook: a feature lies in the
directory, an activation applies its unit and lists its class, and nothing runs on
the way out. The class of `Hello` is `features/Hello/Hello.php`; its markup is the
template `templates/hello.tpl`, `<p class="nino-hello[[classes]]">[[text]]</p>`:

```php features/Hello/Hello.php
<?php
declare(strict_types=1);

namespace Nino\Modules {

	class Hello {

		public const string STORAGE = '/data/hello.php';

		public static function init( array &$appData ): void {

			// false: the manifest's component owns the name and renders it through doShortcode()
			\Nino\Feature::shortcode( $appData, 'hello', [ self::class, 'doShortcode' ] );

			// A route of the feature's own, only where the address is free
			if( isset( $appData['/nino/http/routes']['GET://hello'] ) === false )
				$appData['/nino/http/routes']['GET://hello'] = [ 'uri' => '/hello', 'body' => '[template /templates/page-hello]' ];
		}

		public static function adminPanels( array &$appData ): array {
			return [ \Nino\Modules\Hello\Admin::class ];
		}

		// 1.0.0 kept the name as 'who'
		public static function upgrade( array &$appData, string $fromVersion ): bool {

			if( version_compare( $fromVersion, '1.1.0', '>=' ) === true || \Nino\Filesystem::fileExists( $appData, self::STORAGE ) === false )
				return true;

			return \Nino\Filesystem::mutate( $appData, self::STORAGE, static function( mixed $state ): array {

				$state = is_array( $state ) === true ? $state : [];
				$state['name'] ??= $state['who'] ?? '';
				unset( $state['who'] );

				return $state;
			} );
		}

		public static function doShortcode( array &$appData, array $args ): string {

			$name = trim( (string) ( $args['name'] ?? '' ) );
			$text = \Nino\Features::setting( $appData, 'hello', 'greeting', 'Hello' ). ', '. ( $name === '' ? self::name( $appData ) : $name ). '!';

			return str_replace(
				[ '[[classes]]', '[[text]]' ],
				[ \Nino\Feature::classes( $args ), \Nino\Modules\Components::escape( \Nino\Feature::flag( $args, 'shout' ) === true ? mb_strtoupper( $text ) : $text ) ],
				\Nino\Feature::template( $appData, '/features/Hello/templates', 'hello' )
			);
		}

		public static function name( array &$appData ): string {

			$stored = \Nino\Filesystem::getFileContent( $appData, self::STORAGE, [] );

			return is_array( $stored ) === true && is_string( $stored['name'] ?? null ) === true && $stored['name'] !== '' ? $stored['name'] : 'World';
		}
	}
}
```

`init()` registers `[hello]` through `\Nino\Feature::shortcode()`, which registers
nothing where the Components module already owns the name: here the manifest
declared the component, the answer is `false` and `doShortcode()` is its renderer.
The route is registered only where the address is free: a route in `config.php` is a
page somebody made. It goes with the feature; a menu shows it once the Navigations
panel has put it into one and it has a name (`/_nino/webpage<uri>/name`, written in
the Routes panel under *Routes of features and modules*).

## The toolset

What a feature once wrote for itself, the kernel has once, and the docblock of a
member is its reference. A method whose name starts with `_` is internal, every
other is public; **1.8** marks what kernels before it lack, so a feature that calls
one says `^1.8`. `\Nino\Feature` **1.8** (not `\Nino\Features`, the contract) holds
the helpers of a feature's own code, static methods that render no markup:

| Call | What it does |
|---|---|
| `template( $appData, $directory, $name )` | the `<name>.tpl` of a templates directory (`'/features/Hello/templates'`), ready for `str_replace()` over its `[[tokens]]`; one that is missing or empty is a warning and `''` |
| `classes( $args )` | the `class` attribute of a component's markup, from the argument every shortcode may carry: `''`, or a space and the classes, escaped |
| `flag( $args, $name, $default )` | a flag of a shortcode without a schema: a bare word is on, `name="no"` is read by the words a component's bool is |
| `shortcode( $appData, $name, $callback )` | a plain shortcode, unless the Components module owns the name (the manifest declared a component or a stack): then `false` |
| `origin( $url )`, `allowOrigin( $request, $directive, $url )` | an address as a Content-Security-Policy source - https, no credentials, no ip literal - and the call that adds it to one directive of this response |
| `count( $appData, $name, $key, $limit, $window )`, `spent()`, `forget()` | tries per client in the window, kept in `/data/<name>.php` under a hashed key: count one and learn whether it is within budget; ask without counting; let a client go |
| `slug( $text, $max )` | a name made into a slug, the letters ascii lacks written as `TRANSLITERATIONS` says; `Nino.slug()` is its twin |

The kernel classes a feature calls, by home:

| Class | Members |
|---|---|
| `\Nino\Features` | `setting( $appData, $key, $name, $default )`, `settings()`, `saveSettings()` ([Settings](#settings)); `get()` one feature as `all()` answers it; `localized()`, `satisfies()` |
| `\Nino\Filesystem` | `getFileContent()`, `putFileContent()` a whole file by its virtual path; `mutate( $appData, $path, $fn, $default )` every read-modify-write: it locks, reads, calls `$fn( $state )` and writes what that answers, `null` aborts and `mutate()` answers `false` - a file whose shape may change carries a `format` key; `fileExists()`; `path()`, the real path, to `glob()` a directory; `forceDir()`, `removeDir()`; `getDir()`, `getPublicDir()`, `getPublicPath()` |
| `\Nino\AppData` | `writeContentData( $appData, $keys )` persists the given top-level keys of `config.php`; `stored()` **1.8** reads one as the file holds it |
| `\Nino\Http` | `fail( $request, $status, $error, $code, $params, $field )` and `ok( $request, $body )` end a handler; `redirect()`, `allowSource()` a policy directive widened by one source, `pages()`, `matchesPath()`, `answersHtml()` for a sitemap or a protected area, all **1.8**; `requestRoute()`, `getRequest()`, `getClientIp()` |
| `\Nino\Html` | `renderHtml()` a string as a page is rendered; `renderTextfill()`, `resolveTextfill()` one fill; `addShortcode()`, `addAsset()`; `addFills()`; `getFills( $appData, $locale )` every fill of a language, the argument **1.8**; `removeFill()`, `absoluteUrl()` **1.8**; `sanitizeHtml()` |
| `\Nino\Text` | `entries()`, `entry()`, `isGrammarKey()`, `sanitizeValue()`: the project's texts and their keys |
| `\Nino\Form` | `forms()`, `form()`, `posted()`, `validate()`, `entries()`; `blocked()` **1.8** whether the CSRF guard refused the request |
| `\Nino\Images` | `getUrl()`, `getSlot()`, `getSlots()`, `setSlotFilename()`, `setSlotAlt()`, `cleanAlt()`, `process()`, `fit()`, `limits()`, `reject()`, `read()`, `restore()`, `delete()` |
| `\Nino\Mail` | `send()`; `rateLimited()` **1.8** whether the last send was refused by the cap per ip |
| `\Nino\Locales` | `getCurrentLocale()`, `getNativeLocale()`, `getAvailableLocales()`, `verifyLocale()`, `useLocale()` for the rest of the request |
| `\Nino\Modules\Components` | `components()`, `stacks()`, `defaults()`, `addComponent()`; `escape()` a value into html or an attribute; `renderStack()`, `cell()`, `element()` for a stack |
| `\Nino\Admin\Admin` | `guardPerm()`; `postData()` the json of the posted `data`; `postString()` **1.8** one posted value as a string, never a cast; `cleanInt()`, `failType()`, `uploadError()`; `sessionLocale()`, `textFills()`; `\Nino\Admin\Panels::relative()` |
| beside them | `\Nino\Callbacks::registerCallback()`; `\Nino\Elements::queryElements()`, `getElement()`, `getElementModel()`; `\Nino\Auth::getCurrentUser()`, `checkPermission()`; `\Nino\Fetch::get()`, the one way to the network; `\Nino\Modules\Jstext::publish()` fills for a public script; `\Nino\Modules::callModules()` |

In the browser:

| Where | Members |
|---|---|
| `Nino` | `slug( text, max )`, `clipboard( text )` (a promise of `true` or `false`), **1.8**; `content.getText( key )`; `dir`; `events.bindCallback( evt, cb )`: a callback bound after the event fired runs at once, every callback runs on its own and an error is thrown again from a timer, so one cannot stop the next, and the listeners are passive (**1.8**) |
| `Nino.ui`, the site only | `reducedMotion()`, `options( el, prefix, schema )` the `data-<prefix>-<name>` attributes of an element read against a schema, `whenVisible( el, callback )`, all **1.8**. A site script never reaches `Nino.adminUi` and a panel script never `Nino.ui`; what both need is on `Nino` |
| `Nino.adminUi` | `panel( spec )`, `levels( prefix, names )`, `hint()`, `textField()`, `el()`, `button()`, `localeSelect()` **1.8**; `api.call()`, `api.errorText()`, `showError()`, `status()`, `format()`, `text()`, `emptyState()`, `notice()`, `panelHead()`, `contextBar()`, `actionBar()`, `listActions()`, `selectField()`, `switchField()`, `numberField()`, `elementList()`, `stringList()`, `table()`, `buttonRow()`, `choiceDialog()`, `checkImage()`, `limits()` |
| `Nino.admin`, the shell | `dirty` (`register`, `watchForm`, `snapshot`, `isDirty`, `guard`, `refresh`), `router` (`set`, `current`, `go`, `leave`), `sessionLocale`, `assetUrl()`, `publicUrl()`; a panel script reads none of it at load, `panel()` and `levels()` look it up when asked |
| CSS | the site: the custom properties at `:root` of `_nino/Nino.css` are the tokens a feature's stylesheet uses, written without a fallback (`var( --space-2 )`: the token is there, or the kernel is older than the feature's floor); `.nino-btn` with its `--` variants and `.nino-sr-only`. The workbench: the classes of the [panel recipe](recipes/admin-panel.md#designing-an-admin-frontend), among them the four of 1.8 - `.nino-admin-btn--small`, `.nino-admin-actions`, `.nino-admin-badge[data-state]`, `.nino-admin-move`; a panel stylesheet opens with `@layer nino.system, nino.tool, nino.local;` and writes into `nino.tool`, scoped to its own root |

## The panel

A panel is one screen of the workbench, the class `adminPanels()` answers while the
feature is active: it appears with the next load of the workbench after the
activation, goes with the next one after the deactivation, and always stands in the
rail's **Features** group whatever its `nav()` names. Its permission is offered on
the roles tab; the **Editor** role does not receive it by itself. The class of
`Hello` is `features/Hello/Admin/Admin.php`:

```php features/Hello/Admin/Admin.php
<?php
declare(strict_types=1);

namespace Nino\Modules\Hello {

	class Admin {

		public static function perm(): string {
			return '/_admin/hello/manage';
		}

		public static function actions(): array {
			return [ 'hello/list' => [ self::class, 'apiList' ], 'hello/save' => [ self::class, 'apiSave' ] ];
		}

		public static function nav(): array {
			return [ 'hello', '/_admin/hello/nav', 90 ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			\Nino\Http::ok( $request, [ 'name' => \Nino\Modules\Hello::name( $appData ), 'longest' => self::_longest( $appData ) ] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::perm() ) === false )
				return;

			$name = trim( \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'name' ) );

			if( mb_strlen( $name ) > self::_longest( $appData ) )
				\Nino\Http::fail( $request, 400, 'the name is too long', 'hello_long', [ self::_longest( $appData ) ], 'name' );
			else if( \Nino\Filesystem::mutate( $appData, \Nino\Modules\Hello::STORAGE, static fn( mixed $state ): array => array_merge( is_array( $state ) === true ? $state : [], [ 'name' => $name ] ) ) === false )
				\Nino\Http::fail( $request, 500, 'could not write the name' );
			else
				\Nino\Http::ok( $request, [ 'name' => \Nino\Modules\Hello::name( $appData ) ] );
		}

		private static function _longest( array &$appData ): int {
			return (int) \Nino\Features::setting( $appData, 'hello', 'longest', 40 );
		}
	}
}
```

- Every action guards itself with `\Nino\Admin\Admin::guardPerm()` and the panel's
  permission, in its first lines, whatever the routing did. The uri (`hello`) names
  the link, the pane, the action prefix and the script's namespace
  `Nino.admin.hello`; a taken uri is dropped with a warning.
- A failure a person can cause carries a **code**: the panel's slug with its hyphens
  written as underscores, then a word (`hello_long`; `my_feature_empty` for
  `my-feature`), in lower-case letters, digits and underscores. Its sentence is the
  panel's text under `[[/_admin/error/<code>]]` in both languages, `%s` where a
  param goes; `Http::fail()` carries the English message for a log, the code, the
  params and the `field` refused, and `Nino.adminUi.api.errorText()` makes the
  sentence of them. A failure nobody can cause has no code and shows the server's
  message with the status in front. The kernel's scan for codes without a sentence
  does not reach `features/`, so a feature's test asks, as `Hello`'s does.
- `text()` names a directory of `<locale>.php` files, one for each interface
  language, merged into the workbench's fills while the feature is active: the nav
  label, the labels and errors its script reads and, for the code `hello_long`,
  `[[/_admin/error/hello_long]]` => `The name may have up to %s characters.`

The script runs on `Nino.adminUi.panel()`: it asks for `hello/list` when the pane is
first shown, calls `render( data, panel )` with the answer and says a failed load in
the mount (`/_admin/hello/error/load` unless `error` names another key). `dirty` is
the entry for `Nino.admin.dirty`: the shell asks Save, Discard or Cancel before it
throws away what the field holds, and `save( done )` ends in `done( true )` or
`done( false )` on every way it can. The script is `features/Hello/assets/admin.js`:

```js features/Hello/assets/admin.js
( function(wn) {

	wn.Nino.admin = wn.Nino.admin || {};

	let input = null;
	let line = null;
	let saved = '';

	const save = function( done ) {

		line.saving();

		Nino.admin.hello.call( 'save', { name : input.value }, function( status, response ) {

			if( status !== 200 ) {
				line.error( status, response, '/_admin/hello/error/save' );
				done( false );
				return;
			}

			saved = input.value = response.name;
			line.saved();
			if( typeof Nino.admin.dirty === 'object' )
				Nino.admin.dirty.refresh();
			done( true );
		} );
	};

	Nino.admin.hello = Nino.adminUi.panel( {
		uri		: 'hello',
		mount	: 'hello-list',
		dirty	: { isDirty : function() { return input !== null && input.value !== saved }, save : save, discard : function() { input.value = saved } },
		render	: function( data, panel ) {

			const mount = panel.mount();
			const field = Nino.adminUi.textField( { label : Nino.content.getText('/_admin/hello/label/name'), value : data.name, maxLength : data.longest } );
			const bar = Nino.adminUi.actionBar( Nino.adminUi.el( 'div' ) );

			input = field.querySelector('input');
			input.name = 'name';
			saved = input.value;

			line = Nino.adminUi.status( Nino.adminUi.el( 'span' ) );
			line.bind( mount );

			bar.appendChild( line.element );
			bar.appendChild( Nino.adminUi.button( Nino.content.getText('/_admin/hello/label/save'), function() { save( function() {} ) }, { className : 'nino-admin-btn-primary' } ) );

			mount.innerHTML = '';
			mount.appendChild( field );
			mount.appendChild( bar );
		},
	} );

} )(window);
```

The script never renders a literal sentence (every word is a fill read through
`Nino.content.getText()`), never posts by hand (`panel.call()` wraps
`Nino.adminUi.api.call()`) and reads no member of the shell at load. The router,
drill-down levels, tables, scoped permissions, the design rules and a panel's own
template and icon are the [panel recipe](recipes/admin-panel.md).

## The install unit

`install/manifest.php` has the shape of the unit of a module in the setup wizard
(the [installer package recipe](recipes/installer-package.md) lists its keys,
[Library Format](setup.md#library-format) the files). An activation reads `routes`,
`templates`, `files`, `elementTypes`, `elements`, `blacklist` and `config` from it,
and `text/global.php` and `text/<locale>.php` for every available locale; `key`,
`label`, `moduleClass`, `requiresModules`, `preset`, `navs` and `imageSlots` are the
wizard's alone. The unit of `Hello`, `features/Hello/install/manifest.php`, copies
the template of the page `/hello` and adds a section of the privacy policy:

```php features/Hello/install/manifest.php
<?php return [
	'templates'	=> [ 'page-hello.tpl' ],
	'elements'	=> [ 'privacy' => 'elements/privacy.php' ],
];
```

The unit is applied **add-only**, on every activation of the feature - the first,
each update, a re-activation: a route key `config.php` has, a template `templates/`
has, a file that is there and a text key that exists stay as they are, and a
`config` default is written only where the project has no value. A copy the project
changed stays, one it deleted comes back; the wizard applies a unit with overwrite
on through the same `\Nino\Features::applyUnit()`. So a unit never relies on being
applied again to fix a file, and never promises an uninstall; a route the feature
owns while it is active belongs in `init()`, a visitor page in the unit's `routes`.
The words of a unit live in `/feature/<key>/<part>/<name>` (the feature's own
function) and `/template/<file name without .tpl>/<part>/<name>` (a word only a
template of the unit reads: `page-hello.tpl` reads
`/template/page-hello/intro/title`); a unit never delivers a `/project` key ([the
key grammar](development.md#the-key-grammar)).

A feature that processes personal data brings its own section of the privacy policy
with the `elements` key, a map of an element type's uri to a file of the unit. The
type `privacy` is the Legal module's; the file has no `model`, so it adds to the
type and never creates it. `\Nino\Elements::seed()` only adds: an element that
exists, a section the editor changed and one deleted for good stay as they are, and
a feature that is switched off leaves its section in place - the deactivation dialog
and the Dashboard say so, and the editor may hide it. The file of `Hello` is
`features/Hello/install/elements/privacy.php`:

```php features/Hello/install/elements/privacy.php
<?php return [
	'*' => [ 'hello' => [ 'order' => 860 ] ],
	'en_US' => [ 'hello' => [
		'title' => 'Hello World',
		'text'  => '<p>The Hello World feature stores one name, the one its panel saves. Nothing about a visitor is stored. Questions: #/project/company/contact/email#</p>',
	] ],
];
```

Write the section in your own words, one entry for each language the feature ships,
stating only what the code does; a placeholder names a fact of the website. It is a
starting point and no legal advice, and the feature's manual says so. `order` is the
position, a hundred apart so a section fits between two: the module's own sections
stand in 100-399, cookies and consent in 400-499, contact, forms and mail in
500-599, statistics in 600-699, embedded content in 700-799, other features in
800-899, the closing section at 900.

## Settings

A setting is a form control the Features panel renders and the kernel validates. The
manifest declares each by name (`/^[a-z][a-zA-Z0-9]*$/`) with a schema:

| Key | For | Rule |
|---|---|---|
| `type` | all | one of `\Nino\Features::SETTING_TYPES`: `bool`, `int`, `string`, `text`, `email`, `url`, `select`, `secret`, `lines` |
| `label`, `hint`, `required` | all | a string or a `locale => string` map (the label is the name where left out); `true` refuses an empty value |
| `default` | all but `secret` | has to pass its own schema; without one the type's zero value answers (`false`, `min` or `0`, `[]`, `''`) |
| `min`, `max`, `unit` | `int` | ints, `min` not above `max`; the word the number is counted in, written in brackets behind the label: `seconds`, `minutes`, `hours`, `days`, `weeks`, `months`, `items`, `uris`, `per-hour`, `percent` and `characters` have a word in both languages (`[[/_admin/common/unit/<unit>]]`), any other is written as it stands |
| `maxlength`, `pattern` | `string`, `text`, `secret`, `email`, `url` | `maxlength` 1 to 1000 (`text`: 10000), the ceiling where left out; `pattern`, for `string` only, a valid regular expression |
| `options` | `select` | a non-empty map `value => label` |

A feature reads its settings through the kernel, never out of the array:
`\Nino\Features::setting( $appData, 'hello', 'longest', 40 )` answers one,
`settings( $appData, 'hello' )` all - the stored value, else the default, else the
type's zero value, and only for names the schema declares: a stray key in
`config.php` is no setting, a stored value that no longer validates is not handed
over, and `setting()` answers your `$default` for a name the schema lacks. The
values live under `/nino/features` in `config.php`, per feature as
`key => { version, settings }`:

```php
'/nino/features' => [ 'hello' => [ 'version' => '1.1.0', 'settings' => [ 'greeting' => 'Hi', 'longest' => 24 ] ] ],
```

The form is on the feature's screen in the Features panel; a feature that also has a
panel gets a **Settings** tab in it, on the same actions and the permission
`/_admin/features/manage`, without a line of code. A form posts strings and gets the
real types back. `saveSettings( $appData, $key, $posted )` validates every setting
before any is written and answers `name => message` for each one it rejected, `[]`
when it saved; a setting the form did not send keeps its value, a `secret` posted as
`''` keeps the stored one and as `null` clears it.

## Updates and the upgrade hook

A feature is updated by replacing its directory with the new release and activating
it again; the Features panel offers **Update** for an active feature whose manifest
version differs from the recorded one. The unit adds what is new and leaves what the
project has; then, if the class has it,
`upgrade( array &$appData, string $fromVersion ): bool` runs - when a version is
recorded that differs from the manifest's, whether the feature is on or was switched
off and its directory replaced in the meantime - with the recorded version, after
the unit and before the new version is recorded. `false` refuses the update and the
record stays; any other answer lets it go on, and a class without the method is
updated without a migration. A request that already loaded the previous version's
class refuses the activation; the panel activates again in a request of its own.

`\Nino\Features::activate( $appData, $key )` answers `true`, or why not: a feature
with `problems` (all named), a requirement that failed, a unit file it cannot copy;
a cycle of requirements ends where it began. The unit is applied to the routes
`config.php` holds, not this request's own, so a route `init()` registers is not
written; activating again at the same version changes only what the unit adds back.
`deactivate( $appData, $key )` is refused while an active feature requires the one,
and changes nothing for a feature that is off.

- Migrate only what the feature owns: its `data/` files and its own config keys,
  through `\Nino\Filesystem::mutate()` as `Hello`'s hook does, returning its result:
  a callback that aborts answers `false`, which refuses. The activation writes its
  own keys as differences against `config.php` as it stands, so a hook that migrates
  `config.php` with `mutate()` is not undone.
- Be idempotent for the versions it handles: a refused update is asked again at the
  next activation. Bump `version` with every release a project should tell apart:
  the hook runs on any difference, a lower manifest version too.

### Data and restore

What a feature writes under `data/` belongs to the project: the daily backup carries
the files `data` in the manifest names. A feature whose restore has to merge rather
than overwrite - a removal that must survive an older backup, a counter that must
not go backwards - registers a callback of `'/nino/admin/restore'` in `init()`
(`\Nino\Callbacks::registerCallback()`). The Backups panel and the recovery page
call it, while the feature is active, with `{ dataDir, staging }`, the live `data/`
directory and the extracted backup; it rewrites its own files in the staged copy
before that is copied over the live one.
[`Newsletter::callbackRestore()`](https://github.com/dapeio/nino-features/blob/main/features/Newsletter/Newsletter.php)
of the catalogue is the reference.

## The feature's own test

A feature's test travels with it, `features/<Name>/tests/<key>-smoke.php`, and runs
against a real checkout with a throwaway project in it, not against mocks: only Nino
can say whether the feature kept its contract. It finds the checkout three levels
up, or the one `NINO_ROOT` names, which is how the same test runs against another
Nino from outside the checkout. The bootstrap is three lines - `NINO_FEATURES_DIR`
has to exist before the harness loads the kernel, and the guard keeps one defined
before the test was included - and `tests/harness.php` gives nine helpers:

| Helper | What it does |
|---|---|
| `check( $label, $condition )` | one assertion, one line of output |
| `ninoSandbox( $name )`, `ninoSandboxDir( $appData )` | a fresh, isolated project directory - `$appData` with every path in it, the locales `de_DE` and `en_US`, no modules, the test switches on what it tests - and its path |
| `ninoWarnings()`, `ninoErrors( $run )` | the warnings raised since the last call, cleared as they are read - assert on the one you expect; every error one call raises, the level with it |
| `ninoCall( $appData, $target, $data, $action )` | one workbench request as a panel method sees it: `$_POST['data']` is the json of `$data`; answers `[ status, body, request ]` |
| `ninoUser( $appData, $mail, $perms )` | an account that exists and is signed in, full access unless `$perms` says otherwise |
| `ninoVisit( $appData, $uri, $method, $server )` | one request of the site through `Http::request()`: the request built, the response seeded with the default headers, the callbacks of `/nino/http/request` run - no route resolved, nothing rendered |
| `ninoDone( $appData )` | the summary with the seconds, the sandbox removed, the exit status |

The test of `Hello` is `features/Hello/tests/hello-smoke.php`:

```php features/Hello/tests/hello-smoke.php
<?php
declare(strict_types=1);

// The Hello feature's contract test. Runs against the checkout three levels up,
// or the one NINO_ROOT names
$root = getenv( 'NINO_ROOT' ) ?: dirname( __DIR__, 3 );
defined( 'NINO_FEATURES_DIR' ) === true || define( 'NINO_FEATURES_DIR', dirname( __DIR__, 2 ) );
require $root. '/tests/harness.php';

$appData = ninoSandbox( 'hello' );
$appData['/nino/modules'] = [ '\\Nino\\Modules\\Components' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native' ] );

check( 'the manifest validates without a warning', is_array( \Nino\Features::manifest( dirname( __DIR__ ) ) ) && ninoWarnings() === [] );
check( 'activation lists the class, records the version and applies the unit', \Nino\Features::activate( $appData, 'hello' ) === true
	&& \Nino\Features::get( $appData, 'hello' )['installed'] === '1.1.0' && is_file( \Nino\Filesystem::path( $appData, '/templates/page-hello.tpl' ) ) === true );

\Nino\Html::init( $appData );
\Nino\Modules::callModules( $appData, 'init' );
check( 'the component is registered from the manifest and renders through the template', isset( \Nino\Modules\Components::components( $appData )['hello'] ) === true
	&& \Nino\Html::renderHtml( $appData, '[hello name="Ada" shout="yes" class="wide"]' ) === '<p class="nino-hello wide">HELLO, ADA!</p>' );

ninoUser( $appData, 'dev@example.com' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'name' => 'Grace' ], 'hello/save' );
check( 'hello/save stores the name and hello/list answers it', $status === 200 && $body['name'] === 'Grace'
	&& ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'hello/list' )[1] === [ 'name' => 'Grace', 'longest' => 40 ] );

[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'name' => str_repeat( 'x', 41 ) ], 'hello/save' );
check( 'a name too long is refused with the code, its limit and the field', $status === 400 && $body['code'] === 'hello_long' && $body['params'] === [ 40 ] && $body['field'] === 'name' );
foreach( [ 'en_US', 'de_DE' ] as $locale )
	check( "...and the code has its sentence in $locale", str_contains( \Nino\Admin\Admin::textFills( $appData, '/features/Hello/text', $locale )['[[/_admin/error/'. $body['code']. ']]'] ?? '', '%s' ) );

// The project as 1.0.0 left it; all() keeps its answer for the request, so it is dropped to be asked again
\Nino\Filesystem::putFileContent( $appData, '/data/hello.php', [ 'who' => 'Ada' ] );
$appData['/nino/features']['hello']['version'] = '1.0.0';
\Nino\AppData::writeContentData( $appData, [ '/nino/features' ] );
unset( $appData['./nino/features/all'] );
check( 'a recorded version behind the manifest runs the upgrade hook, which moves who to name', \Nino\Features::activate( $appData, 'hello' ) === true
	&& \Nino\Filesystem::getFileContent( $appData, '/data/hello.php', [] ) === [ 'name' => 'Ada' ] );
check( 'deactivation takes the panel away and keeps the data', \Nino\Features::deactivate( $appData, 'hello' ) === true
	&& isset( \Nino\Admin\Admin::panels( $appData )['hello'] ) === false && \Nino\Modules\Hello::name( $appData ) === 'Ada' );

ninoDone( $appData );
```

Test what a caller can see, as `Hello`'s test does: the manifest, the activation and
its unit, the shortcode and the endpoints, a refusal with its code and its
sentences, the panel there while the feature is active and gone after, the upgrade
hook, the data that deactivation keeps. `php features/Hello/tests/hello-smoke.php`
runs it (`NINO_ROOT=...` before it for another Nino), `php tests/features-smoke.php`
is the kernel's contract test beside it. `ninoErrors()`, `ninoCall()`, `ninoUser()`
and `ninoVisit()` are 1.8: a test that uses them needs a floor of `^1.8`.

CI runs the contract test and then every feature's own test (a loop over
`features/*/tests/*-smoke.php`, guarded because a checkout ships no feature). The
catalogue's `bin/check.sh` does the same against a checkout of Nino: its
`bin/applicable.php` names the features whose `nino` constraint the checkout
satisfies, so the floor decides on which kernels a test runs.

A feature's script is tested over the same stand-ins: `tests/<key>-js-smoke.js`
takes `check()`, `done()`, `load()`, `element()` and `documentOf()` from the
checkout's `tests/harness.js` (1.8; `NINO_ROOT` or three levels up) and `load()`s
`_admin/assets/Nino.admin.js` and the feature's own script over a stand-in for
`Nino` that keeps the requests. `load()` reads from the root of the checkout, so a
feature outside it names its script as `path.relative( root, ... )`. The catalogue's
JavaScript tests carry a stand-in of their own and run with any Nino.

## What a feature does not do

- It does not install or uninstall itself: deactivation only removes the class from
  `/nino/modules`; copied files, texts, routes, settings and data stay.
- It does not overwrite: neither activation nor an update replaces a file, a route
  key or a text key the project has.
- It does not reach the network on a page request, and never but through
  `\Nino\Fetch`; Nino itself makes no outbound request unless someone presses
  **Refresh catalogue** or **Install** ([the workbench manual](_admin.md#features)).
- It does not write output (no `echo`, `header()`, `http_response_code()` or `exit`;
  the response is `Http::ok()`, `fail()` or `redirect()`) and does not build markup
  in PHP: a template, read by `\Nino\Feature::template()`, holds it (an icon and a
  one-line fragment declared once as a property are the exceptions).
- It does not depend on anything outside the public API of `\Nino\*` and its own
  directory: no `_`-method, no class of `app/`, no copy of kernel code; a feature
  that needs what the kernel lacks is blocked, not patched.
- It does not read-modify-write a file with two calls (`Filesystem::mutate()`),
  widen the Content-Security-Policy to `*` or an unsafe keyword
  (`Feature::allowOrigin()` adds one origin) or register what its manifest declares twice.
