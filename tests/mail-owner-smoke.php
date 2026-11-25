<?php
declare(strict_types=1);

/**
 *	Nino
 *	mail-owner-smoke.php	The mails of the owner of \Nino\Mail (Mail::OWNER) and what became of a mail by its key: a round mail that an
 *												account of the workshop sets off is taken only with the session of the account, counts against no cap and
 *												no place of the visitors or of the system and is not kept out by them, keeps its text in a file of its own
 *												for each mail, and is delivered after the mails of the others at a pace that no run exceeds; state() and
 *												states() tell whether the mail of a key waits, was delivered, or was given up on, with no address and no
 *												text kept for it. The mails go to a transport of the test, and mail() is switched off.
 *
 *	Usage: php tests/mail-owner-smoke.php
 */

// No mail leaves this machine from here: the transport of the suite takes every mail, and a suite that finds none ends before it sends one
require __DIR__. '/harness.php';
require __DIR__. '/fixtures/faultfs.php';

$appData = ninoSandbox( 'mail-owner' );
$sandbox = ninoSandboxDir( $appData );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'POST';

const NOW = 1900000000;
$appData['./nino/mail/now'] = NOW;
$appData['/nino/mail/cap'] = 0;

$taken = [];
$answer = true;
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$taken, &$answer ): void {
	$taken[] = $mail;
	$mail['sent'] = $answer;
} );

if( count( array_filter( \Nino\Callbacks::registered( $appData, \Nino\Mail::TRANSPORT ) ) ) === 0 ) {
	echo "FAIL  - no test transport is registered: the suite ends before any mail is sent\n";
	exit( 1 );
}

check( 'the transport of the suite takes a mail before any other is sent: nothing of the suite goes to mail()', \Nino\Mail::send( $appData, 'probe@example.com', 'probe', 'probe', '' ) === true && count( $taken ) === 1 );
$taken = [];

const ACCOUNT = 'owner@example.com';
const PASSWORD = 'correct horse battery staple';
ninoUser( $appData, ACCOUNT );
$signOut = static function() use ( &$appData ): void { \Nino\Auth::logoutUser( $appData ); };
$signIn = static function() use ( &$appData ): void { \Nino\Auth::loginUser( $appData, ACCOUNT, PASSWORD ); };

$mail = static fn( int $i = 1, string $body = '<p>Hello</p>' ): array => [ 'to' => 'person'. $i. '@example.com', 'subject' => 'Subject '. $i, 'body' => $body, 'replyTo' => '' ];
$path = static fn( string $name ): string => \Nino\Filesystem::path( $appData, $name );
$bodyFile = static fn( string $id ): string => $path( \Nino\MailQueue::BODIES. '/'. $id. '.php' );
$waiting = static function() use ( $path ): array {
	clearstatcache();
	$state = \Nino\MailQueue::decode( (string) @file_get_contents( $path( \Nino\MailQueue::WAITING ) ) );
	return is_array( $state ) === true ? array_column( (array) ( $state['waiting'] ?? [] ), null, 'id' ) : [];
};
$holders = static function( string $needle ) use ( $sandbox ): array {
	$found = [];

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $sandbox. '/private', \FilesystemIterator::SKIP_DOTS ) ) as $file )
		if( $file->isFile() === true && str_contains( (string) file_get_contents( $file->getPathname() ), $needle ) === true )
			$found[] = $file->getPathname();

	return $found;
};
$bodies = static function() use ( $path ): array {
	clearstatcache();
	return glob( $path( \Nino\MailQueue::BODIES ). '/*.php' ) ?: [];
};
$reset = static function() use ( &$appData, &$taken, &$answer, $path, $bodies ): void {
	foreach( [ \Nino\MailQueue::WAITING, \Nino\MailQueue::DUE, \Nino\MailQueue::FAILED, \Nino\MailQueue::KEYS, \Nino\MailQueue::STATES, \Nino\MailQueue::DAMAGED, \Nino\MailQueue::DAMAGED_STATES, '/data/mail-status.php', '/data/ratelimit.php', '/data/sysmail-system.php', '/data/sysmail-test.php', '/data/mail-queue-body.php', '/data/mail-queue-states.php', '/data/mail-queue-lapsed.php', '/data/mail-queue-untried.php' ] as $name )
		@unlink( $path( $name ) );

	ninoCountersReset( $appData );

	foreach( $bodies() as $file )
		@unlink( $file );

	clearstatcache();
	$appData['./nino/mail/now'] = NOW;
	$appData['/nino/mail/cap'] = 0;
	$taken = [];
	$answer = true;
	ninoWarnings();
};
// One run of the deliverer at a second after NOW: what it answered and what the transport took in it
$run = static function( int $seconds, int $limit = 100 ) use ( &$appData, &$taken ): array {
	$appData['./nino/mail/now'] = NOW + $seconds;
	$before = count( $taken );

	return [ \Nino\Mail::deliverQueued( $appData, $limit ), array_slice( $taken, $before ) ];
};
$entry = static fn( array $over = [] ): array => $over + [ 'id' => bin2hex( random_bytes( 8 ) ), 'key' => '', 'content' => hash( 'sha256', 'x' ), 'kind' => 'client', 'to' => 'person1@example.com', 'subject' => 's', 'body' => 'b', 'replyTo' => '', 'queued' => NOW, 'tries' => 0, 'next' => NOW, 'error' => '', 'claim' => '', 'until' => 0 ];
$put = static function( array $entries ) use ( $path ): void {
	$real = $path( \Nino\MailQueue::WAITING );
	@mkdir( dirname( $real ), 0755, true );
	file_put_contents( $real, \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => $entries ] ) );
	touch( $real );
};

echo "The session\n";

$reset();
$appData['/nino/mail/cap'] = 5;
$signOut();
$id = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER );
check( 'a mail of the owner is not taken where the request has no session: no id, the reason is session, nothing is written and no cap is touched', $id === '' && \Nino\Mail::queueRefused( $appData ) === 'session' && \Nino\Mail::rateLimited( $appData ) === false && is_file( $path( \Nino\MailQueue::WAITING ) ) === false && is_file( $path( '/data/ratelimit.php' ) ) === false && $bodies() === [] );

$signIn();
$id = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER );
check( '...and with the session of an account it is taken, as a mail of the kind owner, and still no cap is touched', preg_match( '/^[0-9a-f]{16}$/', $id ) === 1 && ( $waiting()[$id]['kind'] ?? '' ) === 'owner' && is_file( $path( '/data/ratelimit.php' ) ) === false );

$signOut();
check( '...a session that has ended is none: the same refusal', \Nino\Mail::queue( $appData, $mail( 2 ), \Nino\Mail::OWNER ) === '' && \Nino\Mail::queueRefused( $appData ) === 'session' && count( $waiting() ) === 1 );

$signIn();
$_SERVER['REQUEST_METHOD'] = 'GET';
$byLink = \Nino\Mail::queue( $appData, $mail( 2 ), \Nino\Mail::OWNER );
$byLinkReason = \Nino\Mail::queueRefused( $appData );
$_SERVER['REQUEST_METHOD'] = 'POST';
check( '...and a session is no more than half of it: a request that only reads - a link another page sends the browser to - is refused as it is, whoever is signed in', $byLink === '' && $byLinkReason === 'method' && count( $waiting() ) === 1 );
$signOut();

$voided = \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::VOID );
$odd = [];

foreach( [ 'Owner', 'OWNER', 'owner ', 'own', '' ] as $kind )
	$odd[] = \Nino\Mail::queue( $appData, $mail( 4 ), $kind ) === '' && \Nino\Mail::queueRefused( $appData ) === 'invalid';

check( '...no other way gets a visitor the kind: a mail to nobody is kept as a mail of the kind client, and a kind that only looks like it is invalid', $voided !== '' && ( $waiting()[$voided]['kind'] ?? '' ) === 'client' && array_unique( $odd ) === [ true ] && count( array_filter( $waiting(), static fn( array $entry ): bool => $entry['kind'] === 'owner' ) ) === 1 );
$signIn();

// A command of a cron job, a process of its own that has the project and no session: the same refusal
$child = $sandbox. '/owner-child.php';
file_put_contents( $child, '<?php
declare(strict_types=1);
$root = $argv[1]; $sandbox = $argv[2];
require $root . "/tests/harness.php";
$appData = [ "./nino/uid" => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"] = $sandbox; $appData["./nino/filesystem/configpath"] = $sandbox . "/private"; $appData["./nino/filesystem/contentpath"] = $sandbox . "/private"; $appData["./nino/filesystem/publicpath"] = $sandbox . "/public";
$appData["/nino/dir"] = ""; $appData["/nino/install/texts"] = \Nino\VERSION; $appData["./nino/locales/current"] = "de_DE"; $appData["/nino/locales/native"] = "de_DE"; $appData["/nino/locales/available"] = [ "de_DE" ];
$appData["/nino/mail/cap"] = 0;
$id = \Nino\Mail::queue( $appData, [ "to" => "cron@example.com", "subject" => "s", "body" => "b", "replyTo" => "" ], \Nino\Mail::OWNER );
echo json_encode( [ $id, \Nino\Mail::queueRefused( $appData ) ] );
' );
$reset();
$output = (string) shell_exec( escapeshellarg( PHP_BINARY ). ' -d sendmail_path=/bin/false '. escapeshellarg( $child ). ' '. escapeshellarg( dirname( __DIR__ ) ). ' '. escapeshellarg( $sandbox ). ' 2>&1' );
check( '...a process of its own with the project and no session - the command of a cron job - is refused as a request without one', $output === '["","session"]' && is_file( $path( \Nino\MailQueue::WAITING ) ) === false );

echo "\n";
echo "Caps and places\n";

$reset();
$appData['/nino/mail/cap'] = 1;
$first = \Nino\Mail::queue( $appData, $mail( 1 ) );
$second = \Nino\Mail::queue( $appData, $mail( 2 ) );
$limited = \Nino\Mail::rateLimited( $appData );
$wave = [ \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::OWNER ), \Nino\Mail::queue( $appData, $mail( 4 ), \Nino\Mail::OWNER ), \Nino\Mail::queue( $appData, $mail( 5 ), \Nino\Mail::OWNER ) ];
check( 'a mail of the owner counts against the cap of the hour of nobody: with the cap of the client used up the wave is taken, and the signal of the cap says nothing', $first !== '' && $second === '' && $limited === true && count( array_filter( $wave ) ) === 3 && \Nino\Mail::rateLimited( $appData ) === false && \Nino\Mail::queue( $appData, $mail( 6 ) ) === '' && \Nino\Mail::queueRefused( $appData ) === 'ratelimited' );

$reset();
$wave = array_map( static fn( int $i ): array => $entry( [ 'kind' => 'owner', 'to' => 'wave'. $i. '@example.com', 'body' => '', 'queued' => NOW + $i ] ), range( 1, \Nino\MailQueue::MAX_OWNER ) );
$put( $wave );
$others = [ \Nino\Mail::queue( $appData, $mail( 1 ) ), \Nino\Mail::queue( $appData, $mail( 2 ), \Nino\Mail::SYSTEM ), \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::TEST ) ];
$over = \Nino\Mail::queue( $appData, $mail( 4 ), \Nino\Mail::OWNER );
check( 'a full wave keeps nobody out: the places of the owner are all taken and the next mail of the owner is full, while a mail of a visitor, of the system and of a test is taken', $over === '' && \Nino\Mail::queueRefused( $appData ) === 'full' && count( array_filter( $others ) ) === 3 && \Nino\Mail::queued( $appData )['waiting'] === \Nino\MailQueue::MAX_OWNER + 3 );

$reset();
$put( array_merge(
	array_map( static fn( int $i ): array => $entry( [ 'kind' => 'client', 'to' => 'visitor'. $i. '@example.com' ] ), range( 1, \Nino\MailQueue::MAX_CLIENT ) ),
	array_map( static fn( int $i ): array => $entry( [ 'kind' => 'system', 'to' => 'system'. $i. '@example.com' ] ), range( 1, \Nino\MailQueue::MAX_WAITING - \Nino\MailQueue::MAX_CLIENT ) )
) );
$refused = [ \Nino\Mail::queue( $appData, $mail( 1 ) ) === '' && \Nino\Mail::queueRefused( $appData ) === 'full', \Nino\Mail::queue( $appData, $mail( 2 ), \Nino\Mail::SYSTEM ) === '' && \Nino\Mail::queueRefused( $appData ) === 'full' ];
check( '...and the places of the others are no places of the owner: with all of them taken the mail of a visitor and of the system are full, and the mail of the owner is taken', $refused === [ true, true ] && \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::OWNER ) !== '' );

echo "\n";
echo "The text of a mail of the owner\n";

$reset();
$small = str_repeat( 'a', 2048 ). 'END';
$large = str_repeat( 'b', 20480 ). 'END';
$idSmall = \Nino\Mail::queue( $appData, $mail( 1, $small ), \Nino\Mail::OWNER );
$sizeSmall = (int) filesize( $path( \Nino\MailQueue::WAITING ) );
$reset();
$idLarge = \Nino\Mail::queue( $appData, $mail( 1, $large ), \Nino\Mail::OWNER );
$stored = \Nino\MailQueue::decode( (string) file_get_contents( $bodyFile( $idLarge ) ) );
check( 'the text of a mail of the owner is in a file of its own and not in the outbox: the file of the outbox is as large for 20 KB as for 2 KB, and holds the text of neither', (int) filesize( $path( \Nino\MailQueue::WAITING ) ) === $sizeSmall && str_contains( (string) file_get_contents( $path( \Nino\MailQueue::WAITING ) ), 'bbbb' ) === false && ( $waiting()[$idLarge]['body'] ?? 'x' ) === '' && is_array( $stored ) === true && ( $stored['body'] ?? '' ) === $large );
check( '...the file is one of the outbox: a php statement that returns json, of the mode 0600, below a folder that no backup carries', str_starts_with( (string) file_get_contents( $bodyFile( $idLarge ) ), "<?php return '" ) === true && ( PHP_OS_FAMILY === 'Windows' || ( fileperms( $bodyFile( $idLarge ) ) & 0777 ) === 0600 ) && in_array( 'data/'. basename( \Nino\MailQueue::BODIES ). '/'. $idLarge. '.php', array_map( static fn( string $name ): string => (string) preg_replace( '#/+#', '/', $name ), \Nino\Backup::manifest( $appData ) ), true ) === false );

[ $result, $sent ] = $run( 0 );
check( '...the transport is given the text from there, whole, and the file goes with the mail: nothing of the text is left on the disk', $result['delivered'] === 1 && count( $sent ) === 1 && $sent[0]['body'] === $large && $bodies() === [] && is_file( $path( \Nino\MailQueue::WAITING ) ) === false );

$reset();
$lost = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER, 'wave/lost' );
unlink( $bodyFile( $lost ) );
[ $result, $sent ] = $run( 0 );
check( 'a mail whose text is gone is no mail that can be sent: it is given up on at once, with the record of it, and the transport is never asked', $result['failed'] === 1 && $sent === [] && \Nino\Mail::queued( $appData )['failed'] === 1 && \Nino\Mail::state( $appData, 'wave/lost' ) === \Nino\Mail::STATE_FAILED );

$reset();
$unread = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER );
FaultFs::on( [ 'read' => static fn( string $name ): bool => basename( $name ) === $unread. '.php' ] );
[ $result, $sent ] = $run( 0 );
FaultFs::off();
$after = $waiting()[$unread] ?? [];
$said = array_values( array_filter( ninoWarnings(), static fn( string $warning ): bool => str_starts_with( $warning, 'Mail queue' ) ) );
check( 'a text that is there and cannot be read is no reason to give the mail up: nothing is sent, the attempt is counted, the mail waits for the next one and the log says so without a word of the mail', $result['retry'] === 1 && $sent === [] && ( $after['tries'] ?? 0 ) === 1 && ( $after['error'] ?? '' ) === \Nino\MailQueue::UNREADABLE && count( $said ) === 1 && str_contains( $said[0], 'example.com' ) === false );
[ $result, $sent ] = $run( 61 );
check( '...and when it can be read again the mail goes out', $result['delivered'] === 1 && count( $sent ) === 1 && $bodies() === [] );

$reset();
$kept = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER );
$lonely = bin2hex( random_bytes( 8 ) );
$young = bin2hex( random_bytes( 8 ) );
file_put_contents( $bodyFile( $lonely ), '<?php return \'{"format":1,"body":"x"}\';' );
file_put_contents( $bodyFile( $young ), '<?php return \'{"format":1,"body":"x"}\';' );
touch( $bodyFile( $lonely ), time() - \Nino\MailQueue::TEMP_KEEP );
touch( $bodyFile( $kept ), time() - \Nino\MailQueue::TEMP_KEEP );
\Nino\MailQueue::sweep( $appData );
check( 'a text with no mail in the outbox goes when it is an hour old; the text of a mail that waits stays however old it is, and a text that was just written stays', is_file( $bodyFile( $lonely ) ) === false && is_file( $bodyFile( $kept ) ) === true && is_file( $bodyFile( $young ) ) === true );

$reset();
$inline = $entry( [ 'kind' => 'owner', 'body' => 'inline text' ] );
$put( [ $inline ] );
@mkdir( dirname( $bodyFile( $inline['id'] ) ), 0755, true );
file_put_contents( $bodyFile( $inline['id'] ), '<?php return \'{"format":1,"body":"aside text"}\';' );
[ $result, $sent ] = $run( 0 );
check( 'an entry of the owner that holds a text in the outbox is not what the outbox writes: it is no mail, and is given up on', $result['failed'] === 1 && $sent === [] );

$reset();
$old = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER, 'wave/old' );
[ $result, $sent ] = $run( \Nino\MailQueue::MAX_AGE );
check( 'a mail of the owner that waited too long is given up on as any mail, and its text and its key go with it: the state says failed', $result['failed'] === 1 && $sent === [] && $bodies() === [] && \Nino\Mail::state( $appData, 'wave/old' ) === \Nino\Mail::STATE_FAILED );
$anew = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER, 'wave/old' );
check( '...and a mail that was given up on is free to be queued again, which is a second try of its owner: it waits, and its text with it', $anew !== '' && \Nino\Mail::state( $appData, 'wave/old' ) === \Nino\Mail::STATE_WAITING && count( $bodies() ) === 1 );

$reset();
$secretText = '<p>Round code 7741-lapse</p>';
$lapsing = \Nino\Mail::queue( $appData, [ 'expires' => 900 ] + $mail( 1, $secretText ), \Nino\Mail::OWNER, 'wave/lapse' );
$lasting = \Nino\Mail::queue( $appData, $mail( 2 ), \Nino\Mail::OWNER, 'wave/last' );
$textBefore = is_file( $bodyFile( $lapsing ) ) === true && $holders( '7741-lapse' ) !== [];
[ $result, $sent ] = $run( 900 );
check( 'a mail of the owner whose expiry has passed is not sent, and its text file goes with it: the one without an expiry is delivered, no file of the project holds the text of the other, and it is no failure', $textBefore === true && $result['delivered'] === 1 && $result['failed'] === 0 && count( $sent ) === 1 && $sent[0]['subject'] === 'Subject 2' && is_file( $bodyFile( $lapsing ) ) === false && $bodies() === [] && $holders( '7741-lapse' ) === [] && \Nino\Mail::queued( $appData )['failed'] === 0 && \Nino\Mail::state( $appData, 'wave/lapse' ) === \Nino\Mail::STATE_FAILED && \Nino\Mail::state( $appData, 'wave/last' ) === \Nino\Mail::STATE_SENT );
$again = \Nino\Mail::queue( $appData, $mail( 1, $secretText ), \Nino\Mail::OWNER, 'wave/lapse' );
check( '...and a mail that lapsed is free to be queued again, as one that was given up on is', $again !== '' && $again !== $lapsing && \Nino\Mail::state( $appData, 'wave/lapse' ) === \Nino\Mail::STATE_WAITING );

// The window the kernel names: the mail is taken a second before its expiry, and the second passes while its text is read from the file - before the transport is asked
$reset();
$late = \Nino\Mail::queue( $appData, [ 'expires' => 900 ] + $mail( 1, $secretText ), \Nino\Mail::OWNER, 'wave/late' );
$appData['./nino/mail/now'] = NOW + 899;
FaultFs::on( [ 'read' => static function( string $name ) use ( &$appData, $late ): bool {
	if( basename( $name ) === $late. '.php' )
		$appData['./nino/mail/now'] = NOW + 900;

	return false;
} ] );
$result = \Nino\Mail::deliverQueued( $appData, 100 );
FaultFs::off();
check( 'a mail of the owner whose expiry passes after it was taken and before the transport is asked is let go with no transport: nothing is delivered, retried or given up on, its text file is gone and the state says failed', $result === [ 'delivered' => 0, 'void' => 0, 'retry' => 0, 'held' => 0, 'failed' => 0, 'unwritable' => false ] && $taken === [] && $bodies() === [] && $holders( '7741-lapse' ) === [] && $waiting() === [] && \Nino\Mail::state( $appData, 'wave/late' ) === \Nino\Mail::STATE_FAILED );

// The transport of a mail of the owner runs over the second of its expiry, and the owner clicks again
$reset();
$held = \Nino\Mail::queue( $appData, [ 'expires' => 60 ] + $mail( 1, $secretText ), \Nino\Mail::OWNER, 'wave/held' );
$gave = 0;
$unwritable = false;
$appData['./nino/mail/now'] = NOW + 59;
$taking = \Nino\MailQueue::claim( $appData, 'aaaa', NOW + 59, $gave, $unwritable );
\Nino\Mail::queue( $appData, $mail( 5 ) );
[ $result, $sent ] = $run( 61 );
$second = \Nino\Mail::queue( $appData, [ 'expires' => 60 ] + $mail( 1, $secretText ), \Nino\Mail::OWNER, 'wave/held' );
$stateDuring = \Nino\Mail::state( $appData, 'wave/held' );
\Nino\MailQueue::settle( $appData, 'aaaa', $held, 'sent', '', NOW + 61 );
$third = \Nino\Mail::queue( $appData, [ 'expires' => 60 ] + $mail( 1, $secretText ), \Nino\Mail::OWNER, 'wave/held' );
check( 'a mail of the owner whose transport runs over the second of its expiry is delivered once: a second click in the meantime is the same mail, state() says waiting and then sent, and the click after the answer queues nothing', $taking !== null && count( $sent ) === 1 && $sent[0]['subject'] === 'Subject 5' && $second === $held && $stateDuring === \Nino\Mail::STATE_WAITING && \Nino\Mail::state( $appData, 'wave/held' ) === \Nino\Mail::STATE_SENT && $third !== '' && $waiting() === [] && $bodies() === [] );

echo "\n";
echo "The pace and the order\n";

$reset();
$visitor = \Nino\Mail::queue( $appData, $mail( 901 ) );
$system = \Nino\Mail::queue( $appData, $mail( 902 ), \Nino\Mail::SYSTEM );

for( $i = 1; $i <= 50; $i++ )
	\Nino\Mail::queue( $appData, $mail( $i ), \Nino\Mail::OWNER );

[ $result, $sent ] = $run( 0 );
$order = array_map( static fn( array $mail ): string => $mail['to'], $sent );
$firstTwo = array_slice( $order, 0, 2 );
sort( $firstTwo );
check( 'a run delivers the mails of the others first and then no more of the owner than a burst, whatever its limit', count( $sent ) === 2 + \Nino\MailQueue::OWNER_BURST && $firstTwo === [ 'person901@example.com', 'person902@example.com' ] && $result['delivered'] === count( $sent ) );

[ $result, $sent ] = $run( 0 );
check( '...a second run in the same second finds nothing due, and the note says when the next mail of the owner is due: after what one mail costs', $result['delivered'] === 0 && $sent === [] && trim( (string) file_get_contents( $path( \Nino\MailQueue::DUE ) ) ) === (string) ( NOW + \Nino\MailQueue::OWNER_GAP ) );

$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
$appData['./nino/mail/now'] = NOW + 1;
check( '...so that the requests of a site in between are not asked for work: the response carries none', \Nino\Http::prepareAfter( $appData, $request ) === [] );

[ $result, $sent ] = $run( \Nino\MailQueue::OWNER_GAP );
check( '...after that time one mail goes out', $result['delivered'] === 1 );

[ $result, $sent ] = $run( 10000 );
check( '...and after a long time no more than a burst at once: the credit does not grow beyond it', $result['delivered'] === \Nino\MailQueue::OWNER_BURST );

// Two deliverers that are real processes, in the same second, with the credit of one mail: together they deliver one. Each is held at the one place
// where the pace is looked at without the lock - the first read of the outbox - until both have looked, by the lock of the outbox that this
// process holds, so that the second takes its mail after the first has used the credit up, and only the look under the lock stops it
$reset();

for( $i = 1; $i <= 5; $i++ )
	\Nino\Mail::queue( $appData, $mail( $i ), \Nino\Mail::OWNER );

$stored = \Nino\MailQueue::decode( (string) file_get_contents( $path( \Nino\MailQueue::WAITING ) ) );
$stored['pace'] = [ 'at' => NOW, 'credit' => \Nino\MailQueue::OWNER_GAP ];
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( $stored ) );
$tally = $sandbox. '/race-tally.txt';
file_put_contents( $tally, '' );
$racer = $sandbox. '/owner-racer.php';
file_put_contents( $racer, '<?php
declare(strict_types=1);
$root = $argv[1]; $sandbox = $argv[2]; $tally = $argv[3];
require $root . "/tests/harness.php";
require $root . "/tests/fixtures/faultfs.php";
$appData = [ "./nino/uid" => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"] = $sandbox; $appData["./nino/filesystem/configpath"] = $sandbox . "/private"; $appData["./nino/filesystem/contentpath"] = $sandbox . "/private"; $appData["./nino/filesystem/publicpath"] = $sandbox . "/public";
$appData["/nino/dir"] = ""; $appData["/nino/install/texts"] = \Nino\VERSION; $appData["./nino/locales/current"] = "de_DE"; $appData["/nino/locales/native"] = "de_DE"; $appData["/nino/locales/available"] = [ "de_DE" ];
$appData["./nino/mail/now"] = ' . NOW . '; $appData["/nino/mail/cap"] = 0;
$out = fopen( $tally, "ab" );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( $out ): void { fwrite( $out, "x\n" ); fflush( $out ); $mail["sent"] = true; } );
if( count( array_filter( \Nino\Callbacks::registered( $appData, \Nino\Mail::TRANSPORT ) ) ) === 0 ) { echo "no transport\n"; exit( 1 ); }
$looked = false;
FaultFs::on( [ "read" => static function( string $name ) use ( &$looked ): bool {
	if( $looked === false && basename( $name ) === "mail-queue.php" ) { $looked = true; fwrite( STDOUT, "looking\n" ); fflush( STDOUT ); }
	return false;
} ] );
\Nino\Mail::deliverQueued( $appData, 100 );
FaultFs::off();
' );
$held = \Nino\Filesystem::lockFile( $appData, \Nino\MailQueue::WAITING );
$procs = [];
$pipes = [];

for( $n = 0; $n < 2; $n++ ) {

	$procs[$n] = proc_open( [ PHP_BINARY, '-d', 'sendmail_path=/bin/false', $racer, dirname( __DIR__ ), $sandbox, $tally ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes[$n] );
}

$told = [];

foreach( $pipes as $n => $pair )
	$told[$n] = trim( (string) fgets( $pair[1] ) );

\Nino\Filesystem::unlockFile( $appData, \Nino\MailQueue::WAITING );

foreach( $procs as $n => $process ) {

	stream_get_contents( $pipes[$n][1] );
	stream_get_contents( $pipes[$n][2] );
	proc_close( $process );
}

check( 'two deliverers that are processes of their own, in the same second, with credit for one mail, deliver one mail between them: the pace is looked at once more under the lock', $held === true && $told === [ 'looking', 'looking' ] && count( file( $tally ) ?: [] ) === 1 );

// A queue that cannot be read says nothing of what waits in it: the texts that are older than an hour are not swept for it
$reset();
$guarded = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::OWNER );
touch( $bodyFile( $guarded ), time() - \Nino\MailQueue::TEMP_KEEP );
FaultFs::on( [ 'read' => static fn( string $name ): bool => basename( $name ) === 'mail-queue.php' ] );
\Nino\MailQueue::sweep( $appData );
FaultFs::off();
check( 'a text that is older than an hour is not swept while the outbox cannot be read: the mail it belongs to would be given up on as one with no text', is_file( $bodyFile( $guarded ) ) === true );

$reset();
$ids = [];

for( $i = 1; $i <= 200; $i++ )
	$ids[$i] = \Nino\Mail::queue( $appData, $mail( $i, '<p>Round mail</p>' ), \Nino\Mail::OWNER, 'wave/'. $i );

$counts = [];
$lastRun = -1;
$late = '';

for( $minute = 0; $minute < 40; $minute++ ) {

	if( $minute === 3 )
		$late = \Nino\Mail::queue( $appData, $mail( 903 ) );

	[ $result, $sent ] = $run( $minute * 60 );
	$counts[] = $result['delivered'];

	if( $result['delivered'] > 0 )
		$lastRun = $minute;

	if( $minute === 3 )
		$lateFirst = ( $sent[0]['to'] ?? '' ) === 'person903@example.com';

	if( \Nino\Mail::queued( $appData )['waiting'] === 0 )
		break;
}

check( 'a wave of 200 mails, with a cron job that runs every minute, takes ten runs: twenty mails in each, the last at nine minutes after the first', array_slice( $counts, 0, 10 ) === array_merge( array_fill( 0, 3, 20 ), [ 21 ], array_fill( 0, 6, 20 ) ) && $lastRun === 9 && count( $counts ) === 10 );
$sentOnce = count( $taken );
$clicks = [];

foreach( [ 15 * 60, 3600, 13 * 86400 ] as $later ) {

	$appData['./nino/mail/now'] = NOW + $later;
	$answers = [];

	for( $i = 1; $i <= 200; $i++ )
		$answers[] = \Nino\Mail::queue( $appData, $mail( $i, '<p>Round mail</p>' ), \Nino\Mail::OWNER, 'wave/'. $i );

	$run( $later );
	$clicks[] = count( array_filter( $answers ) ) === 200 && \Nino\Mail::queued( $appData )['waiting'] === 0 && count( $taken ) === $sentOnce;
}

check( 'a second click after the wave - a quarter of an hour, an hour, thirteen days later, long after the keys are forgotten - sends nothing twice: every mail is answered as the mail that went out, nothing is queued, and no one gets it again', array_unique( $clicks ) === [ true ] );
check( '...and a mail of a visitor that comes in the middle of it goes out in the next run, before the wave', $lateFirst === true && \Nino\Mail::state( $appData, 'wave/200' ) === \Nino\Mail::STATE_SENT );

// The states of a wave are its own: more mails of visitors and mails to nobody than the file keeps for them - a sign-in leaves one for every try -
// push none of them out
$secretNow = \Nino\MailQueue::secret( $appData );
$seeds = [];

for( $i = 0; $i < \Nino\MailQueue::MAX_STATES - 100; $i++ )
	$seeds[] = [ substr( hash_hmac( 'sha256', hash( 'sha256', 'signin/seed-'. $i ), $secretNow ), 0, 32 ), 'sent', NOW + 13 * 86400 - 50, str_repeat( '1', 16 ), 0 ];

$present = \Nino\MailQueue::decode( (string) file_get_contents( $path( \Nino\MailQueue::STATES ) ) );
file_put_contents( $path( \Nino\MailQueue::STATES ), \Nino\MailQueue::encode( [ 'format' => 1, 'states' => array_merge( $seeds, $present['states'] ) ] ) );

for( $i = 0; $i < 150; $i++ ) {

	\Nino\Mail::queue( $appData, $mail( 700 + $i ), $i % 2 === 0 ? \Nino\Mail::VOID : \Nino\Mail::CLIENT, 'signin/live-'. $i );
	$run( 13 * 86400 );
}

$after = \Nino\MailQueue::decode( (string) file_get_contents( $path( \Nino\MailQueue::STATES ) ) );
$of = static fn( int $owner ): int => count( array_filter( $after['states'], static fn( array $record ): bool => $record[4] === $owner ) );
$answers = [];
$sentBefore = count( $taken );

for( $i = 1; $i <= 200; $i++ )
	$answers[] = \Nino\Mail::queue( $appData, $mail( $i, '<p>Round mail</p>' ), \Nino\Mail::OWNER, 'wave/'. $i );

check( 'more mails of visitors and to nobody than the file keeps for them push none of the states of a wave out: the click that follows sends it to nobody a second time, and the list of the visitors is cut where it is', $of( 0 ) === \Nino\MailQueue::MAX_STATES && $of( 1 ) === 200 && count( array_filter( $answers ) ) === 200 && \Nino\Mail::queued( $appData )['waiting'] === 0 && count( $taken ) === $sentBefore );

$many = [];

for( $i = 0; $i < \Nino\MailQueue::MAX_STATES_OWNER + 20; $i++ )
	$many[] = [ substr( hash_hmac( 'sha256', hash( 'sha256', 'crowd/owner-'. $i ), $secretNow ), 0, 32 ), 'sent', NOW + 13 * 86400 - 5000 + $i, str_repeat( '2', 16 ), 1 ];

file_put_contents( $path( \Nino\MailQueue::STATES ), \Nino\MailQueue::encode( [ 'format' => 1, 'states' => $many ] ) );
\Nino\Mail::queue( $appData, $mail( 800 ), \Nino\Mail::OWNER, 'wave/last' );
$run( 13 * 86400 );
$full = \Nino\MailQueue::decode( (string) file_get_contents( $path( \Nino\MailQueue::STATES ) ) );
check( '...and the states of the owner are cut at a number of their own, the oldest first: nothing of a wave is lost before then, and what is older than the last waves is not known', count( $full['states'] ) === \Nino\MailQueue::MAX_STATES_OWNER && \Nino\Mail::state( $appData, 'wave/last' ) === \Nino\Mail::STATE_SENT && \Nino\Mail::state( $appData, 'crowd/owner-0' ) === \Nino\Mail::STATE_UNKNOWN && \Nino\Mail::state( $appData, 'crowd/owner-'. ( \Nino\MailQueue::MAX_STATES_OWNER + 19 ) ) === \Nino\Mail::STATE_SENT );

// What a request that only reads, or that is no POST at all, is told: one reason for each, whoever is signed in
$verbs = [];

foreach( [ 'GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE', 'PATCH', 'post', 'Post', '' ] as $verb ) {

	$_SERVER['REQUEST_METHOD'] = $verb;
	$verbs[$verb] = \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::OWNER ) === '' && \Nino\Mail::queueRefused( $appData ) === 'method';
}

$_SERVER['REQUEST_METHOD'] = 'POST';
check( 'every method but POST is refused as method, the small-letter one too: HEAD, OPTIONS, PUT, DELETE, PATCH, post, Post and none', array_values( array_unique( $verbs ) ) === [ true ] && count( $verbs ) === 9 );

$reset();

for( $i = 1; $i <= 500; $i++ )
	$ids[$i] = \Nino\Mail::queue( $appData, $mail( $i, '<p>Round mail</p>' ), \Nino\Mail::OWNER, 'wave/'. $i );

$again = 0;

foreach( range( 1, 500 ) as $i )
	if( \Nino\Mail::queue( $appData, $mail( $i, '<p>Round mail</p>' ), \Nino\Mail::OWNER, 'wave/'. $i ) === $ids[$i] )
		$again++;

check( 'a wave of 500 queued twice - a double click - is one wave: every mail of the second call is the mail of the first, and nothing is added', $again === 500 && count( $waiting() ) === 500 && count( $bodies() ) === 500 );

$run( 0 );
$again = 0;

foreach( range( 1, 500 ) as $i )
	if( \Nino\Mail::queue( $appData, $mail( $i, '<p>Round mail</p>' ), \Nino\Mail::OWNER, 'wave/'. $i ) === $ids[$i] )
		$again++;

check( '...also when its first mails have gone out in between: their keys are remembered, and the second call adds none', $again === 500 && count( $waiting() ) === 480 && count( $bodies() ) === 480 );

echo "\n";
echo "What became of a mail\n";

$reset();
$key = 'invite/2026-0042';
$states = [ \Nino\Mail::state( $appData, $key ) ];
$queued = \Nino\Mail::queue( $appData, $mail( 5, '<p>Hi</p>' ), \Nino\Mail::OWNER, $key );
$states[] = \Nino\Mail::state( $appData, $key );
$run( 0 );
$states[] = \Nino\Mail::state( $appData, $key );
check( 'the state of a mail follows it by its key: nothing known, waiting, sent', $states === [ \Nino\Mail::STATE_UNKNOWN, \Nino\Mail::STATE_WAITING, \Nino\Mail::STATE_SENT ] && $queued !== '' );

$appData['./nino/mail/now'] = NOW + \Nino\MailQueue::KEY_MEMORY;
$requeued = \Nino\Mail::queue( $appData, $mail( 5, '<p>Hi</p>' ), \Nino\Mail::OWNER, $key );
$other = \Nino\Mail::queue( $appData, $mail( 5, '<p>Other</p>' ), \Nino\Mail::OWNER, $key );
$otherReason = \Nino\Mail::queueRefused( $appData );
check( '...the same mail queued again after the keys are forgotten is the mail that went out, whose id is not kept and which every call answers alike: nothing waits, and another mail under the key is a conflict', $requeued !== '' && $requeued === \Nino\Mail::queue( $appData, $mail( 5, '<p>Hi</p>' ), \Nino\Mail::OWNER, $key ) && \Nino\Mail::queued( $appData )['waiting'] === 0 && $other === '' && $otherReason === 'conflict' && \Nino\Mail::state( $appData, $key ) === \Nino\Mail::STATE_SENT );

$reset();
\Nino\Mail::queue( $appData, $mail( 14 ), \Nino\Mail::OWNER, 'invite/known' );
$run( 0 );
$appData['./nino/mail/now'] = NOW + \Nino\MailQueue::KEY_MEMORY;
FaultFs::on( [ 'read' => static fn( string $name ): bool => basename( $name ) === 'mail-states.php' ] );
$blindClick = \Nino\Mail::queue( $appData, $mail( 14 ), \Nino\Mail::OWNER, 'invite/known' );
$blindReason = \Nino\Mail::queueRefused( $appData );
FaultFs::off();
check( 'where the states cannot be read it is not known whether the mail went out: a mail of the owner under a key is not queued, and the reason is that the outbox cannot be written', $blindClick === '' && $blindReason === 'unwritable' && \Nino\Mail::queued( $appData )['waiting'] === 0 );

$reset();
$answer = false;
\Nino\Mail::queue( $appData, $mail( 6 ), \Nino\Mail::CLIENT, 'invite/refused' );
$at = 0;

for( $try = 0; $try < \Nino\MailQueue::attempts(); $try++ ) {

	$run( $at );
	$at += \Nino\MailQueue::BACKOFF[ min( $try, count( \Nino\MailQueue::BACKOFF ) - 1 ) ];
}

check( 'a mail that the transport never takes is failed once its attempts are over, and the state says it after the record of the mail does', \Nino\Mail::queued( $appData )['failed'] === 1 && \Nino\Mail::state( $appData, 'invite/refused' ) === \Nino\Mail::STATE_FAILED );

$answer = true;
$reset();
\Nino\Mail::queue( $appData, $mail( 7 ), \Nino\Mail::VOID, 'signin/void' );
$run( 0 );
check( 'a mail to nobody is sent when it is let go, as for the counts of the outbox', \Nino\Mail::state( $appData, 'signin/void' ) === \Nino\Mail::STATE_SENT && $taken === [] );

$reset();

for( $i = 1; $i <= 200; $i++ )
	\Nino\Mail::queue( $appData, $mail( $i ), \Nino\Mail::OWNER, 'wave/'. $i );

$run( 0 );
$keys = array_map( static fn( int $i ): string => 'wave/'. $i, range( 1, 200 ) );
FaultFs::on();
\Nino\Mail::state( $appData, 'wave/1' );
$one = count( array_filter( FaultFs::$log, static fn( array $record ): bool => $record[0] === 'open' && str_starts_with( $record[2], 'r' ) ) );
FaultFs::on();
$many = \Nino\Mail::states( $appData, array_merge( $keys, [ 'wave/none', 'no key', 7 ] ) );
$opens = count( array_filter( FaultFs::$log, static fn( array $record ): bool => $record[0] === 'open' && str_starts_with( $record[2], 'r' ) ) );
FaultFs::off();
$tally = array_count_values( $many );
check( 'the states of 200 mails are asked of the files once, as the state of one is: the same files are read, and each key has its answer', $opens === $one && $tally == [ \Nino\Mail::STATE_SENT => 20, \Nino\Mail::STATE_WAITING => 180, \Nino\Mail::STATE_UNKNOWN => 2 ] && count( $many ) === 202 && isset( $many['wave/none'] ) === true && isset( $many[7] ) === false );

$reset();
\Nino\Mail::queue( $appData, $mail( 8 ), \Nino\Mail::CLIENT, 'invite/private' );
$run( 0 );
$secretFile = (string) file_get_contents( $path( \Nino\MailQueue::STATES ) );
check( 'what is kept of a mail that went out is a hash, a word and a time: no address, no subject, no key, no text - in no file of the project', $holders( 'person8@example.com' ) === [] && $holders( 'Subject 8' ) === [] && $holders( 'invite/private' ) === [] && str_contains( $secretFile, 'sent' ) === true && preg_match( '/invite|example|private/', $secretFile ) !== 1 && str_contains( $secretFile, substr( hash( 'sha256', 'invite/private' ), 0, 32 ) ) === false && str_contains( $secretFile, substr( hash( 'sha256', hash( 'sha256', 'invite/private' ) ), 0, 32 ) ) === false );

\Nino\Mail::queue( $appData, $mail( 9 ), \Nino\Mail::CLIENT, 'other/private' );
$run( 0 );
check( '...a key that another feature uses is its own: the state of a key is asked by its form - a name and a slash - and a key of the form that nothing was queued under is unknown, as is a key that is none', \Nino\Mail::state( $appData, 'other/private' ) === \Nino\Mail::STATE_SENT && \Nino\Mail::state( $appData, 'third/private' ) === \Nino\Mail::STATE_UNKNOWN && \Nino\Mail::state( $appData, 'private' ) === \Nino\Mail::STATE_UNKNOWN && \Nino\Mail::state( $appData, '' ) === \Nino\Mail::STATE_UNKNOWN );

$clock = static function( int $seconds ) use ( &$appData ): void { $appData['./nino/mail/now'] = NOW + $seconds; };
$clock( \Nino\MailQueue::KEEP_STATES - 1 );
$near = \Nino\Mail::state( $appData, 'other/private' );
$clock( \Nino\MailQueue::KEEP_STATES );
check( 'a mail that went out is known for fourteen days, to the second, and not a second longer', $near === \Nino\Mail::STATE_SENT && \Nino\Mail::state( $appData, 'other/private' ) === \Nino\Mail::STATE_UNKNOWN );

$reset();
$secret = \Nino\MailQueue::secret( $appData );
$crowd = [];

for( $i = 0; $i < \Nino\MailQueue::MAX_STATES + 10; $i++ )
	$crowd[] = [ substr( hash_hmac( 'sha256', hash( 'sha256', 'crowd/'. $i ), $secret ), 0, 32 ), 'sent', NOW - 1000 + $i, str_repeat( '0', 16 ), 0 ];

file_put_contents( $path( \Nino\MailQueue::STATES ), \Nino\MailQueue::encode( [ 'format' => 1, 'states' => $crowd ] ) );
\Nino\Mail::queue( $appData, $mail( 10 ), \Nino\Mail::CLIENT, 'invite/newest' );
$run( 0 );
$file = \Nino\MailQueue::decode( (string) file_get_contents( $path( \Nino\MailQueue::STATES ) ) );
check( 'the states are kept in a list of a size that is bounded: the oldest go first, and the newest is there', count( $file['states'] ?? [] ) === \Nino\MailQueue::MAX_STATES && \Nino\Mail::state( $appData, 'invite/newest' ) === \Nino\Mail::STATE_SENT && \Nino\Mail::state( $appData, 'crowd/0' ) === \Nino\Mail::STATE_UNKNOWN && \Nino\Mail::state( $appData, 'crowd/'. ( \Nino\MailQueue::MAX_STATES + 9 ) ) === \Nino\Mail::STATE_SENT );

$reset();
\Nino\Mail::queue( $appData, $mail( 11 ), \Nino\Mail::CLIENT, 'invite/blind' );
$run( 0 );
\Nino\Mail::queue( $appData, $mail( 12 ), \Nino\Mail::CLIENT, 'invite/blind-too' );
$blind = [];

foreach( [ 'mail-queue.php', 'mail-states.php', 'mail-secret.php' ] as $name ) {

	FaultFs::on( [ 'read' => static fn( string $file ): bool => basename( $file ) === $name ] );
	$blind[$name] = \Nino\Mail::state( $appData, 'invite/blind' );
	FaultFs::off();
}

check( 'where a file of the outbox is there and cannot be read nothing is known of what became of a mail, and the state says so: neither unknown nor sent', array_values( $blind ) === array_fill( 0, 3, \Nino\Mail::STATE_UNREADABLE ) && \Nino\Mail::state( $appData, 'invite/blind' ) === \Nino\Mail::STATE_SENT );

$reset();
$hand = \Nino\Filesystem::path( $appData, \Nino\MailQueue::STATES );
\Nino\MailQueue::secret( $appData );
file_put_contents( $hand, 'this was written by a person' );
$before = \Nino\Mail::state( $appData, 'invite/damaged' );
\Nino\Mail::queue( $appData, $mail( 13 ), \Nino\Mail::CLIENT, 'invite/damaged' );
$run( 0 );
$notice = \Nino\MailQueue::decode( (string) @file_get_contents( $path( \Nino\MailQueue::DAMAGED_STATES ) ) );
check( 'a file of the states that is no file of the outbox is put aside as a notice of its size and its hash, and the next mail that went out starts the list again', $before === \Nino\Mail::STATE_UNKNOWN && is_array( $notice ) === true && isset( $notice['damaged']['sha256'] ) === true && str_contains( (string) file_get_contents( $path( \Nino\MailQueue::DAMAGED_STATES ) ), 'person' ) === false && \Nino\Mail::state( $appData, 'invite/damaged' ) === \Nino\Mail::STATE_SENT );

echo "\n";
echo "The files and the limits\n";

$never = ( new \ReflectionClass( \Nino\Backup::class ) )->getReflectionConstant( 'NEVER' )->getValue();
check( 'the files of the owner and of the states are no part of a backup, nor of a restore: the list of them, and the counters that keep their log lines from saying the same twice', array_diff( [ basename( \Nino\MailQueue::STATES ), basename( \Nino\MailQueue::DAMAGED_STATES ), 'mail-queue-body.php', 'mail-queue-states.php' ], $never ) === [] );

$constants = ( new \ReflectionClass( \Nino\MailQueue::class ) )->getConstants();
check( 'the limits of the owner and of the states are the ones the manual names', [ $constants['MAX_OWNER'], $constants['OWNER_BURST'], $constants['OWNER_GAP'], $constants['MAX_KEYS'], $constants['MAX_STATES'], $constants['MAX_STATES_OWNER'], $constants['KEEP_STATES'] ] === [ 600, 20, 3, 700, 2000, 3000, 1209600 ] );

ninoDone( $appData );
