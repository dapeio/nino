<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-users-server-smoke.php	Dependency-free smoke test for what the server of the Users panel answers and leaves on disk, through
 *											the entry the browser calls: POST /_admin - the real token check, the dispatcher, the session - with
 *											every request booted from config.php as a live request finds it. The order the actions on an account
 *											and on a role refuse in (the right, the posted values and the own account, the account existing, its
 *											reach, then the change itself), one write of config.php for one save, the counters that follow a
 *											rename, the roles written under the lock of config.php, the Dashboard tile, the line of the activity
 *											log, the rule for the password that is set. Runs against an isolated sandbox directory.
 *
 *	Usage: php tests/admin-users-server-smoke.php
 */

require __DIR__. '/harness.php';
require __DIR__. '/fixtures/faultfs.php';

const PW = 'correct horse battery staple';

$manage	= \Nino\Modules\Users\Admin::MANAGE_PERM;
$unlock	= \Nino\Modules\Users\Lockout::MANAGE_PERM;
$text		= '/_admin/text/manage';
$types	= '/_admin/types/manage';
$door		= '/_admin/elements/manage';
$entry	= '/_admin/elements/testtype/insert';

$app = ninoSandbox( 'users-server' );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$app['/nino/dir']								= '';
$app['/nino/auth/maxtries']			= 3;
$app['/nino/auth/cooldown']			= 3600;
$app['/nino/install/completed']	= true;
// The daily backup and the line of the first request of a session are not what is tested here
$app['/nino/admin/backups']			= false;
$app['/nino/auth/user']					= [];
$app['/nino/auth/roles']				= [
	'developer'	=> [ 'label' => 'Developer', 'perms' => [ '/*' ] ],
	'manager'		=> [ 'label' => 'Manager', 'perms' => [ $manage, $unlock, $text ] ],
	'editor'		=> [ 'label' => 'Editor', 'perms' => [ $text ] ],
	'side'			=> [ 'label' => 'Side', 'perms' => [ $types ] ],
	'rpwmgr'		=> [ 'label' => 'Recovery password', 'perms' => [ \Nino\Modules\Users\RecoveryPassword::MANAGE_PERM ] ],
	'spare'			=> [ 'label' => 'Spare', 'perms' => [ $text ] ],
	'wide'			=> [ 'label' => 'Wide', 'perms' => [ $types ] ],
	'small'			=> [ 'label' => 'Small', 'perms' => [ $manage ] ],
	// The lead manages accounts and holds the elements panel in detail, one entry of it; the panel role is let into all of it
	'lead'			=> [ 'label' => 'Lead', 'perms' => [ $manage, $unlock, $door, $entry ] ],
	'panel'			=> [ 'label' => 'Panel', 'perms' => [ $door ] ],
];

\Nino\AppData::writeContentData( $app, [ '/nino/auth/roles', '/nino/auth/maxtries', '/nino/auth/cooldown', '/nino/install/completed' ] );

$accounts = [ 'dev' => 'developer', 'dev2' => 'developer', 'man' => 'manager', 'ed' => 'editor', 'ed2' => 'editor', 'none' => '', 'rpw' => 'rpwmgr', 'lead' => 'lead', 'panel' => 'panel' ];

foreach( $accounts as $local => $role )
	\Nino\Auth::insertUser( $app, $local. '@example.com', PW, [], $role );

// Every account that acts has signed in once: its session is on disk and in the PHP session of a request that sends its token
$sessions = [];

foreach( $accounts as $local => $role ) {
	\Nino\Auth::loginUser( $app, $local. '@example.com', PW );
	$sessions[$local] = \Nino\Runtime::getSessionValue( $app, './nino/auth/token', '' );
}

ninoWireLive( $app );

/**
 *	What is on disk that a request may change: config.php, the tries file, the recovery secret and the activity log
 */
$disk = static function() use ( &$app ): array {

	$dir 	= ninoSandboxDir( $app ). '/private';
	$out	= [];

	foreach( [ '/config.php', '/data/auth-tries.php', '/.auth/pw.php' ] as $file )
		$out[$file] = is_file( $dir. $file ) === true ? (string) file_get_contents( $dir. $file ) : null;

	foreach( glob( $dir. '/.logs/*' ) ?: [] as $file )
		$out['/.logs/'. basename( $file )] = (string) file_get_contents( $file );

	ksort( $out );

	return $out;
};

// What a file under private/ holds as php reads it
$read = static function( string $file ) use ( &$app ): mixed {

	$path = ninoSandboxDir( $app ). '/private'. $file;

	return is_file( $path ) === true ? include $path : null;
};

$tick = 0;

// The disk as it was: the bytes put back, with a modification time no earlier read has seen
$restore = static function( array $to ) use ( &$app, &$tick ): void {

	$dir = ninoSandboxDir( $app ). '/private';

	foreach( glob( $dir. '/.logs/*' ) ?: [] as $file )
		if( array_key_exists( '/.logs/'. basename( $file ), $to ) === false )
			unlink( $file );

	foreach( $to as $file => $bytes ) {

		if( $bytes === null ) {

			if( is_file( $dir. $file ) === true )
				unlink( $dir. $file );

			continue;
		}

		if( is_dir( dirname( $dir. $file ) ) === false )
			mkdir( dirname( $dir. $file ), 0755, true );

		file_put_contents( $dir. $file, $bytes );
		touch( $dir. $file, time() + ++$tick );
	}

	unset( $app['./nino/filesystem/cache'] );
};

// The account of the session a request is sent with: nobody for the visitor
$as = static function( string $who ) use ( &$app, &$sessions ): void {

	\Nino\Runtime::setSessionValue( $app, './admin/loginLoggedFor', $who. '@example.com' );

	if( $who === 'visitor' ) {
		\Nino\Runtime::unsetSessionValue( $app, './nino/auth/current' );
		\Nino\Runtime::unsetSessionValue( $app, './nino/auth/token' );
		return;
	}

	\Nino\Runtime::setSessionValue( $app, './nino/auth/current', $who. '@example.com' );
	\Nino\Runtime::setSessionValue( $app, './nino/auth/token', $sessions[$who] );
};

// Whether the lock of config.php is free: a second handle of its lock file takes it without waiting
$lockFree = static function() use ( &$app ): bool {

	$method = new ReflectionMethod( \Nino\Filesystem::class, '_canonicalPath' );
	$path		= ninoSandboxDir( $app ). '/private/data/.locks/'. sha1( (string) $method->invokeArgs( null, [ &$app, '/config.php' ] ) ). '.lock';

	if( is_file( $path ) === false )
		return true;

	$handle = fopen( $path, 'c' );
	$free		= flock( $handle, LOCK_EX | LOCK_NB );

	if( $free === true )
		flock( $handle, LOCK_UN );

	fclose( $handle );

	return $free;
};

// What was answered while the lock of config.php stayed taken
$held = [];

// One request with the right token, booted from disk
$send = static function( string $who, string $action, array $data ) use ( &$app, $as, $lockFree, &$held ): array {

	$as( $who );

	$answer = ninoLive( $app, $action, $data, \Nino\Csrf::getToken( $app ) );

	if( $lockFree() === false )
		$held[] = $who. ' '. $action. ' '. $answer['status'];

	return $answer;
};

// The log lines of the day, as the activity log's file holds them
$logLines = static function() use ( &$app ): array {

	$lines = [];

	foreach( glob( ninoSandboxDir( $app ). '/private/.logs/*' ) ?: [] as $file ) {

		$raw 	= (string) file_get_contents( $file );
		$pre	= "<?php http_response_code(403); exit; return '";
		$post	= "';\n";
		$text	= str_starts_with( $raw, $pre ) === true ? base64_decode( substr( $raw, strlen( $pre ), -strlen( $post ) ), true ) : false;

		if( is_string( $text ) === true && $text !== '' )
			array_push( $lines, ...explode( "\n", $text ) );
	}

	return $lines;
};

// The world with one full access
$worldA = $disk();

$as( 'dev' );
ninoBoot( $app );
ninoDeleteUser( $app, 'dev2@example.com' );
$worldB = $disk();
$restore( $worldA );

$code = static fn( array $answer ): ?string => is_array( $answer['body'] ) === true ? ( $answer['body']['code'] ?? null ) : null;

// --- The order of refusals, every account action and every role action -------------------------

echo "The order of refusals - through the dispatcher, from disk\n";

$password = 'a password long enough';

// What each action leaves on disk where it is done: the accounts and the tries as config.php and the tries file hold them
$accountActions = [
	'users/save, rename'		=> [ 'users/save', static fn( string $mail ): array => [ 'username' => $mail, 'mail' => 'renamed.'. $mail ],
		static fn( array $users, array $tries, string $mail ): bool => isset( $users['renamed.'. $mail] ) === true && isset( $users[$mail] ) === false ],
	'users/save, name'			=> [ 'users/save', static fn( string $mail ): array => [ 'username' => $mail, 'mail' => $mail, 'name' => 'A Name' ],
		static fn( array $users, array $tries, string $mail ): bool => ( $users[$mail]['name'] ?? '' ) === 'A Name' ],
	'users/save, password'	=> [ 'users/save', static fn( string $mail ): array => [ 'username' => $mail, 'mail' => $mail, 'pw' => $password ],
		static fn( array $users, array $tries, string $mail ): bool => password_verify( $password, (string) ( $users[$mail]['pw'] ?? '' ) ) === true ],
	'users/save, role'			=> [ 'users/save', static fn( string $mail ): array => [ 'username' => $mail, 'mail' => $mail, 'role' => 'small' ],
		static fn( array $users, array $tries, string $mail ): bool => ( $users[$mail]['role'] ?? '' ) === 'small' ],
	'users/save, role to none'	=> [ 'users/save', static fn( string $mail ): array => [ 'username' => $mail, 'mail' => $mail, 'role' => '' ],
		static fn( array $users, array $tries, string $mail ): bool => ( $users[$mail]['role'] ?? 'x' ) === '' ],
	'users/delete'					=> [ 'users/delete', static fn( string $mail ): array => [ 'username' => $mail ],
		static fn( array $users, array $tries, string $mail ): bool => isset( $users[$mail] ) === false ],
	'users/logoutall'				=> [ 'users/logoutall', static fn( string $mail ): array => [ 'username' => $mail ],
		static fn( array $users, array $tries, string $mail ): bool => ( $users[$mail]['sessions'] ?? [ 'x' ] ) === [] ],
	'users/role'						=> [ 'users/role', static fn( string $mail ): array => [ 'username' => $mail, 'role' => 'small' ],
		static fn( array $users, array $tries, string $mail ): bool => ( $users[$mail]['role'] ?? '' ) === 'small' ],
	'users/role to none'		=> [ 'users/role', static fn( string $mail ): array => [ 'username' => $mail, 'role' => '' ],
		static fn( array $users, array $tries, string $mail ): bool => ( $users[$mail]['role'] ?? 'x' ) === '' ],
	'users/status'					=> [ 'users/status', static fn( string $mail ): array => [ 'username' => $mail, 'active' => false ],
		static fn( array $users, array $tries, string $mail ): bool => ( $users[$mail]['status'] ?? 2 ) !== \Nino\Auth::STATUS_ACTIVE ],
	'lockout/unlock'				=> [ 'lockout/unlock', static fn( string $mail ): array => [ 'username' => $mail ],
		static fn( array $users, array $tries, string $mail ): bool => isset( $tries[$mail] ) === false ],
];

// The targets: which world, which account. 'beyond' holds what the manager does not and another account holds it too, 'only' is the one account with full access,
// 'scoped' is let into the whole elements panel, which the lead holds in detail, 'bare' has no role
$targets = [
	'unknown'	=> [ $worldA, 'ghost@example.com' ],
	'beyond'	=> [ $worldA, 'dev2@example.com' ],
	'only'		=> [ $worldB, 'dev@example.com' ],
	'scoped'	=> [ $worldA, 'panel@example.com' ],
	'within'	=> [ $worldA, 'ed2@example.com' ],
	'bare'		=> [ $worldA, 'none@example.com' ],
];

// who => target => [ status, code ]; the editor has no right to anything here, the visitor no session
$everyone	= array_fill_keys( array_keys( $targets ), null );
$expected = [
	'visitor'	=> array_map( static fn(): array => [ 401, 'session' ], $everyone ),
	'ed'			=> array_map( static fn(): array => [ 403, null ], $everyone ),
	'man'			=> [ 'unknown' => [ 404, 'users_unknown' ], 'beyond' => [ 403, 'users_account_beyond' ], 'only' => [ 403, 'users_account_beyond' ], 'scoped' => [ 403, 'users_account_beyond' ], 'within' => [ 200, null ], 'bare' => [ 200, null ] ],
	'lead'		=> [ 'unknown' => [ 404, 'users_unknown' ], 'beyond' => [ 403, 'users_account_beyond' ], 'only' => [ 403, 'users_account_beyond' ], 'scoped' => [ 403, 'users_account_beyond_scoped' ], 'bare' => [ 200, null ] ],
	'dev'			=> [ 'unknown' => [ 404, 'users_unknown' ], 'beyond' => [ 200, null ], 'scoped' => [ 200, null ], 'within' => [ 200, null ], 'bare' => [ 200, null ] ],
];

$wrong 		= [];
$cells		= 0;
$edBodies	= [];

foreach( $expected as $who => $row )
	foreach( $accountActions as $label => [ $action, $ask, $done ] )
		foreach( $row as $target => [ $status, $wanted ] ) {

			[ $world, $mail ] = $targets[$target];

			$restore( $world );

			// An account that is locked out, for the lift to lift
			if( $action === 'lockout/unlock' )
				\Nino\Filesystem::mutate( $app, '/data/auth-tries.php', static function( mixed $state ) use ( $mail ): array {
					$state = is_array( $state ) === true ? $state : [];
					$state[$mail] = 0 - time() - 3600;
					return $state;
				} );

			$before = $disk();

			$answer = $send( $who, $action, $ask( $mail ) );
			$cells++;

			if( $who === 'ed' )
				$edBodies[$label][json_encode( $answer )] = true;

			$bad = $answer['status'] !== $status || $code( $answer ) !== $wanted || $answer['failure'] !== null
				|| ( $status !== 200 && $disk() !== $before )
				|| ( $status === 403 && $wanted === null && ( $answer['body']['error'] ?? '' ) !== 'not allowed' )
				|| ( $status === 200 && $done( $read( '/config.php' )['/nino/auth/user'], (array) $read( '/data/auth-tries.php' ), $mail ) === false );

			if( $bad === true )
				$wrong[] = "$who x $target x $label: ". $answer['status']. ' '. ( $code( $answer ) ?? '-' );
		}

// The lead has no 'within' target of its own: the editor account holds the text permission, which the lead lacks, so it is beyond the lead too - the account with no role is the lead's within
$twohundred = 0;

foreach( $expected as $row )
	foreach( $row as [ $status ] )
		$twohundred += $status === 200 ? count( $accountActions ) : 0;

check( 'the table is the size it is meant to be: 308 cells, 77 of them done', $cells === 308 && $twohundred === 77 );
check( 'every cell answers its status and code, a refusal leaves config.php, the tries and the log byte for byte, and a 200 has done its change on disk'. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 6 ) ) ), $wrong === [] );
check( 'a caller without the right is told the same for an account that is not there, one that is beyond it and one within', count( array_filter( $edBodies, static fn( array $bodies ): bool => count( $bodies ) !== 1 ) ) === 0 );

// The own account: what is its own to do and what is not, for a manager and for an editor
$ownMan	= [ 'users/save, rename' => [ 200, null ], 'users/save, name' => [ 200, null ], 'users/save, password' => [ 200, null ], 'users/save, role' => [ 400, 'users_own_role' ], 'users/save, role to none' => [ 400, 'users_own_role' ],
	'users/delete' => [ 400, 'users_delete_self' ], 'users/logoutall' => [ 200, null ], 'users/role' => [ 400, 'users_own_role' ], 'users/role to none' => [ 400, 'users_own_role' ], 'users/status' => [ 400, 'users_status_self' ], 'lockout/unlock' => [ 200, null ] ];
$ownEd	= [ 'users/save, rename' => [ 200, null ], 'users/save, name' => [ 200, null ], 'users/save, password' => [ 200, null ], 'users/save, role' => [ 403, null ], 'users/save, role to none' => [ 403, null ],
	'users/delete' => [ 403, null ], 'users/logoutall' => [ 200, null ], 'users/role' => [ 403, null ], 'users/role to none' => [ 403, null ], 'users/status' => [ 403, null ], 'lockout/unlock' => [ 403, null ] ];
$wrong	= [];

foreach( [ 'man' => $ownMan, 'ed' => $ownEd ] as $who => $row )
	foreach( $accountActions as $label => [ $action, $ask, $done ] ) {

		[ $status, $wanted ] = $row[$label];

		$restore( $worldA );
		$before = $disk();
		$data		= $ask( $who. '@example.com' ) + ( $action === 'users/save' ? [ 'currentPassword' => PW ] : [] );
		$answer = $send( $who, $action, $data );

		if( $answer['status'] !== $status || $code( $answer ) !== $wanted || ( $status !== 200 && $disk() !== $before ) )
			$wrong[] = "$who x own x $label: ". $answer['status']. ' '. ( $code( $answer ) ?? '-' );

		if( $status === 200 && $action === 'users/save' && $done( $read( '/config.php' )['/nino/auth/user'], [], $who. '@example.com' ) === false )
			$wrong[] = "$who x own x $label: not on disk";
	}

check( 'the own account: what is refused is refused before any account is looked up, what is done is on disk'. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 6 ) ) ), $wrong === [] );

// Every refusal of the manager on the only full access is a 403, never the 409 that says what it is
$restore( $worldB );
$only = [];

foreach( [ 'users/delete' => [ 'username' => 'dev@example.com' ], 'users/status' => [ 'username' => 'dev@example.com', 'active' => false ], 'users/role' => [ 'username' => 'dev@example.com', 'role' => '' ], 'users/save' => [ 'username' => 'dev@example.com', 'mail' => 'dev@example.com', 'role' => '' ] ] as $action => $data ) {
	$answer = $send( 'man', $action, $data );
	$only[$action] = $answer['status']. ' '. $code( $answer );
}

check( 'a manager without full access is told "beyond" and not "the last one" for delete, status, role and save', array_unique( array_values( $only ) ) === [ '403 users_account_beyond' ] );

$restore( $worldA );

// Roles: the role that holds what the manager does not and an account holds, one nobody holds, one within
$roleActions = [
	'roles/save of the role an account holds, beyond'	=> [ 'roles/save', [ 'id' => 'developer', 'label' => 'Developers', 'perms' => [ '/*' ] ] ],
	'roles/save of it with no permission'							=> [ 'roles/save', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [] ] ],
	'roles/save of a role nobody holds, beyond'				=> [ 'roles/save', [ 'id' => 'wide', 'label' => 'Wider', 'perms' => [ $types ] ] ],
	'roles/save within'																=> [ 'roles/save', [ 'id' => 'spare', 'label' => 'Spare, renamed', 'perms' => [ $text ] ] ],
	'roles/delete of the role an account holds, beyond'	=> [ 'roles/delete', [ 'id' => 'developer' ] ],
	'roles/delete of a role nobody holds, beyond'			=> [ 'roles/delete', [ 'id' => 'wide' ] ],
	'roles/delete within'															=> [ 'roles/delete', [ 'id' => 'spare' ] ],
	'roles/delete of a role that is not there'				=> [ 'roles/delete', [ 'id' => 'ghost' ] ],
];

$expectedRoles = [
	'visitor'	=> array_fill_keys( array_keys( $roleActions ), [ 401, 'session' ] ),
	'ed'			=> array_fill_keys( array_keys( $roleActions ), [ 403, null ] ),
	'man'			=> [ [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 200, null ], [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 200, null ], [ 404, 'roles_unknown' ] ],
	'dev'			=> [ [ 200, null ], [ 409, 'roles_locks_self_out' ], [ 200, null ], [ 200, null ], [ 409, 'roles_in_use' ], [ 200, null ], [ 200, null ], [ 404, 'roles_unknown' ] ],
	// The lead holds the elements panel in detail: the role that lets in all of it is beyond it, with the name of the panel
	'lead'		=> [ [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 403, 'roles_role_beyond' ], [ 404, 'roles_unknown' ] ],
];

$wrong 		= [];
$edBodies	= [];
$cellsRoles = 0;

foreach( $expectedRoles as $who => $row )
	foreach( array_keys( $roleActions ) as $at => $label ) {

		[ $action, $data ] = $roleActions[$label];
		[ $status, $wanted ] = array_values( $row )[$at];

		$restore( $worldA );
		$before = $disk();

		$answer = $send( $who, $action, $data );
		$cellsRoles++;

		if( $who === 'ed' )
			$edBodies[json_encode( $answer )] = true;

		$roles = $read( '/config.php' )['/nino/auth/roles'];
		$done	 = $action === 'roles/save' ? ( $roles[$data['id']]['label'] ?? '' ) === $data['label'] : isset( $roles[$data['id']] ) === false;

		if( $answer['status'] !== $status || $code( $answer ) !== $wanted || $answer['failure'] !== null || ( $status !== 200 && $disk() !== $before ) || ( $status === 200 && $done === false ) )
			$wrong[] = "$who x $label: ". $answer['status']. ' '. ( $code( $answer ) ?? '-' );
	}

check( 'the roles table is the size it is meant to be: 40 cells', $cellsRoles === 40 );
check( 'every role cell answers its status and code, a refusal leaves the disk byte for byte, and a 200 is on disk'. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 6 ) ) ), $wrong === [] );
check( 'a caller without the right is told the same for every role action', count( $edBodies ) === 1 );

// A wrong token and none, for every action of both tables: the real check refuses, and the disk is as it was
$wrong = [];

foreach( array_merge( array_map( static fn( array $entry ): array => [ $entry[0], $entry[1]( 'ed2@example.com' ) ], $accountActions ), array_map( static fn( array $entry ): array => [ $entry[0], $entry[1] ], $roleActions ) ) as $label => [ $action, $data ] )
	foreach( [ 'a wrong token' => 'wrong', 'no token' => null ] as $kind => $token ) {

		$restore( $worldA );
		$as( 'man' );
		$before = $disk();
		$answer = ninoLive( $app, $action, $data, $token );

		if( $answer['status'] !== 403 || $code( $answer ) !== 'csrf' || $answer['blocked'] !== true || $disk() !== $before )
			$wrong[] = "$label, $kind: ". $answer['status']. ' '. ( $code( $answer ) ?? '-' );
	}

check( 'every action of both tables refuses a wrong token and none with 403 "csrf" and leaves the disk byte for byte'. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 4 ) ) ), $wrong === [] );

// The one 409 of a role that no account of the rules above reaches otherwise: full access through the role, the manage permission of the account's own
$restore( $worldA );
ninoBoot( $app );
\Nino\Auth::insertUser( $app, 'solo@example.com', PW, [ $manage ], 'developer' );
\Nino\Auth::loginUser( $app, 'solo@example.com', PW );
$sessions['solo'] = \Nino\Runtime::getSessionValue( $app, './nino/auth/token', '' );
$before = $disk();
$answer = $send( 'solo', 'roles/save', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [] ] );
check( 'a role that is the full access of every account is not emptied by one that keeps the manage permission of its own: 409 "roles_no_full_access"', $answer['status'] === 409 && $code( $answer ) === 'roles_no_full_access' && $disk() === $before );
$restore( $worldA );

// The rules about the caller's own account come before the account is looked up; the posted values before any account
$restore( $worldA );

$cases = [
	[ 'man', 'users/delete', [ 'username' => 'man@example.com' ], 400, 'users_delete_self' ],
	[ 'man', 'users/status', [ 'username' => 'man@example.com', 'active' => false ], 400, 'users_status_self' ],
	[ 'man', 'users/role', [ 'username' => 'man@example.com', 'role' => '' ], 400, 'users_own_role' ],
	[ 'man', 'users/save', [ 'username' => 'ghost@example.com', 'mail' => 'not a mail' ], 400, 'users_invalid_mail' ],
	[ 'man', 'users/save', [ 'username' => 'dev2@example.com', 'mail' => 'not a mail' ], 400, 'users_invalid_mail' ],
	[ 'man', 'users/save', [ 'mail' => 'ghost@example.com' ], 400, 'users_invalid_name' ],
	[ 'man', 'users/save', [ 'username' => [ 'ghost@example.com' ], 'mail' => 'ghost@example.com' ], 400, 'users_invalid_name' ],
	[ 'man', 'users/logoutall', [ 'username' => [ 'ghost@example.com' ] ], 400, 'users_invalid_name' ],
	[ 'ed', 'users/logoutall', [ 'username' => [ 'ghost@example.com' ] ], 403, null ],
	[ 'man', 'users/save', [ 'username' => 'dev2@example.com', 'mail' => 'dev2@example.com', 'role' => 'nonexistent' ], 403, 'users_account_beyond' ],
	[ 'man', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'role' => 'nonexistent' ], 400, 'users_unknown_role' ],
	[ 'man', 'users/save', [ 'username' => 'man@example.com', 'mail' => 'man@example.com', 'role' => 'editor', 'currentPassword' => PW ], 400, 'users_own_role' ],
	[ 'ed', 'users/save', [ 'username' => 'ed@example.com', 'mail' => 'ed@example.com', 'role' => 'manager', 'currentPassword' => PW ], 403, null ],
	[ 'man', 'users/role', [ 'username' => 'dev2@example.com', 'role' => 'nonexistent' ], 403, 'users_account_beyond' ],
	[ 'man', 'users/role', [ 'username' => 'ed2@example.com', 'role' => 'nonexistent' ], 400, 'users_unknown_role' ],
	[ 'man', 'users/role', [ 'username' => 'ed2@example.com' ], 400, 'users_unknown_role' ],
	[ 'man', 'users/role', [ 'username' => 'ed2@example.com', 'role' => [ 'editor' ] ], 400, 'users_unknown_role' ],
	[ 'man', 'users/role', [ 'username' => 'ed2@example.com', 'role' => 'developer' ], 403, 'users_role_too_wide' ],
	[ 'man', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'dev@example.com' ], 400, 'users_mail_in_use' ],
	[ 'man', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'dev@example.com', 'role' => 'developer' ], 403, 'users_role_too_wide' ],
];

$wrong = [];

foreach( $cases as [ $who, $action, $data, $status, $wanted ] ) {

	$before = $disk();
	$answer = $send( $who, $action, $data );

	if( $answer['status'] !== $status || $code( $answer ) !== $wanted || $disk() !== $before )
		$wrong[] = "$who $action ". json_encode( $data ). ': '. $answer['status']. ' '. ( $code( $answer ) ?? '-' );
}

check( 'own account, posted values, account, reach, the change: each in its place'. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 4 ) ) ), $wrong === [] );

// The unknown account of users/save is neither "mail in use" nor a 200, and says what it is
$before = $disk();

$bodies = [];

foreach( [
	[ 'username' => 'ghost@example.com', 'mail' => 'ghost2@example.com' ],
	[ 'username' => 'ghost@example.com', 'mail' => 'ghost@example.com' ],
	[ 'username' => 'ghost@example.com', 'mail' => 'ghost@example.com', 'role' => 'editor', 'name' => 'Ghost' ],
	[ 'username' => 'ghost@example.com', 'mail' => 'ed2@example.com', 'pw' => $password ],
] as $data ) {
	$answer = $send( 'man', 'users/save', $data );
	$bodies[] = $answer['status']. ' '. json_encode( $answer['body'] );
}

check( 'users/save of an account that is not there answers 404 "users_unknown", whatever else is posted', array_unique( $bodies ) === [ '404 {"error":"unknown user","code":"users_unknown"}' ] );
check( '...writes nothing and logs nothing', $disk() === $before );
$answer = $send( 'man', 'users/logoutall', [ 'username' => 'ghost@example.com' ] );
check( 'users/logoutall of an account that is not there is a 404 too, not an ok that is not', $answer['status'] === 404 && $code( $answer ) === 'users_unknown' && $disk() === $before );
$answer = $send( 'man', 'users/delete', [ 'username' => 'ghost@example.com' ] );
check( 'users/delete of one that is not there carries the code', $answer['status'] === 404 && $code( $answer ) === 'users_unknown' );
$answer = $send( 'man', 'users/role', [ 'username' => 'ghost@example.com', 'role' => 'editor' ] );
check( 'users/role of one that is not there carries the code', $answer['status'] === 404 && $code( $answer ) === 'users_unknown' );

// --- One write ----------------------------------------------------------------------------------

echo "\nOne save is one write of config.php\n";

$full = [ 'username' => 'ed2@example.com', 'mail' => 'ed2.new@example.com', 'pw' => $password, 'role' => 'spare', 'name' => 'New Name' ];

$replaces = static function( array $log ): int {
	return count( array_filter( $log, static fn( array $entry ): bool => $entry[0] === 'rename' && basename( $entry[2] ) === 'config.php' ) );
};

$restore( $worldA );
FaultFs::on();
$answer = $send( 'man', 'users/save', $full );
$log = FaultFs::$log;
FaultFs::off();

check( 'a save of address, password, role and name answers 200 with all four', $answer['status'] === 200 && $answer['body'] === [ 'mail' => 'ed2.new@example.com', 'role' => 'spare', 'name' => 'New Name' ] );
check( '...and replaces config.php exactly once', $replaces( $log ) === 1 );

$stored = $read( '/config.php' )['/nino/auth/user'];
check( '...with everything stored and the old address gone', isset( $stored['ed2@example.com'] ) === false && ( $stored['ed2.new@example.com']['role'] ?? '' ) === 'spare' && ( $stored['ed2.new@example.com']['name'] ?? '' ) === 'New Name'
	&& password_verify( $password, (string) ( $stored['ed2.new@example.com']['pw'] ?? '' ) ) === true );

$restore( $worldA );
$before = $disk();
// The last replace of config.php the save makes is the one that is refused
$count = 0;
$last	 = $replaces( $log );
FaultFs::on( [ 'rename' => static function( string $to ) use ( &$count, $last ): bool {

	if( basename( $to ) !== 'config.php' )
		return false;

	return ++$count === $last;
} ] );
$answer = $send( 'man', 'users/save', $full );
FaultFs::off();

check( 'a replace of config.php that is refused ends the request', $answer['failure'] !== null && $answer['status'] === 500 );
check( '...and leaves config.php byte for byte as it was', $disk()['/config.php'] === $before['/config.php'] );

$answer = $send( 'man', 'users/save', $full );
$stored = $read( '/config.php' )['/nino/auth/user'];
check( 'the same request sent once more answers 200', $answer['status'] === 200 && $answer['body'] === [ 'mail' => 'ed2.new@example.com', 'role' => 'spare', 'name' => 'New Name' ] );
check( '...with everything stored', isset( $stored['ed2@example.com'] ) === false && ( $stored['ed2.new@example.com']['role'] ?? '' ) === 'spare' && ( $stored['ed2.new@example.com']['name'] ?? '' ) === 'New Name'
	&& password_verify( $password, (string) ( $stored['ed2.new@example.com']['pw'] ?? '' ) ) === true );

// A save that changes only the role or only the name keeps the record as it stands
$restore( $worldA );
$recordBefore = $read( '/config.php' )['/nino/auth/user']['ed2@example.com'];
$answer = $send( 'man', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'name' => 'Only A Name' ] );
$recordAfter = $read( '/config.php' )['/nino/auth/user']['ed2@example.com'];
check( 'a save of the name alone changes the name and nothing else of the record', $answer['status'] === 200 && $recordAfter === $recordBefore + [ 'name' => 'Only A Name' ] );

// --- Counters follow a rename -------------------------------------------------------------------

echo "\nThe counters of an account follow a rename\n";

$tries = static fn(): array => (array) ( $read( '/data/auth-tries.php' ) ?? [] );

$restore( $worldA );
\Nino\Auth::loginUser( $app, 'ed@example.com', 'wrong password' );
$answer = $send( 'ed', 'users/save', [ 'username' => 'ed@example.com', 'mail' => 'ed@example.com', 'name' => 'Ed', 'currentPassword' => 'wrong password' ] );
check( 'the fixture: a wrong confirmation and a wrong sign-in are counted', $answer['status'] === 401 && isset( $tries()['confirm:ed@example.com'] ) === true && isset( $tries()['ed@example.com'] ) === true );

$answer = $send( 'man', 'users/save', [ 'username' => 'ed@example.com', 'mail' => 'ed.new@example.com' ] );
$state = $tries();
check( 'the manager renames the account', $answer['status'] === 200 );
check( 'the counter of the confirmation is at the new address and not at the old one', ( $state['confirm:ed.new@example.com']['tries'] ?? 0 ) === 1 && isset( $state['confirm:ed@example.com'] ) === false );
check( '...and the counter of the sign-in with it', ( $state['ed.new@example.com']['tries'] ?? 0 ) === 1 && isset( $state['ed@example.com'] ) === false );

$answer = $send( 'man', 'users/create', [ 'mail' => 'ed@example.com', 'pw' => $password, 'role' => 'editor', 'name' => '' ] );
check( 'a new account at the old address is made', $answer['status'] === 200 );
check( '...and its owner starts from zero: the right password confirms', \Nino\Auth::confirmPassword( $app, 'ed@example.com', $password ) === true );
\Nino\Auth::confirmPassword( $app, 'ed@example.com', 'wrong password' );
check( '...and one wrong one is the first', ( $tries()['confirm:ed@example.com']['tries'] ?? 0 ) === 1 );

// A lock is carried the same way
$restore( $worldA );

foreach( [ 1, 2, 3 ] as $attempt )
	$send( 'ed', 'users/save', [ 'username' => 'ed@example.com', 'mail' => 'ed@example.com', 'name' => 'Ed', 'currentPassword' => 'wrong password' ] );

$locked = $tries()['confirm:ed@example.com'] ?? 0;
check( 'the fixture: three wrong confirmations lock the confirmation', is_int( $locked ) === true && $locked < 0 );

$send( 'man', 'users/save', [ 'username' => 'ed@example.com', 'mail' => 'ed.new@example.com' ] );
$state = $tries();
check( 'a lock follows the account to its new address', ( $state['confirm:ed.new@example.com'] ?? 0 ) === $locked && isset( $state['confirm:ed@example.com'] ) === false );
$send( 'man', 'users/create', [ 'mail' => 'ed@example.com', 'pw' => $password, 'role' => 'editor', 'name' => '' ] );
check( '...and does not brake a new account at the old address', \Nino\Auth::confirmPassword( $app, 'ed@example.com', $password ) === true );

// --- Roles are written under the lock, one role at a time ----------------------------------------

echo "\nA role written by one request survives a request that booted before it\n";

/**
 *	One request's boot from the sandbox on disk, as a request would see config.php when it starts; $who is the account that is signed in on it
 */
$bootRequest = static function( string $who ) use ( &$app ): array {

	$sandbox = ninoSandboxDir( $app );
	$request = [ './nino/uid' => $sandbox ];

	\Nino\AppData::prepare( $request );

	$request['./nino/filesystem/path']				= $sandbox;
	$request['./nino/filesystem/configpath']	= $sandbox. '/private';
	$request['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$request['./nino/filesystem/publicpath']	= $sandbox. '/public';

	\Nino\AppData::init( $request );

	$request['./nino/locales/current']	= 'de_DE';
	$request['/nino/locales/native']		= 'de_DE';
	$request['/nino/locales/available']	= [ 'de_DE', 'en_US' ];
	$request['/nino/dir']								= '';
	$request['/nino/modules']						= [];
	$request['/nino/install/texts']			= \Nino\VERSION;
	$request['/nino/admin/backups']			= false;
	$request['./nino/test/sandbox']			= $sandbox;
	$request['./nino/auth/baseline']		= $request['/nino/auth/user'] ?? [];
	$request['./nino/auth/current']			= \Nino\Auth::getUser( $request, $who. '@example.com' );

	return $request;
};

$rolesOnDisk = static fn(): array => $read( '/config.php' )['/nino/auth/roles'];

$restore( $worldA );
$first	= $bootRequest( 'dev' );
$second	= $bootRequest( 'dev2' );
[ $status ] = ninoCall( $first, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'editor', 'label' => 'Editor, relabelled by the first', 'perms' => [ $text, $types ] ], 'roles/save' );
check( 'the first request relabels a role and gives it a permission', $status === 200 && $rolesOnDisk()['editor']['label'] === 'Editor, relabelled by the first' );
[ $status ] = ninoCall( $second, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'side', 'label' => 'Side, saved by the second', 'perms' => [ $types ] ], 'roles/save' );
check( 'the second, which booted before it, saves another role', $status === 200 && $rolesOnDisk()['side']['label'] === 'Side, saved by the second' );
check( '...and the relabel of the first stands', $rolesOnDisk()['editor'] === [ 'label' => 'Editor, relabelled by the first', 'perms' => [ $text, $types ] ] );

$restore( $worldA );
$first	= $bootRequest( 'dev' );
$second	= $bootRequest( 'dev2' );
ninoCall( $first, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'created-by-one', 'label' => 'Created by the first', 'perms' => [ $text ], 'create' => true ], 'roles/save' );
[ $status ] = ninoCall( $second, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'spare' ], 'roles/delete' );
check( 'a role the first request created stands after the second, stale, deleted another', $status === 200 && isset( $rolesOnDisk()['created-by-one'] ) === true && isset( $rolesOnDisk()['spare'] ) === false );

$restore( $worldA );
$first	= $bootRequest( 'dev' );
$second	= $bootRequest( 'dev2' );
ninoCall( $first, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'wide' ], 'roles/delete' );
[ $status ] = ninoCall( $second, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'side', 'label' => 'Side, saved by the second', 'perms' => [ $types ] ], 'roles/save' );
check( 'a role the first request deleted stays deleted after the second, stale, saved another', $status === 200 && isset( $rolesOnDisk()['wide'] ) === false && $rolesOnDisk()['side']['label'] === 'Side, saved by the second' );

// A stale request decides against the roles that stand when it decides
$restore( $worldA );
$first	= $bootRequest( 'dev' );
$second	= $bootRequest( 'dev2' );
ninoCall( $first, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'wide' ], 'roles/delete' );
[ $status, $body ] = ninoCall( $second, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'id' => 'wide' ], 'roles/delete' );
check( 'a second delete of a role that is gone is a 404 "roles_unknown"', $status === 404 && ( $body['code'] ?? '' ) === 'roles_unknown' );

// --- What depends on the state of others is decided under the lock, against what config.php holds ---

echo "\nTwo requests that booted from the same file decide against what is stored\n";

$handle = static function( array &$request, string $action, array $data ) use ( $lockFree, &$held ): array {
	[ $status, $body ] = ninoCall( $request, [ \Nino\Admin\Admin::class, 'handlePost' ], $data, $action );

	if( $lockFree() === false )
		$held[] = 'stale '. $action. ' '. $status;

	return [ $status, $body['code'] ?? null ];
};

$fullActive = static function() use ( $read ): array {

	$config = $read( '/config.php' );
	$out		= [];

	foreach( $config['/nino/auth/user'] as $mail => $user )
		if( ( $user['status'] ?? 0 ) === \Nino\Auth::STATUS_ACTIVE && in_array( '/*', array_merge( (array) ( $user['perms'] ?? [] ), (array) ( $config['/nino/auth/roles'][$user['role'] ?? '']['perms'] ?? [] ) ), true ) === true )
			$out[] = $mail;

	return $out;
};

$pairs = [
	'A, two deletes of the two accounts with full access'		=> [ [ 'dev', 'users/delete', [ 'username' => 'dev2@example.com' ] ], [ 'dev2', 'users/delete', [ 'username' => 'dev@example.com' ] ], 'users_last_admin_delete' ],
	'A2, a deactivation and a role taken away'							=> [ [ 'dev', 'users/status', [ 'username' => 'dev2@example.com', 'active' => false ] ], [ 'dev2', 'users/role', [ 'username' => 'dev@example.com', 'role' => '' ] ], 'users_last_admin_role' ],
	'A3, two saves that take the role away'								=> [ [ 'dev', 'users/save', [ 'username' => 'dev2@example.com', 'mail' => 'dev2@example.com', 'role' => '' ] ], [ 'dev2', 'users/save', [ 'username' => 'dev@example.com', 'mail' => 'dev@example.com', 'role' => '' ] ], 'users_last_admin_role' ],
	'A4, a delete and a deactivation'												=> [ [ 'dev2', 'users/delete', [ 'username' => 'dev@example.com' ] ], [ 'dev', 'users/status', [ 'username' => 'dev2@example.com', 'active' => false ] ], 'users_last_admin_status' ],
];

foreach( $pairs as $label => [ $first, $second, $wanted ] ) {

	$restore( $worldA );
	$requests = [ $first[0] => $bootRequest( $first[0] ), $second[0] => $bootRequest( $second[0] ) ];
	$one			= $handle( $requests[$first[0]], $first[1], $first[2] );
	$two			= $handle( $requests[$second[0]], $second[1], $second[2] );

	check( $label. ': the second answers 409 "'. $wanted. '" and an active account with full access stands', $one[0] === 200 && $two === [ 409, $wanted ] && count( $fullActive() ) === 1 );
}

$restore( $worldA );
$first	= $bootRequest( 'dev' );
$second	= $bootRequest( 'dev2' );
$handle( $first, 'roles/save', [ 'id' => 'x', 'label' => 'One', 'perms' => [], 'create' => true ] );
$answer = $handle( $second, 'roles/save', [ 'id' => 'x', 'label' => 'Two', 'perms' => [ $text ], 'create' => true ] );
check( 'D, two creates of one role: the second answers 409 "roles_id_taken" and the role of the first stands', $answer === [ 409, 'roles_id_taken' ] && $read( '/config.php' )['/nino/auth/roles']['x'] === [ 'label' => 'One', 'perms' => [] ] );

$restore( $worldA );
$first	= $bootRequest( 'dev' );
$second	= $bootRequest( 'dev2' );
$handle( $first, 'users/role', [ 'username' => 'ed2@example.com', 'role' => 'spare' ] );
$answer = $handle( $second, 'roles/delete', [ 'id' => 'spare' ] );
check( 'F, a role given to an account and deleted by a request that booted before: 409 "roles_in_use", the role stands', $answer === [ 409, 'roles_in_use' ] && isset( $read( '/config.php' )['/nino/auth/roles']['spare'] ) === true );

foreach( [
	'G, a role deleted and given by a request that booted before'	=> [ 'dev', 'users/role', [ 'username' => 'ed2@example.com', 'role' => 'spare' ] ],
	'G2, ...and created with'																			=> [ 'dev', 'users/create', [ 'mail' => 'x@example.com', 'pw' => $password, 'role' => 'spare', 'name' => '' ] ],
	'G3, ...and set through a save'																=> [ 'dev', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'role' => 'spare' ] ],
] as $label => [ $who, $action, $data ] ) {

	$restore( $worldA );
	$first	= $bootRequest( 'dev2' );
	$second	= $bootRequest( $who );
	$handle( $first, 'roles/delete', [ 'id' => 'spare' ] );
	$answer = $handle( $second, $action, $data );
	$config = $read( '/config.php' );
	check( $label. ': 400 "users_unknown_role", and no account holds a role that is gone', $answer === [ 400, 'users_unknown_role' ] && isset( $config['/nino/auth/roles']['spare'] ) === false
		&& count( array_filter( $config['/nino/auth/user'], static fn( array $user ): bool => ( $user['role'] ?? '' ) === 'spare' ) ) === 0 );
}

// A change to an account that was deleted or renamed meanwhile is a 404, whatever the change is; activating one does not bring it back
$gone = [
	'a name'							=> [ 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'name' => 'Late' ] ],
	'a password'					=> [ 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'pw' => $password ] ],
	'the activation'			=> [ 'users/status', [ 'username' => 'ed2@example.com', 'active' => true ] ],
	'a lift of its lock'	=> [ 'lockout/unlock', [ 'username' => 'ed2@example.com' ] ],
	'the end of its sessions'	=> [ 'users/logoutall', [ 'username' => 'ed2@example.com' ] ],
];

foreach( [ 'deleted' => [ 'users/delete', [ 'username' => 'ed2@example.com' ] ], 'renamed' => [ 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2.moved@example.com' ] ] ] as $how => [ $action, $data ] )
	foreach( $gone as $what => [ $lateAction, $lateData ] ) {

		$restore( $worldA );
		$first	= $bootRequest( 'dev' );
		$second	= $bootRequest( 'man' );
		$handle( $first, $action, $data );
		$as( 'man' );
		$before = $disk();
		$answer = $handle( $second, $lateAction, $lateData );

		check( 'an account '. $how. ' meanwhile: '. $what. ' is 404 "users_unknown" and writes nothing', $answer === [ 404, 'users_unknown' ] && $disk() === $before && isset( $read( '/config.php' )['/nino/auth/user']['ed2@example.com'] ) === false );
	}

// One address, two requests: the second is told it is in use, and writes nothing
foreach( [
	'two creates'							=> [ [ 'dev', 'users/create', [ 'mail' => 'x@example.com', 'pw' => $password, 'role' => 'editor', 'name' => '' ] ], [ 'dev2', 'users/create', [ 'mail' => 'x@example.com', 'pw' => $password, 'role' => 'spare', 'name' => '' ] ], 409 ],
	'two renames'							=> [ [ 'dev', 'users/save', [ 'username' => 'ed@example.com', 'mail' => 'x@example.com' ] ], [ 'dev2', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'x@example.com' ] ], 400 ],
	'a create and a rename'		=> [ [ 'dev', 'users/create', [ 'mail' => 'x@example.com', 'pw' => $password, 'role' => 'editor', 'name' => '' ] ], [ 'dev2', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'x@example.com' ] ], 400 ],
] as $label => [ $first, $second, $status ] ) {

	$restore( $worldA );
	$one		= $bootRequest( $first[0] );
	$two		= $bootRequest( $second[0] );
	$handle( $one, $first[1], $first[2] );
	$as( $second[0] );
	$before = $disk();
	$answer = $handle( $two, $second[1], $second[2] );

	check( $label. ' of one address: the second answers '. $status. ' "users_mail_in_use" and writes nothing', $answer === [ $status, 'users_mail_in_use' ] && $disk() === $before && isset( $read( '/config.php' )['/nino/auth/user']['ed2@example.com'] ) === true );
}

// An account that was widened meanwhile is beyond the manager when the change is made
foreach( [
	'users/save'			=> [ 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'name' => 'Late' ] ],
	'users/delete'		=> [ 'users/delete', [ 'username' => 'ed2@example.com' ] ],
	'users/status'		=> [ 'users/status', [ 'username' => 'ed2@example.com', 'active' => false ] ],
	'users/role'			=> [ 'users/role', [ 'username' => 'ed2@example.com', 'role' => 'small' ] ],
	'users/logoutall'	=> [ 'users/logoutall', [ 'username' => 'ed2@example.com' ] ],
	'lockout/unlock'	=> [ 'lockout/unlock', [ 'username' => 'ed2@example.com' ] ],
] as $label => [ $action, $data ] ) {

	$restore( $worldA );
	$first	= $bootRequest( 'dev' );
	$second	= $bootRequest( 'man' );
	$handle( $first, 'users/role', [ 'username' => 'ed2@example.com', 'role' => 'developer' ] );
	$as( 'man' );
	$before = $disk();
	$answer = $handle( $second, $action, $data );

	check( 'an account widened meanwhile: '. $label. ' of the manager is 403 "users_account_beyond" and writes nothing', $answer === [ 403, 'users_account_beyond' ] && $disk() === $before );
}

// --- The Dashboard tile -------------------------------------------------------------------------

echo "\nThe Users tile of the Dashboard\n";

$restore( $worldA );
$tiles = [];

foreach( [ 'none', 'ed', 'rpw', 'man', 'dev' ] as $who ) {
	$answer = $send( $who, 'dashboard/summary', [] );
	$tiles[$who] = array_values( array_filter( (array) ( $answer['body']['tiles'] ?? [] ), static fn( array $tile ): bool => $tile['panel'] === 'users' ) );
}

check( 'an account without a role, an editor and an account that manages the recovery password get no Users tile', $tiles['none'] === [] && $tiles['ed'] === [] && $tiles['rpw'] === [] );
check( 'a manager and a developer get it, with the number of accounts', ( $tiles['man'][0]['value'] ?? '' ) === '9' && ( $tiles['dev'][0]['value'] ?? '' ) === '9' );
check( '...and the panel stays everybody\'s: the navigation has it for an editor', isset( \Nino\Admin\Admin::panels( $app )['users'] ) === true && \Nino\Admin\Admin::panels( $app )['users']['perm'] === '' );

// --- The line of the activity log ---------------------------------------------------------------

echo "\nThe line the activity log writes for a save\n";

check( 'the line names the address it was and the one it is', \Nino\Modules\Users\Admin::log( 'users/save', [ 'username' => 'a@example.com', 'mail' => 'b@example.com' ] ) === 'Edit User a@example.com -> b@example.com' );
check( '...and what follows it as it did', \Nino\Modules\Users\Admin::log( 'users/save', [ 'username' => 'a@example.com', 'mail' => 'b@example.com', 'role' => 'editor' ] ) === 'Edit User a@example.com -> b@example.com Role editor' );
check( 'an address that stays is named once, without the blanks around it', \Nino\Modules\Users\Admin::log( 'users/save', [ 'username' => 'a@example.com', 'mail' => '  a@example.com ', 'role' => '' ] ) === 'Edit User a@example.com Role ' );

$restore( $worldA );
$send( 'man', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2.renamed@example.com', 'role' => 'editor' ] );
$send( 'man', 'users/save', [ 'username' => 'ed@example.com', 'mail' => '  ed@example.com  ', 'name' => 'Ed' ] );
$lines = $logLines();
check( 'a rename through the dispatcher is logged with both addresses', count( array_filter( $lines, static fn( string $line ): bool => str_ends_with( $line, '  man@example.com  Edit User ed2@example.com -> ed2.renamed@example.com Role editor' ) ) ) === 1 );
check( '...and a save that keeps the address with the address trimmed', count( array_filter( $lines, static fn( string $line ): bool => str_ends_with( $line, '  man@example.com  Edit User ed@example.com' ) ) ) === 1 );

// --- The password that is set -------------------------------------------------------------------

echo "\nThe rule for a password that is set\n";

$restore( $worldA );

$units8	= str_repeat( 'ä', 8 );
$cases	= [
	'äöüß, four units'								=> [ 'äöüß', 400, 'users_password_short', [ 8 ] ],
	'three emoji, six units'					=> [ '😀😀😀', 400, 'users_password_short', [ 8 ] ],
	'seven letters'										=> [ 'abcdefg', 400, 'users_password_short', [ 8 ] ],
	'73 bytes'												=> [ str_repeat( 'a', 73 ), 400, 'users_password_long', [ 72 ] ],
	'a null byte'											=> [ "abcdefgh\0ijkl", 400, 'users_password_invalid', [] ],
];

$wrong = [];

foreach( $cases as $label => [ $pw, $status, $wanted, $params ] ) {

	foreach( [ [ 'users/create', [ 'mail' => 'made@example.com', 'pw' => $pw, 'role' => 'editor', 'name' => '' ] ], [ 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'pw' => $pw ] ] ] as [ $action, $data ] ) {

		$before = $disk();
		$answer = $send( 'man', $action, $data );

		if( $answer['failure'] !== null || $answer['status'] !== $status || $code( $answer ) !== $wanted || ( $answer['body']['params'] ?? [] ) !== $params || ( $answer['body']['field'] ?? '' ) !== 'pw' || $disk() !== $before )
			$wrong[] = "$label, $action: ". $answer['status']. ' '. ( $code( $answer ) ?? '-' ). ( $answer['failure'] !== null ? ' thrown' : '' );
	}
}

check( 'a password that is too short in units of the browser, too long, with a null byte or no text is refused with its code, its params and the field, and nothing is written'. ( $wrong === [] ? '' : ' - '. implode( '; ', array_slice( $wrong, 0, 4 ) ) ), $wrong === [] );

// What a json body cannot carry reaches the rule only from php itself
check( 'bytes that are no text are refused, a null byte too, and the limits are the ones of the table', \Nino\Auth::passwordRefusal( "abcdefgh\xff\xfe" ) === 'invalid' && \Nino\Auth::passwordRefusal( "abcdefgh\0" ) === 'invalid'
	&& \Nino\Auth::passwordRefusal( 'äöüß' ) === 'short' && \Nino\Auth::passwordRefusal( str_repeat( 'a', 73 ) ) === 'long' && \Nino\Auth::passwordRefusal( str_repeat( 'a', 72 ) ) === null && \Nino\Auth::passwordRefusal( str_repeat( 'ä', 8 ) ) === null );

// The rule needs the kernel and nothing of the workbench: a process that loads only the kernel answers it
$kernel = (string) shell_exec( escapeshellarg( PHP_BINARY ). ' -r '. escapeshellarg( 'require '. var_export( dirname( __DIR__ ). '/_nino/Nino.php', true ). '; echo var_export( \\Nino\\Auth::passwordRefusal( "abc" ), true ), var_export( \\Nino\\Auth::passwordRefusal( "abcdefgh" ), true ), var_export( class_exists( "\\\\Nino\\\\Admin\\\\Recovery", false ), true );' ). ' 2>&1' );
check( 'the rule is answered by a process that loaded the kernel alone', $kernel === "'short'NULLfalse" );

$accepted = [ 'eight units of two-byte letters' => $units8, 'four emoji, eight units' => '😀😀😀😀', '72 bytes' => str_repeat( 'a', 72 ) ];
$wrong		= [];
$n				= 0;

foreach( $accepted as $label => $pw ) {

	$n++;
	$answer = $send( 'man', 'users/create', [ 'mail' => 'made'. $n. '@example.com', 'pw' => $pw, 'role' => 'editor', 'name' => '' ] );
	$saved	= $send( 'man', 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'pw' => $pw ] );

	if( $answer['status'] !== 200 || $saved['status'] !== 200 )
		$wrong[] = $label;
}

check( 'eight units of two-byte letters, four emoji and 72 bytes are accepted, by create and by save'. ( $wrong === [] ? '' : ' - '. implode( '; ', $wrong ) ), $wrong === [] );

// The recovery password of its tab: the same rule, with the codes of its own
$restore( $worldA );
\Nino\Admin\Recovery::set( $app, 'the secret in effect' );
$secret = $disk();
$wrong 	= [];

foreach( [ [ 'äöüß', 'recoverypw_password_short', [ 8 ] ], [ str_repeat( 'a', 73 ), 'recoverypw_password_long', [ 72 ] ], [ "abcdefgh\0ijkl", 'recoverypw_password_invalid', [] ] ] as [ $pw, $wanted, $params ] ) {

	$answer = $send( 'rpw', 'recoverypw/save', [ 'current' => 'the secret in effect', 'pw' => $pw ] );

	if( $answer['failure'] !== null || $answer['status'] !== 400 || $code( $answer ) !== $wanted || ( $answer['body']['params'] ?? [] ) !== $params || ( $answer['body']['field'] ?? '' ) !== 'pw' || $disk() !== $secret )
		$wrong[] = $wanted. ': '. $answer['status']. ' '. ( $code( $answer ) ?? '-' );
}

check( 'the recovery password of its tab is refused by the same rule with its own codes, and nothing is written'. ( $wrong === [] ? '' : ' - '. implode( '; ', $wrong ) ), $wrong === [] );
$answer = $send( 'rpw', 'recoverypw/save', [ 'current' => 'the secret in effect', 'pw' => $units8 ] );
check( '...and eight units are accepted', $answer['status'] === 200 && \Nino\Admin\Recovery::verify( $app, $units8 ) === 200 );

// What exists is not measured: an account whose password is longer than 72 bytes or shorter than the rule signs in and confirms
$restore( $worldA );
ninoBoot( $app );
\Nino\Auth::insertUser( $app, 'long@example.com', str_repeat( 'L', 89 ) );
\Nino\Auth::insertUser( $app, 'short@example.com', 'äöüß' );
check( 'an account with a password of 89 bytes still signs in and confirms it', \Nino\Auth::loginUser( $app, 'long@example.com', str_repeat( 'L', 89 ) ) !== false && \Nino\Auth::confirmPassword( $app, 'long@example.com', str_repeat( 'L', 89 ) ) === true );
check( 'an account with the password "äöüß" still signs in and confirms it', \Nino\Auth::loginUser( $app, 'short@example.com', 'äöüß' ) !== false && \Nino\Auth::confirmPassword( $app, 'short@example.com', 'äöüß' ) === true );

// --- The token check ----------------------------------------------------------------------------

echo "\nThe real token check in front of the dispatcher\n";

$restore( $worldA );
$as( 'man' );

$wrong = [];

foreach( [ [ 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'name' => 'Through The Token' ] ], [ 'users/delete', [ 'username' => 'ed2@example.com' ] ] ] as [ $action, $data ] ) {

	$before = $disk();
	$token	= \Nino\Csrf::getToken( $app );

	foreach( [ 'a wrong token' => ninoLive( $app, $action, $data, 'wrong' ), 'no token' => ninoLive( $app, $action, $data, null ), 'a wrong token in the header' => ninoLive( $app, $action, $data, null, 'wrong' ), 'no session and a right token' => ( function() use ( &$app, $action, $data, $token, $as ): array {
		$as( 'visitor' );
		$answer = ninoLive( $app, $action, $data, $token );
		$as( 'man' );
		return $answer;
	} )() ] as $label => $answer ) {

		$expectCode = $label === 'no session and a right token' ? 'session' : 'csrf';
		$expectStatus = $label === 'no session and a right token' ? 401 : 403;

		if( $answer['status'] !== $expectStatus || $code( $answer ) !== $expectCode || $disk() !== $before )
			$wrong[] = "$action, $label: ". $answer['status']. ' '. ( $code( $answer ) ?? '-' );
	}
}

check( 'users/save and users/delete with a wrong token, with none, and with no session refuse and leave config.php, the tries and the log byte for byte'. ( $wrong === [] ? '' : ' - '. implode( '; ', $wrong ) ), $wrong === [] );

$answer = ninoLive( $app, 'users/save', [ 'username' => 'ed2@example.com', 'mail' => 'ed2@example.com', 'name' => 'Through The Token' ], \Nino\Csrf::getToken( $app ) );
check( 'users/save with the right token answers 200 and the change is on disk', $answer['status'] === 200 && ( $read( '/config.php' )['/nino/auth/user']['ed2@example.com']['name'] ?? '' ) === 'Through The Token' );
$answer = ninoLive( $app, 'users/delete', [ 'username' => 'ed2@example.com' ], null, \Nino\Csrf::getToken( $app ) );
check( 'users/delete with the right token in the header answers 200 and the account is gone from disk', $answer['status'] === 200 && isset( $read( '/config.php' )['/nino/auth/user']['ed2@example.com'] ) === false );

check( 'the lock of config.php was free after every answer of every request above, refusals and successes of every action that takes it among them'. ( $held === [] ? '' : ' - '. implode( '; ', array_slice( $held, 0, 4 ) ) ), $held === [] );

ninoDone( $app );
