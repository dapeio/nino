# Nino repository guide for AI coding agents

This file is the operational specification for AI agents that modify Nino. It
is intentionally explicit and repetitive enough for small models. Follow it
before copying patterns from memory or from another framework.

Nino is a filesystem-based PHP website framework. It has no database, Composer
dependency, JavaScript build step, component framework, or plugin manager.
Prefer readable project files and the public Nino APIs over new abstractions.

## 1. Rule strength and source of truth

The words **MUST**, **MUST NOT**, **SHOULD**, and **MAY** are normative.

1. The current user request and repository instructions have priority.
2. This file defines repository-wide defaults.
3. Existing source code and tests define the current runtime contract.
4. Human manuals in `docs/` explain intent and operation.
5. If documentation and source disagree, inspect the tests and implementation,
   follow the current behavior, and update stale documentation in the same
   change when that is within scope.

Do not invent an API because its name seems plausible. Search for the actual
method, its signature, and at least one current call site.

## 2. Required workflow for every change

Before editing:

1. Read the complete user request and list its observable requirements.
2. Run `git status --short --branch`. Preserve all unrelated user changes.
3. Find the nearest implementation, test, and documentation with `rg`.
4. Read complete functions or classes around every line that will change.
5. Decide which extension type in the next section actually applies.
6. Identify authentication, CSRF, validation, persistence, escaping,
   concurrency, locale, and backward-compatibility consequences.
7. Define a test that fails for the old behavior and passes for the new one.

While editing:

- Make the smallest coherent change.
- Follow local formatting and naming. Do not reformat unrelated code.
- Use public APIs; methods beginning with `_` are internal unless the class
  itself is being maintained.
- Keep PHP, JavaScript, CSS, templates, tests, and documentation synchronized.
- Do not add a runtime dependency or build step without explicit approval.
- Do not silently delete, rename, or migrate existing project data.
- Never trust request, file, template, element, or external-service data.

After editing:

1. Review `git diff --check` and the complete diff.
2. Run syntax checks for every changed PHP and JavaScript file.
3. Run the targeted smoke test and all tests named in the relevant recipe.
4. Run the complete suite for shared kernel, security, filesystem, installer,
   or cross-interface changes.
5. Report changed files, behavior, tests, and any remaining limitation.

If the user requests a patch-only handoff, do not commit. Generate a plain
`git diff --binary` patch against the requested base and verify it with
`git apply --check` in a clean checkout of that exact base.

Repository-owner delivery policy: do not create a commit and do not push.
Deliver the changes as a named `.patch` file unless the user explicitly asks
only for an inline answer. A request to “implement”, “finish”, or “apply” is not
permission to commit.

## 3. Choose the correct extension type

Do not use “page”, “template”, “module”, and “admin module” interchangeably.

| Goal | Correct extension |
| --- | --- |
| Add developer-only CRUD or diagnostics inside `/_admin` | Admin backend and frontend tab |
| Add behavior to public requests or a new shortcode | Runtime module |
| Make a feature selectable and copyable during `/_install` | Installer module package |
| Add an insertable visual building block to `/_templates` | Section preset |
| Add a complete starting page to the installation library | Page library unit |
| Add reusable HTML+ included by other templates | Reusable `.tpl` template |
| Map a public HTTP URL to output | Route |
| Store repeated structured content | Element type and Elements |
| Store a short editable value | Textfill |

These pieces can cooperate but remain separate:

- A **route** maps an HTTP method and public URI to a response.
- A **page template** is a `templates/page-*.tpl` file rendered by a route.
- A **section preset** is source copied into a page template by the Template
  Builder. It has no runtime identity after insertion.
- A **runtime module** registers PHP behavior at boot.
- An **installer module package** selects, copies, and configures a runtime
  feature. It is not the runtime feature itself.
- An **Admin backend** is a developer UI extension. It is not an entry in
  `/nino/modules`.

When a feature spans types, implement each layer explicitly. For example, a
catalog can need a runtime module, an installer package, an element type, a
reusable template, and an Admin tab. One of those does not automatically create
the others.

## 4. Repository map and ownership

Important source directories:

| Path | Ownership |
| --- | --- |
| `_nino/Nino.php` | Kernel and public core APIs |
| `_nino/Nino/Modules/<Name>/<Name>.php` | Built-in runtime modules |
| `_nino/Nino.js` | Shared browser helpers |
| `_nino/Nino.admin.css` | Shared management-interface design |
| `_admin/Admin.php` | All `\Nino\Admin\*` backend classes |
| `content/` | Project state, never code: `.auth/pw.php` (the `/_admin` hash), `.auth/lockout.json` (login throttle), `.logs/` (activity log). Addressed by the virtual prefix `Filesystem::CONTENT_DIR`, resolved by `Filesystem::getContentPath()`, moved by `NINO_CONTENT_DIR`. Never write project state into a tool folder or into `config.php` — the first breaks updates, the second is rolled back by a Restore |
| `_admin/assets/*.js` | Admin frontend modules; no bundler |
| `_admin/templates/page-index.tpl` | Admin shell, tabs, panes, assets |
| `_templates/Templates.php` | Template Builder API, parser, composer |
| `_templates/library/<slug>/` | Section preset library |
| `_install/Install.php` | Installer behavior and library application |
| `_install/library/modules/<slug>/` | Installer module packages |
| `_install/library/pages/<slug>/` | Installable page units |
| `tests/*-smoke.php` | Standalone PHP contract tests |
| `tests/*-js-smoke.js` | Standalone Node/browser-logic tests |
| `docs/` | Human manuals in English and German |

Project content and generated destinations:

| Path | Data |
| --- | --- |
| `config.php` | Stable application configuration and routes |
| `templates/*.tpl` | Runtime HTML+ templates |
| `text/global.php` | Locale-independent textfills |
| `text/<locale>.php` | Translated textfills |
| `text/blacklist.php` | Technical fills hidden from normal editing |
| `elements/<type>.php` | Element schema plus shared and localized records |
| `images/` | Project and uploaded images |
| `data/` | Runtime records, locks, logs, caches; normally not versioned |

A fresh checkout may not yet contain all generated destination directories.
Do not “fix” their absence by adding empty placeholders. `/_install` creates and
populates them.

Library files are sources, not live project files. Editing
`_install/library/pages/home/templates/page-home.tpl` changes future
installations; it does not update an already generated
`templates/page-home.tpl`. Modify the correct ownership layer.

## 5. Core runtime model that every extension must preserve

Every request follows:

```php
$appData = \Nino\init();
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
```

- `$appData` is application state, configuration, callbacks, caches, and
  runtime state.
- `$request` is one normalized request and its response.
- Both are passed by reference.
- Keys under `/...` are stable configuration/data space.
- Keys under `./...` are request-lifecycle-only state.
- A `/...` value is not persistent merely because it is in `$appData`. A
  writer must explicitly save it.

### Response rules

Runtime handlers MUST NOT call `echo`, `header()`, `http_response_code()`, or
`exit`. They MUST modify `$request['/nino/http/response']` or use:

```php
\Nino\Http::ok( $request, [ 'items' => $items ] );
\Nino\Http::fail( $request, 400, 'invalid request' );
```

Do not expose stack traces, local paths, tokens, email-existence checks, or raw
exception messages in a public response.

### Route rules

Routes live under `/nino/http/routes` and are keyed
`METHOD://public-uri`:

```php
'GET://services' => [
	'uri'  => '/services',
	'body' => '[template /templates/page-services]',
],
```

The key is the public request; `uri` is the stable internal response identity.
Route-specific callbacks use the internal identity:

```text
/nino/http/response/GET://services
```

Technical endpoints owned by a module MAY be registered in its `init()`.
Normal visitor pages SHOULD be persisted as page routes instead.

CSRF protection is active by default. A state-changing browser route MUST keep
it active and its form MUST render `[csrf]`. Use `'csrf' => false` only when a
documented alternative verifier, such as a cryptographic webhook signature, is
implemented and tested.

### Persistence and concurrency rules

Never implement a read-modify-write cycle as separate read and write calls.
Two requests can read the same state and one will overwrite the other. Use:

```php
$written = \Nino\Filesystem::mutate(
	$appData,
	'/data/catalog.php',
	function( mixed $state, array &$appData ) use ( $entry ): array {
		$rows = is_array( $state ) ? $state : [];
		$rows[] = $entry;
		return $rows;
	},
	[]
);
```

Returning `null` from the callback aborts the mutation and releases its lock.
Check the boolean result where failure affects the response.

To persist selected top-level configuration keys already changed in
`$appData`, use:

```php
\Nino\AppData::writeContentData( $appData, [
	'/project/catalog',
	'/nino/http/routes',
] );
```

Do not serialize all of `$appData`. It contains runtime values and may overwrite
concurrent changes.

### Rendering and escaping rules

HTML+ is processed as textfills, then shortcodes, then final render callbacks.

- Absolute textfill: `[[/page-home/main-hero/title]]`
- Reusable template: `[template /templates/html-header]`
- Image slot: `[image /page-home/main-hero/image alt=""]`
- Element-local field: `[[title]]` only inside `[element]` or `[elements]`
- CSRF field: `[csrf]`

Escape at the output context:

- HTML text/attribute: `htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE,
  'UTF-8')`.
- Rich element text: declare `'html' => true` in its model and rely on
  `\Nino\Html::sanitizeHtml()` when accepting it.
- Browser DOM: insert untrusted text with `textContent`, not `innerHTML`.
- URL/path/class/ID: validate against a strict allowlist before escaping.
- JSON: return arrays through Nino; do not concatenate JSON by hand.

Shortcode output is rendered again. If untrusted content can contain `[`, it can
otherwise become a new fill or shortcode on the next rendering pass. Escape or
neutralize it for the intended context.

### Callback rules

Callbacks receive `$appData` and the argument by reference. A non-`null` return
value replaces the argument for following callbacks. Keep callback names and
argument shapes backward compatible. Use a project namespace for project
events; `/nino/*` is reserved for Nino.

## 6. Code and interface conventions

### PHP

- PHP is 8.4+ and new files MUST contain `declare(strict_types=1);`.
- Keep the namespace and one public class per autoloaded module file.
- Match nearby tabs, spaces, braces, docblocks, and explicit types.
- Validate array shape before reading nested user data.
- Catch failures only when the caller can recover safely; do not hide bugs.
- Do not call internal `_method()` APIs across class boundaries.

### JavaScript

- There is no module bundler or transpiler.
- Admin files use an IIFE and attach behavior below `window.Nino`.
- Use `const`/`let`, explicit event listeners, and existing HTTP helpers.
- Do not add inline handlers or depend on globally leaked DOM IDs.
- Keep pure model functions separately testable where practical.
- Use `textContent` for all server- or user-derived strings.

### CSS and management UI

- Reuse `_nino/Nino.admin.css` design tokens and primitives.
- Keep application-specific layout in the application stylesheet.
- Prefer list/drill-down views over isolated card inventions.
- Use `admin-drill-list` for clickable Admin lists.
- Use `Nino.adminUi.contextBar()` for the fixed upper context row.
- Use `Nino.adminUi.listActions()` for fixed list actions.
- Use `Nino.adminUi.actionBar()` or the established
  `editor-form-actions nino-admin-actionbar` structure for bottom actions.
- Maintain keyboard focus, labels, error text, small viewports, coarse pointers,
  and `prefers-reduced-motion`.
- Do not encode meaning only by color or hover.

### Templates

- `.tpl` files contain HTML+, never PHP.
- Use valid semantic HTML and accessible names.
- Use real `button` controls for actions and real `a` elements for navigation.
- Every form control needs a label; every content image needs suitable alt text.
- Do not add inline event handlers.
- Do not assume JavaScript is available for essential content.

## 7. Recipe: add an Admin backend and UI tab

Use this recipe for developer-only project management inside `/_admin`. Do not
put ordinary public-site behavior in Admin.

### 7.1 Files that MUST be considered

A visible Admin extension currently requires all of these:

1. A backend class in `_admin/Admin.php`.
2. Its class in `Admin::MODULES`.
3. A navigation link in `_admin/templates/page-index.tpl`.
4. A content pane in that template.
5. A script tag for the frontend file.
6. A `TABS` entry in `_admin/assets/script.js`.
7. A frontend file `_admin/assets/<slug>.js`.
8. Backend and JavaScript smoke-test coverage.
9. CSS only when shared primitives cannot express the UI.

The class's `nav()` method is useful module metadata but the current HTML shell
does not render it dynamically. Adding only `actions()`, `nav()`, and
`Admin::MODULES` creates API actions but no visible tab.

Choose one stable slug and use it consistently:

| Place | Example |
| --- | --- |
| Backend class | `ProjectNotes` |
| Action prefix | `devnotes/*` |
| `nav()` URI | `notes` |
| Nav ID | `admin-nav-notes` |
| Pane ID | `admin-content-notes` |
| Shell class | `show-notes` |
| JS namespace | `Nino.admin.notes` |
| Asset | `_admin/assets/notes.js` |

Action names MUST be globally unique across all classes in `Admin::MODULES`.

### 7.2 Backend skeleton

Put the class in the existing `namespace Nino\Admin` block:

```php
class ProjectNotes {

	private const string STORAGE = '/data/project-notes.php';
	private const int MAX_TITLE_LENGTH = 160;
	private const int MAX_BODY_LENGTH = 5000;

	public static function actions(): array {
		return [
			'devnotes/list' => [ self::class, 'apiList' ],
			'devnotes/save' => [ self::class, 'apiSave' ],
		];
	}

	public static function nav(): array {
		return [ 'notes', 'Project Notes' ];
	}

	public static function apiList( array &$appData, array &$request ): void {

		if( Admin::guard( $appData, $request ) === false )
			return;

		$notes = \Nino\Filesystem::getFileContent(
			$appData,
			self::STORAGE,
			[]
		);

		if( is_array( $notes ) === false )
			$notes = [];

		\Nino\Http::ok( $request, [
			'notes' => array_values( $notes ),
		] );
	}

	public static function apiSave( array &$appData, array &$request ): void {

		if( Admin::guard( $appData, $request ) === false )
			return;

		$data  = Admin::postData();
		$id    = strtolower( trim( (string) ( $data['id'] ?? '' ) ) );
		$title = trim( (string) ( $data['title'] ?? '' ) );
		$body  = trim( (string) ( $data['body'] ?? '' ) );

		if( preg_match( '/^[a-z][a-z0-9-]*$/', $id ) !== 1 ) {
			\Nino\Http::fail( $request, 400, 'invalid note id' );
			return;
		}

		if(
			$title === ''
			|| strlen( $title ) > self::MAX_TITLE_LENGTH
			|| strlen( $body ) > self::MAX_BODY_LENGTH
		) {
			\Nino\Http::fail( $request, 400, 'invalid note content' );
			return;
		}

		$entry = [
			'id'      => $id,
			'title'   => $title,
			'body'    => $body,
			'updated' => date( DATE_ATOM ),
		];

		$written = \Nino\Filesystem::mutate(
			$appData,
			self::STORAGE,
			function( mixed $state ) use ( $id, $entry ): array {
				$notes = is_array( $state ) ? $state : [];
				$notes[$id] = $entry;
				ksort( $notes );
				return $notes;
			},
			[]
		);

		if( $written === false ) {
			\Nino\Http::fail( $request, 500, 'could not save note' );
			return;
		}

		\Nino\Http::ok( $request, [ 'note' => $entry ] );
	}
}
```

This skeleton demonstrates the required invariants:

- Every API method calls `Admin::guard()` itself.
- Payload comes from `Admin::postData()`, which decodes the JSON `data` field.
- IDs, lengths, and required fields are validated server-side.
- The file update is atomic.
- The response uses `Http::ok()` or `Http::fail()`.
- Nothing is directly printed.

Do not rely only on `Admin::handlePost()` having already authenticated. The
method-level guard protects direct calls in tests and future dispatch changes.

For configuration rather than module-owned records, update only the intended
`$appData` key and call `AppData::writeContentData()`. For Elements, Text,
Images, Auth, or Routes, prefer their existing public writers rather than
writing their files manually.

Destructive actions need:

- an exact ID allowlist check,
- existence/conflict handling,
- a deliberate UI confirmation,
- server-side authorization,
- cleanup limited to resources actually owned by the record,
- and tests that unrelated files/data survive.

### 7.3 Register the backend

Add the class to `Admin::MODULES`:

```php
private const array MODULES = [
	// Existing modules...
	\Nino\Admin\ProjectNotes::class,
];
```

Do not add runtime project modules here. This list only supplies Admin action
maps.

### 7.4 Extend the Admin shell

Add all three shell pieces to `_admin/templates/page-index.tpl`:

```html
<a href="#" id="admin-nav-notes">Project Notes</a>
```

```html
<div id="admin-content-notes">
	<div id="notes-list"></div>
	<div id="notes-form"></div>
</div>
```

```html
<script src="[[/nino/dir]]/_admin/assets/notes.js"></script>
```

Then register the tab in `_admin/assets/script.js`:

```js
notes : [ 'show-notes', function() { Nino.admin.notes.showCurrent() } ],
```

Keep the template nav order, content order, `TABS` order, and
`Admin::MODULES` order intentionally aligned.

### 7.5 Frontend skeleton

The following is a minimal list/edit lifecycle. Adapt field names, but preserve
the API, DOM safety, and lifecycle shape:

```js
( function(wn,dc) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.notes = {

		_items : [],
		_ready : false,

		init : function() {

			if( dc.getElementById('notes-list') === null )
				return;

			Nino.admin.notes._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.notes._showError(
						dc.getElementById('notes-list'),
						status,
						response
					);

				Nino.admin.notes._items = response.notes || [];
				Nino.admin.notes._renderList();
				Nino.admin.notes._showList();
				Nino.admin.notes._ready = true;
			} );
		},

		showCurrent : function() {

			if( Nino.admin.notes._ready === false )
				return;

			if(
				dc.getElementById('notes-form')
					.classList.contains('admin-hidden') === false
			)
				return;

			Nino.admin.notes._showList();
		},

		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, {
				action : 'devnotes/'+ endpoint,
				data : JSON.stringify( payload ),
			} );
		},

		_showError : function( container, status, response ) {
			container.innerHTML = '';
			const message = dc.createElement('p');
			message.className = 'admin-error';
			message.textContent = '('+ status+ ') '
				+ ( response && response.error
					? response.error
					: 'Request failed.' );
			container.appendChild( message );
		},

		_showList : function() {
			dc.getElementById('notes-list').classList.remove('admin-hidden');
			dc.getElementById('notes-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('notes-list').classList.add('admin-hidden');
			dc.getElementById('notes-form').classList.remove('admin-hidden');
		},

		_renderList : function() {

			const wrap = dc.getElementById('notes-list');
			wrap.innerHTML = '';

			const heading = dc.createElement('h2');
			heading.textContent = 'Project Notes';
			wrap.appendChild( heading );

			const list = dc.createElement('ul');
			list.className = 'admin-drill-list';

			if( Nino.admin.notes._items.length === 0 ) {
				const empty = dc.createElement('p');
				empty.textContent = 'No project notes yet.';
				wrap.appendChild( empty );
			}

			Nino.admin.notes._items.forEach( function( item ) {
				const li = dc.createElement('li');
				const link = dc.createElement('a');
				const title = dc.createElement('strong');
				const meta = dc.createElement('small');

				link.href = '#';
				title.textContent = item.title;
				meta.textContent = item.id;

				link.appendChild( title );
				link.appendChild( meta );
				link.addEventListener( 'click', function( event ) {
					event.preventDefault();
					Nino.admin.notes._renderForm( item );
				} );

				li.appendChild( link );
				list.appendChild( li );
			} );

			wrap.appendChild( list );

			const add = dc.createElement('button');
			add.type = 'button';
			add.className = 'ui-btn ui-btn--primary';
			add.textContent = 'New note';
			add.addEventListener( 'click', function() {
				Nino.admin.notes._renderForm( null );
			} );
			wrap.appendChild( Nino.adminUi.listActions( [ add ] ) );
		},

		_renderForm : function( item ) {

			const wrap = dc.getElementById('notes-form');
			wrap.innerHTML = '';

			const back = dc.createElement('a');
			back.href = '#';
			back.className = 'back-link';
			back.textContent = 'Back to project notes';
			back.addEventListener( 'click', function( event ) {
				event.preventDefault();
				Nino.admin.notes._showList();
			} );
			wrap.appendChild( Nino.admin.formToolbar( back ) );

			const form = dc.createElement('form');
			const heading = dc.createElement('h2');
			const idLabel = dc.createElement('label');
			const id = dc.createElement('input');
			const titleLabel = dc.createElement('label');
			const title = dc.createElement('input');
			const bodyLabel = dc.createElement('label');
			const body = dc.createElement('textarea');
			const message = dc.createElement('p');
			const save = dc.createElement('button');
			const actions = dc.createElement('div');

			heading.textContent = item ? 'Edit note' : 'New note';

			idLabel.textContent = 'ID';
			idLabel.htmlFor = 'notes-id';
			id.id = 'notes-id';
			id.type = 'text';
			id.name = 'id';
			id.pattern = '[a-z][a-z0-9-]*';
			id.required = true;
			id.value = item ? item.id : '';
			id.disabled = item !== null;

			titleLabel.textContent = 'Title';
			titleLabel.htmlFor = 'notes-title';
			title.id = 'notes-title';
			title.type = 'text';
			title.name = 'title';
			title.maxLength = 160;
			title.required = true;
			title.value = item ? item.title : '';

			bodyLabel.textContent = 'Body';
			bodyLabel.htmlFor = 'notes-body';
			body.id = 'notes-body';
			body.name = 'body';
			body.maxLength = 5000;
			body.value = item ? item.body : '';

			message.className = 'admin-form-message';
			message.setAttribute( 'aria-live', 'polite' );

			save.type = 'submit';
			save.className = 'ui-btn ui-btn--primary';
			save.textContent = 'Save';

			actions.className = 'editor-form-actions nino-admin-actionbar';
			actions.appendChild( save );

			form.appendChild( heading );
			form.appendChild( idLabel );
			form.appendChild( id );
			form.appendChild( titleLabel );
			form.appendChild( title );
			form.appendChild( bodyLabel );
			form.appendChild( body );
			form.appendChild( message );
			form.appendChild( actions );

			form.addEventListener( 'submit', function( event ) {
				event.preventDefault();
				save.disabled = true;
				message.textContent = 'Saving …';

				Nino.admin.notes._apiCall( 'save', {
					id : id.value,
					title : title.value,
					body : body.value,
				}, function( status, response ) {
					save.disabled = false;

					if( status !== 200 ) {
						message.textContent = response && response.error
							? response.error
							: 'Could not save.';
						return;
					}

					const index = Nino.admin.notes._items.findIndex(
						function( current ) {
							return current.id === response.note.id;
						}
					);
					if( index === -1 )
						Nino.admin.notes._items.push( response.note );
					else
						Nino.admin.notes._items[index] = response.note;

					Nino.admin.notes._items.sort( function( a, b ) {
						return a.id.localeCompare( b.id );
					} );
					Nino.admin.notes._renderList();
					Nino.admin.notes._showList();
				} );
			} );

			wrap.appendChild( form );
			Nino.admin.notes._showForm();
			title.focus();
		},

	};

	Nino.events.bindCallback( 'ready', Nino.admin.notes.init );

} )(window,document);
```

Do not copy the skeleton blindly if an existing Admin module already solves the
same list, locale, upload, rich-text, reorder, relationship, or confirmation
problem. Reuse its exact public helper and adapt the nearest implementation.
For a registry plus ordered route membership, inspect `Navigations` and
`_admin/assets/navs.js`.

### 7.6 Admin tests

Backend tests belong in `tests/admin-smoke.php`. They MUST exercise:

- unauthenticated rejection,
- successful list and save,
- malformed IDs and fields,
- maximum lengths and unexpected payload shape,
- persistence after a fresh read,
- an existing-record update,
- a failed or vetoed write if the feature supports one,
- and survival of unrelated records during update/delete.

JavaScript behavior belongs in a focused `tests/admin-<slug>-js-smoke.js` or an
existing shared Admin smoke test. At minimum assert:

- asset and namespace load,
- action names and JSON payload shape,
- list-to-form and back lifecycle,
- server text is inserted safely,
- save updates the model/UI,
- shared list/context/action bars are used,
- and the Admin template and `TABS` contain matching IDs.

Run:

```bash
php -l _admin/Admin.php
node --check _admin/assets/notes.js
php tests/admin-smoke.php
node tests/admin-notes-js-smoke.js
node tests/admin-lists-js-smoke.js
```

Use the actual test filename created by the change.

## 8. Recipe: add a runtime module

Use a runtime module when public requests need new PHP behavior: a shortcode,
an API, a webhook, a form handler, a render hook, or feature-owned runtime
state. Do not create a module for static markup that a `.tpl` can express.

### 8.1 Autoload layout

Nino's autoloader is intentionally small. It converts the full class name to a
path and appends the class basename as the filename:

```text
Class: Project\Catalog\Catalog
Path:  _nino/Project/Catalog/Catalog/Catalog.php
```

```text
Class: Nino\Modules\Catalog
Path:  _nino/Nino/Modules/Catalog/Catalog.php
```

The exact formula is:

```text
_nino/<namespace-and-class-as-path>/<class-basename>.php
```

Therefore:

- the class basename and PHP filename MUST match;
- every namespace segment becomes a directory;
- a leading backslash in the configured class string is accepted;
- class names MUST never be built from request data;
- do not add manual `require` calls for a correctly located module.

Built-in Nino modules use namespace `Nino\Modules`. Project-specific modules
SHOULD use a project namespace so future Nino classes cannot collide.

### 8.2 Minimal complete module

Create
`_nino/Project/Catalog/Catalog/Catalog.php`:

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

### 8.3 Shortcode contract

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

### 8.4 State-changing runtime endpoint

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

### 8.5 Module configuration

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

### 8.6 Module tests

Add focused coverage to `tests/kernel-smoke.php` or a dedicated standalone
smoke script consistent with the suite. Test:

- autoloading from the exact path;
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
php -l _nino/Project/Catalog/Catalog/Catalog.php
php tests/kernel-smoke.php
php tests/concurrency-smoke.php
```

If the module adds frontend JavaScript, also run `node --check` and add a Node
smoke test for its behavior rather than testing only for the file's presence.

## 9. Recipe: add an installer module package

An installer package makes a feature selectable in `/_install`. It can activate
a runtime class, copy templates/assets/element types, merge text, add owned
routes, and supply configuration defaults.

It does not execute as the runtime module. The runtime class must already be
shipped at its valid `_nino` autoload path, or otherwise be placed there by an
explicit, tested distribution process.

### 9.1 Directory shape

```text
_install/library/modules/catalog/
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

The matching runtime class could be:

```text
_nino/Nino/Modules/Catalog/Catalog.php
```

with class `\Nino\Modules\Catalog`.

### 9.2 Complete manifest example

```php
<?php
declare(strict_types=1);

return [
	'label' => 'Catalog',
	'moduleClass' => '\\Nino\\Modules\\Catalog',
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
| `label` | Picker label |
| `moduleClass` | Full runtime class added to `/nino/modules` |
| `requiresModules` | Other installer module directory slugs |
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

### 9.3 Requirements

`requiresModules` contains installer directory keys, not PHP class names:

```php
'requiresModules' => [ 'forms', 'navigation' ],
```

The Setup step resolves module requirements transitively. Keep the dependency
graph small and acyclic even though the resolver terminates cycles. A cycle
usually indicates mixed responsibilities.

A page unit that requires this feature names the same installer slug:

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

### 9.4 Templates and locale gating

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

### 9.5 Files and element types

`files` preserves the path relative to the unit:

```php
'files' => [ 'assets/catalog' ],
```

The directory above is copied to project `assets/catalog`. Prefer listing an
owned directory over a broad shared directory. Do not copy `assets`, `_nino`,
or another broad tree when the feature owns only one subdirectory.

`elementTypes` has different behavior:

```php
'elementTypes' => [ 'catalog-items.php' ],
```

The source is
`_install/library/modules/catalog/catalog-items.php` and the destination is
`elements/catalog-items.php`. Keep these source filenames flat and validate
them in tests.

### 9.6 Text fragments

Text files return bracketed textfill keys:

`_install/library/modules/catalog/text/global.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/project/catalog/api-uri]]' => '/api/catalog',
];
```

`_install/library/modules/catalog/text/en_US.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/project/catalog/title]]' => 'Catalog',
	'[[/project/catalog/empty]]' => 'No entries are available.',
];
```

`_install/library/modules/catalog/text/de_DE.php`:

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

### 9.7 Routes and ownership

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

### 9.8 Installer package tests

Extend `tests/install-smoke.php`. Test:

- the unit appears with label and requirements;
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
php -l _install/library/modules/catalog/manifest.php
php -l _install/library/modules/catalog/text/global.php
php -l _install/library/modules/catalog/text/en_US.php
php -l _install/library/modules/catalog/text/de_DE.php
php -l _install/library/modules/catalog/catalog-items.php
php tests/install-smoke.php
node tests/install-script-js-smoke.js
```

## 10. Recipe: add a Section Library preset

A Section Library preset is a discoverable recipe used by `/_templates`. The
Composer validates its options and generates ordinary HTML+ source. That source
is copied into a page template and remains independent of the library at
runtime.

Use a preset when an existing Composer content type can express the section.
Do not add PHP or a runtime module for visual markup alone.

### 10.1 Directory and slug

```text
_templates/library/services-image-grid/
└── manifest.php
```

An optional code-authored variant adds:

```text
_templates/library/editorial-hero/
├── manifest.php
└── section.tpl
```

The directory slug MUST match:

```regex
^[a-z0-9][a-z0-9-]*$
```

Use a stable semantic slug. A preset is referenced by this key in stored
section metadata.

### 10.2 Prefer a generic manifest first

The generic Composer already handles surface, background, header, content,
layout, actions, spacing, borders, and motion. A manifest-only preset gets all
security, preview, schema, and future compatible behavior from that path.

Example
`_templates/library/services-image-grid/manifest.php`:

```php
<?php
declare(strict_types=1);

return [
	'name' => 'Services — Image Grid',
	'description' => 'A responsive service grid with images, copy and links.',
	'category' => 'Services',
	'tags' => [
		'services',
		'articles',
		'cards',
		'images',
		'grid',
		'links',
	],
	'version' => 1,
	'shell' => 'section',
	'defaults' => [
		'surface' => 'default',
		'background' => 'none',
		'header' => 'title-subtitle-description',
		'align' => 'left',
		'content' => 'articles-image',
		'contentStyle' => 'auto',
		'action' => 'none',
		'motion' => 'page',
		'padding' => 'default',
		'margin' => 'none',
		'border' => 'none',
		'layout' => '3',
		'limit' => 6,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'header' => [ 'title', 'title-subtitle', 'title-subtitle-description' ],
		'align' => [ 'left', 'center' ],
		'contentStyle' => [ 'auto', 'default', 'alt' ],
		'motion' => [ 'page', 'on', 'off' ],
		'padding' => [ 'default', 'compact', 'generous' ],
		'margin' => [ 'none', 'small', 'medium', 'large' ],
		'border' => [ 'none', '1', '2', '3' ],
		'layout' => [ '2', '3', '4' ],
	],
];
```

Manifest metadata rules:

- `name` is the human-facing library title.
- `description` must explain visible structure, not implementation.
- `category` must be useful as a filter and consistent with nearby presets.
- `tags` MUST be non-empty and include likely visual/search terms.
- `version` starts at `1`. Increment it when the preset's generated contract
  changes materially.
- `shell` is `section` or `hero`.
- `defaults` describes a valid complete initial composition.
- `allow` exposes only deliberate variants.

Do not market every preset as “modern”. Tags should distinguish it by visible
content, layout, purpose, and behavior.

### 10.3 The critical `defaults` / `allow` rule

For every Composer choice axis:

1. If `allow[axis]` exists, invalid values are removed and only the remaining
   values are selectable.
2. If `allow[axis]` is absent but `defaults[axis]` exists, that axis is locked
   to the single default.
3. If both are absent, all globally valid values are allowed.

Therefore a curated preset SHOULD define every default and list in `allow`
only axes users are meant to vary. Omitting `allow['content']` in the example
correctly locks the preset to `articles-image`.

Every default must itself be present in that axis's allowed choices and its
content/layout pair must be compatible. Otherwise the Composer falls back to
the first allowed/compatible value, producing a surprising preview.

`limit` is not a choice axis. It is a numeric default clamped to `1..12` and
does not belong in `allow`.

`pageMotion` is a page/template setting, not preset metadata. The section's
`motion: page` inherits it when composed.

### 10.4 Exact current choice values

Do not spell values from memory. The current source of truth is
`Composer::choices()` in `_templates/Templates.php`.

| Axis | Valid values |
| --- | --- |
| `surface` | `default`, `alt`, `primary`, `dark`, `black` |
| `background` | `none`, `image-cover`, `image-static`, `parallax` |
| `header` | `none`, `title`, `title-subtitle`, `title-subtitle-description` |
| `align` | `left`, `center`, `right` |
| `content` | `none`, `text`, `media-split`, `articles`, `articles-image`, `cards`, `lists`, `slider`, `media-slider`, `testimonials`, `profiles`, `stats`, `features`, `feature-list`, `accordion`, `tabs`, `pricing`, `comparison`, `data-table`, `logos`, `badges`, `gallery`, `timeline`, `video`, `video-embed`, `notice`, `contact`, `newsletter` |
| `contentStyle` | `auto`, `default`, `alt` |
| `action` | `none`, `link`, `button`, `dual-buttons` |
| `motion` | `page`, `on`, `off` |
| `padding` | `default`, `none`, `compact`, `generous` |
| `margin` | `none`, `small`, `medium`, `large` |
| `border` | `none`, `1`, `2`, `3` |
| `layout` | `auto`, `2`, `3`, `4`, `media-left`, `media-right`, `media-left-full`, `media-right-full`, `narrow`, `wide`, `spotlight`, `slider`, `grid`, `mosaic`, `bento`, `split`, `featured`, `check`, `check-2`, `numbered`, `numbered-2`, `plain`, `striped`, `bordered`, `striped-bordered`, `pill`, `info`, `success`, `error`, `4-3` |

### 10.5 Content/layout compatibility

A globally valid layout is not necessarily valid for a content type. Use
`Composer::modules()` as the source of truth:

| Content | Source | Compatible layouts |
| --- | --- | --- |
| `none` | none | `auto` |
| `text` | native textfills | `wide`, `narrow` |
| `media-split` | native textfills + image slot | `media-left`, `media-right`, `media-left-full`, `media-right-full` |
| `lists` | Elements | `check`, `check-2`, `numbered`, `numbered-2` |
| `articles` | Elements | `2`, `3`, `4` |
| `articles-image` | Elements | `2`, `3`, `4` |
| `cards` | Elements | `spotlight`, `2`, `3`, `4` |
| `slider` | Elements | `narrow`, `wide` |
| `media-slider` | Elements | `narrow`, `wide` |
| `testimonials` | Elements | `spotlight`, `slider`, `2`, `3` |
| `profiles` | Elements | `spotlight`, `3`, `4` |
| `stats` | Elements | `2`, `3`, `4` |
| `features` | Elements | `2`, `3`, `4`, `bento` |
| `feature-list` | Elements + section image | `media-left`, `media-right` |
| `accordion` | Elements | `narrow`, `wide` |
| `tabs` | Elements | `narrow`, `wide` |
| `pricing` | Elements | `2`, `3`, `4`, `featured` |
| `comparison` | native headings + Elements | `wide` |
| `data-table` | native headings + Elements | `plain`, `striped`, `bordered`, `striped-bordered` |
| `logos` | Elements | `wide` |
| `badges` | Elements | `plain`, `pill` |
| `gallery` | Elements | `grid`, `mosaic` |
| `timeline` | Elements | `3`, `4` |
| `video` | native URI + image slot | `narrow`, `wide` |
| `video-embed` | native URI | `narrow`, `wide`, `4-3` |
| `notice` | native textfill | `info`, `success`, `error` |
| `contact` | native textfills + form module convention | `split` |
| `newsletter` | module text/form convention | `narrow`, `wide` |

If a submitted layout is incompatible, the Composer silently chooses the
content type's first compatible layout. A good manifest never depends on that
fallback.

“Native textfills” means one set of section values at paths based on:

```text
/page-<pageId>/<sectionId>/<suffix>
```

“Elements” means repeatable records and requires a valid Element type slug.
When no slug is supplied, the builder derives:

```text
<pageId>-<sectionId>
```

### 10.6 Add a custom `section.tpl` only when necessary

Use a custom file when the generic renderer cannot express an important DOM
structure. The manifest still supplies validation, fields, images, schema,
options, search metadata, and preview input.

Example `_templates/library/editorial-hero/manifest.php`:

```php
<?php
declare(strict_types=1);

return [
	'name' => 'Hero — Editorial Split',
	'description' => 'Editorial copy, two actions and a full-height side image.',
	'category' => 'Hero',
	'tags' => [ 'hero', 'editorial', 'split', 'image', 'two buttons' ],
	'version' => 1,
	'shell' => 'hero',
	'defaults' => [
		'surface' => 'default',
		'background' => 'none',
		'header' => 'title-subtitle-description',
		'align' => 'left',
		'content' => 'media-split',
		'contentStyle' => 'auto',
		'action' => 'dual-buttons',
		'motion' => 'page',
		'padding' => 'default',
		'margin' => 'none',
		'border' => 'none',
		'layout' => 'media-right',
		'limit' => 1,
	],
	'allow' => [
		'surface' => [ 'default', 'alt', 'primary', 'dark', 'black' ],
		'motion' => [ 'page', 'on', 'off' ],
	],
];
```

Example `_templates/library/editorial-hero/section.tpl`:

```html
<section id="{{section:id}}" class="{{section:classes}}">
	{{section:meta}}
	<div class="ui-grid-row ui-grid-middle">
		<div class="ui-grid-100 ui-grid-m-50 ui-p-2">
			<h2 class="ui-atf-title">{{text:title}}</h2>
			<p class="ui-atf-subtitle">{{text:subtitle}}</p>
			<p>{{text:description}}</p>
			<div>{{text:content}}</div>
			<div class="ui-mt-3">
				<a href="{{text:cta-uri}}" class="ui-btn ui-btn--primary">
					{{text:cta-label}}
				</a>
				<a
					href="{{text:secondary-cta-uri}}"
					class="ui-btn ui-btn--outline"
				>
					{{text:secondary-cta-label}}
				</a>
			</div>
		</div>
		<div class="ui-grid-100 ui-grid-m-50 ui-img-cover">
			{{image:image}}
		</div>
	</div>
</section>
```

The only supported custom tokens are:

| Token | Result |
| --- | --- |
| `{{section:id}}` | Escaped section ID |
| `{{section:classes}}` | Escaped classes derived from the spec |
| `{{section:meta}}` | Inert JSON comment used to reopen settings |
| `{{content:prefix}}` | `/page-<pageId>/<sectionId>` |
| `{{elements:type}}` | Element type slug without a leading slash |
| `{{text:<suffix>}}` | Absolute textfill for a generated field suffix |
| `{{image:<suffix>}}` | Generated `[image ...]` shortcode |

Every `{{text:*}}` suffix must be produced by the selected header, content, or
action. Every `{{image:*}}` suffix must be produced by the selected background
or content type. An unavailable token stays unresolved and composition fails.

For example:

- `header: title-subtitle-description` supplies `title`, `subtitle`, and
  `description`.
- `content: media-split` supplies text `content` and section image `image`.
- `action: dual-buttons` supplies four CTA text fields.
- `background: image-cover` supplies section image `background`.
- an image field inside an Elements schema is local `[[image]]` and is not a
  `{{image:image}}` section slot.

An Elements-based custom section uses:

```html
<section id="{{section:id}}" class="{{section:classes}}">
	{{section:meta}}
	<ul class="ui-list">
		[elements /{{elements:type}} limit="6"]
		<li>
			<strong>[[title]]</strong>
			<span>[[description]]</span>
		</li>
		[/elements]
	</ul>
</section>
```

Its manifest `content` must be an Elements-sourced module whose schema contains
those local fields. There is currently no custom token for a dynamic limit;
either use a deliberate fixed value or prefer the generic renderer.

### 10.7 Custom section structural contract

`section.tpl` MUST:

- contain exactly one complete top-level `<section>...</section>`;
- contain no non-whitespace source before or after it;
- use a normal closing `</section>`, never a self-closing section;
- keep `{{section:meta}}` inside the section;
- use `{{section:id}}` and `{{section:classes}}` on the root;
- contain no PHP;
- leave all supported tokens resolvable by its manifest;
- produce valid, accessible HTML;
- and remain meaningful without preview JavaScript.

Nested sections are legal when semantically appropriate, but multiple
top-level sections are not.

The preview is intentionally inert:

- script blocks and PHP are stripped;
- VPA classes are removed because `Nino.ui.js` does not run in the frame;
- project fills and Elements receive deterministic fixtures;
- image slots receive local SVG fixtures;
- iframe sources become `about:blank`;
- form actions become `#`;
- and remaining project shortcodes are removed.

Do not “fix” preview by enabling arbitrary scripts, remote iframes, or active
forms. Make the preset's static structure communicate the design.

### 10.8 Adding a new Composer content type is a larger change

If no existing `content` module supplies the required field/schema/layout
contract, a new preset alone is insufficient. A new content type requires:

1. a new key in the valid content choices;
2. a `Composer::modules()` entry with `source`, layouts, native fields, image
   slots, and Element model;
3. generic rendering in `Composer::_renderContent()`;
4. deterministic preview fixtures for every new suffix;
5. compatible Template Builder labels/filter behavior;
6. CSS/JavaScript runtime behavior only when necessary;
7. tests for composition, schema, preview, accessibility, and invalid layouts;
8. updates to this table and the human Template Builder manual.

Before adding one, check whether a custom `section.tpl` with an existing content
contract is sufficient. Do not create two content keys with the same schema and
only different cosmetic classes; that is normally a preset or layout variant.

### 10.9 Section preset tests

`tests/templates-smoke.php` already composes every preset with defaults. Add
specific assertions for the new preset's meaningful contract:

```php
$services = \Nino\Templates\Composer::compose( [
	'preset' => 'services-image-grid',
	'pageId' => 'home',
	'id' => 'services',
	'elementType' => 'services',
] );

check(
	'composes the services image grid',
	str_contains( $services['source'], '[elements /services' )
	&& isset( $services['elementSchema']['image'] )
	&& $services['spec']['layout'] === '3'
);
```

Also test:

- searchable non-empty metadata;
- every default and allowed choice;
- invalid choices clamp to the preset, not an unrelated variant;
- exact native field suffixes and image slots;
- exact Element schema for repeatable content;
- one valid top-level section;
- no unresolved custom tokens;
- preview has realistic content and no unresolved shortcodes;
- VPA-enabled preview is visible;
- and HTML/source includes the expected accessible structure.

Run:

```bash
php -l _templates/library/services-image-grid/manifest.php
php tests/templates-smoke.php
node tests/templates-js-smoke.js
```

Finally inspect the real library card and preview at small and large widths.
Automated string assertions do not establish that a visual preset is useful.

## 11. Recipe: write templates and installable page units

Nino templates are HTML+ files. They contain HTML, textfills, and shortcodes,
but no PHP. A route renders a template; the template itself does not create the
route.

### 11.1 Template kinds and filenames

| Kind | Filename | Purpose |
| --- | --- | --- |
| Page template | `page-services.tpl` | Complete route body, listed by Template Builder |
| Reusable shell | `html-header.tpl` | Header/footer/layout include |
| Reusable section | `section-newsletter.tpl` | Shared content included in pages |
| Mail template | `mail-owner.tpl` | HTML email structure |
| Locale variant | `page-legal.de_DE.tpl` | Structure that truly differs per locale |

Only `templates/page-*.tpl` files appear as editable documents in
`/_templates`. Do not prefix reusable includes with `page-`.

Include a template without its `.tpl` extension:

```html
[template /templates/html-header]
```

Use project-root-relative include paths. Do not use `../`, an absolute
filesystem path, a URL, or a request-derived filename.

### 11.2 Modern page-template frame

New page templates SHOULD use explicit metadata and shell slots:

```html
<!-- nino:template-name Services -->
<!-- nino:template-vpa on -->
<!-- nino:template-slot header -->
[template /templates/html-header]
<section id="services-intro" class="ui-section">
	<div class="ui-grid-row ui-grid-middle">
		<div class="ui-grid-100 ui-grid-m-50">
			<h1 class="ui-section-title">
				[[/page-services/services-intro/title]]
			</h1>
			<p class="ui-section-subtitle">
				[[/page-services/services-intro/subtitle]]
			</p>
			<div>
				[[/page-services/services-intro/description]]
			</div>
			<a
				class="ui-btn ui-btn--primary"
				href="[[/page-services/services-intro/cta-uri]]"
			>
				[[/page-services/services-intro/cta-label]]
			</a>
		</div>
		<div class="ui-grid-100 ui-grid-m-50 ui-img-cover">
			[image /page-services/services-intro/image alt=""]
		</div>
	</div>
</section>
<!-- nino:template-slot footer -->
[template /templates/html-footer]
```

A page template may deliberately contain no `<section>` at all. Metadata plus
header/footer slots is the correct blank starting state. Do not insert a dummy
section merely to make the canvas non-empty.

Metadata MUST be the first source in the file and has exact syntax:

```html
<!-- nino:template-name Human readable name -->
<!-- nino:template-vpa on -->
```

`nino:template-vpa` accepts only `on` or `off`.

A display name:

- contains `1..160` bytes;
- contains no control character, `<`, or `>`;
- and does not contain `--` because it lives in an HTML comment.

The filename accepted by the Template Builder matches:

```regex
^page-[A-Za-z0-9][A-Za-z0-9._-]*\.tpl$
```

and MUST NOT contain `..`. Prefer lowercase hyphenated filenames despite the
broader compatibility pattern. The builder derives the page ID from the part
after `page-`, normalizes unsupported characters to hyphens, and prefixes
numeric IDs with `p-`. A simple filename avoids surprising fill paths.

### 11.3 Header and footer slots

The marker and the following include form one fixed slot:

```html
<!-- nino:template-slot header -->
[template /templates/html-header]
```

`None` is represented by the marker with no following include:

```html
<!-- nino:template-slot header -->
```

The same applies to `footer`. There is one marker for each slot, no closing
marker, and no duplicate slot.

Allowed selected include paths match:

```regex
^/templates/[A-Za-z0-9][A-Za-z0-9._-]*$
```

or an empty string for `None`.

Historic exact top/bottom
`[template /templates/html-header]` / `html-footer` includes are recognized in
memory and receive markers on the next deliberate builder save. New templates
should not rely on that legacy normalization.

Header/footer slots are not regular movable canvas sections. Other standalone
`[template /templates/name]` shortcodes remain normal movable components.

### 11.4 What the Template Builder can edit

The parser treats:

- each complete top-level `<section>...</section>` as one component;
- each standalone `[template /templates/name]` line as one component;
- marked header/footer includes as fixed slots;
- and everything else as locked raw source.

A template shortcode nested inside a section remains part of that section. A
template shortcode inside another raw DOM parent remains locked raw source.
Section-like strings inside comments, scripts, styles, textareas, or PHP-like
text are not promoted.

The Builder preserves locked raw segments byte-for-byte and rejects a save that
changes them. Do not loosen this invariant by parsing and reserializing the
whole document with a browser DOM.

Each editable top-level section SHOULD have a unique semantic ID:

```html
<section id="services-overview">
```

The Builder rejects duplicate non-empty section IDs. Composer-created IDs match
`^[a-z][a-z0-9-]*$`. Use that form for hand-authored sections too.

Only Composer-created sections have a valid
`<!-- nino:section {...} -->` comment and can reopen their exact wizard
settings. Do not invent the JSON manually. A hand-authored top-level section is
still movable and editable through the HTML+ escape hatch.

### 11.5 Textfill path design

For section-owned native content, use:

```text
/page-<pageId>/<sectionId>/<semantic-suffix>
```

Examples:

```text
/page-home/main-hero/title
/page-home/main-hero/subtitle
/page-home/main-hero/cta-label
/page-services/services-overview/description
```

Rules:

- page and section segments are stable lowercase slugs;
- the suffix describes meaning, not position or HTML tag;
- use `title` rather than `h2` and `description` rather than `left-p-1`;
- use `cta-label` and `cta-uri` as a pair;
- do not put visible text directly in markup when editors must translate it;
- keep structural class names and technical configuration out of locale text.

General page metadata is separate:

```html
[[/webpage[[/nino/http/response/uri]]/name]]
[[/webpage[[/nino/http/response/uri]]/title]]
[[/webpage[[/nino/http/response/uri]]/description]]
```

The route's internal URI determines these values. Do not hardcode metadata to a
library folder when a page can be mounted at another internal URI.

### 11.6 Images

A native section image is an image slot:

```html
[image /page-services/services-intro/image alt=""]
```

Register and populate that slot through the existing Images APIs/tools. Do not
construct its generated filename.

Inside an Elements loop, an image field contains a filename relative to
project `images/`:

```html
<img
	src="[[/nino/dir]]/images/[[image]]"
	alt="[[title]]"
	loading="lazy"
>
```

Do not use the removed/incorrect `uploads` path. Let the image tooling create
dimensions and format-specific filenames.

Alt text must fit the image's purpose. Decorative images use empty alt text;
meaningful Element images SHOULD have a dedicated localized alt field when the
title is not a truthful substitute.

### 11.7 Forms and interactive templates

A state-changing form needs:

```html
<form class="ui-form" action="[[/nino/dir]]/.feature" method="post">
	[csrf]
	<label for="feature-email">[[/feature/label/email]]</label>
	<input
		id="feature-email"
		name="email"
		type="email"
		required
	>
	<input
		name="location"
		type="text"
		class="ui-sr-only"
		tabindex="-1"
		autocomplete="off"
		aria-hidden="true"
	>
	<p class="ui-form-message" aria-live="polite"></p>
	<button type="submit" class="ui-btn ui-btn--primary">
		[[/feature/label/submit]]
	</button>
</form>
```

The class names alone do not submit or secure a form. A matching runtime module
must own the endpoint, validate it, and return a documented response.

For JS-enhanced controls:

- start with meaningful HTML;
- reuse existing `js-*` hooks and ARIA patterns;
- make touch scrolling and keyboard operation possible;
- avoid trapping focus or pointer gestures;
- and keep preview inert.

### 11.8 Installable page unit

Create:

```text
_install/library/pages/services/
├── manifest.php
├── templates/
│   └── page-services.tpl
├── text/
│   ├── en_US.php
│   └── de_DE.php
└── services.php              # optional Element type source
```

Example `manifest.php`:

```php
<?php
declare(strict_types=1);

return [
	'label' => 'Services',
	'requiresModules' => [ 'navigation' ],
	'routes' => [
		'GET://services' => [
			'uri' => '/services',
			'body' => '[template /templates/page-services]',
			'navs' => [
				'main' => 5,
				'footer' => 5,
			],
		],
	],
	'templates' => [
		'page-services.tpl',
	],
	'elementTypes' => [
		'services.php',
	],
];
```

Current page-unit consumers recognize:

- `label`;
- `requiresModules` using installer module slugs;
- `routes`;
- `templates`, including locale-keyed entries;
- `elementTypes`;
- `blacklist`;
- and `text/global.php` plus selected `text/<locale>.php`.

Some older page manifests contain `files`, but the current Webpages application
path does not copy that key. Do not rely on page-unit `files` without first
implementing and testing support in `Install.php`. Put feature assets in a
required installer module or another supported ownership layer.

Normally one page unit has one route. `/_install` uses the route body to
identify its library unit and lets the developer change:

- its public HTTP URI;
- its internal Element/page URI;
- template choice;
- navigation memberships/order;
- status code;
- and per-locale name, title, and description.

The manifest values are starting suggestions, not immutable route identity.

For a 404 page include:

```php
'statusCode' => 404,
```

Do not add the same route in `config.php`, a module `init()`, and a page
manifest. Choose one owner.

### 11.9 Page text suggestions and real content

`text/en_US.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/webpage/services/uri]]' => '/services',
	'[[/webpage/services/name]]' => 'Services',
	'[[/webpage/services/title]]' => 'Services built around your goals',
	'[[/webpage/services/description]]'
		=> 'Strategy, design and implementation from one team.',

	'[[/page-services/services-intro/title]]'
		=> 'Useful work, clearly delivered',
	'[[/page-services/services-intro/subtitle]]'
		=> 'From the first idea to a maintainable website.',
	'[[/page-services/services-intro/description]]'
		=> 'Choose the support that fits the current stage of your project.',
	'[[/page-services/services-intro/cta-label]]'
		=> 'Start a conversation',
	'[[/page-services/services-intro/cta-uri]]'
		=> '/contact',
];
```

`text/de_DE.php`:

```php
<?php
declare(strict_types=1);

return [
	'[[/webpage/services/uri]]' => '/services',
	'[[/webpage/services/name]]' => 'Leistungen',
	'[[/webpage/services/title]]' => 'Leistungen für klare Ziele',
	'[[/webpage/services/description]]'
		=> 'Strategie, Design und Umsetzung aus einer Hand.',

	'[[/page-services/services-intro/title]]'
		=> 'Sinnvolle Arbeit, klar umgesetzt',
	'[[/page-services/services-intro/subtitle]]'
		=> 'Von der ersten Idee bis zur wartbaren Website.',
	'[[/page-services/services-intro/description]]'
		=> 'Wähle die Unterstützung, die zur aktuellen Projektphase passt.',
	'[[/page-services/services-intro/cta-label]]'
		=> 'Gespräch beginnen',
	'[[/page-services/services-intro/cta-uri]]'
		=> '/contact',
];
```

The four `/webpage/<library-slug>/*` keys are suggestions read into the
installation form. They are stripped from the page fragment during merge.
`/_install` writes the actual metadata under the internal URI chosen for that
page instance.

All other keys are ordinary project content and are merged into the target text
files.

A Webpages entry has one public HTTP URI. Locale fragments may technically
suggest different URI strings, but only one suggestion can win for that entry;
this does not create localized routes. Prefer the same URI in all locale files
unless the route architecture deliberately handles localized URLs.

### 11.10 Locale-specific structural templates

Prefer one template plus translated textfills. If structure itself must differ,
use locale-keyed files:

```php
'routes' => [
	'GET://legal' => [
		'uri' => '/legal',
		'body'
			=> '[template /templates/page-legal.[[/nino/http/response/locale]]]',
		'navs' => [ 'footer' => 5 ],
	],
],
'templates' => [
	'de_DE' => 'page-legal.de_DE.tpl',
	'en_US' => 'page-legal.en_US.tpl',
	'html-footer-legal.tpl',
],
```

Use this only when markup—not merely wording—differs. Every supported locale
must resolve to an existing filename, and an omitted locale-gated file must not
leave a broken include.

### 11.11 Page/template tests

Extend `tests/install-smoke.php` for a new page unit and
`tests/templates-smoke.php` for Builder contracts. Test:

- unit label, suggested URI, metadata in every shipped locale;
- exact template and Element-type copies;
- required module activation;
- generated route body, internal URI, public URI, navs, order, status;
- reapply behavior and survival of unrelated routes;
- page filename/display name/page ID/VPA listing;
- header/footer slot parsing, including `None`;
- one or more valid top-level components;
- unique section IDs;
- locked raw source byte preservation;
- include paths and absence of PHP;
- every referenced textfill/image/Element type is provided or intentionally
  created later;
- and a rendered request reaches the expected template.

Run:

```bash
php -l _install/library/pages/services/manifest.php
php -l _install/library/pages/services/text/en_US.php
php -l _install/library/pages/services/text/de_DE.php
php tests/install-smoke.php
php tests/templates-smoke.php
node tests/templates-js-smoke.js
```

## 12. Recipe: define Element types for repeated content

Use Elements when a section has two or more records of the same schema, when
records must be reordered/filtered/reused, or when each record has an image or
several fields. Use native textfills for one section-specific record.

### 12.1 Admin-manageable model fields

The current Admin model editor supports these types:

```text
string, integer, double, boolean, array, date, datetime, image
```

Common model properties:

| Property | Applies to | Meaning |
| --- | --- | --- |
| `type` | all | Required storage type |
| `locale` | all | Store value per locale instead of globally |
| `required` | non-image | Reject missing value on insert |
| `html` | string | Allow sanitized rich inline HTML |
| `maxlength` | string | Editing limit/hint |
| `suffix` | non-boolean, non-image | Fixed UI unit such as `€` or `%` |
| `options` | supported controls | Fixed choices presented by the editing UI |
| `width`, `height` | image | Required generated image dimensions |

An image MUST NOT be required: the element must exist before its deterministic
upload path can be created.

The lower-level Elements API also understands advanced metadata such as
defaults, callbacks, whitelist, and blacklist. The current Admin schema editor
does not preserve every advanced key when it rewrites a model. Use only the
Admin-manageable shape for types intended to be edited there, or extend the
Admin editor and tests together.

`options` and `maxlength` primarily constrain the management interface; do not
treat client controls as a security boundary. When an enum or length is a
runtime invariant, validate it in the owning server-side operation too.

### 12.2 Complete type file

An installable `services.php` can contain:

```php
<?php
declare(strict_types=1);

return [
	'title' => 'Services',
	'model' => [
		'title' => [
			'type' => 'string',
			'locale' => true,
			'required' => true,
			'maxlength' => 180,
		],
		'description' => [
			'type' => 'string',
			'locale' => true,
			'html' => true,
			'maxlength' => 2000,
		],
		'linkLabel' => [
			'type' => 'string',
			'locale' => true,
			'maxlength' => 120,
		],
		'link' => [
			'type' => 'string',
			'maxlength' => 500,
		],
		'image' => [
			'type' => 'image',
			'width' => 1200,
			'height' => 800,
		],
		'featured' => [
			'type' => 'boolean',
		],
	],
	'*' => [
		'*' => [],
		'strategy' => [
			'link' => '/services/strategy',
			'featured' => true,
		],
	],
	'en_US' => [
		'strategy' => [
			'title' => 'Strategy',
			'description' => 'A clear foundation for informed decisions.',
			'linkLabel' => 'Explore strategy',
		],
	],
	'de_DE' => [
		'strategy' => [
			'title' => 'Strategie',
			'description' => 'Eine klare Basis für fundierte Entscheidungen.',
			'linkLabel' => 'Strategie entdecken',
		],
	],
];
```

Data layout:

- `title` describes the type itself.
- `model` defines all fields.
- `'*']['*']` holds global defaults.
- `'*'][<id>]` holds global fields for a record.
- `<locale>[<id>]` holds localized fields for that same record.

An Element exists if it has global or localized data. It does not need a
duplicate full record in every bucket.

New type slugs SHOULD match `^[a-z][a-z0-9_-]*$`. New record IDs accepted by
Admin match `^[A-Za-z0-9][A-Za-z0-9_-]*$` and contain no slash. Prefer
lowercase hyphenated IDs.

### 12.3 Render Elements

```html
[elements /services limit="6" query="featured=1"]
<article id="service-[[.id]]" class="ui-article">
	<img
		class="ui-article-img"
		src="[[/nino/dir]]/images/[[image]]"
		alt="[[title]]"
	>
	<div class="ui-article-content">
		<h2 class="ui-article-title">[[title]]</h2>
		<div class="ui-article-descr">[[description]]</div>
		<a class="ui-btn ui-btn--primary" href="[[link]]">
			[[linkLabel]]
		</a>
	</div>
</article>
[/elements]
```

- `[[.id]]` is the internal record ID.
- `[[field]]` is local to the current Elements block.
- normal field output is HTML-escaped;
- `html: true` fields are sanitized by the Elements renderer;
- `limit` bounds output;
- `query` matches model values and supports the existing percent wildcard
  forms.

Do not use absolute `[[/page-*]]` fills for per-record data. Do not use local
`[[title]]` outside an Element block.

### 12.4 Element changes and translations

When saving a record with global and localized fields:

- write global fields to locale `*`;
- write each locale's fields to its own locale bucket;
- do not copy the last edited locale over all others;
- preserve omitted fields during partial update;
- sanitize rich fields server-side;
- and use the public `Elements` methods so callbacks, type checks, locks, and
  cache invalidation run.

Changing an existing field between global and localized is a data migration.
Admin preserves values using the native locale/fallback rules. A model patch
must test the migration in both directions and must not leave stale locale
values that override the new shape.

### 12.5 Element tests

Use `tests/kernel-smoke.php` for model/runtime behavior,
`tests/admin-smoke.php` for type CRUD and migrations, and the relevant JS tests
for forms/uploads. Test:

- valid and invalid field types;
- required zero/false values;
- required string/array rejection;
- image dimensions and post-create upload;
- rich-text sanitization;
- global/localized merge in at least two locales;
- partial updates preserving other locale fields;
- global-to-locale and locale-to-global migration;
- query with multiple keys and wildcard forms;
- concurrent writes;
- image replacement/cleanup without deleting unrelated files;
- and rendering/escaping of local placeholders.

## 13. Security review required for every extension

Security is not a separate cleanup pass. Apply the following questions while
designing the behavior and assert important boundaries in tests.

### 13.1 Trust boundaries

Treat all of these as untrusted:

- `$_GET`, `$_POST`, headers, cookies, request body, uploaded bytes, and IP
  forwarding headers;
- Admin and Editor payloads, even after login;
- values loaded from editable `config.php`, text, Elements, and templates;
- filenames and IDs supplied by a browser;
- imported translation JSON;
- external API, webhook, feed, and mail data;
- and HTML returned from a project callback.

Authentication proves who sent data. It does not make the data structurally
valid or safe for HTML, a path, a header, or a class name.

### 13.2 Authentication and authorization

- Every Admin API method calls `Admin::guard()`.
- Every Editor API method follows the Editor's existing auth and permission
  guard pattern.
- Runtime management endpoints check a specific
  `Auth::checkPermission()` value.
- Read and write permissions are distinct when their risk differs.
- Do not accept a username in the request and check that user's permission as
  authorization for the current caller.
- Do not expose Admin or Template Builder actions through a new public route.
- Session fixation protection and cookie policy remain in the kernel; do not
  implement parallel ad-hoc auth cookies.

### 13.3 CSRF

- Keep core CSRF active on state changes.
- Render `[csrf]` in browser forms.
- Respect `./nino/csrf/blocked` before runtime write behavior.
- Admin guards also respect an already failed response.
- Do not exempt JSON requests merely because their content type is JSON.
- A signed webhook exemption validates raw bytes, timestamp/replay window,
  algorithm, and secret using constant-time comparison.

### 13.4 Identifiers, paths, classes, and URLs

- Define a full-match allowlist regex for IDs and slugs.
- Reject `..`, slashes where a flat segment is expected, null/control
  characters, and empty normalized values.
- Resolve project paths through `Filesystem` or a fixed owned base directory.
- Never pass a request-derived class string to autoloading or
  `method_exists()`.
- Never let a request choose an arbitrary callback method.
- Validate schemes for links. Reject `javascript:` and unexpected data schemes
  where the value can enter an `href` or `src`.
- For outbound HTTP, allowlist destinations where possible, set time/size
  limits, and prevent redirects to local/private addresses.

Escaping does not make path traversal safe. Validate structure before resolving
or escaping.

### 13.5 HTML and browser DOM

- Plain strings become text with `htmlspecialchars` or `textContent`.
- Rich text passes `Html::sanitizeHtml()` on the server.
- Do not trust a rich-text editor's client filtering.
- Do not concatenate request data into `innerHTML`.
- Do not concatenate strings into inline scripts/styles.
- Preserve CSP nonces and existing security headers.
- Do not add `unsafe-inline` or a remote CSP source merely to make one widget
  work.
- Neutralize Nino bracket syntax when untrusted data enters a recursively
  rendered shortcode result.
- Attribute, URL, HTML, CSS, JS, JSON, mail header, and shell contexts require
  different handling.

### 13.6 Files and uploads

- Use Nino's image processing API for uploads.
- Enforce size, decoded format, width/height, and an owned destination.
- Store generated relative filenames, not a browser filename or absolute path.
- Do not delete a previous image until the new value was successfully
  persisted; restore/cleanup on veto as current image flows do.
- Delete only files proven to be owned by the exact record/slot.
- Never recursively delete a path assembled from a request.
- Use atomic mutation for array files.
- Keep secrets and runtime records out of versioned templates/text.

### 13.7 Privacy, enumeration, and retention

- Public login, reset, newsletter, and account-like flows return generic
  responses that do not reveal whether a record exists.
- Rate-limit authentication, public mail, signup, and expensive endpoints.
- Bound log and submission retention.
- Do not log passwords, CSRF tokens, session IDs, authorization/cookie headers,
  backup keys, full request arrays, or unnecessary personal data.
- Export only the requested content scope.
- Translation import is merge-only and schema/path validated; unknown keys and
  technical/global/image fields remain untouched.

### 13.8 Failure behavior

- Use `400` for malformed input, `401` for missing authentication, `403` for
  insufficient permission, `404` for an intentionally public missing resource,
  `409` for conflicts, `429` for rate limits, and `500` for an internal write
  failure where the operation did not complete.
- Do not report success before persistence succeeds.
- Do not turn a recoverable logging failure into loss of a successfully handled
  public request.
- Do not swallow programming errors under a broad `catch(Throwable)`.
- Test the failure branch, not only the happy path.

## 14. Common incorrect approaches and their correction

| Incorrect | Correct |
| --- | --- |
| Edit a generated `templates/` file when changing future installs | Edit the owning `_install/library/...` source |
| Assume a page template creates a route | Add a route separately |
| Call a route “the template” | Keep public route, internal URI, and template body distinct |
| Add a runtime module to `Admin::MODULES` | Add runtime class to `/nino/modules` |
| Add only Admin `actions()` and `nav()` | Wire registry, shell, `TABS`, asset, and tests |
| Rely only on dispatcher auth | Guard every API method |
| Use direct `echo`/`header()` | Modify `$request` / use `Http` |
| Read then write a shared PHP array | Use `Filesystem::mutate()` |
| Serialize all `$appData` | Persist selected keys with the owning writer |
| Use `innerHTML` for an API label | Construct DOM and use `textContent` |
| Validate only in JavaScript | Repeat strict validation server-side |
| Make image fields required | Create the Element, then upload |
| Save one merged Element object to every locale | Split global and locale fields |
| Invent a Section token | Use only the seven supported token forms |
| Put an Elements image into `{{image:image}}` | Use local `[[image]]` in the loop |
| Put `limit` in preset `allow` | Set its default; Composer clamps `1..12` |
| Choose any globally valid layout | Choose one valid for the content module |
| Put several top-level sections in `section.tpl` | Exactly one complete root section |
| Add `js-vpa` behavior to preview | Let preview strip VPA and stay visible |
| Enable scripts/remote forms in preview | Keep the sandbox deterministic and inert |
| Prefix reusable includes with `page-` | Reserve `page-*` for full page templates |
| Add metadata below markup | Put both metadata comments at byte zero |
| Remove copied installer files on deselect | Update selection only; preserve edited files |
| Assume page-unit `files` is applied | Use a supported module asset path or add tested support |
| Put translated words in IDs/fill keys | Use stable semantic slugs |
| Update only English or German behavioral docs | Keep both manuals synchronized |
| Test for a new file but not behavior | Assert observable response/data/DOM contracts |

When an existing pattern appears to violate this guide, inspect why. It may be
legacy compatibility covered by a test. Do not duplicate the legacy shape into
new code unless the compatibility requirement applies.

## 15. Test and validation matrix

Every test is a standalone script. It creates or should create its own isolated
temporary project and must not rely on a previously installed working tree.

| Changed area | Minimum targeted tests |
| --- | --- |
| Kernel, callbacks, rendering, runtime module | `tests/kernel-smoke.php` |
| Shared writes, locks, logs | `tests/concurrency-smoke.php` plus owner test |
| Admin backend | `tests/admin-smoke.php` |
| Admin frontend | relevant `tests/admin-*-js-smoke.js` |
| Editor backend | `tests/editor-smoke.php` |
| Editor frontend | relevant `tests/editor-*-js-smoke.js` |
| Installer behavior/library | `tests/install-smoke.php` and relevant install JS test |
| Section preset/Template Builder PHP | `tests/templates-smoke.php` |
| Template Builder browser behavior | `tests/templates-js-smoke.js` |
| Shared public UI slider/tabs | corresponding `tests/nino-ui-*-js-smoke.js` |
| Shared management UI/CSS structure | owner tests plus `tests/admin-lists-js-smoke.js` |

Complete suite:

```bash
php tests/kernel-smoke.php
php tests/editor-smoke.php
php tests/admin-smoke.php
php tests/install-smoke.php
php tests/templates-smoke.php
for test in tests/*-js-smoke.js; do node "$test"; done
php tests/concurrency-smoke.php
```

Syntax and diff checks:

```bash
find . -name '*.php' -not -path './data/*' -print0 \
	| xargs -0 -n1 php -l
find . -name '*.js' -not -path './data/*' -print0 \
	| xargs -0 -n1 node --check
git diff --check
```

Do not claim a check passed if the required interpreter, extension, browser
capability, or fixture was unavailable. Report it as not run and explain why.

### 15.1 What a useful smoke assertion tests

Prefer:

- exact HTTP status and safe response shape;
- exact persisted data after a fresh read;
- atomic survival of two independent changes;
- route ownership and unrelated-route survival;
- permission/CSRF denial;
- sanitized rendered output;
- lossless source reconstruction;
- real component/preset behavior;
- and stable UI state after a request.

Avoid:

- asserting only that a method exists;
- snapshotting an entire unstable HTML page;
- checking a CSS class with no behavioral reason;
- calling private methods directly instead of the public flow;
- or passing because errors were globally suppressed.

### 15.2 Patch verification

For patch-only delivery:

```bash
git diff --binary --no-ext-diff > nino-change.patch
# git diff does not include untracked files. Append each new file explicitly:
git diff --no-index --binary -- /dev/null AGENTS.md \
	>> nino-change.patch || test $? -eq 1
git diff --check
git diff --no-index --check -- /dev/null AGENTS.md || test $? -eq 1
```

In a clean checkout at the intended base:

```bash
git apply --check /path/to/nino-change.patch
git apply /path/to/nino-change.patch
git diff --check
```

Then run the relevant tests in that checkout. A patch that applies only on a
dirty or older tree is not a valid handoff.

## 16. Definition of done by artifact

### Admin backend done

- [ ] Backend class has unique actions and per-method guards.
- [ ] Payload, IDs, enums, lengths, and paths are validated.
- [ ] Persistence is atomic and failures are returned.
- [ ] `Admin::MODULES`, shell nav/pane/script, and `TABS` are synchronized.
- [ ] JS uses existing UI primitives and safe DOM construction.
- [ ] Empty, loading, error, list, form, and saved states are usable.
- [ ] Backend and JS tests cover denial and mutation.

### Runtime module done

- [ ] Class and autoload path match exactly.
- [ ] `init()` registers behavior without output or incidental writes.
- [ ] Technical routes have one owner and correct callback identity.
- [ ] Shortcode/response output is context-safe.
- [ ] Writes enforce CSRF/auth/validation/rate limit as applicable.
- [ ] Shared files use atomic mutation and bounded retention.
- [ ] Activation, API, shortcode, failure, and concurrency are tested.

### Installer module package done

- [ ] Runtime class exists independently of the manifest.
- [ ] Manifest uses recognized keys and installer slugs for requirements.
- [ ] Templates/files/types/text land in exact paths.
- [ ] Config defaults preserve existing custom values.
- [ ] Reapply is idempotent for selection/config.
- [ ] Deselect does not destructively delete copied files.
- [ ] Direct selection and page auto-require paths are tested.

### Section preset done

- [ ] Slug, name, description, category, tags, version, and shell are valid.
- [ ] All defaults are complete and content/layout compatible.
- [ ] `allow` exposes only intended variations.
- [ ] Generic renderer is used unless custom DOM is necessary.
- [ ] Custom template has one root and only resolvable tokens.
- [ ] Native fields, image slots, and Element schema are exact.
- [ ] Preview is deterministic, visible, inert, and representative.
- [ ] Composer, invalid-choice, preview, and search tests pass.

### Page/template unit done

- [ ] Route and template are separate and have one owner each.
- [ ] `page-*.tpl` filename, display metadata, VPA, and slots are valid.
- [ ] Sections have unique semantic IDs and stable fill paths.
- [ ] Includes omit `.tpl` and resolve to shipped/copied files.
- [ ] Required text, modules, image slots, and Element types are supplied.
- [ ] Locale suggestions and actual content are separated.
- [ ] Install/reapply/render and Template Builder losslessness are tested.

## 17. Completion report format

At the end of an implementation, report:

1. **Outcome:** one sentence describing the observable result.
2. **Changed:** exact files grouped by backend, frontend, template/library,
   tests, and docs.
3. **Security/data:** auth, CSRF, validation, escaping, persistence, migration,
   and deletion behavior that mattered.
4. **Validation:** every command actually run and its result.
5. **Not run:** unavailable or deliberately omitted checks.
6. **Handoff:** patch filename and exact base revision when patch delivery was
   requested.
7. **Limitations:** only real remaining constraints; do not hide them in the
   implementation narrative.

Never say “done” when only the happy path was implemented or when the generated
artifact was not checked against its consumer.

## 18. High-value source references

Read these before designing a new implementation:

| Need | Start with |
| --- | --- |
| Runtime lifecycle/APIs | `docs/development.md` and `_nino/Nino.php` |
| Simple shortcode module | `_nino/Nino/Modules/Navigation/Navigation.php` |
| Public validated form | `_nino/Nino/Modules/Form/Form.php` |
| Privacy-sensitive public flow | `_nino/Nino/Modules/Newsletter/Newsletter.php` |
| Admin backend patterns | `_admin/Admin.php` |
| Admin list/form JS | `_admin/assets/elementtypes.js`, `elements.js`, `pages.js` |
| Ordered Admin relationships | `\Nino\Admin\Navigations` and `_admin/assets/navs.js` |
| Shared Admin UI | `_nino/Nino.admin.css` and `_nino/Nino.js` |
| Installer package shape | `_install/library/modules/*/manifest.php` |
| Installer semantics | `_install/Install.php` and `tests/install-smoke.php` |
| Generic Section presets | `_templates/library/*/manifest.php` |
| Custom Section preset | `_templates/library/hero-split/` |
| Composer/parser contracts | `_templates/Templates.php` and `tests/templates-smoke.php` |
| Basic page unit | `_install/library/pages/home/` |
| Module-dependent page | `_install/library/pages/contact/` |
| Locale-structural page | `_install/library/pages/legal/` |
| Element file example | `_install/library/pages/.demo-elements/demo-services.php` |

Human behavior changes normally require matching updates in both English and
German manuals. This AI guide stays in one canonical English file so agents do
not receive two divergent machine instructions.
