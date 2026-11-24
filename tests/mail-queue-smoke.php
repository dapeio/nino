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

/**
 *	The file protocol of php for a moment, with a record of what is done and with the failures a test names: a read of a file that is
 *	there fails, a chmod, an unlink. Everything else is what php does itself
 */
final class FaultFs {

	public $context;

	// What was done, in order: [ operation, path, detail ]
	public static array $log = [];

	// operation => function( string $path ): bool, true to fail it
	public static array $fail = [];

	private $handle = null;
	private string $path = '';

	public static function on( array $fail = [] ): void {
		self::$log = [];
		self::$fail = $fail;
		stream_wrapper_unregister( 'file' );
		stream_wrapper_register( 'file', self::class );
	}

	public static function off(): void {
		stream_wrapper_restore( 'file' );
	}

	private static function real( callable $call ): mixed {
		stream_wrapper_restore( 'file' );

		try {
			return $call();
		}
		finally {
			stream_wrapper_unregister( 'file' );
			stream_wrapper_register( 'file', self::class );
		}
	}

	private static function fails( string $operation, string $path ): bool {
		return isset( self::$fail[$operation] ) === true && ( self::$fail[$operation] )( $path ) === true;
	}

	public function stream_open( string $path, string $mode, int $options, ?string &$opened ): bool {
		self::$log[] = [ 'open', $path, $mode, str_starts_with( $mode, 'x' ) === true ? self::real( static fn(): int => (int) ( @fileperms( dirname( $path ) ) & 0777 ) ) : -1 ];

		if( str_starts_with( $mode, 'r' ) === true && self::fails( 'read', $path ) === true )
			return false;

		$this->path = $path;
		$this->handle = self::real( static fn(): mixed => @fopen( $path, $mode ) );

		return $this->handle !== false;
	}

	public function stream_read( int $count ): string|false { return fread( $this->handle, $count ); }
	public function stream_write( string $data ): int { self::$log[] = [ 'write', $this->path, (string) strlen( $data ) ]; return (int) fwrite( $this->handle, $data ); }
	public function stream_eof(): bool { return feof( $this->handle ); }
	public function stream_tell(): int { return (int) ftell( $this->handle ); }
	public function stream_seek( int $offset, int $whence ): bool { return fseek( $this->handle, $offset, $whence ) === 0; }
	public function stream_flush(): bool { return fflush( $this->handle ); }
	public function stream_close(): void { fclose( $this->handle ); }
	public function stream_stat(): array|false { return fstat( $this->handle ); }
	public function stream_lock( int $operation ): bool { return flock( $this->handle, $operation ); }
	public function stream_truncate( int $size ): bool { return ftruncate( $this->handle, $size ); }
	public function stream_set_option( int $option, int $arg1, ?int $arg2 ): bool { return false; }

	public function url_stat( string $path, int $flags ): array|false {
		$stat = self::real( static fn(): mixed => ( $flags & STREAM_URL_STAT_LINK ) !== 0 ? @lstat( $path ) : @stat( $path ) );

		// 'uid': function( string $path ): ?int - the owner a path is said to have
		if( is_array( $stat ) === true && isset( self::$fail['uid'] ) === true && ( $uid = ( self::$fail['uid'] )( $path ) ) !== null ) {
			$stat['uid'] = $uid;
			$stat[4] = $uid;
		}

		return $stat;
	}

	public function unlink( string $path ): bool {
		self::$log[] = [ 'unlink', $path, '' ];

		return self::fails( 'unlink', $path ) === true ? false : self::real( static fn(): mixed => @unlink( $path ) );
	}

	public function rename( string $from, string $to ): bool {
		self::$log[] = [ 'rename', $from, $to ];

		return self::real( static fn(): mixed => @rename( $from, $to ) );
	}

	public function mkdir( string $path, int $mode, int $options ): bool {
		return self::real( static fn(): mixed => @mkdir( $path, $mode, ( $options & STREAM_MKDIR_RECURSIVE ) !== 0 ) );
	}

	public function rmdir( string $path, int $options ): bool {
		return self::real( static fn(): mixed => @rmdir( $path ) );
	}

	public function stream_metadata( string $path, int $option, mixed $value ): bool {
		if( $option === STREAM_META_ACCESS ) {
			self::$log[] = [ 'chmod', $path, decoct( (int) $value ) ];

			return self::fails( 'chmod', $path ) === true ? false : self::real( static fn(): mixed => @chmod( $path, (int) $value ) );
		}

		if( $option === STREAM_META_TOUCH )
			return self::real( static fn(): mixed => @touch( $path, ...( is_array( $value ) === true ? $value : [] ) ) );

		return false;
	}
}

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
// The files below the project that hold a text
$holders = static function( string $needle ) use ( $sandbox ): array {
	$found = [];

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $sandbox. '/private', \FilesystemIterator::SKIP_DOTS ) ) as $file )
		if( $file->isFile() === true && str_contains( (string) file_get_contents( $file->getPathname() ), $needle ) === true )
			$found[] = $file->getPathname();

	return $found;
};
$clock = static function( int $seconds ) use ( &$appData ): void { $appData['./nino/mail/now'] = NOW + $seconds; };
$reset = static function() use ( &$appData, &$taken, &$answer, &$throws, &$onSend, $path ): void {
	foreach( [ \Nino\MailQueue::WAITING, \Nino\MailQueue::DUE, \Nino\MailQueue::FAILED, \Nino\MailQueue::DAMAGED, \Nino\MailQueue::DAMAGED_FAILED, '/data/mail-status.php', '/data/ratelimit.php', '/data/sysmail-system.php', '/data/sysmail-test.php', '/data/mail-queue-unwritable.php', '/data/mail-queue-unreadable.php', '/data/mail-queue-keys.php', '/data/mail-queue-stuck.php', \Nino\MailQueue::DAMAGED_KEYS ] as $name ) {
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
$notice = \Nino\MailQueue::decode( (string) @file_get_contents( $path( \Nino\MailQueue::DAMAGED ) ) );
check( 'a file that does not decode is replaced by a notice and the outbox starts over: the mail is queued, the notice has the time, the size and the hash of what was there, the log says so', $id !== '' && count( $waiting() ) === 1
	&& ( $notice['damaged']['size'] ?? 0 ) === strlen( '{"waiting": [ broken' ) && ( $notice['damaged']['sha256'] ?? '' ) === hash( 'sha256', '{"waiting": [ broken' ) && is_int( $notice['damaged']['at'] ?? null ) === true
	&& count( array_filter( ninoWarnings(), static fn( string $warning ): bool => str_starts_with( $warning, 'Mail queue' ) ) ) === 1 );
check( '...and nothing of what was in it is kept: no file of the project holds a word of it, the notice is no more than a few bytes', $holders( 'broken' ) === [] && filesize( $path( \Nino\MailQueue::DAMAGED ) ) < 512 );

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

// A mail the outbox did not write is no mail it answers with an id for. The four ways a write fails with nothing to blame but the disk: a directory stands
// where the file belongs, the folder of the file cannot take it (its folder is a file - no mode, no user of the machine, makes that go away), the
// write itself fails (the place it is made for is seeded in the cache of the filesystem, which a failed write drops again), the lock cannot be taken
$seed = static function( string $name, string $real ) use ( &$appData ): void { $appData['./nino/filesystem/cache'][$name] = [ 'path' => $real, 'content' => '', 'fstat' => [] ]; };
$notFolder = $path( '/data/not-a-folder' );
$breaks = [
	'a directory in the place of the file' => [ static function() use ( $path ): void { @unlink( $path( \Nino\MailQueue::WAITING ) ); mkdir( $path( \Nino\MailQueue::WAITING ) ); }, static function() use ( $path ): void { rmdir( $path( \Nino\MailQueue::WAITING ) ); } ],
	'a folder that is a file' => [ static function() use ( $seed, $notFolder ): void { touch( $notFolder ); $seed( \Nino\MailQueue::WAITING, $notFolder. '/mail-queue.php' ); }, static function() use ( $notFolder ): void { unlink( $notFolder ); } ],
	'a write that fails' => [ static function() use ( $seed, $path ): void { $seed( \Nino\MailQueue::WAITING, $path( '/data' ). '/no-such-folder/mail-queue.php' ); }, static function(): void {} ],
	'a lock that cannot be taken' => [ static function() use ( $lockOf ): void { @unlink( $lockOf() ); mkdir( $lockOf() ); }, static function() use ( $lockOf ): void { rmdir( $lockOf() ); } ],
];
foreach( $breaks as $label => [ $break, $mend ] ) {

	$reset();
	$kept = \Nino\Mail::queue( $appData, $mail( 50 ) );
	$before = $label === 'a directory in the place of the file' ? [] : $waiting();
	$break();
	$queued = \Nino\Mail::queue( $appData, $mail( 51 ) );
	$refused = \Nino\Mail::queueRefused( $appData );
	\Nino\Mail::deliverQueued( $appData );
	$mend();
	unset( $appData['./nino/filesystem/cache'][\Nino\MailQueue::WAITING] );
	$after = $waiting();
	check( 'queue() says unwritable, with no id, where the outbox cannot be written - '. $label. ': the mail is not in the file, nothing is sent, and the mails that wait are as they were', $kept !== '' && $queued === '' && $refused === 'unwritable' && in_array( $address( 51 ), array_column( $taken, 'to' ), true ) === false && in_array( $address( 51 ), array_column( $after, 'to' ), true ) === false && array_diff_key( $after, $before ) === [] );
	check( '...and it is not a mail the outbox thinks it holds: once the disk is as it was, the same mail is queued as a new one, and goes out once', \Nino\Mail::queue( $appData, $mail( 51 ) ) !== '' && \Nino\Mail::queueRefused( $appData ) === '' && in_array( $address( 51 ), array_column( $waiting(), 'to' ), true ) === true && \Nino\Mail::deliverQueued( $appData, 10 )['unwritable'] === false && count( array_keys( array_column( $taken, 'to' ), $address( 51 ) ) ) === 1 && $waiting() === [] );
}

// A claim that cannot be written down sends nothing, where the lock is taken and the write is what fails
$reset();
\Nino\Mail::queue( $appData, $mail( 52 ) );
$seed( \Nino\MailQueue::WAITING, $path( '/data' ). '/no-such-folder/mail-queue.php' );
$result = \Nino\Mail::deliverQueued( $appData );
check( 'a mail that cannot be written down as taken is not sent: the write that fails, not the lock, ends the run with no attempt counted', $result['unwritable'] === true && $result['delivered'] === 0 && $taken === [] && array_values( $waiting() )[0]['tries'] === 0 );
unset( $appData['./nino/filesystem/cache'][\Nino\MailQueue::WAITING] );

// The key of a mail is kept so that a double click is one mail, and where it cannot be kept the mail has still gone: a mail that stays is sent again
$reset();
@unlink( $path( \Nino\MailQueue::KEYS ) );
mkdir( $path( \Nino\MailQueue::KEYS ) );
\Nino\Mail::queue( $appData, $mail( 53 ), \Nino\Mail::CLIENT, 'order/53' );
$first = \Nino\Mail::deliverQueued( $appData );
$clock( \Nino\MailQueue::BACKOFF[0] );
$second = \Nino\Mail::deliverQueued( $appData );
rmdir( $path( \Nino\MailQueue::KEYS ) );
check( 'a mail whose key cannot be kept is delivered and leaves the outbox: it is not sent a second time after its wait', $first['delivered'] === 1 && $first['unwritable'] === false && $second['delivered'] === 0 && count( $taken ) === 1 && $waiting() === [] );

// The record of a mail given up on cannot be written: the mail stays, and is given up on with the next run - one record, not one for every run
$reset();
\Nino\Mail::queue( $appData, $mail( 54 ) );
$answer = false;
for( $i = 0; $i < \Nino\MailQueue::attempts() - 1; $i++ ) {
	\Nino\Mail::deliverQueued( $appData );
	$clock( array_sum( array_slice( \Nino\MailQueue::BACKOFF, 0, $i + 1 ) ) );
}
mkdir( $path( \Nino\MailQueue::FAILED ) );
$last = \Nino\Mail::deliverQueued( $appData );
$stayed = count( $waiting() );
rmdir( $path( \Nino\MailQueue::FAILED ) );
$clock( array_sum( \Nino\MailQueue::BACKOFF ) + \Nino\MailQueue::BACKOFF[4] + 1 );
$next = \Nino\Mail::deliverQueued( $appData );
check( 'a mail that is out of attempts is not lost where the record of it cannot be written: it stays, the run says unwritable, and the next run gives up on it once, with its record', $last['unwritable'] === true && $stayed === 1 && $next['unwritable'] === false && $next['failed'] === 1 && $waiting() === [] && \Nino\Mail::queued( $appData )['failed'] === 1 );
$answer = true;
$appData['./nino/mail/now'] = NOW;

// The mode of the files
$reset();
@unlink( $path( \Nino\MailQueue::KEYS ) );
@mkdir( dirname( $path( \Nino\MailQueue::WAITING ) ), 0755, true );
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [] ] ) );
chmod( $path( \Nino\MailQueue::WAITING ), 0644 );
$mode = static fn( string $name ): int => is_file( $path( $name ) ) === true ? (int) ( fileperms( $path( $name ) ) & 0777 ) : -1;
\Nino\Mail::queue( $appData, $mail( 55 ), \Nino\Mail::CLIENT, 'order/55' );
$modes = [ 'waiting' => $mode( \Nino\MailQueue::WAITING ), 'note' => $mode( \Nino\MailQueue::DUE ) ];
\Nino\Mail::queue( $appData, $mail( 56 ) );
$answer = false;
for( $i = 0; $i < \Nino\MailQueue::attempts(); $i++ ) {
	\Nino\Mail::deliverQueued( $appData );
	$clock( array_sum( array_slice( \Nino\MailQueue::BACKOFF, 0, $i + 1 ) ) + 1 );
}
$answer = true;
$modes['failed'] = $mode( \Nino\MailQueue::FAILED );
$appData['./nino/mail/now'] = NOW;
\Nino\Mail::queue( $appData, $mail( 57 ), \Nino\Mail::CLIENT, 'order/57' );
\Nino\Mail::deliverQueued( $appData );
$modes['keys'] = $mode( \Nino\MailQueue::KEYS );
check( 'every file of the outbox has the mode 0600 - the mails that wait, the note of the time, the record of those given up on, the keys - and a file that was there with 0644 has it after its next write', PHP_OS_FAMILY === 'Windows' || ( $modes === [ 'waiting' => 0600, 'note' => 0600, 'failed' => 0600, 'keys' => 0600 ] && glob( dirname( $path( \Nino\MailQueue::WAITING ) ). '/*.tmp' ) === [] ) );

$reset();
@unlink( $path( \Nino\MailQueue::KEYS ) );
foreach( [ \Nino\MailQueue::DAMAGED, \Nino\MailQueue::DAMAGED_FAILED ] as $name ) {
	file_put_contents( $path( $name ), 'old' );
	chmod( $path( $name ), 0644 );
}
file_put_contents( $path( \Nino\MailQueue::WAITING ), 'bad leak@example.org' );
$id = \Nino\Mail::queue( $appData, $mail( 58 ) );
$gave = 0;
$unwritable = false;
$taking = \Nino\MailQueue::claim( $appData, 'aaaa', NOW, $gave, $unwritable );
file_put_contents( $path( \Nino\MailQueue::FAILED ), 'bad leak@example.net' );
\Nino\MailQueue::settle( $appData, 'aaaa', $id, 'invalid', '', NOW );
$modes = [ $mode( \Nino\MailQueue::DAMAGED ), $mode( \Nino\MailQueue::DAMAGED_FAILED ), $mode( \Nino\MailQueue::FAILED ) ];
check( 'the notice of a file that cannot be read has the mode 0600 as well, in the outbox and in the record of the mails given up on, and none of them holds an address', PHP_OS_FAMILY === 'Windows' || $modes === [ 0600, 0600, 0600 ] );
check( '...the notice of each is that of the file that was there, and no file of the project holds a word of what was in them', ( \Nino\MailQueue::decode( (string) file_get_contents( $path( \Nino\MailQueue::DAMAGED_FAILED ) ) )['damaged']['sha256'] ?? '' ) === hash( 'sha256', 'bad leak@example.net' ) && $holders( 'leak@example' ) === [] );

// A call that finds the damaged file and writes nothing leaves the outbox started over all the same: the file is gone, and the log says it once
$reset();
file_put_contents( $path( \Nino\MailQueue::WAITING ), '{ not json leak@example.org' );
\Nino\MailQueue::settle( $appData, 'aaaa', '1234567890123456', 'sent', '', NOW );
$said = count( ninoWarnings() );
\Nino\MailQueue::settle( $appData, 'aaaa', '1234567890123456', 'sent', '', NOW );
check( 'a damaged file that a call finds and does not rewrite is replaced by its notice all the same: it is gone, the log says it once', is_file( $path( \Nino\MailQueue::WAITING ) ) === false && $said === 1 && ninoWarnings() === [] && $holders( 'leak@example' ) === [] );

// A file that a version kept of the damaged file - what people wrote in it - goes with the next delivery, whatever its age; a notice goes when it is fourteen days old
$reset();
file_put_contents( $path( \Nino\MailQueue::DAMAGED ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ [ 'to' => 'kept@example.org' ] ] ] ) );
file_put_contents( $path( \Nino\MailQueue::DAMAGED_FAILED ), 'a file that was kept: kept@example.org' );
\Nino\Mail::queue( $appData, $mail( 60 ) );
\Nino\Mail::deliverQueued( $appData );
check( 'a file kept of a damaged one by an earlier outbox is removed with the next delivery, however young: no address of it stays on the disk', is_file( $path( \Nino\MailQueue::DAMAGED ) ) === false && is_file( $path( \Nino\MailQueue::DAMAGED_FAILED ) ) === false && $holders( 'kept@example' ) === [] );

$reset();
file_put_contents( $path( \Nino\MailQueue::WAITING ), 'broken' );
\Nino\Mail::queue( $appData, $mail( 61 ) );
\Nino\Mail::deliverQueued( $appData );
$young = is_file( $path( \Nino\MailQueue::DAMAGED ) ) === true;
touch( $path( \Nino\MailQueue::DAMAGED ), time() - \Nino\MailQueue::KEEP_FAILED + 100 );
\Nino\Mail::queue( $appData, $mail( 62 ) );
\Nino\Mail::deliverQueued( $appData );
$nearly = is_file( $path( \Nino\MailQueue::DAMAGED ) ) === true;
touch( $path( \Nino\MailQueue::DAMAGED ), time() - \Nino\MailQueue::KEEP_FAILED );
\Nino\Mail::queue( $appData, $mail( 63 ) );
\Nino\Mail::deliverQueued( $appData );
check( 'the notice of a file that could not be read stays for fourteen days and not a second more', $young === true && $nearly === true && is_file( $path( \Nino\MailQueue::DAMAGED ) ) === false );

// The other files of the filesystem keep the mode they have: the mode of the outbox is the call's own
\Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'a' );
chmod( $path( '/data/mode-probe.php' ), 0640 );
\Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'b' );
$kept = (int) ( fileperms( $path( '/data/mode-probe.php' ) ) & 0777 );
\Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'c', false, 0600 );
$named = (int) ( fileperms( $path( '/data/mode-probe.php' ) ) & 0777 );
unlink( $path( '/data/mode-probe.php' ) );
check( 'a file written with no mode keeps the one it has, and one written with a mode has it whatever it had', PHP_OS_FAMILY === 'Windows' || ( $kept === 0640 && $named === 0600 ) );

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

// The notice of a file that could not be read is not kept for ever, and the file is not lost to a race
$reset();
@mkdir( dirname( $path( \Nino\MailQueue::WAITING ) ), 0755, true );
file_put_contents( $path( \Nino\MailQueue::WAITING ), '{ not json' );
\Nino\Mail::queue( $appData, $mail( 36 ) );
touch( $path( \Nino\MailQueue::DAMAGED ), time() - \Nino\MailQueue::KEEP_FAILED - 10 );
\Nino\Mail::deliverQueued( $appData );
check( 'the notice of a damaged file is removed when it is fourteen days old', is_file( $path( \Nino\MailQueue::DAMAGED ) ) === false );

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
$notice = \Nino\MailQueue::decode( (string) @file_get_contents( $path( \Nino\MailQueue::DAMAGED ) ) );
check( 'a file that cannot be read, found by several requests at once, is replaced once, under the lock: every mail they queued is in the new outbox, and the notice is that of the file that was damaged', count( $waiting() ) === 18 && ( $notice['damaged']['sha256'] ?? '' ) === hash( 'sha256', '{ not json' ) && $holders( 'not json' ) === [] );

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
echo "A file that is there and cannot be read\n";

// The outbox is 0600, and a user that is not the one of the webserver - a cron job that runs as root, a command run by hand - reads none of it. What
// cannot be read is not empty: nothing is taken from it, nothing is written over it, and the log says why. The failure is made by the file protocol of
// the suite (FaultFs), which fails the read of the file by name and leaves the file as it is - no mode of the machine is touched
$sha = static fn( string $name ): string => is_file( $path( $name ) ) === true ? hash_file( 'sha256', $path( $name ) ) : '';
$blind = static function( string $file, callable $do ): mixed {
	FaultFs::on( [ 'read' => static fn( string $name ): bool => basename( $name ) === $file ] );

	try {
		return $do();
	}
	finally {
		FaultFs::off();
	}
};
$said = static fn(): array => array_values( array_filter( ninoWarnings(), static fn( string $warning ): bool => str_starts_with( $warning, 'Mail queue' ) ) );

$reset();
$one = \Nino\Mail::queue( $appData, $mail( 70 ) );
$before = $sha( \Nino\MailQueue::WAITING );
$answers = $blind( 'mail-queue.php', static fn(): array => [ \Nino\Mail::queue( $appData, $mail( 71 ) ), \Nino\Mail::queueRefused( $appData ), \Nino\Mail::queue( $appData, $mail( 72 ) ), \Nino\Mail::queueRefused( $appData ) ] );
$log = $said();
check( 'queue() says unwritable, with no id, where the file of the outbox is there and cannot be read - it is no empty outbox: the mails that wait are still all in the file, byte for byte', $one !== '' && $answers === [ '', 'unwritable', '', 'unwritable' ] && $sha( \Nino\MailQueue::WAITING ) === $before && array_keys( $waiting() ) === [ $one ] );
check( '...and the log says it once, not once for each request, with no address and nothing of a mail', count( $log ) === 1 && str_contains( $log[0], 'example.com' ) === false && str_contains( $log[0], 'Subject' ) === false );
$counts = $blind( 'mail-queue.php', static fn(): array => \Nino\Mail::queued( $appData ) );
check( '...queued() does not say there is nothing: it says the outbox cannot be read', $counts['unreadable'] === true && \Nino\Mail::queued( $appData )['unreadable'] === false && \Nino\Mail::queued( $appData )['waiting'] === 1 );

$reset();
\Nino\Mail::queue( $appData, $mail( 73 ) );
$before = $sha( \Nino\MailQueue::WAITING );
$result = $blind( 'mail-queue.php', static fn(): array => \Nino\Mail::deliverQueued( $appData ) );
check( 'a delivery that finds the file there and cannot read it takes nothing and sends nothing: the run says unwritable, and the file is not written over', $result['unwritable'] === true && $result['delivered'] === 0 && $taken === [] && $sha( \Nino\MailQueue::WAITING ) === $before );

$gave = 0;
$unwritable = false;
$mine = $blind( 'mail-queue.php', static function() use ( &$appData, &$gave, &$unwritable ): ?array { return \Nino\MailQueue::claim( $appData, 'aaaa', NOW, $gave, $unwritable ); } );
check( '...claim() answers no mail and says it could not write, where the file cannot be read, and leaves the file as it is', $mine === null && $unwritable === true && $sha( \Nino\MailQueue::WAITING ) === $before );

// A mail that went out, and the outbox cannot be read when it is settled: the answer is not 'delivered' - the mail is still in the file with its lease
$reset();
\Nino\Mail::queue( $appData, $mail( 74 ) );
$onSend = static function() use ( &$onSend ): void { FaultFs::on( [ 'read' => static fn( string $name ): bool => basename( $name ) === 'mail-queue.php' ] ); };
$result = \Nino\Mail::deliverQueued( $appData );
FaultFs::off();
$onSend = null;
$left = array_values( $waiting() );
check( 'a mail that went out whose settlement finds the file unreadable is not reported as delivered: the run ends unwritable, the mail is as the claim wrote it, and no second mail goes out in this run', $result['unwritable'] === true && $result['delivered'] === 0 && count( $taken ) === 1 && count( $left ) === 1 && $left[0]['tries'] === 1 && $left[0]['claim'] !== '' && \Nino\Mail::deliverQueued( $appData )['delivered'] === 0 && count( $taken ) === 1 );

// The file with no mail in it is removed when the last mail has left; one that cannot be read is not known to be empty
$reset();
\Nino\Filesystem::putFileContent( $appData, \Nino\MailQueue::WAITING, [ 'format' => 1, 'waiting' => [] ] );
$blind( 'mail-queue.php', static function() use ( &$appData ): void { \Nino\MailQueue::release( $appData ); } );
$kept = is_file( $path( \Nino\MailQueue::WAITING ) ) === true;
\Nino\MailQueue::release( $appData );
check( 'the file of an outbox that cannot be read is not removed as an empty one', $kept === true && is_file( $path( \Nino\MailQueue::WAITING ) ) === false );

// The key of a mail is asked of the file of the keys, and where that cannot be read it is not known whether the mail went out a moment ago
$reset();
@unlink( $path( \Nino\MailQueue::KEYS ) );
\Nino\Mail::queue( $appData, $mail( 75 ), \Nino\Mail::CLIENT, 'order/75' );
\Nino\Mail::deliverQueued( $appData );
$keyed = $blind( 'mail-keys.php', static fn(): array => [ \Nino\Mail::queue( $appData, $mail( 75 ), \Nino\Mail::CLIENT, 'order/75' ), \Nino\Mail::queueRefused( $appData ), \Nino\Mail::queue( $appData, $mail( 76 ) ) ] );
check( 'a mail with a key is not queued where the file of the keys cannot be read: the same request again - a double click - is not sent twice for it, and a mail with no key is not asked', $keyed[0] === '' && $keyed[1] === 'unwritable' && $keyed[2] !== '' && count( $taken ) === 1 );

$reset();
\Nino\Mail::queue( $appData, $mail( 77 ), \Nino\Mail::CLIENT, 'order/77' );
\Nino\Mail::queue( $appData, $mail( 78 ), \Nino\Mail::CLIENT, 'order/78' );
ninoWarnings();
$result = $blind( 'mail-keys.php', static fn(): array => \Nino\Mail::deliverQueued( $appData ) );
$log = $said();
check( 'a key that cannot be kept - its file cannot be read - is said in the log once, whatever the number of mails: both went out, once, and neither stays', $result['delivered'] === 2 && count( $taken ) === 2 && $waiting() === [] && count( array_filter( $log, static fn( string $line ): bool => str_contains( $line, 'mail-keys.php' ) === true ) ) >= 1 && count( $log ) === 2 );

// The record of the mails given up on cannot be read: the mail that is to be given up on stays, with no attempt counted, and the others are delivered
$reset();
\Nino\Filesystem::putFileContent( $appData, \Nino\MailQueue::FAILED, [ 'format' => 1, 'failed' => [] ] );
$spent = [ 'id' => '5123456789abcdef', 'tries' => \Nino\MailQueue::attempts(), 'next' => NOW, 'error' => 'refused', 'queued' => NOW ] + $stale;
$fresh = [ 'id' => '6123456789abcdef', 'to' => $address( 90 ), 'tries' => 0, 'next' => NOW, 'error' => '', 'queued' => NOW ] + $stale;
file_put_contents( $path( \Nino\MailQueue::WAITING ), \Nino\MailQueue::encode( [ 'format' => 1, 'waiting' => [ $spent, $fresh ] ] ) );
$recordBefore = $sha( \Nino\MailQueue::FAILED );
$result = $blind( 'mail-failed.php', static fn(): array => \Nino\Mail::deliverQueued( $appData ) );
$log = $said();
check( 'a record of the mails given up on that cannot be read holds back the mail that was to be recorded - it stays, with its attempts as they were - and the other mails are delivered', $result['delivered'] === 1 && $result['failed'] === 0 && array_column( $taken, 'to' ) === [ $address( 90 ) ] && ( $waiting()['5123456789abcdef']['tries'] ?? -1 ) === \Nino\MailQueue::attempts() && $sha( \Nino\MailQueue::FAILED ) === $recordBefore && count( $log ) === 2 );
$result = \Nino\Mail::deliverQueued( $appData );
check( '...and once the record can be read it is given up on, once, with its record', $result['failed'] === 1 && $waiting() === [] && \Nino\Mail::queued( $appData )['failed'] === 1 );
$unreadableRecord = $blind( 'mail-failed.php', static fn(): array => \Nino\Mail::queued( $appData ) );
check( 'queued() says unreadable for a record that cannot be read, as it does for the outbox', $unreadableRecord['unreadable'] === true && \Nino\Mail::queued( $appData )['unreadable'] === false );

// A file that does not decode and cannot be replaced: the notice is written once, the outbox says unwritable, and the mails of the file are not lost
$reset();
file_put_contents( $path( \Nino\MailQueue::WAITING ), 'broken leak@example.org' );
$notice = $path( \Nino\MailQueue::DAMAGED );
FaultFs::on( [ 'unlink' => static fn( string $name ): bool => basename( $name ) === 'mail-queue.php' ] );
$answers = [ \Nino\Mail::queue( $appData, $mail( 79 ) ), \Nino\Mail::queue( $appData, $mail( 80 ) ) ];
FaultFs::off();
$written = count( array_filter( FaultFs::$log, static fn( array $entry ): bool => $entry[0] === 'rename' && $entry[2] === $notice ) );
$log = $said();
check( 'a damaged file that cannot be removed is a write that failed: queue() says unwritable, the file stays, and the notice is written once, not for every request', $answers === [ '', '' ] && \Nino\Mail::queueRefused( $appData ) === 'unwritable' && $written === 1 && is_file( $path( \Nino\MailQueue::WAITING ) ) === true && is_file( $notice ) === true && count( $log ) === 1 );
$id = \Nino\Mail::queue( $appData, $mail( 81 ) );
check( '...and once it can be removed the outbox starts over', $id !== '' && count( $waiting() ) === 1 );

// A damaged file is not removed where its notice cannot be written
$reset();
file_put_contents( $path( \Nino\MailQueue::WAITING ), 'broken leak@example.org' );
$before = $sha( \Nino\MailQueue::WAITING );
$seed( \Nino\MailQueue::DAMAGED, $path( '/data' ). '/no-such-folder/mail-queue-damaged.php' );
$id = \Nino\Mail::queue( $appData, $mail( 82 ) );
unset( $appData['./nino/filesystem/cache'][\Nino\MailQueue::DAMAGED] );
check( 'a damaged file stays where the notice of it cannot be written: nothing of it is lost for a notice that is not there', $id === '' && \Nino\Mail::queueRefused( $appData ) === 'unwritable' && $sha( \Nino\MailQueue::WAITING ) === $before && is_file( $path( \Nino\MailQueue::DAMAGED ) ) === false );

// The file of the keys that does not decode has a notice of its own, and a line of its own
$reset();
@unlink( $path( \Nino\MailQueue::KEYS ) );
file_put_contents( $path( \Nino\MailQueue::WAITING ), 'broken queue' );
\Nino\Mail::queue( $appData, $mail( 83 ) );
$queueLine = $said();
$queueNotice = $sha( \Nino\MailQueue::DAMAGED );
file_put_contents( $path( \Nino\MailQueue::KEYS ), 'broken keys leak@example.org' );
\Nino\Mail::queue( $appData, $mail( 84 ), \Nino\Mail::CLIENT, 'order/84' );
\Nino\Mail::deliverQueued( $appData, 10 );
$keysLine = $said();
$keysNotice = \Nino\MailQueue::decode( (string) @file_get_contents( $path( \Nino\MailQueue::DAMAGED_KEYS ) ) );
check( 'a file of the keys that does not decode is replaced by a notice of its own: the notice of the outbox is as it was, and the line in the log is not the line of the outbox', ( $keysNotice['damaged']['sha256'] ?? '' ) === hash( 'sha256', 'broken keys leak@example.org' ) && $sha( \Nino\MailQueue::DAMAGED ) === $queueNotice && count( $queueLine ) === 1 && count( $keysLine ) === 1 && $keysLine[0] !== $queueLine[0] && $holders( 'leak@example' ) === [] );
touch( $path( \Nino\MailQueue::DAMAGED_KEYS ), time() - \Nino\MailQueue::KEEP_FAILED );
\Nino\Mail::queue( $appData, $mail( 85 ) );
\Nino\Mail::deliverQueued( $appData, 10 );
check( '...and the sweep knows it: it goes when it is fourteen days old', is_file( $path( \Nino\MailQueue::DAMAGED_KEYS ) ) === false );

// The sweep takes the lock of the file it removes: a writer that holds it is not overtaken
$reset();
file_put_contents( $path( \Nino\MailQueue::DAMAGED ), 'a file that was kept: kept@example.org' );
$holder = $appData;
unset( $holder['./nino/filesystem/locks'] );
\Nino\Filesystem::lockFile( $holder, \Nino\MailQueue::DAMAGED );
\Nino\Mail::queue( $appData, $mail( 86 ) );
\Nino\Mail::deliverQueued( $appData );
$heldBack = is_file( $path( \Nino\MailQueue::DAMAGED ) ) === true;
\Nino\Filesystem::unlockFile( $holder, \Nino\MailQueue::DAMAGED );
\Nino\Mail::queue( $appData, $mail( 87 ) );
\Nino\Mail::deliverQueued( $appData );
check( 'a file that is removed by the sweep is removed under its lock: while another holds it, it stays', $heldBack === true && is_file( $path( \Nino\MailQueue::DAMAGED ) ) === false );

// The temp file of a write with a mode has the mode before the first byte is in it, the umask of the process is not touched, and a mode that cannot be set is a failed write
\Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'old' );
umask( 0022 );
$umask = umask();
FaultFs::on();
$ok = \Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'new', false, 0600 );
FaultFs::off();
$tempOf = array_values( array_filter( FaultFs::$log, static fn( array $entry ): bool => $entry[0] === 'open' && str_ends_with( $entry[1], '.tmp' ) ) )[0][1] ?? '';
$order = array_column( array_filter( FaultFs::$log, static fn( array $entry ): bool => $entry[1] === $tempOf ), 0 );
$opened = array_values( array_filter( FaultFs::$log, static fn( array $entry ): bool => $entry[0] === 'open' && $entry[1] === $tempOf ) )[0] ?? [];
check( 'a file written with a mode gets it before the first byte: the temp file is made, its mode set, then written and renamed - in that order - and the umask of the process is the one it was', $ok === true && $tempOf !== '' && array_values( array_unique( $order ) ) === [ 'open', 'chmod', 'write', 'rename' ] && umask() === $umask );
check( '...the temp file is made exclusively, in a folder of its own beside the file that has the mode 0700 when the file is made - nobody else can open it while it is written - and the file is where it was asked for', ( $opened[2] ?? '' ) === 'xb' && ( $opened[3] ?? -1 ) === 0700 && basename( dirname( $tempOf ) ) === \Nino\Filesystem::PRIVATE_TEMP && dirname( dirname( $tempOf ) ) === dirname( $path( '/data/mode-probe.php' ) ) && ( fileperms( dirname( $tempOf ) ) & 0777 ) === 0700 && glob( dirname( $tempOf ). '/*' ) === [] );
$dir = $path( '/data' ). '/mode-dir-probe';
mkdir( $dir );
$dirMode = (int) ( fileperms( $dir ) & 0777 );
rmdir( $dir );
check( '...and what is made after it - a directory - has the mode the process gives it', $dirMode === ( 0777 & ~$umask ) );
FaultFs::on( [ 'chmod' => static fn( string $name ): bool => str_ends_with( $name, '.tmp' ) ] );
$failed = \Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'newer', false, 0600 );
FaultFs::off();
check( 'a mode that cannot be set is a write that failed: it answers false, the file is as it was, no temp file is left, and no byte was written to one', $failed === false && str_contains( (string) file_get_contents( $path( '/data/mode-probe.php' ) ), 'new' ) === true && str_contains( (string) file_get_contents( $path( '/data/mode-probe.php' ) ), 'newer' ) === false );
check( '...and the bytes were never written: the log of the file protocol has no write to a temp file after the failed chmod', array_filter( FaultFs::$log, static fn( array $entry ): bool => $entry[0] === 'write' ) === [] && glob( $path( '/data' ). '/mode-probe.php.*.tmp' ) === [] && glob( $path( '/data' ). '/'. \Nino\Filesystem::PRIVATE_TEMP. '/*.tmp' ) === [] );
unlink( $path( '/data/mode-probe.php' ) );

echo "\n";
echo "The folder of the temp files, who writes, what is left\n";

// A folder that is there with another mode, or is a link, is no place for a file that nobody else may open: the write fails and nothing is written
$data = $path( '/data' );
$folder = $data. '/'. \Nino\Filesystem::PRIVATE_TEMP;
$leftOver = glob( $folder. '/*' ) ?: [];
foreach( $leftOver as $file )
	unlink( $file );
@rmdir( $folder );
mkdir( $folder, 0755 );
umask( 0022 );
$wide = \Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'x', false, 0600 );
rmdir( $folder );
mkdir( $data. '/linked-folder' );
symlink( $data. '/linked-folder', $folder );
$linked = \Nino\Filesystem::putFileContent( $appData, '/data/mode-probe.php', 'x', false, 0600 );
unlink( $folder );
rmdir( $data. '/linked-folder' );
check( 'a folder of the temp files that has a wider mode, or is a link, is a write that failed: the folder is not trusted, and no file is written', $wide === false && $linked === false && is_file( $path( '/data/mode-probe.php' ) ) === false );
$plain = \Nino\Filesystem::putFileContent( $appData, '/data/mode-plain.php', 'x' );
FaultFs::on();
\Nino\Filesystem::putFileContent( $appData, '/data/mode-plain.php', 'y' );
\Nino\Filesystem::putFileContent( $appData, '/data/mode-plain.php', 'z', false, 0644 );
FaultFs::off();
$temps = array_values( array_map( static fn( array $entry ): string => dirname( $entry[1] ), array_filter( FaultFs::$log, static fn( array $entry ): bool => $entry[0] === 'open' && str_ends_with( $entry[1], '.tmp' ) ) ) );
unlink( $path( '/data/mode-plain.php' ) );
check( 'a write with no mode, or with a mode the others may read, makes its temp file beside the file as it did: the folder is for the files that shut others out', $plain === true && $temps === [ $data, $data ] );

// The command of a cron job that is not the owner of the folder: it asks before it writes
$mine = posix_geteuid();
FaultFs::on( [ 'uid' => static fn( string $name ): ?int => $name === $data ? $mine + 1 : null ] );
$foreign = \Nino\MailQueue::foreignOwner( $appData );
FaultFs::off();
check( 'the command asks who owns private/data before it writes: where that is another user than the one it runs as it is told both, where it is the same it is told nothing', $foreign === [ 'owner' => $mine + 1, 'user' => $mine ] && \Nino\MailQueue::foreignOwner( $appData ) === null );

// The file of the keys that cannot be read and is older than the keys are kept holds nothing: it is replaced, and mails with a key go
$reset();
\Nino\Filesystem::putFileContent( $appData, \Nino\MailQueue::KEYS, [ 'format' => 1, 'keys' => [] ] );
$young = $blind( 'mail-keys.php', static fn(): string => \Nino\Mail::queue( $appData, $mail( 91 ), \Nino\Mail::CLIENT, 'order/91' ) );
touch( $path( \Nino\MailQueue::KEYS ), time() - \Nino\MailQueue::KEY_MEMORY - 5 );
$old = $blind( 'mail-keys.php', static fn(): string => \Nino\Mail::queue( $appData, $mail( 92 ), \Nino\Mail::CLIENT, 'order/92' ) );
$result = $blind( 'mail-keys.php', static fn(): array => \Nino\Mail::deliverQueued( $appData ) );
check( 'a file of keys that cannot be read and is younger than the keys are kept rejects a mail with a key; an older one is of no use, and the mail is queued', $young === '' && $old !== '' );
$kept = \Nino\MailQueue::decode( (string) @file_get_contents( $path( \Nino\MailQueue::KEYS ) ) );
$stillThere = count( $kept['keys'] ?? [] ) === 1 && ( $kept['keys'][0]['key'] ?? '' ) === hash( 'sha256', 'order/92' );
check( '...and the delivery of it goes out once, and the file of keys is a file of this user again', $result['delivered'] === 1 && count( $taken ) === 1 && $stillThere === true && \Nino\Mail::queue( $appData, $mail( 92 ), \Nino\Mail::CLIENT, 'order/92' ) !== '' );

// With no outbox there is still what it left: the sweep runs when there is no mail to look at
$reset();
@unlink( $path( \Nino\MailQueue::KEYS ) );
$aged = $path( \Nino\MailQueue::DAMAGED_KEYS );
file_put_contents( $aged, \Nino\MailQueue::encode( [ 'format' => 1, 'damaged' => [ 'at' => 1, 'size' => 1, 'sha256' => str_repeat( 'a', 64 ) ] ] ) );
touch( $aged, time() - \Nino\MailQueue::KEEP_FAILED );
\Nino\Filesystem::putFileContent( $appData, \Nino\MailQueue::KEYS, [ 'format' => 1, 'keys' => [] ] );
touch( $path( \Nino\MailQueue::KEYS ), time() - \Nino\MailQueue::KEY_MEMORY - 5 );
@mkdir( $folder, 0700 );
file_put_contents( $folder. '/old.php.aaaaaaaaaaaa.tmp', 'x' );
file_put_contents( $folder. '/new.php.bbbbbbbbbbbb.tmp', 'x' );
touch( $folder. '/old.php.aaaaaaaaaaaa.tmp', time() - \Nino\MailQueue::TEMP_KEEP );
check( 'a site that has no outbox is swept all the same, which is what the command of a cron job does: the notice that is old, the keys that are of no use and the temp file of a write that was cut off go, and what is young stays', is_file( $path( \Nino\MailQueue::WAITING ) ) === false && \Nino\Mail::deliverQueued( $appData )['delivered'] === 0 && is_file( $aged ) === false && is_file( $path( \Nino\MailQueue::KEYS ) ) === false && is_file( $folder. '/old.php.aaaaaaaaaaaa.tmp' ) === false && is_file( $folder. '/new.php.bbbbbbbbbbbb.tmp' ) === true );
unlink( $folder. '/new.php.bbbbbbbbbbbb.tmp' );

// A notice that cannot be read is not judged: it goes when it is old, not at once
$reset();
file_put_contents( $path( \Nino\MailQueue::WAITING ), 'broken' );
\Nino\Mail::queue( $appData, $mail( 93 ) );
\Nino\Mail::deliverQueued( $appData );
$blind( 'mail-queue-damaged.php', static function() use ( &$appData ): void { \Nino\MailQueue::sweep( $appData ); } );
$kept = is_file( $path( \Nino\MailQueue::DAMAGED ) ) === true;
touch( $path( \Nino\MailQueue::DAMAGED ), time() - \Nino\MailQueue::KEEP_FAILED );
$blind( 'mail-queue-damaged.php', static function() use ( &$appData ): void { \Nino\MailQueue::sweep( $appData ); } );
check( 'a notice that cannot be read is kept until it is old, and then removed', $kept === true && is_file( $path( \Nino\MailQueue::DAMAGED ) ) === false );

// The log names the file and not where it is, and the counters of the log are no part of a backup
$reset();
\Nino\Mail::queue( $appData, $mail( 94 ) );
$blind( 'mail-queue.php', static fn(): string => \Nino\Mail::queue( $appData, $mail( 95 ) ) );
$log = $said();
check( 'the line in the log about a file that cannot be read has no path of the project in it', count( $log ) === 1 && str_contains( $log[0], $sandbox ) === false && str_contains( $log[0], 'mail-queue.php' ) === true );
$never = ( new \ReflectionClass( \Nino\Backup::class ) )->getReflectionConstant( 'NEVER' )->getValue();
check( 'the counters that keep the log from saying the same twice in an hour are no part of a backup, nor of a restore', array_diff( [ 'mail-queue-unreadable.php', 'mail-queue-keys.php', 'mail-queue-stuck.php', 'mail-queue-record.php', 'mail-queue-unwritable.php' ], $never ) === [] );
$staging = $sandbox. '/staging';
mkdir( $staging. '/data/'. \Nino\Filesystem::PRIVATE_TEMP, 0755, true );
file_put_contents( $staging. '/data/'. \Nino\Filesystem::PRIVATE_TEMP. '/left.php.cccccccccccc.tmp', 'leak@example.org' );
\Nino\Backup::dropOutbox( $staging );
check( 'a restore brings back no temp file of the outbox: the folder of them is dropped with the files of the outbox from what an archive carries', is_dir( $staging. '/data/'. \Nino\Filesystem::PRIVATE_TEMP ) === false );
@mkdir( $folder, 0700 );
file_put_contents( $folder. '/left.php.dddddddddddd.tmp', 'x' );
check( 'and a backup does not carry it', in_array( 'data/'. \Nino\Filesystem::PRIVATE_TEMP. '/left.php.dddddddddddd.tmp', array_map( static fn( string $name ): string => (string) preg_replace( '#/+#', '/', $name ), \Nino\Backup::manifest( $appData ) ), true ) === false );
unlink( $folder. '/left.php.dddddddddddd.tmp' );

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
$files = [ basename( \Nino\MailQueue::WAITING ), basename( \Nino\MailQueue::FAILED ), basename( \Nino\MailQueue::DAMAGED ), basename( \Nino\MailQueue::DAMAGED_FAILED ), basename( \Nino\MailQueue::DAMAGED_KEYS ), basename( \Nino\MailQueue::KEYS ), basename( \Nino\MailQueue::DUE ) ];
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

// A command that does not run as the owner of private/data writes nothing and sends nothing: the files it wrote are 0600 and another user's, which the webserver could not read
$nobody = function_exists( 'posix_getpwnam' ) === true ? posix_getpwnam( 'nobody' ) : false;

if( function_exists( 'posix_geteuid' ) === true && posix_geteuid() === 0 && $nobody !== false ) {

	$owned = $sandbox. '/owned';
	\Nino\Filesystem::copyDir( $root. '/_nino', $owned. '/_nino' );
	mkdir( $owned. '/private/data', 0755, true );
	file_put_contents( $owned. '/private/config.php', '<?php return [];' );
	$other = [ './nino/uid' => $owned ];
	\Nino\AppData::prepare( $other );
	foreach( [ 'path' => $owned, 'configpath' => $owned. '/private', 'contentpath' => $owned. '/private', 'publicpath' => $owned. '/public' ] as $name => $value )
		$other['./nino/filesystem/'. $name] = $value;
	$other['/nino/mail/cap'] = 0;
	\Nino\Mail::queue( $other, $mail( 14 ) );

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $owned. '/private', \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::SELF_FIRST ) as $item )
		chown( $item->getPathname(), $nobody['uid'] );

	chown( $owned. '/private', $nobody['uid'] );
	$listing = static fn(): array => array_map( static fn( string $file ): string => $file. ':'. filemtime( $file ), array_merge( glob( $owned. '/private/data/*' ) ?: [], glob( $owned. '/private/data/.[!.]*' ) ?: [] ) );
	$before = [ $listing(), (string) @file_get_contents( $sandbox. '/sendmail.out' ), hash_file( 'sha256', $owned. '/private/data/mail-queue.php' ) ];
	$process = proc_open( [ PHP_BINARY, '-d', 'sendmail_path='. $sandbox. '/sendmail.sh', $owned. '/_nino/Nino/MailQueue/deliver.php' ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes, null, [ 'PATH' => (string) getenv( 'PATH' ) ] );
	$out = trim( (string) stream_get_contents( $pipes[1] ) );
	$err = trim( (string) stream_get_contents( $pipes[2] ) );
	$code = proc_close( $process );
	clearstatcache();
	check( 'a command that runs as another user than the owner of private/data writes nothing and sends nothing: it says in one sentence which two users, prints no address and ends with a code that is not 0', $code !== 0 && $out === '' && str_contains( $err, (string) $nobody['uid'] ) === true && str_contains( $err, '@' ) === false && substr_count( $err, "\n" ) === 0
		&& $before === [ $listing(), (string) @file_get_contents( $sandbox. '/sendmail.out' ), hash_file( 'sha256', $owned. '/private/data/mail-queue.php' ) ] );
}
else
	echo "  not run - this needs root and a user 'nobody': the command asks \\Nino\\MailQueue::foreignOwner(), which is pinned above\n";


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
