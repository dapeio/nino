<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	admin-recovery-key-smoke.php	Dependency-free smoke test for restoring the archive of another project on
 *													recovery.php: the key that was typed - from the list and with the archive as a
 *													file -, who may type it (the door's own secret, never the panel), what a wrong
 *													one costs, that it is kept and written nowhere, and what an archive that was
 *													opened with a typed key must hold (data and pictures, never code). Runs against
 *													isolated sandbox directories, never touches the real project data.
 *
 *	Usage: php tests/admin-recovery-key-smoke.php
 */

require __DIR__. '/harness.php';

/** A project that is set up, with one developer account of its own whose password is the one given, and the archive of its first day */
function recoveryKeyProject( string $name, string $mail, string $password, bool $archive = true ): array {

	$appData = ninoSandbox( $name );

	$sandbox = ninoSandboxDir( $appData );
	mkdir( $sandbox. '/private', 0755, true );
	mkdir( $sandbox. '/public', 0755, true );
	mkdir( $sandbox. '/private/.backups', 0755, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/display'			=> true,
		'/nino/auth/maxtries'			=> 5,
		'/nino/auth/cooldown'			=> 3600,
		'/nino/locales/native'		=> $appData['/nino/locales/native'],
		'/nino/locales/available'	=> $appData['/nino/locales/available'],
	] );

	$appData['/nino/auth/roles'] = \Nino\Modules\Users\Roles::defaults( $appData );
	ninoUser( $appData, $mail, [ '/*' ], $password );
	\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ 'owner' => $name ] );
	\Nino\Admin\Recovery::set( $appData, 'recovery password of '. $name );

	if( $archive === true )
		\Nino\Modules\Backups::maybeRun( $appData );
	else
		\Nino\Modules\Backups::recoveryKey( $appData );

	return $appData;
}

/** What a browser hands php of a chosen file: the entry of $_FILES, over a file of the sandbox */
function recoveryKeyFile( array &$appData, string $bytes ): void {

	$path = ninoSandboxDir( $appData ). '/upload-'. bin2hex( random_bytes( 4 ) ). '.bin';
	file_put_contents( $path, $bytes );

	$_FILES['file'] = [ 'name' => 'backup-example.com-2026-11-15.php', 'type' => 'application/octet-stream', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen( $bytes ) ];
}

/** The stub of an archive of the given key around the given plain bytes - what the project's own writer makes of them */
function recoveryKeyStub( string $plain, string $rawKey ): string {
	return ninoSealed( $plain, $rawKey );
}

/** An archive of the key and the files given: [ name in the archive => bytes ], made the way a backup is - a tar, gzipped, encrypted, behind its stub */
function recoveryKeyArchive( array $files, string $rawKey ): string {

	$base = sys_get_temp_dir(). '/nino-recovery-key-'. bin2hex( random_bytes( 4 ) );
	$tar 	= new PharData( $base. '.tar' );

	foreach( $files as $name => $bytes )
		$tar->addFromString( $name, $bytes );

	$tar->compress( Phar::GZ );
	$gz = (string) file_get_contents( $base. '.tar.gz' );

	unset( $tar );
	Phar::unlinkArchive( $base. '.tar' );
	@unlink( $base. '.tar.gz' );
	@unlink( $base. '.tar' );

	return recoveryKeyStub( $gz, $rawKey );
}

/** Whether a key opens the archive in a file: the stub read by the reader of the suite, and every tag of it accepted */
function recoveryKeyOpens( string $file, string $rawKey ): bool {
	return ninoUnsealed( (string) file_get_contents( $file ), $rawKey ) !== false;
}

/** Every file below a directory that holds the text, as a list of paths */
function recoveryKeyFindIn( string $root, string $needle ): array {

	$found = [];

	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file )
		if( $file->isFile() === true && str_contains( (string) file_get_contents( $file->getPathname() ), $needle ) === true )
			$found[] = substr( $file->getPathname(), strlen( $root ) );

	return $found;
}

$recovery = [ \Nino\Admin\Recovery::class, 'handlePost' ];

$call = static function( array &$app, string $action, array $data, bool $open = true ) use ( $recovery ): array {

	if( $open === true )
		\Nino\Runtime::setSessionValue( $app, \Nino\Admin\Recovery::SESSION_KEY, true );
	else
		\Nino\Runtime::unsetSessionValue( $app, \Nino\Admin\Recovery::SESSION_KEY );

	return ninoCall( $app, $recovery, $data, $action );
};

$wrongKey = static fn(): string => base64_encode( random_bytes( 32 ) );

echo "Two projects: the one that was lost, and the one that was installed in its place\n";

$old 				= recoveryKeyProject( 'recovery-key-old', 'owner@old.example', 'the old owner password' );
$oldDir 		= ninoSandboxDir( $old );
$oldKey 		= (string) $old['/nino/backup/key'];
$oldRaw 		= (string) base64_decode( $oldKey );
$oldFile 		= $oldDir. '/private/.backups/'. date( 'Y-m-d' ). '.php';
$oldArchive = (string) file_get_contents( $oldFile );
// The name it has in the list of the new project: not today's, which the new project makes itself
$oldId 			= '2026-01-02';

$new 				= recoveryKeyProject( 'recovery-key-new', 'owner@new.example', 'the new owner password', false );
$newDir 		= ninoSandboxDir( $new );
$newKey 		= (string) $new['/nino/backup/key'];

check( 'the two have keys of their own, and the archive of the old one is no file of the new one', $oldKey !== $newKey && is_file( $newDir. '/private/.backups/'. $oldId. '.php' ) === false && $oldArchive !== '' );

$state = static fn( array &$app ): array => [
	'owners' => array_keys( \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/auth/user'] ?? [] ),
	'key' 	 => \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/backup/key'] ?? null,
	'text' 	 => \Nino\Filesystem::getFileContent( $app, '/text/global.php', [] )['owner'] ?? null,
];
$newBefore = $state( $new );
$snapshots = static fn( string $dir ): array => glob( $dir. '/private/.backups/pre-restore-*.php' ) ?: [];
$temps 		 = static fn( string $dir ): array => glob( $dir. '/private/.backups/*.tmp' ) ?: [];


// --- Who may type a key ----------------------------------------------------

echo "\nThe door\n";

recoveryKeyFile( $new, $oldArchive );
[ $status ] = $call( $new, 'recovery/restore-file', [ 'key' => $oldKey ], false );
check( 'an archive from a file is restored behind the secret of recovery.php only: 401 without it, and nothing is written', $status === 401 && $state( $new ) === $newBefore && $snapshots( $newDir ) === [] );
copy( $oldFile, $newDir. '/private/.backups/'. $oldId. '.php' );
[ $status ] = $call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => $oldKey ], false );
check( '...and so is a restore from the list with a key', $status === 401 && $state( $new ) === $newBefore );
unlink( $newDir. '/private/.backups/'. $oldId. '.php' );

$panel = \Nino\Modules\Backups\Admin::actions();
check( 'the panel of the workbench has the five actions it had and none that takes a key', array_keys( $panel ) === [ 'backups/list', 'backups/restore', 'backups/now', 'backups/download', 'backups/upload' ] );
[ $status, $body ] = ninoCall( $new, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $oldId, 'key' => $oldKey ] );
check( '...a restore of the panel does not open an archive of another project even with a key posted: 404 for a name that is not there', $status === 404 && $state( $new ) === $newBefore );
copy( $oldFile, $newDir. '/private/.backups/'. $oldId. '.php' );
[ $status, $body ] = ninoCall( $new, [ \Nino\Modules\Backups\Admin::class, 'apiRestore' ], [ 'date' => $oldId, 'key' => $oldKey ] );
check( '...and where the archive is there it is refused with the code that says to go to recovery.php, and nothing is restored', $status === 422 && ( $body['code'] ?? '' ) === 'backups_key' && $state( $new ) === $newBefore && $snapshots( $newDir ) === [] );
unlink( $newDir. '/private/.backups/'. $oldId. '.php' );


// --- The key as it is typed ------------------------------------------------

echo "\nThe key as typed\n";

recoveryKeyFile( $new, $oldArchive );
foreach( [ 'abc', str_repeat( 'A', 43 ). '=', substr( $oldKey, 0, 43 ), $oldKey. 'A', '-_'. substr( $oldKey, 2 ), '<script>', "\x00". $oldKey ] as $bad ) {
	[ $status, $body ] = $call( $new, 'recovery/restore-file', [ 'key' => $bad ] );
	$badAnswers[] = $status. ( $body['code'] ?? '' );
}
check( 'what is no key - too short, too long, not base64, the url-safe alphabet, markup, a NUL byte - is a 400 with the code of the format; a run of As is a key of the right shape and is tried', array_slice( $badAnswers, 0, 1 ) === [ '400backups_key_format' ] && $badAnswers[1] === '422backups_key_wrong'
	&& array_slice( $badAnswers, 2 ) === array_fill( 0, 5, '400backups_key_format' ) );
check( '...and nothing was written', $state( $new ) === $newBefore && $snapshots( $newDir ) === [] && $temps( $newDir ) === [] );

recoveryKeyFile( $new, $oldArchive );
[ $status, $body ] = $call( $new, 'recovery/restore-file', [] );
check( 'no key at all with a file is a 400 that says so', $status === 400 && ( $body['code'] ?? '' ) === 'backups_key_missing' && $state( $new ) === $newBefore );

$_FILES = [];
[ $status, $body ] = $call( $new, 'recovery/restore-file', [ 'key' => $oldKey ] );
check( 'no file is a 400 with the code of a missing upload', $status === 400 && ( $body['code'] ?? '' ) === 'upload_missing' );

recoveryKeyFile( $new, $oldArchive );
$_FILES['file']['error'] = UPLOAD_ERR_INI_SIZE;
[ $status, $body ] = $call( $new, 'recovery/restore-file', [ 'key' => $oldKey ] );
check( 'a file php turned away for its size is a 413 with the code of the limit', $status === 413 && ( $body['code'] ?? '' ) === 'upload_too_large' && $state( $new ) === $newBefore );


// --- A wrong key and what it costs -------------------------------------------

echo "\nA wrong key\n";

copy( $oldFile, $newDir. '/private/.backups/'. $oldId. '.php' );
[ $status, $body ] = $call( $new, 'recovery/restore', [ 'date' => $oldId ] );
check( 'an archive of another project from the list without a key: 422, the key of this project does not open it, and the code says it', $status === 422 && ( $body['code'] ?? '' ) === 'backups_key' && $state( $new ) === $newBefore );

$typed = $wrongKey();
[ $status, $body ] = $call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => $typed ] );
check( 'with a key that is not its key: 422, and the code of a wrong key', $status === 422 && ( $body['code'] ?? '' ) === 'backups_key_wrong' && $state( $new ) === $newBefore && $snapshots( $newDir ) === [] );
check( '...the answer does not hold the key', str_contains( json_encode( $body ), $typed ) === false );
check( '...and the key of the project itself is not the key of its own archive either: a typed key is the only one tried', $call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => $newKey ] )[1]['code'] === 'backups_key_wrong' );

recoveryKeyFile( $new, $oldArchive );
[ $status, $body ] = $call( $new, 'recovery/restore-file', [ 'key' => $wrongKey() ] );
check( 'from a file the same: a wrong key is a 422 with the code of a wrong key, and the file is gone from the archive directory', $status === 422 && ( $body['code'] ?? '' ) === 'backups_key_wrong' && $temps( $newDir ) === [] && $state( $new ) === $newBefore );

// The counter: ten tries an hour, for the whole door
\Nino\Feature::forget( $new, 'recovery-key', 'recovery' );
$answers = [];
for( $try = 0; $try < 12; $try++ )
	$answers[] = $call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => $wrongKey() ] )[0];
check( 'the tries of a typed key are counted: ten are tried, the eleventh and what follows is a 429', array_slice( $answers, 0, 10 ) === array_fill( 0, 10, 422 ) && array_slice( $answers, 10 ) === [ 429, 429 ] );
[ $status, $body ] = $call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => $oldKey ] );
check( '...and the right key is not tried either while the counter is full: 429, nothing restored', $status === 429 && ( $body['code'] ?? '' ) === 'backups_key_tries' && $state( $new ) === $newBefore && $snapshots( $newDir ) === [] );
recoveryKeyFile( $new, $oldArchive );
[ $status ] = $call( $new, 'recovery/restore-file', [ 'key' => $oldKey ] );
check( '...with the archive as a file as well', $status === 429 && $temps( $newDir ) === [] );
[ $status ] = $call( $new, 'recovery/restore', [ 'date' => $oldId ] );
check( '...but a restore without a typed key is no try of a key: the archive of this project is not held back', $status === 422 );
\Nino\Feature::forget( $new, 'recovery-key', 'recovery' );

$call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => 'not a key' ] );
check( 'a key of the wrong shape costs no try', \Nino\Feature::spent( $new, 'recovery-key', 'recovery', 1 ) === false );
$call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => $wrongKey() ] );
check( '...and a key of the right shape does', \Nino\Feature::spent( $new, 'recovery-key', 'recovery', 1 ) === true );
\Nino\Feature::forget( $new, 'recovery-key', 'recovery' );


// --- The restore from the list, with the key -----------------------------------

echo "\nA restore from the list with the key\n";

[ $status, $body ] = $call( $new, 'recovery/restore', [ 'date' => $oldId, 'key' => "  ". substr( $oldKey, 0, 20 ). "\n". substr( $oldKey, 20 ). " " ] );
$after = $state( $new );
check( 'the key of the old project restores its archive - spaces and a line break in what was copied are no matter', $status === 200 && ( $body['restoredDate'] ?? '' ) === $oldId );
check( '...the project is the old one: its account, its key, its text', $after['owners'] === [ 'owner@old.example' ] && $after['key'] === $oldKey && $after['text'] === 'recovery-key-old' );
check( '...and the account of the old project signs in with the password it had', password_verify( 'the old owner password', \Nino\Filesystem::getFileContent( $new, '/config.php', [] )['/nino/auth/user']['owner@old.example']['pw'] ?? '' ) === true );
check( '...a snapshot of the state before was made, and the secret of recovery.php is the one of the new project', count( $snapshots( $newDir ) ) === 1 && password_verify( 'recovery password of recovery-key-new', (string) \Nino\Admin\Recovery::hash( $new ) ) === true );
$snapshot = $snapshots( $newDir )[0] ?? '';
check( '...the snapshot is made with the key the new project had, which is the one that is no longer in the project afterwards - and not with the key that was typed', $snapshot !== '' && recoveryKeyOpens( $snapshot, (string) base64_decode( $newKey ) ) === true && recoveryKeyOpens( $snapshot, $oldRaw ) === false );
check( '...and the snapshot can be restored with that key typed in, which brings the new project back: its account is there again', $call( $new, 'recovery/restore', [ 'date' => basename( $snapshot, '.php' ), 'key' => $newKey ] )[0] === 200 && in_array( 'owner@new.example', $state( $new )['owners'], true ) === true && $state( $new )['key'] === $newKey );
check( '...and the counter was let go', \Nino\Feature::spent( $new, 'recovery-key', 'recovery', 1 ) === false );


// --- An archive from a file --------------------------------------------------

echo "\nAn archive from a file\n";

$third 		= recoveryKeyProject( 'recovery-key-third', 'owner@third.example', 'the third owner password', false );
$thirdDir = ninoSandboxDir( $third );
$thirdBefore = $state( $third );

recoveryKeyFile( $third, $oldArchive );
[ $status, $body ] = $call( $third, 'recovery/restore-file', [ 'key' => $oldKey ] );
$after = $state( $third );
check( 'the archive and its key in one request restore the old project on a new one, with no archive of it in the directory before', $status === 200 && $after['owners'] === [ 'owner@old.example' ] && $after['key'] === $oldKey && $after['text'] === 'recovery-key-old' );
check( '...the file is not kept: the directory holds a snapshot of the state before and nothing else that was not there', $temps( $thirdDir ) === [] && count( $snapshots( $thirdDir ) ) === 1 && array_map( 'basename', glob( $thirdDir. '/private/.backups/*.php' ) ) === array_map( 'basename', $snapshots( $thirdDir ) ) );
check( '...the answer names no key', str_contains( json_encode( $body ), $oldKey ) === false && str_contains( json_encode( $body ), $oldRaw ) === false );

// The key is kept nowhere but where the restore put it: config.php, which is the archive's own
$keyFiles = recoveryKeyFindIn( $thirdDir, $oldKey );
check( 'the typed key is in config.php, which the archive brought with it, and in no other file of the project: no log, no counter, no session, no copy', $keyFiles === [ '/private/config.php' ] );
$rawFiles = recoveryKeyFindIn( $thirdDir, $oldRaw );
check( '...and its raw bytes are in none', $rawFiles === [] );
$lines = implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $third, 40 ) );
check( 'the activity log says that a restore was made, by the door and with no key', str_contains( $lines, 'Restore Backup uploaded archive' ) === true && str_contains( $lines, 'recovery.php' ) === true && str_contains( $lines, $oldKey ) === false );

// A wrong key with the file: nothing, and the key is not in the log either
$fourth = recoveryKeyProject( 'recovery-key-fourth', 'owner@fourth.example', 'the fourth owner password', false );
$fourthDir = ninoSandboxDir( $fourth );
$typed = $wrongKey();
recoveryKeyFile( $fourth, $oldArchive );
[ $status, $body ] = $call( $fourth, 'recovery/restore-file', [ 'key' => $typed ] );
check( 'a wrong key with a file: 422, nothing restored, no key in the answer, the logs or any file', $status === 422 && $state( $fourth )['owners'] === [ 'owner@fourth.example' ] && recoveryKeyFindIn( $fourthDir, $typed ) === []
	&& str_contains( json_encode( $body ), $typed ) === false && str_contains( implode( "\n", \Nino\Modules\Logs\Admin::recentLines( $fourth, 40 ) ), $typed ) === false );

// What is no stub
recoveryKeyFile( $fourth, '<?php echo 1;' );
[ $status, $body ] = $call( $fourth, 'recovery/restore-file', [ 'key' => $oldKey ] );
check( 'a file that is no archive is refused with the code of a format that is none, and nothing of it stays', $status === 400 && ( $body['code'] ?? '' ) === 'backups_upload_format' && $temps( $fourthDir ) === [] );

// A restore while another runs
$holder = $fourth;
$holder['./nino/filesystem/locks'] = [];
\Nino\Filesystem::lockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );
recoveryKeyFile( $fourth, $oldArchive );
[ $status, $body ] = $call( $fourth, 'recovery/restore-file', [ 'key' => $oldKey ] );
\Nino\Filesystem::unlockFile( $holder, \Nino\Modules\Backups::LOCK_PATH );
check( 'while a backup or a restore runs it is a 409, the same as for the list, and nothing is written', $status === 409 && ( $body['code'] ?? '' ) === 'backups_busy' && $temps( $fourthDir ) === [] && $state( $fourth )['owners'] === [ 'owner@fourth.example' ] );


// --- What an archive with a typed key may hold ---------------------------------

echo "\nWhat an archive that was opened with a typed key may hold\n";

$data = static fn( mixed $value ): string => '<?php return '. var_export( $value, true ). ';';
$goodFiles = [
	'config.php' 		=> $data( [ '/nino/backup/key' => $oldKey, '/nino/auth/user' => [ 'owner@old.example' => [ 'pw' => '$2y$10$abc', 'status' => 1, 'sessions' => [], 'perms' => [ '/*' ] ] ] ] ),
	'nino.php' 			=> '<?php return '. var_export( [ 'nino' => \Nino\VERSION, 'features' => [] ], true ). ';',
	'text/global.php' => $data( [ 'owner' => 'crafted', 'odd' => "quote ' backslash \\ nul \0 line\nbreak umlaut ä emoji 😀", 'numbers' => [ 1, -2, 3.5, -0.25, 1.0E+25, 0, 1e-7, PHP_INT_MAX ], 'flags' => [ true, false, null ], 'nested' => [ [ [ 'deep' => [ 'x' => 'y' ] ] ] ], -5 => 'negative key', 'empty' => [] ] ),
	'data/logs.2026-11.php' => $data( array_fill( 0, 50, [ 'time' => 1700000000, 'line' => 'GET /x?a=1&b=2 "quoted"' ] ) ),
	'images/elements/a/b.800x600.webp' => 'RIFFxxxxWEBP',
	'images/logo.png' => "\x89PNG\r\n",
	'images/template/page-home/hero/background.svg' => "<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 10 10\">\n\t<defs><linearGradient id=\"sky\" x1=\"0\" y1=\"0\" x2=\"0\" y2=\"1\"><stop offset=\"0\" stop-color=\"#d9dde3\"/></linearGradient></defs>\n\t<rect width=\"10\" height=\"10\" fill=\"url(#sky)\"/><path d=\"M0 0L5 5\"/>\n</svg>\n",
	'images/styled.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><style>.a{fill:url(#g)} .b{fill:red}</style><linearGradient id="g"/><rect class="a" style="stroke:url( #g )"/></svg>',
	'elements/typed.php' => "<?php\ndeclare(strict_types=1);\n\n// a type of its own\n# and a note\n/* and a block\n   of notes */\nreturn [ // inline\n\t'a' => 1, /* here */ 'b' => [ 'c' => 'd' ], # there\n];\n// last words\n",
	'assets/theme.css' => ':root{--a:1}',
	'templates/page-x.tpl' => '<h1>x</h1>',
	'blocks/hero/block.json' => '{"category":"intro"}',
	'blocks/hero/text/en_US.json' => '{"title":"Hero"}',
	'themes/mine/theme.json' => '{"key":"mine"}',
	'themes/mine/blocks/hero/block.tpl' => '[section-wrap]',
	'fonts/inter/400.woff2' => 'wOF2',
	'fonts/inter/font.json' => '{"family":"Inter"}',
	'fonts/inter/LICENSE.txt' => 'SIL Open Font License',
];
$crafted = static fn( array $extra = [], array $without = [] ): string => recoveryKeyArchive( array_diff_key( $extra + $goodFiles, array_flip( $without ) ), $oldRaw );

$project = static function( string $name ) use ( $call ): array {
	$app = recoveryKeyProject( $name, 'owner@'. $name. '.example', 'a password for '. $name, false );
	return [ $app, ninoSandboxDir( $app ) ];
};

[ $fifth, $fifthDir ] = $project( 'recovery-key-fifth' );
recoveryKeyFile( $fifth, $crafted() );
[ $status, $body ] = $call( $fifth, 'recovery/restore-file', [ 'key' => $oldKey ] );
check( 'a crafted archive of nothing but data and pictures - strings with quotes, NUL bytes, umlauts, numbers of every shape, null, nesting, a negative key - is restored: the check is not in the way of what Nino writes', $status === 200 && ( \Nino\Filesystem::getFileContent( $fifth, '/text/global.php', [] )['odd'] ?? '' ) === "quote ' backslash \\ nul \0 line\nbreak umlaut ä emoji 😀" );
check( '...with the blocks of the project and the installed fonts in it: they come back where the project keeps them, private/blocks/ and public/fonts/,',
	is_file( $fifthDir. '/private/blocks/hero/text/en_US.json' ) === true && file_get_contents( $fifthDir. '/private/blocks/hero/block.json' ) === '{"category":"intro"}'
	&& file_get_contents( $fifthDir. '/private/themes/mine/theme.json' ) === '{"key":"mine"}' && file_get_contents( $fifthDir. '/private/themes/mine/blocks/hero/block.tpl' ) === '[section-wrap]' && is_dir( $fifthDir. '/themes' ) === false
	&& file_get_contents( $fifthDir. '/public/fonts/inter/400.woff2' ) === 'wOF2' && file_get_contents( $fifthDir. '/public/fonts/inter/LICENSE.txt' ) === 'SIL Open Font License' && is_dir( $fifthDir. '/blocks' ) === false );

$marker = sys_get_temp_dir(). '/nino-recovery-key-ran-'. bin2hex( random_bytes( 4 ) );
$code = "<?php file_put_contents( '$marker', 'ran' ); return [];";
$refused = [
	'nino.php that is code' 															=> [ [ 'nino.php' => $code ], 'code' ],
	'config.php that is code' 														=> [ [ 'config.php' => $code ], 'code' ],
	'a string that is called' 														=> [ [ 'config.php' => "<?php return 'system'('id');" ], 'code' ],
	'an array that is called' 														=> [ [ 'config.php' => "<?php return [ 'system', 'id' ]();" ], 'code' ],
	'an array() that is called' 													=> [ [ 'text/x.php' => "<?php return array ( 'system' ) ( 'id' );" ], 'code' ],
	'a constant that is a call' 													=> [ [ 'text/x.php' => "<?php return [ phpinfo() ];" ], 'code' ],
	'a name' 																							=> [ [ 'text/x.php' => "<?php return [ PHP_VERSION ];" ], 'code' ],
	'a class constant' 																		=> [ [ 'text/x.php' => "<?php return [ \\Nino\\Filesystem::CONTENT_DIR ];" ], 'code' ],
	'a variable' 																					=> [ [ 'text/x.php' => "<?php return [ \$_GET ];" ], 'code' ],
	'interpolation' 																			=> [ [ 'text/x.php' => "<?php return [ \"{\$_GET['a']}\" ];" ], 'code' ],
	'a heredoc' 																					=> [ [ 'text/x.php' => "<?php return <<<X\nx\nX;" ], 'code' ],
	'a statement before the return' 											=> [ [ 'text/x.php' => "<?php echo 1; return [];" ], 'code' ],
	'a statement after the semicolon' 										=> [ [ 'text/x.php' => "<?php return []; echo 1;" ], 'code' ],
	'a closing tag and html' 															=> [ [ 'text/x.php' => "<?php return []; ?>\n<?= 1 ?>" ], 'code' ],
	'a line comment that a carriage return ends and code behind it' => [ [ 'text/x.php' => "<?php return []; // hi\r system('id');" ], 'code' ],
	'a line comment that a closing tag ends' 							=> [ [ 'text/x.php' => "<?php // hi ?> <?php system('id'); return [];" ], 'code' ],
	'a hash comment that is an attribute' 								=> [ [ 'text/x.php' => "<?php #[Attr]\nreturn [];" ], 'code' ],
	'a second declare' 																		=> [ [ 'text/x.php' => "<?php declare(strict_types=1); declare(strict_types=1); return [];" ], 'code' ],
	'a declare of another kind' 													=> [ [ 'text/x.php' => "<?php declare(ticks=1); return [];" ], 'code' ],
	'a declare after the return' 													=> [ [ 'text/x.php' => "<?php return []; declare(strict_types=1);" ], 'code' ],
	'an unterminated block comment' 											=> [ [ 'text/x.php' => "<?php /* hi return [];" ], 'code' ],
	'a comment that hides nothing: code after it' 				=> [ [ 'text/x.php' => "<?php /* hi */ system('id'); return [];" ], 'code' ],
	'an svg that imports css' 														=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><style>@import "//host/x.css";</style></svg>' ], 'drawing' ],
	'an svg whose css fetches' 														=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><style>rect{fill:url(//host/x)}</style></svg>' ], 'drawing' ],
	'an svg whose css fetches in cdata' 									=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><style><![CDATA[ rect{background:url( "https://host/x" )} ]]></style></svg>' ], 'drawing' ],
	'an svg whose style attribute fetches' 								=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:url(//host/x)"/></svg>' ], 'drawing' ],
	'an svg whose style attribute fetches in single quotes' => [ [ 'images/x.svg' => "<svg xmlns=\"http://www.w3.org/2000/svg\"><rect style='fill:URL(x)'/></svg>" ], 'drawing' ],
	'an svg whose css writes url as an escape' 						=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><style>rect{fill:u\\72l(//host/x)}</style></svg>' ], 'drawing' ],
	'an svg whose css writes url as an entity' 						=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:&#117;rl(//host/x)"/></svg>' ], 'drawing' ],
	'an svg with a font face' 														=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><style>@font-face{font-family:a;src:local(a)}</style></svg>' ], 'drawing' ],
	'a backtick' 																					=> [ [ 'text/x.php' => "<?php return [ `id` ];" ], 'code' ],
	'an attribute' 																				=> [ [ 'text/x.php' => "<?php return #[Attr]\n[];" ], 'code' ],
	'no php tag at all' 																	=> [ [ 'data/x.php' => "plain text" ], 'code' ],
	'a php file in data/ in capitals' 										=> [ [ 'data/x.PHP' => $code ], 'code' ],
	'a php5 file' 																				=> [ [ 'data/x.php5' => $code ], 'code' ],
	'a phtml file' 																				=> [ [ 'data/x.phtml' => $code ], 'code' ],
	'a php file among the pictures' 											=> [ [ 'images/shell.php' => $code ], 'no picture' ],
	'a php file with a picture\'s name behind it' 				=> [ [ 'images/shell.php.png' => $code ], 'no picture' ],
	'a picture\'s name behind a php one' 									=> [ [ 'images/shell.png.php' => $code ], 'no picture' ],
	'a file of the webserver among the pictures' 					=> [ [ 'images/.htaccess' => "AddType application/x-httpd-php .png" ], 'no picture' ],
	'a hidden picture' 																		=> [ [ 'images/.x.png' => 'x' ], 'no picture' ],
	'a script among the pictures' 												=> [ [ 'images/x.html' => '<script>1</script>' ], 'no picture' ],
	'an svg with an event attribute' 											=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>' ], 'drawing' ],
	'an svg with an event attribute in single quotes' 		=> [ [ 'images/x.svg' => "<svg xmlns='http://www.w3.org/2000/svg'><rect onmouseover='alert(1)'/></svg>" ], 'drawing' ],
	'an svg with a script' 																=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' ], 'drawing' ],
	'an svg with a script in capitals' 										=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><SCRIPT >alert(1)</SCRIPT></svg>' ], 'drawing' ],
	'an svg that is html around' 													=> [ [ 'images/x.svg' => '<html><svg xmlns="http://www.w3.org/2000/svg"/></html>' ], 'drawing' ],
	'an svg with foreignObject' 													=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><iframe src="x"/></foreignObject></svg>' ], 'drawing' ],
	'an svg with a link to another document' 							=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="https://example.com"><rect/></a></svg>' ], 'drawing' ],
	'an svg with a javascript link, entity-written' 			=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><a href="&#106;avascript:alert(1)"><rect/></a></svg>' ], 'drawing' ],
	'an svg that animates a link' 												=> [ [ 'images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><a><animate attributeName="href" values="x"/></a></svg>' ], 'drawing' ],
	'an svg with an entity' 															=> [ [ 'images/x.svg' => '<!DOCTYPE svg [ <!ENTITY a "b"> ]><svg xmlns="http://www.w3.org/2000/svg"/>' ], 'drawing' ],
	'a file named svg that is no svg' 										=> [ [ 'images/x.svg' => 'plain text' ], 'drawing' ],
	'a picture without an extension' 											=> [ [ 'images/x' => 'x' ], 'no picture' ],
	'a php file below a picture directory' 								=> [ [ 'images/elements/a/shell.phar' => $code ], 'no picture' ],
	'a php file among the blocks' 												=> [ [ 'blocks/hero/shell.php' => $code ], 'no part of a block' ],
	'a php file among the themes' 												=> [ [ 'themes/mine/shell.php' => $code ], 'no part of a theme' ],
	'a script name behind the extension in a theme' 			=> [ [ 'themes/mine/x.php.json' => $code ], 'no part of a theme' ],
	'a page, a script and a user.ini among the themes' 		=> [ [ 'themes/mine/a.html' => '<p>x</p>', 'themes/mine/a.js' => 'alert(1)', 'themes/mine/user.ini' => 'x=1' ], 'no part of a theme' ],
	'a hidden file among the themes' 											=> [ [ 'themes/mine/.htaccess' => 'x' ], 'no part of a theme' ],
	'an svg with a script among the themes' 							=> [ [ 'themes/mine/blocks/hero/images/x.svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>' ], 'drawing' ],
	'a php file among the fonts' 													=> [ [ 'fonts/inter/shell.php' => $code ], 'no part of a font' ],
	'a script name behind the extension of a font' 			=> [ [ 'fonts/inter/shell.php.woff2' => $code, 'fonts/inter/x.phtml.txt' => $code, 'fonts/inter/x.php5.ttf' => $code ], 'no part of a font' ],
	'a file of the webserver among the fonts' 						=> [ [ 'fonts/.htaccess' => 'AddType application/x-httpd-php .woff2' ], 'no part of a font' ],
];
$notRefused = [];
foreach( $refused as $what => [ $extra, $says ] ) {
	[ $app, $dir ] = $project( 'recovery-key-bad' );
	recoveryKeyFile( $app, $crafted( $extra ) );
	[ $status, $body ] = $call( $app, 'recovery/restore-file', [ 'key' => $oldKey ] );
	$intact = $state( $app )['owners'] === [ 'owner@recovery-key-bad.example' ] && $snapshots( $dir ) === [] && $temps( $dir ) === [] && is_file( $marker ) === false
		&& is_file( $dir. '/public/images/shell.php' ) === false && is_file( $dir. '/public/images/.htaccess' ) === false;
	if( $status !== 422 || str_contains( (string) ( $body['error'] ?? '' ), $says ) === false || $intact === false )
		$notRefused[] = $what. ' ('. $status. ' '. ( $body['error'] ?? '' ). ( $intact === true ? '' : ', project changed' ). ')';
	\Nino\Filesystem::removeDir( $dir );
}
check( 'an archive that holds code - in nino.php or config.php, a call, a name, a variable, interpolation, a heredoc, a statement around the return, a closing tag, a comment, a php file in any spelling, a file that a webserver runs or reads among the pictures, the blocks or the fonts - is refused with a 422 that names the file, before a snapshot, before nino.php is read and before anything is copied'. ( $notRefused === [] ? '' : ' - not: '. implode( '; ', $notRefused ) ), $notRefused === [] );
check( '...and nothing of it ran', is_file( $marker ) === false );

// The same code in the list: an archive that was put there by SFTP and opened with a typed key is judged the same
[ $app, $dir ] = $project( 'recovery-key-list' );
file_put_contents( $dir. '/private/.backups/2026-11-14.php', $crafted( [ 'nino.php' => $code ] ) );
[ $status, $body ] = $call( $app, 'recovery/restore', [ 'date' => '2026-11-14', 'key' => $oldKey ] );
check( 'from the list the same: an archive with code in it is refused when it is opened with a typed key', $status === 422 && str_contains( (string) ( $body['error'] ?? '' ), 'code' ) === true && is_file( $marker ) === false && $snapshots( $dir ) === [] );
\Nino\Filesystem::removeDir( $dir );

// Files of real projects: the element types of an install unit and of the catalogue's features, the owner's own, a text of a feature with its declare
$corpus = __DIR__. '/fixtures/backup-corpus';
$staging = sys_get_temp_dir(). '/nino-recovery-key-corpus-'. bin2hex( random_bytes( 4 ) );
mkdir( $staging, 0755, true );
foreach( [ 'elements', 'text' ] as $part )
	mkdir( $staging. '/'. $part, 0755, true );
$real = [];
foreach( array_merge( glob( $corpus. '/elements/*.php' ), glob( $corpus. '/text/*.php' ) ) as $file ) {
	copy( $file, $staging. '/'. basename( dirname( $file ) ). '/'. basename( $file ) );
	$real[] = basename( $file );
}
check( 'the check takes real files: '. count( $real ). ' of an install unit, of features of the catalogue and of a live project, with declare, comments at the head and between the lines', count( $real ) >= 11 && \Nino\Backup::codeRefusal( $staging ) === null );
\Nino\Filesystem::removeDir( $staging );
check( '...among them a file that starts with the declaration and a comment, and one that starts with a comment alone', str_starts_with( (string) file_get_contents( $corpus. '/elements/positionen.php' ), "<?php\ndeclare(strict_types=1);" ) && str_starts_with( (string) file_get_contents( $corpus. '/elements/closures.php' ), "<?php\n//" ) );

// The check on its own, on the staged tree, for the shapes a tar of this kind makes
$staging = sys_get_temp_dir(). '/nino-recovery-key-staging-'. bin2hex( random_bytes( 4 ) );
mkdir( $staging. '/images/elements/a', 0755, true );
file_put_contents( $staging. '/config.php', $data( [ 'a' => 1 ] ) );
file_put_contents( $staging. '/images/elements/a/b.800x600.webp', 'x' );
check( 'the check on a staging directory: data and pictures pass', \Nino\Backup::codeRefusal( $staging ) === null );
file_put_contents( $staging. '/config.php', "<?php return [ 'a' => [ 1, -2, 'x', true, null ], 'b' => array ( 'c' => 1.5 ) ];\n" );
check( '...so does a file written by hand with the short form of an array', \Nino\Backup::codeRefusal( $staging ) === null );
file_put_contents( $staging. '/config.php', $data( [ 'a' => 1 ] ). "\n" );
check( '...a file that ends in a line break passes', \Nino\Backup::codeRefusal( $staging ) === null );
file_put_contents( $staging. '/config.php', $data( str_repeat( 'x', 3000000 ) ) );
check( '...a large file passes, and costs a pattern and not a list of tokens', \Nino\Backup::codeRefusal( $staging ) === null );
file_put_contents( $staging. '/config.php', "<?php return [ 'a' => '". str_repeat( 'x', 3000000 ). "' ]; system('id');" );
check( '...and code behind a large string is found', \Nino\Backup::codeRefusal( $staging ) !== null );
$bigData = fopen( $staging. '/config.php', 'wb' );
fwrite( $bigData, "<?php return [ 'a' => '" );
for( $piece = 0; $piece < 48; $piece++ )
	fwrite( $bigData, str_repeat( 'x', 1048576 ) );
fwrite( $bigData, "' ];\n" );
fclose( $bigData );
memory_reset_peak_usage();
$bigBase = memory_get_usage();
$bigVerdict = \Nino\Backup::codeRefusal( $staging );
$bigPeak = memory_get_peak_usage() - $bigBase;
check( '...a data file of 48 MB passes with the memory of a window, not of the file', $bigVerdict === null && $bigPeak < 8 * 1048576 );
file_put_contents( $staging. '/config.php', $data( [ 'a' => 1 ] ) );
file_put_contents( $staging. '/images/elements/a/shapes.svg', '<svg xmlns="http://www.w3.org/2000/svg">'. str_repeat( '<path d="M0 0L1 1"/>', 20000 ). '</svg>' );
check( '...a drawing that is a page of shapes passes', \Nino\Backup::codeRefusal( $staging ) === null );
file_put_contents( $staging. '/images/elements/a/shapes.svg', '<svg xmlns="http://www.w3.org/2000/svg">'. str_repeat( '<path d="M0 0L1 1"/>', 60000 ). '</svg>' );
$bigDrawing = \Nino\Backup::codeRefusal( $staging );
check( '...and one of more than a megabyte is refused for its size - it cannot be judged within what a restore may take, and is no drawing that is more than shapes', $bigDrawing !== null && str_contains( $bigDrawing, 'more than shapes' ) === false );
unlink( $staging. '/images/elements/a/shapes.svg' );
file_put_contents( $staging. '/config.php', $data( [ 'a' => 1 ] ) );
mkdir( $staging. '/themes/mine', 0755, true );
file_put_contents( $staging. '/themes/mine/theme.json', '{}' );
check( '...a theme of the project passes', \Nino\Backup::codeRefusal( $staging ) === null );
file_put_contents( $staging. '/themes/mine/x.php.json', '{}' );
check( '...and a script name among the themes is refused on the staged tree itself', str_contains( (string) \Nino\Backup::codeRefusal( $staging ), 'no part of a theme' ) );
\Nino\Filesystem::removeDir( $staging );

\Nino\Filesystem::removeDir( $oldDir );
foreach( [ $new, $third, $fourth, $fifth ] as $app )
	\Nino\Filesystem::removeDir( ninoSandboxDir( $app ) );
ninoDone( $app );
