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
	 *											exists right now (see \Nino\Modules\Users\Admin::permOptions()),
	 *											plus whatever is typed into the field beneath it, which is
	 *											how the scoped permissions get in (see
	 *											\Nino\Admin\Admin::scoped() - there is one per action, per
	 *											field and per text key, so no list could offer them). The two roles every project starts
	 *											with, Editor and Developer, are written by the wizard's
	 *											Setup step from defaults() and are ordinary roles from
	 *											then on.
	 *
	 *											Manager-only, under the same permission as the accounts
	 *											themselves: whoever may hand out a role may say what it
	 *											grants. Two changes are refused outright rather than
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
		 *	field, per text key. There is no enumerating them, so the roles
		 *	editor lets one be typed, and this is what it is checked against.
		 *
		 *	No permission is gained by the change: reaching this endpoint at all
		 *	means holding MANAGE_PERM, and the same request may set '/*'. What
		 *	the shape check is for is the typo - a string no check could ever
		 *	match, written into config.php as if it granted something
		 */
		private const string PERM_PATTERN = '#^/([A-Za-z0-9_.-]+|\*)(/([A-Za-z0-9_.-]+|\*))*$#';
		private const int MAX_PERM_LENGTH = 200;

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
				'roles/save' 		=> 'Edit Role '. ( $data['id'] ?? '' ),
				'roles/delete' 	=> 'Delete Role '. ( $data['id'] ?? '' ),
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
		 *	Whether any account holds full access, its own or its role's -
		 *	the state a delete or a role change must never end (see
		 *	\Nino\Modules\Users\Admin::apiDelete(), \Nino\Modules\Users\Admin::apiSetRole() and apiSave())
		 *
		 *	@param		array 		$appData			App data, or a copy with the change applied
		 *	@param		string		$except				An account to leave out
		 *
		 *	@return 	bool
		 */
		public static function fullAccessExists( array $appData, string $except = '' ): bool {

			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user )
				if( $mail !== $except && is_array( $user ) === true && in_array( '/*', \Nino\Auth::permissions( $appData, $user ), true ) === true )
					return true;

			return false;
		}

		/**
		 *	The first of these permissions the signed-in account does not
		 *	hold itself, or '' when it holds every one. Granting is bounded by
		 *	holding: a role may not be given a permission its author lacks
		 *	(apiSave()), and an account may not be handed a role wider than
		 *	the one handing it out (\Nino\Modules\Users\Admin::apiCreate(),
		 *	apiSetRole()). Without both, an account that may manage users
		 *	writes a full-access role, creates an account holding it with a
		 *	password of its choosing, and signs in as that - and "manage
		 *	users" would silently mean "everything". Checked with the same
		 *	\Nino\Auth::checkPermission() every action uses, so '/*' alone
		 *	covers '/*', and a blanket covers what is under it.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array			$perms				Permission strings
		 *
		 *	@return 	string										The first one not held, else ''
		 */
		public static function notHeld( array &$appData, array $perms ): string {

			foreach( $perms as $perm )
				if( is_string( $perm ) === true && \Nino\Auth::checkPermission( $appData, $perm ) === false )
					return $perm;

			return '';
		}

		/**
		 *	Every role, how many accounts hold each, and the permissions the
		 *	picker offers (see \Nino\Modules\Users\Admin::permOptions() - what a
		 *	role may hold is wider than that, see apiSave())
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
				$roles[] = [ 'id' => $id, 'label' => $role['label'], 'perms' => $role['perms'], 'users' => self::holders( $appData, $id ) ];

			\Nino\Http::ok( $request, [ 'roles' => $roles, 'permOptions' => \Nino\Modules\Users\Admin::permOptions( $appData ) ] );
		}

		/**
		 *	Create or change a role. Every permission has to be shaped like one
		 *	(PERM_PATTERN) and is refused by name otherwise, so a typo cannot
		 *	end up in config.php looking like a grant; '/*' stands alone, since
		 *	it covers everything else already. Tried on a copy first, for the
		 *	two refusals the class docblock names
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
			$id 		= (string) ( $data['id'] ?? '' );
			$label 	= trim( (string) ( $data['label'] ?? '' ) );
			$perms 	= $data['perms'] ?? null;

			if( preg_match( self::ID_PATTERN, $id ) !== 1 ) {
				\Nino\Http::fail( $request, 400, 'a role id is a slug: lowercase letters, digits and hyphens, starting with a letter' );
				return;
			}

			if( $label === '' || mb_strlen( $label ) > self::MAX_LABEL_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'a name of at most '. self::MAX_LABEL_LENGTH. ' characters is required' );
				return;
			}

			if( is_array( $perms ) === false || count( array_filter( $perms, 'is_string' ) ) !== count( $perms ) ) {
				\Nino\Http::fail( $request, 400, 'perms must be an array of strings' );
				return;
			}

			$perms 		= array_values( array_unique( array_map( 'trim', $perms ) ) );
			$malformed = array_values( array_filter( $perms, fn( string $perm ) => strlen( $perm ) > self::MAX_PERM_LENGTH || preg_match( self::PERM_PATTERN, $perm ) !== 1 ) );

			// Named rather than dropped: a permission silently removed from
			// the request looks exactly like one the save accepted, and the
			// role comes back missing it with nothing to explain why
			if( $malformed !== [] ) {
				\Nino\Http::fail( $request, 400, 'not a permission: '. implode( ', ', array_slice( $malformed, 0, 5 ) ) );
				return;
			}

			if( in_array( '/*', $perms, true ) === true )
				$perms = [ '/*' ];

			$next = $appData;
			$next['/nino/auth/roles'][$id] = [ 'label' => $label, 'perms' => $perms ];

			if( \Nino\Auth::checkPermission( $next, self::MANAGE_PERM ) === false ) {
				\Nino\Http::fail( $request, 409, 'this change would take the permission to manage users away from your own account' );
				return;
			}

			if( self::fullAccessExists( $appData ) === true && self::fullAccessExists( $next ) === false ) {
				\Nino\Http::fail( $request, 409, 'this change would leave no account with full access' );
				return;
			}

			// Only what the role gains is measured against the account making
			// the change (see notHeld()): taking a permission it does not hold
			// away from a role narrows somebody else and widens nobody
			$missing = self::notHeld( $appData, array_values( array_diff( $perms, self::all( $appData )[$id]['perms'] ?? [] ) ) );

			if( $missing !== '' ) {
				\Nino\Http::fail( $request, 403, 'cannot grant a permission your own account does not hold: '. $missing );
				return;
			}

			$appData['/nino/auth/roles'] = $next['/nino/auth/roles'];
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/roles' ] );

			\Nino\Http::ok( $request, [ 'id' => $id, 'label' => $label, 'perms' => $perms, 'users' => self::holders( $appData, $id ) ] );
		}

		/**
		 *	Delete a role nobody holds - an account with a dangling role would
		 *	silently lose what the role granted, so the accounts come first
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$id = (string) ( \Nino\Admin\Admin::postData()['id'] ?? '' );

			if( isset( self::all( $appData )[$id] ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown role' );
				return;
			}

			$holders = self::holders( $appData, $id );

			if( $holders > 0 ) {
				\Nino\Http::fail( $request, 409, $holders. ( $holders === 1 ? ' account holds' : ' accounts hold' ). ' this role - give them another one first' );
				return;
			}

			unset( $appData['/nino/auth/roles'][$id] );
			\Nino\AppData::writeContentData( $appData, [ '/nino/auth/roles' ] );

			\Nino\Http::ok( $request );
		}
	}
}
