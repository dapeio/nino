<?php
declare(strict_types=1);

/**
 *	Nino
 *	mail-queue-smoke.php	The outbox of \Nino\Mail: queue() keeps a mail, the requests that follow deliver it after their
 *												response, a transport that does not answer is tried again after waits that grow, and what
 *												cannot be delivered is given up on, said in the log and counted. A process that dies in the
 *												transport leaves the mail where it was, two deliverers never send one mail twice, the limits
 *												refuse at the call, a file a person edited is read with no trust, a backup carries none of
 *												it, and a site with an empty outbox loads none of its classes. The processes are real ones;
 *												the mails go to a transport of the test, and mail() is switched off.
 *
 *	Usage: php tests/mail-queue-smoke.php
 */

// No mail leaves this machine from here: the transport of the suite, registered below, takes every mail, and a suite that finds no transport ends before it
// sends one (sendmail_path cannot be set from a script - it is php.ini's - so mail() is never what stands between a test and a real mail). The processes
// of the suite are started with `-d sendmail_path=/bin/false` as well, which does reach mail(), and register a transport of their own
require __DIR__. '/harness.php';

$appData = ninoSandbox( 'mail-queue' );
$sandbox = ninoSandboxDir( $appData );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$root = dirname( __DIR__ );

// The clock of the outbox is a key of the request, set here and in every child
const NOW = 1900000000;
$appData['./nino/mail/now'] = NOW;
$appData['/nino/mail/cap'] = 0;

$taken = [];
$answer = true;
$throws = false;
$onSend = null;
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$taken, &$answer, &$throws, &$onSend ): void {
	if( $onSend !== null )
		$onSend();

	if( $throws === true )
		throw new \RuntimeException( 'smtp said secret@example.net' );

	$taken[] = $mail;
	$mail['sent'] = $answer;
} );

// The check before the first mail: the transport sits, and takes what it is given, or the suite ends here
if( count( array_filter( \Nino\Callbacks::registered( $appData, \Nino\Mail::TRANSPORT ) ) ) === 0 ) {
	echo "FAIL  - no test transport is registered: the suite ends before any mail is sent\n";
	exit( 1 );
}

$probe = [ 'to' => 'probe@example.com', 'subject' => 'probe', 'body' => 'probe', 'replyTo' => '' ];
check( 'the transport of the suite takes a mail before any other is sent: nothing of the suite goes to mail()', \Nino\Mail::send( $appData, $probe['to'], $probe['subject'], $probe['body'], '' ) === true && count( $taken ) === 1 && $taken[0]['to'] === 'probe@example.com' );
$taken = [];

$address = static fn( int $i ): string => 'person'. $i. '@example.com';
$mail = static fn( int $i = 1, string $body = 'Hello' ): array => [ 'to' => 'person'. $i. '@example.com', 'subject' => 'Subject '. $i, 'body' => $body, 'replyTo' => '' ];
$path = static fn( string $name ): string => \Nino\Filesystem::path( $appData, $name );
// Read from the file itself, never from the cache of this process: the children write it
$waiting = static function() use ( $path ): array {
	clearstatcache();
	$state = \Nino\MailQueue::decode( (string) @file_get_contents( $path( \Nino\MailQueue::WAITING ) ) );
	return is_array( $state ) === true ? array_column( (array) ( $state['waiting'] ?? [] ), null, 'id' ) : [];
};
$clock = static function( int $seconds ) use ( &$appData ): void { $appData['./nino/mail/now'] = NOW + $seconds; };
$reset = static function() use ( &$appData, &$taken, &$answer, &$throws, &$onSend, $path ): void {
	foreach( [ \Nino\MailQueue::WAITING, \Nino\MailQueue::DUE, \Nino\MailQueue::FAILED, \Nino\MailQueue::DAMAGED, \Nino\MailQueue::DAMAGED_FAILED, '/data/mail-status.php', '/data/ratelimit.php', '/data/sysmail-system.php', '/data/sysmail-test.php', '/data/mail-queue-unwritable.php' ] as $name ) {
		is_dir( $path( $name ) ) === true ? @rmdir( $path( $name ) ) : @unlink( $path( $name ) );
	}

	clearstatcache();
	$onSend = null;
	$appData['./nino/mail/now'] = NOW;
	$taken = [];
	$answer = true;
	$throws = false;
	ninoWarnings();
};

echo "Queueing\n";

$id = \Nino\Mail::queue( $appData, $mail( 1 ) );
check( 'queue() answers the id of the mail and sends nothing: it waits in a file below /data/, as a record that has all that delivering it takes', preg_match( '/^[0-9a-f]{16}$/', $id ) === 1 && $taken === []
	&& ( $waiting()[$id]['to'] ?? '' ) === $address( 1 ) && ( $waiting()[$id]['tries'] ?? -1 ) === 0 && str_starts_with( $path( \Nino\MailQueue::WAITING ), $sandbox. '/private/data/' ) === true );

$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
$after = \Nino\Http::prepareAfter( $appData, $request );
check( 'the response of a request that finds the outbox carries the work that delivers it, which the request runs after the response', count( $after ) === 1 && $taken === [] );
foreach( $after as $work )
	$work();
check( '...and the mail is delivered then, once, and the file of the outbox is gone: the next request pays no more than a stat', count( $taken ) === 1 && $taken[0]['to'] === $address( 1 ) && is_file( $path( \Nino\MailQueue::WAITING ) ) === false && is_file( $path( \Nino\MailQueue::DUE ) ) === false );
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
check( 'a request that finds no outbox is sent as it always was: no work, no length told', \Nino\Http::prepareAfter( $appData, $request ) === [] && isset( $request['/nino/http/response']['header']['Content-Length'] ) === false );

$reset();
$again = $mail( 2 );
$first = \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'order/17' );
check( 'the same mail queued twice with its key is one mail: the id that waits is answered, nothing is added', \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'order/17' ) === $first && count( $waiting() ) === 1 );
check( '...another mail under the key of a mail that waits is refused as a conflict, never answered with the id of the other', \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::CLIENT, 'order/17' ) === '' && \Nino\Mail::queueRefused( $appData ) === 'conflict' && count( $waiting() ) === 1 );
check( '...a key belongs to its caller: it begins with a name and a slash, and one that does not, or is no key, is refused', \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'order-17' ) === '' && \Nino\Mail::queueRefused( $appData ) === 'invalid' && \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, "o/\0" ) === '' && \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'Order/17' ) === '' );
check( '...a mail with another key, or none, is a mail of its own', \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'order/18' ) !== $first && \Nino\Mail::queue( $appData, $again ) !== '' && count( $waiting() ) === 3 );
$appData['./nino/mail/now'] = NOW;
\Nino\Mail::deliverQueued( $appData, 10 );
$sentBefore = count( $taken );
$twice = \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'order/17' );
check( '...a key is remembered for a few minutes once its mail is delivered: the same request again - a double click - is the mail that went out, answered with its id, and sends nothing', $twice === $first && count( $waiting() ) === 0 && count( $taken ) === $sentBefore && \Nino\Mail::deliverQueued( $appData )['delivered'] === 0 && count( $taken ) === $sentBefore );
check( '...another mail under that key is refused as a conflict', \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::CLIENT, 'order/17' ) === '' && \Nino\Mail::queueRefused( $appData ) === 'conflict' );
$clock( \Nino\MailQueue::KEY_MEMORY - 1 );
$stillThere = \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'order/17' ) === $first;
$clock( \Nino\MailQueue::KEY_MEMORY );
check( '...and for ten minutes, not a second more: after them it is a new mail', $stillThere === true && \Nino\Mail::queue( $appData, $again, \Nino\Mail::CLIENT, 'order/17' ) !== $first && count( $waiting() ) === 1 );
check( 'what is kept of a delivered mail is its key as a hash, the id and the hash of its content, and a time: no address, no subject, no text', preg_match( '/@|Subject|Hello/', (string) file_get_contents( $path( \Nino\MailQueue::KEYS ) ) ) !== 1 && str_contains( (string) file_get_contents( $path( \Nino\MailQueue::KEYS ) ), $first ) === true );
$appData['./nino/mail/now'] = NOW;

echo "\n";
echo "A request that is dropped, a request that follows\n";

$reset();
$app = $sandbox. '/child.php';
file_put_contents( $app, '<?php
declare(strict_types=1);
$root = $argv[1]; $sandbox = $argv[2]; $mode = $argv[3]; $now = (int) $argv[4]; $count = (int) ( $argv[5] ?? 1 );
require $root . "/tests/harness.php";
$appData = [ "./nino/uid" => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"] = $sandbox; $appData["./nino/filesystem/configpath"] = $sandbox . "/private"; $appData["./nino/filesystem/contentpath"] = $sandbox . "/private"; $appData["./nino/filesystem/publicpath"] = $sandbox . "/public";
$appData["/nino/dir"] = ""; $appData["/nino/install/texts"] = \Nino\VERSION; $appData["./nino/locales/current"] = "de_DE"; $appData["/nino/locales/native"] = "de_DE"; $appData["/nino/locales/available"] = [ "de_DE" ];
$appData["./nino/mail/now"] = $now; $appData["/nino/mail/cap"] = 0;
$_SERVER["REMOTE_ADDR"] = "127.0.0.1";
$log = static function( array $mail ) use ( $sandbox ): void { file_put_contents( $sandbox . "/transport.log", json_encode( [ $mail["to"], getmypid() ] ) . "\n", FILE_APPEND | LOCK_EX ); };
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( $log, $sandbox, $mode ): void {
	if( $mode === "hang" ) { touch( $sandbox . "/marker" ); sleep( 60 ); }
	$log( $mail );
	if( $mode === "sent-then-hang" ) { touch( $sandbox . "/marker" ); sleep( 60 ); }
	if( $mode === "slow" ) usleep( 150000 );
	$mail["sent"] = true;
} );
if( count( array_filter( \Nino\Callbacks::registered( $appData, \Nino\Mail::TRANSPORT ) ) ) === 0 ) { fwrite( STDERR, "no test transport\n" ); exit( 3 ); }
if( $mode === "queue" ) { for( $i = 1; $i <= $count; $i++ ) echo \Nino\Mail::queue( $appData, [ "to" => "m" . $argv[6] . $i . "@example.com", "subject" => "s", "body" => "b", "replyTo" => "" ] ), "\n"; exit; }
if( $mode === "output" || $mode === "dropped" ) {
	$response = [ "statusCode" => 200, "header" => [ "Content-Type" => "text/html; charset=utf-8" ], "body" => "<p>page</p>" ];
	if( $mode === "dropped" ) $response["header"]["Content-Length"] = "11";
	if( ( $argv[5] ?? "" ) === "queue" ) \Nino\Mail::queue( $appData, [ "to" => "late@example.com", "subject" => "s", "body" => "b", "replyTo" => "" ] );
	$request = [ "/nino/http/request" => [ "method" => "GET", "rawMethod" => "GET" ], "/nino/http/response" => $response ];
	\Nino\Http::output( $appData, $request );
}
echo json_encode( \Nino\Mail::deliverQueued( $appData, $count ) );
' );

/**
 *	One child process of the suite, to the end or - where it is told to hang - until the marker is there and it is killed
 *
 *	@return 	array										{ out, pid }
 */
$child = static function( string $mode, int $now, string $extra = '1', string $tag = 'a', bool $kill = false ) use ( $app, $root, $sandbox ): array {

	@unlink( $sandbox. '/marker' );
	$process = proc_open( [ PHP_BINARY, '-d', 'sendmail_path=/bin/false', $app, $root, $sandbox, $mode, (string) $now, $extra, $tag ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );

	if( $kill === true ) {

		// Waits for what it waits for, not for a time: the child says by a file that it is in the transport
		for( $i = 0; $i < 300 && is_file( $sandbox. '/marker' ) === false; $i++ )
			usleep( 50000 );

		proc_terminate( $process, 9 );
	}

	$out = stream_get_contents( $pipes[1] ). stream_get_contents( $pipes[2] );
	proc_close( $process );

	return [ 'out' => $out ];
};
$transportLog = static fn(): array => is_file( $sandbox. '/transport.log' ) === true ? array_map( static fn( string $line ): array => json_decode( $line, true ), array_filter( explode( "\n", (string) file_get_contents( $sandbox. '/transport.log' ) ) ) ) : [];
$resetLog = static function() use ( $sandbox ): void { @unlink( $sandbox. '/transport.log' ); };

$resetLog();
$dropped = $child( 'dropped', NOW, 'queue' );
$lateWaits = count( $waiting() );
check( 'a server that drops the work after the response leaves the mail where it was queued: it waits, and nothing was sent', $lateWaits === 1 && $transportLog() === [] );
$next = $child( 'output', NOW, '1' );
check( 'the next request delivers it after its response, and the outbox is empty again', array_column( $transportLog(), 0 ) === [ 'late@example.com' ] && $waiting() === [] && is_file( $path( \Nino\MailQueue::WAITING ) ) === false );

$reset();
$resetLog();
$child( 'output', NOW, 'queue' );
check( 'a request that queues a mail delivers it itself, after its own response', array_column( $transportLog(), 0 ) === [ 'late@example.com' ] );

echo "\n";
echo "A transport that does not answer\n";

$reset();
$answer = false;
$id = \Nino\Mail::queue( $appData, $mail( 4 ) );
$attemptsAt = [];
foreach( [ 0, 30, 60, 100, 360, 2159, 2160, 9360, 52559, 52560, 90000 ] as $seconds ) {

	$clock( $seconds );
	$before = count( $taken );
	\Nino\Mail::deliverQueued( $appData );

	if( count( $taken ) > $before )
		$attemptsAt[] = $seconds;
}
check( 'a mail the transport refuses is tried at once and then after 1 min, 5 min, 30 min, 2 h and 12 h - six attempts, none between', $attemptsAt === [ 0, 60, 360, 2160, 9360, 52560 ] && $waiting() === [] );
$counts = \Nino\Mail::queued( $appData );
$warnings = ninoWarnings();
$given = array_values( array_filter( $warnings, static fn( string $warning ): bool => str_starts_with( $warning, 'Mail queue' ) ) );
check( 'then it is given up on: counted with its time, said once in the log without its address or its words, and raised in the record the dashboard shows', $counts['failed'] === 1 && $counts['waiting'] === 0 && is_string( $counts['last'] ) === true
	&& count( $given ) === 1 && str_contains( $given[0], 'example.com' ) === false && str_contains( $given[0], 'Subject' ) === false && ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 1 );
$kept = (string) file_get_contents( $path( \Nino\MailQueue::FAILED ) ). (string) file_get_contents( $path( \Nino\MailQueue::DAMAGED ) );
check( '...and what is kept of it holds no address, no subject and no text of the mail', str_contains( $kept, 'example.com' ) === false && str_contains( $kept, 'Subject' ) === false && str_contains( $kept, 'Hello' ) === false && str_contains( (string) file_get_contents( $path( \Nino\MailQueue::FAILED ) ), $id ) === true );
check( 'the record of the outbox is the only trace in the file system of the mail: no file of the project holds its address', array_values( array_filter( glob( $sandbox. '/private/data/*' ) ?: [], static fn( string $file ): bool => is_file( $file ) === true && str_contains( (string) file_get_contents( $file ), $address( 4 ) ) === true ) ) === [] );

$reset();
$answer = false;
\Nino\Mail::queue( $appData, $mail( 5 ) );
\Nino\Mail::deliverQueued( $appData );
$answer = true;
$clock( 60 );
check( 'a transport that answers on the second attempt delivers the mail: it leaves the outbox and the failure of the first is not one', \Nino\Mail::deliverQueued( $appData )['delivered'] === 1 && $waiting() === [] && \Nino\Mail::queued( $appData )['failed'] === 0 && count( $taken ) === 2 );

$reset();
$answer = false;
\Nino\Mail::send( $appData, $address( 20 ), 's', 'b', '' );
$answer = true;
\Nino\Mail::queue( $appData, $mail( 21 ) );
check( 'a failure of a call of send() shown on the dashboard is cleared by a delivery of the outbox, as another call of send() would clear it', \Nino\Mail::failure( $appData ) !== null && \Nino\Mail::deliverQueued( $appData )['delivered'] === 1 && \Nino\Mail::failure( $appData ) === null );

$reset();
$throws = true;
\Nino\Mail::queue( $appData, $mail( 22 ) );
$result = \Nino\Mail::deliverQueued( $appData );
$throws = false;
check( 'a transport that throws is a mail that was not delivered, tried again later - and what it said is kept nowhere', $result['retry'] === 1 && count( $waiting() ) === 1 && array_values( $waiting() )[0]['error'] === \Nino\MailQueue::FAILURE && str_contains( (string) file_get_contents( $path( \Nino\MailQueue::WAITING ) ), 'secret@example.net' ) === false );

$reset();
\Nino\Mail::queue( $appData, $mail( 6 ) );
$clock( \Nino\MailQueue::MAX_AGE );
$expired = \Nino\Mail::deliverQueued( $appData );
check( 'a mail that waited longer than the limit is given up on, not sent and not dropped silently', $expired['failed'] === 1 && $taken === [] && \Nino\Mail::queued( $appData )['failed'] === 1 && count( array_filter( ninoWarnings(), static fn( string $warning ): bool => str_starts_with( $warning, 'Mail queue' ) ) ) === 1 );

$reset();
for( $i = 0; $i < \Nino\MailQueue::BATCH + 3; $i++ )
	\Nino\Mail::queue( $appData, $mail( $i ) );
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
foreach( \Nino\Http::prepareAfter( $appData, $request ) as $work )
	$work();
check( 'one request delivers a few mails after its response, not the whole outbox: the rest waits for the requests that follow', count( $taken ) === \Nino\MailQueue::BATCH && count( $waiting() ) === 3 );
check( 'what the outbox tells the operator holds no address, subject or text', preg_match( '/@|Subject|Hello/', (string) json_encode( \Nino\Mail::queued( $appData ) ) ) !== 1 && \Nino\Mail::queued( $appData )['waiting'] === 3 );

echo "\n";
echo "A process that dies, two that deliver\n";

$reset();
$resetLog();
\Nino\Mail::queue( $appData, $mail( 7 ) );
$child( 'hang', NOW, '1', 'a', true );
$left = $waiting();
check( 'a process killed in the transport leaves the mail in the outbox, its attempt counted', count( $left ) === 1 && array_values( $left )[0]['tries'] === 1 && $transportLog() === [] );
$clock( 70 );
check( 'the lease of the dead deliverer holds the outbox against another for its time, and no longer', \Nino\Mail::deliverQueued( $appData )['delivered'] === 0 && $taken === [] );
$clock( \Nino\MailQueue::LEASE + 1 );
check( '...then the mail is delivered, once', \Nino\Mail::deliverQueued( $appData )['delivered'] === 1 && count( $taken ) === 1 && $waiting() === [] );

$reset();
$resetLog();
\Nino\Mail::queue( $appData, $mail( 8 ) );
$child( 'sent-then-hang', NOW, '1', 'a', true );
$clock( \Nino\MailQueue::LEASE + 1 );
\Nino\Mail::deliverQueued( $appData );
check( 'a process killed after the transport took the mail but before the outbox let it go: the mail goes out a second time, and then not again', count( array_filter( $transportLog(), static fn( array $line ): bool => $line[0] === $address( 8 ) ) ) === 1 && count( $taken ) === 1 && $waiting() === [] );

$reset();
$resetLog();
$queuers = [];
foreach( [ 'a', 'b' ] as $tag )
	$queuers[$tag] = proc_open( [ PHP_BINARY, '-d', 'sendmail_path=/bin/false', $app, $root, $sandbox, 'queue', (string) NOW, '8', $tag ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes[$tag] );
foreach( $queuers as $tag => $process ) {
	stream_get_contents( $pipes[$tag][1] );
	proc_close( $process );
}
check( 'two processes that queue at once lose no mail: every one of the sixteen is in the file', count( $waiting() ) === 16 );

$slow = proc_open( [ PHP_BINARY, '-d', 'sendmail_path=/bin/false', $app, $root, $sandbox, 'slow', (string) NOW, '20', 'a' ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $slowPipes );

// The second deliverer starts when the first is in the transport, which it says by the first line of the log
for( $i = 0; $i < 300 && $transportLog() === []; $i++ )
	usleep( 20000 );

$second = $child( 'slow', NOW, '20', 'b' );
$firstOut = stream_get_contents( $slowPipes[1] ). stream_get_contents( $slowPipes[2] );
proc_close( $slow );
$sentTo = array_column( $transportLog(), 0 );
check( 'two deliverers at once send every mail exactly once: the second takes other mails than the first, never one the first has', count( $sentTo ) === 16 && count( array_unique( $sentTo ) ) === 16 && $waiting() === []
	&& ( json_decode( $second['out'], true )['delivered'] ?? -1 ) + ( json_decode( $firstOut, true )['delivered'] ?? -1 ) === 16 );

echo "\n";
echo "Limits\n";

$reset();
$refused = [];
for( $i = 0; $i < \Nino\MailQueue::MAX_CLIENT + 2; $i++ )
	if( \Nino\Mail::queue( $appData, $mail( $i ) ) === '' )
		$refused[] = \Nino\Mail::queueRefused( $appData );
check( 'mails of visitors may take only a part of the places: the next is refused at the call and says why - nothing is dropped, nothing queued over the part', $refused === [ 'full', 'full' ] && count( $waiting() ) === \Nino\MailQueue::MAX_CLIENT );
$system = 0;
for( $i = 0; $i < \Nino\MailQueue::MAX_WAITING; $i++ )
	if( \Nino\Mail::queue( $appData, $mail( 100 + $i ), \Nino\Mail::SYSTEM ) !== '' )
		$system++;
check( '...and the places the visitors cannot take are the system\'s: its mails are taken up to the limit of the whole outbox, and the one over it is refused as full', $system === \Nino\MailQueue::MAX_WAITING - \Nino\MailQueue::MAX_CLIENT && \Nino\Mail::queueRefused( $appData ) === 'full' && count( $waiting() ) === \Nino\MailQueue::MAX_WAITING );

$reset();
$big = \Nino\Mail::queue( $appData, $mail( 1, str_repeat( 'x', \Nino\MailQueue::MAX_BYTES ) ) );
check( 'a mail larger than the limit is refused as too large', $big === '' && \Nino\Mail::queueRefused( $appData ) === 'size' && is_file( $path( \Nino\MailQueue::WAITING ) ) === false );
check( '...and one that is no mail - an address that is none, a list of addresses, a kind nobody knows - is refused as invalid', \Nino\Mail::queue( $appData, [ 'to' => 'nobody' ] + $mail( 1 ) ) === '' && \Nino\Mail::queueRefused( $appData ) === 'invalid'
	&& \Nino\Mail::queue( $appData, [ 'to' => 'a@example.com,b@example.org' ] + $mail( 1 ) ) === '' && \Nino\Mail::queue( $appData, $mail( 1 ), 'bulk' ) === '' && \Nino\Mail::queueRefused( $appData ) === 'invalid'
	&& \Nino\Mail::queue( $appData, [ 'subject' => str_repeat( 's', 1000 ) ] + $mail( 1 ) ) === '' );

$reset();
$appData['/nino/mail/cap'] = 3;
$kept = \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::CLIENT, 'kk/1' );
\Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::CLIENT, 'kk/1' );
$two = \Nino\Mail::queue( $appData, $mail( 2 ) );
$three = \Nino\Mail::queue( $appData, $mail( 3 ) );
$over = \Nino\Mail::queue( $appData, $mail( 4 ) );
check( 'a mail of a client is charged to the cap of the client when it is queued, once per mail that is taken: the one over the cap is refused and says it was the cap', $kept !== '' && $two !== '' && $three !== '' && $over === '' && \Nino\Mail::rateLimited( $appData ) === true && \Nino\Mail::queueRefused( $appData ) === 'ratelimited' && count( $waiting() ) === 3 );
$appData['/nino/mail/cap'] = 0;

$reset();
for( $i = 0; $i < \Nino\Mail::SYSTEM_CAP + 3; $i++ )
	\Nino\Mail::queue( $appData, $mail( $i ), \Nino\Mail::SYSTEM );
$result = \Nino\Mail::deliverQueued( $appData, 100 );
check( 'the cap of the hour of the system holds: no more mails of its kind go out than the cap says, the others wait for the next hour and are no failure, and no attempt is counted for them', $result['delivered'] === \Nino\Mail::SYSTEM_CAP && $result['held'] === 3 && $result['failed'] === 0
	&& count( $waiting() ) === 3 && array_unique( array_column( $waiting(), 'tries' ) ) === [ 0 ] );
@unlink( $path( '/data/sysmail-system.php' ) );
$clock( \Nino\MailQueue::CAP_WAIT );
check( '...and go out when the hour is over', \Nino\Mail::deliverQueued( $appData, 100 )['delivered'] === 3 && $waiting() === [] );

$reset();
$one = [ 'to' => $address( 1 ), 'subject' => 's', 'body' => 'b', 'replyTo' => '' ];
$hash = hash( 'sha256', 'k' );
$both = [ \Nino\MailQueue::add( $appData, $one, \Nino\Mail::SYSTEM, $hash, $hash ), \Nino\MailQueue::add( $appData, $one, \Nino\Mail::SYSTEM, $hash, $hash ) ];
check( 'the outbox itself, under its lock, takes the mail of a key once: two requests at once with the same key are one mail', $both[0]['id'] !== '' && $both[0]['id'] === $both[1]['id'] && count( $waiting() ) === 1 );
for( $i = 1; $i < \Nino\MailQueue::MAX_WAITING; $i++ )
	\Nino\MailQueue::add( $appData, $one, \Nino\Mail::SYSTEM, '', $hash );
check( '...and under its lock it holds its limit: the mail that would be the next is refused, not queued', \Nino\MailQueue::add( $appData, $one, \Nino\Mail::SYSTEM, '', $hash ) === [ 'id' => '', 'refused' => 'full' ] && count( $waiting() ) === \Nino\MailQueue::MAX_WAITING );

$reset();
\Nino\MailQueue::add( $appData, $one, \Nino\Mail::SYSTEM, '', $hash );
$gave = 0;
$taken = [];
$unwritable = false;
$mine = \Nino\MailQueue::claim( $appData, 'aaaa', NOW, $gave, $unwritable );
\Nino\MailQueue::settle( $appData, 'bbbb', $mine['id'], 'again', \Nino\MailQueue::REFUSED, NOW );
check( 'what became of a mail is told by the deliverer that took it: another deliverer cannot change it', array_values( $waiting() )[0]['claim'] === 'aaaa' && array_values( $waiting() )[0]['error'] === '' );

echo "\n";
echo "What comes from outside\n";

$reset();
$appData['/nino/mail/cap'] = 0;
\Nino\Mail::queue( $appData, [ 'to' => $address( 1 ), 'subject' => "Hello\r\nBcc: y@example.org\0", 'body' => "Line\0 one", 'replyTo' => 'me example.com' ] );
$entry = array_values( $waiting() )[0] ?? [];
check( 'a line break and a nul byte in the subject, and a nul byte in the body, are taken out when the mail is queued, so that no header can be added by them', $entry !== [] && preg_match( "/[\\r\\n\\0]/", $entry['to']. $entry['subject']. $entry['body']. $entry['replyTo'] ) !== 1 && \Nino\Mail::queueRefused( $appData ) === '' );
$say = ninoWarnings();
check( '...a reply address that is none is left out with a line in the log that does not name it', $entry['replyTo'] === '' && count( $say ) === 1 && str_contains( $say[0], 'me example.com' ) === false );
$refusals = [];
foreach( [ 'to' => [ "a@example.com\r\nBcc: x@example.org", "a@example.com\0x", "a@example.com\n" ], 'replyTo' => [ "me@example.com\nBcc: z@example.org", 'x@example.org, y@example.org', "x@example.org\0" ], 'subject' => [ "bad \xff utf-8" ], 'body' => [ "bad \xc3 utf-8" ] ] as $field => $values )
	foreach( $values as $value )
		$refusals[] = \Nino\Mail::queue( $appData, [ $field => $value ] + $mail( 1 ) ) === '' && \Nino\Mail::queueRefused( $appData ) === 'invalid';
check( '...and what would be another address than the one given - a line break or a nul byte in an address, a reply address that is a list - or is no utf-8 is no mail: refused, where send() would strip it', array_unique( $refusals ) === [ true ] && count( $waiting() ) === 1 );

$reset();
$list = $path( \Nino\MailQueue::WAITING );
@mkdir( dirname( $list ), 0755, true );
$good = [ 'id' => '1234567890123456', 'key' => '', 'content' => str_repeat( 'a', 64 ), 'kind' => 'client', 'to' => $address( 9 ), 'subject' => "Edited\r\nBcc: y@example.org", 'body' => 'Hi', 'replyTo' => '', 'queued' => NOW, 'tries' => 0, 'next' => NOW, 'error' => '', 'claim' => '', 'until' => 0 ];
$entries = [
	$good,
	[ 'id' => '1123456789abcdef', 'to' => 'a@example.com,b@example.org' ] + $good,
	[ 'id' => '2123456789abcdef', 'to' => 'nobody' ] + $good,
	[ 'id' => '3123456789abcdef', 'body' => str_repeat( 'x', \Nino\MailQueue::MAX_BYTES + 1 ) ] + $good,
	[ 'id' => '../../etc/passwd' ] + $good,
	[ 'id' => '4123456789abcdef', 'tries' => 'many' ] + $good,
	'not an entry',
	[ 'id' => '5123456789abcdef', 'kind' => 'bulk' ] + $good,
	[ 'id' => '6123456789abcdef', 'key' => 'zz' ] + $good,
	[ 'id' => '7123456789abcdef', 'content' => 'zz' ] + $good,
	[ 'id' => '8123456789abcdef', 'to' => "a@example.com\nBcc: x@example.org" ] + $good,
];
file_put_contents( $list, \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => $entries ] ) );
$result = \Nino\Mail::deliverQueued( $appData, 100 );
check( 'a file a person edited is read with no trust: entries that are no entries, or no address, are given up on and counted, the one that is a mail goes out with its line break taken out', $result['delivered'] === 1 && $result['failed'] === 10 && count( $taken ) === 1 && $taken[0]['to'] === $address( 9 ) && str_contains( $taken[0]['subject'], "\n" ) === false && \Nino\Mail::queued( $appData )['failed'] === 10 );

$reset();
@mkdir( dirname( $list ), 0755, true );
file_put_contents( $list, '{"waiting": [ broken' );
$id = \Nino\Mail::queue( $appData, $mail( 1 ) );
check( 'a file that does not decode is put aside and the outbox starts over: the mail is queued, the damaged file is kept for a person to read, the log says so', $id !== '' && is_file( $path( \Nino\MailQueue::DAMAGED ) ) === true && count( $waiting() ) === 1
	&& str_contains( (string) file_get_contents( $path( \Nino\MailQueue::DAMAGED ) ), 'broken' ) === true && count( array_filter( ninoWarnings(), static fn( string $warning ): bool => str_starts_with( $warning, 'Mail queue' ) ) ) === 1 );

echo "\n";
echo "A disk that is full, a lease, a clock\n";

// The lock of the outbox is a directory, so that it cannot be taken: every write of the outbox fails, as it does on a disk that is full
$lockOf = static fn(): string => $path( '/data/.locks' ). '/'. sha1( \Nino\MailQueue::WAITING ). '.lock';
$reset();
\Nino\Mail::queue( $appData, $mail( 30 ) );
$lock = $lockOf();
@unlink( $lock );
mkdir( $lock );
$runs = [];
for( $i = 0; $i < 4; $i++ )
	$runs[] = \Nino\Mail::deliverQueued( $appData );
$said = array_values( array_filter( ninoWarnings(), static fn( string $warning ): bool => str_starts_with( $warning, 'Mail queue' ) ) );
check( 'an outbox that cannot be written sends nothing, however often it is asked: the mail waits, with no attempt counted, and the log says so once', $taken === [] && array_column( $runs, 'unwritable' ) === [ true, true, true, true ] && count( $said ) === 1 && array_values( $waiting() )[0]['tries'] === 0 );
rmdir( $lock );
check( '...and goes out when the outbox can be written again, once', \Nino\Mail::deliverQueued( $appData )['delivered'] === 1 && count( $taken ) === 1 );

$reset();
\Nino\Mail::queue( $appData, $mail( 31 ) );
$onSend = static function() use ( $lockOf ): void { @unlink( $lockOf() ); mkdir( $lockOf() ); };
$result = \Nino\Mail::deliverQueued( $appData );
$onSend = null;
$again = \Nino\Mail::deliverQueued( $appData );
check( 'a mail that went out and cannot be written down as gone ends the run, and the next run does not send it: nothing is sent where nothing can be written', $result['unwritable'] === true && $result['delivered'] === 0 && $again['delivered'] === 0 && count( $taken ) === 1 );
rmdir( $lockOf() );

// The note beside the outbox is no reason to hold a mail back: where it cannot be written it is removed, which says 'look', and the mail goes out
$reset();
\Nino\Mail::queue( $appData, $mail( 39 ) );
unlink( $path( \Nino\MailQueue::DUE ) );
mkdir( $path( \Nino\MailQueue::DUE ) );
$result = \Nino\Mail::deliverQueued( $appData );
rmdir( $path( \Nino\MailQueue::DUE ) );
check( 'a note that cannot be written holds nothing back: the mail is in the file, and it goes out', $result['delivered'] === 1 && count( $taken ) === 1 && $waiting() === [] );

// What the lease of a mail is, as the transport sees it
$reset();
\Nino\Mail::queue( $appData, $mail( 32 ) );
$seen = null;
$onSend = static function() use ( &$seen, $waiting ): void { $seen = array_values( $waiting() )[0] ?? null; };
\Nino\Mail::deliverQueued( $appData );
check( 'a mail is written as taken - the attempt counted, its lease the length of the lease from now, the deliverer named - before the transport is asked', is_array( $seen ) === true && $seen['tries'] === 1 && $seen['until'] === NOW + \Nino\MailQueue::LEASE && $seen['claim'] !== '' && $seen['next'] === NOW + 60 );

$reset();
@mkdir( dirname( $list ?? $path( \Nino\MailQueue::WAITING ) ), 0755, true );
$held = [ 'id' => '9123456789abcdef', 'key' => '', 'content' => str_repeat( 'b', 64 ), 'kind' => 'client', 'to' => $address( 33 ), 'subject' => 's', 'body' => 'b', 'replyTo' => '', 'queued' => NOW, 'tries' => 1, 'next' => NOW, 'error' => '', 'claim' => 'ghost', 'until' => NOW + \Nino\MailQueue::LEASE + 5000 ];
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ $held ] ] ) );
check( 'a lease that ends further away than a lease is long - a clock that was set back, a file somebody edited - holds nothing: the mail is delivered', \Nino\Mail::deliverQueued( $appData )['delivered'] === 1 );

$reset();
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ [ 'until' => NOW + 100 ] + $held ] ] ) );
check( '...and one that ends within its length holds the mail', \Nino\Mail::deliverQueued( $appData )['delivered'] === 0 && $taken === [] );

// A mail that belongs to a deliverer is left to it while another is taken
$reset();
$free = [ 'id' => '8123456789abcdef', 'to' => $address( 37 ), 'claim' => '', 'until' => 0 ] + $held;
$leased = [ 'id' => '7123456789abcdef', 'to' => $address( 38 ), 'claim' => 'ghost', 'until' => NOW + 100 ] + $held;
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ $leased, $free ] ] ) );
$result = \Nino\Mail::deliverQueued( $appData );
check( 'a mail that belongs to a deliverer for now is not taken by another while a free one is: the free one goes, the other waits for its lease', $result['delivered'] === 1 && array_column( $taken, 'to' ) === [ $address( 37 ) ] && array_keys( $waiting() ) === [ '7123456789abcdef' ] );

// The outbox is read from the disk, not from what this process read of it a moment ago, which the same size in the same second would leave as it was
$reset();
$hashA = hash( 'sha256', 'a' );
$hashB = hash( 'sha256', 'b' );
$file = $path( \Nino\MailQueue::WAITING );
@mkdir( dirname( $file ), 0755, true );
file_put_contents( $file, \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ [ 'key' => $hashA ] + $held ] ] ) );
$stamp = filemtime( $file );
$sawA = \Nino\MailQueue::peek( $appData, \Nino\Mail::CLIENT, $hashA )['id'];
file_put_contents( $file, \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ [ 'key' => $hashB ] + $held ] ] ) );
touch( $file, $stamp );
check( 'a file that another process wrote in the second of the last read, with the size it had, is read as it is now', $sawA !== '' && \Nino\MailQueue::peek( $appData, \Nino\Mail::CLIENT, $hashA )['id'] === '' && \Nino\MailQueue::peek( $appData, \Nino\Mail::CLIENT, $hashB )['id'] !== '' );

// Mails that wait for a time: a request does not see them
$reset();
$answer = false;
\Nino\Mail::queue( $appData, $mail( 34 ) );
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
$dueNow = count( \Nino\Http::prepareAfter( $appData, $request ) );
\Nino\Mail::deliverQueued( $appData );
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
$sleeping = \Nino\Http::prepareAfter( $appData, $request );
check( 'a mail that waits for its next attempt changes no response: no work is asked for, no length told, no connection closed - the response is the one of a site without mail', $dueNow === 1 && $sleeping === [] && $request['/nino/http/response']['header'] === [] && trim( (string) file_get_contents( $path( \Nino\MailQueue::DUE ) ) ) === (string) ( NOW + 60 ) );
$clock( 61 );
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
check( '...and the request after its time carries the work again', count( \Nino\Http::prepareAfter( $appData, $request ) ) === 1 );
@unlink( $path( \Nino\MailQueue::DUE ) );
$request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ];
$clock( 0 );
check( '...and so does one that finds no note: the outbox is asked, and writes the note again', count( \Nino\Http::prepareAfter( $appData, $request ) ) === 1 && \Nino\Mail::deliverQueued( $appData )['retry'] === 0 && trim( (string) @file_get_contents( $path( \Nino\MailQueue::DUE ) ) ) === (string) ( NOW + 60 ) );

// What the file holds of a text that php would read as something else
$reset();
$odd = [ 'to' => $address( 42 ), 'subject' => "It's a \\ \"test\" \\' ü / ' . \$x", 'body' => "<p>It's</p>\n\\\\", 'replyTo' => '' ];
$oddId = \Nino\Mail::queue( $appData, $odd );
\Nino\Mail::deliverQueued( $appData );
$queuedOdd = \Nino\Mail::queue( $appData, $odd );
check( 'quotes, backslashes and umlauts of a mail come out of the file as they went in', $oddId !== '' && count( $taken ) === 1 && $taken[0]['subject'] === $odd['subject'] && $taken[0]['body'] === $odd['body'] && $queuedOdd !== '' );

// A note that is not to be believed says 'look'
$reset();
$answer = false;
\Nino\Mail::queue( $appData, $mail( 41 ) );
\Nino\Mail::deliverQueued( $appData );
$noteFile = $path( \Nino\MailQueue::DUE );
$asks = static function() use ( &$appData ): int { $request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<p>ok</p>' ] ]; return count( \Nino\Http::prepareAfter( $appData, $request ) ); };
$plausible = $asks();
$verdicts = [];
foreach( [ (string) ( NOW + 86400 ), 'soon', '-5', ' ' ] as $text ) {
	file_put_contents( $noteFile, $text );
	touch( $noteFile, time() + 100 );
	$verdicts[] = $asks();
}
file_put_contents( $noteFile, (string) ( NOW + 60 ) );
touch( $noteFile, time() + 100 );
$believed = $asks();
touch( $noteFile, time() - 100 );
$older = $asks();
check( 'a note is believed where it is a time within the longest wait and not older than the file: and where it lies a day away, is no number, is negative, is blank or is older than the file, the outbox is asked, which writes it again', $plausible === 0 && $verdicts === [ 1, 1, 1, 1 ] && $believed === 0 && $older === 1 );
$clock( 61 );
\Nino\Mail::deliverQueued( $appData );
check( '...and the note the outbox writes is the one of what the file holds: the next attempt, after the file that holds it', trim( (string) file_get_contents( $noteFile ) ) === (string) ( NOW + 61 + 300 ) && filemtime( $noteFile ) >= filemtime( $path( \Nino\MailQueue::WAITING ) ) );

// The files are php that gives nothing to a direct call, and data that is read from the disk and never included
$reset();
\Nino\Mail::queue( $appData, $mail( 40 ) );
$file = $path( \Nino\MailQueue::WAITING );
check( 'the files that hold the mails are php files of one statement that returns their json: a direct call of one says nothing, and none of them is ever included', str_ends_with( \Nino\MailQueue::WAITING, '.php' ) === true && str_ends_with( \Nino\MailQueue::FAILED, '.php' ) === true && str_ends_with( \Nino\MailQueue::KEYS, '.php' ) === true
	&& str_starts_with( (string) file_get_contents( $file ), "<?php return '" ) === true && trim( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $file ). ' 2>&1' ) ) === '' && is_array( \Nino\MailQueue::decode( (string) file_get_contents( $file ) ) ) === true );

// What the limits are
$constants = ( new \ReflectionClass( \Nino\MailQueue::class ) )->getConstants();
check( 'the limits are the ones the manual names', [ $constants['MAX_WAITING'], $constants['MAX_CLIENT'], $constants['MAX_BYTES'], $constants['MAX_AGE'], $constants['KEEP_FAILED'], $constants['MAX_FAILED'], $constants['BACKOFF'], $constants['LEASE'], $constants['BATCH'], $constants['CAP_WAIT'], $constants['KEY_MEMORY'], $constants['MAX_KEYS'] ]
	=== [ 100, 60, 65536, 172800, 1209600, 500, [ 60, 300, 1800, 7200, 43200 ], 300, 5, 300, 600, 200 ] );
check( 'the span the request of a site believes a note of the outbox over is the longest wait and a lease', \Nino\Http::MAIL_NOTE_SPAN === \Nino\MailQueue::SPAN && \Nino\MailQueue::SPAN === max( \Nino\MailQueue::BACKOFF ) + \Nino\MailQueue::LEASE );

$reset();
$room = \Nino\MailQueue::MAX_BYTES - strlen( $address( 1 ) ) - strlen( 'Subject 1' );
check( 'a mail of exactly the largest size is taken, one byte more is refused', \Nino\Mail::queue( $appData, $mail( 1, str_repeat( 'x', $room ) ) ) !== '' && \Nino\Mail::queue( $appData, $mail( 1, str_repeat( 'x', $room + 1 ) ) ) === '' && \Nino\Mail::queueRefused( $appData ) === 'size' );

$reset();
$appData['/nino/mail/cap'] = 1;
$system = [ \Nino\Mail::queue( $appData, $mail( 1 ), \Nino\Mail::SYSTEM ), \Nino\Mail::queue( $appData, $mail( 2 ), \Nino\Mail::SYSTEM ), \Nino\Mail::queue( $appData, $mail( 3 ), \Nino\Mail::TEST ) ];
$appData['/nino/mail/cap'] = 0;
check( 'mails of the system are not charged to the cap of the client that set them off: with a cap of one, three of them are taken', in_array( '', $system, true ) === false && \Nino\Mail::rateLimited( $appData ) === false );

$reset();
$stale = [ 'id' => '9123456789abcdef', 'key' => '', 'content' => str_repeat( 'c', 64 ), 'kind' => 'client', 'to' => $address( 35 ), 'subject' => 's', 'body' => 'b', 'replyTo' => '', 'queued' => NOW, 'tries' => 3, 'next' => NOW + \Nino\MailQueue::MAX_AGE + 1000, 'error' => 'refused', 'claim' => '', 'until' => 0 ];
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ $stale ] ] ) );
$clock( \Nino\MailQueue::MAX_AGE - 1 );
$before = \Nino\Mail::deliverQueued( $appData );
$clock( \Nino\MailQueue::MAX_AGE );
$after = \Nino\Mail::deliverQueued( $appData );
check( 'a mail that waits for a late attempt is given up on when it is two days old, not a second before, and not by the clock of its attempt', $before['failed'] === 0 && $after['failed'] === 1 && $taken === [] );
$clock( \Nino\MailQueue::MAX_AGE + \Nino\MailQueue::KEEP_FAILED - 1 );
$kept = \Nino\Mail::queued( $appData )['failed'];
$clock( \Nino\MailQueue::MAX_AGE + \Nino\MailQueue::KEEP_FAILED );
check( 'what was given up on is kept for fourteen days, not a second more', $kept === 1 && \Nino\Mail::queued( $appData )['failed'] === 0 );

$reset();
$spent = [ 'tries' => \Nino\MailQueue::attempts(), 'next' => NOW + 500, 'error' => 'refused' ] + $stale;
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ $spent, [ 'id' => '5123456789abcdef', 'tries' => 0, 'next' => NOW, 'queued' => NOW ] + $stale ] ] ) );
$early = \Nino\Mail::deliverQueued( $appData );
$clock( 500 );
$late = \Nino\Mail::deliverQueued( $appData );
check( 'a mail whose last attempt was taken and not settled - the process died - is given up on when the wait of that attempt is over, and not before', $early['failed'] === 0 && $early['delivered'] === 1 && $late['failed'] === 1 && count( $taken ) === 1 );

// The file that is put aside is not kept for ever, and not lost to a race
$reset();
@mkdir( dirname( $path( \Nino\MailQueue::WAITING ) ), 0755, true );
file_put_contents( $path( \Nino\MailQueue::WAITING ), '{ not json' );
\Nino\Mail::queue( $appData, $mail( 36 ) );
touch( $path( \Nino\MailQueue::DAMAGED ), time() - \Nino\MailQueue::KEEP_FAILED - 10 );
\Nino\Mail::deliverQueued( $appData );
check( 'what was put aside is removed when it is fourteen days old', is_file( $path( \Nino\MailQueue::DAMAGED ) ) === false );

$reset();
$resetLog();
file_put_contents( $path( \Nino\MailQueue::WAITING ), '{ not json' );
$queuers = [];
foreach( [ 'a', 'b', 'c' ] as $tag )
	$queuers[$tag] = proc_open( [ PHP_BINARY, '-d', 'sendmail_path=/bin/false', $app, $root, $sandbox, 'queue', (string) NOW, '6', $tag ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes[$tag] );
foreach( $queuers as $tag => $process ) {
	stream_get_contents( $pipes[$tag][1] );
	proc_close( $process );
}
check( 'a file that cannot be read, found by several requests at once, is put aside once, under the lock: every mail they queued is in the new outbox', count( $waiting() ) === 18 && is_file( $path( \Nino\MailQueue::DAMAGED ) ) === true && str_contains( (string) file_get_contents( $path( \Nino\MailQueue::DAMAGED ) ), 'not json' ) === true );

// The empty file is removed under the lock the writers take
$reset();
\Nino\Filesystem::putFileContent( $appData, \Nino\MailQueue::WAITING, [ 'format' => 1, 'waiting' => [] ] );
$holder = proc_open( [ PHP_BINARY, '-r', 'require $argv[1]; $a = [ "./nino/uid" => $argv[2] ]; \Nino\AppData::prepare( $a ); $a["./nino/filesystem/path"] = $argv[2]; $a["./nino/filesystem/contentpath"] = $argv[2] . "/private"; \Nino\Filesystem::lockFile( $a, \Nino\MailQueue::WAITING ); touch( $argv[2] . "/held" ); sleep( 60 );', $root. '/_nino/Nino.php', $sandbox ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $holderPipes );
@unlink( $sandbox. '/held' );

for( $i = 0; $i < 300 && is_file( $sandbox. '/held' ) === false; $i++ )
	usleep( 20000 );

\Nino\MailQueue::release( $appData );
$keptWhileLocked = is_file( $path( \Nino\MailQueue::WAITING ) ) === true;
proc_terminate( $holder, 9 );
proc_close( $holder );
\Nino\MailQueue::release( $appData );
check( 'the file of an outbox with no mail is not removed while a writer holds its lock, and is removed when it does not', $keptWhileLocked === true && is_file( $path( \Nino\MailQueue::WAITING ) ) === false );

echo "\n";
echo "The backup, the outbox as a whole\n";

$reset();
\Nino\Mail::queue( $appData, $mail( 1 ) );
$clock( \Nino\MailQueue::MAX_AGE );
\Nino\Mail::deliverQueued( $appData );
$clock( \Nino\MailQueue::MAX_AGE + 1 );
\Nino\Mail::queue( $appData, $mail( 2 ) );
file_put_contents( $path( \Nino\MailQueue::DAMAGED ), '<?php return [];' );
\Nino\Filesystem::putFileContent( $appData, '/data/carried.php', [ 'x' => 1 ] );
$claims = $appData;
$claims['./nino/features/all'] = [ 'greedy' => [
	'key' => 'greedy', 'dir' => \Nino\Features::dir(). '/Greedy', 'data' => [ '/data/', '/data/mail-queue.php', '/data/mail-failed.php', '/data/mail-queue-damaged.php', '/data/mail-keys.php', '/data/carried.php' ],
	'active' => false, 'problems' => [], 'version' => '1.0.0', 'installed' => null, 'module' => '',
] ];
$names = array_map( static fn( string $name ): string => (string) preg_replace( '#/+#', '/', $name ), \Nino\Backup::manifest( $claims ) );
check( 'a backup carries neither the mails that wait nor the record of those given up on, whatever a manifest claims - a restore sends no old mail again', is_file( $path( \Nino\MailQueue::WAITING ) ) === true && is_file( $path( \Nino\MailQueue::FAILED ) ) === true
	&& in_array( 'data/mail-queue.php', $names, true ) === false && in_array( 'data/mail-failed.php', $names, true ) === false && in_array( 'data/mail-queue-damaged.php', $names, true ) === false && in_array( 'data/carried.php', $names, true ) === true );

$reflection = new \ReflectionClass( \Nino\Backup::class );
$never = $reflection->getReflectionConstant( 'NEVER' )->getValue();
$files = [ basename( \Nino\MailQueue::WAITING ), basename( \Nino\MailQueue::FAILED ), basename( \Nino\MailQueue::DAMAGED ), basename( \Nino\MailQueue::DAMAGED_FAILED ), basename( \Nino\MailQueue::KEYS ), basename( \Nino\MailQueue::DUE ) ];
check( 'the files the outbox writes are the ones the backup keeps out, and the file every request looks for is the file of the outbox', array_diff( $files, $never ) === [] && \Nino\Http::MAIL_QUEUE === \Nino\MailQueue::WAITING && \Nino\Http::MAIL_QUEUE === '/data/'. basename( \Nino\MailQueue::WAITING ) );

echo "\n";
echo "A site without mail\n";

$reset();
$plain = $sandbox. '/plain.php';
file_put_contents( $plain, '<?php
declare(strict_types=1);
$root = $argv[1]; $sandbox = $argv[2]; $queue = $argv[3];
require $root . "/tests/harness.php";
$appData = [ "./nino/uid" => $sandbox ];
\Nino\AppData::prepare( $appData );
$appData["./nino/filesystem/path"] = $sandbox; $appData["./nino/filesystem/configpath"] = $sandbox . "/private"; $appData["./nino/filesystem/contentpath"] = $sandbox . "/private"; $appData["./nino/filesystem/publicpath"] = $sandbox . "/public";
$appData["/nino/dir"] = ""; $appData["/nino/install/texts"] = \Nino\VERSION; $appData["./nino/locales/current"] = "de_DE"; $appData["/nino/locales/native"] = "de_DE"; $appData["/nino/locales/available"] = [ "de_DE" ];
if( $queue !== "0" ) {
	$appData["./nino/mail/now"] = 1000;
	$entry = [ "id" => "9123456789abcdef", "key" => "", "content" => str_repeat( "d", 64 ), "kind" => "client", "to" => "late@example.com", "subject" => "s", "body" => "b", "replyTo" => "", "queued" => 1000, "tries" => 1, "next" => 5000, "error" => "refused", "claim" => "", "until" => 0 ];
	$real = \Nino\Filesystem::path( $appData, "/data/mail-queue.php" ); @mkdir( dirname( $real ), 0755, true );
	file_put_contents( $real, "<?php return " . var_export( json_encode( [ "format" => 1, "waiting" => $queue === "2" ? [ $entry ] : [] ] ), true ) . ";" );
	if( $queue === "2" ) \Nino\Filesystem::putFileContent( $appData, "/data/mail-queue-due.txt", "5000" );
}
register_shutdown_function( static function(): void { echo json_encode( [ class_exists( "\\\\Nino\\\\Mail", false ), class_exists( "\\\\Nino\\\\MailQueue", false ), count( get_included_files() ) ] ); } );
$request = [ "/nino/http/request" => [ "method" => "GET", "rawMethod" => "GET" ], "/nino/http/response" => [ "statusCode" => 200, "header" => [ "Content-Type" => "text/html; charset=utf-8" ], "body" => "<p>page</p>" ] ];
\Nino\Http::output( $appData, $request );
' );
$run = static function( string $queue ) use ( $plain, $root, $sandbox ): array {
	@unlink( $sandbox. '/private/data/mail-queue.php' );
	@unlink( $sandbox. '/private/data/mail-queue-due.txt' );
	$out = (string) shell_exec( escapeshellarg( PHP_BINARY ). ' -d sendmail_path=/bin/false '. escapeshellarg( $plain ). ' '. escapeshellarg( $root ). ' '. escapeshellarg( $sandbox ). ' '. $queue. ' 2>&1' );
	return (array) json_decode( (string) substr( $out, (int) strrpos( $out, '[' ) ), true );
};
$none = $run( '0' );
$some = $run( '1' );
$sleeping = $run( '2' );
@unlink( $sandbox. '/private/data/mail-queue.php' );
@unlink( $sandbox. '/private/data/mail-queue-due.txt' );
echo 'files loaded: '. $none[2]. ' without an outbox, '. $sleeping[2]. ' with mails that wait for their time, '. $some[2]. ' with a mail that is due'. "\n";
check( 'a request of a site whose outbox is empty loads neither the mail class nor the classes of the outbox', $none[0] === false && $none[1] === false );
check( '...and so is a request of a site whose mails all wait for a time to come: it loads none of them either', $sleeping[0] === false && $sleeping[1] === false && $sleeping[2] === $none[2] );
check( '...and a request that finds a mail due loads them to deliver, after its response', $some[0] === true && $some[1] === true && $some[2] > $none[2] );

echo "\n";
echo "The command of the outbox\n";

$project = $sandbox. '/project';
\Nino\Filesystem::copyDir( $root. '/_nino', $project. '/_nino' );
@mkdir( $project. '/private/data', 0755, true );
file_put_contents( $project. '/private/config.php', '<?php return [];' );
file_put_contents( $sandbox. '/sendmail.sh', "#!/bin/sh\ncat >> ". $sandbox. "/sendmail.out\n" );
chmod( $sandbox. '/sendmail.sh', 0755 );
$cli = [ './nino/uid' => $project ];
\Nino\AppData::prepare( $cli );
foreach( [ 'path' => $project, 'configpath' => $project. '/private', 'contentpath' => $project. '/private', 'publicpath' => $project. '/public' ] as $name => $value )
	$cli['./nino/filesystem/'. $name] = $value;
$cli['/nino/mail/cap'] = 0;
\Nino\Mail::queue( $cli, $mail( 11 ) );
$out = (string) shell_exec( escapeshellarg( PHP_BINARY ). ' -d '. escapeshellarg( 'sendmail_path='. $sandbox. '/sendmail.sh' ). ' '. escapeshellarg( $project. '/_nino/Nino/MailQueue/deliver.php' ). ' 2>&1' );
clearstatcache();
check( 'the command boots the project and delivers what is due, with no visitor: it says what it did and the mail went to the transport of the server', str_starts_with( trim( $out ), 'delivered 1,' ) === true && str_contains( (string) @file_get_contents( $sandbox. '/sendmail.out' ), $address( 11 ) ) === true && is_file( $project. '/private/data/mail-queue.php' ) === false );

$command = static function( array $env ) use ( $project, $sandbox ): array {
	$process = proc_open( [ PHP_BINARY, '-d', 'sendmail_path='. $sandbox. '/sendmail.sh', $project. '/_nino/Nino/MailQueue/deliver.php' ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes, null, $env + [ 'PATH' => (string) getenv( 'PATH' ) ] );
	$out = stream_get_contents( $pipes[1] );
	$err = stream_get_contents( $pipes[2] );
	return [ trim( $out ), trim( $err ), proc_close( $process ) ];
};
mkdir( $sandbox. '/outside/data', 0755, true );
file_put_contents( $sandbox. '/outside/config.php', '<?php return [];' );
$away = [ './nino/uid' => $project ];
\Nino\AppData::prepare( $away );
foreach( [ 'path' => $project, 'configpath' => $sandbox. '/outside', 'contentpath' => $sandbox. '/outside', 'publicpath' => $project. '/public' ] as $name => $value )
	$away['./nino/filesystem/'. $name] = $value;
$away['/nino/mail/cap'] = 0;
\Nino\Mail::queue( $away, $mail( 13 ) );
[ $out, , $code ] = $command( [] );
$untouched = is_file( $sandbox. '/outside/data/mail-queue.php' ) === true && str_contains( (string) @file_get_contents( $sandbox. '/sendmail.out' ), $address( 13 ) ) === false;
[ $out2, , $code2 ] = $command( [ 'NINO_PRIVATE_DIR' => $sandbox. '/outside' ] );
clearstatcache();
check( 'the command finds a project whose private half is moved elsewhere as index.php does, by NINO_PRIVATE_DIR from the environment and no change of a file of the kernel: it delivers there, and where it is not told it does not', $untouched === true && str_starts_with( $out, 'delivered 0,' ) === true && str_starts_with( $out2, 'delivered 1,' ) === true && str_contains( $out2, 'waiting 0' ) === true && $code === 0 && $code2 === 0
	&& is_file( $sandbox. '/outside/data/mail-queue.php' ) === false && str_contains( (string) @file_get_contents( $sandbox. '/sendmail.out' ), $address( 13 ) ) === true );
[ $out3, $err3, $code3 ] = $command( [ 'NINO_PRIVATE_DIR' => $sandbox. '/nowhere' ] );
check( 'a command that cannot boot the project says why in one sentence on the error stream and ends with a code that is not 0, and prints no address', $code3 !== 0 && $out3 === '' && str_starts_with( $err3, 'mail queue:' ) === true && str_contains( $err3, 'example.com' ) === false );

$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
$port = (int) substr( (string) stream_socket_get_name( $socket, false ), strrpos( (string) stream_socket_get_name( $socket, false ), ':' ) + 1 );
fclose( $socket );
\Nino\Mail::queue( $cli, $mail( 12 ) );
$server = proc_open( [ PHP_BINARY, '-d', 'sendmail_path='. $sandbox. '/sendmail.sh', '-S', '127.0.0.1:'. $port, '-t', $project ], [ 1 => [ 'file', '/dev/null', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $serverPipes );
$status = 0;
$body = false;

for( $i = 0; $i < 100 && $status === 0; $i++ ) {
	$body = @file_get_contents( 'http://127.0.0.1:'. $port. '/_nino/Nino/MailQueue/deliver.php', false, stream_context_create( [ 'http' => [ 'ignore_errors' => true, 'timeout' => 5 ] ] ) );
	$status = (int) ( preg_match( '#^HTTP/\S+ (\d+)#', (string) ( $http_response_header[0] ?? '' ), $found ) === 1 ? $found[1] : 0 );

	if( $status === 0 )
		usleep( 50000 );
}

$direct = @file_get_contents( 'http://127.0.0.1:'. $port. '/private/data/mail-queue.php', false, stream_context_create( [ 'http' => [ 'ignore_errors' => true, 'timeout' => 5 ] ] ) );
$directStatus = (int) ( preg_match( '#^HTTP/\S+ (\d+)#', (string) ( $http_response_header[0] ?? '' ), $found ) === 1 ? $found[1] : 0 );
proc_terminate( $server );
proc_close( $server );
check( 'a webserver that serves private/ by mistake gives nothing of the outbox on a direct call: the answer is empty - no address, no subject, no text', $directStatus === 200 && $direct === '' && is_file( $project. '/private/data/mail-queue.php' ) === true );
check( 'asked for over http the command is nothing: it answers 404, delivers nothing and says nothing', $status === 404 && $body === '' && is_file( $project. '/private/data/mail-queue.php' ) === true && str_contains( (string) @file_get_contents( $sandbox. '/sendmail.out' ), $address( 12 ) ) === false );

echo "\n";
echo "The time of the answer\n";

// A server that has no fastcgi_finish_request() and a transport that waits at a gate until the test opens it: the mail a request queues does not hold the
// answer back - it is whole, with its length, before the transport has been asked - and goes out after it. Nothing is timed; the order of the two says it
@mkdir( $project. '/private/data', 0755, true );
file_put_contents( $project. '/probe.php', '<?php
declare(strict_types=1);
require __DIR__ . "/_nino/Nino.php";
$appData = \Nino\init();
if( isset( $_GET["mail"] ) ) \Nino\Mail::queue( $appData, [ "to" => "gate@example.com", "subject" => "s", "body" => "b", "replyTo" => "" ] );
$request = \Nino\request( $appData, $_SERVER );
\Nino\output( $appData, $request );
' );
file_put_contents( $sandbox. '/gate.sh', "#!/bin/sh\nfor i in \$(seq 1 200); do [ -f ". $sandbox. "/gate ] && break; sleep 0.05; done\ncat >> ". $sandbox. "/gated.out\n" );
chmod( $sandbox. '/gate.sh', 0755 );
@unlink( $sandbox. '/gate' );
@unlink( $sandbox. '/gated.out' );
@unlink( $project. '/private/data/mail-queue.php' );
$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
$port = (int) substr( (string) stream_socket_get_name( $socket, false ), strrpos( (string) stream_socket_get_name( $socket, false ), ':' ) + 1 );
fclose( $socket );
$server = proc_open( [ PHP_BINARY, '-d', 'sendmail_path='. $sandbox. '/gate.sh', '-S', '127.0.0.1:'. $port, $project. '/probe.php' ], [ 1 => [ 'file', '/dev/null', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $serverPipes, $project );
$headers = '';
$body = '';

for( $i = 0; $i < 200 && $headers === ''; $i++ ) {

	$connection = @stream_socket_client( 'tcp://127.0.0.1:'. $port, $errno, $errstr, 5 );

	if( is_resource( $connection ) === false ) {
		usleep( 50000 );
		continue;
	}

	fwrite( $connection, "GET /nothing?mail=1 HTTP/1.1\r\nHost: x\r\n\r\n" );

	while( ( $line = fgets( $connection ) ) !== false && $line !== "\r\n" )
		$headers .= $line;

	// What a browser does: it reads as many bytes as the length says and has the answer
	preg_match( '/^Content-Length: (\d+)/mi', $headers, $length );
	$body = isset( $length[1] ) === true ? (string) stream_get_contents( $connection, (int) $length[1] ) : '';
	$gated = is_file( $sandbox. '/gated.out' );
	fclose( $connection );
}

check( 'a request that queues a mail is answered whole, with its length and its connection closed, while the transport has not been asked: the answer does not wait for the mail', $headers !== '' && isset( $length[1] ) === true && (int) $length[1] === strlen( $body ) && stripos( $headers, 'Connection: close' ) !== false && $gated === false );
file_put_contents( $sandbox. '/gate', '1' );

for( $i = 0; $i < 200 && is_file( $sandbox. '/gated.out' ) === false; $i++ )
	usleep( 50000 );

proc_terminate( $server );
proc_close( $server );
check( '...and the mail goes out after the answer, by the same request', str_contains( (string) @file_get_contents( $sandbox. '/gated.out' ), 'gate@example.com' ) === true );

echo "\n";

ninoDone( $appData );
