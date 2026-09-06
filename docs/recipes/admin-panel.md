# Recipe: Add a panel to the workbench

**Additional Links:**
[Agent guide](../../AGENTS.md) · [All recipes](README.md) · [Developer Manual](../development.md) · [Concepts](../concepts.md) · [`/_admin` Workbench](../_admin.md) · [Setup Wizard](../setup.md) · [Templates Panel](../templates.md)

One of the six extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the required workflow, the core runtime model, the conventions and the
security review - apply to every step below.


A panel is one screen of the workbench: a navigation link, a content pane,
an action map, a permission, and the script that renders into the pane.
`/_admin` builds its shell from a panel registry (`\Nino\Admin\Panels`), so a
panel is added by writing one class. The navigation, the pane, the bundles,
the text fills, the dashboard tile and the roles tab's permission checkbox
are rendered from what that class answers; the shell template is never edited.

Two kinds of panel exist, and the class looks the same for both:

- A **module panel** is answered by a runtime module's `adminPanels()` (see
  "Panels, the installer unit and Restore" in the [runtime module
  recipe](runtime-module.md)) and exists exactly while that module is active. Prefer it whenever the
  screen belongs to a feature: the feature then ships, and is removed, as one
  directory. `Modules\Form` (Submissions), `Modules\Newsletter`,
  `Modules\Navigation`, `Modules\Search`, `Modules\Design` and
  `Modules\Templates` are built this way.
- A **workbench panel** is a module under `_admin/Nino/Modules/<Name>/`, the
  same shape as above but delivered with the tool rather than with a runtime
  feature: `Admin/Admin.php` is the panel, `<Tab>/<Tab>.php` a tab of it.
  `Admin::modules()` finds it by reading the directory, so there is no list to
  add it to. Use it only for a screen every project has regardless of its
  modules - Dashboard, Elements, Text, Images, Logs, Routes, Users, Language,
  Backups and Config are built this way.

Which accounts see a panel is its `perm()` and nothing else: a content panel
(editors and developers) declares one under the `content` group, a developer
panel one under `structure` or `system`. Do not put public-site behavior in a
panel.

## The panel contract

A panel is a class with two required and a handful of optional static methods.
There is no interface and no base class, the same way a runtime module is a
class with `init()`. `\Nino\Admin\Panels::collect()` reads them once per
request:

| Method | Required | Returns |
| --- | --- | --- |
| `actions()` | yes | `[ 'slug/action' => [ Class::class, 'apiMethod' ], ... ]` |
| `nav()` | yes | `[ uri, label, weight = 50, group = 'content' ]`, see below |
| `perm()` | yes in practice | the permission that shows the link and gates the panel's actions; `''` only for a panel every account may use (the Dashboard) |
| `panes()` | no | mount ids rendered inside the pane, default `[ '<uri>-list' ]` |
| `template()` | no | instead of mount points: a `.tpl` rendered whole into the pane through the `[template]` shortcode - project-relative, no extension, no `..` |
| `layout()` | no | `'page'` (default: a column of content at reading width) or `'workspace'` (the whole pane, the rail folded to its icons) |
| `icon()` | no | an inline `<svg>` for the rail; without one the folded rail shows the label's initial. Held to that one shape: nothing that runs |
| `tabs()` | no | further panel classes shown as tabs of this panel's pane, see below |
| `tab()` | no | what the tab strip calls this panel's own screen, when its nav label will not do (Language: `'Languages'`) |
| `assets()` | no | project-relative `.js`/`.css` files the panel brings, bundled into `/_admin/.cache/` after the shell's own |
| `text()` | no | directory holding the panel's `<locale>.php` fill files |
| `summary( &$appData )` | no | a Dashboard tile `[ 'value' => ..., 'label' => ... ]`, `null` for none |
| `log( $action, $data )` | no | the activity-log line for a completed action, `''` for none |

`nav()` rules:

- `uri` is a slug (`/^[a-z][a-z0-9-]*$/`) and MUST be unique. It names the
  link (`admin-nav-<uri>`), the pane (`admin-content-<uri>`), the shell state
  class `show-<uri>`, the hash prefix (`#<uri>/...`) and the JS namespace the
  shell calls (`Nino.admin.<uri>`). A second class claiming a taken uri is
  dropped with a warning, so a module cannot replace a core screen.
- A `label` starting with `/` is a fill key, resolved by the normal fill pass
  from `_admin/text/<locale>.php` and the panel's `text()` files; anything
  else is literal text and is escaped. Every shipped panel uses a fill - the
  workbench speaks one language per account, so a module panel brings a
  `text/<locale>.php` for every interface language rather than an English
  literal. The same goes for `tab()`, a `summary()` tile's label and the
  labels and hints a schema hands to the frontend (`Nino.adminUi.text()`
  resolves a value that starts with `/`, `tests/admin-system-smoke.php` fails
  on a key one of the two languages lacks).
- `group` is `content`, `structure` or `system` and decides the heading the
  link sits under; an unknown group falls back to `content` with a warning.
  The **Editor** role the wizard writes is every `content` panel's `perm()`
  at that moment (`Roles::defaults()`); a content panel a module brings later
  is offered on the roles tab like every other.
- `weight` orders the navigation within the group, lowest first, stable for
  equal weights. Core content panels sit at 0 (Dashboard), 20 (Elements), 30
  (Text), 40 (Images) and 90 (Logs); structure at 2 (Templates), 5 (Design)
  and 20 (Routes); system at 2 (Users), 5 (Language), 10 (Backups) and 20
  (Config). A module panel picks the slot it wants: Submissions 60,
  Newsletter 65, Navigations 25 (after Routes), Search 30 (system).

`tabs()` rules:

- A tab is a full panel class - `actions()`, `nav()`, `perm()`, `panes()`,
  `assets()` - whose uri is unique across panels and tabs alike. It is never
  a rail entry: its `nav()` group only says where its permission is listed,
  its weight orders the strip. The shell selects it through `#<uri>`, shows
  `admin-tab-<uri>` and calls `Nino.admin.<uri>.showCurrent()` exactly like a
  panel; `Nino.admin.router.exists()` knows both kinds.
- The strip is rendered only when the account holds more than one screen of
  the pane, the panel's own first. An account holding a tab's permission but
  not the panel's still gets the pane, on that tab alone
  (`Admin::visiblePanels()`, `'own' => false`). A tab has no tabs of its own.
- The workbench's own modules use it: Element Types under Elements, Text Keys under Text,
  Image Slots under Images (each a structure permission beside the content it
  shapes), User roles (sharing `/_admin/users/manage`) and Login protection
  (`/_admin/lockout/manage`) under Users, Translations under Language. The
  strip is the design system's `.nino-admin-tabs--bar`, the same the Design
  panel renders for its four editors.

A dispatched action announces itself on `/nino/admin/action`
(`{ action, panel, status, user, data }`) once it has answered - the one place a
module reacts to what the workbench does without owning the panel the action
belongs to. Notification only: the response is already written, so refusing
stays `guardPerm()`'s job, and *what changed* is the kernel's own events
(`/nino/elements/committed`, `/nino/auth/user/*`). The activity log is not a
listener on it but a direct call, deliberately - an audit line that can be lost
by not registering a callback is not an audit line.

Actions are the panel's slug plus a verb, and every action method guards
itself with `\Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM )`
even though `handlePost()` dispatches through the registry: the method-level
guard protects direct calls in tests and future dispatch changes, and it is
what answers `401` without an account and `403` without the permission.
Core actions are merged first; a module action reusing a core action's name is
ignored.

Assets are validated (`^/[A-Za-z0-9_./-]+\.(js|css)$`, no `..`) and bundled
into `/_admin/.cache/script.js` and `style.css` after the shell's own files.
A panel names them from where its class is, so they move with the module:

```php
public static function assets(): array {
	return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
}
```

A panel's stylesheet opens with `@layer nino.system, nino.tool, nino.local;`
and puts its rules in `@layer nino.tool {}`, every one scoped to the panel's
own root (6a).

Permissions declared through `perm()` are offered automatically: the Users
panel's roles tab (`Users\Admin::permOptions()`) lists every active panel's and
tab's permission once, under its nav label and in its nav group, and
`Roles::defaults()` builds the Editor role the wizard writes from the content
ones. That list also carries every permission this installation actually holds
- on a role or directly on an account - that no panel is offering right now
(`offered => false`, group `other`, named by its own string), because the
picker only sends back the rows it could show and leaving one out would make
the next save of the role that holds it drop it silently; nothing is invented
that nobody holds. It is not the limit of what a role may hold, though:
`Roles::apiSave()` checks each permission for shape (`PERM_PATTERN`) and
refuses a malformed one by name, so a scoped permission can be typed into the
roles form. What an account may do is `\Nino\Auth::permissions()`: its own
`perms` plus its role's (`/nino/auth/roles`), and every guard reads that.

A panel's own permission is a door. What may be done once inside it can be
described further with a **scoped permission** - one string per action, and per
field or key where that is the unit:
`/_admin/elements/services/update/title`, `/_admin/text/update/page-home/*`.
They are ordinary permission strings, so `\Nino\Auth::checkPermission()`'s
`/*` ancestor rule is what makes a whole type or group grantable at once, and
`\Nino\Admin\Admin::scoped( $appData, $prefix, $perm )` is what a panel calls:
it answers true unmodified for an account holding none of them under `$prefix`
(`isScoped()`), so a panel permission keeps meaning what it always meant, and
checks the specific string for an account that holds one. A panel that grows
scoped permissions sends what the current account may do along with its data
(see `Elements\Admin::rights()`, `Text\Admin::apiKeys()`) so its form can draw
itself accordingly - the enforcement stays in the action methods.

Choose one stable slug and use it everywhere:

| Place | Example |
| --- | --- |
| Panel class | `Project\Catalog\Catalog\Admin` (module) or `Nino\Admin\ProjectNotes` (core) |
| Permission | `/_admin/catalog/manage` |
| Action prefix | `catalog/*` |
| `nav()` uri | `catalog` |
| Pane mount ids | `catalog-list`, `catalog-form` |
| JS namespace | `Nino.admin.catalog` |
| Asset | `app/Project/Catalog/Catalog/assets/admin.js` |

## Backend skeleton

A panel of the project module from the [runtime module recipe](runtime-module.md), in
`app/Project/Catalog/Catalog/Admin/Admin.php`:

```php
<?php
declare(strict_types=1);

namespace Project\Catalog\Catalog {

	class Admin {

		public const string MANAGE_PERM = '/_admin/catalog/manage';

		private const string STORAGE = '/data/catalog-notes.php';
		private const int MAX_TITLE_LENGTH = 160;
		private const int MAX_BODY_LENGTH = 5000;

		public static function actions(): array {
			return [
				'catalog/list' => [ self::class, 'apiList' ],
				'catalog/save' => [ self::class, 'apiSave' ],
			];
		}

		public static function nav(): array {
			return [ 'catalog', '/_admin/nav/catalog', 60, 'content' ];
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'catalog-list', 'catalog-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ) ];
		}

		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function summary( array &$appData ): array {
			return [ 'value' => count( self::_notes( $appData ) ), 'label' => '/_admin/catalog/label/count' ];
		}

		public static function log( string $action, array $data ): string {
			return $action === 'catalog/save' ? 'Saved catalog note "'. (string) ( $data['id'] ?? '' ). '"' : '';
		}

		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			\Nino\Http::ok( $request, [ 'notes' => array_values( self::_notes( $appData ) ) ] );
		}

		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data  = \Nino\Admin\Admin::postData();
			$id    = strtolower( trim( (string) ( $data['id'] ?? '' ) ) );
			$title = trim( (string) ( $data['title'] ?? '' ) );
			$body  = trim( (string) ( $data['body'] ?? '' ) );

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $id ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid note id' );
				return;
			}

			if( $title === '' || strlen( $title ) > self::MAX_TITLE_LENGTH || strlen( $body ) > self::MAX_BODY_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'invalid note content' );
				return;
			}

			$entry = [
				'id'      => $id,
				'title'   => $title,
				'body'    => $body,
				'updated' => date( DATE_ATOM ),
			];

			$written = \Nino\Filesystem::mutate( $appData, self::STORAGE, function( mixed $state ) use ( $id, $entry ): array {
				$notes = is_array( $state ) ? $state : [];
				$notes[$id] = $entry;
				ksort( $notes );
				return $notes;
			}, [] );

			if( $written === false ) {
				\Nino\Http::fail( $request, 500, 'could not save note' );
				return;
			}

			\Nino\Http::ok( $request, [ 'note' => $entry ] );
		}

		private static function _notes( array &$appData ): array {

			$notes = \Nino\Filesystem::getFileContent( $appData, self::STORAGE, [] );

			return is_array( $notes ) === true ? $notes : [];
		}
	}

}
```

The module answers with the class name, and nothing else is registered
anywhere:

```php
public static function adminPanels( array &$appData ): array {
	return [ \Project\Catalog\Catalog\Admin::class ];
}
```

`text()` names a directory of `<locale>.php` files with the same shape as
`_admin/text/<locale>.php`; they are merged into the workbench's fills for the
current UI locale, so `[[/_admin/nav/catalog]]` and the tile label resolve
like the tool's own:

```php
<?php
return [
	'[[/_admin/nav/catalog]]'         => 'Catalog',
	'[[/_admin/catalog/label/count]]' => 'Catalog entries',
];
```

A workbench panel has the identical body; it lives in
`_admin/Nino/Modules/<Name>/Admin/Admin.php` (namespace `Nino\Modules\<Name>`,
so `\Nino\Admin\Admin::guardPerm()` is spelled out there too) and needs no
registration at all - `Admin::modules()` reads the directory. The runtime half
of the same module name stays where it belongs: `\Nino\Modules\Elements` is
the kernel's module in `_nino/`, `\Nino\Modules\Elements\Admin` the screen
for it in `_admin/`.

This skeleton demonstrates the required invariants:

- Every API method calls the guard itself, with the panel's permission.
- Payload comes from `Admin::postData()`, which decodes the JSON `data` field.
- IDs, lengths, and required fields are validated server-side.
- The file update is atomic.
- The response uses `Http::ok()` or `Http::fail()`.
- Nothing is directly printed.

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

## A panel with its own template, layout and icon

A panel that lays out its own regions - a two-column editor, a three-column
workspace - answers `template()` instead of `panes()`, and the registry
renders that file whole into the pane through the `[template]` shortcode, so
its fills (`[[/nino/dir]]`, the panel's own text keys) resolve like the
shell's. The file is a fragment: no `<html>`, no `[csrf]` (the page has one),
no `<link>` or `<script>` (the panel's `assets()` are bundled). Every id and
class in it is the panel's own; the components are the design system's.
`app/Nino/Modules/Design` (four editors under a tab strip, `layout()` =
`'page'`) and `app/Nino/Modules/Templates` (the Template Builder, `layout()` =
`'workspace'`) are the shipped references:

```php
public static function template(): string {
	return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/templates/panel' );
}

public static function layout(): string {
	return 'workspace';
}

public static function icon(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="7" x="3" y="3" rx="1"/><rect width="9" height="7" x="3" y="14" rx="1"/><rect width="5" height="7" x="16" y="14" rx="1"/></svg>';
}
```

A `workspace` panel gets the whole pane (no reading-width ceiling, no
padding) and folds the rail to its icons when it is selected; the account can
pin the rail either way, and the shell remembers that in `localStorage`. The
panel lays out its own regions inside `#<its-root>` and scrolls them itself -
on a desktop the pane is `100dvh` tall. A `page` panel is a column of content
at reading width, like every other panel.

## Frontend skeleton

The shell shows the pane whose `data-panel` matches the selected link and
calls `Nino.admin.<uri>.showCurrent()` when a tab is selected, if such a
function exists - which is also where a panel loads its data the first time,
so a panel nobody opens costs no request. Inside the pane the panel owns its
mount points (`panes()`), and toggles between them with the shell's
`admin-hidden` class. The url hash is the panel's to deep-link into:
`Nino.admin.router.set( 'catalog', [ id ] )` while the panel is on screen,
`Nino.admin.router.current()` to read it back on load. The following is a minimal list/edit
lifecycle; adapt field names, but preserve the API, DOM safety, and lifecycle
shape:

```js
( function(wn,dc) {

	wn.Nino.admin = wn.Nino.admin || {};

	Nino.admin.catalog = {

		_items : [],
		_ready : false,

		init : function() {

			if( dc.getElementById('catalog-list') === null )
				return;

			Nino.admin.catalog._apiCall( 'list', {}, function( status, response ) {
				if( status !== 200 || response === null )
					return Nino.admin.catalog._showError( dc.getElementById('catalog-list'), status, response );

				Nino.admin.catalog._items = response.notes || [];
				Nino.admin.catalog._renderList();
				Nino.admin.catalog._showList();
				Nino.admin.catalog._ready = true;
			} );
		},

		showCurrent : function() {

			if( Nino.admin.catalog._ready === false )
				return;

			if( dc.getElementById('catalog-form').classList.contains('admin-hidden') === false )
				return;

			Nino.admin.catalog._showList();
		},

		_apiCall : function( endpoint, payload, callback ) {
			Nino.http.sendRequest( '/_admin/', 'POST', function( xhr ) {
				callback( xhr.status, xhr.responseJSON );
			}, {
				action : 'catalog/'+ endpoint,
				data : JSON.stringify( payload ),
			} );
		},

		_showError : function( container, status, response ) {
			container.innerHTML = '';
			const message = dc.createElement('p');
			message.className = 'nino-admin-error';
			message.textContent = '('+ status+ ') '+ ( response && response.error ? response.error : 'Request failed.' );
			container.appendChild( message );
		},

		_showList : function() {
			dc.getElementById('catalog-list').classList.remove('admin-hidden');
			dc.getElementById('catalog-form').classList.add('admin-hidden');
		},

		_showForm : function() {
			dc.getElementById('catalog-list').classList.add('admin-hidden');
			dc.getElementById('catalog-form').classList.remove('admin-hidden');
		},

		_renderList : function() {

			const wrap = dc.getElementById('catalog-list');
			wrap.innerHTML = '';

			const heading = dc.createElement('h2');
			heading.textContent = 'Catalog';
			wrap.appendChild( heading );

			const list = dc.createElement('ul');
			list.className = 'nino-admin-list';

			if( Nino.admin.catalog._items.length === 0 ) {
				const empty = dc.createElement('p');
				empty.textContent = 'No catalog notes yet.';
				wrap.appendChild( empty );
			}

			Nino.admin.catalog._items.forEach( function( item ) {
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
					Nino.admin.catalog._renderForm( item );
				} );

				li.appendChild( link );
				list.appendChild( li );
			} );

			wrap.appendChild( list );

			const add = dc.createElement('button');
			add.type = 'button';
			add.className = 'nino-btn nino-btn--primary';
			add.textContent = 'New note';
			add.addEventListener( 'click', function() {
				Nino.admin.catalog._renderForm( null );
			} );
			wrap.appendChild( Nino.adminUi.listActions( [ add ] ) );
		},

		_renderForm : function( item ) {

			const wrap = dc.getElementById('catalog-form');
			wrap.innerHTML = '';

			const back = dc.createElement('a');
			back.href = '#';
			back.className = 'nino-admin-back-link';
			back.textContent = 'Back to catalog';
			back.addEventListener( 'click', function( event ) {
				event.preventDefault();
				Nino.admin.catalog._showList();
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
			idLabel.htmlFor = 'catalog-id';
			id.id = 'catalog-id';
			id.type = 'text';
			id.name = 'id';
			id.pattern = '[a-z][a-z0-9-]*';
			id.required = true;
			id.value = item ? item.id : '';
			id.disabled = item !== null;

			titleLabel.textContent = 'Title';
			titleLabel.htmlFor = 'catalog-title';
			title.id = 'catalog-title';
			title.type = 'text';
			title.name = 'title';
			title.maxLength = 160;
			title.required = true;
			title.value = item ? item.title : '';

			bodyLabel.textContent = 'Body';
			bodyLabel.htmlFor = 'catalog-body';
			body.id = 'catalog-body';
			body.name = 'body';
			body.maxLength = 5000;
			body.value = item ? item.body : '';

			message.className = 'admin-form-message';
			message.setAttribute( 'aria-live', 'polite' );

			save.type = 'submit';
			save.className = 'nino-btn nino-btn--primary';
			save.textContent = 'Save';

			actions.className = 'nino-admin-actionbar';
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

				Nino.admin.catalog._apiCall( 'save', {
					id : id.value,
					title : title.value,
					body : body.value,
				}, function( status, response ) {
					save.disabled = false;

					if( status !== 200 ) {
						message.textContent = response && response.error ? response.error : 'Could not save.';
						return;
					}

					const index = Nino.admin.catalog._items.findIndex( function( current ) {
						return current.id === response.note.id;
					} );
					if( index === -1 )
						Nino.admin.catalog._items.push( response.note );
					else
						Nino.admin.catalog._items[index] = response.note;

					Nino.admin.catalog._items.sort( function( a, b ) {
						return a.id.localeCompare( b.id );
					} );
					Nino.admin.catalog._renderList();
					Nino.admin.catalog._showList();
				} );
			} );

			wrap.appendChild( form );
			Nino.admin.catalog._showForm();
			title.focus();
		},

	};

	Nino.events.bindCallback( 'ready', Nino.admin.catalog.init );

} )(window,document);
```

Do not copy the skeleton blindly if an existing panel already solves the same
list, locale, upload, rich-text, reorder, relationship, or confirmation
problem. Reuse its exact public helper and adapt the nearest implementation.
For a registry plus ordered route membership, inspect
`\Nino\Modules\Navigation\Admin` and
`app/Nino/Modules/Navigation/assets/admin.js`; for the smallest complete
panel, `\Nino\Modules\Search\Admin` and its `assets/admin.js`.

## Panel tests

Backend tests belong in `tests/admin-smoke.php` (content panels, accounts)
or `tests/admin-system-smoke.php` (structure and system panels, the registry
contract, recovery). They MUST exercise:

- unauthenticated rejection (`401`) and a missing permission (`403`),
- successful list and save,
- malformed IDs and fields,
- maximum lengths and unexpected payload shape,
- persistence after a fresh read,
- an existing-record update,
- a failed or vetoed write if the feature supports one,
- survival of unrelated records during update/delete,
- and, for a module panel, that the panel is absent from the registry and its
  actions are unknown while the module is not in `/nino/modules`.

The registry itself is covered once, in the "registry contract" blocks of
both suites; a new panel does not repeat those checks.

JavaScript behavior belongs in a focused `tests/admin-<slug>-js-smoke.js` or
an existing shared smoke test. At minimum assert:

- asset and namespace load, and the namespace equals the nav uri,
- action names and JSON payload shape,
- list-to-form and back lifecycle,
- server text is inserted safely,
- save updates the model/UI,
- and shared list/context/action bars are used.

Run:

```bash
php -l app/Project/Catalog/Catalog/Admin/Admin.php
node --check app/Project/Catalog/Catalog/assets/admin.js
php tests/admin-smoke.php
php tests/admin-system-smoke.php
node tests/admin-catalog-js-smoke.js
node tests/admin-lists-js-smoke.js
```

Use the actual test filename created by the change.
