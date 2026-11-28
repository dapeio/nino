<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-backups-smoke.php	Dependency-free smoke test for the archives of the Backups panel as files:
 *											how an archive made by hand is named and how the name made before the suffix is
 *											still read, the download (what is answered, who may ask, an id that is no archive,
 *											a lock that is held), the upload (name, shape, key, memory, an archive that exists,
 *											a file that only looks like one, php that must never run) and what an uploaded
 *											archive means for the retention. Runs against isolated sandbox directories, never
 *											touches the real project data.
 *
 *	Usage: php tests/admin-backups-smoke.php
 */

require __DIR__. '/harness.php';

/** A project that is set up, with one developer account that is signed in and the archive of its first day */
function backupsProject( string $name, ?string $key = null ): array {

	$appData = ninoSandbox( $name );

	$sandbox = ninoSandboxDir( $appData );
	mkdir( $sandbox. '/private', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', ( $key === null ? [] : [ '/nino/backup/key' => $key ] ) + [
		'/nino/error/display'			=> true,
		'/nino/auth/maxtries'			=> 5,
		'/nino/auth/cooldown'			=> 3600,
		'/nino/locales/native'		=> $appData['/nino/locales/native'],
		'/nino/locales/available'	=> $appData['/nino/locales/available'],
	] );

	$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );
	ninoUser( $appData, 'dev@example.com' );
	\Nino\Modules\Backups::maybeRun( $appData );

	return $appData;
}

/** What a browser hands php of a chosen file: the entry of $_FILES, over a file of the sandbox */
function backupsFile( array &$appData, string $name, string $bytes, int $error = UPLOAD_ERR_OK ): void {

	$path = ninoSandboxDir( $appData ). '/upload-'. bin2hex( random_bytes( 4 ) ). '.bin';
	file_put_contents( $path, $bytes );

	$_FILES['file'] = [ 'name' => $name, 'type' => 'application/octet-stream', 'tmp_name' => $path, 'error' => $error, 'size' => strlen( $bytes ) ];
}

/** The stub of an archive of the given key around the given plain bytes - what the project's own writer makes of them */
function backupsStub( string $plain, string $rawKey ): string {
	return ninoSealed( $plain, $rawKey );
}

$upload		= [ \Nino\Modules\Backups\Admin::class, 'apiUpload' ];
$download	= [ \Nino\Modules\Backups\Admin::class, 'apiDownload' ];
$list			= [ \Nino\Modules\Backups\Admin::class, 'apiList' ];

$appData	= backupsProject( 'backups-files' );
$sandbox	= ninoSandboxDir( $appData );
$dir			= $sandbox. '/private/.backups';
$today		= date( 'Y-m-d' );

$tmpFiles	= static fn(): array => glob( $dir. '/*.tmp' ) ?: [];
$ids			= static fn(): array => array_map( static fn( string $file ): string => basename( $file, '.php' ), glob( $dir. '/*.php' ) ?: [] );
$logLines	= static fn( int $count = 40 ): string => implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $appData, $count ) );

echo "Sandbox: $sandbox\n\n";


// --- The name of an archive made by hand -----------------------------------

echo "Naming\n";

$id = \Nino\Modules\Backups::now( $appData );

check( 'Back up now names its archive by date and time and marks it as made by hand', is_string( $id ) === true && preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}-manual$/', $id ) === 1 && is_file( $dir. '/'. $id. '.php' ) === true );
check( '...and the panel lists it as an archive made by hand, with its size, before the daily one', array_column( ninoCall( $appData, $list )[1]['archives'], 'kind', 'id' ) === [ $id => 'manual', $today => 'daily' ]
	&& array_column( ninoCall( $appData, $list )[1]['archives'], 'size', 'id' )[$id] === filesize( $dir. '/'. $id. '.php' ) );

$pattern = \Nino\Modules\Backups\Admin::ID_PATTERN;
check( 'an id is a date, a date and time, either of them marked as made by hand where it has a time, or a snapshot',
	preg_match( $pattern, '2026-10-02' ) === 1 && preg_match( $pattern, '2026-10-02-170512' ) === 1 && preg_match( $pattern, '2026-10-02-170512-manual' ) === 1 && preg_match( $pattern, 'pre-restore-2026-10-02-170512' ) === 1 );
check( '...and nothing else: a date marked without a time, another mark, a path, a suffix after a snapshot',
	preg_match( $pattern, '2026-10-02-manual' ) === 0 && preg_match( $pattern, '2026-10-02-170512-auto' ) === 0 && preg_match( $pattern, '2026-10-02-170512-manual-manual' ) === 0
	&& preg_match( $pattern, '../2026-10-02' ) === 0 && preg_match( $pattern, '2026-10-02-170512-manual/../x' ) === 0 && preg_match( $pattern, 'pre-restore-2026-10-02-170512-manual' ) === 0 && preg_match( $pattern, "2026-10-02\n" ) === 0 );
check( 'the kind of an id: a date is daily, a time is by hand with or without the mark, a snapshot is none of the two',
	\Nino\Modules\Backups\Admin::kind( '2026-10-02' ) === 'daily' && \Nino\Modules\Backups\Admin::kind( '2026-10-02-170512' ) === 'manual'
	&& \Nino\Modules\Backups\Admin::kind( '2026-10-02-170512-manual' ) === 'manual' && \Nino\Modules\Backups\Admin::kind( 'pre-restore-2026-10-02-170512' ) === 'snapshot' );

// An archive of the name made before the suffix, on a project that updated: listed, kept as one made by hand, restorable, and renamed by nothing
// An hour before, and in the first hour of a day the start of the day: the list is told by days, and this archive belongs to the day of the other
$legacy = date( 'Y-m-d-His', max( time() - 3600, strtotime( 'today' ) ) );
copy( $dir. '/'. $id. '.php', $dir. '/'. $legacy. '.php' );

check( 'an archive of the older name is listed as made by hand, after the newer one', ninoCall( $appData, $list )[1]['archives'][1] === [ 'id' => $legacy, 'kind' => 'manual', 'size' => filesize( $dir. '/'. $legacy. '.php' ) ] && \Nino\Modules\Backups\Admin::dates( $appData ) === [ $id, $legacy, $today ] );

$appData['/nino/admin/backups-manual'] = 1;
$second = \Nino\Modules\Backups::now( $appData );
$appData['/nino/admin/backups-manual'] = 3;

check( 'the retention counts it with the ones made by hand: with one kept, a new one deletes the older name and the older one with the mark', is_string( $second ) === true
	&& is_file( $dir. '/'. $legacy. '.php' ) === false && $second !== $id && is_file( $dir. '/'. $id. '.php' ) === false && is_file( $dir. '/'. $second. '.php' ) === true && is_file( $dir. '/'. $today. '.php' ) === true );

// The same second, the two names: the mark decides nothing about age
copy( $dir. '/'. $second. '.php', $dir. '/'. substr( $second, 0, 17 ). '.php' );
$names = [ substr( $second, 0, 17 ). '.php', $second. '.php' ];
$prune = new ReflectionMethod( '\Nino\Modules\Backups', '_prune' );
$prune->invoke( null, $dir, 3, 3, $second. '.php' );
check( 'an archive of the older name made in the second of the new one is not later than it, and neither is deleted while there is room', is_file( $dir. '/'. $names[0] ) === true && is_file( $dir. '/'. $names[1] ) === true );
unlink( $dir. '/'. $names[0] );

$restored = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $second ] );
check( 'an archive made by hand with the mark is restored', $restored[0] === 200 );

// A restore ends the session of the account: in again for what follows
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

copy( $dir. '/'. $second. '.php', $dir. '/'. $legacy. '.php' );
$restored = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $legacy ] );
check( '...and one of the older name', $restored[0] === 200 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
unlink( $dir. '/'. $legacy. '.php' );

// Daily and made by hand are told apart by the shape of the name
copy( $dir. '/'. $second. '.php', $dir. '/2001-02-03.php' );
copy( $dir. '/'. $second. '.php', $dir. '/2001-02-03-040506.php' );
$kinds = array_column( ninoCall( $appData, $list )[1]['archives'], 'kind', 'id' );
check( 'a file that is named as no archive is not listed, whatever it holds', ( file_put_contents( $dir. '/2001-02-03-manual.php', 'x' ) === 1 ) && ( file_put_contents( $dir. '/notes.php', 'x' ) === 1 ) && ! array_key_exists( '2001-02-03-manual', $kinds ) && ! array_key_exists( 'notes', $kinds ) );
check( '...and the two shapes of the others are told apart', ( $kinds['2001-02-03'] ?? '' ) === 'daily' && ( $kinds['2001-02-03-040506'] ?? '' ) === 'manual' );
unlink( $dir. '/2001-02-03-manual.php' );
unlink( $dir. '/notes.php' );
unlink( $dir. '/2001-02-03.php' );
unlink( $dir. '/2001-02-03-040506.php' );


// --- Download ---------------------------------------------------------------

echo "\nDownload\n";

$server	= [ 'Host' => 'Example.COM:8443' ];
$ask		= static function( array &$appData, string $date, array $header = [] ) use ( $download ): array {

	$_POST['data'] = json_encode( [ 'date' => $date ] );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ], '/nino/http/request' => [ 'header' => $header ] ];
	$download( $appData, $request );

	return [ $request['/nino/http/response']['statusCode'], $request['/nino/http/response'] ];
};

[ $status, $response ] = $ask( $appData, $second, $server );
check( 'a download answers 200 with the file of the archive, as it lies', $status === 200 && ( $response['file'] ?? '' ) === $dir. '/'. $second. '.php' && ! array_key_exists( 'error', (array) ( $response['body'] ?? [] ) ) );
check( '...as an attachment, named by the host of the request, the date and the kind', ( $response['header']['Content-Disposition'] ?? '' ) === 'attachment; filename="backup-example.com-'. $second. '.php"' && ( $response['header']['Content-Type'] ?? '' ) === 'application/octet-stream' );
check( '...never cached, and with no body of its own', ( $response['header']['Cache-Control'] ?? '' ) === 'private, no-store' && ( $response['body'] ?? null ) === '' );

[ , $response ] = $ask( $appData, $second, [ 'Host' => "evil\r\nSet-Cookie: x=1" ] );
check( 'a host that is no host name is not in the name of the file', ( $response['header']['Content-Disposition'] ?? '' ) === 'attachment; filename="backup-nino-'. $second. '.php"' );
[ , $response ] = $ask( $appData, $today );
check( 'a request without a host gets a name of its own, and a daily archive is the date', ( $response['header']['Content-Disposition'] ?? '' ) === 'attachment; filename="backup-nino-'. $today. '.php"' );

foreach( [ '', '../'. $today, $today. '/../'. $today, $today. '.php', 'config', '2026-10-02-170512-manual/', '/etc/passwd', "$today\0", 'attempt', 'run', '2026-10-02-manual' ] as $bad ) {
	[ $status, $response ] = $ask( $appData, $bad );
	if( $status !== 400 || array_key_exists( 'file', $response ) === true )
		$badAnswered = $bad;
}
check( 'an id that is no archive is a 400 and names no file', ! isset( $badAnswered ) );

[ $status, $response ] = $ask( $appData, '2001-02-03' );
check( 'an archive that does not exist is a 404 with a code of its own, and names no file', $status === 404 && ( $response['body']['code'] ?? '' ) === 'backups_gone' && array_key_exists( 'file', $response ) === false );

// The note of the attempt and the record of a run are in the directory, behind the same stub, and are no archives
file_put_contents( $dir. '/attempt.php', \Nino\Admin\Recovery::STUB_PREFIX. \Nino\Admin\Recovery::STUB_SUFFIX );
[ $status ] = $ask( $appData, 'attempt' );
check( 'the files of the directory that are no archives are not to be had', $status === 400 );
unlink( $dir. '/attempt.php' );

// A link out of the directory is no archive
symlink( $sandbox. '/private/config.php', $dir. '/2002-02-02.php' );
[ $status, $response ] = $ask( $appData, '2002-02-02' );
check( 'a link in the place of an archive is a 404, whatever it points at', $status === 404 && array_key_exists( 'file', $response ) === false );
unlink( $dir. '/2002-02-02.php' );

// A snapshot is an archive too
$snapshot = 'pre-restore-'. date( 'Y-m-d-His' );
copy( $dir. '/'. $second. '.php', $dir. '/'. $snapshot. '.php' );
[ $status, $response ] = $ask( $appData, $snapshot );
check( 'a safety snapshot is downloaded like the rest', $status === 200 && ( $response['file'] ?? '' ) === $dir. '/'. $snapshot. '.php' );
unlink( $dir. '/'. $snapshot. '.php' );

// The lock of a backup or a restore
$holder = $appData;
$holder['./nino/filesystem/locks'] = [];
check( 'the lock of a run can be held by another process', \Nino\Filesystem::lockFile( $holder, \Nino\Modules\Backups::LOCK_PATH ) === true );
[ $status, $response ] = $ask( $appData, $second );
check( 'a download while a backup or a restore runs is harmless: the archive is whole or it is not there', $status === 200 && ( $response['file'] ?? '' ) === $dir. '/'. $second. '.php' && hash_file( 'sha256', $response['file'] ) === hash_file( 'sha256', $dir. '/'. $second. '.php' ) );
\Nino\Filesystem::unlockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );

// Who may ask
$before = $ids();
$_POST['action'] = 'backups/download';
[ $status, $body, $answered ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'date' => $second ], 'backups/download', 403 );
check( 'the dispatcher stops a request the csrf guard refused before the action is looked at', $status === 403 && array_key_exists( 'file', $answered['/nino/http/response'] ) === false );

[ $status, $body, $answered ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'date' => $second ], 'backups/download' );
check( 'the action is part of the dispatch of the workbench, which answers it with the file and writes it to the activity log', $status === 200 && ( $answered['/nino/http/response']['file'] ?? '' ) === $dir. '/'. $second. '.php'
	&& str_contains( $logLines(), 'Download Backup '. $second ) === true );

\Nino\Auth::logoutUser( $appData );
[ $status, $response ] = $ask( $appData, $second );
check( 'logged out it is a 401 and names no file', $status === 401 && array_key_exists( 'file', $response ) === false );
[ $status, $body, $answered ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'date' => $second ], 'backups/download' );
check( '...also through the dispatch of the workbench', $status === 401 && array_key_exists( 'file', $answered['/nino/http/response'] ) === false );

ninoUser( $appData, 'usersonly@example.com', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
[ $status, $response ] = $ask( $appData, $second );
check( 'an account without the permission of the Backups panel gets a 403 and no file', $status === 403 && array_key_exists( 'file', $response ) === false );
[ $status, $body, $answered ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'date' => $second ], 'backups/download' );
check( '...also through the dispatch of the workbench', $status === 403 && array_key_exists( 'file', $answered['/nino/http/response'] ) === false );
\Nino\Auth::deleteUser( $appData, 'usersonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );


// --- Upload -------------------------------------------------------------------

echo "\nUpload\n";

$put = static function( array &$appData, string $name, string $bytes, int $error = UPLOAD_ERR_OK ) use ( $upload ): array {

	backupsFile( $appData, $name, $bytes, $error );
	[ $status, $body ] = ninoCall( $appData, $upload );
	@unlink( $_FILES['file']['tmp_name'] );

	return [ $status, $body ];
};

$rawKey 	= (string) base64_decode( (string) $appData['/nino/backup/key'], true );
$archive 	= (string) file_get_contents( $dir. '/'. $second. '.php' );
$old			= '2025-03-04-050607-manual';

// An archive of this project that is not in the list: the one that was downloaded, brought back
[ $status, $body ] = $put( $appData, 'backup-example.com-'. $old. '.php', $archive );
check( 'an archive of this project, under the name of the download, is put in the list as it was: 200 with its id, its kind and its size', $status === 200 && ( $body['id'] ?? '' ) === $old && ( $body['kind'] ?? '' ) === 'manual' && ( $body['size'] ?? 0 ) === strlen( $archive ) );
check( '...byte for byte what was downloaded, and no temporary file beside it', is_file( $dir. '/'. $old. '.php' ) === true && (string) file_get_contents( $dir. '/'. $old. '.php' ) === $archive && $tmpFiles() === [] );
check( '...in the list and in the activity log', in_array( $old, array_column( ninoCall( $appData, $list )[1]['archives'], 'id' ), true ) === true && str_contains( $logLines(), 'Upload Backup '. $old ) === true );
check( '...and the answer says what the retention will do with it: it is older than the newest three of its kind', ( $body['drops'] ?? null ) === false && ( $body['keep'] ?? 0 ) === 3 );

// Whatever name it came with without the part the download puts before the id
unlink( $dir. '/'. $old. '.php' );
[ $status, $body ] = $put( $appData, $old. '.php', $archive );
check( 'the bare id is a name too', $status === 200 && ( $body['id'] ?? '' ) === $old );
unlink( $dir. '/'. $old. '.php' );

// A bytes-for-bytes round: downloaded, deleted, uploaded, restored, the project as it was
$round = $appData['/nino/backup/key'];
\Nino\Filesystem::putFileContent( $appData, '/text/probe.php', [ 'a' => 'before' ] );
$made = \Nino\Modules\Backups::now( $appData );
$bytes = (string) file_get_contents( $dir. '/'. $made. '.php' );
\Nino\Filesystem::putFileContent( $appData, '/text/probe.php', [ 'a' => 'after' ] );
unlink( $dir. '/'. $made. '.php' );
$_FILES = [];
[ $status ] = $put( $appData, 'backup-example.com-'. $made. '.php', $bytes );
$restored = $status === 200 ? ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $made ] ) : [ 0 ];
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
unset( $appData['./nino/filesystem/cache'] );
check( 'an archive that was downloaded, deleted and uploaded restores the project as it was', $status === 200 && $restored[0] === 200 && \Nino\Filesystem::getFileContent( $appData, '/text/probe.php', [] ) === [ 'a' => 'before' ] && $appData['/nino/backup/key'] === $round );

// The outbox of the mail is brought back from no archive: one that carries it - an older Nino's, one somebody put together - restores everything else
// and leaves the outbox of the project as it is
$outboxFile = \Nino\Filesystem::path( $appData, '/data/mail-queue.php' );
@mkdir( dirname( $outboxFile ), 0755, true );
file_put_contents( $outboxFile, 'LIVE' );
$foreign = ninoSandboxDir( $appData ). '/foreign-outbox.json';
file_put_contents( $foreign, 'ARCHIVE' );
// The texts of the mails of the owner: the project has one of its own, and the archive carries one with the same id and one more
$bodiesDir = \Nino\Filesystem::path( $appData, \Nino\MailQueue::BODIES );
@mkdir( $bodiesDir, 0755, true );
$liveBody = $bodiesDir. '/aaaaaaaaaaaaaaaa.php';
file_put_contents( $liveBody, 'LIVE' );
$foreignBody = ninoSandboxDir( $appData ). '/foreign-body.json';
file_put_contents( $foreignBody, 'ARCHIVE' );
file_put_contents( $foreignBody. '2', 'ARCHIVE' );
$foreignTar = ninoSandboxDir( $appData ). '/foreign.tar';
\Nino\Filesystem::putFileContent( $appData, '/text/probe.php', [ 'a' => 'archived' ] );
\Nino\Backup::pack( $appData, \Nino\Backup::manifest( $appData ) + [ $foreign => 'data/mail-queue.php', $foreignBody => 'data/.mail-bodies/aaaaaaaaaaaaaaaa.php', $foreignBody. '2' => 'data/.mail-bodies/bbbbbbbbbbbbbbbb.php' ], $foreignTar );
$forgedId = '2026-01-02-030405-manual';
\Nino\Filesystem::putFileContent( $appData, '/text/probe.php', [ 'a' => 'live' ] );
$_FILES = [];
[ $status ] = $put( $appData, 'backup-example.com-'. $forgedId. '.php', backupsStub( (string) file_get_contents( $foreignTar. '.gz' ), (string) base64_decode( (string) $appData['/nino/backup/key'], true ) ) );
$restored = $status === 200 ? ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $forgedId ] ) : [ 0 ];
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
unset( $appData['./nino/filesystem/cache'] );
check( 'an archive that carries the outbox of the mail is restored without it: the rest comes back, the outbox of the project is as it was', $restored[0] === 200 && \Nino\Filesystem::getFileContent( $appData, '/text/probe.php', [] ) === [ 'a' => 'archived' ] && (string) file_get_contents( $outboxFile ) === 'LIVE' );
check( '...and so does the folder of the texts of the mails of the owner, which a backup never carries and an archive that was put together may: the text of the project stays as it was and the ones of the archive are not brought in', (string) file_get_contents( $liveBody ) === 'LIVE' && is_file( $bodiesDir. '/bbbbbbbbbbbbbbbb.php' ) === false );
unlink( $outboxFile );
unlink( $liveBody );

// Names
$beforeIds = $ids();
foreach( [
	'notes.php', 'backup.php', 'x-2025-03-04-050607.php', '2025-03-04.txt', '2025-03-04.php.txt', '2025-03-04.PHP', '2025-03-04-0506.php', '2025-03-04-050607-auto.php', '2025-03-04-manual.php',
	'backup-example.com-pre-restore-2025-03-04-050607.php', 'pre-restore-2025-03-04-050607.php', 'backup-example.com-2025-03-04-050607-manual (1).php', '2025-13-04.php', '2025-02-30.php', '2025-03-04-250000.php', '2025-03-04-006100.php',
	'.2025-03-04.php', '-2025-03-04.php', '', "2025-03-04.php\0.txt",
] as $badName ) {
	[ $status, $body ] = $put( $appData, $badName, $archive );
	if( $status !== 400 || ( $body['code'] ?? '' ) !== 'backups_upload_name' )
		$badNamed = $badName. ' ('. $status. ')';
}
check( 'a name that is no id of a backup - a snapshot, a date that is none, a time that is none, another extension, another mark - is refused with a 400 and a code', ! isset( $badNamed ) && $ids() === $beforeIds && $tmpFiles() === [] );

[ $status ] = $put( $appData, '../../../'. $old. '.php', $archive );
check( 'a path in front of a good name is taken off it, and the archive lands in the directory of the archives and nowhere else', $status === 200 && is_file( $dir. '/'. $old. '.php' ) === true && is_file( $sandbox. '/'. $old. '.php' ) === false );
unlink( $dir. '/'. $old. '.php' );
[ $status ] = $put( $appData, '..\\..\\'. $old. '.php', $archive );
check( '...a path with the backslashes of another system too', $status === 200 && is_file( $dir. '/'. $old. '.php' ) === true );
unlink( $dir. '/'. $old. '.php' );

// Nothing is overwritten
$existing = (string) file_get_contents( $dir. '/'. $second. '.php' );
$other 		= backupsStub( 'x', $rawKey );
[ $status, $body ] = $put( $appData, $second. '.php', $other );
check( 'an archive that exists is not overwritten: 409 with a code and its id, and the file is as it was', $status === 409 && ( $body['code'] ?? '' ) === 'backups_upload_exists' && ( $body['params'] ?? [] ) === [ $second ] && (string) file_get_contents( $dir. '/'. $second. '.php' ) === $existing );
[ $status ] = $put( $appData, 'backup-other.example-'. $today. '.php', $archive );
check( '...whatever the host in front of the name says, and a daily one the same', $status === 409 && $tmpFiles() === [] );

// What php says of the upload
foreach( [ UPLOAD_ERR_INI_SIZE => [ 413, 'backups_too_large' ], UPLOAD_ERR_FORM_SIZE => [ 413, 'backups_too_large' ], UPLOAD_ERR_PARTIAL => [ 400, 'upload_partial' ], UPLOAD_ERR_NO_FILE => [ 400, 'upload_missing' ], UPLOAD_ERR_NO_TMP_DIR => [ 500, 'upload_server' ], UPLOAD_ERR_CANT_WRITE => [ 500, 'upload_server' ] ] as $error => $expected ) {
	[ $status, $body ] = $put( $appData, $old. '.php', $archive, $error );
	if( [ $status, $body['code'] ?? '' ] !== $expected )
		$errorWrong = $error. ' ('. $status. ' '. ( $body['code'] ?? '' ). ')';
}
check( 'what php says of an upload that did not arrive is said: too large is the way round it, the rest as the other upload panels say it, and nothing is written', ! isset( $errorWrong ) && is_file( $dir. '/'. $old. '.php' ) === false );
[ , $body ] = $put( $appData, $old. '.php', $archive, UPLOAD_ERR_INI_SIZE );
check( '...with the limit of the server in megabytes', is_array( $body['params'] ?? null ) === true && count( $body['params'] ) === 1 && is_numeric( $body['params'][0] ) === true );
unset( $_FILES['file'] );
[ $status, $body ] = ninoCall( $appData, $upload );
check( 'no file at all is a 400 as well', $status === 400 && ( $body['code'] ?? '' ) === 'upload_missing' );
$_FILES['file'] = [ 'name' => [ $old. '.php' ], 'tmp_name' => [ '/etc/passwd' ], 'error' => [ 0 ], 'size' => [ 1 ], 'type' => [ '' ] ];
[ $status ] = ninoCall( $appData, $upload );
check( 'a file entry that is a list, as a crafted request makes it, is no file', $status === 400 && $ids() === array_values( array_intersect( $ids(), $beforeIds ) ) );
unset( $_FILES['file'] );

// What is no archive
$notArchives = [
	'a program of php'							=> "<?php echo 'x'; system( \$_GET['c'] );\n",
	'an empty file'									=> '',
	'a stub without a payload'			=> \Nino\Admin\Recovery::STUB_PREFIX. \Nino\Admin\Recovery::STUB_SUFFIX,
	'a stub with a text in it'			=> \Nino\Admin\Recovery::STUB_PREFIX. 'this is not base64 at all, it is a sentence of some length.'. \Nino\Admin\Recovery::STUB_SUFFIX,
	'a stub that is not closed'			=> substr( $archive, 0, -3 ),
	'a stub with code after it'			=> $archive. "<?php system( 'id' );\n",
	'a stub with code before it'		=> "<?php system( 'id' ); ?>\n". $archive,
	'a stub that runs on after it'	=> substr( $archive, 0, -3 ). "'; system( 'id' ); //\n",
	'a stub with a quote in it'			=> substr_replace( $archive, "';system('id');//", strlen( \Nino\Admin\Recovery::STUB_PREFIX ) + 100, 0 ),
	'a stub with a line break in it'	=> substr_replace( $archive, "\n", strlen( \Nino\Admin\Recovery::STUB_PREFIX ) + 100, 0 ),
	'a stub with a space in it'			=> substr_replace( $archive, '    ', strlen( \Nino\Admin\Recovery::STUB_PREFIX ) + 100, 0 ),
	'a stub with padding in the middle' => substr_replace( $archive, '====', strlen( \Nino\Admin\Recovery::STUB_PREFIX ) + 100, 0 ),
	'a stub with a part cut off'		=> substr( $archive, 0, strlen( \Nino\Admin\Recovery::STUB_PREFIX ) + 137 ). \Nino\Admin\Recovery::STUB_SUFFIX,
	'the prefix of a stub of php'		=> "<?php exit; return '". substr( $archive, strlen( \Nino\Admin\Recovery::STUB_PREFIX ) ),
	'a gzip'												=> gzencode( 'hello' ),
	'a zip'													=> "PK\x03\x04". str_repeat( "\0", 200 ),
];

foreach( $notArchives as $what => $bytes ) {
	[ $status, $body ] = $put( $appData, $old. '.php', $bytes );
	if( $status !== 400 || ( $body['code'] ?? '' ) !== 'backups_upload_format' )
		$notWrong[] = $what. ' ('. $status. ' '. ( $body['code'] ?? '' ). ')';
}
check( 'what is not the stub of an archive - php, nothing, text, a stub opened or closed wrongly, code around it, a gzip - is refused with a 400 and the code of a format that is none', ! isset( $notWrong ) );
check( '...and nothing of it is in the directory, not even a temporary file', $ids() === $beforeIds && $tmpFiles() === [] && glob( $sandbox. '/*.php' ) === [] );

// An archive of the project's key that holds what is no archive
[ $status, $body ] = $put( $appData, $old. '.php', backupsStub( 'this is not gzip, whoever made it', $rawKey ) );
check( 'what the key opens to something that is no gzip is refused: 422, and not in the list', $status === 422 && ( $body['code'] ?? '' ) === 'backups_upload_format' && is_file( $dir. '/'. $old. '.php' ) === false && $tmpFiles() === [] );

// A change of one bit
$flipped = $archive;
$at = strlen( \Nino\Admin\Recovery::STUB_PREFIX ) + 400;
$flipped[$at] = $flipped[$at] === 'A' ? 'B' : 'A';
[ $status, $body ] = $put( $appData, $old. '.php', $flipped );
check( 'an archive that was changed after the download is refused, as it cannot be opened: 422 with the code of the key', $status === 422 && ( $body['code'] ?? '' ) === 'backups_upload_key' && is_file( $dir. '/'. $old. '.php' ) === false );

// Another project's archive
$foreign 			= backupsProject( 'backups-foreign' );
$foreignDir		= ninoSandboxDir( $foreign ). '/private/.backups';
$foreignArchive = (string) file_get_contents( $foreignDir. '/'. $today. '.php' );
check( 'the other project has a key of its own', $foreign['/nino/backup/key'] !== $appData['/nino/backup/key'] );
[ $status, $body ] = $put( $appData, 'backup-example.com-2025-05-05.php', $foreignArchive );
check( 'an archive of another project is refused with a clear code and is not in the list', $status === 422 && ( $body['code'] ?? '' ) === 'backups_upload_key' && is_file( $dir. '/2025-05-05.php' ) === false && $tmpFiles() === [] );
\Nino\Filesystem::removeDir( ninoSandboxDir( $foreign ) );

// A new project that was given the key of the old one - where the key is kept, in config.php, the copy beside the archives follows it - takes the old archives
$fresh = backupsProject( 'backups-fresh', (string) $appData['/nino/backup/key'] );
$freshDir = ninoSandboxDir( $fresh ). '/private/.backups';
[ $status, $body ] = $put( $fresh, 'backup-example.com-'. $second. '.php', $archive );
check( 'a new project that was given the key of this one takes its archive, and restores it', $status === 200 && ( $body['id'] ?? '' ) === $second && is_file( $freshDir. '/'. $second. '.php' ) === true
	&& ninoCall( $fresh, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $second ] )[0] === 200 );
\Nino\Filesystem::removeDir( ninoSandboxDir( $fresh ) );

// An archive that this server cannot open: one of format 1, which is read whole. One in chunks is opened with the memory of a chunk, whatever the limit says
$oldFormat = ninoSealedOld( (string) ninoUnsealed( $archive, $rawKey ), $rawKey );
$appData['./nino/backups/memory'] = memory_get_usage() + 1048576;
[ $status, $body ] = $put( $appData, $old. '.php', $oldFormat );
$chunked = $put( $appData, $old. '.php', $archive );
unset( $appData['./nino/backups/memory'] );
check( 'an archive of format 1 that the memory limit does not carry opening is refused before it is opened: 413 with the sizes, nothing in the directory', $status === 413 && ( $body['code'] ?? '' ) === 'backups_upload_memory' && count( $body['params'] ?? [] ) === 2 && $tmpFiles() === [] );
check( '...and an archive in chunks is taken under the same limit', $chunked[0] === 200 && is_file( $dir. '/'. $old. '.php' ) === true );
unlink( $dir. '/'. $old. '.php' );
file_put_contents( $dir. '/'. $old. '.php', $oldFormat );
$snapshotsBefore = glob( $dir. '/pre-restore-*' ) ?: [];
$appData['./nino/backups/memory'] = memory_get_usage() + 1048576;
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $old ] );
unset( $appData['./nino/backups/memory'] );
check( 'the restore of an archive of format 1 from the list is refused the same way where the limit does not carry it, and nothing is restored or made', $status === 413 && ( $body['code'] ?? '' ) === 'backups_upload_memory' && ( glob( $dir. '/pre-restore-*' ) ?: [] ) === $snapshotsBefore );
unlink( $dir. '/'. $old. '.php' );
[ $status ] = $put( $appData, $old. '.php', $oldFormat );
check( 'an archive of format 1 is taken where the limit carries it, and is listed like any other', $status === 200 && is_file( $dir. '/'. $old. '.php' ) === true );
unlink( $dir. '/'. $old. '.php' );

// A project that has no key: the workbench makes one with the first request, so the copy is gone only where backups are off
$keyPath = $sandbox. '/private/.auth/backup-key.php';
$keyCopy = (string) file_get_contents( $keyPath );
unlink( $keyPath );
$appData['/nino/admin/backups'] = false;
[ $status, $body ] = $put( $appData, $old. '.php', $archive );
unset( $appData['/nino/admin/backups'] );
file_put_contents( $keyPath, $keyCopy );
check( 'a project without the copy of its key cannot check an archive, and says so: 409', $status === 409 && ( $body['code'] ?? '' ) === 'backups_upload_nokey' && is_file( $dir. '/'. $old. '.php' ) === false );

// A backup or a restore that runs
$holder = $appData;
$holder['./nino/filesystem/locks'] = [];
\Nino\Filesystem::lockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );
[ $status, $body ] = $put( $appData, $old. '.php', $archive );
check( 'an upload while a backup or a restore runs is turned away: 409 busy, and nothing is written', $status === 409 && ( $body['code'] ?? '' ) === 'backups_busy' && is_file( $dir. '/'. $old. '.php' ) === false && $tmpFiles() === [] );
\Nino\Filesystem::unlockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );
[ $status ] = $put( $appData, $old. '.php', $archive );
check( '...and the lock is given back by every upload: the next one goes through', $status === 200 );
unlink( $dir. '/'. $old. '.php' );

// Who may upload
$beforeIds = $ids();
\Nino\Auth::logoutUser( $appData );
backupsFile( $appData, $old. '.php', $archive );
[ $status ] = ninoCall( $appData, $upload );
check( 'logged out an upload is a 401', $status === 401 && $ids() === $beforeIds );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'backups/upload' );
check( '...also through the dispatch of the workbench', $status === 401 && $ids() === $beforeIds );
ninoUser( $appData, 'usersonly@example.com', [ \Nino\Modules\Users\Admin::MANAGE_PERM ] );
[ $status ] = ninoCall( $appData, $upload );
check( 'an account without the permission of the Backups panel gets a 403, and nothing is written', $status === 403 && $ids() === $beforeIds && $tmpFiles() === [] );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'backups/upload' );
check( '...also through the dispatch of the workbench', $status === 403 && $ids() === $beforeIds );
\Nino\Auth::deleteUser( $appData, 'usersonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'backups/upload', 403 );
check( 'a request the csrf guard refused is not looked at', $status === 403 && $ids() === $beforeIds );
@unlink( $_FILES['file']['tmp_name'] );

// An account with the permission of the panel alone: the list, Back up now and a restore as before, no download and no upload
ninoUser( $appData, 'backupsonly@example.com', [ \Nino\Modules\Backups\Admin::MANAGE_PERM ] );
$beforeIds = $ids();
[ $status, $response ] = $ask( $appData, $second );
check( 'an account with the panel\'s permission and no full access is refused the download with a 403, and no file is named', $status === 403 && array_key_exists( 'file', $response ) === false );
[ $status, $body, $answered ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [ 'date' => $second ], 'backups/download' );
check( '...also through the dispatch of the workbench', $status === 403 && array_key_exists( 'file', $answered['/nino/http/response'] ) === false );
[ $status ] = $put( $appData, $old. '.php', $archive );
check( '...and the upload with a 403, nothing written', $status === 403 && $ids() === $beforeIds && $tmpFiles() === [] );
[ $status ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'backups/upload' );
check( '...also through the dispatch of the workbench', $status === 403 );
[ $status, $body ] = ninoCall( $appData, $list );
check( 'the list tells it is for an account without full access', $status === 200 && $body['full'] === false && count( $body['archives'] ) > 0 );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => '2001-02-03' ] );
check( '...a restore is answered as before: it is asked for the archive, not for full access', $status === 404 );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ] );
check( '...and Back up now goes through', $status === 200 );
\Nino\Auth::deleteUser( $appData, 'backupsonly@example.com' );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
check( 'an account with full access is told so', ninoCall( $appData, $list )[1]['full'] === true );

// Backups switched off: the upload changes no project, and puts an archive where a restore can use it
$appData['/nino/admin/backups'] = false;
[ $status ] = $put( $appData, $old. '.php', $archive );
unset( $appData['/nino/admin/backups'] );
check( 'while backups are switched off an archive can still be put back: a restore is what it is for', $status === 200 );
unlink( $dir. '/'. $old. '.php' );

// A feature that is on and whose manifest is refused stops a backup and a restore, which would lack its data: the download and the upload change nothing of the project
$modulesBefore = $appData['/nino/modules'];
$appData['/nino/modules'][] = '\\Nino\\Modules\\Probe';
$appData['./nino/features/all'] = [];
$appData['./nino/features/rejected'] = [ 'Probe' => 'cannot be included: RuntimeException: boom (line 1)' ];
$refusal = \Nino\Backup::featureRefusal( $appData );
[ $status ] = $put( $appData, $old. '.php', $archive );
check( 'while a feature that is on has a refused manifest the upload goes through', $refusal !== null && $status === 200 );
[ $status, $response ] = $ask( $appData, $second );
check( '...and so does the download', $status === 200 && ( $response['file'] ?? '' ) === $dir. '/'. $second. '.php' );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $old ] );
check( '...and the restore of the panel stays refused, with the reason of the backup', $status === 409 && is_file( $dir. '/'. $old. '.php' ) === true );
unset( $appData['./nino/features/rejected'], $appData['./nino/features/all'] );
$appData['/nino/modules'] = $modulesBefore;
unlink( $dir. '/'. $old. '.php' );


// --- Retention ----------------------------------------------------------------

echo "\nRetention\n";

// Three of each are kept; an upload does not delete, whatever it brings
foreach( [ '2020-01-01', '2020-01-02', '2020-01-03', '2020-01-04' ] as $day ) {
	[ $status, $body ] = $put( $appData, 'backup-example.com-'. $day. '.php', $archive );
	if( $status !== 200 )
		$dayFailed = $day;
}
$dailyNow = array_values( array_filter( $ids(), static fn( string $id ): bool => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $id ) === 1 ) );
check( 'an upload deletes nothing: of the daily kind there are more than the project keeps now', ! isset( $dayFailed ) && count( $dailyNow ) === 5 );
[ , $body ] = $put( $appData, '2019-12-31.php', $archive );
check( 'the answer says that an archive older than the newest of its kind the project keeps is the first to go, and how many that is', ( $body['drops'] ?? null ) === true && ( $body['keep'] ?? 0 ) === 3 );
$appData['/nino/admin/backups-daily'] = 10;
[ , $body ] = $put( $appData, '2019-12-30.php', $archive );
check( '...and that one that is among them is not', ( $body['drops'] ?? null ) === false && ( $body['keep'] ?? 0 ) === 10 );
$appData['/nino/admin/backups-daily'] = 3;

foreach( [ '2019-12-30-000001-manual', '2019-12-30-000002-manual', '2019-12-30-000003-manual' ] as $newer )
	$put( $appData, $newer. '.php', $archive );
[ , $body ] = $put( $appData, '2019-12-29-120000-manual.php', $archive );
check( 'an archive made by hand is counted among those of its own kind: with three newer ones it is over the limit', ( $body['kind'] ?? '' ) === 'manual' && ( $body['drops'] ?? null ) === true && ( $body['keep'] ?? 0 ) === 3 );
$appData['/nino/admin/backups-manual'] = 30;
[ , $body ] = $put( $appData, '2019-12-29-120001.php', $archive );
check( '...and with room for more it is not, and the older name is a time of day like the other', ( $body['drops'] ?? null ) === false && ( $body['keep'] ?? 0 ) === 30 && ( $body['kind'] ?? '' ) === 'manual' );
$appData['/nino/admin/backups-manual'] = 3;

$isHand = static fn( string $id ): bool => preg_match( '/^\d{4}-\d{2}-\d{2}-\d{6}/', $id ) === 1;
$handsBefore = array_values( array_filter( $ids(), $isHand ) );
$made = \Nino\Modules\Backups::now( $appData );
$hands = array_values( array_filter( $ids(), $isHand ) );
$days = array_values( array_filter( $ids(), static fn( string $id ): bool => preg_match( '/^\d{4}-\d{2}-\d{2}$/', $id ) === 1 ) );
sort( $days );

// The newest three by date and time of those there were and the new one, whichever names they have: what a backup in the same second as an earlier one replaces is one archive
$expected = array_values( array_unique( array_merge( $handsBefore, [ (string) $made ] ) ) );
usort( $expected, static fn( string $a, string $b ): int => strcmp( substr( $b, 0, 17 ), substr( $a, 0, 17 ) ) ?: strcmp( $b, $a ) );
$expected = array_slice( $expected, 0, 3 );
sort( $expected );
sort( $hands );
check( 'the next backup that succeeds deletes what is over the limit of each kind, the uploaded archives with the others', is_string( $made ) === true && $hands === $expected && in_array( $made, $hands, true ) === true
	&& count( $handsBefore ) > 3 && $days === [ '2020-01-03', '2020-01-04', $today ] );

$uploadLimit = ninoCall( $appData, $list )[1]['uploadLimit'] ?? null;
check( 'the list says how large an upload can be: the limit of php, 0 where it names none', $uploadLimit === \Nino\Images::limits()['php'] );

// A feature that is on and has no directory is another case: it has no manifest and owns nothing a run could name. The backup and the restore of the panel go through, and each says in a sentence that the feature is switched on and not installed
$appData['/nino/modules'][] = '\\Nino\\Modules\\Gone';
unset( $appData['./nino/features/all'], $appData['./nino/features/missing'], $appData['./nino/features/rejected'] );
ninoWarnings();
[ $status ] = $put( $appData, $old. '.php', $archive );
[ $nowStatus, $nowBody ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiNow' ] );
check( 'while a feature is on and its directory is not there, Back up now goes through and the answer says it in one sentence', $status === 200 && $nowStatus === 200 && is_string( $nowBody['id'] ?? null ) === true
	&& \Nino\Backup::featureRefusal( $appData ) === null && str_contains( (string) ( $nowBody['note'] ?? '' ), 'features/Gone' ) === true && str_contains( (string) $nowBody['note'], 'not installed' ) === true );
[ $status, $body ] = ninoCall( $appData, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $nowBody['id'] ] );
check( '...and so does the restore, with the same sentence', $status === 200 && str_contains( (string) ( $body['note'] ?? '' ), 'features/Gone' ) === true );
$appData['/nino/modules'] = $modulesBefore;
unset( $appData['./nino/features/rejected'], $appData['./nino/features/all'], $appData['./nino/features/missing'] );
@unlink( $dir. '/'. $old. '.php' );

ninoDone( $appData );
