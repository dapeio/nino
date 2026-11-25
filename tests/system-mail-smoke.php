<?php
declare(strict_types=1);

/**
 *	Nino
 *	system-mail-smoke.php	\Nino\Mail::sendSystem(): a mail the system sends on its own account is neither counted
 *												against the cap of the client that set it off nor refused by it, has a cap of its own
 *												for the whole site, and is sendAll() for the rest. And the notes of the backup, which a
 *												page request reads without the workbench's classes: missed() and stopped() in a child
 *												process that has none, and the stub they read held equal to the recovery's.
 *
 *	Usage: php tests/system-mail-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'system-mail' );
$sandbox = ninoSandboxDir( $appData );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$appData['/nino/mail/cap'] = 1;

$taken = [];
$sent = true;
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$taken, &$sent ): void { $taken[] = $mail['to']; $mail['sent'] = $sent; } );
$mails = [ [ 'to' => 'a@example.org', 'subject' => 's', 'body' => 'b', 'replyTo' => '' ], [ 'to' => 'b@example.org', 'subject' => 's', 'body' => 'b', 'replyTo' => '' ] ];

echo "The mail of the system\n";

check( 'the cap of the client lets one call through and refuses the next', \Nino\Mail::sendAll( $appData, $mails ) === true && \Nino\Mail::sendAll( $appData, $mails ) === false && \Nino\Mail::rateLimited( $appData ) === true );
$taken = [];
check( 'a mail of the system goes out although the client is over its cap, to every address, and says it was not refused', \Nino\Mail::sendSystem( $appData, $mails ) === true && $taken === [ 'a@example.org', 'b@example.org' ] && \Nino\Mail::rateLimited( $appData ) === false && ( $appData['./nino/mail/results'] ?? [] ) === [ true, true ] );

\Nino\Feature::forget( $appData, 'ratelimit', '127.0.0.1' );
\Nino\Mail::sendSystem( $appData, $mails );
check( '...and does not use up the cap of the client', \Nino\Mail::sendAll( $appData, $mails ) === true );

$taken = [];
$ran = 0;
for( $i = 0; $i < \Nino\Mail::SYSTEM_CAP + 3; $i++ )
	if( \Nino\Mail::sendSystem( $appData, $mails ) === true )
		$ran++;
check( 'the system has a cap of its own for the whole site: calls an hour, and then it is refused as rate limited with nothing sent', $ran === 10 && \Nino\Mail::SYSTEM_CAP === 12 && \Nino\Mail::rateLimited( $appData ) === true );

ninoCounterSet( $appData, 'sysmail-system', [] );
$units = static function() use ( &$appData ): int { return (int) ( array_values( ninoCounter( $appData, 'sysmail-system' ) )[0]['tries'] ?? 0 ); };
\Nino\Mail::sendSystem( $appData, [ $mails[0], $mails[1], $mails[0] ] );
check( 'all the mails of one call - one mail to several addresses - are one unit of the cap', $units() === 1 );
\Nino\Mail::sendSystem( $appData, $mails, \Nino\Mail::TEST );
check( '...a test mail has a counter of its own and does not use the unit of the others', $units() === 1 && ninoCounter( $appData, 'sysmail-test' ) !== [] );
$taken = [];
check( 'any other kind than the two is refused: nothing is sent, and no counter is made for it', \Nino\Mail::sendSystem( $appData, $mails, 'form-hour' ) === false && \Nino\Mail::sendSystem( $appData, $mails, 'Not A Kind' ) === false && $taken === [] && ninoCounter( $appData, 'sysmail-form-hour' ) === [] );
\Nino\Feature::count( $appData, 'form-hour', 'site', 300, 3600 );
$hourBefore = \Nino\Form::capped( $appData );
for( $i = 0; $i < \Nino\Mail::SYSTEM_CAP + \Nino\Mail::TEST_CAP + 2; $i++ )
	\Nino\Mail::sendSystem( $appData, $mails, $i % 2 === 0 ? \Nino\Mail::SYSTEM : \Nino\Mail::TEST );
check( 'no counter outside the prefix is touched: the form cap of the hour is as it was, and its counter is the one the form made', \Nino\Form::capped( $appData ) === $hourBefore && \Nino\Mail::TEST_CAP === 6 );
\Nino\Feature::forget( $appData, 'form-hour', 'site' );
ninoCounterSet( $appData, 'sysmail-system', [] );
ninoCounterSet( $appData, 'sysmail-test', [] );
$taken = [];
$testRan = 0;
for( $i = 0; $i < 20; $i++ )
	if( \Nino\Mail::sendSystem( $appData, $mails, \Nino\Mail::TEST ) === true )
		$testRan++;
check( 'the cap of the test mails holds over many calls: six an hour, not one more', $testRan === 6 && count( $taken ) === 12 );
ninoCounterSet( $appData, 'sysmail-system', [] );
$taken = [];
$sent = false;
check( 'the first mail that is not delivered ends the call: the ones after it are not tried, and the results are those of the ones that were', \Nino\Mail::sendSystem( $appData, $mails ) === false && $taken === [ 'a@example.org' ] && $appData['./nino/mail/results'] === [ false ] );
$sent = true;
ninoCounterSet( $appData, 'ratelimit', [] );
$sent = false;
check( 'a transport that refuses is a failure, recorded for the dashboard like any other', \Nino\Mail::sendSystem( $appData, $mails ) === false && \Nino\Mail::failure( $appData ) !== null && $appData['./nino/mail/results'] === [ false ] );
$sent = true;
check( 'an empty batch is delivered and leaves no refusal', \Nino\Mail::sendSystem( $appData, [] ) === true && \Nino\Mail::rateLimited( $appData ) === false );

echo "\n";
echo "The notes of the backup without the workbench\n";

require_once dirname( __DIR__ ). '/_admin/Admin.php';
check( 'the stub the notes are read behind is the recovery\'s, byte for byte', \Nino\Modules\Backups::STUB_PREFIX === \Nino\Admin\Recovery::STUB_PREFIX && \Nino\Modules\Backups::STUB_SUFFIX === \Nino\Admin\Recovery::STUB_SUFFIX );

$child = $sandbox. '/child.php';
file_put_contents( $child, '<?php
declare(strict_types=1);
$root = $argv[1]; $sandbox = $argv[2];
require $root . "/_nino/Nino.php";
$appData = [ "./nino/uid" => $sandbox ];
\Nino\AppData::prepare( $appData );
foreach( [ "path" => $sandbox, "configpath" => $sandbox . "/private", "contentpath" => $sandbox . "/private", "publicpath" => $sandbox . "/public" ] as $n => $v ) $appData["./nino/filesystem/" . $n] = $v;
$dir = \Nino\Modules\Backups::dir( $appData ); @mkdir( $dir, 0777, true );
$note = static fn( array $n ): string => \Nino\Modules\Backups::STUB_PREFIX . base64_encode( (string) json_encode( $n ) ) . \Nino\Modules\Backups::STUB_SUFFIX;
file_put_contents( $dir . "/" . \Nino\Modules\Backups::ATTEMPT_FILE, $note( [ "date" => date( "Y-m-d" ), "reason" => "no room" ] ) );
echo json_encode( [ class_exists( "\\\\Nino\\\\Admin\\\\Recovery", false ), \Nino\Modules\Backups::missed( $appData ), \Nino\Modules\Backups::stopped( $appData ) ] );
' );
$out = (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $child ). ' '. escapeshellarg( dirname( __DIR__ ) ). ' '. escapeshellarg( $sandbox ). ' 2>&1' );
$answer = json_decode( $out, true );
check( 'a page request that has no class of the workbench reads the note of a failed attempt: missed() answers its reason and stopped() answers nothing', is_array( $answer ) === true && $answer[0] === false && ( $answer[1]['reason'] ?? '' ) === 'no room' && $answer[2] === null );

echo "\n";

ninoDone( $appData );
