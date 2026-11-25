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
	 *											no self-service) which role an account holds and whether it
	 *											is active: a disabled account cannot log in and keeps no
	 *											session, and is never one's own nor the last active one
	 *											with full access. Address, password and role are one save.
	 *											The list says which accounts are disabled, which are locked
	 *											out right now and when each logged in last. What a role
	 *											grants is the Roles tab of this same pane (see Roles), the
	 *											login throttle - and lifting a lock - the Lockout tab, the
	 *											password recovery.php asks for the RecoveryPassword tab.
	 *											An account may carry a name, the one a feature addresses it
	 *											by (cleanName()).
	 *											Every session an account holds is ended from here too
	 *											(users/logoutall). The permissions an account may hold
	 *											beside its role are written into config.php by hand
	 *
	 *	@package					Dape/Nino
	 *	@author						David Perchermeier <mail@dape.io>
	 *	@link							https://github.com/dapeio/nino
	 */
	class Admin {

		public const string MANAGE_PERM = '/_admin/users/manage';

		// The longest name an account may carry, in characters
		public const int NAME_MAX = 60;

		public static function actions(): array {
			return [
				'users/list' 			=> [ self::class, 'apiList' ],
				'users/create' 		=> [ self::class, 'apiCreate' ],
				'users/save' 			=> [ self::class, 'apiSave' ],
				'users/delete' 		=> [ self::class, 'apiDelete' ],
				'users/logoutall' => [ self::class, 'apiLogoutAll' ],
				'users/role' 			=> [ self::class, 'apiSetRole' ],
				'users/status' 		=> [ self::class, 'apiStatus' ],
			];
		}

		/**
		 *	A Dashboard tile - see \Nino\Admin\Panels
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *
		 *	@return 	array|null								The tile; null for an account that does not manage accounts, which has none
		 */
		public static function summary( array &$appData ): ?array {

			// The number is every account's, so it is the tile of whoever manages them: the panel itself lists anybody else their own row
			if( \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM ) === false )
				return null;

			return [ 'value' => count( $appData['/nino/auth/user'] ?? [] ), 'label' => '/_admin/dashboard/label/users' ];
		}

		// No perm(): every account may edit its own profile. What a manager
		// may do beyond that is MANAGE_PERM, checked inside each action
		public static function nav(): array {
			return [ 'users', '/_admin/nav/user', 2, 'system' ];
		}

		// The entries of the manual its "?" opens, in the order they are shown (see \Nino\Admin\Admin::manual())
		public static function manual(): array {
			return [ 'intro', 'account', 'name', 'role', 'password', 'deactivate', 'lockout', 'recovery' ];
		}

		public static function icon(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-users-round-icon lucide-users-round"><path d="M18 21a8 8 0 0 0-16 0"/><circle cx="10" cy="8" r="5"/><path d="M22 20c0-3.37-2-6.5-4-8a5 5 0 0 0-.45-8.3"/></svg>';
		}

		// The roles accounts hold, the login throttle and the recovery password
		// sit on tabs of this pane, each with its own permission (see
		// \Nino\Admin\Panels::collect())
		public static function tabs(): array {
			return [ Roles::class, Lockout::class, RecoveryPassword::class ];
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
				'users/create' 		=> 'Create User '. trim( \Nino\Admin\Admin::postString( $data, 'mail' ) ),
				'users/delete' 		=> 'Delete User '. \Nino\Admin\Admin::postString( $data, 'username' ),
				'users/save' 			=> 'Edit User '. self::_savedAccount( $data ). ( is_string( $data['role'] ?? null ) === true ? ' Role '. $data['role'] : '' ),
				'users/status' 		=> ( ( $data['active'] ?? false ) === true ? 'Activate User ' : 'Deactivate User ' ). \Nino\Admin\Admin::postString( $data, 'username' ),
				'users/logoutall' => 'Logout-All '. \Nino\Admin\Admin::postString( $data, 'username' ),
				'users/role' 			=> 'Set Role '. \Nino\Admin\Admin::postString( $data, 'username' ). ' '. \Nino\Admin\Admin::postString( $data, 'role' ),
				default 					=> '',
			};
		}

		/**
		 *	The account a save names in the activity log: its address, and the address it is given
		 *	where the two differ
		 *
		 *	@param		array 		$data					What was posted
		 *
		 *	@return 	string
		 */
		private static function _savedAccount( array $data ): string {

			$old = \Nino\Admin\Admin::postString( $data, 'username' );
			$new = trim( \Nino\Admin\Admin::postString( $data, 'mail' ) );

			if( $new === '' )
				return $old;

			return $new === $old ? $new : $old. ' -> '. $new;
		}

		/**
		 *	The name an account may carry, as it is stored: what a feature addresses
		 *	it by, so one line of text - no control or format character
		 *	of any kind (a null byte, a line break, a right-to-left mark), at
		 *	most NAME_MAX characters, a blank around it, a no-break space among them, taken off. Nothing is
		 *	cut to fit: a name that is too long is refused. '' is no name
		 *
		 *	@param		mixed			$raw					What was posted
		 *	@param		string|null	&$problem		(reference) Why it is refused, 'invalid' or 'long'; null where it is not
		 *
		 *	@return 	string|null							The name, null when it is refused
		 */
		public static function cleanName( mixed $raw, ?string &$problem = null ): ?string {

			$problem = null;

			if( is_string( $raw ) === false || preg_match( '/^[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}]*$/u', $raw ) !== 1 ) {
				$problem = 'invalid';
				return null;
			}

			$name = (string) preg_replace( '/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $raw );

			if( mb_strlen( $name ) > self::NAME_MAX ) {
				$problem = 'long';
				return null;
			}

			return $name;
		}

		/**
		 *	Say why a name is refused - see cleanName()
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$problem			'long' or 'invalid'
		 *
		 *	@return 	void
		 */
		private static function _refuseName( array &$request, string $problem ): void {

			$long = $problem === 'long';

			\Nino\Http::fail( $request, 400, $long ? 'a name has at most '. self::NAME_MAX. ' characters' : 'a name is one line of text', $long ? 'users_name_long' : 'users_name_invalid', $long ? [ self::NAME_MAX ] : [], 'name' );
		}

		/**
		 *	Say why a password is refused - see \Nino\Auth::passwordRefusal()
		 *
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$reason				'invalid', 'short' or 'long'
		 *
		 *	@return 	void
		 */
		private static function _refusePassword( array &$request, string $reason ): void {

			if( $reason === 'invalid' )
				\Nino\Http::fail( $request, 400, 'a password is text without a null byte', 'users_password_invalid', [], 'pw' );
			elseif( $reason === 'long' )
				\Nino\Http::fail( $request, 400, 'password must be at most '. \Nino\Auth::PASSWORD_MAX_BYTES. ' bytes', 'users_password_long', [ \Nino\Auth::PASSWORD_MAX_BYTES ], 'pw' );
			else
				\Nino\Http::fail( $request, 400, 'password must be at least '. \Nino\Admin\Recovery::MIN_PW_LENGTH. ' characters', 'users_password_short', [ \Nino\Admin\Recovery::MIN_PW_LENGTH ], 'pw' );
		}

		/**
		 *	Every permission the Roles tab's picker offers, label and group
		 *	included. Not the limit of what a role may hold - the scoped
		 *	permissions are one string per action, field and text key, so
		 *	there is no finite list of them and Roles::apiSave() checks a shape
		 *	instead; scopeOptions() is where a panel offers them. Two
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
		 *	hand, a scoped one a panel no longer lists - joins the list under
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

			foreach( self::permsInUse( $appData ) as $perm )
				if( isset( $options[$perm] ) === false )
					$options[$perm] = [ 'perm' => $perm, 'label' => $perm, 'group' => 'other', 'offered' => false ];

			return array_values( $options );
		}

		/**
		 *	The scoped permissions the panels offer, for the three lists of the
		 *	Roles tab - area, action, field. A panel that has any answers
		 *	scopes() (a static method, optional: a panel without it simply
		 *	offers none, and one whose answer is not what is described below
		 *	loses what is wrong with it rather than the whole answer):
		 *
		 *	  [ { scope, door, label, areas: [ { id, label, perm?, actions: [
		 *	    { id, label, perm, fields?: [ { id, label, perm } ] } ] } ] } ]
		 *
		 *	scope is the prefix \Nino\Admin\Admin::isScoped() reads, door the
		 *	panel's own permission, label of a scope and of an action a fill key
		 *	or literal text, those of an area and a field literal text. Every
		 *	perm has to be shaped like a permission (Roles::isPermShape()) and
		 *	lie below its scope, so what the form can add is always something
		 *	apiSave() accepts and a check can match. An action without a valid
		 *	perm, an area without an action and a scope without an area are
		 *	dropped. Asked of the registry's classes only, which are not
		 *	derived from a request
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		bool			$bare					Keep a scope that has no area right now - the guards read the prefix whether or not anything is offered under it (see Roles::notHeld())
		 *
		 *	@return 	array										The tree, as above
		 */
		public static function scopeOptions( array &$appData, bool $bare = false ): array {

			$scopes = [];

			foreach( \Nino\Admin\Admin::allPanels( $appData ) as $panel ) {

				$class = $panel['class'] ?? '';

				if( is_string( $class ) === false || method_exists( $class, 'scopes' ) === false )
					continue;

				$offered = $class::scopes( $appData );

				foreach( is_array( $offered ) === true ? $offered : [] as $scope ) {

					$clean = is_array( $scope ) === true ? self::_cleanScope( $scope, $bare ) : null;

					if( $clean !== null )
						$scopes[] = $clean;
				}
			}

			return $scopes;
		}

		/**
		 *	One scope of scopeOptions(), with everything that is not the shape
		 *	described there taken out
		 *
		 *	@param		array 		$scope
		 *
		 *	@return 	array|null								Null when nothing usable is left
		 */
		private static function _cleanScope( array $scope, bool $bare = false ): ?array {

			$prefix = $scope['scope'] ?? null;
			$door 	= $scope['door'] ?? null;
			$label 	= $scope['label'] ?? null;

			if( is_string( $prefix ) === false || is_string( $door ) === false || is_string( $label ) === false
				|| str_ends_with( $prefix, '/' ) === false || Roles::isPermShape( rtrim( $prefix, '/' ) ) === false || Roles::isPermShape( $door ) === false )
				return null;

			$below = static fn( mixed $perm ): bool => is_string( $perm ) === true && Roles::isPermShape( $perm ) === true && str_starts_with( $perm, $prefix ) === true;

			$areas = [];

			foreach( is_array( $scope['areas'] ?? null ) === true ? $scope['areas'] : [] as $area ) {

				if( is_array( $area ) === false || is_string( $area['id'] ?? null ) === false || is_string( $area['label'] ?? null ) === false )
					continue;

				$actions = [];

				foreach( is_array( $area['actions'] ?? null ) === true ? $area['actions'] : [] as $action ) {

					if( is_array( $action ) === false || is_string( $action['id'] ?? null ) === false || is_string( $action['label'] ?? null ) === false || $below( $action['perm'] ?? null ) === false )
						continue;

					$fields = [];

					foreach( is_array( $action['fields'] ?? null ) === true ? $action['fields'] : [] as $field )
						if( is_array( $field ) === true && is_string( $field['id'] ?? null ) === true && is_string( $field['label'] ?? null ) === true && $below( $field['perm'] ?? null ) === true )
							$fields[] = [ 'id' => $field['id'], 'label' => $field['label'], 'perm' => $field['perm'] ];

					$clean = [ 'id' => $action['id'], 'label' => $action['label'], 'perm' => $action['perm'] ];

					if( $fields !== [] )
						$clean['fields'] = $fields;

					$actions[] = $clean;
				}

				if( $actions === [] )
					continue;

				$cleanArea = [ 'id' => $area['id'], 'label' => $area['label'], 'actions' => $actions ];

				if( $below( $area['perm'] ?? null ) === true )
					$cleanArea['perm'] = $area['perm'];

				$areas[] = $cleanArea;
			}

			return $areas === [] && $bare === false ? null : [ 'scope' => $prefix, 'door' => $door, 'label' => $label, 'areas' => $areas ];
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
		 *	only ever see their own row. Per account whether it is active, until
		 *	when it is locked out (see \Nino\Auth::lockedAccounts()) and when it
		 *	last logged in, as dates. Never a password hash
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

			$locked = \Nino\Auth::lockedAccounts( $appData );

			$users = [];
			foreach( $appData['/nino/auth/user'] ?? [] as $mail => $user ) {

				if( $canManage === false && $mail !== $current['mail'] )
					continue;

				$lastLogin = is_array( $user ) === true ? \Nino\Auth::lastLogin( $user ) : 0;

				$users[] = [
					'mail' 		=> $mail,
					'isSelf' 	=> $mail === $current['mail'],
					'role' 		=> (string) ( $user['role'] ?? '' ),
					'name' 		=> is_array( $user ) === true && is_string( $user['name'] ?? null ) === true ? $user['name'] : '',
					'status' 	=> ( $user['status'] ?? 0 ) === \Nino\Auth::STATUS_ACTIVE ? 'active' : 'disabled',
					'locked' 	=> isset( $locked[$mail] ) === true ? date( 'Y-m-d H:i', $locked[$mail] ) : '',
					'lastLogin' => $lastLogin > 0 ? date( 'Y-m-d H:i', $lastLogin ) : '',
					// The permissions the account holds beside its role - not
					// editable here, but what tells a "no role" apart from a
					// recovery-created account with full access of its own
					'perms' 	=> array_values( array_filter( (array) ( $user['perms'] ?? [] ), 'is_string' ) ),
					// Whether the signed-in account may change this one (see beyond()):
					// the form offers nothing a request would be refused for
					'canChange' => self::beyond( $appData, (string) $mail ) === '',
				];
			}

			$roles = [];
			foreach( Roles::all( $appData ) as $id => $role )
				$roles[] = [ 'id' => $id, 'label' => $role['label'], 'canGrant' => Roles::notHeld( $appData, $role['perms'] ) === '' ];

			\Nino\Http::ok( $request, [ 'users' => $users, 'canManage' => $canManage, 'roles' => $roles ] );
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
			$mail 	= trim( \Nino\Admin\Admin::postString( $data, 'mail' ) );
			$pw 		= \Nino\Admin\Admin::postString( $data, 'pw' );
			$role 	= \Nino\Admin\Admin::postString( $data, 'role' );

			if( $mail === '' || filter_var( $mail, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid mail', 'users_invalid_mail', [], 'mail' );
				return;
			}

			if( ( $refusal = \Nino\Auth::passwordRefusal( $pw ) ) !== null ) {
				self::_refusePassword( $request, $refusal );
				return;
			}

			// The role is looked up in the roles config.php holds, and the account is
			// written before the lock is let go: a role that is deleted meanwhile is not handed out
			$fresh = Roles::lockStored( $appData, $request, self::MANAGE_PERM );

			if( $fresh === null )
				return;

			try {

				if( $role !== '' && isset( Roles::all( $fresh )[$role] ) === false ) {
					\Nino\Http::fail( $request, 400, 'unknown role', 'users_unknown_role', [], 'role' );
					return;
				}

				$name = self::cleanName( $data['name'] ?? '', $problem );

				if( $name === null ) {
					self::_refuseName( $request, (string) $problem );
					return;
				}

				// A new account with a role wider than the one creating it is
				// the account its creator signs in as next (see Roles::notHeld())
				$missing = $role !== '' ? Roles::notHeld( $fresh, Roles::all( $fresh )[$role]['perms'] ) : '';

				if( $missing !== '' ) {
					\Nino\Http::fail( $request, 403, 'cannot hand out a role holding a permission your own account does not: '. $missing, Roles::isScope( $missing ) === true ? 'users_role_too_wide_scoped' : 'users_role_too_wide', [ $missing ], 'role' );
					return;
				}

				if( \Nino\Auth::getUser( $fresh, $mail ) !== false || \Nino\Auth::twin( $fresh, $mail ) !== null ) {
					\Nino\Http::fail( $request, 409, 'mail already in use', 'users_mail_in_use', [], 'mail' );
					return;
				}

				if( \Nino\Auth::insertUser( $appData, $mail, $pw, [], $role, $name ) === false ) {
					\Nino\Http::fail( $request, 409, 'mail already in use', 'users_mail_in_use', [], 'mail' );
					return;
				}
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}

			\Nino\Http::ok( $request, [ 'mail' => $mail, 'role' => $role, 'name' => $name ] );
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

			$username = \Nino\Admin\Admin::postString( \Nino\Admin\Admin::postData(), 'username' );
			$current 	= \Nino\Auth::getCurrentUser( $appData );

			if( $current !== false && $current['mail'] === $username ) {
				\Nino\Http::fail( $request, 400, 'cannot delete yourself', 'users_delete_self' );
				return;
			}

			if( \Nino\Auth::getUser( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return;
			}

			if( Roles::refuseBeyond( $appData, $request, self::beyond( $appData, $username ), 'account' ) === true )
				return;

			// Whether full access remains is decided against the accounts config.php
			// holds, under its lock, and the account goes before the lock is let go
			$fresh = Roles::lockStored( $appData, $request, self::MANAGE_PERM );

			if( $fresh === null )
				return;

			try {

				if( self::refuseTarget( $fresh, $request, $username ) === true )
					return;

				if( self::_refuseLastFullAccess( $fresh, $request, $username, true ) === true )
					return;

				if( \Nino\Auth::deleteUser( $appData, $username ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not delete' );
					return;
				}
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}

			\Nino\Http::ok( $request );
		}

		/**
		 *	Update a user's mail, password, name and - for a manager - role, in one
		 *	save: one write of config.php, after every check. The answers come in
		 *	the order every action on an account gives them: the right, what is
		 *	wrong with the posted values and what concerns the signed-in account
		 *	itself, the account existing, the account being within reach (see
		 *	beyond()), and last what the change itself runs into. The role counts only
		 *	when it differs from the stored one, and the checks of apiSetRole() - not
		 *	your own, one that exists, not the last full access, not wider than
		 *	yours - run for a change only
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
			$username 		= \Nino\Admin\Admin::postString( $data, 'username' );
			$newUsername 	= trim( \Nino\Admin\Admin::postString( $data, 'mail' ) );
			$pw 					= \Nino\Admin\Admin::postString( $data, 'pw' );
			$currentPw 		= \Nino\Admin\Admin::postString( $data, 'currentPassword' );
			$postedRole 	= array_key_exists( 'role', $data ) === true ? $data['role'] : null;

			[ $allowed, $isSelf ] = self::authorize( $appData, $username, self::MANAGE_PERM );

			if( $allowed === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			// An array, a number or nothing is no name of an account
			if( is_string( $data['username'] ?? null ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid username', 'users_invalid_name', [], 'username' );
				return;
			}

			if( $postedRole !== null && is_string( $postedRole ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown role', 'users_unknown_role', [], 'role' );
				return;
			}

			if( $newUsername === '' || filter_var( $newUsername, FILTER_VALIDATE_EMAIL ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid mail', 'users_invalid_mail', [], 'mail' );
				return;
			}

			if( $pw !== '' && ( $refusal = \Nino\Auth::passwordRefusal( $pw ) ) !== null ) {
				self::_refusePassword( $request, $refusal );
				return;
			}

			// The name is changed only where the request names one, and is
			// refused rather than cut
			$postedName = null;

			if( array_key_exists( 'name', $data ) === true ) {

				$postedName = self::cleanName( $data['name'], $problem );

				if( $postedName === null ) {
					self::_refuseName( $request, (string) $problem );
					return;
				}
			}

			// Changing your own account requires re-confirming your current password -
			// an admin editing someone else's account doesn't need to know their password
			if( $isSelf === true ) {
				// The failed guesses are counted against the account, the sign-in's own counter (see \Nino\Auth::confirmPassword()): a session that is somebody else's
				// cannot guess the password here without limit, and an account that is cooling down is refused as a wrong password is - no more is told of it
				if( \Nino\Auth::confirmPassword( $appData, $username, $currentPw ) === false ) {
					\Nino\Http::fail( $request, 401, 'wrong current password', 'wrong_password', [], 'currentPassword' );
					return;
				}
			}

			// The role: only a real change is a change - and one only a manager
			// may make, never on their own account. The own account is always
			// there, and what is refused of it is answered before any other account is looked up
			$stored = \Nino\Auth::getUser( $appData, $username );

			if( $isSelf === true && $stored !== false && $postedRole !== null && $postedRole !== (string) ( $stored['role'] ?? '' ) ) {

				if( self::_refuseOwnRoleChange( $appData, $request, $username ) === true )
					return;
			}

			if( $stored === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return;
			}

			// Nor may any change be made to an account that holds what the
			// signed-in one does not: its address, its password, its name, its role
			if( Roles::refuseBeyond( $appData, $request, self::beyond( $appData, $username ), 'account' ) === true )
				return;

			$mail 			= $username;
			$confirmed	= $stored['pw'] ?? null;

			// What the change runs into is decided against the accounts and roles
			// config.php holds, under its lock, and the account is written before the lock is let go
			$fresh = Roles::lockStored( $appData, $request, self::MANAGE_PERM, $username );

			if( $fresh === null )
				return;

			try {

				if( self::refuseTarget( $fresh, $request, $username ) === true )
					return;

				// What is a change is asked of the account as config.php holds it, and the
				// current password was confirmed against the hash this request found: a password
				// set meanwhile is a password that was not confirmed, and not a guess to count. A hash
				// of the same password that another request made meanwhile (a sign-in that rehashes) is none
				$stored 		= \Nino\Auth::getUser( $fresh, $username );
				$roleChange = $postedRole !== null && $postedRole !== (string) ( $stored['role'] ?? '' );
				$changeName = $postedName !== null && $postedName !== (string) ( $stored['name'] ?? '' );

				if( $isSelf === true && ( $stored['pw'] ?? null ) !== $confirmed && password_verify( $currentPw, (string) ( $stored['pw'] ?? '' ) ) === false ) {
					\Nino\Http::fail( $request, 401, 'wrong current password', 'wrong_password', [], 'currentPassword' );
					return;
				}

				if( $isSelf === true && $roleChange === true && self::_refuseOwnRoleChange( $fresh, $request, $username ) === true )
					return;

				if( $roleChange === true && self::_refuseRoleChange( $fresh, $request, $username, $postedRole ) === true )
					return;

				if( $newUsername !== $username && ( \Nino\Auth::getUser( $fresh, $newUsername ) !== false || \Nino\Auth::twin( $fresh, $newUsername, $username ) !== null ) ) {
					\Nino\Http::fail( $request, 400, 'mail already in use', 'users_mail_in_use', [], 'mail' );
					return;
				}

				if( $newUsername !== $username || $pw !== '' || $roleChange === true || $changeName === true ) {

					$result = \Nino\Auth::saveUser( $appData, $username, $newUsername, $pw, $roleChange === true ? $postedRole : null, $changeName === true ? $postedName : null );

					if( $result === false ) {
						\Nino\Http::fail( $request, 500, 'could not save' );
						return;
					}

					$mail = $result['mail'];
				}
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}

			$saved = \Nino\Auth::getUser( $appData, $mail );

			\Nino\Http::ok( $request, [ 'mail' => $mail, 'role' => $saved === false ? '' : (string) ( $saved['role'] ?? '' ) ] + ( $postedName === null ? [] : [ 'name' => $saved === false ? '' : (string) ( $saved['name'] ?? '' ) ] ) );
		}

		/**
		 *	Log a user out of every session ("Log out everywhere")
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
			$username = \Nino\Admin\Admin::postString( $data, 'username' );

			[ $allowed, $isSelf ] = self::authorize( $appData, $username, self::MANAGE_PERM );

			if( $allowed === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return;
			}

			// An array or a number is no name: read as '' it would pass for the
			// account nobody holds and be answered like any other
			if( isset( $data['username'] ) === true && is_string( $data['username'] ) === false ) {
				\Nino\Http::fail( $request, 400, 'invalid username', 'users_invalid_name', [], 'username' );
				return;
			}

			if( \Nino\Auth::getUser( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return;
			}

			if( Roles::refuseBeyond( $appData, $request, self::beyond( $appData, $username ), 'account' ) === true )
				return;

			// Reach and existence once more against what config.php holds, under its lock
			$fresh = Roles::lockStored( $appData, $request, self::MANAGE_PERM, $username );

			if( $fresh === null )
				return;

			try {

				if( self::refuseTarget( $fresh, $request, $username ) === true )
					return;

				$ok = \Nino\Auth::logoutAllSessions( $appData, $username );
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}

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
			$username = \Nino\Admin\Admin::postString( $data, 'username' );
			$role 		= \Nino\Admin\Admin::postString( $data, 'role' );

			// No role posted is no role to give, and not the role that is none
			if( is_string( $data['role'] ?? null ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown role', 'users_unknown_role', [], 'role' );
				return;
			}

			if( self::_refuseOwnRole( $appData, $request, $username ) === true )
				return;

			if( \Nino\Auth::getUser( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return;
			}

			if( Roles::refuseBeyond( $appData, $request, self::beyond( $appData, $username ), 'account' ) === true )
				return;

			$fresh = Roles::lockStored( $appData, $request, self::MANAGE_PERM );

			if( $fresh === null )
				return;

			try {

				if( self::refuseTarget( $fresh, $request, $username ) === true )
					return;

				if( self::_refuseRoleChange( $fresh, $request, $username, $role ) === true )
					return;

				if( \Nino\Auth::setRole( $appData, $username, $role ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not save' );
					return;
				}
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}

			\Nino\Http::ok( $request, [ 'role' => $role ] );
		}

		/**
		 *	Switch an account on or off - manager-only, never your own (log out
		 *	and ask another manager), and never the last active one holding
		 *	full access, the same rule apiDelete() keeps: a project whose only
		 *	full access cannot log in has only recovery.php to get back in.
		 *	Disabling ends the account's sessions (see \Nino\Auth::setStatus())
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *
		 *	@return 	void
		 */
		public static function apiStatus( array &$appData, array &$request ): void {

			if( \Nino\Admin\Admin::guardPerm( $appData, $request, self::MANAGE_PERM ) === false )
				return;

			$data 		= \Nino\Admin\Admin::postData();
			$username = $data['username'] ?? null;
			$active 	= $data['active'] ?? null;

			if( is_string( $username ) === false || is_bool( $active ) === false ) {
				\Nino\Http::fail( $request, 400, 'username and active are required', 'users_incomplete' );
				return;
			}

			$current = \Nino\Auth::getCurrentUser( $appData );

			if( $current !== false && $current['mail'] === $username ) {
				\Nino\Http::fail( $request, 400, 'cannot change the status of your own account', 'users_status_self' );
				return;
			}

			if( \Nino\Auth::getUser( $appData, $username ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return;
			}

			if( Roles::refuseBeyond( $appData, $request, self::beyond( $appData, $username ), 'account' ) === true )
				return;

			$fresh = Roles::lockStored( $appData, $request, self::MANAGE_PERM );

			if( $fresh === null )
				return;

			try {

				if( self::refuseTarget( $fresh, $request, $username ) === true )
					return;

				if( $active === false && self::_refuseLastFullAccess( $fresh, $request, $username, false ) === true )
					return;

				if( \Nino\Auth::setStatus( $appData, $username, $active ) === false ) {
					\Nino\Http::fail( $request, 500, 'could not save' );
					return;
				}
			} finally {
				\Nino\Filesystem::unlockFile( $appData, '/config.php' );
			}

			\Nino\Http::ok( $request, [ 'status' => $active === true ? 'active' : 'disabled' ] );
		}

		/**
		 *	Whether the own role may not be changed by the signed-in account - and if so, the response says what:
		 *	the right to manage accounts first, then the rule that nobody changes their own role. Asked of this
		 *	request's copy before the lock and of the stored accounts under it, so that the two answer alike
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data, or the copy with the stored accounts and roles
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$username			The account
		 *
		 *	@return 	bool										True when the change is refused and the response set
		 */
		private static function _refuseOwnRoleChange( array &$appData, array &$request, string $username ): bool {

			if( \Nino\Auth::checkPermission( $appData, self::MANAGE_PERM ) === false ) {
				\Nino\Http::fail( $request, 403, 'not allowed' );
				return true;
			}

			return self::_refuseOwnRole( $appData, $request, $username );
		}

		/**
		 *	Whether the change is one of the signed-in account's own role, which
		 *	nobody makes for themselves - and if so, the response says what. Shared
		 *	by apiSetRole() and apiSave(); the caller has checked MANAGE_PERM
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$username			The account
		 *
		 *	@return 	bool										True when the change is refused and the response set
		 */
		private static function _refuseOwnRole( array &$appData, array &$request, string $username ): bool {

			$current = \Nino\Auth::getCurrentUser( $appData );

			if( $current !== false && $current['mail'] === $username ) {
				\Nino\Http::fail( $request, 400, 'cannot change your own role', 'users_own_role', [], 'role' );
				return true;
			}

			return false;
		}

		/**
		 *	Whether the account is gone from what config.php holds or has become one the signed-in account may not
		 *	change (see beyond()) - and if so, the response says what. Asked under the lock, of the accounts and roles
		 *	as stored (see Roles::lockStored()), after the same questions were asked of this request's copy
		 *
		 *	@param		array 		&$fresh				(reference) The copy of the app data with the stored accounts and roles
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$username			The account
		 *
		 *	@return 	bool										True when the change is refused and the response set
		 */
		public static function refuseTarget( array &$fresh, array &$request, string $username ): bool {

			if( \Nino\Auth::getUser( $fresh, $username ) === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return true;
			}

			return Roles::refuseBeyond( $fresh, $request, self::beyond( $fresh, $username ), 'account' );
		}

		/**
		 *	Whether the account is the last one that holds full access - what a delete, a deactivation or
		 *	a role change must not end - and so is refused, the response says. Asked of the
		 *	accounts and roles config.php holds (see Roles::lockStored()); an account that is gone
		 *	from them is a 404. A signed-in account that may act on an account with full access holds full
		 *	access itself and is counted as long as it is active, which the lock has asked of it: this refusal
		 *	and the one of _refuseRoleChange() for the last full access are a second safeguard, which a
		 *	request of the panel does not reach
		 *
		 *	@param		array 		&$fresh				(reference) The copy of the app data with the stored accounts and roles
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$username			The account
		 *	@param		bool			$deleting			Whether the change is a delete, else a deactivation
		 *
		 *	@return 	bool										True when the change is refused and the response set
		 */
		private static function _refuseLastFullAccess( array &$fresh, array &$request, string $username, bool $deleting ): bool {

			$user = \Nino\Auth::getUser( $fresh, $username );

			if( $user === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return true;
			}

			if( in_array( '/*', \Nino\Auth::permissions( $fresh, $user ), true ) === true && Roles::fullAccessExists( $fresh, $username ) === false ) {
				if( $deleting === true )
					\Nino\Http::fail( $request, 409, 'the last account with full access cannot be deleted', 'users_last_admin_delete' );
				else
					\Nino\Http::fail( $request, 409, 'the last active account with full access cannot be deactivated', 'users_last_admin_status' );

				return true;
			}

			return false;
		}

		/**
		 *	Whether something stands in the way of giving $username the role
		 *	$role - and if so, the response says what. Shared by apiSetRole()
		 *	and apiSave(); the caller has checked MANAGE_PERM and that the account is within
		 *	reach (see beyond()), and asks it of the accounts and roles config.php holds (see
		 *	Roles::lockStored()). Refused: an account that is gone, a role that does not exist, the
		 *	last full access taken away, and a role wider than the signed-in account
		 *	itself (see Roles::notHeld())
		 *
		 *	@param		array 		&$fresh				(reference) The copy of the app data with the stored accounts and roles
		 *	@param		array 		&$request			(reference) Current server request
		 *	@param		string		$username			The account
		 *	@param		string		$role					A role id, or '' for none
		 *
		 *	@return 	bool										True when the change is refused and the response set
		 */
		private static function _refuseRoleChange( array &$fresh, array &$request, string $username, string $role ): bool {

			$user = \Nino\Auth::getUser( $fresh, $username );

			if( $user === false ) {
				\Nino\Http::fail( $request, 404, 'unknown user', 'users_unknown' );
				return true;
			}

			if( $role !== '' && isset( Roles::all( $fresh )[$role] ) === false ) {
				\Nino\Http::fail( $request, 400, 'unknown role', 'users_unknown_role', [], 'role' );
				return true;
			}

			$next 				= $user;
			$next['role'] = $role;

			if( in_array( '/*', \Nino\Auth::permissions( $fresh, $user ), true ) === true
				&& in_array( '/*', \Nino\Auth::permissions( $fresh, $next ), true ) === false
				&& Roles::fullAccessExists( $fresh, $username ) === false ) {
				\Nino\Http::fail( $request, 409, 'the last account with full access cannot lose it', 'users_last_admin_role' );
				return true;
			}

			// The same bound as on creating an account (see Roles::notHeld())
			$missing = $role !== '' ? Roles::notHeld( $fresh, Roles::all( $fresh )[$role]['perms'] ) : '';

			// A role is not the account's only source (see \Nino\Auth::permissions()):
			// taking one away, or swapping it, can leave the permissions the
			// account holds itself wide for a panel the role described
			if( $missing === '' )
				$missing = Roles::notHeld( $fresh, \Nino\Auth::permissions( $fresh, $next ), \Nino\Auth::permissions( $fresh, $user ) );

			if( $missing !== '' ) {
				\Nino\Http::fail( $request, 403, 'cannot hand out a role holding a permission your own account does not: '. $missing, Roles::isScope( $missing ) === true ? 'users_role_too_wide_scoped' : 'users_role_too_wide', [ $missing ], 'role' );
				return true;
			}

			return false;
		}

		/**
		 *	Whether the signed-in account may change $username - rename it, switch
		 *	it on or off, delete it, end its sessions, lift its lock, give it
		 *	another role: the bound that already holds for setting its password
		 *	and for handing out a role (see Roles::notHeld()), asked of what the
		 *	account holds now, its role's permissions and its own. An account
		 *	that holds what the signed-in one does not is not its to change - which
		 *	leaves one's own account always one's own - and an account that does
		 *	not exist is the caller's to refuse
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$username			The account
		 *
		 *	@return 	string										What the signed-in account does not hold of it, as Roles::notHeld() says; '' when it may change the account
		 */
		public static function beyond( array &$appData, string $username ): string {

			$user = \Nino\Auth::getUser( $appData, $username );

			return $user === false ? '' : Roles::notHeld( $appData, \Nino\Auth::permissions( $appData, $user ) );
		}

		/**
		 *	Whether the current user may act on $username, and whether it's themselves: the account itself
		 *	or one that holds the permission. The one question the action asks before the lock and
		 *	Roles::lockStored() asks under it, of the record config.php holds, as well
		 *
		 *	@param		array 		&$appData			(reference) Array with current app data
		 *	@param		string		$username 		Target username
		 *	@param		string		$perm					The permission that lets in an account other than the target itself
		 *
		 *	@return 	array										[ allowed, isSelf ]
		 */
		public static function authorize( array &$appData, string $username, string $perm ): array {

			$current 	= \Nino\Auth::getCurrentUser( $appData );
			$isSelf 	= $current !== false && $current['mail'] === $username;
			$canManage = \Nino\Auth::checkPermission( $appData, $perm );

			return [ $isSelf === true || $canManage === true, $isSelf ];
		}
	}
}
