<?php
declare(strict_types=1);

/**
 *	Nino
 *	mail-void-smoke.php	A mail to nobody, \Nino\Mail::VOID: it goes the way of a mail of the kind CLIENT, and the way is the same to the
 *											file operation - what a request does to the disk, what the response says, the work asked for after it,
 *											what the cap of the client and the places of the visitors say afterwards - and ends in the deliverer,
 *											which lets it go without a transport. The suite holds that equality; what is not equal is told by
 *											the checks that say so. The address of a mail to nobody is kept nowhere, the limits are those of a mail,
 *											and a file a person edited gives the kind no way a mail has not.
 *
 *	Usage: php tests/mail-void-smoke.php
 */

// No mail leaves this machine from here: the transport of the suite, registered below, takes every mail, and a suite that finds no transport ends before it
// sends one (sendmail_path cannot be set from a script - it is php.ini's - so mail() is never what stands between a test and a real mail)
require __DIR__. '/harness.php';
require __DIR__. '/fixtures/faultfs.php';

$appData = ninoSandbox( 'mail-void' );
$sandbox = ninoSandboxDir( $appData );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'noreply@example.org' ], '*' );

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

$mail = static fn( int $i = 1, string $body = '<p>Code 123456</p>' ): array => [ 'to' => 'person'. $i. '@example.com', 'subject' => 'Your code '. $i, 'body' => $body, 'replyTo' => '' ];
$path = static fn( string $name ): string => \Nino\Filesystem::path( $appData, $name );
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
$reset = static function() use ( &$appData, &$taken, &$answer, $path ): void {
	foreach( [ \Nino\MailQueue::WAITING, \Nino\MailQueue::DUE, \Nino\MailQueue::FAILED, \Nino\MailQueue::KEYS, \Nino\MailQueue::STATES, \Nino\MailQueue::DAMAGED, '/data/mail-status.php', '/data/ratelimit.php', '/data/sysmail-system.php', '/data/sysmail-test.php' ] as $name )
		@unlink( $path( $name ) );

	clearstatcache();
	$appData['./nino/mail/now'] = NOW;
	$appData['/nino/mail/cap'] = 0;
	$taken = [];
	$answer = true;
	ninoWarnings();
};
// One entry of the outbox as the file holds it, for a test that writes the file the way a person edits it
$entry = static fn( array $over = [] ): array => $over + [ 'id' => bin2hex( random_bytes( 8 ) ), 'key' => '', 'content' => hash( 'sha256', 'x' ), 'kind' => 'client', 'to' => 'person1@example.com', 'subject' => 's', 'body' => 'b', 'replyTo' => '', 'queued' => NOW, 'tries' => 0, 'next' => NOW, 'error' => '', 'claim' => '', 'until' => 0 ];
$put = static function( array $entries ) use ( $path ): void {
	file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => $entries ] ) );
	@unlink( $path( \Nino\MailQueue::DUE ) );
	clearstatcache();
};

echo "The kind\n";

$constants = ( new \ReflectionClass( \Nino\Mail::class ) )->getConstants();
check( 'the kinds of a mail are the three it had and VOID, and the words are the ones the manual names', [ $constants['CLIENT'], $constants['SYSTEM'], $constants['TEST'], $constants['VOID'] ] === [ 'client', 'system', 'test', 'void' ] );

$reset();
$id = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'login/one' );
$stored = array_values( $waiting() )[0] ?? [];
check( 'queue() answers an id of 16 hex digits for a mail to nobody and says nothing refused; it waits in the outbox as a mail of the kind CLIENT', preg_match( '/^[0-9a-f]{16}$/', $id ) === 1 && \Nino\Mail::queueRefused( $appData ) === '' && count( $waiting() ) === 1 && ( $stored['kind'] ?? '' ) === \Nino\Mail::CLIENT && $taken === [] );
check( '...with its subject and body as they were given, and the address nowhere: a line of hyphens as long as the address stands in its place', ( $stored['subject'] ?? '' ) === 'Your code 1' && ( $stored['body'] ?? '' ) === '<p>Code 123456</p>' && ( $stored['to'] ?? '' ) === str_repeat( '-', strlen( 'person1@example.com' ) ) );

echo "\n";
echo "What a mail and a mail to nobody have the same\n";

// One whole way of one mail, recorded: what queue() does to the disk, what the request does before its response (prepareAfter()), and what the work after
// the response does. The names of the temporary files are made at random and are one name in the record; everything else - the order, the file, the mode, the bytes - is kept
$normal = static fn( array $log ): array => array_map( static fn( array $line ): string => preg_replace( '/[0-9a-f]{12}\.tmp/', 'X.tmp', str_replace( $sandbox, '<sandbox>', implode( ' ', array_map( 'strval', $line ) ) ) ), $log );
$trace = static function( string $kind ) use ( &$appData, &$taken, $reset, $mail, $normal ): array {

	$reset();
	FaultFs::on();
	$id			= \Nino\Mail::queue( $appData, $mail( 1 ), $kind, 'login/same' );
	$queue	= FaultFs::$log;
	FaultFs::$log = [];
	$request	= [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>page</p>' ] ];
	$after		= \Nino\Http::prepareAfter( $appData, $request );
	$before		= FaultFs::$log;
	FaultFs::$log = [];

	foreach( $after as $work )
		$work();

	$work = FaultFs::$log;
	FaultFs::off();

	return [ 'id' => $id, 'queue' => $normal( $queue ), 'before' => $normal( $before ), 'work' => $normal( $work ), 'response' => $request['/nino/http/response'], 'after' => count( $after ), 'taken' => count( $taken ), 'counts' => \Nino\Mail::queued( $appData ) ];
};

// The first runs of each load the classes and make the folders: the record is of the runs after them
$trace( \Nino\Mail::CLIENT );
$trace( \Nino\Mail::VOID );
$real = $trace( \Nino\Mail::CLIENT );
$void = $trace( \Nino\Mail::VOID );

check( 'queue() does to the disk what it does for a mail: the same files read, locked and written in the same order, with the same bytes', $real['queue'] !== [] && $real['queue'] === $void['queue'] );
check( 'the request before its response asks the disk what it asks for a mail, and says the same: a length, a closed connection, one piece of work', $real['before'] !== [] && $real['before'] === $void['before'] && $real['response'] === $void['response'] && $real['after'] === 1 && $void['after'] === 1 && isset( $void['response']['header']['Content-Length'] ) === true && ( $void['response']['header']['Connection'] ?? '' ) === 'close' );
check( 'the work after the response does what it does for a mail: the same operations on the disk, the outbox is empty, the note and the file gone, the key kept', $real['work'] !== [] && $real['work'] === $void['work'] && $void['counts'] === $real['counts'] && $void['counts']['waiting'] === 0 && $void['counts']['failed'] === 0 && is_file( $path( \Nino\MailQueue::WAITING ) ) === false && str_contains( (string) file_get_contents( $path( \Nino\MailQueue::KEYS ) ), $void['id'] ) === true );
check( '...and the mail went out where it is a mail and went nowhere where it is a mail to nobody: the one difference of the whole way', $real['taken'] === 1 && $void['taken'] === 0 );

// The same through a request of the site: a route whose response callback queues, answered by the kernel
$signin = [ 'kind' => \Nino\Mail::CLIENT ];
$appData['/nino/http/routes']['GET://signin'] = [ 'uri' => '/signin', 'body' => '<p>we sent a code, if we could</p>' ];
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://signin', static function( array &$appData, array &$request ) use ( &$signin, $mail ): void {
	if( $signin['kind'] !== '' )
		\Nino\Mail::queue( $appData, $mail( 1 ), $signin['kind'], 'login/site' );
} );
$visit = static function( string $kind ) use ( &$appData, &$signin, $reset ): array {
	$reset();
	$signin['kind'] = $kind;
	$request = ninoVisit( $appData, '/signin' );
	\Nino\Http::response( $appData, $request );
	$after = \Nino\Http::prepareAfter( $appData, $request );

	return [ 'status' => $request['/nino/http/response']['statusCode'], 'header' => $request['/nino/http/response']['header'], 'body' => $request['/nino/http/response']['body'], 'after' => count( $after ) ];
};
$visit( \Nino\Mail::CLIENT );
$realVisit = $visit( \Nino\Mail::CLIENT );
$voidVisit = $visit( \Nino\Mail::VOID );
check( 'a request of the site that queues answers the same status, the same header lines with the same values and the same body, and asks for the same one piece of work after it', $realVisit['status'] === 200 && $realVisit['status'] === $voidVisit['status'] && $realVisit['header'] === $voidVisit['header'] && $realVisit['body'] === $voidVisit['body'] && $realVisit['after'] === 1 && $voidVisit['after'] === 1 && ( $voidVisit['header']['Connection'] ?? '' ) === 'close' );
$noMail = $visit( '' );
check( '...whereas the same request that queues nothing answers another response - no length, no closed connection, no work - which is what a feature gives away by calling queue() for a mail and not for none', $noMail['after'] === 0 && isset( $noMail['header']['Content-Length'] ) === false && $noMail['header'] !== $realVisit['header'] );
$reset();

// Time is told and never bound: the machines the suite runs on differ
$times = [ 'client' => [], 'void' => [] ];

foreach( [ 'client', 'void' ] as $kind ) {

	for( $i = 0; $i < 20; $i++ ) {

		$reset();
		$t = hrtime( true );
		\Nino\Mail::queue( $appData, $mail( 1 ), $kind === 'void' ? \Nino\Mail::VOID : \Nino\Mail::CLIENT, 'login/time' );
		\Nino\Mail::deliverQueued( $appData );
		$times[$kind][] = ( hrtime( true ) - $t ) / 1e6;
	}

	sort( $times[$kind] );
}

printf( "  (queue() and its delivery, median of 20: mail %.2f ms, mail to nobody %.2f ms)\n", $times['client'][10], $times['void'][10] );
$reset();

echo "\n";
echo "The cap of the client and the places of the visitors\n";

$appData['/nino/mail/cap'] = 3;
$kinds = [ \Nino\Mail::VOID, \Nino\Mail::CLIENT, \Nino\Mail::VOID ];
$ids = [];

foreach( $kinds as $i => $kind )
	$ids[] = \Nino\Mail::queue( $appData, $mail( $i + 1 ), $kind );

$over = \Nino\Mail::queue( $appData, $mail( 4 ), \Nino\Mail::CLIENT );
check( 'a mail to nobody is one try of the cap of the client, as a mail is: with a cap of three, a mail to nobody, a mail and a mail to nobody take it, and the fourth, of either kind, is refused as over the cap', count( array_filter( $ids ) ) === 3 && $over === '' && \Nino\Mail::rateLimited( $appData ) === true && \Nino\Mail::queueRefused( $appData ) === 'ratelimited' && \Nino\Mail::queue( $appData, $mail( 5 ), \Nino\Mail::VOID ) === '' && \Nino\Mail::queueRefused( $appData ) === 'ratelimited' && count( $waiting() ) === 3 );
check( '...and what the fourth of a mail to nobody said is what it said for the mail: the flag of the cap is set, and the system, which no cap of a client binds, is taken', \Nino\Mail::rateLimited( $appData ) === true && \Nino\Mail::queue( $appData, $mail( 6 ), \Nino\Mail::SYSTEM ) !== '' && \Nino\Mail::rateLimited( $appData ) === false );

$reset();
$ids = [];

for( $i = 1; $i <= \Nino\MailQueue::MAX_CLIENT; $i++ )
	$ids[] = \Nino\Mail::queue( $appData, $mail( $i ), $i % 2 === 0 ? \Nino\Mail::VOID : \Nino\Mail::CLIENT );

check( 'the places of the visitors are taken by mails and mails to nobody alike: sixty of either kind fill them, and the next of either kind is refused as full', count( array_filter( $ids ) ) === \Nino\MailQueue::MAX_CLIENT && \Nino\Mail::queue( $appData, $mail( 61 ), \Nino\Mail::VOID ) === '' && \Nino\Mail::queueRefused( $appData ) === 'full' && \Nino\Mail::queue( $appData, $mail( 62 ), \Nino\Mail::CLIENT ) === '' && \Nino\Mail::queueRefused( $appData ) === 'full' );
check( '...and the places of the system stay the system\'s: a mail of the system is taken, and no place of the whole outbox is taken by a mail to nobody beyond the sixty', \Nino\Mail::queue( $appData, $mail( 63 ), \Nino\Mail::SYSTEM ) !== '' && count( $waiting() ) === \Nino\MailQueue::MAX_CLIENT + 1 && count( array_filter( $waiting(), static fn( array $stored ): bool => preg_match( '/^-+$/', $stored['to'] ) === 1 ) ) === \Nino\MailQueue::MAX_CLIENT / 2 );

$result = \Nino\Mail::deliverQueued( $appData, 100 );
check( 'a delivery lets the mails to nobody go and sends the others: thirty went out, thirty were let go, the mail of the system went too - and none of them was a failure', $result['delivered'] === 31 && $result['void'] === 30 && $result['failed'] === 0 && count( $taken ) === 31 && $waiting() === [] );

echo "\n";
echo "The deliverer\n";

$reset();
\Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'login/void' );
$result = \Nino\Mail::deliverQueued( $appData );
$ignored = ninoWarnings();
check( 'a mail to nobody is taken, let go and settled: it leaves the outbox, the transport is not asked, and the result says void - not delivered', $result === [ 'delivered' => 0, 'void' => 1, 'retry' => 0, 'held' => 0, 'failed' => 0, 'unwritable' => false ] && $taken === [] && $waiting() === [] );
check( '...it is no failure and is none that anyone reads: the outbox counts none given up on, the log has no line, and no record of a mail given up on was written', \Nino\Mail::queued( $appData )['failed'] === 0 && $ignored === [] && is_file( $path( \Nino\MailQueue::FAILED ) ) === false );
check( '...and its key is kept for the time a key is kept: the same mail again is the mail that went out, answered with its id and queued no more', \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'login/void' ) !== '' && $waiting() === [] );

$reset();
$answer = false;
\Nino\Mail::send( $appData, 'probe@example.com', 'probe', 'probe', '' );
$answer = true;
$taken = [];
$before = \Nino\Mail::failure( $appData );
\Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID );
\Nino\Mail::deliverQueued( $appData );
$afterVoid = \Nino\Mail::failure( $appData );
\Nino\Mail::queue( $appData, $mail( 2 ), \Nino\Mail::CLIENT );
\Nino\Mail::deliverQueued( $appData );
check( 'a mail to nobody clears no failure of the dashboard: the record of mails that could not be sent stands after one, and goes after a mail that went out, which is the one thing the deliverer does for a mail that it does not for a mail to nobody', $before !== null && $afterVoid === $before && \Nino\Mail::failure( $appData ) === null );

$reset();
$ignored = ninoWarnings();
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'no-address' ], '*' );
\Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::CLIENT );
\Nino\Mail::deliverQueued( $appData );
$realSaid = ninoWarnings();
\Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID );
\Nino\Mail::deliverQueued( $appData );
$voidSaid = ninoWarnings();
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'noreply@example.org' ], '*' );
check( 'the log says the same for a mail to nobody as for a mail: a sender that is no address is said once for the one and once for the other, and the line is the same line', $realSaid !== [] && $realSaid === $voidSaid );
$reset();

echo "\n";
echo "The address is kept nowhere\n";

$secret = 'unknown-person-7731@example.net';
$id = \Nino\Mail::queue( $appData, [ 'to' => $secret ] + $mail( 1 ), \Nino\Mail::VOID, 'login/secret' );
check( 'while a mail to nobody waits, no file of the project holds the address, its hash or its local part', $id !== '' && $holders( $secret ) === [] && $holders( 'unknown-person-7731' ) === [] && $holders( hash( 'sha256', $secret ) ) === [] );
$contentHash = (string) ( array_values( $waiting() )[0]['content'] ?? '' );
\Nino\Mail::deliverQueued( $appData );
check( '...nor after it was let go: the keys, the note and the record hold none of it', $holders( 'unknown-person-7731' ) === [] && $holders( 'example.net' ) === [] && ninoWarnings() === [] );
$otherVoid = \Nino\Mail::queue( $appData, [ 'to' => 'another-5555@example.org' ] + $mail( 1 ), \Nino\Mail::VOID, 'login/secret' );
$otherVoidSaid = \Nino\Mail::queueRefused( $appData );
\Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::CLIENT, 'login/real' );
$otherReal = \Nino\Mail::queue( $appData, [ 'to' => 'x@example.org' ] + $mail( 3 ), \Nino\Mail::CLIENT, 'login/real' );
check( '...and the hash of its content holds the address keyed with the secret of the outbox: no plain hash of the mail, which could be tried with an address, is kept - and the same mail to another address under its key is a conflict, as it is for a mail', $contentHash !== hash( 'sha256', (string) json_encode( [ 'void', [ 'to' => $secret ] + $mail( 1 ) ] ) ) && $contentHash !== hash( 'sha256', (string) json_encode( [ 'void', [ 'to' => '' ] + $mail( 1 ) ] ) )
	&& $otherVoid === '' && $otherVoidSaid === 'conflict' && $otherReal === '' && \Nino\Mail::queueRefused( $appData ) === 'conflict' );
check( '...the secret is a file of the outbox of its own, of the mode 0600, which no backup carries: 64 hex digits, never changed by a second call', PHP_OS_FAMILY === 'Windows' || ( ( fileperms( $path( \Nino\MailQueue::SECRET ) ) & 0777 ) === 0600 && \Nino\MailQueue::secret( $appData ) === \Nino\MailQueue::secret( $appData ) && preg_match( '/^[0-9a-f]{64}$/', \Nino\MailQueue::secret( $appData ) ) === 1 && in_array( 'mail-secret.php', ( new \ReflectionClassConstant( \Nino\Backup::class, 'OUTBOX' ) )->getValue(), true ) === true ) );
$reset();

echo "\n";
echo "What it takes of a mail, and what it refuses\n";

$bad = [
	'an address that is none'		=> [ 'to' => 'nobody' ] + $mail( 1 ),
	'no address'								=> [ 'subject' => 's', 'body' => 'b', 'replyTo' => '' ],
	'a line break in the address'	=> [ 'to' => "a@example.com\nBcc: x@example.com" ] + $mail( 1 ),
	'a list as reply address'		=> [ 'replyTo' => 'a@example.com,b@example.com' ] + $mail( 1 ),
	'a text that is no utf-8'		=> [ 'body' => "\xff\xfe" ] + $mail( 1 ),
];
$refusals = [];

foreach( $bad as $mailToRefuse )
	$refusals[] = [ \Nino\Mail::queue( $appData, $mailToRefuse, \Nino\Mail::VOID ), \Nino\Mail::queueRefused( $appData ) ];

check( 'a mail to nobody is checked as a mail is, its address too: what is no mail is refused as invalid, and the address that was given has to be one', array_unique( array_map( 'json_encode', $refusals ) ) === [ json_encode( [ '', 'invalid' ] ) ] && $waiting() === [] );
check( '...a key of the wrong form is refused as for a mail, and so is a kind that is none', \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'login-one' ) === '' && \Nino\Mail::queueRefused( $appData ) === 'invalid' && \Nino\Mail::queue( $appData, $mail( 1 ), 'nobody' ) === '' && \Nino\Mail::queueRefused( $appData ) === 'invalid' );

$room = \Nino\MailQueue::MAX_BYTES - strlen( 'person1@example.com' ) - strlen( 'Your code 1' );
$exact = \Nino\Mail::queue( $appData, $mail( 1, str_repeat( 'x', $room ) ), \Nino\Mail::VOID );
$one = \Nino\Mail::queue( $appData, $mail( 1, str_repeat( 'x', $room + 1 ) ), \Nino\Mail::VOID );
check( 'the largest mail is the largest mail to nobody, and one byte more is refused as too large: the outbox grows no more than a mail makes it grow', $exact !== '' && $one === '' && \Nino\Mail::queueRefused( $appData ) === 'size' && count( $waiting() ) === 1 );
$reset();

$id1 = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'login/key' );
$conflict = \Nino\Mail::queue( $appData, $mail( 1, '<p>Code 654321</p>' ), \Nino\Mail::VOID, 'login/key' );
$conflictSaid = \Nino\Mail::queueRefused( $appData );
$realConflict = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::CLIENT, 'login/key' );
$realConflictSaid = \Nino\Mail::queueRefused( $appData );
check( 'the key holds what it holds for a mail: the same mail to nobody is that mail, another one under the key is a conflict, and a mail under the key of a mail to nobody is a conflict as well', $id1 !== '' && \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'login/key' ) === $id1 && $conflict === '' && $conflictSaid === 'conflict' && $realConflict === '' && $realConflictSaid === 'conflict' && count( $waiting() ) === 1 );

echo "\n";
echo "A feature that signs a visitor in, the way the manual writes it\n";

/**
 *	The fixture of the recipe: the same code in both branches (a fresh one each call, of the same length), the same text, the key made
 *	of the session and the address in both, and an answer that never depends on what queue() answered; what it logs holds no address
 */
final class SigninFixture {

	public const MEMBER = 'member@example.com';
	public const MEMBER_TWO = 'mmber2@example.com';

	public static function handle( array &$appData, string $address, string $sessionId ): string {

		$known	= in_array( $address, [ self::MEMBER, self::MEMBER_TWO ], true );
		$code		= (string) random_int( 100000, 999999 );
		$id			= \Nino\Mail::queue( $appData, [ 'to' => $address, 'subject' => 'Your code', 'body' => str_replace( '[[code]]', $code, '<p>Your code is [[code]]</p>' ), 'replyTo' => '' ],
			$known === true ? \Nino\Mail::CLIENT : \Nino\Mail::VOID, 'signin/'. hash( 'sha256', $sessionId. '|'. $address ) );

		if( $id === '' )
			trigger_error( 'Signin: no mail queued ('. \Nino\Mail::queueRefused( $appData ). ')', E_USER_WARNING );

		return 'If the address is known, a code is on its way.';
	}

	// What the manual tells a feature to ask before it gives the promise: does the server let the client go before the work after the response begins
	public static function mayPromise( array &$appData ): bool {

		return \Nino\Http::releasesResponse( $appData );
	}
}

// What a file of the secret holds, read from the disk
final class MailVoidSecretFile {

	public static function read( string $file ): string {

		clearstatcache();
		$stored = \Nino\MailQueue::decode( (string) @file_get_contents( $file ) );

		return is_array( $stored ) === true ? (string) ( $stored['secret'] ?? '' ) : '';
	}
}

// Two calls in one session, whatever the addresses are: what queue() answered, what the visitor was told, what was logged, what the disk was asked
$session = static function( array $addresses ) use ( &$appData, $reset, $normal, $path ): array {

	$reset();
	FaultFs::on();
	$said = [];
	$answers = [];

	foreach( $addresses as $address ) {

		$answers[] = SigninFixture::handle( $appData, $address, 'session-1' );
		$said[] = \Nino\Mail::queueRefused( $appData ) === '' ? 'taken' : \Nino\Mail::queueRefused( $appData );
	}

	$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>page</p>' ] ];
	$after = \Nino\Http::prepareAfter( $appData, $request );

	foreach( $after as $work )
		$work();

	$log = FaultFs::$log;
	FaultFs::off();

	return [ 'queue' => $said, 'answers' => $answers, 'log' => ninoWarnings(), 'ops' => $normal( $log ), 'header' => $request['/nino/http/response']['header'] ];
};
$member = SigninFixture::MEMBER;
// The addresses are as long as the member's: the size of the file follows the address, which the recipe keeps equal by one template and fields of the same length
$other = 'nobody@example.net';
$third = 'visitr@example.net';
$session( [ $member, $member ] );
$session( [ $other, $other ] );
$sameAddress = [ 'known twice' => $session( [ $member, $member ] ), 'unknown twice' => $session( [ $other, $other ] ) ];
$otherAddress = [ 'unknown, unknown' => $session( [ $other, $third ] ), 'unknown, member' => $session( [ $other, $member ] ), 'member, unknown' => $session( [ $member, $other ] ), 'member, other member' => $session( [ $member, SigninFixture::MEMBER_TWO ] ) ];
$shape = static fn( array $run ): string => (string) json_encode( [ $run['queue'], $run['answers'], $run['log'] ] );
check( 'two calls with the same address in one session are alike for a member and for an unknown address: the same answers of queue() - a conflict for the second, as the code is another - and the same words to the visitor', count( array_unique( array_map( $shape, $sameAddress ) ) ) === 1 && $sameAddress['known twice']['queue'] === [ 'taken', 'conflict' ] && count( array_unique( array_column( $sameAddress, 'answers' ), SORT_REGULAR ) ) === 1 );
check( '...and with another address the second call is a mail of its own whoever the first was: member or unknown, first or second, every sequence answers the same, and the log of each says the same', count( array_unique( array_map( static fn( array $run ): string => (string) json_encode( [ array_slice( $run['queue'], 0, 2 ), array_slice( $run['answers'], 0, 2 ) ] ), $otherAddress ) ) ) === 1 && array_slice( $otherAddress['unknown, member']['queue'], 0, 2 ) === [ 'taken', 'taken' ] );
check( '...the disk is asked the same in every sequence of two calls of the same kind of outcome: the same operations in the same order, whether the second call is a member or not', $sameAddress['known twice']['ops'] === $sameAddress['unknown twice']['ops'] && $otherAddress['unknown, member']['ops'] === $otherAddress['member, unknown']['ops'] && $otherAddress['unknown, member']['ops'] === $otherAddress['unknown, unknown']['ops'] && $otherAddress['unknown, member']['ops'] === $otherAddress['member, other member']['ops'] && $sameAddress['known twice']['header'] === $sameAddress['unknown twice']['header']  && $sameAddress['known twice']['header'] !== [] );
check( '...and what the visitor is told and what the log holds name no address, and no file of the project holds an address of an unknown visitor', str_contains( (string) json_encode( [ $sameAddress, $otherAddress ] ), 'example.net' ) === false && $holders( 'nobody@' ) === [] && $holders( 'visitr@' ) === [] );
$reset();

echo "\n";
echo "Where the disk is asked the same in the odd states, and the size is the same\n";

// The full outbox and a key that is known: the states in which a mail to nobody must ask the disk what a mail asks
$odd = static function( string $state, string $kind ) use ( &$appData, $reset, $mail, $normal ): array {

	$reset();
	$appData['/nino/mail/cap'] = 1000;

	if( $state === 'full' )
		for( $i = 1; $i <= \Nino\MailQueue::MAX_CLIENT; $i++ )
			\Nino\Mail::queue( $appData, $mail( $i ), \Nino\Mail::CLIENT );

	if( $state === 'known' )
		\Nino\Mail::queue( $appData, $mail( 1 ), $kind, 'odd/known' );

	FaultFs::on();
	$id = \Nino\Mail::queue( $appData, $mail( 1 ), $kind, $state === 'known' ? 'odd/known' : 'odd/new' );
	$log = FaultFs::$log;
	FaultFs::off();

	return [ 'id' => $id === '' ? \Nino\Mail::queueRefused( $appData ) : 'id', 'ops' => $normal( $log ) ];
};
$odd( 'full', \Nino\Mail::CLIENT );
$odd( 'known', \Nino\Mail::CLIENT );
$oddReal = [ 'full' => $odd( 'full', \Nino\Mail::CLIENT ), 'known' => $odd( 'known', \Nino\Mail::CLIENT ) ];
$oddVoid = [ 'full' => $odd( 'full', \Nino\Mail::VOID ), 'known' => $odd( 'known', \Nino\Mail::VOID ) ];
check( 'a full outbox and a key that is known are asked of the disk as for a mail: the same operations, the same answer - full, and the id of the mail that waits', $oddReal['full']['id'] === 'full' && $oddReal['known']['id'] === 'id' && $oddReal === $oddVoid && $oddReal['full']['ops'] !== [] );
$reset();

// The file is as large for a mail to nobody as for a mail, with the characters of an address that the file writes with an escape of their own
$sizes = [];

foreach( [ [ 'person@example.com' ], [ "o'neil@example.com" ], [ '"a\\"b"@example.com' ], [ '"a\\\\b"@example.com' ] ] as [ $address ] ) {

	foreach( [ \Nino\Mail::CLIENT, \Nino\Mail::VOID ] as $kind ) {

		$reset();
		$taken = [];
		\Nino\Mail::queue( $appData, [ 'to' => $address ] + $mail( 1 ), $kind, 'size/same' );
		$sizes[$address][] = filesize( $path( \Nino\MailQueue::WAITING ) );
	}
}

check( 'the file of the outbox is as large for a mail to nobody as for a mail of the same size, with a quote, a backslash or a quoted address in it, which the file writes with escapes of their own', count( $sizes ) === 4 && array_filter( $sizes, static fn( array $pair ): bool => $pair[0] !== $pair[1] ) === [] && $sizes["o'neil@example.com"][0] > $sizes['person@example.com'][0] - 1 );
// The one place the two kinds differ in what they take: a mail at the very limit whose address the file writes with escapes
$quoted = '"a\\"b"@example.com';
$room = \Nino\MailQueue::MAX_BYTES - strlen( $quoted ) - strlen( 'Your code 1' );
$reset();
$edgeReal = \Nino\Mail::queue( $appData, [ 'to' => $quoted ] + $mail( 1, str_repeat( 'x', $room ) ), \Nino\Mail::CLIENT );
$reset();
$edgeVoid = \Nino\Mail::queue( $appData, [ 'to' => $quoted ] + $mail( 1, str_repeat( 'x', $room ) ), \Nino\Mail::VOID );
check( 'the stand-in of an address that the file writes with escapes is longer than the address, and a mail to nobody of the very largest size with such an address is refused as too large where the mail is taken - the edge, as the manual says it', $edgeReal !== '' && $edgeVoid === '' && \Nino\Mail::queueRefused( $appData ) === 'size' );

// The secret is what this class wrote or it is made anew
$reset();
$good = \Nino\MailQueue::secret( $appData );
file_put_contents( $path( \Nino\MailQueue::SECRET ), \Nino\MailQueue::encode( [ 'format' => 1, 'secret' => 'short' ] ) );
$mended = \Nino\MailQueue::secret( $appData );
check( 'a secret that is not 64 hex digits is not believed: a new one is made, and kept', preg_match( '/^[0-9a-f]{64}$/', $mended ) === 1 && $mended !== 'short' && $mended !== $good && \Nino\MailQueue::secret( $appData ) === $mended );
file_put_contents( $path( \Nino\MailQueue::SECRET ), \Nino\MailQueue::encode( [ 'format' => 1, 'secret' => '' ] ) );
$blank = \Nino\MailQueue::secret( $appData );
check( '...and a secret that is empty is none: the keyed hash is never made with an empty key', preg_match( '/^[0-9a-f]{64}$/', $blank ) === 1 && \Nino\MailQueue::secret( $appData ) === $blank );

// The secret is made once and stays: what the file holds is what it held, whatever is queued after it
$before = (string) file_get_contents( $path( \Nino\MailQueue::SECRET ) );
$a = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'secret/kept' );
$b = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::VOID, 'secret/kept' );
check( 'the secret stays what it is while mails are queued: the file is byte for byte the same, and the same mail to nobody under its key is that mail, which a secret made anew each time would turn into a conflict', $a !== '' && $b === $a && (string) file_get_contents( $path( \Nino\MailQueue::SECRET ) ) === $before );

// Two processes that make the secret at once: the one that comes to the lock second finds the secret of the first and takes it. The second process is
// made by the test at the moment the first has looked and not found it - the read of the file after the lock is its read, and the file is there by then
$reset();
@unlink( $path( \Nino\MailQueue::SECRET ) );
$planted = str_repeat( 'ab', 32 );
$reads = 0;
FaultFs::on( [ 'read' => static function( string $file ) use ( &$reads, $planted ): bool {
	if( str_ends_with( $file, '/mail-secret.php' ) === true && ++$reads === 2 )
	{
		// The other process is a write the wrapper does not see
		FaultFs::off();
		file_put_contents( $file, \Nino\MailQueue::encode( [ 'format' => 1, 'secret' => $planted ] ) );
		stream_wrapper_unregister( 'file' );
		stream_wrapper_register( 'file', FaultFs::class );
	}

	return false;
} ] );
$raced = \Nino\MailQueue::secret( $appData );
FaultFs::off();
check( 'a secret that another process made between the look and the lock is the secret: the one that came second takes it and writes none of its own', $raced === $planted && \MailVoidSecretFile::read( $path( \Nino\MailQueue::SECRET ) ) === $planted );

// A secret that is there and cannot be read is never written over: every kind is refused as unwritable, and the file is left as it is
$reset();
$kept = \Nino\MailQueue::secret( $appData );
$bytes = (string) file_get_contents( $path( \Nino\MailQueue::SECRET ) );
FaultFs::on( [ 'read' => static fn( string $file ): bool => str_ends_with( $file, '/mail-secret.php' ) ] );
$refused = [];

foreach( [ \Nino\Mail::CLIENT, \Nino\Mail::SYSTEM, \Nino\Mail::VOID ] as $kind )
	$refused[] = [ \Nino\Mail::queue( $appData, $mail( 1 ), $kind ), \Nino\Mail::queueRefused( $appData ) ];

$writes = array_filter( FaultFs::$log, static fn( array $line ): bool => $line[0] === 'write' && str_contains( $line[1], 'mail-secret' ) === true || $line[0] === 'rename' && str_contains( (string) $line[2], 'mail-secret' ) === true );
FaultFs::off();
$ignored = ninoWarnings();
check( 'a secret that is there and cannot be read is never written over: a mail of every kind is refused as unwritable, nothing is written to its file, and the file is what it was', array_unique( array_map( 'json_encode', $refused ) ) === [ json_encode( [ '', 'unwritable' ] ) ] && $writes === [] && (string) file_get_contents( $path( \Nino\MailQueue::SECRET ) ) === $bytes && $kept !== '' );
$reset();
$reset();

echo "\n";
echo "A file a person edited\n";

$reset();
$put( [ $entry( [ 'kind' => 'void', 'to' => '---------' ] ) ] );
$result = \Nino\Mail::deliverQueued( $appData );
$ignored = ninoWarnings();
check( 'the kind void is no kind of the file: an entry that says it is given up on as one that is no mail, and nothing is let go for it as void', $result['failed'] === 1 && $result['void'] === 0 && $result['delivered'] === 0 && $taken === [] && \Nino\Mail::queued( $appData )['failed'] === 1 );

$reset();
$put( [ $entry( [ 'kind' => 'system', 'to' => '---------' ] ), $entry( [ 'kind' => 'test', 'to' => '---------' ] ) ] );
$result = \Nino\Mail::deliverQueued( $appData );
$ignored = ninoWarnings();
check( 'only a mail of the kind CLIENT is a mail to nobody: an entry of the system or of a test whose address is hyphens is given up on as no mail, and is neither let go nor sent', $result['failed'] === 2 && $result['void'] === 0 && $taken === [] && $waiting() === [] );

$reset();
$put( [ $entry( [ 'to' => '---------', 'replyTo' => 'a@example.com,b@example.com' ] ), $entry( [ 'to' => 'person1@example.com', 'replyTo' => 'a@example.com,b@example.com' ] ) ] );
$result = \Nino\Mail::deliverQueued( $appData );
$ignored = ninoWarnings();
check( 'the rest of an entry is checked when a mail to nobody is let go, as it is for a mail: one whose reply address is a list is given up on, the mail to nobody as the mail', $result['failed'] === 2 && $result['void'] === 0 && $result['delivered'] === 0 && $taken === [] );

$reset();
$put( [ $entry( [ 'to' => '-----@---' ] ), $entry( [ 'to' => '' ] ), $entry( [ 'to' => '--- ---' ] ) ] );
$result = \Nino\Mail::deliverQueued( $appData );
$ignored = ninoWarnings();
check( 'what is not hyphens alone is no mail to nobody: an entry with a hyphen and an at, none, or a blank is given up on, and nothing is let go', $result['failed'] === 3 && $result['void'] === 0 && $taken === [] );

$reset();

echo "\n";
echo "Where the server lets the response go\n";

// What the question of the manual answers on a real server of php that does not let the client go before the work: php -S. canAfterResponse() can answer yes there, since the
// response goes out by its length, and that is not what the promise of a mail to nobody needs (a real site, whose page is answered with nothing in the way, is told yes)
$router = $sandbox. '/router.php';
file_put_contents( $router, '<?php
require getenv( "NINO_ROOT" ) . "/_nino/Nino.php";
$appData = [];
echo "releases=", \Nino\Http::releasesResponse( $appData ) === true ? "1" : "0";
' );
$probe = stream_socket_server( 'tcp://127.0.0.1:0' );
$port = (int) substr( (string) stream_socket_get_name( $probe, false ), (int) strrpos( (string) stream_socket_get_name( $probe, false ), ':' ) + 1 );
fclose( $probe );
$server = proc_open( [ PHP_BINARY, '-d', 'sendmail_path=/bin/false', '-S', '127.0.0.1:'. $port, $router ], [ 1 => [ 'file', '/dev/null', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $serverPipes, $sandbox, [ 'NINO_ROOT' => dirname( __DIR__ ), 'PATH' => (string) getenv( 'PATH' ) ] );
$served = '';

// The server is there when it takes a connection: asked until it does (a fuse of ten seconds, no verdict)
for( $ready = 0; $ready < 200 && $served === ''; $ready++ ) {

	$socket = @stream_socket_client( 'tcp://127.0.0.1:'. $port, $errno, $errstr, 1 );

	if( is_resource( $socket ) === true ) {

		fwrite( $socket, "GET / HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n" );
		$served = (string) stream_get_contents( $socket );
		fclose( $socket );
	}
	else
		usleep( 50000 );
}

proc_terminate( $server );
check( 'under php -S, a real request, the server does not let the client go before the work: releasesResponse() answers false, which canAfterResponse() does not say of it', str_contains( $served, 'releases=0' ) === true && str_contains( $served, 'releases=1' ) === false );
check( 'the fixture feature asks the question the manual names, and its answer is the one of this process: no', SigninFixture::mayPromise( $appData ) === false );

// A response that stands for no mail, the work after it asked for a mail that waits - on a server that lets the response go the header is no other. Defined for the
// rest of the process, after every check that wants a server that does not (php's own command line has no such function)
if( function_exists( 'fastcgi_finish_request' ) === false ) {

	eval( 'function fastcgi_finish_request(): bool { return true; }' );

	$reset();
	$bare = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>page</p>' ] ];
	$none = \Nino\Http::prepareAfter( $appData, $bare );
	\Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::CLIENT );
	$mailed = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>page</p>' ] ];
	$first = \Nino\Http::prepareAfter( $appData, $mailed );
	$answer = false;
	\Nino\Mail::deliverQueued( $appData );
	$answer = true;
	$appData['./nino/mail/now'] = NOW + 65;
	$later = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>page</p>' ] ];
	$second = \Nino\Http::prepareAfter( $appData, $later );
	check( 'with a function that lets the client go, the question is yes, for the fixture feature as for the kernel', \Nino\Http::releasesResponse( $appData ) === true && SigninFixture::mayPromise( $appData ) === true );
	check( 'where the server lets the response go, a mail that waits is no header of any response: with the mail queued, and again when its transport failed and its next try is due, the response is the one of a site without mail - no length, no closed connection', \Nino\Http::canAfterResponse( $appData ) === true && $none === [] && count( $first ) === 1 && count( $second ) === 1 && $mailed === $bare && $later === $bare && $bare['/nino/http/response']['header'] === [] );
}

$reset();

ninoDone( $appData );
