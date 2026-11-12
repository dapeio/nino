<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-system-smoke.php	Dependency-free smoke test for the system panels of the workbench
 *											(_admin/Admin.php): the recovery page - its secret, the account gate, a
 *											restore without the Backups module, the accounts it can make, an action
 *											that is no string -, Backups and Restore, Config and the Lockout tab,
 *											Language, the Recovery password, Maintenance, Users and Roles. The
 *											Restore checks count the temporary files a restore leaves in the
 *											system's temp directory, so run this suite alone: any other that makes
 *											a backup writes there as well. Runs against an isolated sandbox
 *											directory, never touches the real project data.
 *
 *	Usage: php tests/admin-system-smoke.php
 */

// Run as a child of itself (see "Recovery::handlePost without a Backups
// module" below): a kernel whose autoloader refuses the Backups panel, which
// is the only way to be without that class once this suite has loaded it.
// Prints the status the restore answered, or the error it threw
if( ( $argv[1] ?? '' ) === 'restore-without-backups' ) {
	require __DIR__. '/harness.php';
	$appData = ninoSandbox( 'recovery-child' );
	foreach( spl_autoload_functions() as $loader ) {
		spl_autoload_unregister( $loader );
		spl_autoload_register( function( string $class ) use ( $loader ): void {
			if( $class !== 'Nino\\Modules\\Backups\\Admin' )
				$loader( $class );
		} );
	}
	\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
	try {
		[ $status ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => 'not-a-date' ], 'recovery/restore' );
		echo $status;
	} catch( \Throwable $e ) {
		echo get_class( $e ). ': '. $e->getMessage();
	}
	\Nino\Filesystem::removeDir( ninoSandboxDir( $appData ) );
	exit;
}

// Run as a child of itself (see "The daily backup makes one attempt a day"
// below): the first authenticated request of the day over a project directory
// the parent made, with no memory pre-check and a memory limit its archive
// cannot stay under. Prints "survived" when it comes back; the fatal error of
// the limit is what the parent expects of the first run
if( ( $argv[1] ?? '' ) === 'daily-backup' ) {
	require __DIR__. '/harness.php';
	$appData = [ './nino/uid' => $argv[2] ];
	\Nino\AppData::prepare( $appData );
	$appData['./nino/filesystem/path']				= $argv[2];
	$appData['./nino/filesystem/configpath']	= $argv[2]. '/private';
	$appData['./nino/filesystem/contentpath']	= $argv[2]. '/private';
	$appData['./nino/filesystem/publicpath']	= $argv[2]. '/public';
	$appData['./nino/backups/memory']					= -1;
	\Nino\Modules\Backups::maybeRun( $appData );
	echo 'survived';
	exit;
}

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'admin-system' );
$sandbox = ninoSandboxDir( $appData );
mkdir( $sandbox. '/private/elements', 0777, true );

// A hand-authored-shaped type file (top-level 'title' key, real locale content) -
// apiSave must only ever touch 'title'/'model', never this real content
file_put_contents( $sandbox. '/private/elements/testtype.php', '<?php return [
	\'title\'	=> \'Test Type\',
	\'model\'	=> [ \'name\' => [ \'type\' => \'string\', \'locale\' => true ] ],
	\'*\'			=> [ \'*\' => [] ],
	\'de_DE\'	=> [ \'item1\' => [ \'name\' => \'Hallo\' ] ],
];' );

// Config::apiList() reads config.php fresh rather than $appData (see its
// docblock) - a real deployment always has $appData booted from this file
// in the first place, so mirror that here instead of only setting $appData
\Nino\Filesystem::putFileContent( $appData, '/config.php', [
	'/nino/error/display'			=> true,
	'/nino/auth/maxtries'			=> 5,
	'/nino/auth/cooldown'			=> 3600,
	'/nino/locales/native'		=> $appData['/nino/locales/native'],
	'/nino/locales/available'	=> $appData['/nino/locales/available'],
] );

// A set-up project with one developer account, logged in - the shell serves
// the workbench, every panel action runs against this session
$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );
ninoUser( $appData, 'dev@example.com' );

echo "Sandbox: $sandbox\n\n";


// --- Recovery secret and the account gate ---------------------------------

echo "Recovery::verify / Admin::guard / guardPerm\n";

check( 'a fresh checkout has no recovery secret at all', \Nino\Admin\Recovery::hash( $appData ) === null );
check( 'so every attempt fails rather than matching anything', \Nino\Admin\Recovery::verify( $appData, '' ) === 401 );
check( 'storing a secret succeeds', \Nino\Admin\Recovery::set( $appData, 'the real password' ) === true );
check( 'a wrong secret is still rejected', \Nino\Admin\Recovery::verify( $appData, 'not the password' ) === 401 );
check( 'the right one verifies', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 200 );

// 5 wrong attempts trip the lockout
for( $i = 0; $i < 5; $i++ )
	\Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' );
check( 'the 6th attempt is locked out (429), not just rejected as wrong', \Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' ) === 429 );
check( 'the lockout applies regardless of the secret tried next', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 429 );
// Reset the lockout state for the rest of the file - a fresh cooldown window shouldn't leak into later checks
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );

// A counter file that is not the counter. getFileContent()'s $default
// answers for a file that is not there; a file that IS there answers with
// what it holds, and an empty, truncated or unreadable .json decodes to
// null. Under a callback typed `array $state` that was a TypeError, ie. a
// 500 on the one door left to somebody who is locked out of the workbench,
// until the file was repaired by hand. Nothing here can produce such a file
// - _writeFile() renames a temp file into place - but a partial deploy, a
// hand edit or a uid mismatch on the read can
$lockoutFile = \Nino\Filesystem::path( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json' );
foreach( [ 'an empty' => '', 'a truncated' => '{ "tries": ', 'a non-array' => '7' ] as $what => $bytes ) {

	file_put_contents( $lockoutFile, $bytes );
	clearstatcache( true, $lockoutFile );
	unset( $appData['./nino/filesystem/cache'][ \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json' ] );

	check( $what. ' lockout.json still lets recovery answer, rather than 500ing the door shut', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 200 );
}
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );

// How long an answer takes is itself an answer. An installation whose
// secret file went missing must not reject faster than one that has a
// secret to reject against - see Recovery::DECOY_HASH
$attempt = static function() use ( &$appData ): float {
	// A fresh window each time, so neither measurement is cut short by the cooldown
	\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );
	$start = hrtime( true );
	\Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' );
	return ( hrtime( true ) - $start ) / 1e6;
};

$withSecret = $attempt();
@unlink( \Nino\Admin\Recovery::path( $appData ) );
$withoutSecret = $attempt();

// The constant is private, so read it out of the source: a php that moves
// PASSWORD_DEFAULT on must fail a check here rather than quietly leave the
// decoy cheaper than the hashes set() writes
preg_match( '#DECOY_HASH = \'([^\']+)\'#', (string) @file_get_contents( __DIR__. '/../_admin/Admin.php' ), $decoy );
check( 'the decoy hash is of the kind and cost this php hashes with', isset( $decoy[1] ) === true && password_needs_rehash( $decoy[1], PASSWORD_DEFAULT ) === false );
check( 'losing the secret file leaves no stored hash', \Nino\Admin\Recovery::hash( $appData ) === null );
check( 'so nothing authenticates, least of all against the decoy', \Nino\Admin\Recovery::verify( $appData, 'definitely-the-wrong-password' ) === 401 );
check( 'and that rejection costs what one against a real hash costs ('. round( $withoutSecret ). ' ms vs '. round( $withSecret ). ' ms)', $withoutSecret > $withSecret / 4 );

check( 'the secret can be stored again for the rest of the file', \Nino\Admin\Recovery::set( $appData, 'the real password' ) === true );
\Nino\Filesystem::putFileContent( $appData, \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json', [ 'tries' => 0, 'until' => 0 ] );
$noMarker = $appData;
unset( $noMarker['/nino/install/completed'] );
check( 'the secret alone counts as installed - losing the marker must not reopen the wizard', \Nino\Admin\Admin::isInstalled( $noMarker ) === true );

// The gate every panel action stands behind: a session with an account, and
// for anything but the everyone-panels that account's permission
\Nino\Auth::logoutUser( $appData );
$guardRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
check( 'guard rejects an unauthed request', \Nino\Admin\Admin::guard( $appData, $guardRequest ) === false );
check( 'guard sets a 401', $guardRequest['/nino/http/response']['statusCode'] === 401 );

[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'types/list' );
check( 'a panel action is rejected while unauthed', $status === 401 );

ninoUser( $appData, 'editor@example.com', [ '/_admin/text/manage' ] );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'types/list' );
check( 'a structure panel\'s action is 403 for an account without its permission', $status === 403 );
check( '...and that panel is not in the account\'s navigation either', isset( \Nino\Admin\Admin::visiblePanels( $appData )['types'] ) === false && isset( \Nino\Admin\Admin::visiblePanels( $appData )['text'] ) === true );
check( 'a tab the account lacks the permission for is left off its pane', \Nino\Admin\Admin::visiblePanels( $appData )['text']['tabs'] === [] );

// The other way round: an account holding only a tab's permission gets the
// pane for the tab alone - no strip, since there is nothing to switch to
ninoUser( $appData, 'typesonly@example.com', [ '/_admin/types/manage' ] );
$typesOnly = \Nino\Admin\Admin::visiblePanels( $appData );
check( 'an account holding only a tab\'s permission gets the pane without the panel\'s own screen', isset( $typesOnly['elements'] ) === true && $typesOnly['elements']['own'] === false && array_keys( $typesOnly['elements']['tabs'] ) === [ 'types' ] );
check( '...rendered as the tab pane alone under the panel\'s own head - one tab is no strip, but the name stays', str_contains( \Nino\Admin\Admin::panesHtml( $appData ), '<div id="admin-content-elements" data-panel="elements" data-layout="page" hidden><div class="admin-panel-head"><h2 class="admin-panel-title">[[/_admin/nav/elements]]</h2><div class="admin-panel-actions"></div><button type="button" class="admin-panel-manual" data-manual="elements" aria-haspopup="dialog" aria-label="[[/_admin/common/label/manual]]" title="[[/_admin/common/label/manual]]">?</button></div><div id="admin-tab-types" data-tab="types" hidden><div id="types-list"></div><div id="types-form"></div></div></div>' ) === true );
\Nino\Auth::deleteUser( $appData, 'typesonly@example.com' );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'types/list' );
check( 'and 200 for full access', $status === 200 );
\Nino\Auth::deleteUser( $appData, 'editor@example.com' );

echo "\n";


// --- Recovery::handlePost without a Backups module -------------------

echo "Recovery::handlePost without a Backups module\n";

// A delivery may drop the Backups module; the recovery page then offers the
// password reset alone and a restore is answered 501 (see the 'recovery/list'
// case). The date check read \Nino\Modules\Backups\Admin::ID_PATTERN before
// the class_exists() guard, so without the module the constant read threw
// first: a 500 behind the one door that is open when nothing else is.
// Measured in a child process whose autoloader refuses that class (see the
// top of this file)
$childAnswer = trim( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( __FILE__ ). ' restore-without-backups 2>&1' ) );
check( 'a delivery without the Backups module answers recovery/restore with 501, not a 500'. ( $childAnswer !== '501' ? ' - child said: '. substr( $childAnswer, 0, 120 ) : '' ), $childAnswer === '501' );

echo "\n";


// --- Dev\Restore ----------------------------------------------------------

echo "Restore - reads Backup's output independently, doesn't call into _admin/Admin.php\n";

/** The activity log's lines that hold all of the given pieces, newest first */
$logLines = static function( string ...$pieces ) use ( &$appData ): array {
	return array_values( array_filter( \Nino\Modules\Logs\Admin::recentLines( $appData, 200 ), static function( string $line ) use ( $pieces ): bool {
		foreach( $pieces as $piece )
			if( str_contains( $line, $piece ) === false )
				return false;
		return true;
	} ) );
};

mkdir( $sandbox. '/_admin', 0777, true );
mkdir( $sandbox. '/_admin', 0777, true );

$appData['/nino/auth/maxtries'] 	= 5;
$appData['/nino/auth/cooldown'] 	= 3600;
$appData['/nino/auth/user'] 			= $appData['/nino/auth/user'] ?? [];
ninoUser( $appData, 'admin@example.com' );
// The guard checks above already ran today's backup, before this account
// existed - drop it so the one restored below carries the account
array_map( 'unlink', glob( $sandbox. '/private/.backups/*.php' ) ?: [] );

$guardOk = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::guard( $appData, $guardOk ); // bootstraps + creates today's backup as a side effect

check( 'Backup::maybeRun (via Admin::guard) writes the archive key into the private directory', is_file( $sandbox. '/private/.auth/backup-key.php' ) === true );

check( 'the archives land under the private directory, not in a tool folder', is_dir( $sandbox. '/private/.backups' ) === true );

// Regression: an install that already had Backup running (key already in
// config.php) before this out-of-config copy existed must still get one
// written on the next admin request - not just on the very first bootstrap
unlink( $sandbox. '/private/.auth/backup-key.php' );
\Nino\Admin\Admin::guard( $appData, $guardOk );
check( 'Backup::maybeRun re-creates a missing key copy on an already-bootstrapped install', is_file( $sandbox. '/private/.auth/backup-key.php' ) === true );

// Restore has to find the archives on an installation that is nothing but a
// kernel and the workbench shell - a broken one is what reaches for it, and by
// this line the test process is the opposite of that: a sandbox full of
// fixtures, accounts, panels and an appData every check above has written to.
// Only a subprocess can say what a bare boot sees. The driver below requires
// \Nino.php and _admin/Admin.php and nothing else, and asks the panel for its
// list: what it answers, it found on disk under private/, not in anything this
// file put there. That is why the panel keeps its own copy of the archive
// directory and the key path instead of reaching for \Nino\Modules\Backups'
// private constants (see its _backupDir() and _key()).
$standaloneDriver = $sandbox. '/restore-standalone.php';
file_put_contents( $standaloneDriver, '<?php
declare(strict_types=1);
require '. var_export( __DIR__. '/../_nino/Nino.php', true ). ';
require '. var_export( __DIR__. '/../_admin/Admin.php', true ). ';
set_error_handler( function() { return true; } );
$appData = [ "./nino/uid" => '. var_export( $sandbox, true ). ' ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"]				= '. var_export( $sandbox, true ). ';
$appData["./nino/filesystem/configpath"]	= '. var_export( $sandbox. '/private', true ). ';
$appData["./nino/filesystem/contentpath"] = '. var_export( $sandbox. '/private', true ). ';
$appData["./nino/filesystem/publicpath"]	= '. var_export( $sandbox. '/public', true ). ';
$appData["/nino/dir"] = "";
\Nino\AppData::init( $appData );
echo json_encode( [
	"dates" => \Nino\Modules\Backups\Admin::dates( $appData ),
] );
' );

$standalone = json_decode( (string) shell_exec( 'php '. escapeshellarg( $standaloneDriver ). ' 2>/dev/null' ), true ) ?? [];

check( '...and still finds the archives from there', in_array( date( 'Y-m-d' ), $standalone['dates'] ?? [], true ) === true );

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
$dates = $body['dates'] ?? [];
check( 'apiList finds today\'s backup without reading config.php\'s own copy of the dir/key', count( $dates ) === 1 && $dates[0] === date( 'Y-m-d' ) );

/*	"Back up now": one more archive, named by date and time, beside the daily
	one. Today's Y-m-d.php is the state before the day's work - the first
	authenticated request wrote it - and a click in the afternoon must not
	replace it. Done before the restores below, which write snapshots of their
	own into the same directory and would change what the checks above count	*/
$nowDir			= $sandbox. '/private/.backups';
$timedFiles	= static fn(): array => array_map( 'basename', glob( $nowDir. '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9].php' ) ?: [] );
$dailyFile	= $nowDir. '/'. date( 'Y-m-d' ). '.php';
$dailyBytes	= (string) file_get_contents( $dailyFile );

$nowId = \Nino\Modules\Backups::now( $appData );
check( 'Backups::now writes an archive named by date and time', is_string( $nowId ) === true && preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}$/', (string) $nowId ) === 1 && is_file( $nowDir. '/'. $nowId. '.php' ) === true );
check( '...behind the same stub a daily archive has, and no temporary file beside it', str_starts_with( (string) file_get_contents( $nowDir. '/'. $nowId. '.php' ), \Nino\Admin\Recovery::STUB_PREFIX ) && glob( $nowDir. '/*.tmp' ) === [] );
check( '...and leaves today\'s daily archive byte for byte as it was', (string) file_get_contents( $dailyFile ) === $dailyBytes );
check( 'dates() lists it before today\'s date, and an archive of its own is not a snapshot', \Nino\Modules\Backups\Admin::dates( $appData ) === [ $nowId, date( 'Y-m-d' ) ] );

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
check( 'apiList carries the dates and whether backups are on', ( $body['dates'] ?? [] ) === [ $nowId, date( 'Y-m-d' ) ] && ( $body['enabled'] ?? null ) === true );

$idPattern = \Nino\Modules\Backups\Admin::ID_PATTERN;
check( 'ID_PATTERN takes a date and time, and still turns away a time cut short and a path',
	preg_match( $idPattern, '2026-10-02-170512' ) === 1 && preg_match( $idPattern, '2026-10-02' ) === 1 && preg_match( $idPattern, 'pre-restore-2026-10-02-170512' ) === 1
	&& preg_match( $idPattern, '2026-10-02-1705' ) === 0 && preg_match( $idPattern, '../x' ) === 0 && preg_match( $idPattern, '2026-10-02-170512/../x' ) === 0 );

// Retention: the days the daily archives have, and no more than ten of these.
// A snapshot and a daily archive are not touched by it
$staleTimed	= date( 'Y-m-d', strtotime( '-20 days' ) ). '-120000';
$keptDaily	= date( 'Y-m-d', strtotime( '-3 days' ) );
$oldSnap		= 'pre-restore-'. date( 'Y-m-d', strtotime( '-20 days' ) ). '-120000';
foreach( [ $staleTimed, $keptDaily, $oldSnap ] as $name )
	file_put_contents( $nowDir. '/'. $name. '.php', 'x' );
for( $hour = 1; $hour <= 12; $hour++ )
	file_put_contents( $nowDir. '/'. date( 'Y-m-d', strtotime( '-1 day' ) ). '-'. sprintf( '%02d', $hour ). '0000.php', 'x' );

$pruneId = \Nino\Modules\Backups::now( $appData );
$timedNow = $timedFiles();
check( 'an archive of this kind older than the retention days is pruned', in_array( $staleTimed. '.php', $timedNow, true ) === false );
check( '...and only the newest ten are kept - the new one among them', count( $timedNow ) === 10 && in_array( $pruneId. '.php', $timedNow, true ) === true && in_array( $nowId. '.php', $timedNow, true ) === true
	&& in_array( date( 'Y-m-d', strtotime( '-1 day' ) ). '-010000.php', $timedNow, true ) === false && in_array( date( 'Y-m-d', strtotime( '-1 day' ) ). '-120000.php', $timedNow, true ) === true );
check( '...the daily archives and the snapshots are left to their own sweeps', is_file( $nowDir. '/'. $keptDaily. '.php' ) === true && is_file( $dailyFile ) === true && is_file( $nowDir. '/'. $oldSnap. '.php' ) === true );

// The panel's door
$nowCall = static function( array &$appData ): array {
	return ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ] );
};
$countBefore = count( $timedFiles() );
[ $status, $body ] = $nowCall( $appData );
check( 'apiNow answers the id of the archive it wrote', $status === 200 && is_string( $body['id'] ?? null ) === true && is_file( $nowDir. '/'. $body['id']. '.php' ) === true );
check( '...and the activity log names the action without calling it twice', \Nino\Modules\Backups\Admin::log( 'backups/now', [] ) === 'Create Backup' && \Nino\Modules\Backups\Admin::log( 'backups/list', [] ) === '' );

$appData['/nino/admin/backups'] = false;
[ $status ] = $nowCall( $appData );
$offFiles = count( $timedFiles() );
[ , $listBody ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
check( 'apiNow is 409 while backups are switched off, and writes nothing', $status === 409 && $offFiles === count( $timedFiles() ) && ( $listBody['enabled'] ?? null ) === false );
unset( $appData['/nino/admin/backups'] );

// An archive that cannot be written: an encryption key that is none
$goodKey = $appData['/nino/backup/key'];
$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => 'not a key' ] + $config );
$timedBefore = $timedFiles();
$tempBefore = count( glob( sys_get_temp_dir(). '/ninobackup*' ) ?: [] );
[ $status, $body ] = $nowCall( $appData );
check( 'a failing run is a 500 with its reason, writes no archive and leaves no temporary file', $status === 500 && is_string( $body['error'] ?? null ) === true && $timedFiles() === $timedBefore
	&& count( glob( sys_get_temp_dir(). '/ninobackup*' ) ?: [] ) === $tempBefore && glob( $nowDir. '/*.tmp' ) === [] );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => $goodKey ] + $config );
$appData['/nino/backup/key'] = $goodKey;
[ $status ] = $nowCall( $appData );
check( '...and the next one, with the key back, is fine', $status === 200 );

/*	The daily backup makes one attempt a day. An archive the request cannot
	hold ends that request with a fatal error no catch sees, and every later
	request found today's archive missing and died the same way - the
	workbench was out of reach until somebody edited config.php. The attempt
	is on disk before it starts now. Today's archive is put aside for these
	checks and comes back after them	*/
rename( $dailyFile, $dailyFile. '.kept' );
$attemptFile	= $nowDir. '/attempt.php';
$attemptNote	= static function( string $date, string $reason ): void {
	global $attemptFile;
	file_put_contents( $attemptFile, \Nino\Admin\Recovery::STUB_PREFIX. base64_encode( (string) json_encode( [ 'date' => $date, 'reason' => $reason ] ) ). \Nino\Admin\Recovery::STUB_SUFFIX );
};
$yesterday		= date( 'Y-m-d', strtotime( '-1 day' ) );
@unlink( $attemptFile );
ninoWarnings();

// A run that fails leaves its note, with the reason it logged
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => 'not a key' ] + $config );
$appData['/nino/backup/key'] = 'not a key';
\Nino\Modules\Backups::maybeRun( $appData );
$failWarnings = ninoWarnings();
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => $goodKey ] + $config );
$appData['/nino/backup/key'] = $goodKey;
check( 'a failing automatic run is logged and leaves a note of the attempt, but no archive', count( $failWarnings ) === 1 && str_contains( (string) $failWarnings[0], 'Backup failed' ) === true
	&& is_file( $attemptFile ) === true && is_file( $dailyFile ) === false );
check( '...the note says why, for the panel', ( \Nino\Modules\Backups::missed( $appData ) ?? [] ) === [ 'reason' => 'backup encryption key is invalid' ] );
[ , $listBody ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
check( '...and apiList carries it', ( $listBody['missed'] ?? null ) === [ 'reason' => 'backup encryption key is invalid' ] );

// The key is back, the next request is the same day: no second attempt
\Nino\Modules\Backups::maybeRun( $appData );
check( 'the next request of the day does not try again, though nothing is in its way any more', is_file( $dailyFile ) === false && ninoWarnings() === [] );

// A request that died in the attempt wrote the note before it began, without a reason
$attemptNote( date( 'Y-m-d' ), '' );
\Nino\Modules\Backups::maybeRun( $appData );
check( '...nor after a note that carries no reason, which is what a request that ended inside the attempt leaves', is_file( $dailyFile ) === false
	&& ( \Nino\Modules\Backups::missed( $appData ) ?? [] ) === [ 'reason' => '' ] );

// The next day it tries again
$attemptNote( $yesterday, 'backup encryption key is invalid' );
check( 'the note of an earlier day says nothing is missed today', \Nino\Modules\Backups::missed( $appData ) === null );
\Nino\Modules\Backups::maybeRun( $appData );
check( 'the next day the attempt is made, and with it done the note is gone', is_file( $dailyFile ) === true && is_file( $attemptFile ) === false && \Nino\Modules\Backups::missed( $appData ) === null );

// A run by hand always tries, and a success makes the state healthy again
unlink( $dailyFile );
$attemptNote( date( 'Y-m-d' ), 'backup encryption key is invalid' );
$handId = \Nino\Modules\Backups::now( $appData );
check( 'Backup now works while the day is marked, and clears the mark', is_string( $handId ) === true && is_file( $nowDir. '/'. $handId. '.php' ) === true && is_file( $attemptFile ) === false
	&& \Nino\Modules\Backups::missed( $appData ) === null );
\Nino\Modules\Backups::maybeRun( $appData );
check( '...after which the automatic one is tried again', is_file( $dailyFile ) === true );

// The note is no archive: not offered, not swept, not readable as php output
$attemptNote( date( 'Y-m-d' ), 'x' );
unlink( $dailyFile );
$listed = \Nino\Modules\Backups\Admin::dates( $appData );
( new ReflectionMethod( '\Nino\Modules\Backups', '_prune' ) )->invoke( null, $nowDir );
$noteStub = (string) file_get_contents( $attemptFile );
check( 'the note of the attempt is not listed as an archive', in_array( 'attempt', $listed, true ) === false && \Nino\Modules\Backups::lastDate( $appData ) !== 'attempt'
	&& preg_match( \Nino\Modules\Backups\Admin::ID_PATTERN, 'attempt' ) === 0 );
check( '...is left alone by the sweep of the archives', is_file( $attemptFile ) === true && $noteStub !== '' );
check( '...and is behind the stub that ends a request for it', str_starts_with( $noteStub, \Nino\Admin\Recovery::STUB_PREFIX ) === true && str_ends_with( $noteStub, \Nino\Admin\Recovery::STUB_SUFFIX ) === true );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => 'attempt' ] );
check( '...nor can it be asked for as a restore', $status === 400 );
unlink( $attemptFile );

// What a step holds of memory, set against the limit where its size is known:
// the largest file before the archive is built, the compressed archive before
// it is read. What compresses costs nothing; the sources are never held together
$imageDir = $sandbox. '/public/images';
$formsDir = $sandbox. '/private/data';
@mkdir( $imageDir, 0777, true );
@mkdir( $formsDir, 0777, true );
$mb = 1048576;
$withLimit = static function( int $limit ) use ( &$appData ): array|string {
	$appData['./nino/backups/memory'] = $limit;
	$result = \Nino\Modules\Backups::now( $appData );
	unset( $appData['./nino/backups/memory'] );
	return $result;
};
$tempLeft = static fn(): int => count( glob( sys_get_temp_dir(). '/ninobackup*' ) ?: [] );
$dropNew = static function( array $before ) use ( $timedFiles, $nowDir ): void {
	foreach( array_diff( $timedFiles(), $before ) as $name )
		unlink( $nowDir. '/'. $name );
};

// Compressible sources many times the limit: a backup that works carries on
$wide = [];
for( $i = 0; $i < 8; $i++ ) {
	$wide[] = $formsDir. '/forms.wide'. $i. '.php';
	file_put_contents( $wide[$i], str_repeat( 'a submission, much alike the next one. ', (int) ( 6 * $mb / 39 ) ) );
}
$timedBefore = $timedFiles();
$tempBefore = $tempLeft();
$carried = $withLimit( memory_get_usage() + 16 * $mb + 6 * $mb + 3 * $mb );
check( 'sources several times the limit that compress away are archived: it is the archive that costs memory, not its sources', is_string( $carried ) === true && $tempLeft() === $tempBefore );
$dropNew( $timedBefore );
array_map( 'unlink', $wide );

// Incompressible ones: refused once the compressed archive is known, with nothing left behind
$noise = [ $imageDir. '/noise-a.bin', $imageDir. '/noise-b.bin' ];
foreach( $noise as $file )
	file_put_contents( $file, random_bytes( 6 * $mb ) );
$timedBefore = $timedFiles();
$tempBefore = $tempLeft();
$refused = $withLimit( memory_get_usage() + 30 * $mb );
check( 'incompressible sources are refused once the compressed archive is in the way, as the 500 now() answers, with the sizes', is_array( $refused ) === true && $refused[0] === 500
	&& preg_match( '/\d+ MB for the compressed archive, \d+ MB in use\) and the limit is \d+ MB/', (string) $refused[1] ) === 1 );
check( '...naming no path, writing no archive and leaving no temporary file', str_contains( (string) $refused[1], $sandbox ) === false && str_contains( (string) $refused[1], sys_get_temp_dir() ) === false
	&& $timedFiles() === $timedBefore && $tempLeft() === $tempBefore && glob( $nowDir. '/*.tmp' ) === [] );
check( '...and the same sources are carried by a limit that holds the archive twice', is_string( $withLimit( memory_get_usage() + 80 * $mb ) ) === true );
$dropNew( $timedBefore );

// One file larger than the limit leaves: refused before anything is built
$timedBefore = $timedFiles();
$tooBig = $withLimit( memory_get_usage() + 10 * $mb );
check( 'a single file the limit cannot read whole is refused before a temporary file is made', is_array( $tooBig ) === true && $tooBig[0] === 500
	&& preg_match( '/\d+ MB for the largest file, \d+ MB in use\) and the limit is \d+ MB/', (string) $tooBig[1] ) === 1 && $tempLeft() === $tempBefore && $timedFiles() === $timedBefore );
$tooBig = $withLimit( memory_get_usage() + 16 * $mb + 3 * $mb );
check( 'the file alone makes the refusal: a limit above the overhead and the use, but below them and the largest file, is refused at that stage', is_array( $tooBig ) === true
	&& preg_match( '/\d+ MB for the largest file/', (string) $tooBig[1] ) === 1 );
check( '...and the same sources pass once the limit is above that', is_string( $withLimit( memory_get_usage() + 80 * $mb ) ) === true );
$dropNew( $timedBefore );
check( '-1 is no limit at all, and so is a huge one; a limit that is no int is not one', is_string( $withLimit( -1 ) ) === true && is_string( $withLimit( 1 << 40 ) ) === true );
$appData['./nino/backups/memory'] = '1';
$stringLimit = \Nino\Modules\Backups::now( $appData );
unset( $appData['./nino/backups/memory'] );
check( '...the string "1" left to php\'s own limit', is_string( $stringLimit ) === true );
$dropNew( $timedBefore );

// An archive larger than one piece of the reader opens whole
$bigId = \Nino\Modules\Backups::now( $appData );
$noiseBytes = (string) file_get_contents( $noise[0] );
file_put_contents( $noise[0], 'replaced' );
$snapshotsHad = glob( $nowDir. '/pre-restore-*.php' ) ?: [];
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $bigId ] );
check( 'an archive of several pieces of the reader restores byte for byte', is_string( $bigId ) === true && filesize( $nowDir. '/'. $bigId. '.php' ) > 3 * 786432 && $status === 200 && (string) file_get_contents( $noise[0] ) === $noiseBytes );
foreach( array_diff( glob( $nowDir. '/pre-restore-*.php' ) ?: [], $snapshotsHad ) as $file )
	unlink( $file );
unlink( $nowDir. '/'. $bigId. '.php' );
array_map( 'unlink', $noise );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
// The request of the restore was one of the workbench: it made today's archive
@unlink( $dailyFile );
$dropNew( $timedBefore );

// The parked archive of a restore is the plain archive, config.php among it: only its owner reads it
if( DIRECTORY_SEPARATOR === '/' ) {
	$parkedId = \Nino\Modules\Backups::now( $appData );
	$parkedMode = null;
	$probe = new class( $parkedMode ) implements ArrayAccess {
		public bool $armed = false;
		public function __construct( public ?int &$mode ) {}
		public function offsetExists( mixed $key ): bool { return true; }
		public function offsetSet( mixed $key, mixed $value ): void {}
		public function offsetUnset( mixed $key ): void {}
		public function offsetGet( mixed $key ): mixed {
			$parked = glob( sys_get_temp_dir(). '/ninorestore*.tar.gz' ) ?: [];
			if( $this->armed === true && $parked !== [] ) {
				usort( $parked, static fn( string $a, string $b ): int => filemtime( $b ) <=> filemtime( $a ) );
				$this->mode = fileperms( $parked[0] ) & 0777;
				throw new \RuntimeException( 'probe' );
			}
			return $key === 'data' ? [] : ( $key === 'active' ? false : '' );
		}
	};
	$appData['./nino/features/all'] = [ 'probe' => $probe ];
	$probe->armed = true;
	[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $parkedId ] );
	unset( $appData['./nino/features/all'] );
	check( 'the archive a restore parks in the temp directory across the safety snapshot is readable by its owner alone', $status === 500 && $parkedMode === 0600 );
	unlink( $nowDir. '/'. $parkedId. '.php' );
	@unlink( $dailyFile );
}

// The note: what it reports, the day it names, what it is read as
$reason = new ReflectionMethod( '\Nino\Modules\Backups', '_reason' );
check( 'the reason in the note is the message of an exception of the class itself, and nothing of any other - a path never reaches it',
	$reason->invoke( null, new \RuntimeException( 'backup encryption key is invalid' ) ) === 'backup encryption key is invalid'
	&& $reason->invoke( null, new \UnexpectedValueException( 'internal corruption of phar "'. sys_get_temp_dir(). '/ninobackup1.tar"' ) ) === ''
	&& $reason->invoke( null, new \PharException( $sandbox ) ) === '' && $reason->invoke( null, new \LogicException( 'x' ) ) === '' );
// A run that fails notes the day it began - the pre-note and the note of its failure alike
$appData['./nino/backups/today'] = '2000-01-01';
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => 'not a key' ] + $config );
$appData['/nino/backup/key'] = 'not a key';
\Nino\Modules\Backups::maybeRun( $appData );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/backup/key' => $goodKey ] + $config );
$appData['/nino/backup/key'] = $goodKey;
unset( $appData['./nino/backups/today'] );
ninoWarnings();
$began = ( new ReflectionMethod( '\Nino\Modules\Backups', '_readAttempt' ) )->invoke( null, $nowDir );
check( 'the note of a failed run carries the day the run began', $began === [ 'date' => '2000-01-01', 'reason' => 'backup encryption key is invalid' ] );
unlink( $attemptFile );
( new ReflectionMethod( '\Nino\Modules\Backups', '_noteAttempt' ) )->invoke( null, $nowDir, $yesterday, 'late' );
$late = (string) file_get_contents( $attemptFile );
check( 'the note carries the day the attempt began, not the day it is written on', ( \Nino\Modules\Backups::missed( $appData ) ) === null
	&& str_contains( base64_decode( substr( $late, strlen( \Nino\Admin\Recovery::STUB_PREFIX ), -strlen( \Nino\Admin\Recovery::STUB_SUFFIX ) ), true ) ?: '', $yesterday ) === true );
$forged = str_repeat( 'X', strlen( \Nino\Admin\Recovery::STUB_PREFIX ) ). base64_encode( (string) json_encode( [ 'date' => date( 'Y-m-d' ), 'reason' => 'forged' ] ) ). \Nino\Admin\Recovery::STUB_SUFFIX;
file_put_contents( $attemptFile, $forged );
check( 'a file that is no stub is no note', \Nino\Modules\Backups::missed( $appData ) === null );
$attemptNote( date( 'Y-m-d' ), 'x' );
$appData['/nino/admin/backups'] = false;
[ , $listBody ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
unset( $appData['/nino/admin/backups'] );
check( 'apiList says nothing is missed while backups are switched off', array_key_exists( 'missed', $listBody ?? [] ) === true && $listBody['missed'] === null );
unlink( $attemptFile );

// The same, for real: a child whose memory limit the archive does not fit in
// (and with the check that would have turned it away left out) ends in the
// fatal error. The request after it comes back, and today's archive is not made
file_put_contents( $imageDir. '/dies.bin', random_bytes( 40 * 1048576 ) );
@mkdir( $sandbox. '/tmp', 0777, true );
$childCommand = escapeshellarg( PHP_BINARY ). ' -d memory_limit=48M -d sys_temp_dir='. escapeshellarg( $sandbox. '/tmp' ). ' '. escapeshellarg( __FILE__ ). ' daily-backup '. escapeshellarg( $sandbox ). ' 2>&1';
$first = (string) shell_exec( $childCommand );
check( 'a request that runs out of memory in the daily backup ends, and leaves its note', str_contains( $first, 'survived' ) === false && is_file( $attemptFile ) === true
	&& is_file( $dailyFile ) === false && ( \Nino\Modules\Backups::missed( $appData ) ?? [] ) === [ 'reason' => '' ] );
$second = (string) shell_exec( $childCommand );
check( 'the request after it is answered - the workbench is not out of reach - and does not make the attempt again', str_contains( $second, 'survived' ) === true && is_file( $dailyFile ) === false );
unlink( $imageDir. '/dies.bin' );
// What the child left in its own temp directory goes with the sandbox, not into the system's
@unlink( $attemptFile );
rename( $dailyFile. '.kept', $dailyFile );

/*	An archive of this writer is the one the readers always had: the stub is
	the prefix, the base64 of iv, tag and ciphertext, the suffix - cut into
	pieces to write, not changed	*/
$pieceCheck = true;
foreach( [ 0, 1, 2, 3, 4, 5, 100, 786432, 786433, 786434, 786435, 786437, 2 * 786432 + 7 ] as $length ) {
	$iv = random_bytes( 12 ); $tag = random_bytes( 16 ); $body = $length === 0 ? '' : random_bytes( $length );
	if( implode( '', iterator_to_array( \Nino\Admin\Recovery::stubPieces( $iv, $tag, $body ), false ) ) !== \Nino\Admin\Recovery::STUB_PREFIX. base64_encode( $iv. $tag. $body ). \Nino\Admin\Recovery::STUB_SUFFIX )
		$pieceCheck = false;
}
check( 'the stub in pieces is the stub in one, for every length around the size of a piece', $pieceCheck === true );

\Nino\Auth::logoutUser( $appData );
$timedBefore = $timedFiles();
[ $status ] = $nowCall( $appData );
check( 'apiNow is 401 logged out', $status === 401 && $timedFiles() === $timedBefore );
ninoUser( $appData, 'usersmanageonly@example.com', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
[ $status ] = $nowCall( $appData );
check( '...403 without the backups permission', $status === 403 && $timedFiles() === $timedBefore );
\Nino\Auth::deleteUser( $appData, 'usersmanageonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

/*	What an archive holds besides the texts and the elements: the pages and
	the look the Builder and Design write - templates/*.tpl and assets/ - and a
	note of the state it comes from, nino.php. Read back from the outside, as
	somebody with the key reads one: the stub spelled out here and not taken
	from the class that writes it, the payload decrypted with the key the
	project keeps, the tar.gz opened with PharData. Names are the suite's own;
	no file of the product is pinned	*/
$stubPrefix	= "<?php http_response_code(403); exit; return '";
$stubSuffix	= "';\n";
$archiveKey	= base64_decode( substr( (string) file_get_contents( $sandbox. '/private/.auth/backup-key.php' ), strlen( $stubPrefix ), -strlen( $stubSuffix ) ), true );

$readArchive = static function( string $id ) use ( $nowDir, $stubPrefix, $stubSuffix, $archiveKey ): array {

	$payload	= base64_decode( substr( (string) file_get_contents( $nowDir. '/'. $id. '.php' ), strlen( $stubPrefix ), -strlen( $stubSuffix ) ), true );
	$gz				= openssl_decrypt( substr( (string) $payload, 28 ), 'aes-256-gcm', (string) $archiveKey, OPENSSL_RAW_DATA, substr( (string) $payload, 0, 12 ), substr( (string) $payload, 12, 16 ) );
	$tmp			= sys_get_temp_dir(). '/ninotest-archive-'. bin2hex( random_bytes( 6 ) ). '.tar.gz';
	$entries	= [];

	file_put_contents( $tmp, (string) $gz );
	foreach( new RecursiveIteratorIterator( new PharData( $tmp ) ) as $file )
		$entries[ substr( $file->getPathname(), strlen( 'phar://'. $tmp. '/' ) ) ] = $file->getContent();
	unlink( $tmp );

	return $entries;
};

$writeArchive = static function( string $id, array $entries ) use ( $nowDir, $stubPrefix, $stubSuffix, $archiveKey ): void {

	$base	= sys_get_temp_dir(). '/ninotest-pack-'. bin2hex( random_bytes( 6 ) );
	$tar	= new PharData( $base. '.tar' );
	foreach( $entries as $name => $bytes )
		$tar->addFromString( $name, $bytes );
	$tar->compress( Phar::GZ );
	unset( $tar );

	$iv			= random_bytes( 12 );
	$tag		= '';
	$cipher	= openssl_encrypt( (string) file_get_contents( $base. '.tar.gz' ), 'aes-256-gcm', (string) $archiveKey, OPENSSL_RAW_DATA, $iv, $tag );
	file_put_contents( $nowDir. '/'. $id. '.php', $stubPrefix. base64_encode( $iv. $tag. (string) $cipher ). $stubSuffix );
	unlink( $base. '.tar' );
	unlink( $base. '.tar.gz' );
};

$pagesDir		= $sandbox. '/private/templates';
$assetsDir	= $sandbox. '/private/assets';
$snapshotsBefore = glob( $nowDir. '/pre-restore-*.php' ) ?: [];

\Nino\Filesystem::putFileContent( $appData, '/templates/page-probe.tpl', 'probe page, as archived' );
\Nino\Filesystem::putFileContent( $appData, '/assets/probe.css', '.probe { color: #123456; }' );
\Nino\Filesystem::putFileContent( $appData, '/assets/nested/deep.css', '.deep { color: #654321; }' );
// What is not a template: a directory with the name of one, a file with another
// extension and a link, which is not followed out of the directory
mkdir( $pagesDir. '/folder.tpl' );
file_put_contents( $pagesDir. '/notes.txt', 'not a template' );
$linked = @symlink( 'page-probe.tpl', $pagesDir. '/link.tpl' );

// Two features, one on: only the one that is on is in the note
$appData['./nino/features/all'] = [
	'stamped'	=> [ 'key' => 'stamped', 'dir' => '', 'data' => [], 'active' => true, 'problems' => [], 'version' => '2.3.4', 'installed' => '2.3.4', 'module' => '' ],
	'resting'	=> [ 'key' => 'resting', 'dir' => '', 'data' => [], 'active' => false, 'problems' => [], 'version' => '1.0.0', 'installed' => null, 'module' => '' ],
];

$pagesId		= \Nino\Modules\Backups::now( $appData );
$entries		= is_string( $pagesId ) === true ? $readArchive( $pagesId ) : [];
$stampFile	= $sandbox. '/stamp-probe.php';
file_put_contents( $stampFile, $entries['nino.php'] ?? '' );
$stamped		= include $stampFile;
unlink( $stampFile );
unset( $appData['./nino/features/all'] );

check( 'an archive carries the templates of the project, each as templates/<name>', ( $entries['templates/page-probe.tpl'] ?? null ) === 'probe page, as archived' );
check( '...and every file below assets/, nested ones at their relative path', ( $entries['assets/probe.css'] ?? null ) === '.probe { color: #123456; }' && ( $entries['assets/nested/deep.css'] ?? null ) === '.deep { color: #654321; }' );
check( '...but not a directory that is called x.tpl, a file that is no .tpl or a link'. ( $linked === true ? '' : ' (no link could be made here)' ),
	isset( $entries['templates/folder.tpl'] ) === false && isset( $entries['templates/notes.txt'] ) === false && isset( $entries['templates/link.tpl'] ) === false );
check( 'the archive names the state it comes from: nino.php holds the version that runs and the features that are on, by their key', $stamped === [ 'nino' => \Nino\VERSION, 'features' => [ 'stamped' => '2.3.4' ] ] );
check( '...and no file of the manifest is called that, since the note is no file of the project', in_array( 'nino.php', \Nino\Backup::manifest( $appData ), true ) === false && is_string( $pagesId ) === true );

// Changed after the archive, and one more written after it: the archive's own
// come back as they were, the one it has not stays
\Nino\Filesystem::putFileContent( $appData, '/templates/page-probe.tpl', 'probe page, changed after the archive' );
\Nino\Filesystem::putFileContent( $appData, '/assets/probe.css', '.probe { color: #abcdef; }' );
\Nino\Filesystem::putFileContent( $appData, '/assets/nested/deep.css', '.deep { color: #fedcba; }' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-late.tpl', 'probe page, written after the archive' );
\Nino\Filesystem::putFileContent( $appData, '/assets/late.css', '.late { color: #111111; }' );

$restoreActor = \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '';
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $pagesId ] );
$pageSnapshots = array_values( array_diff( glob( $nowDir. '/pre-restore-*.php' ) ?: [], $snapshotsBefore ) );
check( 'a restore brings the templates and the assets back as they were archived, nested ones too', $status === 200 && ( $body['ok'] ?? false ) === true
	&& file_get_contents( $pagesDir. '/page-probe.tpl' ) === 'probe page, as archived' && file_get_contents( $assetsDir. '/probe.css' ) === '.probe { color: #123456; }'
	&& file_get_contents( $assetsDir. '/nested/deep.css' ) === '.deep { color: #654321; }' );
check( '...and leaves a template and an asset that were written after the archive, as the texts and the elements are treated', file_get_contents( $pagesDir. '/page-late.tpl' ) === 'probe page, written after the archive'
	&& file_get_contents( $assetsDir. '/late.css' ) === '.late { color: #111111; }' );
check( '...and the note of where the archive comes from is none of the files it puts back: nothing named nino.php is left in the project\'s root', is_file( $sandbox. '/nino.php' ) === false );
check( '...an archive of the version that runs carries no notice, in the answer or in the activity log', isset( $body['notice'] ) === false
	&& str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $appData, 20 ) ), 'comes from Nino' ) === false );

// The most powerful action of the workbench leaves its line, with who did it and
// which archive: the session it ends is the one the shell would ask for the
// actor, and the activity log is none of what an archive holds
check( 'a restore writes its line to the activity log - the account that ran it, the archive - and it is there after the files were put back',
	$restoreActor !== '' && count( $logLines( $restoreActor, 'Restore Backup '. $pagesId ) ) === 1
	&& str_contains( $logLines( 'Restore Backup '. $pagesId )[0], 'failed' ) === false );

// The safety snapshot a restore makes holds what an archive holds: the state it
// replaced, templates and assets with it, and the note
$snapshot = count( $pageSnapshots ) === 1 ? $readArchive( basename( $pageSnapshots[0], '.php' ) ) : [];
check( 'the safety snapshot before the restore carries the templates and the assets of the state it replaces, and the note', ( $snapshot['templates/page-probe.tpl'] ?? null ) === 'probe page, changed after the archive'
	&& ( $snapshot['assets/probe.css'] ?? null ) === '.probe { color: #abcdef; }' && ( $snapshot['templates/page-late.tpl'] ?? null ) === 'probe page, written after the archive'
	&& is_string( $snapshot['nino.php'] ?? null ) === true && str_contains( $snapshot['nino.php'], \Nino\VERSION ) === true );

// The same archive with its note rewritten to another version, one with the same
// version and one made before there was a note
$otherId		= date( 'Y-m-d' ). '-000001';
$sameId			= date( 'Y-m-d' ). '-000002';
$noNoteId		= date( 'Y-m-d' ). '-000003';
$writeArchive( $otherId, array_merge( $entries, [ 'nino.php' => '<?php return '. var_export( [ 'nino' => '0.9.1', 'features' => [] ], true ). ';' ] ) );
$writeArchive( $sameId, array_merge( $entries, [ 'nino.php' => '<?php return '. var_export( [ 'nino' => \Nino\VERSION, 'features' => [ 'other' => '9.9.9' ] ], true ). ';' ] ) );
$writeArchive( $noNoteId, array_diff_key( $entries, [ 'nino.php' => true ] ) );

// A restore ends the session of the account that ran it (see apiRestore()):
// signed in again before each one that follows another
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $otherId ] );
check( 'a restore of an archive from another version of Nino answers with a notice: the code, and the version it comes from and the one that runs', $status === 200 && ( $body['ok'] ?? false ) === true
	&& ( $body['notice'] ?? null ) === [ 'code' => 'backups_restored_other_version', 'params' => [ '0.9.1', \Nino\VERSION ] ] );
check( '...restores it all the same, nothing is planted in the root for the note, and the activity log says it in a line of its own',
	file_get_contents( $pagesDir. '/page-probe.tpl' ) === 'probe page, as archived' && is_file( $sandbox. '/nino.php' ) === false
	&& str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $appData, 20 ) ), 'Backup '. $otherId. ' comes from Nino 0.9.1, this is '. \Nino\VERSION. ' (backups_restored_other_version)' ) === true );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $sameId ] );
check( 'an archive whose note names the version that runs has no notice, whatever its features were', $status === 200 && ( $body['ok'] ?? false ) === true && isset( $body['notice'] ) === false );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $noNoteId ] );
check( '...and an archive with no note at all restores as it always did, with nothing to say', $status === 200 && ( $body['ok'] ?? false ) === true && isset( $body['notice'] ) === false && is_file( $sandbox. '/nino.php' ) === false );

// A restore that fails writes its line as well, and one that cannot start
// because another is running answers 409 and copies nothing
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$failActor = \Nino\Auth::getCurrentUser( $appData )['mail'] ?? '';
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2001-02-03' ] );
check( 'a restore of a date that has no archive is 404 and its line is in the activity log, marked as failed', $status === 404
	&& count( $logLines( $failActor, 'Restore Backup 2001-02-03', 'failed (404)' ) ) === 1 );

\Nino\Filesystem::putFileContent( $appData, '/templates/page-probe.tpl', 'changed while a restore runs' );
$snapshotsHeld	= glob( $nowDir. '/pre-restore-*.php' ) ?: [];
$holder					= $appData;
$holder['./nino/filesystem/locks'] = [];
check( 'the lock of the restore can be held by another process', \Nino\Filesystem::lockFile( $holder, \Nino\Modules\Backups::LOCK_PATH ) === true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $pagesId ] );
check( 'a restore while another runs is 409 with a code, and copies nothing and makes no snapshot', $status === 409 && ( $body['code'] ?? '' ) === 'backups_busy'
	&& file_get_contents( $pagesDir. '/page-probe.tpl' ) === 'changed while a restore runs' && ( glob( $nowDir. '/pre-restore-*.php' ) ?: [] ) === $snapshotsHeld );
check( '...the session of the account stays, and the line says it failed', \Nino\Auth::getCurrentUser( $appData ) !== false && count( $logLines( $failActor, 'Restore Backup '. $pagesId, 'failed (409)' ) ) === 1 );
\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $pagesId ], 'recovery/restore' );
check( 'the recovery page takes the same lock: 409, and nothing copied', $status === 409 && ( $body['code'] ?? '' ) === 'backups_busy'
	&& file_get_contents( $pagesDir. '/page-probe.tpl' ) === 'changed while a restore runs' );
\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );
// The daily run does not start in the middle of a restore: the same lock, and it
// is not waited for (a request that waited would restore a second time)
$appData['./nino/backups/today'] = '2099-01-01';
ninoWarnings();
\Nino\Modules\Backups::maybeRun( $appData );
check( 'the daily backup does not start while a restore holds the lock', is_file( $nowDir. '/2099-01-01.php' ) === false );
check( '...and a lock that is merely taken is nothing to log', ninoWarnings() === [] );
\Nino\Filesystem::unlockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $pagesId ] );
\Nino\Modules\Backups::maybeRun( $appData );
check( '...and makes the day\'s archive as soon as it is free', is_file( $nowDir. '/2099-01-01.php' ) === true );
unlink( $nowDir. '/2099-01-01.php' );

// A lock that cannot be had at all is not "taken": the directory of the locks is
// a file, the run fails and says so in the log
$locksDir = \Nino\Filesystem::path( $appData, '/data/.locks' );
rename( $locksDir, $locksDir. '-aside' );
file_put_contents( $locksDir, 'not a directory' );
$unlockable = $appData;
unset( $unlockable['./nino/filesystem/locks'] );
ninoWarnings();
\Nino\Modules\Backups::maybeRun( $unlockable );
$lockWarnings = ninoWarnings();
unlink( $locksDir );
rename( $locksDir. '-aside', $locksDir );
check( 'a lock the daily backup cannot have at all is a failure in the log, and no archive', count( $lockWarnings ) === 1 && str_contains( $lockWarnings[0], 'daily backup could not be locked' ) === true && is_file( $nowDir. '/2099-01-01.php' ) === false );
unset( $appData['./nino/backups/today'] );
check( '...and with the lock free again the restore runs', $status === 200 && file_get_contents( $pagesDir. '/page-probe.tpl' ) === 'probe page, as archived' );
check( '...and gives the lock back, so the next one is not turned away', \Nino\Filesystem::lockFile( $holder, \Nino\Modules\Backups::LOCK_PATH, false ) === true );
\Nino\Filesystem::unlockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );

// The recovery page restores through the same function and answers the same way
\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $otherId ], 'recovery/restore' );
check( 'the recovery page carries the notice in its answer too', $status === 200 && ( $body['restoredDate'] ?? null ) === $otherId
	&& ( $body['notice'] ?? null ) === [ 'code' => 'backups_restored_other_version', 'params' => [ '0.9.1', \Nino\VERSION ] ] );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $sameId ], 'recovery/restore' );
check( '...and none for an archive of the version that runs', $status === 200 && ( $body['restoredDate'] ?? null ) === $sameId && isset( $body['notice'] ) === false );
check( 'the recovery page writes the line of its restore too, with the door as the actor', count( $logLines( 'recovery.php', 'Restore Backup '. $otherId ) ) === 1
	&& count( $logLines( 'recovery.php', 'Restore Backup '. $pagesId, 'failed (409)' ) ) === 1 );
\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );

// --- Templates in the folders of templates/ -------------------------------
//
// A module or a feature keeps its templates in a folder of its own
// (templates/<folder>/<name>.tpl). The archive carries them at their relative
// path, a restore makes the folder where it is missing and puts the bytes back,
// and the rules of the top level - a directory called x.tpl, a link, a file
// that is no template - hold in every depth. What a restore refuses is read
// from the tar itself: the unpacking would turn 'templates/../x.tpl' into a
// plain 'x.tpl' and copy that into the project
echo "Templates in folders of templates/\n";

$tarEntry = static function( string $name, string $content = '', string $type = '0', string $link = '', ?int $size = null, string $prefix = '', string $magic = "ustar\0". '00' ): string {

	$header = str_pad( $name, 100, "\0" ). sprintf( '%07o', 0644 ). "\0". sprintf( '%07o', 0 ). "\0". sprintf( '%07o', 0 ). "\0"
		. sprintf( '%011o', $size ?? strlen( $content ) ). "\0". sprintf( '%011o', 0 ). "\0". '        '. $type. str_pad( $link, 100, "\0" )
		. $magic. str_repeat( "\0", 32 + 32 + 16 ). str_pad( $prefix, 155, "\0" ). str_repeat( "\0", 12 );
	$sum = array_sum( array_map( 'ord', str_split( $header ) ) );

	return substr( $header, 0, 148 ). sprintf( '%06o', $sum ). "\0 ". substr( $header, 156 ). $content. str_repeat( "\0", ( 512 - strlen( $content ) % 512 ) % 512 );
};
$tarLong = static fn( string $name ): string => $tarEntry( '././@LongLink', $name. "\0", 'L' );

$writeTar = static function( string $id, string $tar ) use ( $nowDir, $stubPrefix, $stubSuffix, $archiveKey ): void {

	$iv			= random_bytes( 12 );
	$tag		= '';
	$cipher	= openssl_encrypt( gzencode( $tar. str_repeat( "\0", 1024 ) ), 'aes-256-gcm', (string) $archiveKey, OPENSSL_RAW_DATA, $iv, $tag );
	file_put_contents( $nowDir. '/'. $id. '.php', $stubPrefix. base64_encode( $iv. $tag. (string) $cipher ). $stubSuffix );
};

/** Every file below the sandbox, by relative path, without what a restore writes in passing: the archives, the log and the locks */
$projectFiles = static function() use ( $sandbox ): array {

	$names = [];
	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sandbox, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$relative = substr( $file->getPathname(), strlen( $sandbox ) + 1 );
		if( preg_match( '#^private/(\.backups|\.logs|data/\.locks)/#', $relative ) !== 1 )
			$names[] = $relative;
	}
	sort( $names );

	return $names;
};

$folderTemplates = [
	'crm-portal/list.tpl'						=> "list \xff\xfe bytes\r\n<p>[[/template/crm-portal/list/title]]</p>\n",
	'crm-portal/deep/card.tpl'			=> "card, two levels down\0with a NUL byte\n",
	'crm-portal/'. str_repeat( 'long-', 19 ). '.tpl'	=> 'a name of a hundred characters with its folder, which the tar splits into two fields',
	'crm-portal/page.tpl'						=> str_repeat( "five thousand bytes, so that the entry after this one is found past its blocks\n", 64 ),
	'crm-mail/mail.tpl'							=> "mail body\n",
];

foreach( $folderTemplates as $name => $bytes ) {
	mkdir( dirname( $pagesDir. '/'. $name ), 0755, true );
	file_put_contents( $pagesDir. '/'. $name, $bytes );
}

// What is none of them, in a folder: a link, a directory called x.tpl with a
// template in it, a file with another extension, a hidden one, and a link to
// the folder itself
mkdir( $pagesDir. '/crm-portal/folder.tpl' );
file_put_contents( $pagesDir. '/crm-portal/folder.tpl/inner.tpl', 'below a directory that is called like a template' );
file_put_contents( $pagesDir. '/crm-portal/notes.txt', 'not a template' );
file_put_contents( $pagesDir. '/crm-portal/.hidden.tpl', 'hidden' );
$folderLink = @symlink( '../page-probe.tpl', $pagesDir. '/crm-portal/link.tpl' );
$folderDirLink = @symlink( 'crm-portal', $pagesDir. '/linked-folder' );

$folderId		= \Nino\Modules\Backups::now( $appData );
$entries		= is_string( $folderId ) === true ? $readArchive( $folderId ) : [];
$templateEntries = array_values( array_filter( array_keys( $entries ), static fn( string $name ): bool => str_starts_with( $name, 'templates/' ) ) );
sort( $templateEntries );
$expected		= array_merge( [ 'templates/page-late.tpl', 'templates/page-probe.tpl' ], array_map( static fn( string $name ): string => 'templates/'. $name, array_keys( $folderTemplates ) ) );
sort( $expected );

check( 'an archive carries the templates of a folder of templates/ at their relative path, in one and in two levels of depth', $templateEntries === $expected
	&& ( $entries['templates/crm-portal/list.tpl'] ?? null ) === $folderTemplates['crm-portal/list.tpl'] && ( $entries['templates/crm-portal/deep/card.tpl'] ?? null ) === $folderTemplates['crm-portal/deep/card.tpl'] );
check( '...a path longer than a hundred characters, and a template that fills several blocks of the tar, with the entry after it',
	( $entries['templates/crm-portal/'. str_repeat( 'long-', 19 ). '.tpl'] ?? null ) === $folderTemplates['crm-portal/'. str_repeat( 'long-', 19 ). '.tpl']
	&& ( $entries['templates/crm-portal/page.tpl'] ?? null ) === $folderTemplates['crm-portal/page.tpl'] && ( $entries['templates/crm-mail/mail.tpl'] ?? null ) === $folderTemplates['crm-mail/mail.tpl'] );
check( '...but not, in a folder, a link, a directory that is called x.tpl and what is in it, a file that is no .tpl, a hidden one, or the folder through a link'. ( $folderLink === true && $folderDirLink === true ? '' : ' (no link could be made here)' ),
	count( array_filter( array_keys( $entries ), static fn( string $name ): bool => preg_match( '#(link\.tpl|folder\.tpl|inner\.tpl|notes\.txt|hidden|linked-folder)#', $name ) === 1 ) ) === 0 );
check( 'what is a template is one rule for a collection and a restore: a file called *.tpl, in a folder that is not called like one, and not a hidden one',
	\Nino\Backup::template( 'page-home.tpl' ) === true && \Nino\Backup::template( 'crm-portal/deep/card.tpl' ) === true
	&& \Nino\Backup::template( 'notes.txt' ) === false && \Nino\Backup::template( 'folder.tpl/inner.tpl' ) === false && \Nino\Backup::template( 'crm-portal/.hidden.tpl' ) === false
	&& \Nino\Backup::template( '.tpl' ) === false && \Nino\Backup::template( 'crm-portal/' ) === false && \Nino\Backup::template( '' ) === false );

// The state after the archive: a template changed, a folder gone altogether,
// and one more written into a folder that stays
file_put_contents( $pagesDir. '/crm-portal/list.tpl', 'changed after the archive' );
file_put_contents( $pagesDir. '/crm-portal/deep/card.tpl', 'changed after the archive' );
\Nino\Filesystem::removeDir( $pagesDir. '/crm-mail' );
file_put_contents( $pagesDir. '/crm-portal/late.tpl', 'written after the archive' );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $folderId ] );
$identical = true;
foreach( $folderTemplates as $name => $bytes )
	if( @file_get_contents( $pagesDir. '/'. $name ) !== $bytes )
		$identical = false;
check( 'a restore puts the templates of the folders back byte for byte, in one and in two levels of depth', $status === 200 && ( $body['ok'] ?? false ) === true && $identical === true );
check( '...makes the folder where it is gone', is_dir( $pagesDir. '/crm-mail' ) === true && file_get_contents( $pagesDir. '/crm-mail/mail.tpl' ) === $folderTemplates['crm-mail/mail.tpl'] );
check( '...and leaves a template of a folder that the archive has not, as it leaves one directly in templates/', file_get_contents( $pagesDir. '/crm-portal/late.tpl' ) === 'written after the archive' );

// The recovery page runs the same restore
file_put_contents( $pagesDir. '/crm-portal/list.tpl', 'changed again' );
\Nino\Filesystem::removeDir( $pagesDir. '/crm-portal/deep' );
\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $folderId ], 'recovery/restore' );
check( 'the recovery page restores the templates of the folders the same way, a missing folder made', $status === 200 && ( $body['restoredDate'] ?? null ) === $folderId
	&& file_get_contents( $pagesDir. '/crm-portal/list.tpl' ) === $folderTemplates['crm-portal/list.tpl'] && file_get_contents( $pagesDir. '/crm-portal/deep/card.tpl' ) === $folderTemplates['crm-portal/deep/card.tpl'] );
\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );

// An archive made before there were folders in it: the same format with fewer
// entries
$flatId = date( 'Y-m-d' ). '-100001';
$writeArchive( $flatId, array_filter( $entries, static fn( string $name ): bool => $name === 'nino.php' || preg_match( '#^templates/[^/]+$|^assets/#', $name ) === 1, ARRAY_FILTER_USE_KEY ) );
file_put_contents( $pagesDir. '/page-probe.tpl', 'changed after the flat archive' );
file_put_contents( $pagesDir. '/crm-portal/list.tpl', 'a folder the flat archive knows nothing of' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $flatId ] );
check( 'an archive that has no folder in it restores as it did, and touches no template of a folder', $status === 200 && ( $body['ok'] ?? false ) === true
	&& file_get_contents( $pagesDir. '/page-probe.tpl' ) === 'probe page, as archived' && file_get_contents( $pagesDir. '/crm-portal/list.tpl' ) === 'a folder the flat archive knows nothing of' );

// An archive somebody with the key made by hand. Each one carries a good
// template before the entry that is refused: it is refused as a whole, nothing
// of it is written, and no snapshot is made for it
$good = $tarEntry( 'templates/crm-portal/list.tpl', 'from the archive that is refused' );
$flawed = $tarEntry( 'templates/crm-portal/flawed.tpl', 'flawed' );
$flawed = substr_replace( $flawed, '0000000', 148, 7 );
// A directory carries no data, whatever its size field says: PharData reads the
// next block as the next header. An entry hidden in the "data" of a directory
$dirHiding = static fn( string $hidden ): string => $tarEntry( 'images', '', '5', '', strlen( $hidden ) ). $hidden;
$hostile = [
	'a path with .. in a folder'					=> $good. $tarEntry( 'templates/crm-portal/../../x.tpl', 'x' ),
	'a path with .. at the top'						=> $good. $tarEntry( 'templates/../x.tpl', 'x' ),
	'an absolute path'										=> $good. $tarEntry( $sandbox. '/private/templates/abs-probe.tpl', 'x' ),
	'an absolute path in a long name'			=> $good. $tarLong( $sandbox. '/private/templates/abs-probe.tpl' ). $tarEntry( 'x', 'x' ),
	'a .. in a long name'									=> $good. $tarLong( 'templates/crm-portal/../../x.tpl' ). $tarEntry( 'x', 'x' ),
	'a NUL byte in a name, a template before it'	=> $good. $tarEntry( "templates/crm-portal/ok.tpl\0evil", 'x' ),
	'a NUL byte in a long name'						=> $good. $tarLong( "templates/crm-portal/n\0ul.tpl" ). $tarEntry( 'x', 'x' ),
	'a symbolic link'											=> $good. $tarEntry( 'templates/crm-portal/sym.tpl', '', '2', '/etc/hostname' ),
	'a hard link'													=> $good. $tarEntry( 'templates/crm-portal/hard.tpl', '', '1', 'templates/crm-portal/list.tpl' ),
	'a file that is no template in a folder'	=> $good. $tarEntry( 'templates/crm-portal/evil.php', '<?php' ),
	'a file that is no template at the top'		=> $good. $tarEntry( 'templates/evil.php', '<?php' ),
	'a hidden file'												=> $good. $tarEntry( 'templates/crm-portal/.hidden.tpl', 'x' ),
	'a directory called like a template'	=> $good. $tarEntry( 'templates/crm-portal/dir.tpl/', '', '5' ),
	'a template below such a directory'		=> $good. $tarEntry( 'templates/crm-portal/dir.tpl/inner.tpl', 'x' ),
	'an entry beside the project\'s own'	=> $good. $tarEntry( 'index.php', '<?php' ),
	'a file where a directory belongs'		=> $good. $tarEntry( 'templates', 'x' ),
	'a header that is none'								=> $good. $flawed,
	'a directory whose size hides index.php'			=> $dirHiding( $tarEntry( 'index.php', '<?php' ) ),
	'a directory whose size hides templates/evil.php'	=> $dirHiding( $tarEntry( 'templates/evil.php', '<?php' ) ),
	'a directory whose size hides a link'				=> $dirHiding( $tarEntry( 'templates/crm-portal/hid.tpl', '', '2', '/etc/hostname' ) ),
	'a directory whose size hides an absolute path'		=> $dirHiding( $tarEntry( $sandbox. '/private/templates/abs-probe.tpl', 'x' ) ),
	'a directory whose size hides it behind a template'	=> $good. $dirHiding( $tarEntry( 'index.php', '<?php' ) ),
	'a name prefix in a header with no ustar magic'		=> $good. $tarEntry( 'templates/crm-portal/pre.tpl', 'x', '0', '', null, 'zz', str_repeat( "\0", 8 ) ),
	'a name prefix that leaves the project'				=> $good. $tarEntry( 'x.tpl', 'x', '0', '', null, '../..' ),
	'data after the end of the tar'						=> $good. str_repeat( "\0", 1024 ). $tarEntry( 'index.php', '<?php' ),
];

$hostileN = 0;
foreach( $hostile as $what => $tar ) {

	$id = date( 'Y-m-d' ). '-1001'. sprintf( '%02d', ++$hostileN );
	$writeTar( $id, $tar );

	$filesBefore		= $projectFiles();
	$snapshotsNow		= glob( $nowDir. '/pre-restore-*.php' ) ?: [];
	$listBefore			= file_get_contents( $pagesDir. '/crm-portal/list.tpl' );
	$tempBefore			= count( glob( sys_get_temp_dir(). '/ninorestore*' ) ?: [] );

	\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
	[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $id ] );

	check( 'an archive with '. $what. ' is refused with 422, as a whole: nothing is written, no snapshot is made, no temporary file is left',
		$status === 422 && is_string( $body['error'] ?? null ) === true && $projectFiles() === $filesBefore && file_get_contents( $pagesDir. '/crm-portal/list.tpl' ) === $listBefore
		&& ( glob( $nowDir. '/pre-restore-*.php' ) ?: [] ) === $snapshotsNow && count( glob( sys_get_temp_dir(). '/ninorestore*' ) ?: [] ) === $tempBefore );
	unlink( $nowDir. '/'. $id. '.php' );
}

check( '...and the answer names what is wrong without the bytes of the name', is_string( $body['error'] ?? null ) === true && preg_match( '/^[\x20-\x7e]+$/', (string) $body['error'] ) === 1 );
check( '...nothing a hostile archive named is on disk', is_file( $sandbox. '/x.tpl' ) === false && is_file( $pagesDir. '/abs-probe.tpl' ) === false && is_file( $sandbox. '/private/x.tpl' ) === false
	&& is_file( $sandbox. '/index.php' ) === false && is_file( $pagesDir. '/crm-portal/evil.php' ) === false && is_file( $pagesDir. '/evil.php' ) === false );

// The two readings on their own. The header reader alone: the archives above that
// hide an entry in a directory are refused from the headers, and a link entry is
// only to be seen there, since PharData unpacks it as a plain file. The staged
// copy alone: the unpacking of an archive the header reader would refuse is read
// for what it made, through the step of the restore that does it
$tmpTar = sys_get_temp_dir(). '/ninotest-reader-'. bin2hex( random_bytes( 6 ) ). '.tar.gz';
$readers = [];
foreach( [ 'a directory whose size hides index.php', 'a directory whose size hides a link', 'a directory whose size hides it behind a template', 'data after the end of the tar', 'a name prefix in a header with no ustar magic' ] as $what ) {
	file_put_contents( $tmpTar, gzencode( $hostile[$what]. str_repeat( "\0", 1024 ) ) );
	$readers[$what] = \Nino\Backup::refusal( $tmpTar );
}
file_put_contents( $tmpTar, gzencode( $good. $tarEntry( 'templates/crm-portal/ok.tpl', 'ok' ). str_repeat( "\0", 1024 ) ) );
$readers['good'] = \Nino\Backup::refusal( $tmpTar );
unlink( $tmpTar );
check( 'the header reader alone refuses an entry hidden in the size of a directory, a link hidden there, data after the end of the tar and a name prefix that has no magic to go with it, and passes a good archive',
	count( array_filter( array_diff_key( $readers, [ 'good' => 1 ] ), 'is_string' ) ) === 5 && $readers['good'] === null );

$unpack = new ReflectionMethod( '\Nino\Modules\Backups\Admin', '_unpack' );
$unpackOf = static function( string $tar ) use ( $unpack ): array {
	// A name of its own each time: PharData keeps the tar it makes of a .tar.gz beside it
	$base		= sys_get_temp_dir(). '/ninotest-unpack-'. bin2hex( random_bytes( 6 ) );
	$staging = $base. '-staging';
	file_put_contents( $base. '.tar.gz', gzencode( $tar. str_repeat( "\0", 1024 ) ) );
	$result = $unpack->invoke( null, $base. '.tar.gz', $staging );
	$left = is_dir( $staging );
	\Nino\Filesystem::removeDir( $staging );
	array_map( 'unlink', glob( $base. '.*' ) ?: [] );
	return [ $result, $left ];
};
[ $stagedIndex ] = $unpackOf( $hostile['a directory whose size hides index.php'] );
[ $stagedEvil ] = $unpackOf( $hostile['a directory whose size hides templates/evil.php'] );
[ $stagedOk, $stagedLeft ] = $unpackOf( $good. $tarEntry( 'templates/crm-portal/ok.tpl', 'ok' ) );
check( 'the staged copy alone refuses what was really unpacked - a top-level entry outside the project\'s, a file below templates/ that is no template - with 422, and passes a good one',
	is_array( $stagedIndex ) === true && $stagedIndex[0] === 422 && is_array( $stagedEvil ) === true && $stagedEvil[0] === 422 && $stagedOk === true && $stagedLeft === true );

// A tree of its own for each case: one with a link in it is not removed by removeDir()
$stageRoot = sys_get_temp_dir(). '/ninotest-stage-'. bin2hex( random_bytes( 6 ) );
$stageN = 0;
$stageCase = static function( callable $make ) use ( $stageRoot, &$stageN ): ?string {
	$stage = $stageRoot. '/'. ++$stageN;
	mkdir( $stage. '/templates/crm-portal', 0755, true );
	file_put_contents( $stage. '/templates/crm-portal/ok.tpl', 'ok' );
	$make( $stage );
	return \Nino\Backup::staged( $stage );
};
$fifo = function_exists( 'posix_mkfifo' ) === true;
check( 'the rule of the staged copy: a good tree passes; a link, a fifo, a top-level file or directory outside the project\'s, a file that is no template, a directory called x.tpl and a template in one are refused',
	$stageCase( static function( string $dir ): void {} ) === null
	&& $stageCase( static function( string $dir ): void { file_put_contents( $dir. '/config.php', '<?php return [];' ); mkdir( $dir. '/data/a', 0755, true ); file_put_contents( $dir. '/data/a/b.php', 'x' ); } ) === null
	&& is_string( $stageCase( static function( string $dir ): void { file_put_contents( $dir. '/index.php', 'x' ); } ) )
	&& is_string( $stageCase( static function( string $dir ): void { mkdir( $dir. '/app' ); } ) )
	&& is_string( $stageCase( static function( string $dir ): void { mkdir( $dir. '/templates/crm-portal/d.tpl' ); } ) )
	&& is_string( $stageCase( static function( string $dir ): void { file_put_contents( $dir. '/templates/crm-portal/evil.php', 'x' ); } ) )
	&& is_string( $stageCase( static function( string $dir ): void { @symlink( '/etc/hostname', $dir. '/templates/crm-portal/l.tpl' ); } ) )
	&& is_string( $stageCase( static function( string $dir ): void { @symlink( '/etc', $dir. '/text' ); } ) )
	&& ( $fifo === false || is_string( $stageCase( static function( string $dir ): void { posix_mkfifo( $dir. '/templates/f.tpl', 0600 ); } ) ) ) );
shell_exec( 'rm -rf '. escapeshellarg( $stageRoot ) );

// The recovery page has no way of its own: the same refusal, the line marked as failed
$id = date( 'Y-m-d' ). '-100199';
$writeTar( $id, $good. $tarEntry( 'templates/../x.tpl', 'x' ) );
\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], [ 'date' => $id ], 'recovery/restore' );
check( 'the recovery page refuses it the same way, writes nothing and marks its line as failed', $status === 422 && is_file( $sandbox. '/x.tpl' ) === false
	&& file_get_contents( $pagesDir. '/crm-portal/list.tpl' ) === $listBefore && count( $logLines( 'recovery.php', 'Restore Backup '. $id, 'failed (422)' ) ) === 1 );
unlink( $nowDir. '/'. $id. '.php' );
\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );

// What this block wrote is taken away again
unlink( $nowDir. '/'. $folderId. '.php' );
unlink( $nowDir. '/'. $flatId. '.php' );
foreach( [ 'crm-portal', 'crm-mail' ] as $name )
	\Nino\Filesystem::removeDir( $pagesDir. '/'. $name );
if( $folderDirLink === true )
	unlink( $pagesDir. '/linked-folder' );

// What this block wrote is taken away again: the archives, the snapshots its
// restores made, the files and the directories
foreach( [ $pagesId, $otherId, $sameId, $noNoteId ] as $name )
	unlink( $nowDir. '/'. $name. '.php' );
foreach( array_diff( glob( $nowDir. '/pre-restore-*.php' ) ?: [], $snapshotsBefore ) as $name )
	unlink( $name );
foreach( [ $pagesDir. '/page-probe.tpl', $pagesDir. '/page-late.tpl', $pagesDir. '/notes.txt', $assetsDir. '/probe.css', $assetsDir. '/late.css', $assetsDir. '/nested/deep.css' ] as $name )
	unlink( $name );
if( $linked === true )
	unlink( $pagesDir. '/link.tpl' );
rmdir( $pagesDir. '/folder.tpl' );
rmdir( $assetsDir. '/nested' );
foreach( [ $pagesDir, $assetsDir ] as $name )
	if( ( scandir( $name ) ?: [] ) === [ '.', '..' ] )
		rmdir( $name );

// The restores below count the archives; theirs are the ones to count
foreach( $timedFiles() as $name )
	unlink( $nowDir. '/'. $name );
foreach( [ $keptDaily, $oldSnap ] as $name )
	unlink( $nowDir. '/'. $name. '.php' );

// In again after the last restore - before the corruption below, since the login
// writes the account record and the file the restore is to put back must stay the
// wiped one
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// Simulate config.php DATA corruption (not a syntax error) - eg. a wrecked user record -
// the scenario Restore exists for. A genuine syntax error is out of scope (see Admin.php docblock).
$beforeCorruption = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$corrupted = $beforeCorruption;
$corrupted['/nino/auth/user'] = [];
\Nino\Filesystem::putFileContent( $appData, '/config.php', $corrupted );
check( 'the simulated corruption actually wiped the user record', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user'] === [] );

// What a restore leaves in the system's temp directory: tempnam() creates the
// file it names, and both the restore and its safety snapshot went on to work
// on that name plus a suffix - the file tempnam() made was never removed, two
// per restore, for the life of the server
$tempFiles	= static fn(): int => count( glob( sys_get_temp_dir(). '/ninorestore*' ) ?: [] ) + count( glob( sys_get_temp_dir(). '/ninosnapshot*' ) ?: [] );
$tempBefore	= $tempFiles();

$csrfBefore = \Nino\Csrf::getToken( $appData );
[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $dates[0] ] );
check( 'apiRestore reports success', ( $body['ok'] ?? false ) === true );
check( '...and ends the session of the account that ran it: no account on the request, no session values, a new csrf token, and the next action is 401 until the login', \Nino\Auth::getCurrentUser( $appData ) === false
	&& \Nino\Runtime::getSessionValue( $appData, './nino/auth/current' ) === null && \Nino\Runtime::getSessionValue( $appData, './nino/auth/token' ) === null
	&& \Nino\Csrf::getToken( $appData ) !== $csrfBefore && ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] )[0] === 401 );

$afterRestore = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the wrecked user record is back after restore', isset( $afterRestore['/nino/auth/user']['admin@example.com'] ) === true );

$backupDir = $sandbox. '/private/.backups';
check( 'a pre-restore safety snapshot of the (corrupted) state was made first', count( glob( $backupDir. '/pre-restore-*.php' ) ?: [] ) === 1 );

/*	...and that snapshot is a way back out, which is what the panel's confirm
	text promises. It used to match nothing: not the list,
	not apiRestore(), not recovery.php - so "a wrong pick can itself be
	undone" was true of a file only ssh could reach. It is also the one
	archive nothing ever pruned, so every restore a project did stayed on
	disk as a full encrypted copy, for good	*/
$snapshotId = basename( ( glob( $backupDir. '/pre-restore-*.php' ) ?: [] )[0], '.php' );
check( 'the snapshot is offered, after the dated backups rather than among them', in_array( $snapshotId, \Nino\Modules\Backups\Admin::dates( $appData ), true ) === true
	&& array_key_last( \Nino\Modules\Backups\Admin::dates( $appData ) ) === array_search( $snapshotId, \Nino\Modules\Backups\Admin::dates( $appData ), true ) );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $snapshotId ] );
check( 'restoring it undoes the restore', ( $body['ok'] ?? false ) === true
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/user']['admin@example.com'] ) === false );

// Undoing wrote one of its own, so there are two now - and the bound is what
// keeps that from growing with every restore the project ever does
for( $i = 0; $i < 4; $i++ ) {
	touch( $backupDir. '/pre-restore-2020-01-0'. $i. '-120000.php' );
}
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $snapshotId ] );
check( 'a restore keeps the newest three snapshots and drops the rest', count( glob( $backupDir. '/pre-restore-*.php' ) ?: [] ) === 3 );

check( 'three restores left nothing behind in the temp directory - neither their own file nor the snapshot\'s', $tempFiles() === $tempBefore );

// The restore writes to disk behind the filesystem. What a page of the same request has made of the text files - the fills of a language and the list of
// the languages that have a file, once per request - goes with the cache of the filesystem, so that the next fill is the text the archive put back
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/restore/probe/text]]' => 'in the archive' ] );
[ , $textArchive ] = $nowCall( $appData );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/restore/probe/text]]' => 'written after the archive' ] );
$restoreSeen = \Nino\Html::getFills( $appData )['[[/restore/probe/text]]'] ?? null;
$restoreListed = \Nino\Locales::getTextLocales( $appData );
file_put_contents( \Nino\Filesystem::path( $appData, '/text' ). '/ja_JP.php', '<?php return [];' );
[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $textArchive['id'] ?? '' ] );
check( 'a restore is seen by the fills of its own request: the text the archive put back, not the one a page made of the file before', ( $body['ok'] ?? false ) === true && $restoreSeen === 'written after the archive'
	&& ( \Nino\Html::getFills( $appData )['[[/restore/probe/text]]'] ?? null ) === 'in the archive' );
check( '...and by the list of the languages that have a file, which is as long as the directory is', in_array( 'ja_JP', $restoreListed, true ) === false && in_array( 'ja_JP', \Nino\Locales::getTextLocales( $appData ), true ) === true );
unlink( \Nino\Filesystem::path( $appData, '/text' ). '/ja_JP.php' );
unset( $appData['./nino/locales/text'] );

/*	An archive that decrypts but is not one: a backup file somebody truncated
	or replaced, encrypted with the project's own key so that it passes the
	one check before unpacking. PharData threw out of restore(), which the
	panel answered as a 500 with nothing said - and the staging directory and
	the archive it had written stayed in the temp directory as well. Through
	a try/catch so the old answer is a failed check rather than the end of
	this suite	*/
$backupKey = base64_decode( substr( (string) file_get_contents( $sandbox. '/private/.auth/backup-key.php' ), strlen( "<?php http_response_code(403); exit; return '" ), -strlen( "';\n" ) ), true );
$garbageIv = random_bytes( 12 ); $garbageTag = '';
$garbage	 = openssl_encrypt( 'this is not a tar.gz', 'aes-256-gcm', (string) $backupKey, OPENSSL_RAW_DATA, $garbageIv, $garbageTag );
file_put_contents( $backupDir. '/2020-01-01.php', "<?php http_response_code(403); exit; return '". base64_encode( $garbageIv. $garbageTag. $garbage ). "';\n" );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$garbageRequest = [];
$garbageOutcome = ( static function() use ( &$appData, &$garbageRequest ): string {
	try {
		[ , , $garbageRequest ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2020-01-01' ] );
		return 'answered';
	}
	catch( \Throwable $e ) {
		return $e::class;
	}
} )();

check( 'a backup that is not an archive is answered, not thrown', $garbageOutcome === 'answered' && $garbageRequest['/nino/http/response']['statusCode'] === 500
	&& str_contains( (string) ( $garbageRequest['/nino/http/response']['body']['error'] ?? '' ), 'could not be unpacked' ) === true );
check( '...and leaves nothing behind either - the staging directory included', $tempFiles() === $tempBefore );

unlink( $backupDir. '/2020-01-01.php' );

/*	config.php is the one file every request reads at boot, and a restore
	replaces it. Written in place, a request booting mid-write read a
	half-written file - an include of a truncated var_export either fatals or
	returns something that is not an array, and AppData::init() answers that
	with "config.php exists but did not return an array" for everybody until
	the write finished. Written beside it and renamed over it, under the lock
	every other writer of that file takes, a reader sees one file or the
	other. Three things, each measured on the files rather than read off the
	source: the file that stands there afterwards is another file (a new
	inode, nothing temporary left beside it), the file does not change while
	somebody else holds the lock, and the module callback below runs with
	the extracted backup in hand	*/
$restoreSeen = [];
\Nino\Callbacks::registerCallback( $appData, '/nino/admin/restore', static function( array &$appData, array &$args ) use ( &$restoreSeen ): void {
	// The first restore is the one under test; the lock experiment below
	// runs the write step once more, with an archive of one file
	if( $restoreSeen !== [] )
		return;
	$staging = (string) ( $args['staging'] ?? '' );
	$restoreSeen = [
		'dataDir'		=> $args['dataDir'] ?? null,
		'staged'		=> $staging !== '' && is_dir( $staging ) ? count( glob( $staging. '/*' ) ?: [] ) : 0,
	];
} );

$configFile		= \Nino\Filesystem::getConfigPath( $appData ). '/config.php';
$inodeBefore	= fileinode( $configFile );

// Back to the state the checks below read
[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $dates[0] ] );
clearstatcache();

check( 'a restore replaces config.php with another file rather than writing into it, and leaves nothing temporary beside it',
	( $body['ok'] ?? false ) === true
	&& fileinode( $configFile ) !== $inodeBefore
	&& glob( $configFile. '.*.tmp' ) === [] );

/*	The lock. Held from a child process, because flock() is per handle and a
	lock this process already holds is re-entered rather than waited for (see
	Filesystem::lockFile()); the child watches config.php's inode for the
	second and a half it holds the lock and says afterwards whether the file
	changed under it. Driven at the write step itself - _restoreFrom(), with
	an archive of one file - because the safety snapshot a whole restore
	takes first waits for the same lock on its own account, and would hide a
	write step that does not. The lock file's name is what lockFile()
	derives it from	*/
$lockKey		= (string) ( new ReflectionMethod( '\Nino\Filesystem', '_canonicalPath' ) )->invokeArgs( null, [ &$appData, '/config.php' ] );
$lockPath		= \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( $lockKey ). '.lock';
\Nino\Filesystem::lockFile( $appData, '/config.php' );
\Nino\Filesystem::unlockFile( $appData, '/config.php' );

$configBefore	= \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
$configMarked	= $configBefore + [ '/nino/restore/marker' => 'written after the lock was released' ];
$archiveBase	= sys_get_temp_dir(). '/ninotest-lock-'. bin2hex( random_bytes( 6 ) );
$archive			= new PharData( $archiveBase. '.tar' );
$archive->addFromString( 'config.php', "<?php\nreturn ". var_export( $configMarked, true ). ";\n" );
$archive->compress( Phar::GZ );
unset( $archive );
$gz = (string) file_get_contents( $archiveBase. '.tar.gz' );
@unlink( $archiveBase. '.tar' );
@unlink( $archiveBase. '.tar.gz' );

$watcher = 'set_error_handler( function() { return true; } ); $lock = fopen( $argv[1], "c" ); flock( $lock, LOCK_EX ); $was = fileinode( $argv[2] ); $changed = false; echo "held\n";'
	. ' for( $i = 0; $i < 75; $i++ ) { usleep( 20000 ); clearstatcache( true, $argv[2] ); if( fileinode( $argv[2] ) !== $was ) $changed = true; }'
	. ' flock( $lock, LOCK_UN ); echo $changed === true ? "changed\n" : "unchanged\n";';
$holder = proc_open( [ PHP_BINARY, '-r', $watcher, $lockPath, $configFile ], [ 1 => [ 'pipe', 'w' ] ], $holderPipes );
$held		= is_resource( $holder ) === true && trim( (string) fgets( $holderPipes[1] ) ) === 'held';

$tmpBase	= (string) tempnam( sys_get_temp_dir(), 'ninotest' );
$tmpGz		= $tmpBase. '.tar.gz';
$staging	= sys_get_temp_dir(). '/ninotest-staging-'. bin2hex( random_bytes( 6 ) );
$written	= ( new ReflectionMethod( '\Nino\Modules\Backups\Admin', '_restoreFrom' ) )->invokeArgs( null, [ &$appData, $gz, $tmpGz, $staging ] );
$underLock = is_resource( $holder ) === true ? trim( (string) stream_get_contents( $holderPipes[1] ) ) : '';
if( is_resource( $holder ) === true ) { fclose( $holderPipes[1] ); proc_close( $holder ); }
@unlink( $tmpBase );
@unlink( $tmpGz );
if( is_dir( $staging ) === true )
	\Nino\Filesystem::removeDir( $staging );
clearstatcache();

check( '...and under the lock every other writer of that file takes: while another process holds it the file does not change, and once the lock is gone the restored file is what stands there',
	$held === true && $written === true && $underLock === 'unchanged'
	&& ( ( include $configFile )['/nino/restore/marker'] ?? null ) === 'written after the lock was released' );

// The marker was the experiment's, not the project's
\Nino\Filesystem::putFileContent( $appData, '/config.php', $configBefore );

// And the restored file is the one a reader gets afterwards - the check
// above is about how it is written, this one that it was
check( 'the restored config.php is a readable array', is_array( ( static function() use ( $appData ): mixed {
	return include \Nino\Filesystem::getConfigPath( $appData ). '/config.php';
} )() ) === true );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2020-01-01' ] );
check( 'restoring a date with no matching backup 404s', $status === 404 );

[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => 'not-a-date' ] );
check( 'restoring a malformed date is rejected before touching the filesystem', $status === 400 );

/*	An archive made on request is one to restore like any other, and it holds
	the state of its own moment: the daily one the state before the day's work
	- which is what a click in the afternoon must not have replaced	*/
$stateKey = '/nino/restore/state';
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ $stateKey => 'afternoon' ] + \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) );
$afternoonId = \Nino\Modules\Backups::now( $appData );
\Nino\Filesystem::putFileContent( $appData, '/config.php', [ $stateKey => 'evening' ] + \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) );

[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $afternoonId ] );
check( 'restoring an archive made on request gives the state of that moment', ( $body['ok'] ?? false ) === true
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )[$stateKey] ?? null ) === 'afternoon' );

\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ , $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => date( 'Y-m-d' ) ] );
check( '...and the daily archive of today still gives the state before it', ( $body['ok'] ?? false ) === true
	&& array_key_exists( $stateKey, \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );
unlink( $backupDir. '/'. $afternoonId. '.php' );

// The last restore of this block signed the account out, and what follows acts as it
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Restore - a module merges its own files through the callback ----------

echo "Restore - a module merges its own data files through /nino/admin/restore\n";

// Restore itself carries no knowledge of any module's data: a module that
// keeps files under data/ registers '/nino/admin/restore' in its init() and
// merges its own (the Newsletter feature in dapeio/nino-features is the
// reference, tested in its own suite), so a project without the module has
// nothing to merge and Restore has nothing to know. What it hands every
// callback is the live data directory and the directory the backup was
// extracted to - the one registered before the restore above saw both
check( 'Restore hands the module callback the live data directory and the extracted backup',
	( $restoreSeen['dataDir'] ?? null ) === \Nino\Filesystem::path( $appData, '/data' )
	&& ( $restoreSeen['staged'] ?? 0 ) > 0 );

echo "\n";


// --- Backup/Restore with config.php outside the project root --------------

echo "Backup/Restore - config.php resolves via configPath, not root (NINO_CONFIG_DIR)\n";

$outSandbox 	= sys_get_temp_dir(). '/nino-dev-smoke-outofweb-'. uniqid();
$outConfigDir = $outSandbox. '/secret-config';

mkdir( $outSandbox. '/_admin', 0777, true );
mkdir( $outSandbox. '/_admin', 0777, true );
mkdir( $outConfigDir, 0777, true );

$outAppData = [ './nino/uid' => $outSandbox ];
\Nino\AppData::prepare( $outAppData );
$outAppData['./nino/filesystem/path']			= $outSandbox;
$outAppData['./nino/filesystem/configpath']	= $outConfigDir; // simulates NINO_CONFIG_DIR
$outAppData['./nino/filesystem/contentpath']	= $outSandbox. '/private';
$outAppData['./nino/filesystem/publicpath']	= $outSandbox. '/public';
$outAppData['/nino/dir']										= '';
$outAppData['/nino/locales/native']				= 'de_DE';
$outAppData['/nino/locales/available']			= [ 'de_DE' ];
$outAppData['/nino/auth/maxtries']					= 5;
$outAppData['/nino/auth/cooldown']					= 3600;

\Nino\Filesystem::putFileContent( $outAppData, '/config.php', [
	'/nino/error/log'					=> false,
	'/nino/error/display'			=> true,
	'/nino/locales/native'		=> 'de_DE',
	'/nino/locales/available'	=> [ 'de_DE' ],
	'/nino/html/assets'				=> [],
	'/nino/http/routes'				=> [],
] );

check( 'config.php was written under configPath, not under the project root', is_file( $outConfigDir. '/config.php' ) === true && is_file( $outSandbox. '/config.php' ) === false );

ninoUser( $outAppData, 'admin@example.com' );

$outGuard = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Admin\Admin::guard( $outAppData, $outGuard ); // bootstraps + creates today's backup as a side effect

$outBackupDir = $outSandbox. '/private/.backups';
$outToday 		= $outBackupDir. '/'. date( 'Y-m-d' ). '.php';

check( 'a backup was created for the out-of-webroot setup', is_file( $outToday ) === true );

$outKey 		= base64_decode( $outAppData['/nino/backup/key'] );
$outPrefix 	= "<?php http_response_code(403); exit; return '";
$outSuffix 	= "';\n";
$outRaw 		= file_get_contents( $outToday );
$outPayload = base64_decode( substr( $outRaw, strlen( $outPrefix ), -strlen( $outSuffix ) ) );
$outGz 			= openssl_decrypt( substr( $outPayload, 28 ), 'aes-256-gcm', $outKey, OPENSSL_RAW_DATA, substr( $outPayload, 0, 12 ), substr( $outPayload, 12, 16 ) );

$outTmpGz 		= $outSandbox. '/verify.tar.gz';
$outExtractDir = $outSandbox. '/verify-extracted';
file_put_contents( $outTmpGz, $outGz );
mkdir( $outExtractDir );
( new \PharData( $outTmpGz ) )->extractTo( $outExtractDir );

check( 'the out-of-webroot config.php made it into the backup archive', is_file( $outExtractDir. '/config.php' ) === true );

// Corrupt config.php (still under configPath) and restore it
$outCorrupted = \Nino\Filesystem::getFileContent( $outAppData, '/config.php', [] );
$outCorrupted['/nino/auth/user'] = [];
\Nino\Filesystem::putFileContent( $outAppData, '/config.php', $outCorrupted );

// Dev's own session gate, not Auth's - same shortcut the rest of this file
// uses instead of driving the (placeholder-hashed) real login flow
\Nino\Runtime::setSessionValue( $outAppData, './nino/admin/authed', true );

[ , $body ] = ninoCall( $outAppData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => date( 'Y-m-d' ) ] );
check( 'apiRestore succeeds for the out-of-webroot setup', ( $body['ok'] ?? false ) === true );

$outAfterRestore = \Nino\Filesystem::getFileContent( $outAppData, '/config.php', [] );
check( 'the restored config.php landed back under configPath, with the user record restored', isset( $outAfterRestore['/nino/auth/user']['admin@example.com'] ) === true );
check( 'restore did not leak a stray config.php copy into the webroot root', is_file( $outSandbox. '/config.php' ) === false );

\Nino\Filesystem::removeDir( $outSandbox );

echo "\n";


// --- Dev\Config ---------------------------------------------------------

echo "Config - soft-value json editor\n";

/**
 *	Dispatch one action directly against a Dev\* module class
 *
 *	@param		array 		&$appData
 *	@param		string		$class				eg. "\Nino\Modules\Config\Admin"
 *	@param		string		$method				eg. "apiList"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body, request ]
 */
function callDev( array &$appData, string $class, string $method, array $data = [] ): array {
	return ninoCall( $appData, [ $class, $method ], $data );
}

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'apiList returns the field schema in render order', array_column( $body['fields'], 'key' ) === [
	'/nino/mode',
	'/nino/error/log', '/nino/error/display', '/nino/session/force-secure-cookie', '/nino/http/proxies', '/nino/mail/cap',
	'/nino/admin/backups', '/nino/admin/logs',
	'/nino/admin/greeting/hello', '/nino/admin/greeting/sentence', '/nino/admin/greeting/facts',
	'/nino/cache/status', '/nino/cache/ttl', '/nino/cache/blacklist',
] );
check( 'every field carries the type its editor renders from', array_column( $body['fields'], 'type' ) === [
	'select', 'bool', 'bool', 'bool', 'lines', 'int', 'bool', 'bool', 'bool', 'bool', 'bool', 'bool', 'int', 'lines',
] );
check( 'apiList returns the group headings, the mode before the rest', array_keys( $body['groups'] ) === [ 'mode', 'diagnostics', 'editor', 'dashboard', 'cache' ] );
check( 'every field belongs to a declared group', array_diff( array_unique( array_column( $body['fields'], 'group' ) ), array_keys( $body['groups'] ) ) === [] );

$byKey = array_column( $body['fields'], null, 'key' );
check( 'a stored value comes back typed, not as json text', $byKey['/nino/error/display']['value'] === true );
check( 'an int comes back as an int', $byKey['/nino/cache/ttl']['value'] === 3600 );
check( 'an int field carries the bounds its editor and apiSave share', $byKey['/nino/cache/ttl']['min'] === 10 && $byKey['/nino/cache/ttl']['max'] === 2592000 );
check( 'the mail cap is an int field of the diagnostics group, bounded 0 to 1000, in calls per hour', $byKey['/nino/mail/cap']['group'] === 'diagnostics'
	&& $byKey['/nino/mail/cap']['min'] === 0 && $byKey['/nino/mail/cap']['max'] === 1000 && $byKey['/nino/mail/cap']['unit'] === 'per-hour' );
check( '...and a key absent from config.php reports its default, the 5 the mail cap runs with', $byKey['/nino/mail/cap']['value'] === 5 && $byKey['/nino/mail/cap']['value'] === \Nino\AppData::DEFAULTS['/nino/mail/cap'] );

// A missing key must report the same default the runtime itself applies, or the
// form shows a value the site is not actually running with
check( 'a key absent from config.php reports the runtime default', $byKey['/nino/admin/backups']['value'] === true );
check( 'the mode of a config.php that does not name one is production, and the field offers both modes, each with the fill key of its word', $byKey['/nino/mode']['value'] === 'production'
	&& array_column( $byKey['/nino/mode']['options'], 'value' ) === [ 'production', 'development' ]
	&& array_filter( array_column( $byKey['/nino/mode']['options'], 'label' ), static fn( string $label ): bool => str_starts_with( $label, '/_admin/config/option/' ) === false ) === [] );

echo "\n";
echo "Language - the site's languages, and the Translations tab beside them\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiList' );
check( 'apiList returns the two locale settings and the stored native language', $status === 200 && array_column( $body['fields'], 'key' ) === [ '/nino/locales/available', '/nino/locales/native' ] && $body['fields'][1]['value'] === 'de_DE' && $body['intro'] !== '' );

// The locale inventory is the union of what config.php lists and what has a
// text file on disk - a translated language that is merely switched off should
// be one checkbox away from being back on, and a configured language without a
// text file has none of its own: its pages show the native language's texts and
// it is in neither the Text panel nor the Translations tab
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/a]]' => 'a', '[[/b]]' => 'b' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/fr_FR.php', [ '[[/a]]' => 'a' ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiList' );
$inventory = array_column( $body['locales'], null, 'code' );
check( 'the inventory lists a configured locale', isset( $inventory['de_DE'] ) === true && $inventory['de_DE']['active'] === true );
check( 'the inventory also lists a translated but unconfigured locale', isset( $inventory['fr_FR'] ) === true && $inventory['fr_FR']['active'] === false );
check( 'a locale with a text file reports its key count', $inventory['de_DE']['hasText'] === true && $inventory['de_DE']['keys'] === 2 );
check( 'a configured locale without a text file is flagged as such', $inventory['en_US']['hasText'] === false && $inventory['en_US']['active'] === true );
check( 'a known locale gets a readable name', $inventory['de_DE']['name'] === 'German (Germany)' );

// The name the site's language switch shows is another one, the language's own: the kernel's table, for the codes this panel names
$endonyms 		= \Nino\Locales::ENDONYMS;
$panelCodes 	= array_keys( ( new ReflectionClassConstant( \Nino\Modules\Language\Admin::class, 'LOCALE_NAMES' ) )->getValue() );
$endonymCodes = array_keys( $endonyms );
sort( $panelCodes ); sort( $endonymCodes );
check( 'the table of the languages\' own names has a language id for each name, a name that is a text of its own and no two languages with the same one', $endonyms !== []
	&& array_filter( array_keys( $endonyms ), static fn( string $code ): bool => preg_match( \Nino\Locales::PATTERN, $code ) !== 1 ) === []
	&& array_filter( $endonyms, static fn( string $name, string $code ): bool => $name === '' || $name !== trim( $name ) || $name === $code, ARRAY_FILTER_USE_BOTH ) === []
	&& count( array_unique( $endonyms ) ) === count( $endonyms ) );
check( '...and it names the languages this panel names, no more and no fewer', $endonymCodes === $panelCodes );
check( '...and the languages added below are two it knows and one it does not', isset( $endonyms['it_IT'], $endonyms['fr_FR'] ) === true && isset( $endonyms['pt_BR'] ) === false );

// --- apiAddLocale -------------------------------------------------------
//
// Adding a language used to leave a configured locale with no text file of its
// own, one that is in neither the Text panel nor the Translations tab and whose
// pages show the native language's texts. Text::saveBatch() cannot bootstrap
// one either - it validates against keys that already exist.

[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'it_IT' ] );
check( 'apiAddLocale creates a text file for a new language', $status === 200 && ( $body['created'] ?? null ) === true );
check( '...copying the key count of the native language', ( $body['keys'] ?? 0 ) === 2 && ( $body['from'] ?? '' ) === 'de_DE' );
check( '...and names the language, in its own tongue, in global.php: /_nino/locale/<code>/name is the system\'s to write, no editor creates it', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/it_IT/name]]'] ?? null ) === $endonyms['it_IT'] );

$skeleton = \Nino\Filesystem::getFileContent( $appData, '/text/it_IT.php', false );
check( '...the file really is on disk', is_array( $skeleton ) === true );
check( '...with exactly the native language\'s keys, in its order', array_keys( $skeleton ) === array_keys( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ) ) );
check( '...and every value empty, so an untranslated key reads as untranslated', array_values( array_unique( $skeleton ) ) === [ '' ] );

// The new language is a translation skeleton, not yet a language of the site -
// activating it is the form's Save, together with the native language it has
// to agree with
$afterAdd = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'apiAddLocale does not activate the language on its own', in_array( 'it_IT', $afterAdd['/nino/locales/available'] ?? [], true ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiList' );
$inventoryAfterAdd = array_column( $body['locales'], null, 'code' );
check( '...but the inventory now offers it as a translated, inactive language', ( $inventoryAfterAdd['it_IT']['hasText'] ?? null ) === true && ( $inventoryAfterAdd['it_IT']['active'] ?? null ) === false );

// Reachable from a button, so it must never be one click away from emptying a
// finished translation
\Nino\Filesystem::putFileContent( $appData, '/text/it_IT.php', [ '[[/a]]' => 'tradotto', '[[/b]]' => 'anche' ] );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/locale/it_IT/name]]'] = 'Lingua italiana';
	return $global;
} );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'it_IT' ] );
check( 'apiAddLocale refuses to overwrite an existing translation', $status === 200 && ( $body['created'] ?? null ) === false );
check( '...and keeps the name an editor gave the language since', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/it_IT/name]]'] ?? null ) === 'Lingua italiana' );
// A file made by hand or imported has no name: the existing file's answer adds it, and nothing else
\Nino\Filesystem::putFileContent( $appData, '/text/fr_FR.php', [ '[[/a]]' => 'traduit' ] );
check( 'a hand-made language file has no name to begin with', isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/fr_FR/name]]'] ) === false );
[ $handStatus, $handBody ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'fr_FR' ] );
check( '...so apiAddLocale names its language when it answers "exists", the one thing it adds - and in its own tongue', $handStatus === 200 && ( $handBody['created'] ?? null ) === false
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/fr_FR/name]]'] ?? null ) === $endonyms['fr_FR'] && \Nino\Filesystem::getFileContent( $appData, '/text/fr_FR.php', [] ) === [ '[[/a]]' => 'traduit' ] );
unlink( \Nino\Filesystem::path( $appData, '/text/fr_FR.php' ) );
// A name somebody already wrote - the installer's, or an editor's - is left alone
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	$global['[[/_nino/locale/es_ES/name]]'] = 'Español';
	return $global;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'es_ES' ] );
check( 'a language that has a name already keeps it when its file is created', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/es_ES/name]]'] ?? null ) === 'Español' );
unlink( \Nino\Filesystem::path( $appData, '/text/es_ES.php' ) );
check( '...reporting what that file actually holds', ( $body['keys'] ?? 0 ) === 2 );
check( '...and leaving its values untouched', \Nino\Filesystem::getFileContent( $appData, '/text/it_IT.php', [] )['[[/a]]'] === 'tradotto' );

// The tag of the pages of a language: the one text of the skeleton that is not empty - the language subtag of the new language - where the native
// language has the key; no key is made up where it has not
$tagKey = '[[/project/website/html/lang]]';
check( 'a skeleton made where the native language has no key for the tag of its pages has none', isset( \Nino\Filesystem::getFileContent( $appData, '/text/it_IT.php', [] )[$tagKey] ) === false );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', fn( array $texts ): array => $texts + [ $tagKey => 'de' ] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'pt_BR' ] );
$tagSkeleton = \Nino\Filesystem::getFileContent( $appData, '/text/pt_BR.php', [] );
check( 'the skeleton of a language says the language subtag in the key of the tag of its pages, not the native one\'s and not a tag of both parts, and every other text of it is empty', $status === 200 && ( $tagSkeleton[$tagKey] ?? null ) === 'pt'
	&& array_values( array_unique( array_diff_key( $tagSkeleton, [ $tagKey => 1 ] ) ) ) === [ '' ] );
check( 'a language the table of own names does not know is named by its code', ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/locale/pt_BR/name]]'] ?? null ) === 'pt_BR' );
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', function( array $texts ) use ( $tagKey ): array {
	unset( $texts[$tagKey] );
	return $texts;
} );
unlink( \Nino\Filesystem::path( $appData, '/text/pt_BR.php' ) );
\Nino\Filesystem::mutate( $appData, '/text/global.php', function( array $global ): array {
	unset( $global['[[/_nino/locale/pt_BR/name]]'] );
	return $global;
} );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'notalocale' ] );
check( 'apiAddLocale rejects a malformed language id', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'de_DE' ] );
check( 'apiAddLocale answers the native language from its own existing file', $status === 200 );

// A locale whose native has nothing to copy from cannot produce a skeleton -
// better a clear refusal than an empty file that looks like one
$noNative = $appData;
$noNativeConfig = \Nino\Filesystem::getFileContent( $noNative, '/config.php', [] );
$noNativeConfig['/nino/locales/native'] = 'pt_PT';
\Nino\Filesystem::putFileContent( $noNative, '/config.php', $noNativeConfig );
[ $status, $body ] = callDev( $noNative, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'sv_SE' ] );
check( 'apiAddLocale refuses when the native language has no text file', $status === 400 && str_contains( (string) ( $body['error'] ?? '' ), 'pt_PT' ) === true );
check( '...and writes nothing', \Nino\Filesystem::fileExists( $noNative, '/text/sv_SE.php' ) === false );
$noNativeConfig['/nino/locales/native'] = 'de_DE';
\Nino\Filesystem::putFileContent( $appData, '/config.php', $noNativeConfig );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiAddLocale', [ 'locale' => 'nl_NL' ] );
check( 'apiAddLocale requires an authed _admin session', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// --- Config::apiSave ------------------------------------------------------

echo "\n";
echo "Config::apiSave and the Lockout tab's - typed writes into config.php\n";

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/display' => false ] ] );
check( 'apiSave accepts a bool', $status === 200 );
check( 'the saved bool lands in appData', $appData['/nino/error/display'] === false );

// A form posts strings; config.php has to receive the real types
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => '600', '/nino/admin/logs' => 'true' ] ] );
check( 'apiSave coerces a posted numeric string', $status === 200 && $appData['/nino/cache/ttl'] === 600 );
check( 'apiSave coerces a posted "true"', $appData['/nino/admin/logs'] === true );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => '5.5' ] ] );
check( 'apiSave rejects a non-integer rather than casting it', $status === 400 && $appData['/nino/cache/ttl'] === 600 );

pinGreetingSwitchesInConfig( $appData );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 1 ] ] );
check( 'apiSave rejects an int below its minimum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 999999999 ] ] );
check( 'apiSave rejects an int above its maximum', $status === 400 );

// The refusal says what it is in a way a client can word: the code of the type,
// the limits that belong in the sentence, and the field it was posted for
[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 1 ] ] );
check( 'a refused int carries the code "int_range", its bounds as params and the field',
	$status === 400 && ( $body['code'] ?? '' ) === 'int_range' && is_int( $body['params'][0] ?? null ) === true && is_int( $body['params'][1] ?? null ) === true
	&& $body['params'][0] < $body['params'][1] && ( $body['field'] ?? '' ) === '/nino/cache/ttl'
	&& str_starts_with( (string) ( $body['error'] ?? '' ), '/nino/cache/ttl: expected a whole number between ' ) );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/log' => 'yes please' ] ] );
check( 'apiSave rejects a bool that is neither', $status === 400 );
check( '...with the code "bool" and the field', ( $body['code'] ?? '' ) === 'bool' && ( $body['field'] ?? '' ) === '/nino/error/log' && array_key_exists( 'params', $body ) === false );

/*	The proxy list decides which address every per-ip rule in the site counts
	a visitor as, so a line that is not an address at all is refused rather
	than stored: it would match nothing while the form reads as configured */
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => "198.51.100.7\n10.0.0.0/8\n2001:db8::/32" ] ] );
check( 'apiSave takes addresses and cidr ranges as the proxy list', $status === 200 && $appData['/nino/http/proxies'] === [ '198.51.100.7', '10.0.0.0/8', '2001:db8::/32' ] );
check( '...and writes them to config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/proxies'] === [ '198.51.100.7', '10.0.0.0/8', '2001:db8::/32' ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => 'cloudflare' ] ] );
check( 'apiSave refuses a proxy entry that is no address', $status === 400 && $appData['/nino/http/proxies'] === [ '198.51.100.7', '10.0.0.0/8', '2001:db8::/32' ] );
check( '...with the code "lines_ip" and the field', ( $body['code'] ?? '' ) === 'lines_ip' && ( $body['field'] ?? '' ) === '/nino/http/proxies' );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => '10.0.0.0/64' ] ] );
check( '...and a cidr prefix the address it belongs to cannot have', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/http/proxies' => '' ] ] );
check( 'an emptied proxy list saves as the empty list that trusts nothing', $status === 200 && $appData['/nino/http/proxies'] === [] );

// The mail cap: a count of calls an hour, 0 for none
foreach( [ 0, 50 ] as $capValue ) {
	[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mail/cap' => $capValue ] ] );
	check( "apiSave writes the mail cap $capValue as an int", $status === 200 && $appData['/nino/mail/cap'] === $capValue
		&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/mail/cap'] === $capValue );
}
foreach( [ -1, 1001, '5.5', 'many' ] as $capValue ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mail/cap' => $capValue ] ] );
	check( 'apiSave refuses the mail cap '. var_export( $capValue, true ). ' and leaves the 50', $status === 400 && ( $body['field'] ?? '' ) === '/nino/mail/cap' && $appData['/nino/mail/cap'] === 50 );
}
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mail/cap' => '1000' ] ] );
check( 'apiSave takes the upper bound 1000 from a posted string', $status === 200 && $appData['/nino/mail/cap'] === 1000 );

// Label and hint in both interface languages, and the unit the field names
foreach( [ 'en_US', 'de_DE' ] as $locale ) {
	$configText = require dirname( __DIR__ ). '/_admin/Nino/Modules/Config/text/'. $locale. '.php';
	$commonText = require dirname( __DIR__ ). '/_admin/text/'. $locale. '.php';
	check( "the mail cap has its label, its hint and its unit in $locale", trim( (string) ( $configText['[[/_admin/config/label/mailcap]]'] ?? '' ) ) !== ''
		&& trim( (string) ( $configText['[[/_admin/config/hint/mailcap]]'] ?? '' ) ) !== '' && trim( (string) ( $commonText['[[/_admin/common/unit/per-hour]]'] ?? '' ) ) !== '' );
}

// Nothing is written until every field validates, so one bad value cannot leave
// half a form saved
$beforePartial = $appData['/nino/error/display'];
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/display' => true, '/nino/cache/ttl' => 'nope' ] ] );
check( 'a rejected field leaves the valid ones in the same request unwritten', $status === 400 && $appData['/nino/error/display'] === $beforePartial );

/*	The mode. Switching it writes the four keys a site in that mode wants,
	over what the form sent for them; saving the mode as it is writes what
	the form sent and nothing else */
$modeKeys = [ '/nino/error/display', '/nino/error/log', '/nino/cache/status', '/nino/cache/ttl' ];
$modeStored = static fn(): array => \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );

callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/admin/logs' => false ] ] );
$formSent = [ '/nino/error/display' => false, '/nino/error/log' => false, '/nino/cache/status' => true, '/nino/cache/ttl' => '7200' ];

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mode' => 'development' ] + $formSent ] );
$written = $modeStored();
check( 'saving the mode development answers with the mode held now', $status === 200 && ( $body['mode'] ?? '' ) === 'development' && $written['/nino/mode'] === 'development' );
check( '...and writes the four keys of that mode into config.php, with the types it needs, though the form sent others for them',
	$written['/nino/error/display'] === true && $written['/nino/error/log'] === true && $written['/nino/cache/status'] === false && $written['/nino/cache/ttl'] === 60
	&& $appData['/nino/error/display'] === true && $appData['/nino/cache/ttl'] === 60 );
check( '...and names all five as saved', $status === 200 && array_diff( array_merge( $modeKeys, [ '/nino/mode' ] ), $body['saved'] ?? [] ) === [] );
check( '...and leaves what a switch has no business with: the audit trail stays as it was', $written['/nino/admin/logs'] === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiList' );
$byKey = array_column( $body['fields'], null, 'key' );
check( 'the form read again shows the mode and the four values the switch wrote', $byKey['/nino/mode']['value'] === 'development' && $byKey['/nino/error/display']['value'] === true && $byKey['/nino/cache/status']['value'] === false && $byKey['/nino/cache/ttl']['value'] === 60 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mode' => 'development', '/nino/error/display' => false, '/nino/cache/ttl' => '900' ] ] );
$written = $modeStored();
check( 'saving the mode as it is writes what the form sent and does not write the mode\'s four keys again', $status === 200 && ( $body['mode'] ?? '' ) === 'development'
	&& $written['/nino/error/display'] === false && $written['/nino/cache/ttl'] === 900 && $written['/nino/cache/status'] === false && $written['/nino/mode'] === 'development' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => '1200' ] ] );
check( 'a form that does not send the mode leaves it alone, and the answer still names it', $status === 200 && ( $body['mode'] ?? '' ) === 'development' && $modeStored()['/nino/mode'] === 'development' && $modeStored()['/nino/cache/ttl'] === 1200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mode' => 'production', '/nino/error/display' => true, '/nino/cache/status' => false, '/nino/cache/ttl' => '120' ] ] );
$written = $modeStored();
check( 'switching back to production writes its four keys again, over the form\'s', $status === 200 && ( $body['mode'] ?? '' ) === 'production' && $written['/nino/mode'] === 'production'
	&& $written['/nino/error/display'] === false && $written['/nino/error/log'] === true && $written['/nino/cache/status'] === true && $written['/nino/cache/ttl'] === 3600 );

// Refused whole: config.php is as it was, the valid fields of the same request included
$beforeRefusal = $modeStored();
foreach( [ 'staging', '', 'Development', [ 'development' ], true, null ] as $refused ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mode' => $refused, '/nino/cache/ttl' => '777' ] ] );
	check( 'a mode that is none of the two ('. json_encode( $refused ). ') is a 400 with the code and the field, and writes nothing', $status === 400 && ( $body['code'] ?? '' ) === 'invalid_value' && ( $body['field'] ?? '' ) === '/nino/mode'
		&& $modeStored() === $beforeRefusal );
}

// A mode nobody can have chosen in the form is a production site: saving production is no switch
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content ): array { $content['/nino/mode'] = 'staging'; $content['/nino/error/display'] = true; return $content; } );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/mode' => 'production' ] ] );
check( 'a hand-edited mode reads as production: saving production over it is not a switch, and the answer says production', $status === 200 && ( $body['mode'] ?? '' ) === 'production' && $modeStored()['/nino/mode'] === 'production' && $modeStored()['/nino/error/display'] === true );

// The login throttle: two ints on a tab of the Users panel, validated the
// way Config validates - a maxtries of 0 would lock every account out, a
// cooldown of 0 removes the throttle
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiList' );
check( 'Lockout::apiList returns both numbers as config.php holds them, with their bounds', $status === 200 && array_column( $body['fields'], 'key' ) === [ '/nino/auth/maxtries', '/nino/auth/cooldown' ] && $body['fields'][0]['value'] === 5 && $body['fields'][1]['value'] === 3600 && $body['fields'][0]['min'] === 1 && $body['fields'][1]['max'] === 604800 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => '8' ] ] );
check( 'Lockout::apiSave coerces a posted numeric string', $status === 200 && $appData['/nino/auth/maxtries'] === 8 );
check( '...and writes it to config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/maxtries'] === 8 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => '5.5' ] ] );
check( 'Lockout::apiSave rejects a non-integer rather than casting it', $status === 400 && $appData['/nino/auth/maxtries'] === 8 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 0 ] ] );
check( 'Lockout::apiSave rejects an int below its minimum', $status === 400 );
check( '...with the code "int_range", the bounds 1 and 100 and the field', ( $body['code'] ?? '' ) === 'int_range' && ( $body['params'] ?? [] ) === [ 1, 100 ] && ( $body['field'] ?? '' ) === '/nino/auth/maxtries' );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/cooldown' => 999999999 ] ] );
check( 'Lockout::apiSave rejects an int above its maximum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/cache/ttl' => 60 ] ] );
check( 'Lockout::apiSave knows its two keys and nothing else', $status === 400 );

// The accounts locked out right now, and lifting one lock. The ip buckets are
// not accounts: they stay out of the list and untouched by the lift
$lockTries = static function( array $seed ) use ( &$appData ): void {
	\Nino\Filesystem::mutate( $appData, '/data/auth-tries.php', static function( mixed $state ) use ( $seed ): array {
		$state = is_array( $state ) ? $state : [];
		foreach( $seed as $key => $value )
			if( $value === null )
				unset( $state[$key] );
			else
				$state[$key] = $value;
		return $state;
	} );
};
\Nino\Auth::insertUser( $appData, 'lockedone@example.com', 'correct horse battery staple' );
\Nino\Auth::insertUser( $appData, 'lockedtwo@example.com', 'correct horse battery staple' );
$lockTries( [ 'lockedtwo@example.com' => 0 - time() - 3600, 'lockedone@example.com' => 0 - time() - 7200, 'ip:203.0.113.9' => 0 - time() - 3600 ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiList' );
check( 'Lockout::apiList carries the locked accounts, by mail, with the time the lock ends', $status === 200 && array_column( $body['locked'], 'mail' ) === [ 'lockedone@example.com', 'lockedtwo@example.com' ]
	&& preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $body['locked'][0]['until'] ) === 1 && $body['locked'][0]['until'] > $body['locked'][1]['until'] );
check( '...and no ip', str_contains( json_encode( $body['locked'] ), '203.0.113.9' ) === false );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'lockedone@example.com' ] );
check( 'apiUnlock lifts the lock and answers with the accounts still locked', $status === 200 && array_column( $body['locked'], 'mail' ) === [ 'lockedtwo@example.com' ] );
check( '...the right password logs the account in again, and the locked ip stays locked', ( \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] )['ip:203.0.113.9'] ?? 0 ) < 0
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/data/auth-tries.php', [] )['lockedone@example.com'] ) === false );
check( '...and the activity log says so', \Nino\Modules\Users\Lockout::log( 'lockout/unlock', [ 'username' => 'lockedone@example.com' ] ) === 'Lift Lock lockedone@example.com'
	&& \Nino\Modules\Users\Lockout::log( 'lockout/save', [] ) === 'Edit Login Protection' && \Nino\Modules\Users\Lockout::log( 'lockout/list', [] ) === '' );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'nobody-at-all@example.com' ] );
check( 'apiUnlock 404s for an unknown mail', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => [ 'lockedtwo@example.com' ] ] );
check( '...and for a name that is not a string', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [] );
check( '...and for none', $status === 404 );

// A tries file that cannot be written: the lock is still there and the answer says so
$lockKey	= (string) ( new ReflectionMethod( '\Nino\Filesystem', '_canonicalPath' ) )->invokeArgs( null, [ &$appData, '/data/auth-tries.php' ] );
$lockFile	= \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( $lockKey ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $lockFile );
@mkdir( $lockFile );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'lockedtwo@example.com' ] );
@rmdir( $lockFile );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( 'apiUnlock answers 500 when the lock could not be lifted', $status === 500 && isset( \Nino\Auth::lockedAccounts( $appData )['lockedtwo@example.com'] ) === true );

ninoUser( $appData, 'usersmanageonly@example.com', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'lockedtwo@example.com' ] );
check( 'apiUnlock is 403 for an account that holds users/manage but not the login protection permission', $status === 403 && isset( \Nino\Auth::lockedAccounts( $appData )['lockedtwo@example.com'] ) === true );
\Nino\Auth::deleteUser( $appData, 'usersmanageonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

$lockTries( [ 'ip:203.0.113.9' => null ] );
\Nino\Auth::deleteUser( $appData, 'lockedone@example.com' );
\Nino\Auth::deleteUser( $appData, 'lockedtwo@example.com' );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiSave', [ 'fields' => [ '/nino/auth/maxtries' => 3 ] ] );
check( 'Lockout actions require an authed _admin session too', $status === 401 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Lockout::class, 'apiUnlock', [ 'username' => 'dev@example.com' ] );
check( '...apiUnlock too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/modules' => [] ] ] );
check( 'apiSave ignores a key outside the schema', $status === 400 );

// --- the two locale keys constrain each other ---------------------------

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'en_US',
] ] );
check( 'apiSave accepts a language list with a native language inside it', $status === 200 );
check( 'the language list round-trips', $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

[ $status, $errorBody ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE' ],
	'/nino/locales/native' 		=> 'en_US',
] ] );
check( 'apiSave refuses a native language outside the list being saved', $status === 400 );
check( '...and says which rule was broken', str_contains( (string) ( $errorBody['error'] ?? '' ), 'native' ) === true );
check( '...leaving the previous list untouched', $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

// Dropping the currently native language without naming a new one is the same
// contradiction, just arrived at from the other side
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [ 'de_DE', 'fr_FR' ] ] ] );
check( 'apiSave refuses a list that drops the current native language', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [] ] ] );
check( 'apiSave refuses an empty language list', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [ '/nino/locales/available' => [ 'de_DE', 'notalocale' ] ] ] );
check( 'apiSave refuses a malformed locale id', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'de_DE', 'en_US' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );
check( 'apiSave deduplicates a repeated locale', $status === 200 && $appData['/nino/locales/available'] === [ 'de_DE', 'en_US' ] );

// A 'lines' field is typed as a textarea and stored as a list - the split and
// the trim live in the backend, so the two cannot disagree about what a blank
// line means
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => "/contact\n\n  /blog/*  \n/contact\n" ] ] );
check( 'apiSave splits a posted textarea into a list', $status === 200 && $appData['/nino/cache/blacklist'] === [ '/contact', '/blog/*' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => [ '/a', '/b' ] ] ] );
check( '...and accepts an already-split list too', $status === 200 && $appData['/nino/cache/blacklist'] === [ '/a', '/b' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => [ [ 'not', 'a', 'string' ] ] ] ] );
check( '...but refuses a list that is not of strings', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/cache/blacklist' => '' ] ] );
check( '...and an emptied textarea clears the list', $status === 200 && $appData['/nino/cache/blacklist'] === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [] ] );
check( 'apiSave rejects an empty form rather than writing nothing quietly', $status === 400 );

// Put the language list back the way the blocks below expect to find it -
// Translations asserts against all three. Saved through apiSave rather than
// assigned, so this also lands in config.php, which is where its own apiInfo
// reads the list from
[ $status ] = callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );
check( 'the language list is restored for the blocks below', $status === 200 && $appData['/nino/locales/available'] === [ 'de_DE', 'en_US', 'fr_FR' ] );

// Regression: init() used to merge its own routes with '+=', which does NOT
// overwrite a key that already exists - so a persisted 'GET://_admin' (from a
// hand-written config.php, or from Pages) shadowed the dashboard and left no ui
// path back to the route that did it. Same fix Install::init() already carries.
$shadowed = $appData;
$shadowed['/nino/http/routes']['GET://_admin'] 	= [ 'uri' => '/_admin', 'body' => 'hijacked' ];
$shadowed['/nino/http/routes']['POST://_admin'] = [ 'uri' => '/_admin', 'body' => 'hijacked' ];
$shadowed['/nino/http/routes']['GET://custom'] 	= [ 'uri' => '/custom', 'body' => 'hand-written route' ];
\Nino\Admin\Admin::init( $shadowed );
check( 'init always restores the tool-owned GET route over a stale/hand-written collision', $shadowed['/nino/http/routes']['GET://_admin']['body'] === '[template /_admin/templates/page-index]' );
check( 'init always restores the tool-owned POST route too', ( $shadowed['/nino/http/routes']['POST://_admin']['body'] ?? null ) === null );
check( 'a route the tool does not own is left untouched', $shadowed['/nino/http/routes']['GET://custom']['body'] === 'hand-written route' );

// Admin::init() may be called more than once in a composed request - the
// bundles and fills it registers have to come out the same, not doubled
\Nino\Admin\Admin::init( $shadowed );
check( 'repeated Admin init keeps the bundles free of duplicates', count( $shadowed['/nino/html/assets']['/_admin/.cache/script.js'] ) === count( array_unique( $shadowed['/nino/html/assets']['/_admin/.cache/script.js'] ) ) );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiList' );
check( 'Config actions require an authed _admin session too', $status === 401 );
[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/error/log' => true ] ] );
check( '...apiSave too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// --- The Recovery password tab --------------------------------------------
//
// The secret recovery.php asks for, changed from the workbench with the old
// one. The checks that matter: a malformed request costs no attempt, a wrong
// old password counts on recovery.php's own five, and what is stored stays
// behind its stub

echo "Users\\RecoveryPassword - changing the recovery password\n";

$lockoutPath	= \Nino\Filesystem::CONTENT_DIR. '/.auth/lockout.json';
$resetLockout	= static function() use ( &$appData, $lockoutPath ): void {
	\Nino\Filesystem::putFileContent( $appData, $lockoutPath, [ 'tries' => 0, 'until' => 0 ] );
	unset( $appData['./nino/filesystem/cache'][$lockoutPath] );
};
$tries 				= static function() use ( &$appData, $lockoutPath ): int {
	unset( $appData['./nino/filesystem/cache'][$lockoutPath] );
	return (int) ( \Nino\Filesystem::getFileContent( $appData, $lockoutPath, [ 'tries' => 0 ] )['tries'] ?? 0 );
};
$resetLockout();
$secretBefore	= \Nino\Admin\Recovery::hash( $appData );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'RecoveryPassword::apiSave is 401 logged out', $status === 401 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );

ninoUser( $appData, 'usersmanageonly@example.com', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( '...403 for an account that manages users but does not hold the recovery password permission', $status === 403 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );
\Nino\Auth::deleteUser( $appData, 'usersmanageonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

check( 'the tab\'s permission is its own, under /_admin/<uri>/manage', \Nino\Modules\Users\RecoveryPassword::MANAGE_PERM === '/_admin/recoverypw/manage' && \Nino\Modules\Users\RecoveryPassword::perm() === '/_admin/recoverypw/manage' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'not the password', 'pw' => 'short' ] );
check( 'a new password under the minimum is 400, before the old one is looked at - no attempt is used up', $status === 400 && $tries() === 0 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );
check( '...named "recoverypw_password_short" with the minimum, for the field "pw"', ( $body['code'] ?? '' ) === 'recoverypw_password_short' && ( $body['params'] ?? [] ) === [ \Nino\Admin\Recovery::MIN_PW_LENGTH ] && ( $body['field'] ?? '' ) === 'pw' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => [ 'a brand new secret' ] ] );
check( '...and so is one that is no string', $status === 400 && $tries() === 0 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'not the password', 'pw' => 'a brand new secret' ] );
check( 'a wrong current password is 401, the secret is unchanged and the attempt counts', $status === 401 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore && $tries() === 1
	&& str_contains( json_encode( $body ), 'a brand new secret' ) === false && str_contains( json_encode( $body ), 'not the password' ) === false );
check( '...named "recoverypw_wrong" for the field "current"', ( $body['code'] ?? '' ) === 'recoverypw_wrong' && ( $body['field'] ?? '' ) === 'current' );

$resetLockout();
for( $i = 0; $i < 5; $i++ )
	callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'not the password', 'pw' => 'a brand new secret' ] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'five wrong attempts lock it - the right password is then 429 too, and nothing is stored', $status === 429 && \Nino\Admin\Recovery::hash( $appData ) === $secretBefore );
check( '...named "recoverypw_locked"', ( $body['code'] ?? '' ) === 'recoverypw_locked' );
check( '...the same lock recovery.php meets', \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 429 );
$resetLockout();

$secretFile	= \Nino\Admin\Recovery::path( $appData );
$secretRaw	= (string) file_get_contents( $secretFile );
unlink( $secretFile );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'a project without a recovery password is refused, 409 - there is no old one to compare with', $status === 409 && is_file( $secretFile ) === false && $tries() === 0 );
check( '...named "recoverypw_unset"', ( $body['code'] ?? '' ) === 'recoverypw_unset' );
file_put_contents( $secretFile, $secretRaw );

[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] );
check( 'the right current password changes it, 200', $status === 200 );
check( '...the new one verifies and the old one does not', \Nino\Admin\Recovery::verify( $appData, 'a brand new secret' ) === 200 && \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 401 );
$secretNow = (string) file_get_contents( $secretFile );
check( '...and the file is still behind its stub', str_starts_with( $secretNow, \Nino\Admin\Recovery::STUB_PREFIX ) && str_ends_with( $secretNow, \Nino\Admin\Recovery::STUB_SUFFIX )
	&& str_contains( $secretNow, 'a brand new secret' ) === false );
check( '...the activity log names the action and nothing of the data', \Nino\Modules\Users\RecoveryPassword::log( 'recoverypw/save', [ 'current' => 'the real password', 'pw' => 'a brand new secret' ] ) === 'Change Recovery Password'
	&& \Nino\Modules\Users\RecoveryPassword::log( 'recoverypw/other', [] ) === '' );

\Nino\Auth::logoutUser( $appData );
$secretRaw = (string) file_get_contents( $secretFile );
[ $status ] = callDev( $appData, \Nino\Modules\Users\RecoveryPassword::class, 'apiSave', [ 'current' => 'a brand new secret', 'pw' => 'yet another secret' ] );
check( 'logged out it changes nothing, even with the right current password', $status === 401 && (string) file_get_contents( $secretFile ) === $secretRaw );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// The suite's own secret again, for everything after this
check( 'the suite\'s secret is back for the rest of the file', \Nino\Admin\Recovery::set( $appData, 'the real password' ) === true && \Nino\Admin\Recovery::verify( $appData, 'the real password' ) === 200 );
$resetLockout();

echo "\n";

// --- Recovery::handlePost - accounts ---------------------------------------
//
// recovery.php's two account actions, in-process with the session flag the
// login sets (the child above does the same for restore): setting a password
// is for an account that exists, and a typo must not make one - making one is
// its own action and asks for a confirmation the page sends

echo "Recovery::handlePost - set a password, create an account\n";

$recoveryCall = static function( string $action, array $data, bool $open = true ) use ( &$appData ): array {
	if( $open === true )
		\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
	else
		\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );
	return ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ], $data, $action );
};

// The Config checks above switched the activity log off: it is what these read
$logSwitch = $appData['/nino/admin/logs'] ?? true;
$appData['/nino/admin/logs'] = true;
ninoUser( $appData, 'resetme@example.com', [ '/_admin/text/manage' ], 'the old password' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
$accountsBefore = array_keys( $appData['/nino/auth/user'] );

check( 'the account to reset has a session of its own', count( \Nino\Auth::getUser( $appData, 'resetme@example.com' )['sessions'] ) === 1 );

[ $status, $body ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'typo@example.com', 'pw' => 'a new password' ] );
check( 'reset for an address that has no account is 404 and creates nothing', $status === 404 && array_keys( $appData['/nino/auth/user'] ) === $accountsBefore );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'not a mail', 'pw' => 'a new password' ] );
check( '...400 for a bad address', $status === 400 );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'short' ] );
check( '...400 for a password under the minimum, and the account keeps its own', $status === 400 && password_verify( 'the old password', $appData['/nino/auth/user']['resetme@example.com']['pw'] ) === true );

[ $status, $body ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'a new password' ] );
check( 'reset for an account that exists is 200, created false', $status === 200 && ( $body['created'] ?? null ) === false && ( $body['mail'] ?? '' ) === 'resetme@example.com' );
check( '...every session of it is gone', \Nino\Auth::getUser( $appData, 'resetme@example.com' )['sessions'] === [] );
check( '...the new password logs in and the old one does not', \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'the old password' ) === false
	&& is_array( \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'a new password' ) ) === true );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// What a recovered password is for: the developer who cannot get in. A lock
// or a deactivation would leave the new password as useless as the old one
$lockTries( [ 'resetme@example.com' => 0 - time() - 3600 ] );
\Nino\Auth::setStatus( $appData, 'resetme@example.com', false );
check( 'an account that is locked and deactivated gets in with no password at all', isset( \Nino\Auth::lockedAccounts( $appData )['resetme@example.com'] ) === true
	&& \Nino\Auth::getUser( $appData, 'resetme@example.com' )['status'] === \Nino\Auth::STATUS_DISABLED
	&& \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'a new password' ) === false );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'a second password' ] );
check( 'reset lifts the lock and activates it again', $status === 200 && isset( \Nino\Auth::lockedAccounts( $appData )['resetme@example.com'] ) === false
	&& \Nino\Auth::getUser( $appData, 'resetme@example.com' )['status'] === \Nino\Auth::STATUS_ACTIVE );
check( '...and the new password logs in', is_array( \Nino\Auth::loginUser( $appData, 'resetme@example.com', 'a second password' ) ) === true );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'a fresh password' ] );
check( 'create without the confirmation is 400 and writes nothing', $status === 400 && \Nino\Auth::getUser( $appData, 'fresh@example.com' ) === false );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'a fresh password', 'confirm' => 'yes' ] );
check( '...and so is anything but true for it', $status === 400 && \Nino\Auth::getUser( $appData, 'fresh@example.com' ) === false );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'not a mail', 'pw' => 'a fresh password', 'confirm' => true ] );
check( 'a bad address is 400', $status === 400 && array_keys( $appData['/nino/auth/user'] ) === $accountsBefore );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'short', 'confirm' => true ] );
check( '...so is a password under the minimum', $status === 400 && \Nino\Auth::getUser( $appData, 'fresh@example.com' ) === false );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'resetme@example.com', 'pw' => 'another password', 'confirm' => true ] );
check( 'an address that has an account is 409 - and its password is untouched', $status === 409
	&& password_verify( 'a second password', $appData['/nino/auth/user']['resetme@example.com']['pw'] ) === true );

[ $status, $body ] = $recoveryCall( 'recovery/create', [ 'mail' => 'fresh@example.com', 'pw' => 'a fresh password', 'confirm' => true ] );
$fresh = \Nino\Auth::getUser( $appData, 'fresh@example.com' );
check( 'create with the confirmation is 200, created true', $status === 200 && ( $body['created'] ?? null ) === true && $fresh !== false );
check( '...an account with full access and the Developer role', $fresh !== false && $fresh['perms'] === [ '/*' ] && $fresh['role'] === 'developer' && $fresh['status'] === \Nino\Auth::STATUS_ACTIVE );
check( '...that can log in', is_array( \Nino\Auth::loginUser( $appData, 'fresh@example.com', 'a fresh password' ) ) === true );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// Each of the two leaves one line, with the door as the actor - and no password in it;
// the ones that were refused above left none
check( 'a reset writes one line to the activity log, naming the account', count( $logLines( 'recovery.php', 'Reset Password resetme@example.com' ) ) === 2 );
check( '...and a create one', count( $logLines( 'recovery.php', 'Create User fresh@example.com' ) ) === 1 );
check( '...nothing about an address the door refused, and no password in the log', $logLines( 'typo@example.com' ) === []
	&& array_filter( [ 'a new password', 'a second password', 'a fresh password', 'the old password' ], static fn( string $secret ): bool => $logLines( $secret ) !== [] ) === [] );

// A reset that set the password and could not lift the lock answers 500 and has
// left its line: the account is changed, and the log says who changed it
$lockKey	= (string) ( new ReflectionMethod( '\Nino\Filesystem', '_canonicalPath' ) )->invokeArgs( null, [ &$appData, '/data/auth-tries.php' ] );
$lockFile	= \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( $lockKey ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $lockFile );
@mkdir( $lockFile );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'fresh@example.com', 'pw' => 'a fresh password' ] );
@rmdir( $lockFile );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( 'a reset that could not lift the lock is 500 and has written its line', $status === 500 && count( $logLines( 'recovery.php', 'Reset Password fresh@example.com' ) ) === 1 );

$appData['/nino/admin/logs'] = $logSwitch;

$accountsAfter = array_keys( $appData['/nino/auth/user'] );
[ $status ] = $recoveryCall( 'recovery/reset', [ 'mail' => 'resetme@example.com', 'pw' => 'yet another password' ], false );
check( 'with the session flag unset, reset is 401', $status === 401 && password_verify( 'a second password', $appData['/nino/auth/user']['resetme@example.com']['pw'] ) === true );
[ $status ] = $recoveryCall( 'recovery/create', [ 'mail' => 'unlocked@example.com', 'pw' => 'yet another password', 'confirm' => true ], false );
check( '...and so is create', $status === 401 && array_keys( $appData['/nino/auth/user'] ) === $accountsAfter );

/*	Nothing says a request carries strings, and this is the door that is open when
	nothing else is: its action was cast to a string, and 'action[]=x' is an "Array to
	string conversion" - a level the runtime treats as fatal, so a 500 for anybody who
	has fetched the page and its token. The workbench and the wizard read the action as
	a string or as none, and so does this page now: a posted array is no action. The
	harness records the warning and carries on where a request would have died, so the
	warnings are what is read back	*/
ninoWarnings();
$_POST['action'] = [ 'recovery/list' ];
\Nino\Runtime::setSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY, true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ] );
check( 'an array-shaped action is an unknown action to a recovery session that is open, not a 500', $status === 404 && ( $body['error'] ?? '' ) === 'unknown action' );
\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Recovery::class, 'handlePost' ] );
check( '...and to one that has not passed the secret, which is turned away like any other action', $status === 401 );
unset( $_POST['action'] );
check( '...with no "Array to string conversion" raised on the way', array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'Array to string conversion' ) ) === [] );

\Nino\Runtime::unsetSessionValue( $appData, \Nino\Admin\Recovery::SESSION_KEY );
\Nino\Auth::deleteUser( $appData, 'resetme@example.com' );
\Nino\Auth::deleteUser( $appData, 'fresh@example.com' );
$lockTries( [ 'resetme@example.com' => null ] );

echo "\n";


// --- Modules\Maintenance\Admin ---------------------------------------------

echo "Modules\\Maintenance\\Admin - the one switch, from the workbench\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus answers the current, unconfigured state', $status === 200 && $body['status'] === false && $body['retry'] === 3600 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => true, 'retry' => 120 ] );
check( 'apiSet accepts a valid switch', $status === 200 && $body['status'] === true && $body['retry'] === 120 );
check( '...and writes both keys to config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/status'] === true
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/retry'] === 120 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus reflects the just-saved state', $status === 200 && $body['status'] === true && $body['retry'] === 120 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => 'yes', 'retry' => 120 ] );
check( 'apiSet rejects a status that is not a bool', $status === 400 && ( $body['code'] ?? '' ) === 'bool' && ( $body['field'] ?? '' ) === 'status' && $body['error'] === 'status: expected true or false' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 30 ] );
check( 'apiSet rejects a retry below its minimum', $status === 400 && ( $body['code'] ?? '' ) === 'int_range' && ( $body['field'] ?? '' ) === 'retry' && count( $body['params'] ?? [] ) === 2 && str_starts_with( $body['error'], 'retry: expected a whole number between ' ) );

[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 999999999 ] );
check( 'apiSet rejects a retry above its maximum', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 'nope' ] );
check( 'apiSet rejects a non-integer retry rather than casting it', $status === 400 );

check( 'a rejected field leaves config.php exactly as it was', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/status'] === true
	&& \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/maintenance/retry'] === 120 );

check( 'log() names the direction of the switch', \Nino\Modules\Maintenance\Admin::log( 'maintenance/set', [ 'status' => true ] ) === 'Switch maintenance on'
	&& \Nino\Modules\Maintenance\Admin::log( 'maintenance/set', [ 'status' => false ] ) === 'Switch maintenance off' );

// The panel draws its retry field from the answer, so the bounds the answer
// names are the ones apiSet holds a value to: both edges are taken, a second
// beyond either is refused
[ , $retryBounds ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
$retryTaken = function( int $retry ) use ( &$appData ): bool {
	return callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => true, 'retry' => $retry ] )[0] === 200;
};
check( 'the state names the retry bounds apiSet enforces', is_int( $retryBounds['min'] ?? null ) && is_int( $retryBounds['max'] ?? null )
	&& $retryTaken( $retryBounds['min'] ) && $retryTaken( $retryBounds['max'] )
	&& $retryTaken( $retryBounds['min'] - 1 ) === false && $retryTaken( $retryBounds['max'] + 1 ) === false );

// Back off, so the rest of the suite runs against a normal site
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => false, 'retry' => 3600 ] );
check( 'switched back off for the rest of the suite', $status === 200 && $appData['/nino/maintenance/status'] === false );

// 401/403, the same way every other panel is checked
\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus requires an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

ninoUser( $appData, 'noswitch@example.com', [ '/_admin/text/manage' ] );
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiStatus' );
check( 'apiStatus is 403 for an account without the permission', $status === 403 );
[ $status ] = callDev( $appData, \Nino\Modules\Maintenance\Admin::class, 'apiSet', [ 'status' => true, 'retry' => 120 ] );
check( '...apiSet too', $status === 403 );
\Nino\Auth::deleteUser( $appData, 'noswitch@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Dev\Users ------------------------------------------------------------

echo "Users - accounts with a role, deletion and the role change\n";

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'pw' => 'a-long-enough-password', 'role' => 'developer' ] );
check( 'apiCreate creates a user', $status === 200 );
check( 'the new user has the manage permission', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage', 'newdev@example.com' ) === true );

// A non-manager account still gets full content access (elements/text/...),
// same as any admin account had before per-module permissions existed - only
// /_admin/users/manage is gated by the isManager checkbox
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'newcontenteditor@example.com', 'pw' => 'a-long-enough-password', 'role' => 'editor' ] );
check( 'apiCreate creates a non-manager user', $status === 200 );
check( 'a non-manager still has content-module access', \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'newcontenteditor@example.com' ) === true );
check( 'a non-manager does not have the manage permission', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage', 'newcontenteditor@example.com' ) === false );
check( '...nor any structure or system panel', \Nino\Auth::checkPermission( $appData, '/_admin/config/manage', 'newcontenteditor@example.com' ) === false && \Nino\Auth::checkPermission( $appData, '/_admin/types/manage', 'newcontenteditor@example.com' ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'norole@example.com', 'pw' => 'a-long-enough-password', 'role' => 'wizard' ] );
check( 'apiCreate rejects an unknown role', $status === 400 );
\Nino\Auth::deleteUser( $appData, 'newcontenteditor@example.com' );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'not-an-email', 'pw' => 'a-long-enough-password' ] );
check( 'apiCreate rejects an invalid mail', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'short@example.com', 'pw' => 'short' ] );
check( 'apiCreate rejects a too-short password', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'newdev@example.com', 'pw' => 'a-long-enough-password' ] );
check( 'apiCreate rejects a mail that already exists', $status === 409 );

[ , $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiList' );
check( 'the new user shows up in apiList, never a password hash', in_array( 'newdev@example.com', array_column( $body['users'], 'mail' ), true ) === true && isset( $body['users'][0]['pw'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'dev@example.com' ] );
check( 'apiDelete refuses your own account', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'newdev@example.com' ] );
check( 'apiDelete removes the user', $status === 200 );
check( 'the user is actually gone', isset( $appData['/nino/auth/user']['newdev@example.com'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'newdev@example.com' ] );
check( 'apiDelete 404s for an already-gone user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'permsdev@example.com', 'pw' => 'a-long-enough-password' ] );
check( 'apiCreate creates an account without a role - one that may only edit its own profile', $status === 200 && \Nino\Auth::getUser( $appData, 'permsdev@example.com' )['role'] === '' && \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'permsdev@example.com' ) === false );

// A name that is no string is a refusal, not a 500: the activity log asked for the
// raw value and concatenated an array
ninoWarnings();
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiLogoutAll', [ 'username' => [ 'dev@example.com' ] ] );
check( 'users/logoutall with an array for the name is 400', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiLogoutAll', [ 'username' => [ 'a' => [ 'b' ] ] ] );
check( '...whatever the array holds', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiLogoutAll', [ 'username' => 5 ] );
check( '...and a number for the name', $status === 400 );
$conversions = static fn( array $posted ): array => array_filter( ninoWarnings(), static fn( string $message ): bool => str_contains( $message, 'Array to string conversion' ) );
\Nino\Modules\Users\Admin::log( 'users/logoutall', [ 'username' => [ 'x' ] ] );
check( '...and the line the log asks for is made of it without a conversion', $conversions( [] ) === [] );
$logged = [];
$bait = [ 'username' => [ 'x' ], 'role' => [ 'x' ], 'type' => [ 'x' ], 'uri' => [ 'x' ], 'key' => [ 'x' ], 'newKey' => [ 'x' ], 'id' => [ 'x' ], 'originalKey' => [ 'x' ], 'locale' => [ 'x' ], 'targetLocale' => [ 'x' ], 'renames' => [ 'x' ] ];
foreach( [
	\Nino\Modules\Users\Admin::class 							=> [ 'users/create', 'users/delete', 'users/save', 'users/status', 'users/role' ],
	\Nino\Modules\Users\Lockout::class 						=> [ 'lockout/unlock' ],
	\Nino\Modules\Users\Roles::class 							=> [ 'roles/save', 'roles/delete' ],
	\Nino\Modules\Elements\Admin::class 						=> [ 'elements/save', 'elements/uploadimage', 'elements/removeimage', 'elements/delete' ],
	\Nino\Modules\Elements\Types::class 						=> [ 'types/save', 'types/create' ],
	\Nino\Modules\Images\Admin::class 							=> [ 'images/upload', 'images/remove', 'images/alt' ],
	\Nino\Modules\Images\Slots::class 							=> [ 'slots/create', 'slots/save', 'slots/delete' ],
	\Nino\Modules\Language\Admin::class 						=> [ 'language/addlocale' ],
	\Nino\Modules\Language\Translations::class 			=> [ 'translations/import' ],
	\Nino\Modules\Routes\Admin::class 							=> [ 'routes/savetexts' ],
	\Nino\Modules\Text\Keys::class 								=> [ 'keys/create', 'keys/save', 'keys/rename', 'keys/delete' ],
	\Nino\Modules\Navigation\Admin::class 					=> [ 'navs/save', 'navs/delete' ],
] as $class => $actions )
	foreach( $actions as $action )
		$logged[] = $class::log( $action, $bait + ( $action === 'keys/save' ? [ 'format' => 'x' ] : [] ) );
check( '...as is that of every other panel that names a posted value in it', $conversions( [] ) === [] && count( $logged ) === 29 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'unknown@example.com', 'role' => 'editor' ] );
check( 'apiSetRole 404s for an unknown user', $status === 404 );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'permsdev@example.com', 'role' => 'wizard' ] );
check( 'apiSetRole rejects a role the config does not have', $status === 400 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'permsdev@example.com', 'role' => 'editor' ] );
check( 'apiSetRole hands out a role', $status === 200 && $body['role'] === 'editor' && \Nino\Auth::getUser( $appData, 'permsdev@example.com' )['role'] === 'editor' );
check( 'the role\'s permissions took effect', \Nino\Auth::checkPermission( $appData, '/_admin/elements/manage', 'permsdev@example.com' ) === true );
check( 'a permission the role lacks stays denied', \Nino\Auth::checkPermission( $appData, '/_admin/types/manage', 'permsdev@example.com' ) === false );

[ , $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiList' );
$listed = $body['users'][ array_search( 'permsdev@example.com', array_column( $body['users'], 'mail' ), true ) ];
check( 'apiList exposes the role and the roles there are, never a password hash', $listed['role'] === 'editor' && isset( $listed['pw'] ) === false && array_column( $body['roles'], 'id' ) === [ 'editor', 'developer' ] && array_column( $body['roles'], 'label' ) === [ 'Editor', 'Developer' ] );

echo "\n";


// --- Roles ----------------------------------------------------------------

echo "Roles - named permission sets, edited on a tab of the Users panel\n";

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiList' );
check( 'apiList lists the two default roles with how many accounts hold each', $status === 200 && array_column( $body['roles'], 'id' ) === [ 'editor', 'developer' ] && $body['roles'][0]['users'] === 1 && $body['roles'][1]['users'] === 0 );
check( 'apiList offers every declared permission, grouped like the navigation - tabs included, the manage permission once', in_array( [ 'perm' => '/_admin/types/manage', 'label' => '/_admin/nav/types', 'group' => 'structure', 'offered' => true ], $body['permOptions'], true ) === true
	&& in_array( [ 'perm' => '/_admin/users/manage', 'label' => '/_admin/users/label/permissions-manage', 'group' => 'system', 'offered' => true ], $body['permOptions'], true ) === true
	&& in_array( [ 'perm' => '/_admin/lockout/manage', 'label' => '/_admin/nav/lockout', 'group' => 'system', 'offered' => true ], $body['permOptions'], true ) === true
	&& in_array( [ 'perm' => '/_admin/translations/manage', 'label' => '/_admin/nav/translations', 'group' => 'system', 'offered' => true ], $body['permOptions'], true ) === true
	&& count( array_filter( $body['permOptions'], fn( array $o ): bool => $o['perm'] === '/_admin/users/manage' ) ) === 1
	&& array_values( array_filter( $body['permOptions'], fn( array $o ): bool => $o['perm'] === '/_admin/recoverypw/manage' ) ) === [ [ 'perm' => '/_admin/recoverypw/manage', 'label' => '/_admin/nav/recoverypw', 'group' => 'system', 'offered' => true ] ] );
check( 'apiList names the groups in the rail\'s order with \'other\' last, and every offered permission sits in one of them', ( $body['groups'] ?? null ) === [ ...\Nino\Admin\Panels::GROUPS, 'other' ]
	&& array_diff( array_column( $body['permOptions'], 'group' ), $body['groups'] ?? [] ) === [] );

// A permission an account carries directly, with no panel and no role behind
// it, is in force just the same - so it is on the list, marked as offered by
// nothing, and a role save cannot quietly wash it out of the installation
$strayAppData = $appData;
$strayAppData['/nino/auth/user']['stray@example.com'] = [ 'perms' => [ '/_admin/legacy/manage' ] ];
check( 'a permission held by an account alone is listed too, as offered by nothing', in_array( [ 'perm' => '/_admin/legacy/manage', 'label' => '/_admin/legacy/manage', 'group' => 'other', 'offered' => false ], \Nino\Modules\Users\Admin::permOptions( $strayAppData ), true ) === true );
check( 'full access is never one of them - it is its own control', in_array( '/*', \Nino\Modules\Users\Admin::permsInUse( $strayAppData ), true ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'Not A Slug', 'label' => 'X', 'perms' => [] ] );
check( 'apiSave rejects an id that is not a slug', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => '', 'perms' => [] ] );
check( 'apiSave rejects an empty name', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => 'not-an-array' ] );
check( 'apiSave rejects a non-array perms value', $status === 400 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', 42 ] ] );
check( 'apiSave rejects a perms array with a non-string entry', $status === 400 );

// Checked for shape rather than against a list: the scoped permissions (see
// \Nino\Admin\Admin::scoped()) are one string per action, field and text key,
// so there is nothing to enumerate. A string no permission check could ever
// match is still refused, and by name - dropping it silently is how a role
// comes back missing what was just typed into it
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', 'not a perm' ] ] );
check( 'apiSave refuses a string that is not shaped like a permission', $status === 400 );
check( '...naming it, so the typo can be found', str_contains( (string) ( $body['error'] ?? '' ), 'not a perm' ) );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', '/_admin/elements/services/update/title' ] ] );
check( 'apiSave keeps a well-formed permission no panel offers', $status === 200 && $body['perms'] === [ '/_admin/elements/manage', '/_admin/elements/services/update/title' ] && $body['users'] === 0 );
check( 'the role landed in config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/roles']['reviewer'] === [ 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', '/_admin/elements/services/update/title' ] ] );
check( 'a wildcard segment is a permission too', callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/text/update/page-home/*' ] ] )[0] === 200 );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/elements/manage', '/*' ] ] );
check( 'full access stands alone - it covers everything else already', $status === 200 && $body['perms'] === [ '/*' ] );

// The two refusals: the account making the change keeps the manage
// permission, and some account keeps full access. dev@example.com relies on
// the Developer role alone for this, the way the wizard's root account does -
// and has to be the last full access there is, so the Backup section's
// account above goes first
\Nino\Auth::deleteUser( $appData, 'admin@example.com' );
$appData['/nino/auth/user']['dev@example.com']['perms'] = [];
\Nino\Auth::setRole( $appData, 'dev@example.com', 'developer' );
check( 'the signed-in account now holds full access through its role only', \Nino\Auth::checkPermission( $appData, '/_admin/users/manage' ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [ '/_admin/elements/manage' ] ] );
check( 'a change that would take the manage permission from your own account is refused', $status === 409 && $appData['/nino/auth/roles']['developer']['perms'] === [ '/*' ] );

ninoUser( $appData, 'mgr@example.com', [ '/_admin/users/manage', '/_admin/text/manage' ], 'a-long-enough-password' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [ '/_admin/elements/manage', '/_admin/users/manage' ] ] );
check( 'a change that would leave no account with full access is refused', $status === 409 && $appData['/nino/auth/roles']['developer']['perms'] === [ '/*' ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'dev@example.com', 'role' => 'editor' ] );
check( 'nor may the last full access be handed away through a role change', $status === 409 && \Nino\Auth::getUser( $appData, 'dev@example.com' )['role'] === 'developer' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiDelete', [ 'username' => 'dev@example.com' ] );
check( 'nor deleted - the role counts, not just the account\'s own permissions', $status === 409 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/text/manage' ] ] );
check( 'a manager without full access still edits any other role', $status === 200 );

// Granting is bounded by holding (see Roles::notHeld()). The detour a manager
// could take to full access - write a full-access role, create an account
// holding it with a password of its choosing, sign in as that - is refused
// at every step, and so is the narrower version of the same move with any
// permission the manager does not hold itself
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'super', 'label' => 'Super', 'perms' => [ '/*' ] ] );
check( 'a manager may not write a role holding a permission it does not hold itself', $status === 403 && isset( $appData['/nino/auth/roles']['super'] ) === false );
check( '...and the refusal names the permission', str_contains( (string) ( $body['error'] ?? '' ), '/*' ) );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'reviewer', 'label' => 'Reviewer', 'perms' => [ '/_admin/text/manage', '/_admin/elements/manage' ] ] );
check( 'nor add one to a role that exists', $status === 403 && $appData['/nino/auth/roles']['reviewer']['perms'] === [ '/_admin/text/manage' ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'detour@example.com', 'pw' => 'a-long-enough-password', 'role' => 'developer' ] );
check( 'nor create an account with a role wider than its own', $status === 403 && \Nino\Auth::getUser( $appData, 'detour@example.com' ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'detour@example.com', 'pw' => 'a-long-enough-password', 'role' => 'reviewer' ] );
check( '...while one holding only what the manager holds is created', $status === 200 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'detour@example.com', 'role' => 'developer' ] );
check( 'nor move an account onto such a role', $status === 403 && \Nino\Auth::getUser( $appData, 'detour@example.com' )['role'] === 'reviewer' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developers', 'perms' => [ '/*' ] ] );
check( 'a role it may not widen it may still rename - only what a role gains is measured', $status === 200 && $appData['/nino/auth/roles']['developer']['label'] === 'Developers' && $appData['/nino/auth/roles']['developer']['perms'] === [ '/*' ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'developer', 'label' => 'Developer', 'perms' => [ '/*' ] ] );
\Nino\Auth::deleteUser( $appData, 'detour@example.com' );

// Holding fewer permissions is not always holding less: a panel permission
// with no scoped one beside it allows the whole panel, one with a scoped
// one only what that names (see \Nino\Admin\Admin::isScoped()). So a set
// that opens a panel without describing it in detail may not come into
// being at the hands of an account that is described in detail for it -
// as a role, as an account's role, or as an account's password
$elementsDoor = '/_admin/elements/manage';
$elementsOne 	= '/_admin/elements/testtype/insert';
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'lead', 'label' => 'Lead', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM, $elementsDoor, $elementsOne ] ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'plainlead', 'label' => 'Plain lead', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM, $elementsDoor ] ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'wide', 'label' => 'Wide', 'perms' => [ $elementsDoor ] ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'described', 'label' => 'Described', 'perms' => [ $elementsDoor, $elementsOne ] ] );
\Nino\Auth::insertUser( $appData, 'lead@example.com', 'lead password', [], 'lead' );
\Nino\Auth::insertUser( $appData, 'plainlead@example.com', 'plainlead password', [], 'plainlead' );
\Nino\Auth::insertUser( $appData, 'widehold@example.com', 'widehold password', [], 'wide' );
\Nino\Auth::insertUser( $appData, 'describedhold@example.com', 'describedhold password', [], 'described' );
$storedRoles = static fn(): array => \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/roles'] ?? [];
$rolesBefore = $storedRoles();

\Nino\Auth::loginUser( $appData, 'lead@example.com', 'lead password' );
check( 'the fixture: the lead is described in detail for the elements panel, the plain lead is not', \Nino\Admin\Admin::isScoped( $appData, '/_admin/elements/' ) === true
	&& \Nino\Auth::checkPermission( $appData, '/_admin/elements/other/insert' ) === false );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'lead', 'label' => 'Lead', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM, $elementsDoor ] ] );
check( 'a role may not drop its own scoped entry - the door alone allows the whole panel', $status === 403 && ( $body['code'] ?? '' ) === 'roles_grant_too_wide_scoped' );
check( '...and the stored roles are as they were', $storedRoles() === $rolesBefore );
check( '...the refusal names the panel', str_contains( (string) ( $body['error'] ?? '' ), '/_admin/elements/' ) );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'door', 'label' => 'Door', 'perms' => [ $elementsDoor ] ] );
check( 'nor write a new role holding only the door it holds', $status === 403 && ( $body['code'] ?? '' ) === 'roles_grant_too_wide_scoped' && isset( $storedRoles()['door'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'door', 'label' => 'Door', 'perms' => [ $elementsDoor, '/_admin/elements/*' ] ] );
check( '...nor with a blanket the lead does not hold', $status === 403 && isset( $storedRoles()['door'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'door', 'label' => 'Door', 'perms' => [ '/*' ] ] );
check( '...nor full access', $status === 403 && isset( $storedRoles()['door'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'door', 'label' => 'Door', 'perms' => [ $elementsDoor, $elementsOne ] ] );
check( 'a role described in detail within what the lead holds is written', $status === 200 && $storedRoles()['door']['perms'] === [ $elementsDoor, $elementsOne ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => 'door' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'wide', 'label' => 'Wide, relabelled', 'perms' => [ $elementsDoor ] ] );
check( 'a role that already was wide is relabelled by the lead - nothing widens', $status === 200 && $storedRoles()['wide']['label'] === 'Wide, relabelled' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'described', 'label' => 'Described', 'perms' => [ $elementsDoor ] ] );
check( 'a described role is not widened by the lead, whoever holds it', $status === 403 && $storedRoles()['described']['perms'] === [ $elementsDoor, $elementsOne ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'viawide@example.com', 'pw' => 'a-long-enough-password', 'role' => 'wide' ] );
check( 'the lead creates no account with a role that allows the whole panel', $status === 403 && ( $body['code'] ?? '' ) === 'users_role_too_wide_scoped' && \Nino\Auth::getUser( $appData, 'viawide@example.com' ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'viadescribed@example.com', 'pw' => 'a-long-enough-password', 'role' => 'described' ] );
check( '...while a role described within its own is handed out', $status === 200 );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'viadescribed@example.com', 'role' => 'wide' ] );
check( 'nor sets such a role on an account', $status === 403 && ( $body['code'] ?? '' ) === 'users_role_too_wide_scoped' && \Nino\Auth::getUser( $appData, 'viadescribed@example.com' )['role'] === 'described' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSave', [ 'username' => 'widehold@example.com', 'mail' => 'widehold@example.com', 'pw' => 'a password of my choosing' ] );
check( 'nor sets the password of an account that holds it', $status === 403 && ( $body['code'] ?? '' ) === 'users_password_too_wide_scoped' && password_verify( 'widehold password', \Nino\Auth::getUser( $appData, 'widehold@example.com' )['pw'] ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSave', [ 'username' => 'describedhold@example.com', 'mail' => 'describedhold@example.com', 'pw' => 'a password of my choosing' ] );
check( '...while the password of an account described within its own is set', $status === 200 );

// A role is not the account's only source of permissions: taking it away
// leaves what the account holds of its own
$appData['/nino/auth/user']['viadescribed@example.com']['perms'] = [ $elementsDoor ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'viadescribed@example.com', 'role' => '' ] );
check( 'a role is not taken from an account whose own permissions are then wide', $status === 403 && ( $body['code'] ?? '' ) === 'users_role_too_wide_scoped' && \Nino\Auth::getUser( $appData, 'viadescribed@example.com' )['role'] === 'described' );
$appData['/nino/auth/user']['viadescribed@example.com']['perms'] = [];
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiSetRole', [ 'username' => 'viadescribed@example.com', 'role' => '' ] );
check( '...and is taken from one that is then holding nothing', $status === 200 );

// A door and a blanket describe nothing in detail; a scoped entry does
check( 'isDescribed asks the definition of a list: the door and a blanket do not describe a panel, a scoped entry does', \Nino\Admin\Admin::isDescribed( [ $elementsDoor, '/_admin/elements/*' ], '/_admin/elements/' ) === false
	&& \Nino\Admin\Admin::isDescribed( [ $elementsDoor, $elementsOne ], '/_admin/elements/' ) === true );

// Only an account that holds the whole panel widens a role for it: not one
// that lost the door (a lead narrowed to managing users), nor a manager who
// never had it
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'sock', 'label' => 'Sock', 'perms' => [ $elementsDoor, $elementsOne ] ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'lead', 'label' => 'Lead', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM ] ] );
\Nino\Auth::loginUser( $appData, 'lead@example.com', 'lead password' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'sock', 'label' => 'Sock', 'perms' => [ $elementsDoor ] ] );
check( 'a manager without the panel turns a described role into the whole panel - refused', $status === 403 && ( $body['code'] ?? '' ) === 'roles_grant_too_wide_scoped' && $storedRoles()['sock']['perms'] === [ $elementsDoor, $elementsOne ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'sock', 'label' => 'Sock, relabelled', 'perms' => [ $elementsDoor, $elementsOne ] ] );
check( '...while relabelling it is open', $status === 200 );

// A blanket lifts the limit: the account holds the whole panel
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'lead', 'label' => 'Lead', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM, $elementsDoor, '/_admin/elements/*', $elementsOne ] ] );
\Nino\Auth::loginUser( $appData, 'lead@example.com', 'lead password' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'sock', 'label' => 'Sock', 'perms' => [ $elementsDoor ] ] );
check( 'an account holding a blanket of the panel beside a scoped entry writes the door-only role', $status === 200 && $storedRoles()['sock']['perms'] === [ $elementsDoor ] );

// A panel with nothing to offer right now is a panel all the same: the
// guards read its prefix, not its list
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'lead', 'label' => 'Lead', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM, $elementsDoor, $elementsOne ] ] );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'sock', 'label' => 'Sock', 'perms' => [ $elementsDoor, $elementsOne ] ] );
rename( $sandbox. '/private/elements/testtype.php', $sandbox. '/private/elements/testtype.off' );
check( 'the fixture: the elements panel offers no scope without a type, and the guards\' list keeps it', in_array( '/_admin/elements/', array_column( \Nino\Modules\Users\Admin::scopeOptions( $appData ), 'scope' ), true ) === false
	&& in_array( '/_admin/elements/', array_column( \Nino\Modules\Users\Admin::scopeOptions( $appData, true ), 'scope' ), true ) === true );
\Nino\Auth::loginUser( $appData, 'lead@example.com', 'lead password' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'sock', 'label' => 'Sock', 'perms' => [ $elementsDoor ] ] );
check( 'a door-only role is refused although the panel has no type to name', $status === 403 && ( $body['code'] ?? '' ) === 'roles_grant_too_wide_scoped' && $storedRoles()['sock']['perms'] === [ $elementsDoor, $elementsOne ] );
rename( $sandbox. '/private/elements/testtype.off', $sandbox. '/private/elements/testtype.php' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// The same for the text panel: another scope, the same behaviour
$textDoor = \Nino\Modules\Text\Admin::MANAGE_PERM;
$textOne 	= '/_admin/text/update/template/page-home/*';
$scopes 	= array_column( \Nino\Modules\Users\Admin::scopeOptions( $appData ), 'scope' );
check( 'the scopes there are come from the panels, and the text panel is one of them', in_array( '/_admin/text/', $scopes, true ) === true && in_array( '/_admin/elements/', $scopes, true ) === true );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'textlead', 'label' => 'Text lead', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM, $textDoor, $textOne ] ] );
\Nino\Auth::insertUser( $appData, 'textlead@example.com', 'textlead password', [], 'textlead' );
\Nino\Auth::loginUser( $appData, 'textlead@example.com', 'textlead password' );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'textdoor', 'label' => 'Text door', 'perms' => [ $textDoor ] ] );
check( 'a role holding only the door of the text panel is refused the same way, naming that panel', $status === 403 && ( $body['code'] ?? '' ) === 'roles_grant_too_wide_scoped' && str_contains( (string) ( $body['error'] ?? '' ), '/_admin/text/' ) );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'textdoor', 'label' => 'Text door', 'perms' => [ \Nino\Modules\Users\Admin::MANAGE_PERM, $textDoor, $textOne ] ] );
check( '...and one described within what it holds is written', $status === 200 );

// An account that is not described in detail for a panel holds all of it,
// and may hand out all of it; full access is never limited
\Nino\Auth::loginUser( $appData, 'plainlead@example.com', 'plainlead password' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'door', 'label' => 'Door', 'perms' => [ $elementsDoor ] ] );
check( 'a manager with no scoped permission writes the door-only role', $status === 200 && $storedRoles()['door']['perms'] === [ $elementsDoor ] );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Admin::class, 'apiCreate', [ 'mail' => 'viadoor@example.com', 'pw' => 'a-long-enough-password', 'role' => 'door' ] );
check( '...creates an account with it', $status === 200 );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'door', 'label' => 'Door', 'perms' => [ $elementsDoor, $elementsOne ] ] );
check( '...but describes none in detail, which is a permission it does not hold', $status === 403 && ( $body['code'] ?? '' ) === 'roles_grant_too_wide' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiSave', [ 'id' => 'door', 'label' => 'Door', 'perms' => [ $elementsDoor ] ] );
check( 'full access widens any role, as before', $status === 200 && $storedRoles()['door']['perms'] === [ $elementsDoor ] );
foreach( [ 'viadoor@example.com', 'viadescribed@example.com', 'lead@example.com', 'plainlead@example.com', 'widehold@example.com', 'describedhold@example.com', 'textlead@example.com' ] as $mail )
	\Nino\Auth::deleteUser( $appData, $mail );
foreach( [ 'door', 'sock', 'lead', 'plainlead', 'wide', 'described', 'textlead', 'textdoor' ] as $role )
	callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => $role ] );

[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => 'nope' ] );
check( 'apiDelete 404s for an unknown role', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => 'editor' ] );
check( 'apiDelete refuses a role an account holds', $status === 409 && isset( $appData['/nino/auth/roles']['editor'] ) === true );
[ $status ] = callDev( $appData, \Nino\Modules\Users\Roles::class, 'apiDelete', [ 'id' => 'reviewer' ] );
check( 'apiDelete removes a role nobody holds', $status === 200 && isset( $appData['/nino/auth/roles']['reviewer'] ) === false && isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/auth/roles']['reviewer'] ) === false );

\Nino\Auth::deleteUser( $appData, 'mgr@example.com' );
\Nino\Auth::deleteUser( $appData, 'permsdev@example.com' );
$appData['/nino/auth/user']['dev@example.com']['perms'] = [ '/*' ];
\Nino\Auth::setRole( $appData, 'dev@example.com', '' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";


// --- Final: unauthed guard sanity check across every module -------------

echo "Every module rejects an unauthed request\n";

\Nino\Auth::logoutUser( $appData );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiList' ] );
check( 'apiList requires an authed _admin session, same as every other module', $status === 401 );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ] );
check( '...apiNow too', $status === 401 );

ninoDone( $appData );

/** The three lines of the dashboard's greeting are three switches of the Config panel, in a group of their own, on by default, each saved on its own */
function pinGreetingSwitchesInConfig( array &$appData ): void {

	[ , $body ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiList' );
	$byKey = array_column( $body['fields'], null, 'key' );
	$keys = [ '/nino/admin/greeting/hello', '/nino/admin/greeting/sentence', '/nino/admin/greeting/facts' ];

	check( 'the panel offers the three switches of the greeting, as booleans of the group Dashboard, on without a stored value',
		count( array_intersect( $keys, array_keys( $byKey ) ) ) === 3
		&& count( array_filter( $keys, static fn( string $key ): bool => $byKey[$key]['type'] === 'bool' && $byKey[$key]['group'] === 'dashboard' && $byKey[$key]['value'] === true && \Nino\AppData::DEFAULTS[$key] === true ) ) === 3 );
	check( '...the group has its heading and its line, and every switch its label and its hint, in both interface languages', ( static function() use ( $body, $keys, $byKey ): bool {
		foreach( [ 'en_US', 'de_DE' ] as $locale ) {
			$words = (array) require dirname( __DIR__ ). '/_admin/Nino/Modules/Config/text/'. $locale. '.php';
			$asked = $body['groups']['dashboard'];
			foreach( $keys as $key )
				$asked = array_merge( $asked, [ $byKey[$key]['label'], $byKey[$key]['hint'] ] );
			foreach( $asked as $word )
				if( trim( (string) ( $words['[['. $word. ']]'] ?? '' ) ) === '' )
					return false;
		}
		return true;
	} )() );

	[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/admin/greeting/sentence' => false ] ] );
	$stored = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
	check( 'switching one off writes that one, and the other two are left as they are', $status === 200 && $stored['/nino/admin/greeting/sentence'] === false
		&& array_key_exists( '/nino/admin/greeting/hello', $stored ) === false && array_key_exists( '/nino/admin/greeting/facts', $stored ) === false );

	[ $status ] = callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/admin/greeting/facts' => 'yes' ] ] );
	check( 'a value that is no boolean is refused', $status === 400 && ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/admin/greeting/facts'] ?? null ) === null );

	callDev( $appData, \Nino\Modules\Config\Admin::class, 'apiSave', [ 'fields' => [ '/nino/admin/greeting/sentence' => true ] ] );
}
