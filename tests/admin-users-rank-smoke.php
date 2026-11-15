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

			\Nino\Auth::deleteUser( $appData, $mail );
			\Nino\Auth::deleteUser( $appData, 'renamed.'. $mail );
		}

		foreach( $roleActions as $action => [ $class, $method, $ask, $done, $applies ] ) {

			if( $applies( $target['perms'] ) === false )
				continue;

			$id = 'target';

			$appData['/nino/auth/roles'][$id] = [ 'label' => 'Target', 'perms' => $target['perms'] ];

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
check( 'setting the password of a Developer answers with its own code', $status === 403 && ( $body['code'] ?? '' ) === 'users_password_too_wide' );

// A role that is wide for a panel lets in all of it; an account that holds
// the panel in detail does not hold what that role is
$elementsDoor	= '/_admin/elements/manage';
$elementsOne	= '/_admin/elements/testtype/insert';
$appData['/nino/auth/roles']['panel']		= [ 'label' => 'Panel', 'perms' => [ $elementsDoor ] ];
$appData['/nino/auth/roles']['partial']	= [ 'label' => 'Partial', 'perms' => [ $manage, $elementsDoor, $elementsOne ] ];
\Nino\Auth::insertUser( $appData, 'partial@example.com', $password, [], 'partial' );
\Nino\Auth::insertUser( $appData, 'panel@example.com', $password, [], 'panel' );
$signIn( 'partial@example.com' );
check( 'the fixture: the account is described in detail for the elements panel', \Nino\Admin\Admin::isScoped( $appData, '/_admin/elements/' ) === true );
[ $status, $body ] = $call( $users, 'apiStatus', [ 'username' => 'panel@example.com', 'active' => false ] );
check( 'an account that holds a panel in detail does not deactivate one that is let into all of it - the refusal names the panel', $status === 403 && ( $body['code'] ?? '' ) === 'users_account_beyond_scoped'
	&& \Nino\Auth::getUser( $appData, 'panel@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );
[ $status ] = $call( $users, 'apiLogoutAll', [ 'username' => 'panel@example.com' ] );
check( '...nor ends its sessions', $status === 403 );
\Nino\Auth::deleteUser( $appData, 'panel@example.com' );
[ $status, $body ] = $call( $roles, 'apiDelete', [ 'id' => 'panel' ] );
check( '...nor deletes the role it holds, which nobody holds now', $status === 403 && ( $body['code'] ?? '' ) === 'roles_role_beyond_scoped' && isset( $appData['/nino/auth/roles']['panel'] ) === true );
$signIn( 'dev@example.com' );
[ $status ] = $call( $roles, 'apiDelete', [ 'id' => 'panel' ] );
check( 'the Developer deletes it', $status === 200 && isset( $appData['/nino/auth/roles']['panel'] ) === false );
\Nino\Auth::deleteUser( $appData, 'partial@example.com' );
unset( $appData['/nino/auth/roles']['partial'] );

// A role that holds nothing beyond the manager is still not the manager's to
// change while an account beyond the manager holds it: that account is changed
// through its role
$appData['/nino/auth/roles']['shared'] = [ 'label' => 'Shared', 'perms' => [ $text ] ];
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
[ $status, $body ] = $call( $roles, 'apiSave', [ 'id' => 'shared', 'label' => 'Shared', 'perms' => [ $text, $types ] ] );
check( 'a role that holds the permission itself is refused for that, whoever holds it', $status === 403 && ( $body['code'] ?? '' ) === 'roles_role_beyond' );
$appData['/nino/auth/roles']['shared']['perms'] = [ $text ];
\Nino\Auth::deleteUser( $appData, 'shared@example.com' );
[ $status ] = $call( $roles, 'apiSave', [ 'id' => 'shared', 'label' => 'Renamed', 'perms' => [ $text ] ] );
check( 'with nobody beyond the manager holding it the role is the manager\'s to change', $status === 200 && $appData['/nino/auth/roles']['shared']['label'] === 'Renamed' );
unset( $appData['/nino/auth/roles']['shared'] );

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

ninoDone( $appData );
