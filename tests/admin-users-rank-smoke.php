<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-users-rank-smoke.php	Dependency-free smoke test for the one bound on every change of an
 *											account or a role in the Users panel: the signed-in account changes
 *											nothing that holds a permission it does not hold itself (see
 *											\Nino\Modules\Users\Admin::beyond() and Roles::beyond()). A matrix -
 *											the account that acts (a Developer, a manager of users, an editor)
 *											against the account or role it acts on (higher, equal, lower) against
 *											every action that changes one - and the flags the workbench draws its
 *											controls from. Runs against an isolated sandbox directory.
 *
 *	Usage: php tests/admin-users-rank-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'users-rank' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$manage	= \Nino\Modules\Users\Admin::MANAGE_PERM;
$unlock	= \Nino\Modules\Users\Lockout::MANAGE_PERM;
$text		= '/_admin/text/manage';
$types	= '/_admin/types/manage';

$appData['/nino/dir']								= '';
$appData['/nino/auth/maxtries']			= 3;
$appData['/nino/auth/cooldown']			= 3600;
$appData['/nino/install/completed']	= true;
$appData['/nino/auth/user']					= [];
$appData['/nino/auth/roles']				= [
	'developer'	=> [ 'label' => 'Developer', 'perms' => [ '/*' ] ],
	'manager'		=> [ 'label' => 'Manager', 'perms' => [ $manage, $unlock, $text ] ],
	'editor'		=> [ 'label' => 'Editor', 'perms' => [ $text ] ],
	'side'			=> [ 'label' => 'Side', 'perms' => [ $types ] ],
];

// The roles stand in config.php, as a request finds them: what a role action decides, it decides against that
$storeRoles = static function() use ( &$appData ): void {
	\Nino\AppData::writeContentData( $appData, [ '/nino/auth/roles' ] );
};

$storeRoles();

$password = 'correct horse battery staple';

foreach( [ 'dev' => 'developer', 'dev2' => 'developer', 'man' => 'manager', 'man2' => 'manager', 'ed' => 'editor', 'ed2' => 'editor' ] as $name => $role )
	\Nino\Auth::insertUser( $appData, $name. '@example.com', $password, [], $role );

$users = \Nino\Modules\Users\Admin::class;
$roles = \Nino\Modules\Users\Roles::class;
$lock	 = \Nino\Modules\Users\Lockout::class;

$call = static function( string $class, string $method, array $data ) use ( &$appData ): array {
	[ $status, $body ] = ninoCall( $appData, [ $class, $method ], $data );
	return [ $status, $body ];
};

// An account that is locked out right now - what apiUnlock lifts
$lockOut = static function( string $mail ) use ( &$appData ): void {
	\Nino\Filesystem::mutate( $appData, '/data/auth-tries.php', static function( mixed $state ) use ( $mail ): array {
		$state = is_array( $state ) ? $state : [];
		$state[$mail] = 0 - time() - 3600;
		return $state;
	} );
};
$isLocked = static function( string $mail ) use ( &$appData ): bool {
	return isset( \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] )[$mail] );
};

// The stored record under a mail, and under a role's id
$accountOf = static function( string $mail ) use ( &$appData ): string {
	return json_encode( $appData['/nino/auth/user'][$mail] ?? null );
};
$roleOf		 = static function( string $id ) use ( &$appData ): string {
	return json_encode( $appData['/nino/auth/roles'][$id] ?? null );
};

$signIn = static function( string $mail ) use ( &$appData, $password ): void {
	\Nino\Auth::loginUser( $appData, $mail, $password );
};

// The accounts that act, and what stands below, beside and above them: the
// role an account of that rank holds, and the permissions a role of that rank
// is made of. 'higher' and 'beside' hold what the actor does not
$actors = [
	'developer' => [ 'mail' => 'dev@example.com', 'manages' => true, 'targets' => [
		'equal'		=> [ 'role' => 'developer', 'perms' => [ '/*' ] ],
		'lower'		=> [ 'role' => 'manager', 'perms' => [ $manage, $unlock, $text ] ],
		'lowest'	=> [ 'role' => '', 'perms' => [] ],
	] ],
	'manager' => [ 'mail' => 'man@example.com', 'manages' => true, 'targets' => [
		'higher'	=> [ 'role' => 'developer', 'perms' => [ '/*' ] ],
		'beside'	=> [ 'role' => 'side', 'perms' => [ $types ] ],
		'equal'		=> [ 'role' => 'manager', 'perms' => [ $manage, $unlock, $text ] ],
		'lower'		=> [ 'role' => 'editor', 'perms' => [ $text ] ],
		'lowest'	=> [ 'role' => '', 'perms' => [] ],
	] ],
	'editor' => [ 'mail' => 'ed@example.com', 'manages' => false, 'targets' => [
		'higher'	=> [ 'role' => 'developer', 'perms' => [ '/*' ] ],
		'equal'		=> [ 'role' => 'editor', 'perms' => [ $text ] ],
		'lowest'	=> [ 'role' => '', 'perms' => [] ],
	] ],
];

// Every action that changes an account: what is asked of it (given the mail
// and the role the account holds) and whether it was done
$accountActions = [
	'rename' => [ $users, 'apiSave',
		static fn( string $mail, string $role ): array => [ 'username' => $mail, 'mail' => 'renamed.'. $mail ],
		static fn( array $a, string $mail, string $role ): bool => isset( $a['/nino/auth/user']['renamed.'. $mail] ) ],
	'name' => [ $users, 'apiSave',
		static fn( string $mail, string $role ): array => [ 'username' => $mail, 'mail' => $mail, 'name' => 'A Name' ],
		static fn( array $a, string $mail, string $role ): bool => ( $a['/nino/auth/user'][$mail]['name'] ?? '' ) === 'A Name' ],
	'password' => [ $users, 'apiSave',
		static fn( string $mail, string $role ): array => [ 'username' => $mail, 'mail' => $mail, 'pw' => 'another long password' ],
		static fn( array $a, string $mail, string $role ): bool => password_verify( 'another long password', (string) ( $a['/nino/auth/user'][$mail]['pw'] ?? '' ) ) ],
	'role, with the save' => [ $users, 'apiSave',
		static fn( string $mail, string $role ): array => [ 'username' => $mail, 'mail' => $mail, 'role' => $role === 'editor' ? '' : 'editor' ],
		static fn( array $a, string $mail, string $role ): bool => ( $a['/nino/auth/user'][$mail]['role'] ?? '' ) !== $role ],
	'role' => [ $users, 'apiSetRole',
		static fn( string $mail, string $role ): array => [ 'username' => $mail, 'role' => $role === 'editor' ? '' : 'editor' ],
		static fn( array $a, string $mail, string $role ): bool => ( $a['/nino/auth/user'][$mail]['role'] ?? '' ) !== $role ],
	'deactivate' => [ $users, 'apiStatus',
		static fn( string $mail, string $role ): array => [ 'username' => $mail, 'active' => false ],
		static fn( array $a, string $mail, string $role ): bool => ( $a['/nino/auth/user'][$mail]['status'] ?? 0 ) !== \Nino\Auth::STATUS_ACTIVE ],
	'activate' => [ $users, 'apiStatus',
		static fn( string $mail, string $role ): array => [ 'username' => $mail, 'active' => true ],
		static fn( array $a, string $mail, string $role ): bool => ( $a['/nino/auth/user'][$mail]['status'] ?? 0 ) === \Nino\Auth::STATUS_ACTIVE ],
	'log out everywhere' => [ $users, 'apiLogoutAll',
		static fn( string $mail, string $role ): array => [ 'username' => $mail ],
		static fn( array $a, string $mail, string $role ): bool => ( $a['/nino/auth/user'][$mail]['sessions'] ?? [ 'x' ] ) === [] ],
	'delete' => [ $users, 'apiDelete',
		static fn( string $mail, string $role ): array => [ 'username' => $mail ],
		static fn( array $a, string $mail, string $role ): bool => isset( $a['/nino/auth/user'][$mail] ) === false ],
	'lift the lock' => [ $lock, 'apiUnlock',
		static fn( string $mail, string $role ): array => [ 'username' => $mail ],
		static fn( array $a, string $mail, string $role ): bool => $isLocked( $mail ) === false ],
];

// ...and every action that changes a role: what is asked (given its id and
// what it holds), whether it was done, and whether there is anything to do
$roleActions = [
	'rename' => [ $roles, 'apiSave',
		static fn( string $id, array $perms ): array => [ 'id' => $id, 'label' => 'Renamed', 'perms' => $perms ],
		static fn( array $a, string $id, array $perms ): bool => ( $a['/nino/auth/roles'][$id]['label'] ?? '' ) === 'Renamed',
		static fn( array $perms ): bool => true ],
	'change what it grants' => [ $roles, 'apiSave',
		static fn( string $id, array $perms ): array => [ 'id' => $id, 'label' => 'Target', 'perms' => $perms === [ $text ] ? [ $manage ] : [ $text ] ],
		static fn( array $a, string $id, array $perms ): bool => ( $a['/nino/auth/roles'][$id]['perms'] ?? $perms ) !== $perms,
		static fn( array $perms ): bool => true ],
	'empty' => [ $roles, 'apiSave',
		static fn( string $id, array $perms ): array => [ 'id' => $id, 'label' => 'Target', 'perms' => [] ],
		static fn( array $a, string $id, array $perms ): bool => ( $a['/nino/auth/roles'][$id]['perms'] ?? $perms ) === [],
		static fn( array $perms ): bool => $perms !== [] ],
	'delete' => [ $roles, 'apiDelete',
		static fn( string $id, array $perms ): array => [ 'id' => $id ],
		static fn( array $a, string $id, array $perms ): bool => isset( $a['/nino/auth/roles'][$id] ) === false,
		static fn( array $perms ): bool => true ],
];

echo "Users and roles - nothing is changed that holds more than the account that changes it\n";

$wrong	= [];
$cells	= 0;
$refusedCells	= 0;

foreach( $actors as $actorName => $actor ) {

	$signIn( $actor['mail'] );

	foreach( $actor['targets'] as $rank => $target ) {

		// Higher and beside hold what the actor does not; an editor manages nobody
		$refused = $actor['manages'] === false || $rank === 'higher' || $rank === 'beside';

		foreach( $accountActions as $action => [ $class, $method, $ask, $done ] ) {

			$mail = 'target@example.com';

			\Nino\Auth::insertUser( $appData, $mail, 'target password', [], $target['role'] );
			$appData['/nino/auth/user'][$mail]['sessions'] = [ 'token' => [ 'time' => time() ] ];

			if( $action === 'activate' )
				\Nino\Auth::setStatus( $appData, $mail, false );

			if( $action === 'lift the lock' )
				$lockOut( $mail );

			$before = $accountOf( $mail );
			[ $status ] = $call( $class, $method, $ask( $mail, $target['role'] ) );
			$did = $done( $appData, $mail, $target['role'] );

			$cells++;

			if( $refused === true ) {
				$refusedCells++;

				if( $status !== 403 || $did === true || $accountOf( $mail ) !== $before )
					$wrong[] = "$actorName x $rank x $action: $status". ( $did === true ? ', done' : '' );
			}
			elseif( $status !== 200 || $did === false )
				$wrong[] = "$actorName x $rank x $action: $status". ( $did === true ? '' : ', not done' );

			ninoDeleteUser( $appData, $mail );
			ninoDeleteUser( $appData, 'renamed.'. $mail );
		}

		foreach( $roleActions as $action => [ $class, $method, $ask, $done, $applies ] ) {

			if( $applies( $target['perms'] ) === false )
				continue;

			$id = 'target';

			$appData['/nino/auth/roles'][$id] = [ 'label' => 'Target', 'perms' => $target['perms'] ];
			$storeRoles();

			$before = $roleOf( $id );
			[ $status ] = $call( $class, $method, $ask( $id, $target['perms'] ) );
			$did = $done( $appData, $id, $target['perms'] );

			$cells++;

			if( $refused === true ) {
				$refusedCells++;

				if( $status !== 403 || $did === true || $roleOf( $id ) !== $before )
					$wrong[] = "$actorName x $rank x role $action: $status". ( $did === true ? ', done' : '' );
			}
			elseif( $status !== 200 || $did === false )
				$wrong[] = "$actorName x $rank x role $action: $status". ( $did === true ? '' : ', not done' );

			unset( $appData['/nino/auth/roles'][$id] );
			$storeRoles();
		}
	}
}

check( 'the matrix is there: '. $cells. ' cells, '. $refusedCells. ' of them refusals', $cells >= 150 && $refusedCells >= 69 );
check( 'every cell (actor x target x action) is a 403 that leaves its target as it was, or a 200 that does what it was asked'. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 8 ) ) ), $wrong === [] );

echo "\n";
echo "The refusal\n";

$signIn( 'man@example.com' );

[ $status, $body ] = $call( $users, 'apiSave', [ 'username' => 'dev2@example.com', 'mail' => 'dev3@example.com' ] );
check( 'renaming a Developer account answers 403 with the code of this refusal, naming the permission', $status === 403 && ( $body['code'] ?? '' ) === 'users_account_beyond' && ( $body['params'] ?? [] ) === [ '/*' ] );
[ $status, $body ] = $call( $roles, 'apiSave', [ 'id' => 'developer', 'label' => 'Developers', 'perms' => [ '/*' ] ] );
check( 'relabelling the Developer role answers 403 with the code of this refusal', $status === 403 && ( $body['code'] ?? '' ) === 'roles_role_beyond' && ( $body['params'] ?? [] ) === [ '/*' ] );

// The password of a wider account has words of its own, and keeps them
[ $status, $body ] = $call( $users, 'apiSave', [ 'username' => 'dev2@example.com', 'mail' => 'dev2@example.com', 'pw' => 'a password of my choosing' ] );
check( 'setting the password of a Developer is refused as the change of an account beyond the manager is', $status === 403 && ( $body['code'] ?? '' ) === 'users_account_beyond' && ( $body['params'] ?? [] ) === [ '/*' ] );

// A role that is wide for a panel lets in all of it; an account that holds
// the panel in detail does not hold what that role is
$elementsDoor	= '/_admin/elements/manage';
$elementsOne	= '/_admin/elements/testtype/insert';
$appData['/nino/auth/roles']['panel']		= [ 'label' => 'Panel', 'perms' => [ $elementsDoor ] ];
$storeRoles();
$appData['/nino/auth/roles']['partial']	= [ 'label' => 'Partial', 'perms' => [ $manage, $elementsDoor, $elementsOne ] ];
$storeRoles();
\Nino\Auth::insertUser( $appData, 'partial@example.com', $password, [], 'partial' );
\Nino\Auth::insertUser( $appData, 'panel@example.com', $password, [], 'panel' );
$signIn( 'partial@example.com' );
check( 'the fixture: the account is described in detail for the elements panel', \Nino\Admin\Admin::isScoped( $appData, '/_admin/elements/' ) === true );
[ $status, $body ] = $call( $users, 'apiStatus', [ 'username' => 'panel@example.com', 'active' => false ] );
check( 'an account that holds a panel in detail does not deactivate one that is let into all of it - the refusal names the panel', $status === 403 && ( $body['code'] ?? '' ) === 'users_account_beyond_scoped'
	&& \Nino\Auth::getUser( $appData, 'panel@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );
[ $status ] = $call( $users, 'apiLogoutAll', [ 'username' => 'panel@example.com' ] );
check( '...nor ends its sessions', $status === 403 );
ninoDeleteUser( $appData, 'panel@example.com' );
[ $status, $body ] = $call( $roles, 'apiDelete', [ 'id' => 'panel' ] );
check( '...nor deletes the role it holds, which nobody holds now', $status === 403 && ( $body['code'] ?? '' ) === 'roles_role_beyond_scoped' && isset( $appData['/nino/auth/roles']['panel'] ) === true );
$signIn( 'dev@example.com' );
[ $status ] = $call( $roles, 'apiDelete', [ 'id' => 'panel' ] );
check( 'the Developer deletes it', $status === 200 && isset( $appData['/nino/auth/roles']['panel'] ) === false );
ninoDeleteUser( $appData, 'partial@example.com' );
unset( $appData['/nino/auth/roles']['partial'] );
$storeRoles();

// A role that holds nothing beyond the manager is still not the manager's to
// change while an account beyond the manager holds it: that account is changed
// through its role
$appData['/nino/auth/roles']['shared'] = [ 'label' => 'Shared', 'perms' => [ $text ] ];
$storeRoles();
\Nino\Auth::insertUser( $appData, 'shared@example.com', $password, [ $types ], 'shared' );
$signIn( 'man@example.com' );
[ $status, $body ] = $call( $roles, 'apiSave', [ 'id' => 'shared', 'label' => 'Shared', 'perms' => [] ] );
check( 'a manager does not empty a role it holds in full while an account holds it that has a permission of its own beyond the manager',
	$status === 403 && ( $body['code'] ?? '' ) === 'roles_role_held_beyond' && ( $body['params'] ?? [] ) === [ $types ] && $appData['/nino/auth/roles']['shared']['perms'] === [ $text ] );
[ $status ] = $call( $roles, 'apiSave', [ 'id' => 'shared', 'label' => 'Renamed', 'perms' => [ $text ] ] );
check( '...nor rename it', $status === 403 && $appData['/nino/auth/roles']['shared']['label'] === 'Shared' );
[ , $body ] = $call( $roles, 'apiList', [] );
check( '...and the list says so, and that it is the holder that is beyond and not the role', array_column( $body['roles'], 'canChange', 'id' )['shared'] === false
	&& array_column( $body['roles'], 'heldBeyond', 'id' )['shared'] === true && array_column( $body['roles'], 'heldBeyond', 'id' )['developer'] === false );
$appData['/nino/auth/roles']['shared']['perms'] = [ $text, $types ];
$storeRoles();
[ $status, $body ] = $call( $roles, 'apiSave', [ 'id' => 'shared', 'label' => 'Shared', 'perms' => [ $text, $types ] ] );
check( 'a role that holds the permission itself is refused for that, whoever holds it', $status === 403 && ( $body['code'] ?? '' ) === 'roles_role_beyond' );
$appData['/nino/auth/roles']['shared']['perms'] = [ $text ];
$storeRoles();
ninoDeleteUser( $appData, 'shared@example.com' );
[ $status ] = $call( $roles, 'apiSave', [ 'id' => 'shared', 'label' => 'Renamed', 'perms' => [ $text ] ] );
check( 'with nobody beyond the manager holding it the role is the manager\'s to change', $status === 200 && $appData['/nino/auth/roles']['shared']['label'] === 'Renamed' );
unset( $appData['/nino/auth/roles']['shared'] );
$storeRoles();

echo "\n";
echo "What the workbench draws its controls from\n";

$signIn( 'man@example.com' );
[ , $body ] = $call( $users, 'apiList', [] );
$flags = array_column( $body['users'], 'canChange', 'mail' );
check( 'the list says per account whether it may be changed: the Developers not, the manager\'s own, its equal and the editors so', $flags['dev@example.com'] === false && $flags['dev2@example.com'] === false
	&& $flags['man@example.com'] === true && $flags['man2@example.com'] === true && $flags['ed@example.com'] === true && $flags['ed2@example.com'] === true );
check( 'the roles in that list say which of them the account may hand out', array_column( $body['roles'], 'canGrant', 'id' ) === [ 'developer' => false, 'manager' => true, 'editor' => true, 'side' => false ] );
[ , $body ] = $call( $roles, 'apiList', [] );
$flags = array_column( $body['roles'], 'canChange', 'id' );
check( '...and per role', $flags['developer'] === false && $flags['side'] === false && $flags['manager'] === true && $flags['editor'] === true );

$lockOut( 'dev2@example.com' );
$lockOut( 'ed2@example.com' );
[ , $body ] = $call( $lock, 'apiList', [] );
$flags = array_column( $body['locked'], 'canChange', 'mail' );
check( 'the locked accounts carry it, so a lock that is not the manager\'s to lift has no button', $flags['dev2@example.com'] === false && $flags['ed2@example.com'] === true );

$signIn( 'dev@example.com' );
[ , $body ] = $call( $users, 'apiList', [] );
check( 'the Developer may change every account', in_array( false, array_column( $body['users'], 'canChange' ), true ) === false );
[ , $body ] = $call( $roles, 'apiList', [] );
check( '...and every role', in_array( false, array_column( $body['roles'], 'canChange' ), true ) === false );

$signIn( 'ed@example.com' );
[ , $body ] = $call( $users, 'apiList', [] );
check( 'an editor sees itself, and may change that', count( $body['users'] ) === 1 && $body['users'][0]['canChange'] === true );

echo "\n";
echo "What stays\n";

[ $status, $body ] = $call( $users, 'apiSave', [ 'username' => 'ed@example.com', 'mail' => 'ed.renamed@example.com', 'currentPassword' => $password ] );
check( 'an editor renames its own account with its password', $status === 200 && ( $body['mail'] ?? '' ) === 'ed.renamed@example.com' );
[ $status ] = $call( $users, 'apiSave', [ 'username' => 'ed2@example.com', 'mail' => 'ed2.renamed@example.com' ] );
check( '...and nobody else\'s', $status === 403 && isset( $appData['/nino/auth/user']['ed2@example.com'] ) === true );

$signIn( 'dev@example.com' );
[ $status ] = $call( $users, 'apiDelete', [ 'username' => 'dev@example.com' ] );
check( 'the Developer does not delete its own account', $status === 400 );
[ $status ] = $call( $users, 'apiDelete', [ 'username' => 'dev2@example.com' ] );
check( 'a Developer deletes a Developer while another full access stays', $status === 200 && \Nino\Auth::getUser( $appData, 'dev2@example.com' ) === false );
[ $status ] = $call( $users, 'apiSetRole', [ 'username' => 'man@example.com', 'role' => 'editor' ] );
check( '...and gives a manager another role', $status === 200 && \Nino\Auth::getUser( $appData, 'man@example.com' )['role'] === 'editor' );

echo "\n";
echo "What the workbench's accounts panel relies on - through the dispatcher the browser reaches every action by\n";

/*	What was pinned before: the rank matrix above drives the api methods of the panel directly, one account against the next, and admin-smoke.php and
	admin-system-smoke.php hold what each method answers for a plain user, a manager and a developer. What no suite held is the entry the browser calls -
	Admin::handlePost(), with a session or without one and a token or none - for each of the seven actions of the account list and its form, the last
	account with full access losing its role through users/save (only through users/role), and the effect on the sessions of a password that is set	*/
$post = static function( string $action, array $data = [] ) use ( &$appData ): array {
	[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], $data, $action );
	return [ $status, $body ];
};
$store = static function() use ( &$appData ): string {
	return json_encode( [ $appData['/nino/auth/user'] ?? null, $appData['/nino/auth/roles'] ?? null ] );
};

// The last account with full access is not a manager's to change through the one save of the account's form either: it is beyond the manager, whatever the change
$signIn( 'man2@example.com' );
$fullAccess = array_keys( array_filter( $appData['/nino/auth/user'], static fn( array $user ): bool => in_array( '/*', \Nino\Auth::permissions( $appData, $user ), true ) ) );
$before = $accountOf( 'dev@example.com' );
[ $status, $body ] = $post( 'users/save', [ 'username' => 'dev@example.com', 'mail' => 'dev@example.com', 'role' => 'editor' ] );
check( 'the fixture: one account holds full access, and a manager asks that its role is taken away through users/save', $fullAccess === [ 'dev@example.com' ] );
check( 'the last account with full access is refused a manager through users/save as an account beyond it: 403, "users_account_beyond", nothing written', $status === 403 && ( $body['code'] ?? '' ) === 'users_account_beyond' && $accountOf( 'dev@example.com' ) === $before );
check( '...the refusal names no field of the form: it is said in the line', array_key_exists( 'field', $body ?? [] ) === false );

\Nino\Auth::insertUser( $appData, 'dev3@example.com', $password, [], 'developer' );
$signIn( 'dev@example.com' );
[ $status ] = $post( 'users/save', [ 'username' => 'dev3@example.com', 'mail' => 'dev3@example.com', 'role' => 'editor' ] );
check( 'while another account holds full access the same save goes through - the bound is the last one', $status === 200 && \Nino\Auth::getUser( $appData, 'dev3@example.com' )['role'] === 'editor' );
ninoDeleteUser( $appData, 'dev3@example.com' );

// A manager who sets another account's password ends every session of that account
$signIn( 'man2@example.com' );
$appData['/nino/auth/user']['ed2@example.com']['sessions'] = [ 'one' => [ 'time' => time() ], 'two' => [ 'time' => time() ] ];
[ $status ] = $post( 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'pw' => 'a password of my choosing' ] );
check( 'a manager who sets the password of another account ends that account\'s sessions', $status === 200 && ( $appData['/nino/auth/user']['ed2@example.com']['sessions'] ?? [ 'x' ] ) === []
	&& password_verify( 'a password of my choosing', (string) $appData['/nino/auth/user']['ed2@example.com']['pw'] ) === true );
check( '...and keeps its own session', \Nino\Auth::getCurrentUser( $appData ) !== false && \Nino\Auth::getCurrentUser( $appData )['mail'] === 'man2@example.com' );
$appData['/nino/auth/user']['ed2@example.com']['sessions'] = [ 'one' => [ 'time' => time() ] ];
[ $status ] = $post( 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2.renamed@example.com' ] );
check( '...a change of the address that is no password ends none: the account is the same, and its sessions go with it', $status === 200 && isset( $appData['/nino/auth/user']['ed2@example.com'] ) === false && array_keys( $appData['/nino/auth/user']['ed2.renamed@example.com']['sessions'] ?? [] ) === [ 'one' ] );

// Who may call each of the seven actions
$actionsOf = [
	'users/list'			=> static fn( string $mail ): array => [],
	'users/create'		=> static fn( string $mail ): array => [ 'mail' => 'made@example.com', 'pw' => 'a long enough password', 'role' => 'editor', 'name' => '' ],
	'users/save'			=> static fn( string $mail ): array => [ 'username' => $mail, 'mail' => $mail, 'name' => 'Set through the dispatcher' ],
	'users/delete'		=> static fn( string $mail ): array => [ 'username' => $mail ],
	'users/logoutall'	=> static fn( string $mail ): array => [ 'username' => $mail ],
	'users/role'			=> static fn( string $mail ): array => [ 'username' => $mail, 'role' => '' ],
	'users/status'		=> static fn( string $mail ): array => [ 'username' => $mail, 'active' => false ],
];

\Nino\Auth::insertUser( $appData, 'tmp@example.com', $password, [], 'editor' );

// No session: 401, the code "session", nothing written
$signedOut = $appData;
unset( $signedOut['./nino/auth/current'] );
$bad = [];
foreach( $actionsOf as $action => $ask ) {
	$was = json_encode( [ $signedOut['/nino/auth/user'], $signedOut['/nino/auth/roles'] ] );
	[ $status, $body ] = ninoCall( $signedOut, [ \Nino\Admin\Admin::class, 'handlePost' ], $ask( 'tmp@example.com' ), $action );
	if( $status !== 401 || ( $body['code'] ?? '' ) !== 'session' || json_encode( [ $signedOut['/nino/auth/user'], $signedOut['/nino/auth/roles'] ] ) !== $was )
		$bad[] = $action. ': '. $status;
}
check( 'with no session every one of the seven actions is 401 with the code "session", and writes nothing'. ( $bad === [] ? '' : ' - '. implode( ', ', $bad ) ), $bad === [] );

// A token the guard refused: 403, the code "csrf", the action is not called
$signIn( 'man2@example.com' );
$bad = [];
foreach( $actionsOf as $action => $ask ) {
	$was = $store();
	$_POST['action'] = $action;
	$_POST['data'] = json_encode( $ask( 'tmp@example.com' ) );
	$refused = [ '/nino/http/response' => [ 'statusCode' => 403, 'body' => false ], './nino/csrf/blocked' => true ];
	\Nino\Admin\Admin::handlePost( $appData, $refused );
	if( $refused['/nino/http/response']['statusCode'] !== 403 || ( $refused['/nino/http/response']['body']['code'] ?? '' ) !== 'csrf' || $store() !== $was )
		$bad[] = $action. ': '. $refused['/nino/http/response']['statusCode'];
}
check( 'the dispatcher respects a request the token guard refused (it arrives marked blocked): 403 with the code "csrf" for every one of the seven actions, even for a manager, and the action is not called'. ( $bad === [] ? '' : ' - '. implode( ', ', $bad ) ), $bad === [] );

// An account without the permission to manage users: the list is its own row, the ones that manage are 403, the ones for an account are its own or 403
$signIn( 'ed.renamed@example.com' );
$bad = [];
foreach( [ 'users/create', 'users/delete', 'users/role', 'users/status' ] as $action ) {
	$was = $store();
	[ $status, $body ] = $post( $action, $actionsOf[$action]( 'tmp@example.com' ) );
	if( $status !== 403 || array_key_exists( 'code', $body ?? [] ) === true || $store() !== $was )
		$bad[] = $action. ': '. $status;
}
check( 'without the permission to manage users users/create, users/delete, users/role and users/status are 403 - no code, nothing written'. ( $bad === [] ? '' : ' - '. implode( ', ', $bad ) ), $bad === [] );
foreach( [ 'users/delete', 'users/role', 'users/status' ] as $action ) {
	$was = $store();
	[ $status ] = $post( $action, $actionsOf[$action]( 'ed.renamed@example.com' ) );
	if( $status !== 403 || $store() !== $was )
		$bad[] = $action. ' of the own account: '. $status;
}
check( '...nor of the account\'s own record: it neither deletes, nor changes the role of, nor deactivates itself through them', $bad === [] );
[ $status, $body ] = $post( 'users/list' );
check( 'users/list answers every account, and for one that does not manage users its own row only, with the flag that says it cannot manage', $status === 200 && count( $body['users'] ) === 1 && $body['users'][0]['mail'] === 'ed.renamed@example.com' && $body['canManage'] === false && $body['users'][0]['isSelf'] === true );
$was = $store();
[ $status ] = $post( 'users/save', $actionsOf['users/save']( 'tmp@example.com' ) );
[ $statusOut ] = $post( 'users/logoutall', $actionsOf['users/logoutall']( 'tmp@example.com' ) );
check( 'users/save and users/logoutall are the own account\'s or a manager\'s: for another account an editor is 403 and nothing is written', $status === 403 && $statusOut === 403 && $store() === $was );
[ $status, $body ] = $post( 'users/save', [ 'username' => 'ed.renamed@example.com', 'mail' => 'ed.renamed@example.com', 'name' => 'Own name', 'currentPassword' => $password ] );
check( '...the own account is let through, with its current password', $status === 200 && ( $body['name'] ?? '' ) === 'Own name' && ( $appData['/nino/auth/user']['ed.renamed@example.com']['name'] ?? '' ) === 'Own name' );
$was = $store();
[ $status, $body ] = $post( 'users/save', [ 'username' => 'ed.renamed@example.com', 'mail' => 'ed.renamed@example.com', 'name' => 'Another name', 'currentPassword' => 'not the password' ] );
check( '...and refused with a wrong one: 401, "wrong_password" at the field currentPassword, nothing is written', $status === 401 && ( $body['code'] ?? '' ) === 'wrong_password' && ( $body['field'] ?? '' ) === 'currentPassword' && $store() === $was
	&& ( $appData['/nino/auth/user']['ed.renamed@example.com']['name'] ?? '' ) === 'Own name' );

// A manager
$signIn( 'man2@example.com' );
[ $status, $body ] = $post( 'users/list' );
check( 'a manager is answered every account, and says that it may manage', $status === 200 && count( $body['users'] ) === count( $appData['/nino/auth/user'] ) && $body['canManage'] === true );
[ $status ] = $post( 'users/save', $actionsOf['users/save']( 'tmp@example.com' ) );
check( 'a manager saves the account of another that is below it', $status === 200 && ( $appData['/nino/auth/user']['tmp@example.com']['name'] ?? '' ) === 'Set through the dispatcher' );
$appData['/nino/auth/user']['tmp@example.com']['sessions'] = [ 'one' => [ 'time' => time() ] ];
[ $status, $body ] = $post( 'users/logoutall', $actionsOf['users/logoutall']( 'tmp@example.com' ) );
check( '...and ends its sessions - the answer says that they are not its own', $status === 200 && ( $body['loggedOutSelf'] ?? null ) === false && ( $appData['/nino/auth/user']['tmp@example.com']['sessions'] ?? [ 'x' ] ) === [] );
[ $status ] = $post( 'users/role', [ 'username' => 'tmp@example.com', 'role' => '' ] );
check( '...takes its role away', $status === 200 && ( $appData['/nino/auth/user']['tmp@example.com']['role'] ?? 'x' ) === '' );
[ $status ] = $post( 'users/status', $actionsOf['users/status']( 'tmp@example.com' ) );
check( '...deactivates it', $status === 200 && ( $appData['/nino/auth/user']['tmp@example.com']['status'] ?? 1 ) === \Nino\Auth::STATUS_DISABLED );
[ $status, $body ] = $post( 'users/create', $actionsOf['users/create']( '' ) );
check( '...creates an account with a role it may hand out', $status === 200 && ( $body['mail'] ?? '' ) === 'made@example.com' && ( $appData['/nino/auth/user']['made@example.com']['role'] ?? '' ) === 'editor' );
[ $status ] = $post( 'users/delete', [ 'username' => 'made@example.com' ] );
check( '...and deletes one', $status === 200 && isset( $appData['/nino/auth/user']['made@example.com'] ) === false );
[ $status ] = $post( 'users/delete', [ 'username' => 'man2@example.com' ] );
check( '...but not its own account', $status === 400 && isset( $appData['/nino/auth/user']['man2@example.com'] ) === true );
[ $status ] = $post( 'users/status', [ 'username' => 'man2@example.com', 'active' => false ] );
check( '...nor deactivates it', $status === 400 && \Nino\Auth::getUser( $appData, 'man2@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );
ninoDeleteUser( $appData, 'tmp@example.com' );

// A change of the own password keeps the session it is made in, and ends every other
$signIn( 'ed.renamed@example.com' );
$current = \Nino\Runtime::getSessionValue( $appData, './nino/auth/token', '' );
$appData['/nino/auth/user']['ed.renamed@example.com']['sessions']['another-browser'] = [ 'time' => time() ];
[ $status ] = $post( 'users/save', [ 'username' => 'ed.renamed@example.com', 'mail' => 'ed.renamed@example.com', 'pw' => 'a new password of my own', 'currentPassword' => $password ] );
check( 'a change of the own password ends the sessions of the account but the one it is made in', $status === 200 && is_string( $current ) === true && $current !== '' && array_keys( $appData['/nino/auth/user']['ed.renamed@example.com']['sessions'] ?? [] ) === [ $current ]
	&& password_verify( 'a new password of my own', (string) $appData['/nino/auth/user']['ed.renamed@example.com']['pw'] ) === true && \Nino\Auth::getCurrentUser( $appData ) !== false );

ninoDone( $appData );
