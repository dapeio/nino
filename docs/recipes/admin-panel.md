# Recipe: Add a panel to the workbench

One of the five extension recipes of the [Nino agent guide](../../AGENTS.md). Its
rules - the working method, the core runtime model, the conventions and the
security review - apply to every step below.


How a panel looks and behaves is not this recipe's to decide: [The workbench
interface](../admin-ui.md) is the binding rule for every panel, a feature's
included - the build of a view, lists, forms, saving, dialogs, words and the
tokens - and a panel is reviewed against its checklist.

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
  screen belongs to a module or a feature: it then ships, and is removed, as
  one directory. `Modules\Form` (Submissions) and `Modules\Navigation` are
  built this way as kernel modules, `Modules\Builder`,
  `Modules\Newsletter` and `Modules\Search` as features from the
  catalogue [dapeio/nino-features](https://github.com/dapeio/nino-features),
  copied under `features/` (the [feature manual](../features.md)).
- A **workbench panel** is a module under `_admin/Nino/Modules/<Name>/`, the
  same shape as above but delivered with the tool rather than with a runtime
  feature: `Admin/Admin.php` is the panel, `<Tab>/<Tab>.php` a tab of it.
  `Admin::modules()` finds it by reading the directory, so there is no list to
  add it to. Use it only for a screen every project has regardless of its
  modules - Dashboard, Elements, Text, Images, Logs, Routes, Users, Language,
  Backups, Features and Config are built this way.

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
| `scope()` | no, tabs only | the prefix of an area both the tab's `perm()` and the `perm()` of its panel (the door) lie in, such as `/_admin/builder/` - it begins and ends with `/` and lies under `/_admin/`. With one the tab is open to an account that holds its `perm()`, or holds the door and was described in no detail in that area (`Admin::isScoped()`); an account with neither gets nothing, in the rail, the tiles and the actions. A scope() the permissions do not lie in is a warning and the tab has a plain `perm()` |
| `panes()` | no | mount ids rendered inside the pane, default `[ '<uri>-list' ]` |
| `template()` | no | instead of mount points: a `.tpl` rendered whole into the pane through the `[template]` shortcode - project-relative, no extension, no `..` |
| `layout()` | no | `'page'` (default: a column of content at reading width) or `'workspace'` (the whole pane, the rail folded to its icons) |
| `head()` | no | `false` opens the pane on its screen alone, without the head that names the panel, for a work surface that names itself in its context bar; every other panel gets one, the Dashboard too, see below |
| `manual()` | no | the handles of the panel's manual, in the order its `?` shows them: `'intro'` first, what the panel is for, then one for each thing a person does in it. A handle is a slug and a word of the Text panel's vocabulary; its line is the fill `/_admin/<uri>/manual/<handle>` of the panel's `text()` files, in every language. A feature's panel without one shows the manual of its manifest, and one with neither has no `?` - see [The manual](#the-manual) |
| `icon()` | no | an inline `<svg>` for the rail; without one the folded rail shows the label's initial. Held to that one shape: nothing that runs |
| `tabs()` | no | further panel classes shown as tabs of this panel's pane, see below |
| `tab()` | no | what the tab strip calls this panel's own screen, when its nav label will not do (Language: `'Languages'`) |
| `assets()` | no | project-relative `.js`/`.css` files the panel brings, bundled into `/_admin/.cache/` after the shell's own |
| `text()` | no | directory holding the panel's `<locale>.php` fill files |
| `summary( &$appData )` | no | a Dashboard tile `[ 'value' => ..., 'label' => ... ]`, `null` for none |
| `log( $action, $data )` | no | the activity-log line for a completed action, `''` for none |
| `scopes( &$appData )` | no | the scoped permissions the panel knows, for the roles tab's three lists, see below: `[ { scope, door, label, areas } ]` |

`nav()` rules:

- `uri` is a slug (`/^[a-z][a-z0-9-]*$/`) and MUST be unique. It names the
  link (`admin-nav-<uri>`), the pane (`admin-content-<uri>`), the shell state
  class `show-<uri>`, the hash prefix (`#<uri>/...`) and the JS namespace the
  shell calls (`Nino.admin.<uri>`). The first claim of a uri stands: a second
  class claiming a taken one is dropped with a warning that names both, so a
  module cannot replace a core screen. The claims are made in the order of the
  workbench's own panels with their tabs, the panels of the kernel's own
  modules, and then the other modules' panels as `/nino/modules` lists them.
  The uris of the first two are therefore reserved, wherever a module stands in
  that list: `dashboard`, `elements`, `types`, `text`, `keys`, `images`,
  `slots`, `logs`, `routes`, `users`, `roles`, `lockout`, `recoverypw`,
  `language`, `translations`, `backups`, `features` and `config`, and the
  screens of the Form, Navigation and Maintenance modules - `submissions`,
  `navs` and `maintenance`. Two modules naming one uri that is neither: the
  module listed first keeps it.
- A `label` starting with `/` is a fill key, resolved by the normal fill pass
  from `_admin/text/<locale>.php` and the panel's `text()` files; anything
  else is literal text and is escaped. Every shipped panel uses a fill - the
  workbench speaks one language per account, so a module panel brings a
  `text/<locale>.php` for every interface language rather than an English
  literal. The same goes for `tab()`, a `summary()` tile's label and the
  labels and hints a schema hands to the frontend (`Nino.adminUi.text()`
  resolves a value that starts with `/`, `tests/admin-shell-smoke.php` fails
  on a key one of the two languages lacks).
- `group` is `content`, `structure`, `features` or `system` and decides the
  heading the link sits under; an unknown group falls back to `content` with
  a warning. `features` is not a free choice: it is refused the same way an
  unknown group is unless the panel's own class file lies below
  `\Nino\Features::dir()`, and a panel that does live there lands in
  `features` regardless of what it names - a group, none or a word that is no
  group - and with no warning, since the entry is read for the kernel's own
  and `app/` panels only; see `Panels::_isFeaturePanel()`.
  The **Editor** role the wizard writes is every `content` panel's `perm()`
  at that moment (`Roles::defaults()`); a content panel a module brings later
  is offered on the roles tab like every other, and so is a feature's under
  the `features` group.
- `weight` orders the navigation within the group, lowest first, stable for
  equal weights. Core content panels sit at 0 (Dashboard), 20 (Elements), 30
  (Text), 40 (Images) and 90 (Logs); structure at 20 (Routes); system at
  2 (Users), 5 (Language), 10 (Backups), 15 (Features) and 20
  (Config). A module panel picks the slot it wants: Submissions 60,
  Navigations 25 (after Routes), Maintenance 18; a feature's panel names a
  weight of its own just as well, which orders it within `features`.

`tabs()` rules:

- A tab is a full panel class - `actions()`, `nav()`, `perm()`, `panes()`,
  `assets()` - whose uri is unique across panels and tabs alike. It is never
  a rail entry: its `nav()` group only says where its permission is listed,
  its weight orders the strip. The shell selects it through its address `#<panel>/<tab>`
  (the tab is its uri without the name of the panel in front), shows
  `admin-tab-<uri>` and calls `Nino.admin.<uri>.showCurrent()` exactly like a
  panel; `Nino.admin.router.exists()` knows both kinds.
- The strip is rendered only when the account holds more than one screen of
  the pane, the panel's own first. An account holding a tab's permission but
  not the panel's still gets the pane, on that tab alone
  (`Admin::visiblePanels()`, `'own' => false`). A tab has no tabs of its own.
- A tab may also answer `scope()` beside `perm()`: the area a feature describes
  its rights in, which the `perm()` of the tab and the `perm()` of its panel (the door) both lie in.
  The tab is then shown, and its actions run, for an account that holds its `perm()` and for one that
  holds the door and was described in no detail in the area, so an older account keeps the tab; one
  without the door and the right gets nothing. The actions follow the same rule only while every
  screen naming that `perm()` is a tab of the same panel with the same scope.
  The other way round, a string that a tab with a `scope()` names as its `perm()` gets the rule of that tab
  wherever `guardPerm()` checks it, the door without a description included: check a fine right behind
  the door of its own, or with `Admin::scoped()`, and not with `guardPerm()` of a string a tab names.
- A feature's panel can get a tab it does not name. When an active feature
  declares settings and has a panel, `Panels::collect()` adds one entry to that
  panel - uri `<panel>-settings`, class `\Nino\Modules\Features\Settings`, the
  `/_admin/features/manage` permission, the Features panel's own words and a
  mount `feature-settings-<key>` whose form `Features/assets/settings.js`
  draws - and no action of its own: the form is `features/list` and
  `features/settings`. Its `label` and `group` are the Features panel's, so the
  Roles list offers that permission where it did. The entry is built by hand
  because one class serves every feature, which is also why the registry
  checks its uri against the taken ones itself; it is left out when the Features
  module's `Settings/` directory is not there. Once the tab is attached, the
  panel's own mounts move from direct children of `#admin-content-<uri>` into `#admin-tab-<uri>`;
  CSS or JS keyed on the first structure breaks the day the feature declares
  settings.
- The workbench's own modules use it: Element Types under Elements, Text Keys under Text,
  Image Slots under Images (each a structure permission beside the content it
  shapes), User roles (sharing `/_admin/users/manage`), Login protection
  (`/_admin/lockout/manage`) and Recovery password (`/_admin/recoverypw/manage`)
  under Users, Translations under Language. The
  strip is the design system's `.nino-admin-tabs--bar`, the same the Features
  panel draws over a feature's own screen (Description and Settings); the registry adds
  `.nino-admin-tabs--panel` to a pane's own strip, which is what keeps the
  generic tab rules off it.

The head: every pane but the Dashboard's opens with one row, rendered from
`Panels::$html` like the rest of the shell - the panel's label as the `<h2>`
of the screen (`.admin-panel-title`, the same label the rail shows, through
the same escape), the tab strip beside it where the registry has tabs, and
an empty `.admin-panel-actions` slot at its end, and after it the `?` of the panel's manual where it has one (`.admin-panel-manual`, see [The manual](#the-manual)). It is the workbench's bar
surface, edge to edge, so the screens read as one tool: the name says where
you are, the strip what else is here. A panel does not draw a title of its
own, then. A panel whose tabs are its own rather than the registry's - the
Features panel's Active/Inactive/Available, a feature switching between two
editors - puts its strip beside the name through
`Nino.adminUi.panelHead( mount ).tabs( strip )`, which replaces the strip
before it so a screen drawn again does not stack two. Where the head already
holds the registry's strip - the mount is inside one of a pane's tabs - `tabs()`
leaves that strip alone and puts the screen's own at the top of the tab's pane,
replacing the one it drew there before (a feature's screen that draws its own
strip keeps the Settings tab the registry gave its panel). A panel that keeps
buttons over its screen appends them to `.actions`. `panelHead()` answers
`null` where there is no head, so a script rendered somewhere other than its
pane keeps its strip where it drew it. `head()` returning `false` drops the
row; the Dashboard does that, because its screen has no single subject.

A word on a tab - how many of something wait in it, what state it is in - is
`Nino.adminUi.tabBadge( tab, text, state )`: one chip, the workbench's
`.nino-admin-badge` pill, after the label on the registry's button for that
tab (`#admin-tabbutton-<uri>`). `state` is `ok`, `warn`, `error` or `muted`,
the `data-state` the other badges take; without one the chip is the colour of
the label. A second call replaces the chip, an empty text or `null` takes it
away, and `tabBadge( tab )` answers the word it was given, `''` for none. The
shell draws its strip once and never again - a move between panels and tabs
changes the classes of the buttons and not the buttons - so the chip stays
until the panel changes it; the panel calls it where the number changes, and
a tab the account has no button for draws nothing. A chip of a panel's own on
the same button is left alone.

### The manual

A pane with a manual ends its head with a `?` button (`Panels::$html['head-manual']`, a fragment like the rest of the head), and where there is no head - a work surface - draws it in a row of its own (`head-bare`, `.admin-panel-manualrow`: not a head, so `panelHead()` answers `null` for that pane as before), which is why that panel draws its screen into a mount of its own and not into the pane. The button names its pane in `data-manual`; one listener of the shell (`Nino.admin.manual`) answers every one of them, asks `admin/manual` for the manual and shows it with `Nino.adminUi.manual()` in `Nino.adminUi.manualDialog()` - a panel script does nothing for it. Which panes have one is in the registry (`manual`, the handles, and `manualFeature`, the key of the feature whose manifest manual a feature's panel shows); `Panels::hasManual()` says it, and `admin/manual` answers only for a pane the account may see.

A panel writes its manual as text, one fill a handle, and names the handles in `manual()`:

```php
public static function manual(): array {
	return [ 'intro', 'type', 'element', 'delete' ];
}
```

```php
'[[/_admin/catalog/manual/intro]]'	=> 'The products of the shop, one list for every kind of product.',
'[[/_admin/catalog/manual/type]]'	=> 'Open a type to see its products. ...',
```

Short is the rule: the first entry says what the panel is for, one entry follows for each thing a person does there, at most eight in all, each a sentence or two; German addresses the person as *Du*, capitalised, and English is plain. Every segment after the panel's own name is a word of the vocabulary the Text panel names keys with (`/_admin/common/word/<slug>`, one word to a slug), the handle among them, and a word that is missing is added there once, in both languages - the manual shows the handle by that word in the interface language, so `type` reads "Typ" over its line. The handle `intro` has no word shown: it is the line about the whole panel. Nothing in a line is markup - it is set as text - so no `<`, `>` or `&`. `tests/admin-text-smoke.php` holds every panel of the tool to this.


A dispatched action announces itself on `/nino/admin/action`
(`{ action, panel, status, user, data }`) once it has answered - the one place a
module reacts to what the workbench does without owning the panel the action
belongs to. Notification only: the response is already written, so refusing
stays `guardPerm()`'s job, and *what changed* is the kernel's own events
(`/nino/elements/committed`, `/nino/auth/user/*`). `data` is the posted
payload with its top-level `pw`, `current` and `currentPassword` blanked. A
secret posted under any other key is not: a feature's `secret` setting arrives
in `features/settings` as `data.fields.<name>`, in plain text, so a listener
treats `data` as sensitive. The activity log is not a
listener on it but a direct call, deliberately - an audit line that can be lost
by not registering a callback is not an audit line.

Actions are the panel's slug plus a verb. `handlePost()` dispatches through
the registry and checks two things before it calls an action: the session
token (`403`, code `csrf`) and that an account is signed in (`401`, code
`session`, the answer `guard()` gives). A page of the site hands a token to
every visitor, so the token proves nothing about who sends the request, and no
action is reachable without an account; the kernel keeps no list of open
actions and a feature cannot add one. The kernel does not check the
permission: every action method guards itself with
`\Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM )` as
its first line, and that is what answers `403` without the permission. An
action that leaves the line out is open to every signed-in account, an
editor's included.
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
own root (see [Designing an admin frontend](#designing-an-admin-frontend)).

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
refuses a malformed one by name. What an account may do is `\Nino\Auth::permissions()`: its own
`perms` plus its role's (`/nino/auth/roles`), and every guard reads that.

A panel's own permission is a door. What may be done once inside it can be
described further with a **scoped permission** - one string per action, and per
field or key where that is the unit:
`/_admin/elements/services/update/title`, `/_admin/text/update/template/page-home/*`.
They are ordinary permission strings, so `\Nino\Auth::checkPermission()`'s
`/*` ancestor rule is what makes a whole type or group grantable at once, and
`\Nino\Admin\Admin::scoped( $appData, $prefix, $perm )` is what a panel calls:
it answers true unmodified for an account holding none of them under `$prefix`
(`isScoped()`), so a panel permission keeps meaning what it always meant, and
checks the specific string for an account that holds one. A panel that grows
scoped permissions sends what the current account may do along with its data
(see `Elements\Admin::rights()`, `Text\Admin::apiKeys()`) so its form can draw
itself accordingly - the enforcement stays in the action methods.

Handing out is bounded by holding (`Roles::notHeld()`): a role may not be given a
permission its author lacks, and neither an account's role nor an account's
password may be set beyond the account setting it. Scoping is opt-in, and so a
smaller set is not always less: a panel permission with no scoped one beside it
allows the whole panel. So there is one more condition for every panel
`scopeOptions()` lists, with the prefix `isScoped()` reads (a panel that grows
`scopes()` is covered with no change here): a set of permissions that opens a
panel and is not described in detail for it - `isDescribed()` is `isScoped()`
asked of a list - may not be written, handed out or set as a password by an
account that does not hold the whole panel - a blanket of it, or the door with no
scoped entry. A role that is wide may still be edited by such an account as long
as nothing widens. The panels are asked of every `scopes()`, including one that
offers no area at the moment (`scopeOptions( $appData, true )`). The refusal is a `403` with the codes `roles_grant_too_wide_scoped`,
`users_role_too_wide_scoped` and `users_password_too_wide_scoped`, which name the panel's scope.

Changing is bounded by the same holding. `\Nino\Modules\Users\Admin::beyond()` asks
`Roles::notHeld()` of what an account other than the signed-in one holds, role and own permissions together,
and `Roles::beyond()` of what a role holds: an account or a role that holds what the
signed-in account does not is not its to rename, deactivate, delete, log out, give another role, lift the lock
of, empty or change. It is the last check an action makes before it writes, so every other answer an action gave
stays, and the refusal is a `403` with the codes `users_account_beyond`, `users_account_beyond_scoped`,
`roles_role_beyond` and `roles_role_beyond_scoped` (`Roles::refuseBeyond()`); a role that an account beyond the signed-in one holds is refused the same way, with `roles_role_held_beyond` and `roles_role_held_beyond_scoped` (`Roles::refuseChange()`). A panel that adds an
action changing an account or a role asks the same function. `users/list`, `roles/list` and the locked accounts of `lockout/list`
carry `canChange` per row, which the forms read to leave out the fields and buttons the request would be refused for.

The roles tab does not ask anybody to type a scoped permission: a panel that has
them lists them in an optional `scopes( &$appData )`, and the tab offers them as
three lists that depend on each other - area, action, field - from what
`\Nino\Modules\Users\Admin::scopeOptions()` collects from the registry's classes.
The answer is an array of scopes, one per panel (or per part of a panel):

```php
public static function scopes( array &$appData ): array {
	return [ [
		'scope'	=> self::SCOPE,									// the prefix isScoped() reads
		'door'	=> self::MANAGE_PERM,						// the panel's own permission
		'label'	=> '/_admin/nav/catalog',				// a fill key or literal text
		'areas'	=> [ [
			'id'			=> 'books',
			'label'		=> 'Books',								// literal text, shown as it is
			'perm'		=> self::SCOPE. 'books/*',	// optional: "everything in this area"
			'actions'	=> [
				[ 'id' => 'insert', 'label' => '/_admin/catalog/scope/insert', 'perm' => self::SCOPE. 'books/insert' ],
				[ 'id' => 'update', 'label' => '/_admin/catalog/scope/update', 'perm' => self::SCOPE. 'books/update/*',
					'fields' => [ [ 'id' => 'title', 'label' => 'title', 'perm' => self::SCOPE. 'books/update/title' ] ] ],
			],
		] ],
	] ];
}
```

`scopeOptions()` keeps only what is shaped like a permission (`Roles::isPermShape()`,
the shape `Roles::apiSave()` checks) and lies below its `scope`; an action without
a valid `perm`, an area without an action and a scope without an area are dropped.
A field whose name cannot be part of a permission - a space, an umlaut - has to be
left out by the panel, and is covered by the area's `perm`. A panel without
`scopes()` offers none: a role can still hold a scoped permission of it - one
written by hand, or one a role held before - which the picker then shows under
*Not offered*, but the form cannot add a new one.

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
			return $action === 'catalog/save' ? 'Saved catalog note "'. \Nino\Admin\Admin::postString( $data, 'id' ). '"' : '';
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
			$id    = strtolower( trim( \Nino\Admin\Admin::postString( $data, 'id' ) ) );
			$title = trim( \Nino\Admin\Admin::postString( $data, 'title' ) );
			$body  = trim( \Nino\Admin\Admin::postString( $data, 'body' ) );

			if( preg_match( '/^[a-z][a-z0-9-]*$/', $id ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'invalid note id', 'catalog_invalid_id', [], 'id' );
				return;
			}

			if( $title === '' || strlen( $title ) > self::MAX_TITLE_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'invalid note title', 'catalog_invalid_title', [ self::MAX_TITLE_LENGTH ], 'title' );
				return;
			}

			if( strlen( $body ) > self::MAX_BODY_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'note body too long', 'catalog_body_too_long', [ self::MAX_BODY_LENGTH ], 'body' );
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
	'[[/_admin/catalog/empty]]'       => 'No catalog notes yet.',
	'[[/_admin/catalog/label/new]]'   => 'New note',
	'[[/_admin/catalog/label/edit]]'  => 'Edit note',
	'[[/_admin/catalog/label/back]]'  => 'Back to catalog',
	'[[/_admin/catalog/label/id]]'    => 'ID',
	'[[/_admin/catalog/label/title]]' => 'Title',
	'[[/_admin/catalog/label/body]]'  => 'Body',
	'[[/_admin/catalog/label/save]]'  => 'Save',
	'[[/_admin/catalog/error/load]]'  => 'Could not load the catalog.',
	'[[/_admin/catalog/error/save]]'  => 'Could not save the entry.',
	'[[/_admin/error/catalog_invalid_id]]'    => 'The id may use lower-case letters, digits and hyphens, and starts with a letter.',
	'[[/_admin/error/catalog_invalid_title]]' => 'The title is required and may have up to %s characters.',
	'[[/_admin/error/catalog_body_too_long]]' => 'The text may have up to %s characters.',
];
```

The tile label and the words from `empty` to `label/save` are what the
frontend skeleton below draws; the two `error/` keys are what it asks for when
a request fails (see "Saying what happened"): the panel's own sentences for a
failed load and save. The last three are the texts of the codes the backend
skeleton above answers with.

A workbench panel has the identical body; it lives in
`_admin/Nino/Modules/<Name>/Admin/Admin.php` (namespace `Nino\Modules\<Name>`,
so `\Nino\Admin\Admin::guardPerm()` is spelled out there too) and needs no
registration at all - `Admin::modules()` reads the directory. The runtime half
of the same module name stays where it belongs: `\Nino\Modules\Elements` is
the kernel's module in `_nino/`, `\Nino\Modules\Elements\Admin` the screen
for it in `_admin/`.

This skeleton demonstrates the required invariants:

- Every API method calls the guard itself, with the panel's permission.
- Payload comes from `Admin::postData()`, which decodes the JSON `data` field, and
  every value is read with `Admin::postString()`, never cast: the payload is
  untrusted json, and the cast of an array raises "Array to string conversion",
  which the runtime treats as fatal - a 500 for a payload one line long.
- IDs, lengths, and required fields are validated server-side.
- The file update is atomic.
- The response uses `Http::ok()` or `Http::fail()`; a failure a person can
  cause carries a code, its params and the field (see "Saying what happened").
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
The Builder feature (`features/Builder` once copied in,
`layout()` = `'workspace'`) is the reference:

```php
public static function template(): string {
	return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/templates/panel' );
}

public static function layout(): string {
	return 'workspace';
}

public static function icon(): string {
	return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>';
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
calls `Nino.admin.<uri>.showCurrent()` every time a tab is selected, if such a
function exists. That is where a panel that does not start with the page loads
its data the first time - `Nino.adminUi.panel()` starts lazily unless it is told
`boot: 'ready'` - so a panel nobody opens costs no request; a panel that binds
its `init()` to the ready event loads with the page, and its `showCurrent()`
shows it again. Inside the pane the panel owns its mount points (`panes()`).

That lifecycle is `Nino.adminUi.panel( spec )`, written once. The script
publishes what it answers - `Nino.admin.catalog = Nino.adminUi.panel( { ... } )` -
and the shell calls `showCurrent()` on it. The spec:

- `uri`, `mount` and `render( data, panel )` are required: the pane's uri (the
  endpoints are `<uri>/<action>`, and the dirty registry knows the panel by it),
  the id of the element the panel draws into - a page without it is never asked
  for anything - and the function that draws the screen from the body of an
  answer.
- `action` is the first endpoint (`'list'`) and `payload` what goes with it, an
  object or a function answering one (`{}`).
- `show` is what showing the panel again does, in one of three policies.
  `'once'`, the default, asks while the panel is not loaded and does nothing
  afterwards. `'always'` asks on every showing. `'keep'` asks while the panel is
  not loaded and afterwards draws the last answer again, from `panel.data` and
  without a request - and not at all while the dirty registry says the panel
  holds input nobody has saved.
- `stamps` is a list of kinds of data (the next section) the panel shows and
  another panel writes: the stamp of each is kept as it was when the screen was
  asked for, and with `show: 'keep'` a showing asks again instead of drawing the
  last answer when one of them moved - not while the panel holds input nobody
  has saved, which is asked again at the next showing. `'once'` and `'always'`
  have their own rule for a second showing, and `stamps` changes neither.
- `boot` is `'lazy'` (the default: the first showing starts the panel) or
  `'ready'`, which starts it at the ready event too. A request that is out is
  asked for once, so a `'ready'` panel that the shell shows first - as it does on
  a page opened at the panel's hash - no longer asks twice.
- `dirty` is `{ isDirty, save, discard, bar, shortcut }`, registered under the uri with
  `Nino.admin.dirty` at the first load where the shell has the registry (see
  [Unsaved input](#unsaved-input)).
- `error` is the fill key of a failed load, `/_admin/<uri>/error/load` unless
  it is given, and `onError( status, response, panel )` is called once the
  failure is shown.

What it answers is a plain object: `uri`, `ready` (false until a load
succeeded), `loading` (true while a request is out) and `data` (the body of the
last answer); `mount()`, the element or `null`; `call( action, payload, done,
extra )`, which is `Nino.adminUi.api.call()` for `<uri>/<action>`, so a panel
still never posts by hand; `fail( container, status, response, key )`, which
says a failure in a container the way a failed load is said, with the panel's
key unless it is given another; `init( then )` and `reload( then )`, which ask
for the screen, draw it and call `then()`; and `showCurrent()`. The script may
add members to it and replace `showCurrent()` with its own - a drill-down panel,
which follows the hash into a level (below), keeps its own, since `panel()` reads
nothing of the hash. It names no `Nino.admin` member when it is called: the
dirty registry is looked up when it is needed, so the same panel runs on a page
that has none, as the setup wizard is.

The levels of the pane - `catalog-list`, `catalog-form` - are shown one at a
time, and the focus goes with them. `Nino.adminUi.showLevel( 'catalog-form' )`
shows the level and hides its siblings - the other children of its parent whose
ids begin alike - with the shell's `admin-hidden` class, and puts the focus into
it: onto the element `{ focus : element }` names, else the first heading of the
level, else its back link, else the name of the panel. A level the person did
not just open - one the address names, one drawn when its answer arrives - is
shown with `{ focus : false }`: the focus stays where it is, unless it stood in
a level that is hidden now, and then it goes to the name of the panel, where
the person started. `Nino.adminUi.backTo( 'catalog-list', opener )` shows the
level a drill-down came from and gives the focus to `opener`, the row or the
button that opened the level that is left, where that is still in the page. A
list that is drawn again in the meantime has a new row for it, which the panel
finds again - by a `data-*` of the row - and passes; so does a picker or a list
that is drawn again behind a focus that stood in it, which hands the focus to
the control of the same record. Without the two a drill-down hides the element
that has the focus, the focus falls to the `body`, and the next Tab key starts
again at the top of the page.

A row of a list that opens a level and writes no address is a `button` of the
class `nino-admin-list-link` in its `li` - the look of the link rows is done in
the stylesheet. A row that does write one - the form of a user, of a text key -
is a link whose `href` is `Nino.admin.router.href( panel, parts )`, with the click
handler going through the router as before. An `href="#"` that only stands for
a button is neither. `Nino.adminUi.levels( 'catalog', [ 'list', 'form' ] ).show( 'form' )`
only shows and hides, and `show( 'form', [ id ] )` writes the level into the
address through the router as well. What goes with a level - a redraw, a
refresh, a dropped working copy - stays with the panel. The url hash is the
panel's to deep-link into: `Nino.admin.router.set( 'catalog', [ id ] )` while
the panel is on screen, `Nino.admin.router.current()` to read it back on load.
What the router writes for it is `#catalog/catalog/<id>`: the one form of
[Addresses](../admin-ui.md#addresses), `#<panel>/<tab>/<part>`, where the own
screen of a panel is its first tab named like the panel and a tab is
named by its uri without the panel's name in front. A panel names a screen by its
uri, as it always has (`'catalog'`, `'catalog-frames'`), and passes the parts: a
word the panel says as `Nino.admin.router.word( 'new' )`, and a key - any string -
as ONE part however many slashes it holds, never `key.split('/')`, never `'#'+ ...`.
A key is always encoded, an at sign in it too: the element called `@new` is
`%40new`, and `@new` is the form that makes one. `current()` answers
`{ panel, parts, known }`: the screen the address names, its parts, and whether the
address was one the router could read whole; a part is read with
`Nino.admin.router.isWord( parts, at, 'new' )` and never compared with `'@new'`,
which a key can be. A part that is no part ends the parts there; a part the panel
does not know - a thing that is gone, a word it does not have - is its list, and
the address is written over by the one the screen has.
A panel on the server's side asks `\Nino\AdminAddress::of( 'catalog', [ $id ] )`.

The address is also what the browser's Back and Forward walk, so a panel that
deep-links has three more things to do. `set()` replaces the entry the browser
is on: it keeps the address true to the screen, which is what `showCurrent()`
and a save are for (a new element's address, once it has one). A move the
person makes inside the panel - a row opened, a back link, a "new" button -
calls `Nino.admin.router.go( 'catalog', [ id ] )` before the level draws itself:
that adds an entry, does nothing where the address already says it, and the
`set()` the level then makes finds it true. The shell does the same for the
rail, the tabs and the phone's select (`selectTab( panel, tab, true )`); the
page load, the arrow keys of a tab strip and a step through the history only
replace. The address of the panel itself, `#catalog`, is its own screen: for a
pane with tabs the shell opens the first tab, whichever was open last, and
`#catalog/<tab>` opens that tab - only the rail, and the phone's select, come
back to the tab last open. And `showCurrent()` follows the hash: when `router.current().panel`
names this panel, the level its parts name is shown - a step through the
history changes the address and nothing else - and a hash that names another
panel (a click on the rail) keeps the level in memory, as before. Leaving an
open form that way runs inside `Nino.admin.router.leave( [ 'catalog' ], formIsOpen,
proceed, resync )`, which asks Save / Discard / Cancel like the back link does;
`resync` is what a Cancel does, showing the level in memory again and so writing
it back into the address. The minimal example below keeps no hash, so it needs
none of it. It is a minimal list/edit lifecycle on `panel()`; adapt field names,
but preserve the API, DOM safety, and lifecycle shape:

```js
( function(wn,dc) {

	wn.Nino.admin = wn.Nino.admin || {};

	// Asks for the list when the panel is first shown, draws the answer and
	// says a failed load in the mount: the lifecycle is panel()'s
	Nino.admin.catalog = Nino.adminUi.panel( {
		uri			: 'catalog',
		mount		: 'catalog-list',
		render	: function( data ) {
			Nino.admin.catalog._items = data.notes || [];
			Nino.admin.catalog._renderList();
			// Drawn when its answer arrives, not opened by the person: the focus stays
			Nino.adminUi.showLevel( 'catalog-list', { focus : false } );
		},
	} );

	Object.assign( Nino.admin.catalog, {

		_items : [],

		// The row of a note as the list is drawn now - the list is drawn again
		// after a save, and the row that was pressed is gone with the old drawing
		_rowOf : function( id ) {
			return Array.from( dc.getElementById('catalog-list').querySelectorAll('button') ).find( function( row ) { return row.dataset.id === id } ) ?? null;
		},

		_renderList : function() {

			const wrap = dc.getElementById('catalog-list');
			wrap.innerHTML = '';

			// No heading of its own: the pane's head names the panel (see
			// "The head" above), and a title drawn here would say it twice.
			// The form a level below opens with one, under its context bar

			const list = dc.createElement('ul');
			list.className = 'nino-admin-list';

			if( Nino.admin.catalog._items.length === 0 ) {
				const empty = dc.createElement('p');
				empty.textContent = Nino.content.getText('/_admin/catalog/empty');
				wrap.appendChild( empty );
			}

			Nino.admin.catalog._items.forEach( function( item ) {
				const li = dc.createElement('li');
				// Opening a note writes no address: a button, which looks like a link row
				const row = dc.createElement('button');
				const title = dc.createElement('strong');
				const meta = dc.createElement('small');

				row.type = 'button';
				row.className = 'nino-admin-list-link';
				row.dataset.id = item.id;
				title.textContent = item.title;
				meta.textContent = item.id;

				row.appendChild( title );
				row.appendChild( meta );
				row.addEventListener( 'click', function() {
					Nino.admin.catalog._renderForm( item );
				} );

				li.appendChild( row );
				list.appendChild( li );
			} );

			wrap.appendChild( list );

			// "New <thing>" is the one main button of the bar of a list
			const add = Nino.adminUi.button( Nino.content.getText('/_admin/catalog/label/new'), function() {
				Nino.admin.catalog._renderForm( null, add );
			} );
			wrap.appendChild( Nino.adminUi.actionBar( { list : true, primary : add } ).el );
		},

		// opener: the button that opened the blank form, which Back returns to
		_renderForm : function( item, opener ) {

			const wrap = dc.getElementById('catalog-form');
			wrap.innerHTML = '';

			const back = dc.createElement('a');
			back.href = '#';
			back.className = 'nino-admin-back-link';
			back.textContent = Nino.content.getText('/_admin/catalog/label/back');
			back.addEventListener( 'click', function( event ) {
				event.preventDefault();
				// To the row that was opened, as the list is drawn now - for a blank
				// form to the button that opened it
				Nino.adminUi.backTo( 'catalog-list', item === null ? opener : Nino.admin.catalog._rowOf( item.id ) );
			} );
			wrap.appendChild( Nino.admin.formToolbar( back ) );

			const form = dc.createElement('form');
			form.id = 'catalog-edit';
			const heading = Nino.adminUi.el( 'h2', '', Nino.content.getText( item ? '/_admin/catalog/label/edit' : '/_admin/catalog/label/new' ) );

			const idField = Nino.adminUi.textField( { label : Nino.content.getText('/_admin/catalog/label/id'), id : 'catalog-id', value : item ? item.id : '', required : true } );
			const id = idField.querySelector('input');
			id.name = 'id';
			id.pattern = '[a-z][a-z0-9\\-]*';
			id.disabled = item !== null;

			const titleField = Nino.adminUi.textField( { label : Nino.content.getText('/_admin/catalog/label/title'), id : 'catalog-title', value : item ? item.title : '', required : true, maxLength : 160 } );
			const title = titleField.querySelector('input');
			title.name = 'title';

			const bodyLabel = dc.createElement('label');
			const body = dc.createElement('textarea');
			bodyLabel.textContent = Nino.content.getText('/_admin/catalog/label/body');
			bodyLabel.htmlFor = 'catalog-body';
			body.id = 'catalog-body';
			body.name = 'body';
			body.maxLength = 5000;
			body.value = item ? item.body : '';

			const message = Nino.adminUi.el( 'p', 'admin-form-message' );

			const save = Nino.adminUi.button( Nino.content.getText('/_admin/catalog/label/save'), null, { type : 'submit', className : 'nino-admin-btn-primary' } );
			const actions = Nino.adminUi.actionBar( Nino.adminUi.el( 'div' ) );
			actions.appendChild( save );

			form.appendChild( heading );
			form.appendChild( idField );
			form.appendChild( titleField );
			form.appendChild( bodyLabel );
			form.appendChild( body );
			form.appendChild( message );
			form.appendChild( actions );

			// "Saving", "Unsaved changes" - or why it failed,
			// with the field the server named marked and focused (see
			// "Saying what happened")
			const line = Nino.adminUi.status( message );
			line.bind( form );

			// What the submit does, and what the shell's Save does (see "Unsaved
			// input"): it ends in done( true ) or done( false ) on every way it can
			Nino.admin.catalog._save = function( done ) {

				save.disabled = true;
				line.saving();

				// panel.call() is Nino.adminUi.api.call() for catalog/save
				Nino.admin.catalog.call( 'save', {
					id : id.value,
					title : title.value,
					body : body.value,
				}, function( status, response ) {
					save.disabled = false;

					if( status !== 200 ) {
						line.error( status, response, '/_admin/catalog/error/save' );
						done( false );
						return;
					}

					line.saved();

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
					Nino.adminUi.backTo( 'catalog-list', Nino.admin.catalog._rowOf( response.note.id ) );
					done( true );
				} );
			};

			form.addEventListener( 'submit', function( event ) {
				event.preventDefault();
				Nino.admin.catalog._save( function() {} );
			} );

			wrap.appendChild( form );
			// Opened by the person: the focus goes in, to the first field that takes it -
			// the id of a new note, else the title (the id of a note is fixed)
			Nino.adminUi.showLevel( 'catalog-form', { focus : item === null ? id : title } );
		},
	} );

} )(window,document);
```

The rest of the screen is built from `Nino.adminUi`'s small builders, which own no
strings - every word is the caller's, and a caller that has a fill key runs it
through `text()`. `hint( text, kind )` is a line of explanation (`'error'` for the
error colour); `textField( options )` the labelled line of text beside
`selectField()`, `switchField()`, `numberField()` and `textareaField()` - the label with the star
and the `aria-required` of a required field, its `input` the control;
`button( text, onClick, options )` a real button, whose `name` is the title and
the `aria-label` of a glyph (its kind, size and icon are in
[Buttons and icons](#buttons-and-icons)); `el( tag, className, text )` an element with a class
and a text; `localeSelect( options )` the switch of the translation a form
shows, for the context bar - what a change does is the panel's (it calls
`Nino.admin.workLocale.set()` and draws when the language moves) -; `localeName( code, among )`
the name of a language, and `localeBadge( locale, bar )` the pill of the context bar that names
the content language where it is not the native one. Their docblocks in
`_admin/assets/Nino.admin.js` are the reference.

### The fields of a form: ticks, languages, files, code and moments

Beside `textField()`, `selectField()`, `switchField()`, `numberField()` and `textareaField()` the form blocks have six
more, each with the help behind the (?) and the note under the control that every field has
([Help, notes and textfills at a field](#help-notes-and-textfills-at-a-field)), each with what is wrong said at the field
(`fieldError()`). The ones that are one control answer the element - the label, with the control as its `fieldControl` where it
is no `input` (`control` is a label's own, built in and read only: no block writes to it or reads it) - and the ones that are a group answer a handle, `{ el, value(), set(), error( text ), clear(), focus() }`,
which a form reads its value from.

`Nino.adminUi.checkField( { key, label, checked, required, help, note, onChange } )` is one tick with its name, the label at
the left of the box as at every field; a yes or no that is a setting is `switchField()`.
`Nino.adminUi.checklist( { key, label, options : [ { value, label, state } ], value, help, onChange } )` is several of a set:
a `fieldset` with a legend for its name, one row for each option, a quiet `state` at the end of a row, and a value that is
the list of the ticked ones in the order of the options (`value()`, `set( list )`).

`Nino.adminUi.localeRows( { key, label, locales, values, multiline, onChange } )` is the value of each language under the
other - the badge of the language at the left (`localeName()`), the field at the right, the working language of the workbench
(`Nino.admin.workLocale.current`, or `work`) first and marked. It is for the short values that are required in every language
and for nothing else: whatever is longer is edited in the working language. `value()` answers the map `code => text`,
`value( code )` one text, `set( map )` or `set( code, text )` writes, and `error( code, text )` says what is wrong at that row
and at no other.

```js
const title = Nino.adminUi.localeRows( {
	key : 'title', label : t('/_admin/catalog/label/title'), required : true,
	locales : locales, values : item.title, help : t('/_admin/catalog/hint/title'),
	onChange : function( code, text ) { draft.title[code] = text },
} );
form.appendChild( title.el );
// when saving
const values = title.value();
Object.keys( values ).forEach( function( code ) {
	if( values[code].trim() === '' )
		title.error( code, Nino.adminUi.format( t('/_admin/common/error/missing'), Nino.adminUi.localeName( code ) ) );
} );
```

`Nino.adminUi.fileField( { key, label, accept, maxBytes, note, button, onChange } )` is the choice of a file as the guide has
it: a button of the second kind, the name and the size of the chosen file beside it, "Remove", the limits as the `note`. A
file that is larger than `maxBytes`, or of a type `accept` does not name, is not taken and is said at the field - the types it takes by their names ("PDF or picture"), not as the picker's notation. Enter on the button asks for the picker when the key is let go, as Space does (a picker asked for while the key goes down is refused in a dialog); the release of the Enter that opened the dialog asks for nothing. It uploads
nothing: `onChange( file )` hands the `File` over (`null` once it was removed), `value()` answers it, and the view sends it
the way its model of saving says - at once, with the form, through `Nino.adminUi.api.call()` - and reports it to
`Nino.admin.dirty`, which does not follow a file by itself.

`Nino.adminUi.codeField( { key, label, value, rows, fills, ... } )` is a `textareaField()` for technical values: the face of
code, a scroll bar of its own, no spell checking, completing or wrapping, and the Tab key left to the browser. There is no
editor and no highlighting.

`Nino.adminUi.dateTime( { key, label, value, min, max, zone, required, onChange } )` is a date and a time as two native fields
with one value, an ISO string of the clock of the site without a zone - `'2026-11-17T06:02'`; nothing is converted to or from
the browser's zone, and `zone` names the site's under the field. `value()` answers `''` for no moment, a date alone is
midnight and a time without a date is no value; `valid()` answers `''`, `'incomplete'`, `'min'` or `'max'`, which the
caller words (`error( text )`).

`formDialog()` draws every one of them: a field of its `fields` is `type : 'check'`, `'checklist'` (`options`), `'locales'`
(`locales`, `multiline`), `'file'` (`accept`, `maxBytes`), `'code'` or `'datetime'`; a field that takes text (`'code'` and `'locales'` too) may carry a `placeholder`, an example of what is typed ("#ffffff") that is never the name of the field. `onSubmit( values )` gets a boolean,
a list, a map of the languages, a `File` or `null`, a text and an ISO string for them. A required field is empty where a tick
is not ticked, a list has none, a language has no text or there is no file.

### Help, notes and textfills at a field

The fields of a form say what a value does behind the (?) at their name, never in a line
under them. Every field block takes the sentence as its option `help` (`hint` draws the
same thing), and what has to stay in view - a counter, a limit, a conversion, the result
of a check, a warning with a consequence - as `note`, a quiet line after the control. A format ("one per line") is the
`placeholder` of the empty field, as an example, and is in the help as well:

```js
const pause = Nino.adminUi.numberField( {
	key  : 'pause', value : 120, unit : 'seconds',
	label : t('/_admin/hello/label/pause'),
	help  : t('/_admin/hello/hint/pause'),           // behind the (?)
	note  : t('/_admin/hello/note/pause'),           // a warning with a consequence: stays under the input
} );
```

The explanation of a whole section is the same (?) at its title: `sectionHelp( legend, fieldset, text )` takes the words of
the title into a span, names the section by them (`aria-labelledby`, a `role="group"` where it is no fieldset) and describes it by the sentence.

The (?) is `fieldHelp( text, { field, name } )`, a button of 24 px named "Help for
<name>" whose `aria-expanded` follows the bubble; the sentence is always in the document and
the control is described by it (`aria-describedby`), and named by its label alone
(`aria-labelledby`). Escape and a press beside it close the bubble, at most one is open, and
it stays inside the window or the dialog, never lies on the field it is about and never goes below the bar. The label points at
the field (`for`), so a press on the name focuses the field. A field that is built by hand names the sentence the
same way: `name.appendChild( Nino.adminUi.fieldHelp( text, { field : control, name : label } ) )`,
with the words of the name in a span of their own that the control's `aria-labelledby` names.

A field whose work textfills decide - a multi-line text, HTML+, a technical value - takes the
option `fills` and draws the "[ ]" at its name, for an account that may edit it:

```js
const body = Nino.adminUi.textareaField( {
	key : 'text', label : t('/_admin/hello/label/text'), rows : 8,
	fills : {
		editable : may.update,                        // no icon where the account may not edit the field
		load : function( done ) {                     // asked at the press; items : [ ... ] for a list that is known
			Nino.adminUi.api.call( 'elements/fills', { type : 'legal', field : 'text' }, function( status, response ) {
				done( status === 200 ? response.fills.map( function( fill ) { return { name : fill.key, notation : fill.notation, value : fill.value } } ) : null );   // null: the list could not be had, said at the field
			} );
		},
	},
} );
```

A press opens `fillsDialog( { title, fills } )` - `[ { name, notation, value } ]` with a search - and
a press on a row copies the notation. The list is the panel's action to answer; `\Nino\Text::fills()`
makes it for the two notations a field can read, `textfill` (`[[/key]]`) and `placeholder` (`#/key#`),
and offers no key outside the namespaces of the form. A field of an editor that is no textarea draws
the icon with `Nino.adminUi.fillsButton( fills, control, label )` into the element that holds its name.

## Buttons and icons

Three builders of `Nino.adminUi` make every button of a screen; none of them owns a
word, the text and the name are the caller's.

`icon( name, { size, label } )` is an inline `<svg>` of the Lucide set (ISC; the
notice is in `THIRD-PARTY-NOTICES.md`), drawn in the colour of the text it stands in
and 16 pixels wide unless `size` says otherwise. Without a `label` it is decoration
and is `aria-hidden`; with one it is `role="img"` of that name. A name the set does
not have draws nothing and says so once in the console; it throws nothing.

```js
const trash = Nino.adminUi.icon( 'trash-2', { label : Nino.content.getText('/_admin/catalog/label/delete') } );
```

`button( text, onClick, { kind, small, icon, name, type, className, id, disabled } )`
keeps its call as it was: no `kind` is the look of a plain button. `kind` is
`'primary'`, `'secondary'`, `'danger'` or `'link'` and puts the class of that kind on
it (`.nino-admin-btn-primary`, `-secondary`, `-danger`, `.nino-admin-linkbutton`); any
other value throws a `TypeError`. `small` adds `.nino-admin-btn--small`. `icon` is the
name of an icon drawn before the word, hidden from a screen reader since the word is
the button's name; `name` is the `aria-label` and the title where the name is not the
text.

```js
const add = Nino.adminUi.button( Nino.content.getText('/_admin/catalog/label/new'), onNew, { kind : 'primary', icon : 'plus' } );
```

`iconButton( icon, name, onClick, { danger, small, disabled, id, className } )` is a
button that shows an icon and no word: for the rows and tools of an ordered list, a
reload. The `name` is the `aria-label` and the title and is not optional - a call
without a non-empty text throws a `TypeError`. It is a square target of 28 pixels (24
when `small`) with the focus ring of every button, and `danger` gives it the colour of
`.nino-admin-btn-danger`.

```js
const up = Nino.adminUi.iconButton( 'arrow-up', Nino.content.getText('/_admin/catalog/label/up'), function() { move( index, -1 ) }, { disabled : index === 0 } );
```

The names of the set are `align-center`, `align-center-horizontal`, `align-center-vertical`, `align-end-horizontal`, `align-end-vertical`, `align-horizontal-space-between`, `align-justify`, `align-left`, `align-right`, `align-start-horizontal`, `align-start-vertical`, `arrow-down`, `arrow-left`, `arrow-left-right`, `arrow-right`, `arrow-up`, `brackets`, `calendar`, `calendar-plus`, `check`, `chevron-down`, `chevron-left`, `chevron-right`, `chevron-up`, `chevrons-left`, `chevrons-right`, `circle-help`, `clock`, `code`, `columns-3`, `contact`, `cookie`, `copy`, `database`, `ellipsis`, `external-link`, `eye`, `eye-off`, `gauge`, `hand`, `heading`, `image`, `images`, `list`, `lock`, `mail`, `menu`, `monitor`, `mouse-pointer-click`, `move-horizontal`, `newspaper`, `panel-bottom`, `panel-top`, `panels-top-left`, `pencil`, `play`, `plus`, `rectangle-horizontal`, `redo-2`, `refresh-cw`, `repeat`, `rows-2`, `rss`, `search`, `settings-2`, `share-2`, `sliders-horizontal`, `smartphone`, `square`, `square-split-horizontal`, `stretch-horizontal`, `stretch-vertical`, `sun-moon`, `table`, `tablet`, `text`, `text-cursor-input`, `timer`, `trash-2`, `triangle-alert`, `undo-2` and `x`: Lucide 0.577.0, spelled as Lucide
spells them, in the `ICONS` table of `_admin/assets/Nino.admin.js`; `\Nino\Components::ICONS`
is the same list for the schema of a component, and a test holds the two together. Another icon is
added to both, to the table as a string for a path and `[ tag, attributes ]` for any other
shape, from the same version of Lucide.

## Dialogs

Every dialog of the workbench is `Nino.adminUi.choiceDialog()`, a native `<dialog>`
opened with `showModal()`: its title and its answers stand where they are and what is
between them scrolls, so it is never higher than the screen. The answers are one row
at the right, never the full width, in one order in every dialog, the shell's own
questions among them: the other ways on, then Cancel, and at the far right the one that
goes on (*Discard, Cancel, Save*; *Reload, Cancel, Overwrite*). The focus is never on the
answer that destroys. Its size is a class, `.nino-admin-dialog--narrow` (a
question, 26 rem), `--form` (40 rem), `--wide` (60 rem) or `--large` (72 rem, 90 % of the height at the
most: the dialog of a record, which fills the whole screen at 390 px), each read from a token
(`--nino-admin-dialog-narrow`, `-form`, `-wide`, `-large`); at 390 px the others fill the width less
1 rem a side. A panel builds no `<dialog>` of its own and names no dialog class. The
focus opens on the first field, or on the answer that does no harm where the way on
is dangerous, and goes back to what asked. Four builders sit on it, and none of them
owns a word.

| What | Builder |
|---|---|
| A question before an action | `confirm( level, { title, message, details, yes, no, nested, opener, onChoose, onClose } )` - `nested : true` where it is asked of an open dialog: it stands on it, Esc closes it alone and the focus goes back into the dialog below |
| Make or copy something from a name and at most four more fields (duplicate, save as) | `formDialog()` |
| One entry of a directory with an address of its own, steps to the entries around it and a bar (a message to read, a thing to edit) | `record()` - see [the standard directory and the record](#the-standard-directory-and-the-record) |
| Pick one from a list (a block, a source, a component) | `pickDialog()` |
| The properties of one part of a work surface, which stays in view | `propertiesDialog()` |
| Text to be read | `manualDialog( title, content, opener )` |
| Anything else - a form of a record of its own with more fields, anything with an address of its own | a screen of its own, not a dialog |

`confirm()` takes `details` beside its `message` for a question that is about things: a list of lines of text, and groups
`{ title, lines }` where the lines have a heading each - what a deletion takes, what it keeps. They are drawn as `<ul>` under
the message with `textContent`, never as markup, and the caller cuts a long list short with a line of its own ("and 3
more"). A modal dialog keeps the Tab key in itself: the rest of the page is inert while it is open, and Tab at the last
control goes to the first, Shift+Tab at the first to the last.

`choiceDialog( { dismiss : true } )` lets a press on the darkened surface behind a modal dialog close it as its secondary answer
does: the `guard` is asked and the focus goes back to `opener`. Both ends of the press must lie on the surface - a selection
dragged out of a field and let go beside the dialog does not close it - and the dialog must be the top one of the stack. The
default is `false`: a question is closed by an answer. `formDialog()` and `confirm()` do not pass the option on, so a form is
never closed by a press beside it.

`formDialog( { title, fields, submit, cancel, message, size, onSubmit, onCancel,
onClose, opener } )` draws `fields` - `[ { key, label, type, value, required, maxLength,
min, max, step, unit, validate } ]`, `type : 'select'` with `options : [ { value, label } ]`
(required: an empty value is wrong), `type : 'number'` with `step`, `min`, `max` and a `unit`
([a number](#a-number-and-a-select-that-must-be-chosen)), the types of
[the fields of a form](#the-fields-of-a-form-ticks-languages-files-code-and-moments), any other `type`
that of an `<input>` - or a `body` element of your own. A required field that is empty
and a `validate( value )` that answers a sentence are said at the field with
`fieldError()` and the dialog stays open; so does a send that is on its way, until
`onSubmit( values, ctl )` answers with `ctl.close()`, or says what the server refused with
`ctl.fail( key, text )`. Enter sends; Escape and Cancel close at once where nothing was
changed and ask once whether to discard where something was.

```js
Nino.adminUi.formDialog( {
	title		: Nino.content.getText('/_admin/catalog/dialog/duplicate-title'),
	fields	: [ { key : 'name', label : Nino.content.getText('/_admin/catalog/label/name'), required : true, value : theme.name } ],
	submit	: Nino.content.getText('/_admin/catalog/dialog/duplicate-title'),
	cancel	: Nino.content.getText('/_admin/common/label/cancel'),
	opener	: button,
	onSubmit : function( values, ctl ) {
		Nino.admin.catalog.call( 'duplicate', { name : values.name }, function( status, response ) {
			if( status === 200 )
				ctl.close();
			else
				ctl.fail( 'name', Nino.adminUi.api.errorText( status, response ) );
		} );
	},
} );
```

`pickDialog( { title, items, search, searchFrom, empty, cancel, size, onChoose, onRow, onCancel, onClose, opener } )`
draws `items` - `[ { value, label, description, code, group, image } ]`, `code` a notation in the face of code
before the line - as rows; with a `group` (a text) the items of one group stand under a heading, in the order the groups first appear (`role="group"`, named by the heading, and a search
hides a group that has nothing left); with an `image` (an address) on any item every item is a tile in a grid - a picture of a fixed shape over the name, `alt=""`, loaded lazily,
and a quiet surface of the same size for an item with none - and the arrows walk it in every direction, Home and End to the ends, over the groups; with neither the list is as it was; a press picks one and closes, or, where `onRow( value, row, pick )` is
given, hands the press to it: the caller calls `pick()` when the pick is made. A search over the
names and the lines is drawn from `Nino.adminUi.PICK_SEARCH_FROM` (8) items on, or from
`searchFrom`, and only when `search` names it; the size is `--wide`.

`propertiesDialog( { title, sections, close, onChange, onClose, tools, open, opener } )`
is a sheet at the right over the whole height, for what one part of a work surface has:
`sections` are the sections of an `accordion()` with one open, each
`{ key, label, summary, badge, fields | content }`. A value is the caller's the moment
it changes - `onChange( key, value, section )` is called on the input - so the dialog has
one button, Close, which Escape is also; what was changed is saved with the bar of the
surface. From 38 rem on the sheet is not modal: no backdrop, no trap for the focus, the
surface stays in view and in use, and while the sheet is open the workbench root carries
the class `nino-admin-has-sheet`, which leaves the width of the sheet free on the root's
right (`padding-inline-end: var(--nino-admin-dialog-sheet)`) so that a preview wraps
beside it. A question the surface asks goes over it. Below 38 rem it is modal and the
whole screen. The call answers a handle, `{ close(), set( key, value, [section] ) }`:
`set()` puts a value the surface changed (an undo, a click on another part) into the
fields without calling `onChange`.

```js
Nino.adminUi.propertiesDialog( {
	title		: label,
	close		: Nino.content.getText('/_admin/common/label/close'),
	opener	: button,
	onChange : function( key, value ) { draft.set( key, value ) },
	sections : [ { key : 'content', label : Nino.content.getText('/_admin/catalog/label/content'), fields : [ { key : 'text', label : Nino.content.getText('/_admin/catalog/label/text'), value : draft.text } ] } ],
} );
```

### Where the focus goes back, and what is told after

Every dialog block - `choiceDialog()`, `confirm()`, `formDialog()`, `pickDialog()`, `conflict()`, `propertiesDialog()`,
`manualDialog()` and `record.open( key, { opener } )` - takes an `opener`: what gets the focus when the dialog closes, where it
is not what had it when it opened (a button that was clicked has the focus in Chromium and Firefox, not in Safari). It is

- an element, or anything that has a `focus()` - it is called as it is -, or
- a function that answers one of them and is called when the dialog closes, not when it opens: for a control that is made
  anew while the dialog is open, the row of a list that a save draws again.

```js
Nino.adminUi.formDialog( {
	title : t('rename-title'), fields : fields, submit : t('rename-title'), cancel : t('cancel'),
	opener : function() { return list.querySelector( '[data-key="'+ key+ '"] button' ) },   // the row as it is when the dialog closes
	onSubmit : function( values, ctl ) { save( values, function() { draw(); ctl.close() } ) },
	onClose : function( how ) { if( how === 'submit' ) announce( key ) },                  // after the focus is back
} );
```

What is not in the page when the dialog closes gets no focus, since a browser would drop it on the page: it goes to the name
of the panel the dialog was opened in (`.admin-panel-title`, as after any change of view). `onClose( value )` - `choiceDialog()`, `confirm()`, `formDialog()`, `pickDialog()` and `conflict()` take it - is called once
the dialog is gone and the focus is back, after `onChoose` (a form's `onCancel`), on every way out and once: an answer, Esc,
the cross. `value` is the answer - `submit` or `cancel` for a form
(Esc and the cross are Cancel), `pick` or `cancel` for a list, `reload`, `cancel` or `overwrite` for a conflict, `true` or
`false` for `confirm()` (which also calls it, with `true`, where the policy of the shell asks nothing). The question of a
form that was changed is no way out of the form: `onClose` is called when the form closes. `propertiesDialog()` has its own
`onClose()`, which is told nothing; a record sends the focus to its row itself and has no `onClose`.

**One dialog after another, and over a record.** `formDialog()` takes `nested : true` as `confirm()` does: it opens over a dialog
that is open - a `record()`, another form - stands on it, and Escape closes it alone with the focus back in the dialog below; the
call answers `false` where a dialog is open and `nested` was not said, else a handle `{ el, close() }`, and `close()` closes the
form as Cancel without the question about what was typed. A dialog is closed when `ctl.close()`, `close()` or an answer says so,
not when the browser reports it a moment later: a dialog that the next line - or the `onClose` of the one that closed - asks
for is opened, and the focus goes back to the opener of each in turn.

**Actions of a record and of a directory.** `record( { actions } )` calls `run( row, ctx )` with the button as `ctx.opener`, and
`directory( { select : { actions } } )` calls `run( keys, opener )` with the button of the bar: pass it on to what the action
opens - `confirm( ..., { nested : true, opener : ctx.opener } )` - and the focus is on the button when that closes. After a save
the title, the identifier and the buttons that fit the entry (`hidden( row )` of the actions and of `danger`) are drawn from
the row the save answered. The buttons of a row of a table are the panel's own and get their own `event.currentTarget`.

### A number and a select that must be chosen

`numberField( { key, label, value, min, max, step, unit, required, help, note } )` is a number input in bounds. `step` is
1, a whole number, unless it is given: 0.1, 0.5, `'any'`. `unit` is a word of the workbench, `/_admin/common/unit/<unit>`,
written in brackets behind the label - seconds, minutes, hours, days, weeks, months, items, uris, per-hour, percent,
characters and mm, in both languages; any other word is the caller's to write into the label. A browser in German takes
`12,5` and puts `12.5` into the value of the input (a browser in another language drops the comma: the value is `125`);
`Nino.adminUi.numberOf( input.value )` is the number, 12.5, for `12.5` and for a text typed with the comma, and null for an
empty field and for anything that is no number - the caller says whether that is nothing typed or wrong. A `formDialog()`
field of `type : 'number'` takes `step`, `min`, `max` and `unit` the same way and gives its value as the input does, a
string; read it with `numberOf()`.

`selectField( { ..., required : true } )` has the star and `aria-required` of every required field. It has no state of
"nothing chosen" of its own: give it an empty first option and an empty `value`, and a `formDialog()` says "<Name> is
missing." at the field, as it does for a text, when it is sent with that option.

## What one panel tells another

A panel that writes data another panel shows says so, and the other panel finds
out when it is shown again - with no event and without the page being loaded
anew. `Nino.admin.touch( topic )` raises the counter of a kind of data after a
write has succeeded, and `Nino.admin.stamp( topic )` reads it. The kinds are
`templates` (the files of `templates/`), `texts` (the keys of the text files),
`elements`, `slots` (the image slots), `routes` and `navigations`; a name that is
none of them is ignored by `touch()` and reads `0`. The kernel's panels touch
what they write - Text and Keys the texts, and the templates where a key of a
template is created, Elements and Element Types the elements, Images and Slots
the slots, Routes the routes, Navigations the navigations - and a feature's panel
that writes one of these kinds does the same: the Builder creates templates, and
touches `templates`.

A panel that shows data it does not write remembers the stamp of it as it was
when the request went out, and compares at its next `showCurrent()`: where the
stamp moved, it asks again, and not while it holds input nobody has saved, which
is asked again at the next showing. Routes does so for `templates`, so that its
form offers a page template the Builder has just created; Text and Images read
`templates` the same way. A panel on `Nino.adminUi.panel()` says it in its spec,
`show: 'keep'` with `stamps: [ 'templates' ]`. The counters live as long as the
page does: a page that is loaded anew asks the server for everything.

`touch()` has a second form, `Nino.admin.touch( topic, uri )`, for a writer that
knows which page of the website the write was about. The first argument raises
the counter of the kind, as above; besides, `touch()` posts `{ kind, uri, at }` on a
`BroadcastChannel` named `nino-admin:` and the directory the installation is in
(`data-dir` of the shell, empty at the root, so that two installations on one
origin do not hear each other): `kind` is the topic, `at` the time in
milliseconds, and `uri` the address given - a path of the website, which starts
with a slash - or `null` where there is none, or where it is anything else. A
name that is none of the six kinds counts nothing and posts nothing, and a browser
without `BroadcastChannel` posts nothing and throws nothing. Whoever listens is
another window: the [follow window](../_admin.md#following-the-website) loads the
website anew after every message, and goes to the `uri` when it has one. The
`uri` is the address of the page *the writer knows* - the path the Routes panel
has just saved a page at - and nothing the writer would have to look up; a panel
that cannot name a page calls `touch( topic )`, and the window loads the page it
shows.

```js
// A catalogue of the page templates, drawn from what the server holds: when the
// Builder (or any panel) touched the templates since, showing the panel asks again
Nino.admin.catalog = Nino.adminUi.panel( {
	uri			: 'catalog',
	mount		: 'catalog-list',
	show		: 'keep',
	stamps	: [ 'templates' ],
	render	: function( data ) { /* draw data.notes */ },
} );

// ...and the write of the panel that creates one
Nino.admin.touch( 'templates' );

// A write that was about one page of the website says which
Nino.admin.touch( 'routes', '/blog/hello' );
```

## The language content is edited in

A view that shows values which depend on the language - a translation of an element, a text of a
key, the name of a page - shows the language the whole workbench edits content in, and has no
switch of its own to choose another: it is chosen once, in the account area of the rail, and
held for the session by the server (`\Nino\Admin\Admin::contentLocale()`). The page hands it
to the script on the shell root (`data-work-locale`, `data-native-locale` and the names of the
languages in `data-locales`), and `Nino.admin.workLocale` is the one source of it: `current`
is the code, `native` the language the project's own words are in, and it starts as that. It is
not the language the workbench is drawn in - that is the interface language, which the picker of
the settings menu sets (`\Nino\Admin\Admin::sessionLocale()`) - and the two are never called
by one name: "Edit content in" and "Interface language".

A view reads `current` whenever it draws, and follows the language where one of its levels is
on screen when it moves, whoever moved it - the rail, or a panel that sets it:

```js
// current is the language to draw in; a language this view has no translation of (one the website does not serve) is a choice of the view
const locale = Nino.admin.workLocale.current ?? Nino.admin.catalog._locales[0] ?? '';

// a select of the view - only until the panel of the view drops it - tells the source and leaves the drawing to the follower
Nino.adminUi.localeSelect( { locales : locales, current : locale, onChange : function( code ) { Nino.admin.workLocale.set( code ) } } );

// the name is the view's entry of the registry of unsaved input (see Unsaved input): the rail asks about those entries before it moves the language
Nino.admin.workLocale.follow( 'catalog', function( code ) {
	Nino.admin.catalog._storeVisibleFields();
	Nino.admin.catalog._draw( code );
	Nino.adminUi.localeBadge( code, bar );
} );
```

A choice in the rail asks first, as every exit of the workbench does, when a view that follows
the language holds unsaved input (Save, Discard, Cancel; Discard also closes the form it threw
away); Cancel stays in the old language. A view that holds nothing the language touches follows
with the name `null` and is not asked about. `set()` moves the source, tells the server and calls
every follower; a view that is not on screen only has to read `current` when it is drawn.

A view that writes content in one language and cannot draw another without being opened anew
does not follow; it calls `Nino.admin.workLocale.bind( name )`
with the name of its entry of unsaved input and its panel. The rail then asks about its unsaved
input as well, and while the view is on screen it loads the page anew in the language chosen
(the address stays, as at a change of the interface language), so that no view shows one language
and writes another. An entry of that registry may carry `saving()`, true while a save runs: the rail
does not take its choice then and says so, instead of asking a question that ends in a save which
is not made twice. A writer that is sent to the server names the language of its text in the
request, and never leaves it to the session, which another window of the same browser may move.

A view whose content depends on the language says so where the person looks, once the language
is not the native one: `Nino.adminUi.localeBadge( locale, bar )` puts a quiet pill in the context
bar (and takes it away again for the native language), so that nobody writes in a foreign
language unnoticed. Where the bar is narrow, the name of the view, its code, its icons and the pill wrap onto a second line
rather than squeeze: nothing is cut away and the bar is never wider than the screen. A language is named in one form everywhere - `Nino.adminUi.localeName( code )`,
"Deutsch" or "English (US)", the code behind it only where two of the languages that stand
together (`localeName( code, among )`) are named alike, which `localeSelect()` does for its options.

## Saying what happened

Every request goes through `Nino.adminUi.api.call( action, payload, callback,
extra )`, which calls back with `( status, body )`, synchronously. When the
page has outlived its session the helper asks for a login again over the page,
and sends the request on afterwards if the same account signs in - so a panel
never handles that itself, and a form is never lost to an expired session. A
page whose session now belongs to another account gets a reload notice instead
and sends nothing. The dialog can be closed without a login: the requests that
waited then call back with the answer they had, so a panel shows its error and
gives its form back, as for any failure. Only a `401` with the code `session` and a `403` with the
code `csrf` are the end of a session: a panel's own `401` or `403` (a wrong
password, a missing permission) reaches its callback as it is.

A failure a person can cause names itself, so the client can say it in the
interface language and mark the field:

```php
\Nino\Http::fail( $request, 409, 'note id already taken: "'. $id. '"', 'catalog_id_taken', [ $id ], 'id' );
```

The third argument stays the English message for a developer or a log; the code
is stable and made of lower-case letters, digits and underscores - what
`errorText()` looks up - the params fill the `%s` placeholders of its text in
order, and the field is the posted key the value was refused for. A panel's own
codes carry its slug as a prefix, the hyphens of the slug written as
underscores (`my_feature_empty` for the slug `my-feature`), and live in its own
`text()` directory as `'[[/_admin/error/catalog_id_taken]]'`, both languages;
the codes every panel may meet - an upload that did not arrive, a value of the
wrong type - are the shell's. `tests/admin-shell-smoke.php` finds every code in
the source and fails on one without text in both languages. Leave a failure
nobody can cause (a file that could not be written) without a code: the person
then sees the English message of the server, with the status number in front,
and the panel's sentence where there is none.

A feature whose `nino` constraint still admits kernels before this release
detects `Nino.adminUi.api` and falls back to its own post where it is missing
(its base is the literal `'[[/nino/dir]]/_admin/'` - `Nino.dir` does not exist
on kernels before 1.3.2),
and passes no code, params or field to `\Nino\Http::fail()` (the older
signature has three parameters) until its constraint requires the release that
brought them.

On the client, `Nino.adminUi.api.errorText( status, response, fallbackKey )`
turns a failure into one sentence - the code's text, else the server's own
message, else the panel's fallback fill, the last two with the status number in
front - and says so plainly when the request never reached the server.
`Nino.adminUi.showError( container, status, response, fallbackKey )` writes it
in place of a list, and `Nino.adminUi.status( el )` is the status line of a
form (`saving()`, `saved()`, `dirty()`, `error( status, response, fallbackKey )`,
and `bind( form, isDirty )`, which turns "saved" into "unsaved changes" on
input). The field the server named is found by `[data-field="<field>"]` or
`[name="<field>"]` inside the bound form, so give the control one of the two.
A message with params goes through `Nino.adminUi.format( text, ...params )`,
never through `String.replace()`: a param is what somebody typed, and
`'$&'` in it is not a pattern.

An upload endpoint asks `\Nino\Admin\Admin::uploadError( $_FILES['file'] ?? null )`
first - it maps what php says about a file that did not arrive to a status, a
code and the limit - and `\Nino\Images::reject( $bytes )` for what is wrong
with the picture (`image_too_large`, `image_type`, `image_too_many_pixels`).
The browser knows the limits too (`\Nino\Images::limits()` writes them onto the
shell as `data-upload-bytes` and `data-upload-pixels`): where a file field is the
block's (`Nino.adminUi.fileField()`) the text of `Nino.adminUi.uploadHint()` is
its `note` and `Nino.adminUi.limits().bytes` its `maxBytes`, so that the limit
stays in view and a file over it is refused at the field; send the file through
`Nino.adminUi.checkImage( file, done )` first, which refuses one that cannot
work in the server's own words without sending it, and say what the server
refuses at the field with `error( text )` of the block. An upload that is the
whole action of its view is stored at once and said by a toast (the guide's
third model), and the block is emptied again, since the picture it holds is the
one the view shows.

A question before an action is `Nino.adminUi.confirm( level, { title, message,
yes, no, kind, typed, visitors, opener, onChoose } )`, never `window.confirm()`: the project is in
a mode (`Nino.admin.mode.current()`; the rail's chip says Development), and the mode decides
how much the workbench asks. `level` says what the action is worth:

| Level | Is | Development | Production |
|---|---|---|---|
| `'routine'` | what is done all day | not asked | asked |
| `'big'` | what reaches visitors or many records at once - a rename, a change of the native language, a scope, a deactivation | not asked | asked, with a line that the site is live and not in maintenance, unless the maintenance page is up |
| `'destructive'` | what cannot be taken back - a delete, a restore | asked | asked |

A level nobody named counts as `'destructive'`. The policy is the shell's
(`Nino.admin.mode.policy( level )`, answering `{ ask, note }`): the design system
owns no rule about when to ask, and asks every question where there is no shell
or the shell has no policy - the setup wizard, a test, a shell older than the
policy. `title` is not optional - a call without one throws a `TypeError`, also
where the policy would not ask: it says what is done to what as a statement
(*Delete element*), and `yes` carries the same words, never *Yes* or *OK*. `message`
is the question; `no` is what the answer that does nothing is called
(`/_admin/common/label/cancel`), and `kind` is `'danger'` or `'primary'` for the answer that goes on,
`'danger'` for a destructive level and `'primary'` otherwise: say `'danger'`
yourself where something is switched off or goes back to what the base has, whatever
the level. A dialog whose way on is `'danger'` opens with the focus on the answer that
does nothing, so Enter on a dialog that has just appeared is a No; any other opens on its
primary answer. The answers stand in one row at the right, Cancel at the left of the
one that goes on. `visitors : false` says that the action does not touch what
visitors see - an account, a role - and leaves out the line of a `'big'` level that
the site is live. `onChoose( ok )` is
called once with `true` or `false`, and **before `confirm()` returns** where
nothing is asked, so what follows the question goes into it. When another
question is open `confirm()` answers `false` and calls nobody back: a save that
asked ends with `done( false )` then, as it does for a No.

```js
Nino.adminUi.confirm( 'destructive', {
	title			: Nino.content.getText('/_admin/catalog/confirm/delete-title'),
	message		: Nino.adminUi.format( Nino.content.getText('/_admin/catalog/confirm/delete'), name ),
	yes				: Nino.content.getText('/_admin/catalog/confirm/delete-title'),
	no				: Nino.content.getText('/_admin/common/label/cancel'),
	onChoose	: function( ok ) {
		if( ok === true )
			Nino.admin.catalog._send( 'delete', { id : id } );
	},
} );
```

`typed : 'legal'` is the highest weight: the answer that goes on stays locked until that
word is typed into a field the dialog draws under the message (`typedLabel` words it,
`/_admin/common/confirm/typed` by default), and the question is asked even where the
policy would not. `onChoose( ok, word )` is given the word as it was typed, and a call
that goes to the server sends that word, not the one the page knows. Use it where the thing
takes many others with it - an element type with all of its elements - and nowhere else.

A feature whose `nino` constraint still admits kernels before this one checks
`typeof Nino.adminUi.confirm === 'function'` and asks with `window.confirm()`
where it is not there, until its constraint requires the release that brought it.

Do not copy the skeleton blindly if an existing panel already solves the same
list, locale, upload, rich-text, reorder, relationship, or confirmation
problem. Reuse its exact public helper and adapt the nearest implementation.
For a registry plus ordered route membership - a working copy that one Save
writes as a whole, registered with the shell - inspect
`\Nino\Modules\Navigation\Admin` and
`_nino/Nino/Modules/Navigation/assets/admin.js`; for the smallest complete
panel, `\Nino\Modules\Sample\Admin` in `tests/fixtures/features/Sample/Admin/Admin.php`,
the reference feature of the contract test - or, with a script beside it,
the catalogue's `\Nino\Modules\Search\Admin` in
[features/Search/Admin/Admin.php](https://github.com/dapeio/nino-features/tree/main/features/Search/Admin/Admin.php)
and its `assets/admin.js`.

## Unsaved input

A form that holds something nobody has saved tells the shell, and the shell
asks **Save**, **Discard** or **Cancel** before anything throws it away: the
back link of the form, a move through the rail - its links and the select that
stands in for it on a phone, but not a move from one tab of a pane to another - a log out, the
interface language, and every exit the panel guards itself. The registry is `Nino.admin.dirty`, defined by the shell
script before any panel file loads; a panel registers when its own file loads,
behind a feature check, because panel scripts also run in tests and on shells
that have no registry:

```js
if( typeof Nino.admin.dirty === 'object' )
	Nino.admin.dirty.watchForm( 'catalog', function() { return dc.getElementById('catalog-edit') }, function( done ) {
		Nino.admin.catalog._save( done );
	} );
```

A panel built on `Nino.adminUi.panel()` passes a `register()` entry -
`{ isDirty, save, discard, bar, shortcut }`, described below - as the `dirty` of its spec
and is registered behind the same check at its first load; a panel that watches
its form calls `watchForm()` itself.

`watchForm( name, formGetter, save, bar, shortcut )` serialises the plain fields of the form
the getter answers and compares them with what `snapshot( name )` saw - `bar`
is optional, a function answering the action bar that carries the "unsaved"
marker, for a pane in which the first bar of the form is not the form's own, and
`shortcut` is what Ctrl+S does here (see below): call
`snapshot( 'catalog' )` after the form is drawn and after a save went through.
A file input, a search box, a password the browser may fill in and a field with
`data-dirty="ignore"` are not input. A form whose state is not its plain fields
(rich text read back as other markup, edits kept in the model, a copy nobody
saved) registers with `register( name, { isDirty, save, discard, bar, shortcut } )` and
answers `isDirty()` itself - compared with what the controls held when they
were drawn, never with the stored string. `name` is the uri of the panel or tab:
the shell finds the pane by `admin-tab-<name>`, else `admin-content-<name>`.

A panel whose levels are several forms over one draft goes up a level inside
itself with a back link that says `data-keep-draft`: the shell asks nothing of
it and leaves what was typed where it is, and a Discard from the rail does not
press it as the link that leaves the form. The panel's own click handler does
the going up.

```js
const up = Nino.adminUi.el( 'a', 'nino-admin-back-link', 'Back to the groups' );
up.href = Nino.admin.router.href( 'catalog', [ 'craft' ] );
up.setAttribute( 'data-keep-draft', '' );
up.addEventListener( 'click', function( event ) { event.preventDefault(); showLevel( 1 ) } );
```

Four rules follow:

- `save( done )` calls `done( true )` or `done( false )` on **every** way it can
  end: a second submit while one runs, a refusal before anything is sent, a
  failed request, success. The shell goes on only on `true`; on `false` it
  brings the panel on screen again with its errors.
- Every exit that would lose the form runs inside
  `Nino.admin.dirty.guard( [ 'catalog' ], proceed, onCancel )` - `null` for a
  reload of the whole page (feature activation, a backup restore, a log out).
  `onCancel` undoes the control that started the exit (a checkbox, a select).
  Nothing is sent before `proceed`, which hears the answer - `'save'` or
  `'discard'` - where a question was asked, and nothing where there was none.
- A form that is rebuilt from the server whenever its panel is shown must not be
  rebuilt over unsaved input (`showCurrent()` checks `isDirty()` first). A load
  error written into the watched container leaves no fields: the shell takes
  that for clean, and a panel that tracks its own drawn state resets it there.
- `discard()` forgets the input *before* the exit runs. The fields of a form
  `watchForm()` watches are put back to what `snapshot()` saw - the controls
  that hang on them are told, as if the person typed the old values back - and
  the form counts as saved; a form that has other fields than it was drawn with
  (rows added since) cannot be put back field by field and is only taken for
  saved. What a `register()` entry forgets is its model, and its controls
  are the exit's to draw again: when that exit's request then fails without
  redrawing the form, the discarded text stays in the controls and counts as
  saved. That is a known limitation, not a rule the exits keep: an exit that
  wants to avoid it redraws or reloads the form itself when its request fails
  (the Keys schema, rename and delete exits and the feature, backup and
  logout-all exits do not).

The rail asks with the same `guard()` as the back link: **Cancel** puts the rail back
(the open link, the select, the address - written by the shell where the panel writes
none of its own) and **Discard** calls the `discard()` of every entry, goes on to the
panel asked for and then presses the back link of the form, the one on screen in the
pane of the entry (the nearest tab, else the panel). The rail leaves a pane standing in
memory, and without that the form would come back with the discarded input in it: the
panel's own handler for its back link leaves the form, and it is drawn again from what is
stored when it is opened next. A form that is the whole screen of its panel has no back
link; its `discard()` has put the fields back. So a panel registered under its uri, or
the uris of its tabs, with a back link in the form it draws, has nothing more to do for
the rail.

The question is `Nino.adminUi.choiceDialog( { title, message, choices, onChoose } )`
in the design system: it owns no words and names no `Nino.admin` member. A text that
is read rather than answered - a manual - is `content`, an element drawn in place of the
message, with `className` for the dialog and `opener` for what gets the focus back where
that is not what had it (a button that was clicked has it in Chromium and Firefox, not
in Safari); `Nino.adminUi.manualDialog( title, content, opener )` is that, with one
button to close it. A form
that has a status line (`Nino.adminUi.status()`) shows the unsaved state there;
every form gets the word *Unsaved changes* in its action bar from
`Nino.admin.dirty.refresh()`, which a panel calls at the end of its own save -
the style sheet hides it in a bar that has a status line, except below 38 rem,
where the status line is hidden. While a save of the form runs, its `discard()`
leaves the model alone and its links are inert.

**Ctrl+S**, or Cmd+S, saves what is on screen, and the shell does it through the
registry: the entry of the panel or tab the address names - `Nino.admin.router.current().panel` -
has its `save( done )` called where it holds unsaved input, and nothing happens where it
does not, or where no entry is registered, a save is running or a question is open. A
panel that saves something other than a form, or does one thing in development and
another in production, gives its entry a `shortcut`, `{ development : fn( done ), production : fn( done ) }`:
the function for the mode the project is in is called whether the entry is unsaved or not -
it decides that itself - and an entry with none for that mode falls back to `save()`.
`done( ok )` ends the run, as it ends a Save the shell asked for, and a second press
before it does is ignored. The shell always takes the key from the browser, whose
answer is to save the page as a file, also where there is nothing to do.

A required field is marked the same way everywhere: the asterisk
(`.nino-admin-required`, `aria-hidden`) after its name, `aria-required` on its
control, and after a refused save the sentence under it
(`.nino-admin-field-error`, linked with `aria-describedby`) with the focus on
the first one. The sentences are not alerts; the form's status line announces
the refusal once. `Nino.adminUi.fieldError( wrapper, control, id, text )` draws
a sentence - after a `label`, else at the end of the block that holds the field
- and marks the control `aria-invalid` and `aria-describedby`;
`Nino.adminUi.clearFieldErrors( root )` takes them off again. What a panel
refuses before it sends anything goes into the line with `status.fail( text )`
- an alert - and the sentences into the fields, in that order: the line says
that something is wrong, the fields say what.

## Designing an admin frontend

This section is the reference of the classes and blocks; which of them a screen
uses for which task, and what a panel must not build itself, is
[The workbench interface](../admin-ui.md). The design system's tokens for type,
weight, space and height are declared at the top of `style.css`; a panel's
stylesheet names a step of them and writes no size of its own.

The `nino.system` half of `_admin/assets/style.css` is the design system for
the workbench and every panel in it, the setup wizard and the recovery page
included. It is not a stylesheet one screen happens to share - it defines the
vocabulary, and a panel's own stylesheet - `assets/admin.css` beside its
class, whether the module is the workbench's own or a project's - supplies only
what is genuinely local to it. The `nino.tool` half of the same file is the
shell's own: the rail, the panel switching, the login screen.
`_admin/assets/Nino.admin.js` is the same idea for behaviour: a component that assembles
markup (`Nino.adminUi.switchField()`, `.table()`, `.selectField()`) lives there,
never as a per-tool copy. It extends the namespace `Nino.js` creates, so it loads
after it - and it is deliberately *not* in the public script bundle, which is why
it is a file of its own rather than more of `Nino.js`.

**A panel ships its own stylesheet and nothing else.** A panel's `assets()`
join the workbench's bundle after the shell's own files, and a module panel's
template is a fragment that links nothing. The old tools used to load each
other's complete stylesheets for a handful of classes; copy the class you need
into the panel that needs it, or promote it to the design system - never
restate the shell. Every rule of a panel stylesheet is loaded on every screen
of the workbench, so scope every one of them to the panel's own root
(`#pd-app .pd-canvas`, `#features-list .admin-features-head`) - a bare `body {}` or
`code {}` in a panel file lands on the whole workbench.

### The five rules

1. **The shared file contains no id selectors.** An id names one element in one
   tool. A tool's own stylesheet may use ids freely - that is where one-off
   layout belongs.
2. **Shared classes are namespaced `nino-admin-*`.** If a class does not carry
   that prefix it belongs to one tool, and the shared file must not style it.
3. **`.nino-admin` is the scope root**, on the outermost element of any admin
   surface. Add `.nino-admin-shell` for a full-height application surface,
   `.nino-admin-shell--rail` if it is the usual rail+pane layout, or
   `.nino-admin-auth` for a centered login card.
4. **A class name describes a role, never a place.** `.nino-admin-btn-primary`
   is "the primary action", not "the button under a list". A class carrying the
   margins of wherever it first appeared breaks the moment it is reused - which
   is exactly how the wizard's "New Route" button once inherited a stray
   `margin-top` from another screen's list class.
5. **Cascade layers decide who wins before specificity.** Every stylesheet of
   the workbench opens with `@layer nino.system, nino.tool, nino.local;`. The
   design system lives in `nino.system`; normal screen and component rules
   live in the later `nino.tool` layer, so they can refine the low-specificity
   `:where(.nino-admin)` baseline without selector escalation. Reserve
   `nino.local` for a deliberate final override, not every normal component
   difference.

### When adding a screen

Reach for an existing class first; only invent one when no role fits.

| Need | Class |
|---|---|
| Surface root | `.nino-admin` + `.nino-admin-shell` (+ `--rail`) |
| Sidebar, its brand block, its nav | `.nino-admin-rail`, `.nino-admin-rail-head`, `.nino-admin-nav` |
| Scrolling content column | `.nino-admin-pane` |
| Sticky top row (back link, locale switch) | `.nino-admin-contextbar` via `Nino.adminUi.contextBar( { back, title, code, icons, tools, panel, panelLabel } )` (called with a link and its controls it takes them as they are); the switch is `Nino.adminUi.localeSelect()` (`.nino-admin-locale-select`) |
| Back link inside that row | `.nino-admin-back-link`, drawn by `contextBar( { back : { to, onClick } } )` as "Back to <to>" |
| Fixed bottom actions | `.nino-admin-actionbar` via `Nino.adminUi.actionBar( { danger, status, secondary, primary } )`: the destructive action at the left, the state and its line, the side actions, ONE main button at the right edge |
| The answer to an action that went well | `Nino.adminUi.say( text )`: the toast above the bar, one at a time, never for an error (a kind it does not know, 'error' too, is the neutral one and never throws) |
| The manual of a screen - sections of a handle and what it does, or running text - in a dialog or on the screen | `.nino-admin-manual` via `Nino.adminUi.manual( content, options )`: `[ { heading, entries : [ { handle, text } ] } ]` or a text, `options.named` to name a handle in words instead of code, `options.none` for the sentence of an empty section, `options.level` for the headings; `Nino.adminUi.manualDialog()` shows it in `.nino-admin-dialog--manual` |
| Fixed actions above a list | `.nino-admin-list-actions` via `Nino.adminUi.actionBar( { list : true, primary } )` for "New <thing>"; `Nino.adminUi.listActions( buttons )` for a list bar with more than one button (a scan and a new one, an export) |
| Status text in a bottom bar - saving, unsaved changes, why it failed (a save that went well empties it: the toast and the badge say it) | `.nino-admin-status` via `Nino.adminUi.status()` (in the bar it replaces `.nino-admin-actionbar-status`) |
| Mark after the name of a required field, and the sentence under a refused one | `.nino-admin-required` (drawn `aria-hidden`; the control gets `aria-required`), `.nino-admin-field-error` (linked with `aria-describedby`, never `role="alert"`) |
| "Unsaved changes" mark in a registered form's action bar | `.nino-admin-actionbar-dirty`, written by `Nino.admin.dirty.refresh()` into every registered form's bar and hidden by the stylesheet only in a bar that has a status line (below 38 rem, where the status line is hidden, it shows again - except in a bar that has the badge of `Nino.adminUi.actionBar()` or `saveBar()`: there the registry paints the badge and draws no mark of its own) - not a `p`, not `role="status"` |
| Primary / destructive button | `.nino-admin-btn-primary`, `.nino-admin-btn-danger`, built with `Nino.adminUi.button()` |
| A button small enough for a row - the actions of one entry, a button beside a field | `.nino-admin-btn--small` beside the variant that says what it does (30px high, where the base button is as high as a field) |
| Buttons and a status line in one row inside a form or under a list - not the fixed bar | `.nino-admin-actions` (wraps; `--end` stands it at the right edge, `--between` spreads it); the space above it is the screen's own rule |
| A short word as a pill beside a name - a state, a kind, a count | `.nino-admin-badge`; `data-state="ok"`, `"warn"`, `"error"` or `"muted"` colours it like the status line's saved, unsaved, failed and quiet text, and the word says it too. On the button of a tab it is drawn by `Nino.adminUi.tabBadge( tab, text, state )` |
| Up, down and remove at the end of a row of an ordered list | `.nino-admin-move` around the square buttons (inside a `.nino-admin-list` row it keeps off the row's edge) |
| Raised panel | `.nino-admin-card` (a `fieldset` is one already) |
| Clickable drill-down list | `Nino.adminUi.list( rows, options )` - see [Lists, filters and tabs](#lists-filters-and-tabs); a row that writes an address is a link with the `href` of `Nino.admin.router.href()` |
| The first stop of a page, a way past the rail to the pane | `.nino-admin-skip`, a link to the id of the pane; it waits above the screen until it has the focus. The shell's script makes the pane a target of the focus (`tabindex="-1"`) for the jump and takes that off as the focus leaves it - one that always had it would take the focus from a click on any text in it |
| Many rows of the same record, with search/sort/paging | `Nino.adminUi.table()` — see below |
| List of rows that are read, not opened | `.nino-admin-list-dense` |
| List whose rows are `button`s | `.nino-admin-list-buttons` |
| Dashboard tiles | `.nino-admin-tiles`, `.nino-admin-tile` |
| The card a screen opens with, in lines of different weight | `.nino-admin-card` with a rule of the panel's own stylesheet for each line, scoped to the id of the card, in the shell's text colours so that both themes keep their contrast |
| Share-of-total bar under a label | `.nino-admin-meter-row` + `-label`/`-count`, `.nino-admin-meter-track` + `-fill` |
| Labelled form field | `.nino-admin-field` (`.nino-admin-field-wide` opts out of the two-column desktop grid), built with `Nino.adminUi.textField()`, `selectField()`, `switchField()`, `numberField()`, `textareaField()`, `checkField()` or `codeField()` |
| Container whose fields share that grid | `.nino-admin-fieldgrid` |
| Run of checkbox rows, a group of ticks as a field | `.nino-admin-checklist`, `Nino.adminUi.checklist()` |
| Rich-text editor mount | `.nino-admin-richtext` (`Nino.admin.htmlEditor.create()`, its fifth argument the format: `inline`, `lines` or `blocks`) |
| List of texts, edited as rows | `.nino-admin-stringlist` via `Nino.adminUi.stringList()` (the value is a hidden input carrying the JSON, like `elementList()`'s) |
| Sections one under another, one open or several, the open one in the address | `.nino-admin-accordion` via `Nino.adminUi.accordion()` - see [Settings screens](#settings-screens-accordion-steps-split-view-and-colour-field) |
| One choice among a few steps (-2 to +2, or named) | `.nino-admin-steps` via `Nino.adminUi.steps()`, a radiogroup |
| Controls on one side, a preview on the other, a switch between them where it is narrow | `.nino-admin-split` via `Nino.adminUi.split()`; as a work surface that fills the height, with an outline that folds: `split( { fill, aside } )` - see [A work surface](#a-work-surface) |
| The page a preview shows, its width and Reload as one group | `.nino-admin-previewtools` via `Nino.adminUi.previewTools()` - in `contextBar( { tools } )` of a work surface |
| Entries whose look is the content, each with a picture, a name, badges and a line of buttons | `.nino-admin-cards` via `Nino.adminUi.cards( items )` |
| A preview that follows a draft, the question of a refused save, the bar of a screen that is saved as a whole | `Nino.adminUi.previewFrame()`, `conflict()` and `.nino-admin-savebar` via `saveBar()` - see [the three builders](#workbench-screens-that-save-as-a-whole-the-preview-loop-the-conflict-and-the-save-bar) |
| Entries one under another with children, a type icon, a hint and buttons of their own - the components of a page, the outline of a template; the same marked in several views at once | `.nino-admin-tree` via `Nino.adminUi.tree()`, a `role="tree"`, with `hoverSync()` and `previewFrame().marks()` - see [A tree](#a-tree) |
| A colour as a hex code, a picker and its contrast against another colour | `.nino-admin-color` via `Nino.adminUi.color()` |
| Explanatory text beside a thing; the lead of a step of the setup wizard | `.nino-admin-hint`, `.nino-admin-hint-lead` (for the wizard alone); `Nino.adminUi.hint( text, kind )` builds the line |
| Something needs attention until it is fixed - failing mail, untranslated texts, maintenance on | `.nino-admin-notice` via `Nino.adminUi.notice( message, { href, label } )`; a link only for a `#panel` href, the text always as text |
| Nothing here yet (a list without entries, a scan that found nothing) | `.nino-admin-empty` via `Nino.adminUi.emptyState( text, action )` |
| Error text | `.nino-admin-error` |

The bar, the toast and the way back of a form, in the three calls the Routes and Navigations panels make:

```js
// The way back: "Back to <name of the panel>", the name read from the head of the pane
wrap.appendChild( Nino.adminUi.contextBar( { back : { to : wrap, onClick : function() { Nino.adminUi.backTo( 'notes-list', opener ) } } } ) );

// The bar: Delete at the left, the badge and the line, Save at the right edge - and nothing else
const bar = Nino.adminUi.actionBar( {
	danger  : Nino.adminUi.button( Nino.content.getText('/_admin/notes/label/delete'), remove, { kind : 'danger' } ),
	status  : true,
	primary : Nino.adminUi.button( Nino.content.getText('/_admin/common/label/save'), null, { type : 'submit' } ),
} );
form.appendChild( bar.el );
bar.line.bind( form );

// What went well, said once the answer is in; a failure goes to bar.line.error()
Nino.adminUi.say( Nino.content.getText('/_admin/common/msg/saved') );
```

**Every word is a fill.** A panel script never renders a literal English
sentence: the workbench's strings are `_admin/text/<locale>.php`, a module's are
the `text()` directory beside its panel, and `[jstext]` hands all of them to
`Nino.content.getText()`. A placeholder is `%s`, `%d` or `%n`, filled with
`Nino.adminUi.format()`. A label the backend sends - a schema's `label` and `hint`, a
dashboard tile's or a permission's label - may be a fill key or literal text;
`Nino.adminUi.text()` resolves it either way - which is also how a value that
is both shown and compared stays translatable: send a slug and name it on the
client (see the Features panel's `_categoryLabel()`), never
compare against a word a locale file can change.

A date or a moment a panel draws itself is written by `Nino.adminUi.dateText( value, options )` (`_admin/assets/Nino.admin.js`): `value` is a string as the files write it - `2026-11-17`, `2026-11-17 06:02`, a `T` for the space, seconds read and left out - or a `Date`, and the answer is `17.11.2026, 06:02` in a German interface and `2026-11-17, 06:02` in any other one, the date alone for a value that has no time of day, and `''` for what is no moment. A panel never formats a date with the browser's `toLocaleString()`.

How a request goes to the server and how a failure is worded is [Saying what happened](#saying-what-happened); a form that holds unsaved input is [Unsaved input](#unsaved-input).

A workspace panel's markup (`templates/panel.tpl`) is the other place a
sentence hides, and reads ordinary `[[fills]]`. A fill's *value* goes into the
page as it is and nobody reads it for calls, so a shortcode token in a value is
only text (`[jstext]` ships the text of a value, its entities decoded and the
fills in it filled in, for a script to write with `textContent`). Put a `%s`
in a sentence a script renders, filling it from the script's own
source - a `.js` file is a static asset and is never rendered.
`tests/admin-lists-js-smoke.js` fails on a literal sentence in a panel script
or in that markup, and `tests/admin-shell-smoke.php` twice over: statically on
a key a panel's scripts or markup ask for that either language lacks, and end to
end - it renders the whole workbench in both interface languages and fails on any
`[[fill]]` that came out the other side unresolved, which is the only version a
key its regexes do not recognise cannot slip past. A schema two surfaces share keeps its English as the
fallback, and the panel looks for a fill of its own first. The recovery page
and the setup wizard are not panels and have no fills to ask - the project is
what the wizard makes, and the recovery page has to work when the project's
text is what is broken: they speak German or English, German first, from one
fixed table each with the same keys in both (`_admin/install/assets/text.js`,
`_admin/assets/recovery-text.js`, registered with `Nino.pageText`,
`_admin/assets/pagetext.js`). A template marks what it writes with
`data-text="<key>"` (also `data-label` and `data-title`), a script writes
`Nino.pageText.t( key, ...params )`, and a refusal of the server comes back as a
code - `Nino.pageText.failure( status, response )` finds its sentence under
`/_admin/page/error/<code>` with the underscores of the code written as
hyphens, and says one general sentence with the status for a code that has no
sentence, never the server's own English. The language is one of two
(`Nino.pageText.LANGUAGES`, an allow-list that never reaches markup or a path),
chosen by the button at the top right and kept in the browser's storage under
one key, with no cookie. The choice itself is not sent: the one thing the pages
send of it is `lang` (`de` or `en`, an allow-list on the server that anything
else falls out of) with the requests that ask the catalogue of applications for
names, so that the names come in the language of the page; it is kept nowhere.
A step that writes words by hand redraws them in `retext()` and keeps what was typed
(`Nino.pageText.keep()`). The words of the blocks of the workbench
(`/_admin/common/...`) are in each table as the workbench writes them, and a test
holds them equal. `tests/page-text-js-smoke.js` fails on a key one language lacks,
on an empty sentence, on a sentence that stands in a script or a template, and
on a code of the server without a sentence.

### The shared data table

`Nino.adminUi.table()` renders a sortable, searchable, paged table of records.
Use it where the values themselves are what the screen is for; the grouped list
stays right for a handful of rows you only drill into.

It owns **no strings**. Everything that can be a number or a glyph is one - the
pager arrows, the row range, a boolean cell - so a caller supplies only
`labels: { search, empty, noMatch }`. That is deliberate: every word comes
from the caller, translated through `Nino.content` or not, and a component
that hardcoded one would be half-translated wherever the caller's are not
English.

Pass the **whole set** and let it page. An element type is one file read whole
on every request (`\Nino\Elements::queryElements`), so asking for one page costs
exactly what asking for everything costs, and searching locally is instant
instead of a round trip per keystroke. 1000 rows of eight columns is 14 KB
gzipped. Roughly 1000 elements per type is also where the storage model itself
stops being the right tool — past that, use SQLite or MySQL.

A column may carry `render(value, row)` returning an Element, for a cell that
holds a link or a per-row action; sorting and searching still use the plain
value, so behaviour never depends on how a cell is drawn. A column is sorted
by the button its label is: one with no label - the column of a row's own
buttons - has no sort button, nor has one that says `sortable: false`. With an
`onRowClick` a row opens on a click and on Enter or Space; it is a stop of the
Tab key of its own only where no control stands in it - a row that holds
buttons or links is reached through them - and it carries the row key as
`data-row`, which is how a panel finds it again after the table was drawn anew.
A column may also carry `absent(row)`: a row for which it answers true counts as empty when the column is sorted, after the others in either direction, though its cell shows a value (a text shown in place of another language's); searching still reads what is shown.

The pure half (`Nino.adminUi.tableModel`) is filtering, sorting, paging and cell
formatting as plain functions — extend and test there, not in the renderer.
`tests/nino-ui-table-js-smoke.js` covers it.

### The standard directory and the record

`directory()` takes the rows it starts with as `rows` (a `setRows( rows )` straight after the call); `rows : []` is a directory with nothing in it, and a call without `rows` says "Loading ..." (`labels.loading`) until `setRows()` or `fail()` - give no `rows` where they are read from the server, and `rows : []` only where there are none. `directory()` draws everything over and under the rows of a panel in one call - the category, the search, the selects that narrow, the table with its box to choose rows by, its cells (`cell : 'clamp'`, `'limit'`, `'thumb'`, `'locales'`, `'date'`, `'badge'`) and buttons, the paging (25 for a table of pictures, 50 for every other); under 40 rem each row is a card drawn from the same columns - `primary : true` names the column that is the name of the card, `narrow : false` leaves one out, `narrow : 'second'` makes it the line under the name, and the select "Sort by" takes the place of the heads - and hands the rows in their order to `record()`, which opens one entry in the large dialog: name and identifier, the steps through the rows the directory shows, the bar, the address `#panel/tab/<entry>` and the question about unsaved input. `readLine()`, `messageBox()` and `imageFrame()` are what a record that is read is drawn with, `counter` of `textField()` and `textareaField()` the "n / limit" of a field. The options, the rules for when a panel uses which and a recipe to copy - a script that runs as it stands, and the fills and the server side that go beside it, with the actions behind [the backend skeleton](#backend-skeleton) - are in [the workbench interface](../admin-ui.md#directory-and-record); `tests/nino-ui-directory-js-smoke.js` and `tests/nino-ui-record-js-smoke.js` pin the blocks, `tests/admin-submissions-js-smoke.js` is the first panel over them.

### Lists, filters and tabs

`list()` draws the groups and short directories of a panel, `table()` its directories, `filterBar()` and `chip()` what narrows a list, `emptyState()` the box of an empty one and `tabs()` the second level of tabs of a view. None of them owns a word.

```js
// A group of rows: the title of the group, rows that open, at most three small actions at the end of a row
mount.appendChild( Nino.adminUi.list( [
	{ key : 'home', title : 'Home', detail : '3 texts', badge : 'new', onOpen : function( row ) { open( row.key ) } },
	{ key : 'about', title : 'About', detail : '7 texts', href : Nino.admin.router.href( 'text', [ 'about' ] ), onOpen : function( row ) { open( row.key ) },
		actions : [ Nino.adminUi.button( 'Copy', copy, { small : true } ), Nino.adminUi.button( 'Delete', remove, { kind : 'danger', small : true } ) ] },
], { title : 'Pages' } ) );
```

A row with an `href` is a link, one without a button; the press of either is `onOpen( row, event )`, and a row that has neither is read only. The row is as high as `--nino-admin-row-height` where it has a `detail` and as `--nino-admin-row-dense` where it has none. `sub : true` sets a row in under the one above it. An action that takes something away is a button of the kind `danger`; `rowActions()` puts it last, and refuses a fourth button with a `RangeError`.

```js
// A directory: the search over the full width, sortable columns, the small buttons of a row at its end
Nino.adminUi.table( {
	mount, rows, rowKey : 'key', search : true,
	columns : [ { key : 'name', label : 'Name' }, { key : 'version', label : 'Version' } ],
	labels  : { search : 'Search features', empty : 'No features.', noMatch : 'Nothing found.' },
	actions : function( row ) { return [ Nino.adminUi.button( 'Activate', function() { activate( row ) }, { kind : 'primary', small : true } ) ] },
	onRowClick : open,
} );
```

`search : true` is the search of a directory; the field filters the rows that are drawn, over the values of the columns and, where `searchText( row )` answers one, over more text of a row (a key, a description), and a search that finds nothing shows `labels.noMatch` in the empty state of the table. A panel that draws the table again gives the search back with `query` and hears it with `onQuery( query )`. A select that picks a kind stands left of the search, in the same line, as `filter : { options : [ { value, label } ], value, label, onChange( value ) }`: the table does NOT filter by it - it draws and tells, and the panel loads or filters and draws the table again - and under 30 rem the select stands over the search, both of the full width. A row that opens (`onRowClick`) draws a "›" at its end, in the column of the actions. A column that holds a sentence says `wrap : true`. A press on a button of a row does not open the row.

```js
// What narrows a list: one bar with a search and chips
const empty = Nino.adminUi.chip( 'Empty in de_DE', { onToggle : function( on ) { onlyEmpty = on; draw() } } );
mount.appendChild( Nino.adminUi.filterBar( { search : { label : 'Search texts', onInput : function( query ) { find( query ) } }, items : [ empty ] } ).el );

// The same bar for a list that is no table, cards say: a select of the kind left of the search, which the table's `filter` is made of
mount.appendChild( Nino.adminUi.filterBar( { filter : { options : [ { value : '', label : 'All themes' }, { value : 'own', label : 'Own themes' } ], value : kind, label : 'Kind of theme', onChange : function( value ) { kind = value; draw() } }, search : { label : 'Search themes', onInput : function( query ) { find( query ) } } } ).el );

// A box for what is not there yet, with the button that makes the first
mount.appendChild( Nino.adminUi.emptyState( 'No redirects yet.', Nino.adminUi.button( 'New redirect', create, { kind : 'primary' } ) ) );

// The second level of tabs: arrow keys, Home and End, one tab stop; a tab may bring its pane
const strip = Nino.adminUi.tabs( [ { key : 'one', label : 'Description', panel : about }, { key : 'two', label : 'Settings', panel : settings } ], { onSelect : function( key ) { remember( key ) } } );
mount.appendChild( strip.el );
```

A chip turns its `aria-pressed` over on a press and then calls `onToggle( pressed )`; the caller draws what it filters. `tabs()` is the block for the tabs of the second level of a view; the tabs of the first level stand in the head of the pane and are not made with it. A tab may carry a `badge` (`{ key : 'active', label : 'Active', badge : 12 }`): a count or a short word as the workbench's pill after the label, which is no part of the label, and `strip.badge( 'active', 13 )` says it again in place.

### Settings screens: accordion, steps, split view and colour field

Four builders for a panel that sets a number of values with a preview beside
them. Each answers an object whose `el` is the element to put into the panel;
the words they draw themselves (the switch of the split view, the verdict of the
colour field, the mark of a default) are fills of `_admin/text/<locale>.php`
(`/_admin/common/split/*`, `/_admin/common/color/*`, `/_admin/common/label/default`),
and every other word - a label, a summary, a title - is the caller's, set as
text. The four examples each run on their own in a panel's `render()`, `mount` being
the element it draws into.

An **accordion** is a list of sections, a heading with a button each. One is open
(the open head is `aria-disabled`: it says it is open and a press changes
nothing) or, with `multiple`, as many as the person opens; with `collapsible` at
most one is open: a press on the open head closes it (so the head below it can be
reached), no head is `aria-disabled`, and none is open at the start unless `open`
names one. Enter and Space press a
head, Up and Down walk the heads, Home and End go to the ends; a section may carry a
`help` - the sentence behind a (?) beside its heading (in a row with it, outside the heading, so that the name of the heading is the title alone), a button of its own that does not open or close the section: it is one more stop for the Tab key behind its head (the
arrows walk the heads alone, as they do with any composed block), and the head is described by the
sentence as a field is by its help; a section without `help` is the heading and its button and has no row. A section's body is
hidden when closed and not removed, and one given as a function is made when it
first opens. An accordion in the body of a section is the second level - give it
`level : 4`. With `address` the open section is the address of the panel: a press
adds a history entry, and `follow()` takes the section from the address, which is
what the panel's `showCurrent()` does when the shell calls it after Back or a
typed address. Several open sections are one part of the address, their keys
joined by a comma, which is why a key holds none. Keys are unique within an
accordion, and two accordions of one panel that both write the address need
different `parts`, or each reads the other's section. Where one section is open
at a time one always is: with no section in the address, or one the accordion has
not, it is `open`, and with no `open` the first. A collapsible accordion has none
open there: its address without a section is the address of the level, and
`follow()` closes every section on it. `update( key, { summary, badge } )`
changes the summary of a head and its badge and nothing else - not the label, the
body or what is open. A summary or a badge says something that is true whether or
not the section is open, and is set where the value changes, as the example does.

```js
const spacing = Nino.adminUi.steps( {
	label : 'Spacing', steps : [ -2, -1, 0, 1, 2 ], value : 0, standard : 0,
	onChange : function( value ) {
		accordion.update( 'page', { summary : value === 0 ? '' : '1 of 2 knobs changed', badge : value === 0 ? '' : '● changed' } );
	},
} );
const accordion = Nino.adminUi.accordion( {
	address : { panel : 'themes', parts : [ 'craft' ] },
	open : 'page',
	sections : [
		{ key : 'brand', label : 'Brand', summary : 'Two colours', content : function() { return Nino.adminUi.hint( 'Made when the section first opens.' ) } },
		{ key : 'page', label : 'Page', content : spacing.el },
	],
} );
mount.appendChild( accordion.el );
accordion.follow();
```

**Steps** is a `radiogroup` of buttons: the chosen step is the one tab stop, the
arrows move the choice and the focus (and wrap), Home and End go to the ends. A
number is written with its sign; `standard` marks the default step with a dot
and with its name for a screen reader. A step may be `{ value, label, title, disabled, icon }`: with an `icon` (a name of
the [icon table](#buttons-and-icons)) the step shows the icon, centred, in place of its word; the word stays the step's name for a
screen reader and its tooltip where the step has no `title`. A step without an `icon` is drawn as it always is, and a name that the
table does not have is a step with its word. `onChange` is told what the hand chose,
`set()` reports nothing, and `disable()` takes the strip out of use.

```js
const spacing = Nino.adminUi.steps( {
	label : 'Spacing', steps : [ -2, -1, 0, 1, 2 ], value : 0, standard : 0,
	onChange : function( value ) { mount.dataset.spacing = String( value ) },
} );
mount.appendChild( spacing.el );
```

The **split view** is the settings on one side and the preview on the other. `settingsWidth : 'wide'` gives the settings column 480 px (18 rem at the least, half of the view at the most) instead of 23 rem, with or without `fill`. It has an effect from a view of 46 rem on, where half of it is the usual width - below that the view lays out as it does without; a side column and the narrow view lay out their own columns, and any other value is the usual width. Where
the view itself is narrow (a container query: the pane beside the rail, not the
window) a switch of two buttons shows one of them and keeps the other in the page,
so a switch loses nothing. The preview is a page in an iframe or an element the
caller draws; `reload()` loads the frame once more without touching the settings, and
another address given to it takes the place of the one the frame shows in the history of
the window, so that Back walks through the panel and not through the previews. The container's name, `nino-split`, is part of the contract: a panel's own
stylesheet follows the width of the split view with `@container nino-split (max-width: 40rem)`
and not with a media query. Only
the project's own origin is loaded into the frame - a relative address, or an absolute
one of this origin; `//host`, `\\host`, `javascript:`, `data:` and other hosts are refused,
however they are written - the frame says `aria-busy` until it has loaded, and `sandbox` is the
frame's own attribute where the caller wants one. `tools` is the place in the bar over
the preview for what the panel wants beside its title (the widths of the preview, a
line of advice), `words( { settings, preview } )` says the words of the switch
afterwards without the frame being loaded once more, and with no preview given the view is
the settings alone, with no switch and no bar. A page that is to be seen on a layout width
of its own - 1280, 768 or 390 px - is given `preview : { src, width }`, or `split.width( px )`
later: the frame is laid out as a browser that wide would lay it out and scaled down as a whole
into the room the pane has (`Nino.adminUi.scaleFrame()`), and `split.width( null )` gives it back
the width of the pane.

```js
const settings = document.createElement( 'div' );
settings.appendChild( Nino.adminUi.hint( 'The controls of the panel stand here.' ) );

const split = Nino.adminUi.split( {
	settings : settings,
	preview : { src : '/', title : 'The website' },
	onLoad : function( frame ) { frame.dataset.loaded = 'true' },
} );
split.tools.appendChild( Nino.adminUi.hint( 'Widths and notes of the panel stand in the bar.' ) );
mount.appendChild( split.el );
// after a change the preview has to show: split.reload()
// a phone's width in the room of the pane: split.width( 390 )
```

The **colour field** is a hex code in a text field, the system's colour picker, a
surface that shows the colour and, where `against` names a second colour, the
contrast between them: the ratio as a number cut (not rounded) to two decimals and
whether it reaches `minimum` (4.5 : 1 by default, the level WCAG asks of ordinary
text), said in words and with a mark, never by colour alone. A code is `#rrggbb`
or `#rgb`; it is taken as it is typed and reported through `onChange` as
`#rrggbb`; the field takes what is pasted whole and trims the space round it, so a
code of eight digits or `rgb()` is no colour. An empty field is one with the sentence of
a required field only with `required`, and else no colour and no error. One that is no colour says so at the field (`aria-invalid`, the
sentence pointed at by `aria-describedby`) and goes to `onInvalid` instead. The
pure half is `Nino.adminUi.colorModel` - `parse()`, `luminance()`, `contrast()`. In a section of a form that is wide enough its name stands at the left of the code, as the name of every field does, and the sentence of an error under the code; a field with nothing in it has an empty picker, and what the code is described by besides the block's own sentences (its help behind the name) stays.

```js
const brand = Nino.adminUi.color( {
	label : 'Brand colour', value : '#2563eb', against : '#ffffff',
	onChange : function( value ) { mount.dataset.brand = value },
} );
mount.appendChild( brand.el );
// the colour it is measured against moved: brand.against( '#111111' )
```

### Three traps

- **Never assign `className` on an element that carries a design-system class.**
  The shells carry `.nino-admin` plus their `show-<panel>` state, so switching
  panels with `el.className = 'show-x'` silently deletes the entire shared
  layout. Use `Nino.adminUi.setStateClass()`, or `classList` for anything else.
- **A panel stylesheet must scope every `nino-admin-*` rule to its own root**
  (`#pd-app .nino-admin-btn-primary`, not `.nino-admin-btn-primary`) - see the
  bundling note above.
- **Restating a shared component in a panel is not "refining" it.** A panel copy
  sits in the later `nino.tool` layer, so it wins over the design system whole
  and the two drift apart silently. The old editor carried a second copy of every
  token, button, input, field, tile, card and rich-text rule this way. Delete
  the copy; if the shared version is genuinely wrong for every screen, fix it in
  the design-system half of `style.css`.

### A work surface

A screen that edits a draft with a preview and saves it as a whole is a **work surface**: no head, one context bar, the
split view over the whole height, the bar at the foot. Three blocks make it.

`Nino.adminUi.split( { fill : true, ... } )` is the surface. It stands directly under the context bar and reaches down to the
bar at the foot - no gap, no frame, no bar over the preview - and the page does not scroll: the settings, the outline and the
preview each have a scroll bar of their own (the preview's is the one of its page). The pane that holds a surface that is on the
screen is marked `.nino-admin-pane--surface` by the script, and the stylesheet takes its padding and its scroll; a surface in a
level that is hidden does not mark it. A view without `fill` is the block in the page it always was. Below 44 rem of the view's own
width the switch "Settings | Preview" is drawn, as in the other form.

`settings` may be left out: with `aside` the view is the outline and the preview - two columns, the preview taking all the rest, the switch of a narrow view named by `aside.label` - and without it the preview alone. `aside : { content, label, collapsed, rail }` adds the third column at the left edge. `content` is what it holds when it is open,
`rail` what stands in the strip of 48 px when it is folded, `label` names the button at its top (the list icon and the double arrow
of the rail's button, `aria-expanded`, the focus stays on it), `collapsed` where it starts folded when the session holds nothing. A
view with an `id` keeps whether its column is folded for the session. `split.aside` is `{ el, body, rail, toggle, collapsed(), collapse( on ) }`.

`Nino.adminUi.previewTools( { pages, page, widths, width, icons, text, onPage, onWidth, onReload } )` is the group of the tools of the
preview: a select named "Shows" for the page (drawn where `pages` is a list, `[ { value, label } ]`), the widths as one strip of
steps (`mobile`, `tablet`, `desktop` unless `widths` names others; `icons : true` draws them as a phone, a tablet and a monitor - the word stays the name of each step and its tooltip), a quiet line beside the choice (`note`, `setNote()`) and Reload as an icon button (`reload : false` leaves it out where the view has one). It reports what a hand did; `setPage()`
and `setWidth()` move it from outside and report nothing. `contextBar( { tools : tools.el } )` puts it at the right of the context bar;
where the bar is narrower than 56 rem (its own width) the group is a menu behind one icon button "Tools" - `aria-expanded`, Esc and a
click beside it close it. The context bar also takes `code`, a quiet identifier after the title, and `icons`, icon buttons after that
(`[ { icon, name, onClick } ]`, made by `iconButton()`).

`previewFrame( { split, send, address, mark : 'loose' } )` marks the page in the frame with `data-nino-preview="loose"` on its
`<html>`: the address it loads asks for the mark (`\Nino\Preview::MARK`), the kernel writes it from the first byte of a page of a request that holds a draft - never of a public one - and the script writes it again after every page the frame loads: the header of Basis that stays at the top of a page is static under that mark, so it
takes no room of the page that is worked on. A style sheet of a theme or a feature may ask for the same mark.

```js
const tools = Nino.adminUi.previewTools( {
	pages : [ { value : '/', label : 'Home' }, { value : '/contact', label : 'Contact' } ], page : '/', width : 'desktop',
	onPage : function( page ) { split.reload( page ) },
	onWidth : function( name ) { preview.width( name ) },
	onReload : function() { split.reload() },
} );
const split = Nino.adminUi.split( {
	id : 'notes-split', fill : true, settings : settings, preview : { src : '/', title : 'The page' },
	aside : { content : outline, label : 'Outline', rail : icons },
} );
const preview = Nino.adminUi.previewFrame( { split : split, send : send, address : address, mark : 'loose' } );

level.appendChild( Nino.adminUi.contextBar( {
	panel : 'Notes', title : note.name, code : note.slug, back : { to : 'Notes', onClick : leave },
	icons : [ { icon : 'panels-top-left', name : 'Edit the frame', onClick : openFrame } ], tools : tools.el, within : mount,
} ) );
level.appendChild( split.el );
level.appendChild( bar.el );           // saveBar()
```

**`Nino.adminUi.cards( items, options )`** draws the entries whose look is the content - a theme, a block: `items` are
`{ key, title, picture, badges, actions }`, the picture an element (`scaleFrame()` makes one from a page), the badges short words
(a string or `{ text, state }`), the actions at most three small buttons with short words, which share the width of the card. The name and the badges stand in one line that does not wrap
(a name that is too long is cut and whole in its title), the buttons in one line under it, every card as high as the highest.

### Workbench screens that save as a whole: the preview loop, the conflict and the save bar

Three more builders, for a panel that edits a draft, shows it in a split view and saves it in one step - the
Builder and the Themes panel. They own no strings of their own beyond the words of the question and of the bar
(`/_admin/common/conflict/*`, `/_admin/common/savebar/*`, and `/_admin/common/label/cancel`), and a caller that
says its own words passes `text`. In the examples `mount` is the element a panel draws into, `settings` the controls of
the split view and `api( action, data, done )` the panel's own request to its action.

**`Nino.adminUi.previewFrame( options )`** is the loop that keeps the preview in step with the draft. The panel
says what changed with `schedule()` and the loop sends the draft when the hand has rested (150 ms), one request at
a time: what changes while a request is out is sent in the next, once, and the frame is loaded only with the last
answer. `flush()` sends at once and takes back the wait that `schedule()` had out, so a change and an at once are one request. The answer of `send` is `{ token, query, ttl }` - the token of a [draft](../development.md#a-draft-for-a-preview),
the query that makes a request for the page ask for it and the seconds it holds. The token goes with the next
`send`, so that the server renews it, and is asked for again 5 seconds before it runs out (`grace`). A failed
request, an answer that is no draft and a renewal are told to the panel (`onError`, `onUnavailable`, `onRenew`), and
the frame stays as it was. `width( 'mobile' | 'tablet' | 'desktop' )` lays the page out at 390, 768 or 1280 px in
the split view; `stop()` is for when the panel is left.

```js
const split = Nino.adminUi.split( { settings : settings, preview : { src : '/', title : 'The page' } } );
const preview = Nino.adminUi.previewFrame( {
	split : split,
	send : function( token, done ) { api( 'preview', { draft : draft, token : token }, done ) },
	address : function( query ) { return '/page?'+ query },
	onError : function( status, response ) { bar.line.error( status, response ) },
} );
// every change of the draft: preview.schedule()
// a change that has to be seen now, and then something: preview.flush( function( ok ) { ... } )
```

**`Nino.adminUi.conflict( options )`** is the question a refused save asks - the stamp the save carried is not the
one the server holds, because another tab or another person wrote the same thing. Overwrite is the dangerous answer
and calls `onOverwrite`, which sends the save again with the stamp the refusal was about; Reload calls `onReload`
at once - the question says in a sentence under its message that it drops what the person did, and a second
question after the answer would ask the same thing twice; Cancel and the Escape key call `onCancel`. It is a
`choiceDialog()`, so the keyboard, the focus (back to `opener`) and the screen reader are its own.

```js
function saved( status, response, send ) {
	if( status === 409 )
		Nino.adminUi.conflict( {
			message : Nino.adminUi.api.errorText( status, response ),
			onOverwrite : function() { send( { force : true } ) },
			onReload : function() { load() },
			onCancel : function() { bar.line.dirty() },
			opener : bar.save,
		} );
}
```

**`Nino.adminUi.saveBar( options )`** is the bar at the foot of the screen: the state ("● Changed" or "✓ Saved"),
the status line, the buttons of the panel (undo, redo - `buttons`), "Restore the last state" and Save. The panel says
what is the case with `set( { dirty, blocked, restorable } )` and the bar draws it: Save is on where there is a
change and nothing keeps it back, `blocked` is a sentence the line says as an error while it holds, and Restore is
on the screen once there is a last state and waits while changes are unsaved. Save is pressed once at a time; the
panel's `onSave( done )` - the line says "Saving" only when it has been out for 0.4 seconds, so a question the panel asks first is no "Saving" - ends with `done( true )` or `done( false )` on every way it can end - a failure says
itself on `bar.line` before it ends the save, and where it says nothing the line is back to "unsaved changes" -, and what
was changed while it was on its way stays a change. The first change of a saved screen is said once on the line, a status a
screen reader reads out: the sentence is kept off the screen - the badge shows the state - and goes after four seconds, and what the
panel says itself (an error, a reason in `blocked`, words of its own) stays on the line until the panel changes it; `calm` is a third, quiet state with a word of its own ("Applied"). `entry()` is the entry for the shell's registry of unsaved input
(`Nino.admin.dirty.register()`), so that Ctrl+S, leaving the panel and the question that is asked then are the
bar's.

```js
const bar = Nino.adminUi.saveBar( {
	id : 'pd-bar',
	buttons : [ { key : 'undo', label : '\u21B6', name : 'Undo', onPress : undo } ],
	onSave : function( done ) {
		api( 'save', draft, function( status, response ) {
			if( status !== 200 )
				bar.line.error( status, response );
			done( status === 200 );
		} );
	},
	onRestore : function( opener ) { restore( opener ) },
} );
mount.appendChild( bar.el );
Nino.admin.dirty.register( 'pd', bar.entry( { discard : drop } ) );
// after every change: bar.set( { dirty : draft !== saved, restorable : hasLast } )
// a screen whose saved state is another word: bar.set( { calm : 'Applied' } ) - shown with a check mark while nothing is changed
```

Each has a suite - `tests/nino-ui-previewframe-js-smoke.js`, `tests/nino-ui-conflict-js-smoke.js` and
`tests/nino-ui-savebar-js-smoke.js` - and they are drawn in the browser by the same stylesheet as the other builders
(`.nino-admin-savebar` is an action bar, and on a phone its line, while it holds words, is one row above, and the badge at the left and the compact buttons (30 px, 12 px words) at the right share the rows under it: two rows for the badge and three or four buttons).

### A tree

**`Nino.adminUi.tree( options )`** shows entries the caller gives as data, each with its children, as the
ARIA pattern of the same name: `role="tree"`, `treeitem` and `group`, `aria-level`, `aria-expanded` on the
entries that have children, `aria-selected`, and exactly one entry in the tab order. It is the one drawing of
every tree of the workbench - a panel that has entries one under another with children uses it and draws no
list of its own. An entry is

```
{ id, kind, name, detail, label, icon, meta, children, open, marked, locked, hint, loop, source, actions }
```

- `id` is a string, unique in the tree, and follows the component, not its place: a panel that takes the path of
  a node as its id loses the open state and the focus of a node that moved.
- `kind` is the bold word - "Section", "Row", "Title" -, `name` the content, quietly after it, `detail` a second
  quiet note; `label` is the word of an entry that has no `kind`, and `label` with `detail` is how a
  plain entry is given. Labels, names and hints are set as text. A letter key goes to the next entry whose kind
  begins with it.
- `icon` is the Lucide name of its type icon, drawn before the kind; an entry that names none or one the set has not
  has the neutral square. A component names its own in the schema (`icon`, see the
  [component recipe](component.md)), so a panel passes `schema.icon` through.
- `meta` is a quiet note at the right - the width of a column, a variant - that gives way to the buttons.
- `marked` says that the entry is the one shown elsewhere (the preview), `locked` that it can be walked to and
  opened but not chosen. A marked and a locked entry carry a quiet word at the end of the row - the locked one
  with a lock - never a sign alone.
- `hint` (or `finding`, the same) is `{ text, state }` or the sentence alone: a line of its own under the row, with
  a warning sign, `state` warn, error or muted, read by a screen reader as part of the entry's description.
- `loop` makes the entry a loop - `true`, the quiet note as a string, or `{ detail, type }`: the entry carries the loop
  icon and its note, and its children are the pattern of what is repeated "per element": the guide line of the
  pattern is dashed and in the accent colour and a tag over the first child says so. Where a loop stands inside the
  pattern of another, its tag names `type`, the type of its elements, so that two tags one under another are told apart.
- `source` is the field an entry reads, `'.title'` or `{ field, label }` (a word before the field, "Target"): a tag with the
  database icon and the name of the field, which the row never cuts: the content gives way first, then the kind - each ends in an ellipsis and keeps its full text as a title - and where the tag does not fit beside them it goes on a second line of the row. A screen reader reads the kind and then the content, or the field ("Text Field .teaser"), as the name of the row; the note, the state and the hint are its description, and the pattern of a loop is a group named "pattern, repeated for every element".
- `actions` are the buttons of the row, `{ key, icon, name, disabled, danger }`: each is an `iconButton()` of 24 px, `icon` the
  Lucide name (`plus`, `arrow-up`, `arrow-down`, `trash-2`), `name` what a screen reader and a title say, `danger`
  drawing the one that destroys in red. The glyphs `+`, `↑`, `↓` and `×` as `label` stand for those four icons where there is
  no `icon`; a button with neither has its `label` as its word.

The buttons of a row are drawn only where the pointer is over it (where there is a pointer), on the row that has the stop while
the focus is anywhere in the tree - so Tab from any row goes to its first button, then through the rest, then out of the tree, and Shift+Tab
back - and on the chosen row; on a touch screen on the chosen row alone. The chosen row has a tint with a hairline of
the accent colour round it and no shadow, and its kind is heavier, in the text colour. Guide lines run down the left
of every entry that holds others, with a branch to each child; the last child's line ends at its branch.

Up and Down walk the entries that can be seen, Right opens a closed entry and goes to the first child of
an open one, Left closes an open entry and goes to the parent of a closed one, Home and End go to the ends,
Enter and Space choose (also the entry that is chosen already: in a builder that is the way into it). A click on the
arrow opens or closes, a click on the row chooses. The buttons of a row are in the tab order only while its row is the
one with the stop, so that Tab from the row reaches them and a tree of fifty rows is one stop; the mouse reaches every one.
A locked entry carries no `aria-disabled` (that would take its buttons from a screen reader too) and
`select()` refuses it. The tree writes no address - the caller binds the chosen entry to its own, as with `levels()`: `onSelect`
is told the id and the item, `onToggle` the id and whether it is open after the move, `onAction` the key, the id and
the item, each only after a hand did it; `onFocus` is told the id and the item when the stop moves to another entry.
`update( items )` draws new data and keeps, where the ids stay, what is open (an id the tree has not seen takes the
item's `open`), what is chosen, the stop and the focus - on the button of the row where it stood on one, if that is still
there and can be pressed, else on the row. Where the entry of the focus is gone, the focus goes to its next sibling,
else the previous one, else the parent; `update( [] )` leaves it nowhere. `select( id )` chooses an entry, opens what is above
it and scrolls it into view, `focus( [id], [key] )` puts the focus on it - on its button with that key, where there is one that can be pressed -, `get()` answers `{ selected, stop, open }`;
none of them calls back, and they answer `false` for an id the tree has not. After an action the panel draws the tree again with `update()` and the focus stays where a person expects it: on the same button of the row that moved, on the next row (else the one before, else the parent) of one that was removed; for a row the action added the panel asks `tree.focus( newId )` - before or after the `update()` that draws it: asked for an id that is not there yet, the tree remembers it for the next `update()` and puts the focus there once.

```js
const tree = Nino.adminUi.tree( {
	label : 'Components', selected : 'title', path : true,
	items : [
		{ id : 'wrap', kind : 'Section', icon : 'panel-top', meta : 'Regular', open : true, actions : [ { key : 'add', icon : 'plus', name : 'Add inside Section' } ], children : [
			{ id : 'title', kind : 'Title', icon : 'heading', name : 'Three good reasons', meta : 'H2', actions : [ { key : 'up', icon : 'arrow-up', name : 'Move Title up', disabled : true }, { key : 'remove', icon : 'trash-2', name : 'Remove Title', danger : true } ] },
			{ id : 'html', kind : 'Free HTML', icon : 'code', locked : true, hint : 'Not editable here' },
			{ id : 'team', kind : 'Collection', loop : { detail : 'Team - 3 elements', type : 'Member' }, open : true, children : [
				{ id : 'name', kind : 'Title', icon : 'heading', source : '.title' },
			] },
		] },
	],
	onSelect : function( id ) { Nino.admin.router.set( 'builder', [ 'page', id ] ) },
	onAction : function( key, id ) { if( key === 'remove' ) remove( id ) },
} );
mount.appendChild( tree.path );
mount.appendChild( tree.el );
// after a change: tree.update( itemsNow ); after Back: tree.select( idOfTheAddress )
```

**The path.** With `path : true` the tree makes `tree.path` (otherwise `null`), a line that names the way to the chosen entry,
"Section > Row > Column > Title": the kinds from the top to the chosen entry, each one above a button that chooses its
entry and takes the focus into the tree, the chosen one as the place (`aria-current`), a loop with its word. It is
a `nav` of its own, since a list holds no line: the caller puts it over `tree.el` in the element that scrolls, where it stays
at the top. It is one line - a long way scrolls inside it, the end, the place, in sight - and hidden while nothing is chosen.

**Pointed at, in every view.** `onHover( id )` is told when the pointer or the focus comes over an entry - and `null`
when it leaves them all - once for each change; a redrawing that leaves them where they were tells nobody.
`tree.highlight( id | ids | null, [{ scroll }] )` marks entries the way the pointer does - the tint, not the buttons - from
outside, without moving the focus and without telling anybody; it opens what is above the first and rolls it into
view where it lies outside the window of the tree (`scrollIntoView( { block : 'nearest' } )`), and `scroll : false` leaves
the tree where it is. What is marked outlives `update()`. The ids are the keys of the sync. A marked row has the accent inside it - the tint and a line of
2 px, 3 : 1 or more against the ground in both colour schemes - and the chosen row keeps its hairline and its heavier name, so the two stay apart; a button
with the class `is-lit` in the strip that `split( { aside : { rail } } )` draws is marked in the same colours.

`Nino.adminUi.hoverSync()` is the switchboard between views that show the same things: `join( on )` gives a
member `{ set( key ), leave() }`, and `on( key )` is called with what another member reported - a string, a list of
them where one thing is drawn more than once (the pattern of a loop in a preview), or `null`. A member never hears its own
report, a key reported again tells nobody, `null` takes the key away only from the member that reported it (a view that has pointed at nothing cannot clear what another is pointing at: what is reported last is shown), and `leave()` takes the member and its key away. The tree reports what moved last, the pointer or the focus: when the pointer has gone it reports nothing, the focus that stands in the tree does not speak for it. The preview side is
`previewFrame().marks( { elements, keyAt, onHover } )` (or `Nino.adminUi.frameMarks( { frame, ... } )`):
`elements( key )` answers the elements of the page in the frame that stand for the key, `keyAt( element )` the key of what an
element is part of; it draws a box of the accent colour round every element of the marked keys, in a shadow root of the
page that takes no pointer and stands fixed in the viewport - a header that sticks keeps its box, a moved body moves none, the boxes follow the scroll -, tells the pointer over the page through `onHover`, and comes back with each page the frame
loads. Outline, preview and tree are wired alike:

```js
const board = Nino.adminUi.hoverSync();
const asTree = board.join( function( key ) { tree.highlight( key ) } );
const marks = preview.marks( { elements : elementsOfKey, keyAt : keyOfElement, onHover : function( key ) { asPreview.set( key ) } } );
const asPreview = board.join( function( key ) { marks.highlight( key ) } );
// tree: onHover : function( id ) { asTree.set( id ) }
```

The rows are 34 px high with arrows of 24 px and buttons of 24 px, the indent is `--nino-admin-tree-indent`, a long kind or
content is cut with an ellipsis and keeps its text as a title, and nothing overflows at 390 px. It is drawn by the stylesheet in both
schemes (`tests/nino-ui-tree-js-smoke.js` holds the keys, the ARIA, the drawing, the path and the sync; `tests/nino-ui-previewframe-js-smoke.js`
the marks of the frame).

## Panel tests

Backend tests belong in `tests/admin-smoke.php` (content panels, accounts)
or in the `tests/admin-*-smoke.php` suite of the panel's area:
`admin-system-smoke.php` (recovery, backups, config, languages, users),
`admin-shell-smoke.php` (the registry contract, scoped permissions, failure
codes), `admin-elements-smoke.php`, `admin-text-smoke.php` and
`admin-pages-smoke.php`. They MUST exercise:

- unauthenticated rejection (`401`, with the code `session`) and a missing
  permission (`403`, without one),
- the code, params and field of every failure a person can cause - and that
  each code has text in both languages,
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
for test in tests/admin-*-smoke.php; do php "$test" || exit 1; done
node tests/admin-catalog-js-smoke.js
node tests/admin-lists-js-smoke.js
```

Use the actual test filename created by the change.
