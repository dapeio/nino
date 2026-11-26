<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Users\Roles	User roles tab: named sets of permissions
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Users {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Roles							A role is a named set of permissions under
	 *											'/nino/auth/roles', and an account holds one of them (see
	 *											\Nino\Auth::permissions()). This tab of the Users pane
	 *											creates, edits and deletes them: full access as a switch,
	 *											and below it the permissions in the shared picker, grouped
	 *											the way the navigation is - one per panel or tab that
	 *											exists right now (see \Nino\Modules\Users\Admin::permOptions()).
	 *											The scoped permissions (see \Nino\Admin\Admin::scoped() -
	 *											one per action, per field and per text key) are added
	 *											with three dependent lists - area, action, field - from
	 *											the tree the panels answer in scopes() (see
	 *											\Nino\Modules\Users\Admin::scopeOptions()), and the form
	 *											says in plain words what the role may do. The two roles every project starts
	 *											with, Editor and Developer, are written by the wizard's
	 *											Setup step from defaults() and are ordinary roles from
	 *											then on.
	 *
	 *											Manager-only, under the same permission as the accounts
	 *											themselves: whoever may hand out a role may say what it
	 *											grants - of a role that holds nothing beyond their own
	 *											account, since one that does is not theirs to change (see
	 *											beyond()). Two changes are refused outright rather than
	 *											guarded against afterwards - one that would take the
	 *											manage permission from the account making it, and one
	 *											that would leave no account with full access, since a
	 *											project without one has only recovery.php to get back in
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Roles {

		public const string MANAGE_PERM = \Nino\Modules\Users\Admin::MANAGE_PERM;

		// A role id is a slug: it is a key of config.php, an <option> value
		// and part of a hash, and nothing else should ever be one
		private const string ID_PATTERN = '/^[a-z][a-z0-9-]{0,39}$/';
		private const int MAX_LABEL_LENGTH = 60;

		/**
		 *	What a permission string may look like: slash-separated segments of
		 *	letters, digits, '_', '-' and '.', with '*' allowed as a whole
		 *	segment - the wildcard \Nino\Auth::checkPermission() reads as "and
		 *	everything below".
		 *
		 *	This is a shape, not a list. It used to be a list: the permissions
		 *	every panel offers, and no others. That worked while a permission
		 *	was one string per panel and tab, and stopped working with the
		 *	scoped ones (see \Nino\Admin\Admin::scoped()) - one per action, per
		 *	field, per text key. There is no finite list of them, so this is
		 *	what a permission is checked against, and what the panels' own
		 *	scopes() are kept to (see isPermShape()).
		 *
		 *	No permission is gained by the change: reaching this endpoint at all
		 *	means holding MANAGE_PERM, and the same request may set '/*'. What
		 *	the shape check is for is the typo - a string no check could ever
		 *	match, written into config.php as if it granted something
		 */
		private const string PERM_PATTERN = '#^/([A-Za-z0-9_.-]+|\*)(/([A-Za-z0-9_.-]+|\*))*$#';
		private const int MAX_PERM_LENGTH = 200;

		/**
		 *	Whether a string is shaped like a permission: PERM_PATTERN and no
		 *	longer than MAX_PERM_LENGTH - what apiSave() accepts, and what the
		 *	scoped permissions a panel offers (see \Nino\Modules\Users\Admin::scopeOptions())
		 *	are kept to
		 *
		 *	@param		string		$perm
		 *
		 *	@return 	bool
		 */
		public static function isPermShape( string $perm ): bool {
			return strlen( $perm ) <= self::MAX_PERM_LENGTH && preg_match( self::PERM_PATTERN, $perm ) === 1;
		}

		public static function actions(): array {
			return [
				'roles/list' 		=> [ self::class, 'apiList' ],
				'roles/save' 		=> [ self::class, 'apiSave' ],
				'roles/delete' 	=> [ self::class, 'apiDelete' ],
			];
		}

		// A tab of the Users pane (see \Nino\Modules\Users\Admin::tabs()) - the group only says
		// where its permission is listed
		public static function nav(): array {
			return [ 'roles', '/_admin/nav/roles', 10, 'system' ];
		}

		public static function perm(): string {
			return self::MANAGE_PERM;
		}

		public static function panes(): array {
			return [ 'roles-list', 'roles-form' ];
		}

		public static function assets(): array {
			return [ \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/roles.js' ) ];
		}

		// A tab's words are the module's words - the same text/ its panel
		// names, said again here so the tab describes itself
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'roles/save' 		=> 'Edit Role '. \Nino\Admin\Admin::postString( $data, 'id' ),
				'roles/delete' 	=> 'Delete Role '. \Nino\Admin\Admin::postString( $data, 'id' ),
				default 				=> '',
			};
		}

		/**
		 *	Every role as stored, reduced to the shape the rest of the tool
		 *	relies on: a label and a list of permission strings
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ id => [ label, perms ] ]
		 */
		public static function all( array &$appData ): array {

			$roles = [];

			foreach( $appData['/nino/auth/roles'] ?? [] as $id => $role ) {

				if( is_string( $id ) === false || is_array( $role ) === false )
					continue;

				$roles[$id] = [
					'label' => (string) ( $role['label'] ?? $id ),
					'perms' => array_values( array_filter( (array) ( $role['perms'] ?? [] ), 'is_string' ) ),
				];
			}

			return $roles;
		}

		/**
		 *	The two roles a project starts with: an editor holds every content
		 *	panel's permission and nothing else, a developer holds full
		 *	access. Read off the registry, so a module's content panel is an
		 *	editor's the moment the wizard writes them (see
		 *	\Nino\Install\Setup::apiApply()); from then on they are ordinary
		 *	roles a manager may change
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ id => [ label, perms ] ]
		 */
		public static function defaults( array &$appData ): array {

			$content = [];
			foreach( \Nino\Admin\Admin::allPanels( $appData ) as $panel )
				if( $panel['perm'] !== '' && $panel['group'] === 'content' )
					$content[] = $panel['perm'];

			return [
				'editor' 		=> [ 'label' => 'Editor', 		'perms' => array_values( array_unique( $content ) ) ],
				'developer' => [ 'label' => 'Developer', 'perms' => [ '/*' ] ],
			];
		}

		/**
		 *	How many accounts hold a role - what decides whether it may go
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id
		 *
		 *	@return 	int
		 */
		public static function holders( array &$appData, string $id ): int {

			$count = 0;
			foreach( $appData['/nino/auth/user'] ?? [] as $user )
				if( is_array( $user ) === true && (string) ( $user['role'] ?? '' ) === $id )
					$count++;

			return $count;
		}

		/**
		 *	Whether any account that can log in holds full access, its own or
		 *	its role's - the state a delete, a deactivation or a role change
		 *	must never end (see \Nino\Modules\Users\Admin::apiDelete(),
		 *	apiStatus(), apiSetRole() and apiSave() here). A disabled account
		 *	holds it on paper only and does not count: the last full access
		 *	there is, is the last active one
		 *
		 *	@param		array 		$appData			App data, or a copy with the change applied
		 *	@param		string		$except				An account to leave out
		 *
		 *	@return 	bool
		 */
		public static function fullAccessExists( array $appData, string $except = '' ): bool {

			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user )
				if( $mail !== $except && is_array( $user ) === true && ( $user['status'] ?? 0 ) === \Nino\Auth::STATUS_ACTIVE && in_array( '/*', \Nino\Auth::permissions( $appData, $user ), true ) === true )
					return true;

			return false;
		}

		/**
		 *	The first thing in these permissions the signed-in account may not
		 *	hand out, or '' when there is none. Granting is bounded by holding:
		 *	a role may not be given a permission its author lacks (apiSave()),
		 *	an account may not be handed a role wider than the one handing it
		 *	out (\Nino\Modules\Users\Admin::apiCreate(), apiSetRole()), and a
		 *	password may not be set on an account wider than the one setting
		 *	it (\Nino\Modules\Users\Admin::apiSave()). Without all three, an
		 *	account that may manage users writes a full-access role, creates an
		 *	account holding it with a password of its choosing - or gives one to
		 *	an account that already holds it - and signs in as that: "manage
		 *	users" would silently mean "everything".
		 *
		 *	Two things are measured. Every permission, with the same
		 *	\Nino\Auth::checkPermission() every action uses, so '/*' alone
		 *	covers '/*', and a blanket covers what is under it. And the
		 *	panels that describe themselves in detail (see
		 *	\Nino\Admin\Admin::scoped()): holding fewer permissions is not
		 *	always holding less, because a panel permission with no scoped one
		 *	beside it allows the whole panel, and one with a scoped one allows
		 *	only what that names. So a set that opens a panel without
		 *	describing it in detail may not be handed out by an account that
		 *	does not hold the whole panel - one without its door, or described
		 *	in detail for it - every permission of the set
		 *	could be held by the account and the set would still allow what
		 *	the account is not allowed. The panels are those
		 *	\Nino\Modules\Users\Admin::scopeOptions() lists, the very prefixes
		 *	the guards read, a panel with nothing to offer right now included.
		 *
		 *	With $stored, the set is a change of a set that exists: only what it
		 *	gains is measured per permission, and a panel only when the set
		 *	becomes wide for it - a role that already was wide may be edited by
		 *	an account that could not have written it, as long as nothing
		 *	widens
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$perms				Permission strings
		 *	@param		array|null	$stored				The set these replace, null for one that comes into being
		 *
		 *	@return 	string										The first permission not held, or the scope (ends with '/') of the first panel the set widens, else ''
		 */
		public static function notHeld( array &$appData, array $perms, ?array $stored = null ): string {

			foreach( $stored === null ? $perms : array_diff( $perms, $stored ) as $perm )
				if( is_string( $perm ) === true && \Nino\Auth::checkPermission( $appData, $perm ) === false )
					return $perm;

			if( $perms === [] )
				return '';

			foreach( \Nino\Modules\Users\Admin::scopeOptions( $appData, true ) as $scope ) {

				if( self::_isWide( $perms, $scope ) === false || ( $stored !== null && self::_isWide( $stored, $scope ) === true ) )
					continue;

				// A set that allows the whole panel needs an account that holds
				// the whole panel: a blanket, or the door with no scoped entry.
				// One without the door, or described in detail, does not
				if( \Nino\Auth::checkPermission( $appData, $scope['scope']. '*' ) === false
					&& ( \Nino\Auth::checkPermission( $appData, $scope['door'] ) === false || \Nino\Admin\Admin::isScoped( $appData, $scope['scope'] ) === true ) )
					return $scope['scope'];
			}

			return '';
		}

		/**
		 *	Whether a set opens a panel without describing it in detail - and so
		 *	allows all of it (see notHeld())
		 *
		 *	@param		array 		$perms				Permission strings
		 *	@param		array			$scope				One of \Nino\Modules\Users\Admin::scopeOptions()
		 *
		 *	@return 	bool
		 */
		private static function _isWide( array $perms, array $scope ): bool {
			return \Nino\Auth::holds( $perms, $scope['door'] ) === true && \Nino\Admin\Admin::isDescribed( $perms, $scope['scope'] ) === false;
		}

		/**
		 *	Whether the signed-in account may change a role that exists - rename
		 *	it, change what it grants, empty it, delete it: the same bound as
		 *	every other change of a set of permissions (see notHeld()), asked of
		 *	the set the role holds now. A role that holds what the account does
		 *	not is not the account's to change, however little the change itself
		 *	grants - nor is one that an account beyond it holds (heldBeyond())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id						The role
		 *
		 *	@return 	string										What the account does not hold of it, as notHeld() says; '' when it may change the role or there is no such role
		 */
		public static function beyond( array &$appData, string $id ): string {

			$role = self::all( $appData )[$id] ?? null;

			if( $role === null )
				return '';

			return self::notHeld( $appData, $role['perms'] );
		}

		/**
		 *	What the signed-in account does not hold of the accounts that hold a
		 *	role: an account holds more than its role where its own permissions add
		 *	to it, and changing the role changes that account. '' where every
		 *	holder is within the account, or nobody holds the role
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$id						The role
		 *
		 *	@return 	string										What the account does not hold of the first holder beyond it, as notHeld() says
		 */
		public static function heldBeyond( array &$appData, string $id ): string {

			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user ) {

				if( is_array( $user ) === false || (string) ( $user['role'] ?? '' ) !== $id )
					continue;

				$missing = \Nino\Modules\Users\Admin::beyond( $appData, (string) $mail );

				if( $missing !== '' )
					return $missing;
			}

			return '';
		}

		/**
		 *	Answer 403 when a role is not the signed-in account's to change: it
		 *	holds what the account does not (beyond()), or an account beyond the
		 *	account holds it (heldBeyond()) - each with the code that says which
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$id						The role
		 *
		 *	@return 	bool											True when the change is refused and the response set
		 */
		public static function refuseChange( array &$appData, array &$request, string $id ): bool {

			return self::refuseBeyond( $appData, $request, self::beyond( $appData, $id ), 'role' ) === true
				|| self::refuseBeyond( $appData, $request, self::heldBeyond( $appData, $id ), 'holder' ) === true;
		}

		/**
		 *	Answer 403 when what an account or a role holds lies beyond the
		 *	signed-in account (see beyond() and \Nino\Modules\Users\Admin::beyond()):
		 *	the one refusal every action that changes an account or a role
		 *	gives, with the permission or the panel it names
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$missing			What beyond() answered
		 *	@param		string		$kind					'account', 'role' or 'holder' - a role that an account beyond the signed-in one holds
		 *
		 *	@return 	bool											True when the change is refused and the response set
		 */
		public static function refuseBeyond( array &$appData, array &$request, string $missing, string $kind ): bool {

			if( $missing === '' )
				return false;

			if( $kind === 'holder' )
				\Nino\Http::fail( $request, 403, 'cannot change a role that an account holds that holds a permission your own account does not: '. $missing, self::isScope( $missing ) === true ? 'roles_role_held_beyond_scoped' : 'roles_role_held_beyond', [ $missing ] );
			elseif( $kind === 'role' )
				\Nino\Http::fail( $request, 403, 'cannot change a role that holds a permission your own account does not: '. $missing, self::isScope( $missing ) === true ? 'roles_role_beyond_scoped' : 'roles_role_beyond', [ $missing ] );
			else
				\Nino\Http::fail( $request, 403, 'cannot change an account that holds a permission your own account does not: '. $missing, self::isScope( $missing ) === true ? 'users_account_beyond_scoped' : 'users_account_beyond', [ $missing ] );

			return true;
		}

		/**
		 *	Whether what notHeld() answered is a panel rather than a permission -
		 *	a scope ends with a slash, which no permission does (PERM_PATTERN) -
		 *	for a caller whose refusal has words of its own for it
		 *
		 *	@param		string		$missing			What notHeld() answered
		 *
		 *	@return 	bool
		 */
		public static function isScope( string $missing ): bool {
			return str_ends_with( $missing, '/' );
		}

		/**
		 *	Every role, how many accounts hold each, the permissions the picker
		 *	offers (see \Nino\Modules\Users\Admin::permOptions() - what a role may
		 *	hold is wider than that, see apiSave()), the order it lists their
		 *	groups in (the rail's, then 'other') and the scoped permissions
		 *	the panels list for the three selects (see
		 *	\Nino\Modules\Users\Admin::scopeOptions())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$roles = [];
			foreach( self::all( $appData ) as $id => $role )
				$roles[] = [ 'id' => $id, 'label' => $role['label'], 'perms' => $role['perms'], 'users' => self::holders( $appData, $id ), 'canChange' => self::beyond( $appData, $id ) === '' && self::heldBeyond( $appData, $id ) === '', 'heldBeyond' => self::beyond( $appData, $id ) === '' && self::heldBeyond( $appData, $id ) !== '' ];

			\Nino\Http::ok( $request, [
				'roles' 				=> $roles,
				'permOptions' => \Nino\Modules\Users\Admin::permOptions( $appData ),
				// The rail's groups in its order, then 'other' - the group
				// \Nino\Modules\Users\Admin::permOptions() files a permission
				// no panel offers under, and the name it alone gives
				'groups' 			=> [ ...\Nino\Admin\Panels::GROUPS, 'other' ],
				'scopes' 			=> \Nino\Modules\Users\Admin::scopeOptions( $appData ),
			] );
		}

		/**
		 *	Create or change a role (with `create` true: create only, an id that is taken is a 409). Every permission has to be shaped like one
		 *	(PERM_PATTERN) and is refused by name otherwise, so a typo cannot
		 *	end up in config.php looking like a grant; '/*' stands alone, since
		 *	it covers everything else already. Tried on a copy first, for the
		 *	two refusals the class docblock names. Decided against the roles
		 *	config.php holds, under its lock, and only this role is set in them
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$id 		= \Nino\Admin\Admin::postString( $data, 'id' );
			$label 	= trim( \Nino\Admin\Admin::postString( $data, 'label' ) );
			$perms 	= $data['perms'] ?? null;

			if( preg_match( self::ID_PATTERN, $id ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'a role id is a slug: lowercase letters, digits and hyphens, starting with a letter', 'roles_invalid_id', [], 'id' );
				return;
			}

			// The form that makes a new role says so: an id that is taken is refused, and the role that holds it stays as it is.
			// A request without the mark creates or changes, as it always did
			if( ( $data['create'] ?? false ) === true && array_key_exists( $id, self::all( $appData ) ) === true ) {
				\Nino\Http::fail( $request, 409, 'a role with this id exists', 'roles_id_taken', [], 'id' );
				return;
			}

			if( $label === '' || mb_strlen( $label ) > self::MAX_LABEL_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'a name of at most '. self::MAX_LABEL_LENGTH. ' characters is required', 'roles_name_required', [ self::MAX_LABEL_LENGTH ], 'label' );
				return;
			}

			if( is_array( $perms ) === false || count( array_filter( $perms, 'is_string' ) ) !== count( $perms ) ) {
				\Nino\Http::fail( $request, 400, 'perms must be an array of strings' );
				return;
			}

			$perms 		= array_values( array_unique( array_map( 'trim', $perms ) ) );
			$malformed = array_values( array_filter( $perms, fn( string $perm ) => self::isPermShape( $perm ) === false ) );

			// Named rather than dropped: a permission silently removed from
			// the request looks exactly like one the save accepted, and the
			// role comes back missing it with nothing to explain why
			if( $malformed !== [] ) {
				\Nino\Http::fail( $request, 400, 'not a permission: '. implode( ', ', array_slice( $malformed, 0, 5 ) ), 'roles_invalid_perm', [ implode( ', ', array_slice( $malformed, 0, 5 ) ) ] );
				return;
			}

			if( in_array( '/*', $perms, true ) === true )
				$perms = [ '/*' ];

			// Read, checked and written under one lock (see lockStored()): what the
			// decisions rest on is what config.php holds - the roles and the accounts - and
			// only the role of this request is set in the copy of the roles that is written
			$fresh = self::lockStored( $appData, $request, self::MANAGE_PERM );

			if( $fresh === null )
				return;

			try {

				// The form that makes a new role says so: an id that is taken is refused
				// against the roles config.php holds, and the role that holds it stays as it is
				if( ( $data['create'] ?? false ) === true && array_key_exists( $id, self::all( $fresh ) ) === true ) {
					\Nino\Http::fail( $request, 409, 'a role with this id exists', 'roles_id_taken', [], 'id' );
					return;
				}

				// A role that holds what the account does not is not its to change -
				// not to rename, not to empty
				if( self::refuseChange( $fresh, $request, $id ) === true )
					return;

				// Only what the role gains is measured against the account making
				// the change (see notHeld()): taking a permission it does not hold
				// away from a role narrows somebody else and widens nobody
				$missing = self::notHeld( $fresh, $perms, self::all( $fresh )[$id]['perms'] ?? [] );

				if( $missing !== '' ) {
					\Nino\Http::fail( $request, 403, 'cannot grant a permission your own account does not hold: '. $missing, self::isScope( $missing ) === true ? 'roles_grant_too_wide_scoped' : 'roles_grant_too_wide', [ $missing ] );
					return;
				}

				$next = $fresh;
				$next['/nino/auth/roles'][$id] = [ 'label' => $label, 'perms' => $perms ];

				if( \Nino\Auth::checkPermission( $next, self::MANAGE_PERM ) === false ) {
					\Nino\Http::fail( $request, 409, 'this change would take the permission to manage users away from your own account', 'roles_locks_self_out' );
					return;
				}

				if( self::fullAccessExists( $fresh ) === true && self::fullAccessExists( $next ) === false ) {
					\Nino\Http::fail( $request, 409, 'this change would leave no account with full access', 'roles_no_full_access' );
					return;
				}

				$appData['/nino/auth/roles'] = $next['/nino/auth/roles'];
				\Nino\AppData::writeContentData( $appData, [ '/nino/auth/roles' ] );

				\Nino\Http::ok( $request, [ 'id' => $id, 'label' => $label, 'perms' => $perms, 'users' => self::holders( $fresh, $id ) ] );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}

		/**
		 *	Take the lock of config.php and read what it holds: the roles and the accounts. The
		 *	roles and the accounts of this request become the stored ones, the baseline the
		 *	accounts are merged against with them (the accounts by \Nino\Auth::lockAccounts()), so that what is written afterwards is what
		 *	config.php holds plus the one change of the action; the answer is a copy of the
		 *	app data, the one a decision about them is made against, while a write goes through $appData
		 *	(accounts are merged, see \Nino\AppData::writeContentData()). Then the signed-in account is asked
		 *	of what config.php holds as well: its record still lists the session of this request
		 *	and it still holds the right of the action. An account that was deleted, switched off, logged out
		 *	everywhere or given another password meanwhile is answered with the 401 and the code 'session' of a request
		 *	without a session, one that lost the right with the 403 of guardPerm() - the lock is let go and nothing
		 *	is written (an ended session is not cleaned up here, the next request of its browser does that). The stored
		 *	record is the signed-in account for what follows. The caller releases the lock with
		 *	\Nino\Filesystem::unlockFile( $appData, '/config.php' )
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$perm					The permission the action asks for, as its guardPerm() does
		 *	@param		string|null	$selfOr				The account the signed-in one may act on without the permission, the action's own account (see \Nino\Modules\Users\Admin::authorize())
		 *
		 *	@return 	array|null								The copy, null when the lock could not be taken or the signed-in account may not go on, and the response says so
		 */
		public static function lockStored( array &$appData, array &$request, string $perm, ?string $selfOr = null ): ?array {

			if( \Nino\Auth::lockAccounts( $appData ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not lock config.php for writing' );
				return null;
			}

			$roles = \Nino\AppData::stored( $appData, '/nino/auth/roles', [] );

			$appData['/nino/auth/roles'] = is_array( $roles ) === true ? $roles : [];

			if( self::_refuseCaller( $appData, $request, $perm, $selfOr ) === true ) {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
				return null;
			}

			return $appData;
		}

		/**
		 *	Whether what config.php holds does not let the signed-in account of this request go on - and if so,
		 *	the response says what. The account becomes the stored record of the account
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data, its accounts and roles the stored ones
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$perm					The permission the action asks for
		 *	@param		string|null	$selfOr				The account the signed-in one may act on without it
		 *
		 *	@return 	bool										True when the action is refused and the response set
		 */
		private static function _refuseCaller( array &$appData, array &$request, string $perm, ?string $selfOr ): bool {

			$current	= \Nino\Auth::getCurrentUser( $appData );
			$token		= \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
			$stored		= $current === false ? false : \Nino\Auth::getUser( $appData, (string) $current['mail'] );

			if( $stored === false || is_string( $token ) === false || \Nino\Auth::sessionHolds( $stored, $token ) === false ) {
				\Nino\Http::fail( $request, 401, 'not logged in', 'session' );
				return true;
			}

			$appData['./nino/auth/current'] = $stored;

			$allowed = $selfOr === null
				? \Nino\Admin\Admin::permitted( $appData, $perm )
				: Admin::authorize( $appData, $selfOr, $perm )[0];

			if( $allowed === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return true;
			}

			return false;
		}

		/**
		 *	Delete a role nobody holds - an account with a dangling role would
		 *	silently lose what the role granted, so the accounts come first.
		 *	Decided against the roles config.php holds, under its lock
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$id = \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'id' );

			// Read, checked and written under one lock, as apiSave() does
			$fresh = self::lockStored( $appData, $request, self::MANAGE_PERM );

			if( $fresh === null )
				return;

			try {

				if( isset( self::all( $fresh )[$id] ) === false ) {
					\Nino\Http::fail( $request, 404, 'unknown role', 'roles_unknown', [ $id ] );
					return;
				}

				if( self::refuseChange( $fresh, $request, $id ) === true )
					return;

				$holders = self::holders( $fresh, $id );

				if( $holders > 0 ) {
					\Nino\Http::fail( $request, 409, $holders. ( $holders === 1 ? ' account holds' : ' accounts hold' ). ' this role - give them another one first', 'roles_in_use', [ $holders ] );
					return;
				}

				unset( $appData['/nino/auth/roles'][$id] );
				\Nino\AppData::writeContentData( $appData, [ '/nino/auth/roles' ] );

				\Nino\Http::ok( $request );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}
		}
	}
}
