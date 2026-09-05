<?php
declare(strict_types=1);
/**
 *	Nino							A compact filesystembased php framework
 *	Modules\Users\Admin	Users panel: accounts, profiles and the role each holds
 *
 *	@package					Dape/Nino
 *	@author						David Perchermeier <mail@dape.io>
 *	@link							https://github.com/dapeio/nino
 */
namespace Nino\Modules\Users {

	/**
	 *	Nino							A compact filesystembased php framework
	 *	Modules						The workbench's own screens
	 *	Users							User editor: lets a user change their own mail/password (with
	 *											current-password confirmation), or - given the
	 *											'/_admin/users/manage' permission - anyone's, plus (manage-only,
	 *											no self-service) which role an account holds. What a role
	 *											grants is the Roles tab of this same pane (see Roles), the
	 *											login throttle the Lockout tab. Sessions/tries/status, and
	 *											the permissions an account may hold beside its role, stay a
	 *											developer-only, direct-json task
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/users/manage';
		private const int MIN_PW_LENGTH = 8;

		public static function actions(): array {
			return [
				'users/list' 			=> [ self::class, 'apiList' ],
				'users/create' 		=> [ self::class, 'apiCreate' ],
				'users/save' 			=> [ self::class, 'apiSave' ],
				'users/delete' 		=> [ self::class, 'apiDelete' ],
				'users/logoutall' => [ self::class, 'apiLogoutAll' ],
				'users/role' 			=> [ self::class, 'apiSetRole' ],
			];
		}

		/**
		 *	A Dashboard tile - see \Nino\Admin\Panels
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array
		 */
		public static function summary( array &$appData ): array {
			return [ 'value' => count( $appData['/nino/auth/user'] ?? [] ), 'label' => '/_admin/dashboard/label/users' ];
		}

		// No perm(): every account may edit its own profile. What a manager
		// may do beyond that is MANAGE_PERM, checked inside each action
		public static function nav(): array {
			return [ 'users', '/_admin/nav/user', 2, 'system' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-round-icon lucide-users-round"><path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/></svg>';
		}

		// The roles accounts hold and the login throttle sit on tabs of this
		// pane, each with its own permission (see \Nino\Admin\Panels::collect())
		public static function tabs(): array {
			return [ Roles::class, Lockout::class ];
		}

		public static function panes(): array {
			return [ 'users-list', 'users-form' ];
		}

		public static function assets(): array {
			return [
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.js' ),
				\Nino\Admin\Panels::relative( dirname( __DIR__ ). '/assets/admin.css' ),
			];
		}

		// The module's own words, one <locale>.php per interface language -
		// its tabs' among them, since a tab is part of the same module
		public static function text(): string {
			return \Nino\Admin\Panels::relative( dirname( __DIR__ ). '/text' );
		}

		public static function log( string $action, array $data ): string {
			return match( $action ) {
				'users/create' 		=> 'Create User '. trim( (string) ( $data['mail'] ?? '' ) ),
				'users/delete' 		=> 'Delete User '. ( $data['username'] ?? '' ),
				'users/save' 			=> 'Edit User '. ( trim( (string) ( $data['mail'] ?? '' ) ) !== '' ? $data['mail'] : ( $data['username'] ?? '' ) ),
				'users/logoutall' => 'Logout-All '. ( $data['username'] ?? '' ),
				'users/role' 			=> 'Set Role '. ( $data['username'] ?? '' ). ' '. ( $data['role'] ?? '' ),
				default 					=> '',
			};
		}

		/**
		 *	Every permission the Roles tab's picker offers, label and group
		 *	included. Not the limit of what a role may hold - the scoped
		 *	permissions are one string per action, field and text key, so
		 *	there is no list of them and Roles::apiSave() checks a shape
		 *	instead. This is what can be picked rather than typed. Two
		 *	sources, in this order:
		 *
		 *	The panel registry. A panel or tab that gates itself with perm()
		 *	is assignable, the workbench's own modules and a runtime module's
		 *	panels alike, under the label its nav entry carries and in the
		 *	group it sits in. This panel itself is everyone's; what a manager
		 *	may do beyond their own profile is MANAGE_PERM, offered where the
		 *	panel sits. A tab sharing its parent's permission (Roles) is
		 *	covered by that one entry.
		 *
		 *	And then whatever is actually in force. A permission held by a
		 *	role or by an account that no panel is offering right now - a
		 *	module switched off, a module deleted, a permission written by
		 *	hand, a scoped one typed into the roles form - joins the list under
		 *	its own string, in the 'other' group and with offered => false. It
		 *	has to: the picker only sends back the rows it could show, so a
		 *	permission missing from here is one the next save of that role
		 *	silently drops. Named, never invented - nothing is added that no
		 *	role and no account holds.
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										[ { perm, label, group, offered }, ... ] - label is a fill key or literal text
		 */
		public static function permOptions( array &$appData ): array {

			$options = [];

			foreach( \Nino\Admin\Admin::allPanels( $appData ) as $panel ) {

				$perm 	= $panel['class'] === self::class ? self::MANAGE_PERM : $panel['perm'];
				$label 	= $panel['class'] === self::class ? '/_admin/users/label/permissions-manage' : $panel['label'];

				if( $perm === '' || isset( $options[$perm] ) === true )
					continue;

				$options[$perm] = [ 'perm' => $perm, 'label' => $label, 'group' => $panel['group'], 'offered' => true ];
			}

			if( isset( $options[self::MANAGE_PERM] ) === false )
				$options[self::MANAGE_PERM] = [ 'perm' => self::MANAGE_PERM, 'label' => '/_admin/users/label/permissions-manage', 'group' => 'system', 'offered' => true ];

			foreach( self::permsInUse( $appData ) as $perm )
				if( isset( $options[$perm] ) === false )
					$options[$perm] = [ 'perm' => $perm, 'label' => $perm, 'group' => 'other', 'offered' => false ];

			return array_values( $options );
		}

		/**
		 *	Every permission this installation actually holds somewhere -
		 *	on a role or directly on an account - whether or not a panel is
		 *	offering it. Full access is not one of them: '/*' is its own
		 *	control, not an entry in a list of permissions
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array										Permission strings, in the order first seen
		 */
		public static function permsInUse( array &$appData ): array {

			$perms = [];

			$collect = static function( mixed $held ) use ( &$perms ): void {
				foreach( is_array( $held ) === true ? $held : [] as $perm )
					if( is_string( $perm ) === true && $perm !== '' && $perm !== '/*' )
						$perms[$perm] = true;
			};

			foreach( is_array( $appData['/nino/auth/roles'] ?? null ) === true ? $appData['/nino/auth/roles'] : [] as $role )
				$collect( is_array( $role ) === true ? ( $role['perms'] ?? null ) : null );

			foreach( is_array( $appData['/nino/auth/user'] ?? null ) === true ? $appData['/nino/auth/user'] : [] as $user )
				$collect( is_array( $user ) === true ? ( $user['perms'] ?? null ) : null );

			return array_keys( $perms );
		}

		/**
		 *	List every user the current user may see: everyone if they have
		 *	MANAGE_PERM, otherwise just themselves. Includes each user's role
		 *	and the roles there are (so a manager's forms can offer them),
		 *	regardless of canManage - harmless to a non-manager since they
		 *	only ever see their own row. Never a password hash
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiList( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$current 	= \Nino\Auth::getCurrentUser( $appData );
			$canManage = \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM );

			$users = [];
			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user ) {

				if( $canManage === false && $mail !== $current['mail'] )
					continue;

				$users[] = [
					'mail' 		=> $mail,
					'isSelf' 	=> $mail === $current['mail'],
					'role' 		=> (string) ( $user['role'] ?? '' ),
					// The permissions the account holds beside its role - not
					// editable here, but what tells a "no role" apart from a
					// recovery-created account with full access of its own
					'perms' 	=> array_values( array_filter( (array) ( $user['perms'] ?? [] ), 'is_string' ) ),
				];
			}

			$roles = [];
			foreach( Roles::all( $appData ) as $id => $role )
				$roles[] = [ 'id' => $id, 'label' => $role['label'] ];

			\Nino\Http::ok( $request, [ 'users' => $users, 'canManage' => $canManage, 'self' => $current['mail'], 'roles' => $roles ] );
		}

		/**
		 *	Create an account holding one of the roles (see Roles) - manager-
		 *	only. The role is all the account gets: what it may do is the
		 *	role's, never posted permission strings
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiCreate( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 	= \Nino\Admin\Admin::postData();
			$mail 	= trim( (string) ( $data['mail'] ?? '' ) );
			$pw 		= (string) ( $data['pw'] ?? '' );
			$role 	= (string) ( $data['role'] ?? '' );

			if( $mail === '' || filter_var( $mail, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid mail' );
				return;
			}

			if( strlen( $pw ) < self::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. self::MIN_PW_LENGTH. ' characters' );
				return;
			}

			if( $role !== '' && isset( Roles::all( $appData )[$role] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown role' );
				return;
			}

			// A new account with a role wider than the one creating it is
			// the account its creator signs in as next (see Roles::notHeld())
			$missing = $role !== '' ? Roles::notHeld( $appData, Roles::all( $appData )[$role]['perms'] ) : '';

			if( $missing !== '' ) {
				\Nino\Http::fail( $request, 403, 'cannot hand out a role holding a permission your own account does not: '. $missing );
				return;
			}

			if( \Nino\Auth::insertUser( $appData, $mail, $pw, [], $role ) === false ) {
				\Nino\Http::fail( $request, 409, 'mail already in use' );
				return;
			}

			\Nino\Http::ok( $request, [ 'mail' => $mail, 'role' => $role ] );
		}

		/**
		 *	Delete an account - manager-only, never your own (log out and
		 *	ask another manager), and never the last one holding full
		 *	access, its own or its role's: a project with no developer
		 *	account left has only recovery.php to get back in
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiDelete( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$username = (string) ( \Nino\Admin\Admin::postData()['username'] ?? '' );
			$current 	= \Nino\Auth::getCurrentUser( $appData );

			if( $current !== false && $current['mail'] === $username ) {
				\Nino\Http::fail( $request, 400, 'cannot delete yourself' );
				return;
			}

			$user = \Nino\Auth::getUser( $appData, $username );

			if( $user === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user' );
				return;
			}

			if( in_array( '/*', \Nino\Auth::permissions( $appData, $user ), true ) === true && Roles::fullAccessExists( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 409, 'the last account with full access cannot be deleted' );
				return;
			}

			if( \Nino\Auth::deleteUser( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not delete' );
				return;
			}

			\Nino\Http::ok( $request );
		}

		/**
		 *	Update a user's mail and/or password
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSave( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 				= \Nino\Admin\Admin::postData();
			$username 		= (string) ( $data['username'] ?? '' );
			$newUsername 	= trim( (string) ( $data['mail'] ?? '' ) );
			$pw 					= (string) ( $data['pw'] ?? '' );
			$currentPw 		= (string) ( $data['currentPassword'] ?? '' );

			[ $allowed, $isSelf ] = self::_authorize( $appData, $username );

			if( $allowed === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			if( $newUsername === '' || filter_var( $newUsername, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid mail' );
				return;
			}

			if( $pw !== '' && strlen( $pw ) < self::MIN_PW_LENGTH ) {
				\Nino\Http::fail( $request, 400, 'password must be at least '. self::MIN_PW_LENGTH. ' characters' );
				return;
			}

			// Changing your own account requires re-confirming your current password -
			// an admin editing someone else's account doesn't need to know their password
			if( $isSelf === true ) {
				$storedUser = \Nino\Auth::getUser( $appData, $username );
				if( $storedUser === false || password_verify( $currentPw, $storedUser['pw'] ) === false ) {
					\Nino\Http::fail( $request, 401, 'wrong current password' );
					return;
				}
			}

			$result = \Nino\Auth::updateUser( $appData, $username, $newUsername, $pw );

			if( $result === false ) {
				\Nino\Http::fail( $request, 400, 'mail already in use' );
				return;
			}

			\Nino\Http::ok( $request, [ 'mail' => $result['mail'] ] );
		}

		/**
		 *	Log a user out of every session ("überall abmelden")
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiLogoutAll( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guard( $appData, $request ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$username = (string) ( $data['username'] ?? '' );

			[ $allowed, $isSelf ] = self::_authorize( $appData, $username );

			if( $allowed === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			$ok = \Nino\Auth::logoutAllSessions( $appData, $username );

			\Nino\Http::ok( $request, [ 'ok' => $ok, 'loggedOutSelf' => $isSelf ] );
		}

		/**
		 *	Give an account another role, or none - manager-only, and unlike
		 *	apiSave()/apiLogoutAll() with no self-service path at all
		 *	(deliberately: changing your own role is exactly the kind of
		 *	accidental self-lockout this does not try to guard against; log
		 *	out and ask another manager). The role has to exist (see
		 *	\Nino\Auth::setRole()), and the last full access there is must
		 *	not be handed away either - the same rule apiDelete() keeps
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiSetRole( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$username = (string) ( $data['username'] ?? '' );
			$role 		= (string) ( $data['role'] ?? '' );
			$current 	= \Nino\Auth::getCurrentUser( $appData );

			if( $current !== false && $current['mail'] === $username ) {
				\Nino\Http::fail( $request, 400, 'cannot change your own role' );
				return;
			}

			$user = \Nino\Auth::getUser( $appData, $username );

			if( $user === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user' );
				return;
			}

			if( $role !== '' && isset( Roles::all( $appData )[$role] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown role' );
				return;
			}

			$next 				= $user;
			$next['role'] = $role;

			if( in_array( '/*', \Nino\Auth::permissions( $appData, $user ), true ) === true
				&& in_array( '/*', \Nino\Auth::permissions( $appData, $next ), true ) === false
				&& Roles::fullAccessExists( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 409, 'the last account with full access cannot lose it' );
				return;
			}

			// The same bound as on creating an account (see Roles::notHeld())
			$missing = $role !== '' ? Roles::notHeld( $appData, Roles::all( $appData )[$role]['perms'] ) : '';

			if( $missing !== '' ) {
				\Nino\Http::fail( $request, 403, 'cannot hand out a role holding a permission your own account does not: '. $missing );
				return;
			}

			if( \Nino\Auth::setRole( $appData, $username, $role ) === false ) {
				\Nino\Http::fail( $request, 500, 'could not save' );
				return;
			}

			\Nino\Http::ok( $request, [ 'role' => $role ] );
		}

		/**
		 *	Whether the current user may act on $username, and whether it's themselves
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$username 		Target username
		 *
		 *	@return 	array										[ allowed, isSelf ]
		 */
		private static function _authorize( array &$appData, string $username ): array {

			$current 	= \Nino\Auth::getCurrentUser( $appData );
			$isSelf 	= $current !== false && $current['mail'] === $username;
			$canManage = \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM );

			return [ $isSelf === true || $canManage === true, $isSelf ];
		}
	}
}
