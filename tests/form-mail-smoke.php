<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	form-mail-smoke.php		Dependency-free smoke test for Modules\Form, \Nino\Mail and
 *											\Nino\RotatingLog: a submission and what problems() says, several
 *											forms and the seam a guard refuses at, checkbox, radio and date
 *											fields, the per-ip send rate limit of Mail, its transport callback,
 *											the record of a mail that did not go out, and the sweep of a dated
 *											log. Runs against an isolated sandbox directory, never touches the
 *											real project data.
 *
 *	Usage: php tests/form-mail-smoke.php
 */

require __DIR__. '/harness.php';

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'form-mail' );
$sandbox = ninoSandboxDir( $appData );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// What /data/ratelimit.php calls this client: the hash of its address, never the address
$clientKey = hash( 'sha256', '127.0.0.1' );

echo "Sandbox: $sandbox\n\n";


// --- Modules\Form -------------------------------------------------------

echo "Modules\\Form::callbackResponse - validate/send/record a contact submission\n";

\Nino\Html::addFills( $appData, [
	'[[/project/mail/address/owner]]' 	=> 'owner@example.com',
	'[[/module/form/subject/owner]]' => 'New inquiry',
	'[[/module/form/subject/user]]' 	=> 'Thanks for reaching out',
], '*' );

// A transport that takes every mail. A submission whose owner mail did not go
// out is a 500, so what these checks pin must not depend on whether this
// machine has a sendmail - it is registered once and the unset after the
// 'recording off' check removes it, where the checks that want to read the
// mails register their own
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = true; } );

function submitForm( array &$appData, array $post ): array {
	$_POST = array_merge( [ 'name' => '', 'email' => '', 'message' => '', 'location' => '', 'cat' => '' ], $post );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Form::callbackResponse( $appData, $request );
	return $request;
}

$missingRequest = submitForm( $appData, [ 'name' => 'Jo' ] );
check( 'a missing required field is rejected (400)', $missingRequest['/nino/http/response']['statusCode'] === 400 );

$invalidEmailRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'not-an-email', 'message' => 'Hi' ] );
check( 'an invalid email is rejected (400)', $invalidEmailRequest['/nino/http/response']['statusCode'] === 400 );

$honeypotRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi', 'location' => 'filled-by-a-bot' ] );
check( 'a filled honeypot is rejected (418)', $honeypotRequest['/nino/http/response']['statusCode'] === 418 );

// Other writers create /data too (a failed login writes /data/auth-tries.php) -
// check the form's own file specifically instead of the whole directory
check( 'neither rejected submission created the form data file', is_file( \Nino\Filesystem::getPath( $appData ). '/data/forms.'. date( 'Y-m' ). '.php' ) === false );

$okRequest = submitForm( $appData, [ 'name' => 'Jo Client', 'email' => 'jo@example.com', 'message' => "Line one\nLine two", 'cat' => 'General' ] );
check( 'a valid submission succeeds (200)', $okRequest['/nino/http/response']['statusCode'] === 200 );
check( 'a valid submission bootstraps the data dir on the private root, not under _editor', is_dir( \Nino\Filesystem::path( $appData, '/data' ) ) === true && is_dir( \Nino\Filesystem::getPath( $appData ). '/_admin/data' ) === false );

$formsDir 	= \Nino\Filesystem::path( $appData, '/data' );
$monthFile 	= $formsDir. '/forms.'. date( 'Y-m' ). '.php';
check( 'this month\'s submissions file was actually written', is_file( $monthFile ) === true );

$monthEntries = \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] );
$lastEntry 		= end( $monthEntries );

check( 'the recorded entry has the submitted name/email/message/cat', $lastEntry['name'] === 'Jo Client' && $lastEntry['email'] === 'jo@example.com' && $lastEntry['message'] === "Line one\nLine two" && $lastEntry['cat'] === 'General' );
check( 'the recorded entry has a date and ip', isset( $lastEntry['date'] ) === true && $lastEntry['ip'] === '127.0.0.1' );

check( 'the file is a plain, human-readable php array file - not an encoded stub', str_starts_with( file_get_contents( $monthFile ), "<?php return array (" ) === true );

$submissionsBefore = count( \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] ) );
$_POST['_csrf'] = 'wrong-token';
$blockedRequest = [ '/nino/http/request' => [ 'method' => 'POST' ], '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Csrf::callbackResponse( $appData, $blockedRequest ); // sets 403 + the blocked flag, same as the real POST pipeline
$_POST = array_merge( $_POST, [ 'name' => 'Attacker', 'email' => 'attacker@example.com', 'message' => 'Hi', 'location' => '', 'cat' => '' ] );
\Nino\Modules\Form::callbackResponse( $appData, $blockedRequest );
check( 'a csrf-blocked request is rejected even with otherwise-valid fields', $blockedRequest['/nino/http/response']['statusCode'] === 403 );
check( 'a csrf-blocked request does not send mail or record a submission', count( \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] ) ) === $submissionsBefore );
check( 'Form::blocked() says it was Csrf that refused the request', \Nino\Form::blocked( $blockedRequest ) === true );

// The same through requests of the site, on a copy of the sandbox so that the
// guard \Nino\init() registers stays out of the rest of this suite
$guardedAppData = $appData;
\Nino\Csrf::init( $guardedAppData );
$postVisit = ninoVisit( $guardedAppData, '/anywhere', 'POST' );
\Nino\Http::response( $guardedAppData, $postVisit );
$getVisit = ninoVisit( $guardedAppData, '/anywhere' );
\Nino\Http::response( $guardedAppData, $getVisit );
check( 'a POST without a token is refused by the guard, and Form::blocked() reads it', $postVisit['/nino/http/response']['statusCode'] === 403 && \Nino\Form::blocked( $postVisit ) === true );
check( '...a GET is not blocked, whatever else it came to', $getVisit['/nino/http/response']['statusCode'] !== 403 && \Nino\Form::blocked( $getVisit ) === false );
check( '...nor is a request nothing has looked at', \Nino\Form::blocked( [] ) === false );

// A post that is not a flat map of strings. Casting one raised an engine
// warning Runtime::handleError() treats as fatal - an unauthenticated 500
// from the one endpoint the whole internet may post to
unset( $_POST['_csrf'] );
$arrayRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
$_POST = [ 'name' => [ 'x' ], 'email' => 'jo@example.com', 'message' => 'Hi', 'location' => '' ];

ninoWarnings();
\Nino\Modules\Form::callbackResponse( $appData, $arrayRequest );
$raised = ninoWarnings();

check( 'a field posted as an array is a 400, and raises nothing the error handler would turn fatal', $arrayRequest['/nino/http/response']['statusCode'] === 400 && $raised === [] );

echo "\n";


// --- Form - several forms, and the seam a guard refuses at ------------------

echo "Form - a project's own forms, and refusing a submission ahead of the engine\n";

/*	The shipped form is DEFAULT_FORM, read from there rather than copied
	here: what these pin is that it is offered whole - normalize() drops a
	field it does not accept without a word - and that its fields are typed
	from the vocabulary and named uniquely outside the reserved names, which
	normalize() would silently repair (an unknown type becomes text) rather
	than refuse	*/
$shippedForms = \Nino\Form::forms( $appData );
check( 'with nothing configured, the form this framework ships is what is offered, whole', count( $shippedForms ) === 1 && $shippedForms[0]['key'] === \Nino\Form::DEFAULT_FORM['key']
	&& array_column( $shippedForms[0]['fields'], 'name' ) === array_column( \Nino\Form::DEFAULT_FORM['fields'], 'name' ) );
check( '...its fields typed from the vocabulary, named uniquely and outside the reserved names', array_diff( array_column( \Nino\Form::DEFAULT_FORM['fields'], 'type' ), \Nino\Form::TYPES ) === []
	&& count( array_unique( array_column( \Nino\Form::DEFAULT_FORM['fields'], 'name' ) ) ) === count( \Nino\Form::DEFAULT_FORM['fields'] )
	&& array_intersect( array_column( \Nino\Form::DEFAULT_FORM['fields'], 'name' ), \Nino\Form::RESERVED ) === [] );

$appData[ \Nino\Form::FORMS ] = [
	\Nino\Form::DEFAULT_FORM,
	[
		'key' => 'quote', 'name' => 'Quote', 'to' => 'sales@example.com', 'confirm' => false,
		'fields' => [
			[ 'name' => 'email',   'label' => 'Mail',    'type' => 'email',    'required' => true ],
			[ 'name' => 'budget',  'label' => 'Budget',  'type' => 'number' ],
			[ 'name' => 'date',    'label' => 'Ignored', 'type' => 'text' ],
		],
	],
	[ 'key' => 'broken', 'fields' => [] ],
];

check( 'a project defines its forms in config.php, beside its routes', array_column( \Nino\Form::forms( $appData ), 'key' ) === [ 'contact', 'quote' ] );
check( 'a definition with no usable field is left out rather than half-read', \Nino\Form::form( $appData, 'broken' ) === null );
check( 'a field named like something a record already carries is dropped', array_column( \Nino\Form::form( $appData, 'quote' )['fields'], 'name' ) === [ 'email', 'budget' ] );
check( 'an empty key is the first form defined - which is what a page posting no key belongs to', \Nino\Form::form( $appData, '' )['key'] === 'contact' );

$unknownRequest = submitForm( $appData, [ 'form' => 'nowhere', 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a key no form has is a 404 - a page pointing at a form that was renamed, not spam', $unknownRequest['/nino/http/response']['statusCode'] === 404 );

// handle() used to pre-filter the key against the slug shape and collapse a
// miss to '' - the first form - so a page posting 'Quote' was validated
// against, mailed to and recorded under the contact form instead of 404'd
$shapedKeyRequest = submitForm( $appData, [ 'form' => 'Quote', 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( '...whatever shape the key has - a miss is never routed to the first form', $shapedKeyRequest['/nino/http/response']['statusCode'] === 404 );

// posted() reads keys of at most 64 characters; a definition allowing a
// longer name described a field that could never be submitted
$longNames = \Nino\Form::normalize( [ 'key' => 'long', 'fields' => [ [ 'name' => str_repeat( 'n', 65 ) ], [ 'name' => str_repeat( 'n', 64 ) ] ] ] );
check( 'a field name is bounded at definition time to what posted() reads', array_column( $longNames['fields'] ?? [], 'name' ) === [ str_repeat( 'n', 64 ) ] );

$_POST = [ 'form' => 'quote', 'email' => 'jo@example.com', 'budget' => 'not-a-number', 'location' => '' ];
$badTypeRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $badTypeRequest );
check( 'a value that is not of its field\'s declared type is a 400', $badTypeRequest['/nino/http/response']['statusCode'] === 400 );

$_POST = [ 'form' => 'quote', 'email' => 'jo@example.com', 'budget' => '5000', 'location' => '' ];
$quoteRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $quoteRequest );
$quoteEntries = \Nino\Filesystem::getFileContent( $appData, '/data/forms.'. date( 'Y-m' ). '.php', [] );
$quoteEntry   = end( $quoteEntries );
check( 'a submission to another form is accepted and recorded under that form', $quoteRequest['/nino/http/response']['statusCode'] === 200
	&& $quoteEntry['form'] === 'quote' && $quoteEntry['budget'] === '5000' && preg_match( '/^[0-9a-f]{16}$/', $quoteEntry['id'] ) === 1 );
// An entry from before this framework knew more than one form: no 'form',
// no 'id', the values flat beside the date and the ip. A project's history
// has to survive the update untouched
\Nino\Filesystem::mutate( $appData, '/data/forms.'. date( 'Y-m' ). '.php', static function( array $entries ): array {
	$entries[] = [ 'date' => '2020-01-01 00:00:00', 'name' => 'Old Entry', 'email' => 'old@example.com', 'message' => 'Hi', 'cat' => '', 'ip' => '127.0.0.1' ];
	return $entries;
} );
check( 'an entry written before there was more than one form reads as the first form\'s, unchanged otherwise', ( static function( array &$appData ): bool {
	foreach( \Nino\Form::entries( $appData ) as $entry )
		if( ( $entry['name'] ?? '' ) === 'Old Entry' )
			return $entry['form'] === 'contact' && $entry['id'] === '' && $entry['email'] === 'old@example.com' && $entry['date'] === '2020-01-01 00:00:00';
	return false;
} )( $appData ) );

// What is kept, and for how long. Both beside the definitions in
// config.php, so a project decides them the way it decides its forms -
// and the Forms feature's builder writes these very keys
check( 'without a setting, the window is the built-in one', \Nino\Form::retention( $appData ) === \Nino\Form::RETENTION_MONTHS && \Nino\Form::stores( $appData ) === true );

$appData[ \Nino\Form::RETENTION ] = 12;
check( 'a project sets its own window', \Nino\Form::retention( $appData ) === 12 );

$appData[ \Nino\Form::RETENTION ] = 0;
check( 'a window outside 1..60 is a hand edit gone wrong, and falls back rather than deleting everything', \Nino\Form::retention( $appData ) === \Nino\Form::RETENTION_MONTHS );
$appData[ \Nino\Form::RETENTION ] = 999;
check( '...at either end', \Nino\Form::retention( $appData ) === \Nino\Form::RETENTION_MONTHS );
unset( $appData[ \Nino\Form::RETENTION ] );

// A transport takes the mail here: with recording off, a mail that did not
// go out is a 500 (see below), so "goes out" has to mean it
$taken = 0;
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$taken ): void {
	$taken++;
	$mail['sent'] = true;
} );
$appData[ \Nino\Form::STORE ] = false;
$storedBefore = count( \Nino\Form::entries( $appData ) );
$_POST = [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi', 'location' => '', 'cat' => '' ];
$noStoreRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Modules\Form::callbackResponse( $appData, $noStoreRequest );
check( 'with recording off the mail still goes out and nothing is written', $noStoreRequest['/nino/http/response']['statusCode'] === 200
	&& $taken === 2 && count( \Nino\Form::entries( $appData ) ) === $storedBefore );
unset( $appData[ \Nino\Form::STORE ], $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );

// What a submission's text survives. posted() cut a value at
// MAX_FIELD_LENGTH with substr(), so a multibyte character at the cut lost
// half its bytes - and render() and record() escaped without
// ENT_SUBSTITUTE, which answers invalid utf-8 with '' rather than a
// replacement character: the message went out and was stored as nothing,
// and the visitor saw ok. A transport takes the mails here, so their bodies
// can be read instead of handed to mail()
$mailed = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$mailed ): void {
	$mailed[] = $mail;
	$mail['sent'] = true;
} );
\Nino\Filesystem::putFileContent( $appData, '/templates/mail-test.tpl', '<p>[[message]]</p>[[fields]]' );
\Nino\Modules\Template::init( $appData );
// The submissions above have used up part of Mail's per-ip cap - a fresh
// window for the ones below, whose mails have to go out
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
$formsBefore = $appData[ \Nino\Form::FORMS ];
$appData[ \Nino\Form::FORMS ] = [ [ 'ownerTemplate' => '/templates/mail-test', 'userTemplate' => '/templates/mail-test' ] + \Nino\Form::DEFAULT_FORM ];
$formsFile = '/data/forms.'. date( 'Y-m' ). '.php';

$almostAll = str_repeat( 'a', \Nino\Form::MAX_FIELD_LENGTH - 1 );
$longRequest	= submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => $almostAll. 'ä und hier geht es weiter' ] );
$formEntries	= \Nino\Filesystem::getFileContent( $appData, $formsFile, [] );
$longEntry		= end( $formEntries );
check( 'a value is cut at MAX_FIELD_LENGTH on a character boundary - the character at the cut goes, the message stays', $longRequest['/nino/http/response']['statusCode'] === 200 && $longEntry['message'] === $almostAll && str_contains( $mailed[0]['body'] ?? '', '<p>'. $almostAll. '</p>' ) === true );

$mailed = [];
$latinRequest	= submitForm( $appData, [ 'name' => "Ren\xE9", 'email' => 'jo@example.com', 'message' => "caf\xE9 au lait" ] );
$formEntries	= \Nino\Filesystem::getFileContent( $appData, $formsFile, [] );
$latinEntry		= end( $formEntries );
check( 'a byte that is not utf-8 becomes the replacement character, in the record and in the mail, rather than blanking the value', $latinRequest['/nino/http/response']['statusCode'] === 200 && $latinEntry['name'] === "Ren\u{FFFD}" && $latinEntry['message'] === "caf\u{FFFD} au lait" && str_contains( $mailed[0]['body'] ?? '', "<p>caf\u{FFFD} au lait</p>" ) === true );

// render() promises a submitted value is never read as a placeholder. It
// filled with str_replace() over arrays, pair by pair over the whole
// string - so the [[fields]] table, inserted first, had the [[date]] and
// [[email]] inside a visitor's message rewritten by the pairs after it
$mailed = [];
$placeholderRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'see [[date]] and [[email]]' ] );
check( 'a submitted value is never read as a placeholder - in the [[fields]] table included', $placeholderRequest['/nino/http/response']['statusCode'] === 200 && substr_count( $mailed[0]['body'] ?? '', 'see [[date]] and [[email]]' ) === 2 );

// A submission the per-ip cap refused was answered ok: 200 and {status: ok}
// were set before the flag was looked at, nothing had gone out and nothing
// was recorded, and the visitor waited for a reply to a message nobody
// received. Over budget is a 429 - the same generic message on the page,
// and the visitor knows to try again later. The cap is Mail's, 5 per hour
$mailed = [];
$countBefore = count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [ $clientKey => [ 'tries' => 5, 'reset' => time() + 3600 ] ] );
$cappedRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a submission the per-ip mail cap refuses is a 429, not ok - and neither mailed nor recorded', $cappedRequest['/nino/http/response']['statusCode'] === 429
	&& isset( $cappedRequest['/nino/http/response']['body'] ) === false && $mailed === [] && count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) ) === $countBefore );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );

// A mail that did not go out. The inquiry is recorded where a copy is kept -
// it did happen, and it is in the panel - but the visitor is told it did not
// arrive, whether or not a copy is kept: a 500 and no body, the generic
// message on the page. What the owner reads is the dashboard's notice, and the
// record behind it stays
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = false; } );
$countBefore = count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) );
$keptRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a submission whose owner mail did not go out is a 500, not ok - with no body', $keptRequest['/nino/http/response']['statusCode'] === 500 && isset( $keptRequest['/nino/http/response']['body'] ) === false );
check( '...and the inquiry is still recorded where a copy is kept', count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) ) === $countBefore + 1 );
check( '...and the failure is on record for the dashboard', ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) >= 1 );
$appData[ \Nino\Form::STORE ] = false;
$lostRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( '...and with none kept it is a 500 as well - nothing has the inquiry', $lostRequest['/nino/http/response']['statusCode'] === 500 && isset( $lostRequest['/nino/http/response']['body'] ) === false );
unset( $appData[ \Nino\Form::STORE ] );

// Only the owner's mail decides. A transport that refuses the visitor's
// confirmation and takes the owner's: the owner has the inquiry, and a visitor
// sent back to the form would send it twice - 200 whether it is kept or not
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = $mail['to'] === 'owner@example.com'; } );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
$countBefore = count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) );
$confirmRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'only the visitor\'s confirmation refused, the owner mail out: 200, and recorded', $confirmRequest['/nino/http/response']['statusCode'] === 200
	&& ( $confirmRequest['/nino/http/response']['body']['status'] ?? '' ) === 'ok' && count( \Nino\Filesystem::getFileContent( $appData, $formsFile, [] ) ) === $countBefore + 1 );
$appData[ \Nino\Form::STORE ] = false;
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
$confirmRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( '...and with no copy kept the same: 200', $confirmRequest['/nino/http/response']['statusCode'] === 200 && ( $confirmRequest['/nino/http/response']['body']['status'] ?? '' ) === 'ok' );
unset( $appData[ \Nino\Form::STORE ] );
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
/*	The owner's notification goes out in the site's native locale whatever
	language the visitor wrote in, and that switch was made with
	setCurrentLocale() - which writes what it is given into the visitor's
	session. So did the switch back, and what it wrote was whatever
	getCurrentLocale() answered: for a visitor who never picked a locale,
	that is the project's default, which Locales::init() takes care never to
	persist. Its comment says why - "a default nobody chose has no business
	being written into the visitor's session, where it would then outlive a
	later change of the project's native locale" - and sending one inquiry
	was the one thing that wrote it there anyway.

	A fill of its own per locale, because the block above registered
	[[/module/form/subject/owner]] for '*': a fill that answers the same in either
	locale says nothing about which one a mail was rendered in	*/
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
$localeMails = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$localeMails ): void {
	$localeMails[] = $mail;
	$mail['sent'] = true;
} );
\Nino\Html::addFills( $appData, [ '[[/test/locale/probe]]' => 'deutsch' ], 'de_DE' );
\Nino\Html::addFills( $appData, [ '[[/test/locale/probe]]' => 'english' ], 'en_US' );
$appData[ \Nino\Form::FORMS ] = [ [ 'subject' => '[[/test/locale/probe]]', 'ownerTemplate' => '/templates/mail-test', 'userTemplate' => '/templates/mail-test' ] + \Nino\Form::DEFAULT_FORM ];
\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );

// A visitor who has chosen nothing, on a page that declares no locale of its
// own: the current locale is the project default (de_DE here), and their
// session holds no locale at all
\Nino\Runtime::unsetSessionValue( $appData, './nino/locales/current' );
\Nino\Locales::useLocale( $appData, \Nino\Locales::getNativeLocale( $appData ) );

$defaultRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'the inquiry is answered as before', $defaultRequest['/nino/http/response']['statusCode'] === 200 );
check( 'sending one writes no locale into the visitor session', \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', 'unwritten' ) === 'unwritten' );

/*	Which is what makes that write more than untidy: init() reads the session
	value back and lets it win over the project's own native locale. With the
	default pinned there, a project that changes its native locale afterwards
	never reaches the visitor who once wrote in - for as long as their session
	lives. A copy of the sandbox, so the same session is read by a kernel
	that boots with a different native locale	*/
$changedProject = $appData;
$changedProject['/nino/locales/native'] = 'en_US';
\Nino\Locales::init( $changedProject );
check( '...so a later change of the project native locale still reaches them', \Nino\Locales::getCurrentLocale( $changedProject ) === 'en_US' );

// The mail is unchanged by all this: the owner's still renders in the native
// locale while the visitor keeps reading the site in theirs
$localeMails = [];
\Nino\Locales::useLocale( $appData, 'en_US' );
$visitorRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'the owner mail still goes out in the native locale', $visitorRequest['/nino/http/response']['statusCode'] === 200 && ( $localeMails[0]['subject'] ?? '' ) === 'deutsch' );
check( '...and the visitor is still reading the site in their own', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US' );

// A locale the visitor did choose is theirs, and a submission leaves it be
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$localeMails = [];
$chosenRequest = submitForm( $appData, [ 'name' => 'Jo', 'email' => 'jo@example.com', 'message' => 'Hi' ] );
check( 'a locale the visitor did choose survives a submission unchanged', $chosenRequest['/nino/http/response']['statusCode'] === 200
	&& \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', '' ) === 'en_US' );
check( '...and the owner mail is still the native one', ( $localeMails[0]['subject'] ?? '' ) === 'deutsch' );

// The two switches, side by side: one is a choice and is remembered, the
// other is this request's business and is not
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( 'setCurrentLocale persists a choice', \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', '' ) === 'de_DE' );
\Nino\Locales::useLocale( $appData, 'en_US' );
check( '...and useLocale switches the request without touching the session', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US'
	&& \Nino\Runtime::getSessionValue( $appData, './nino/locales/current', '' ) === 'de_DE' );
check( '...and neither takes a locale this project does not have', \Nino\Locales::useLocale( $appData, 'fr_FR' ) === 'en_US'
	&& \Nino\Locales::setCurrentLocale( $appData, 'fr_FR' ) === 'en_US' );

\Nino\Runtime::unsetSessionValue( $appData, './nino/locales/current' );
\Nino\Locales::useLocale( $appData, 'de_DE' );

unset( $appData[ \Nino\Form::STORE ], $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/html/shortcodes']['template'], $appData['./nino/callbacks']['/nino/html/shortcode/template'] );
$appData[ \Nino\Form::FORMS ] = $formsBefore;
@unlink( \Nino\Filesystem::path( $appData, '/templates/mail-test.tpl' ) );

// Removing one entry - the request a person makes about their own inquiry.
// By id, which is why record() writes one
$ids = array_values( array_filter( array_column( \Nino\Form::entries( $appData ), 'id' ) ) );
$removeId = $ids[0];
$countBefore = count( \Nino\Form::entries( $appData ) );

check( 'an id nothing carries removes nothing, and says so', \Nino\Form::remove( $appData, str_repeat( 'a', 16 ) ) === false
	&& count( \Nino\Form::entries( $appData ) ) === $countBefore );
check( 'something that is not an id at all is refused without touching a file', \Nino\Form::remove( $appData, '../../config' ) === false
	&& \Nino\Form::remove( $appData, '' ) === false && count( \Nino\Form::entries( $appData ) ) === $countBefore );
check( 'one submission is removed by its id, and the rest of the month stays', \Nino\Form::remove( $appData, $removeId ) === true
	&& count( \Nino\Form::entries( $appData ) ) === $countBefore - 1
	&& in_array( $removeId, array_column( \Nino\Form::entries( $appData ), 'id' ), true ) === false );
check( 'removing it twice is not an error, it is simply not there', \Nino\Form::remove( $appData, $removeId ) === false );

// The seam a spam guard sits at: the same route callback, ahead of the
// module, and why there is no callback name of its own for it (\Nino\Csrf
// refuses the same way one step earlier, on the global /nino/http/response)
$guarded = 0;
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', static function( array &$appData, array &$request ) use ( &$guarded ): void {
	$guarded++;
	$request['/nino/http/response']['statusCode'] = 418;
}, 1 );

$before = count( \Nino\Form::entries( $appData ) );
$_POST = [ 'name' => 'Spam', 'email' => 'spam@example.com', 'message' => 'Buy', 'location' => '' ];
$refusedRequest = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
\Nino\Callbacks::doCallbacks( $appData, '/nino/http/response/POST://.form', $refusedRequest );
check( 'a guard registered ahead of the module refuses without a callback name of its own', $guarded === 1
	&& $refusedRequest['/nino/http/response']['statusCode'] === 418 && count( \Nino\Form::entries( $appData ) ) === $before );

unset( $appData['./nino/callbacks']['/nino/http/response/POST://.form'][1], $appData[ \Nino\Form::FORMS ] );
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/POST://.form', [ '\Nino\Modules\Form', 'callbackResponse' ] );

// --- Form - checkbox, radio and date, and what problems() says -------------

echo "Form - checkbox, radio and date fields, and Form::problems()\n";

check( 'the vocabulary holds checkbox, radio and date', array_diff( [ 'checkbox', 'radio', 'date' ], \Nino\Form::TYPES ) === [] );

$typed = \Nino\Form::normalize( [ 'key' => 'typed', 'fields' => [
	[ 'name' => 'consent',	'type' => 'checkbox',	'required' => true ],
	[ 'name' => 'plan',			'type' => 'radio',		'options' => [ 'small', ' large ', '', 5 ] ],
	[ 'name' => 'since',		'type' => 'date' ],
] ] );
check( 'the three normalise to what was declared', array_column( $typed['fields'] ?? [], 'type', 'name' ) === [ 'consent' => 'checkbox', 'plan' => 'radio', 'since' => 'date' ]
	&& $typed['fields'][0]['required'] === true );
check( '...a radio keeping its non-empty options, trimmed', ( $typed['fields'][1]['options'] ?? [] ) === [ 'small', 'large' ] );

$noOptions = [ 'key' => 'noopts', 'fields' => [ [ 'name' => 'plan', 'type' => 'radio' ], [ 'name' => 'blank', 'type' => 'radio', 'options' => [ '', '  ' ] ], [ 'name' => 'kept', 'type' => 'text' ] ] ];
check( 'a radio without a non-empty option is left out', array_column( \Nino\Form::normalize( $noOptions )['fields'] ?? [], 'name' ) === [ 'kept' ] );
check( '...and reported, once for each', \Nino\Form::problems( $noOptions ) === [ [ 'field' => 0, 'code' => 'options' ], [ 'field' => 1, 'code' => 'options' ] ] );
check( 'a select without options stays what it was: kept, and nothing to report', array_column( \Nino\Form::normalize( [ 'key' => 'sel', 'fields' => [ [ 'name' => 'pick', 'type' => 'select' ] ] ] )['fields'] ?? [], 'name' ) === [ 'pick' ]
	&& \Nino\Form::problems( [ 'key' => 'sel', 'fields' => [ [ 'name' => 'pick', 'type' => 'select' ] ] ] ) === [] );

// problems(): the code and the place, for each thing normalize() would change
$oneField = static fn( array $field ): array => [ 'key' => 'probe', 'fields' => [ [ 'name' => 'ok' ], $field ] ];

check( 'a name with a non-ascii letter is reported at its field', \Nino\Form::problems( $oneField( [ 'name' => 'Straße' ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ] );
check( '...a label typed into the name box, with its space', \Nino\Form::problems( $oneField( [ 'name' => 'Ihre Nachricht' ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ] );
check( '...an empty name and one of 65 characters', \Nino\Form::problems( $oneField( [ 'name' => '' ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ]
	&& \Nino\Form::problems( $oneField( [ 'name' => str_repeat( 'n', 65 ) ] ) ) === [ [ 'field' => 1, 'code' => 'name' ] ] );
check( '...a reserved name', \Nino\Form::problems( $oneField( [ 'name' => 'date' ] ) ) === [ [ 'field' => 1, 'code' => 'reserved' ] ] );
check( '...a name taken already', \Nino\Form::problems( $oneField( [ 'name' => 'ok' ] ) ) === [ [ 'field' => 1, 'code' => 'duplicate' ] ] );
check( '...a type that is none of the vocabulary, which normalize() repairs to text', \Nino\Form::problems( $oneField( [ 'name' => 'more', 'type' => 'colour' ] ) ) === [ [ 'field' => 1, 'code' => 'type' ] ]
	&& \Nino\Form::normalize( $oneField( [ 'name' => 'more', 'type' => 'colour' ] ) )['fields'][1]['type'] === 'text' );
check( '...while a field with no type is a text field and nothing to report', \Nino\Form::problems( $oneField( [ 'name' => 'more' ] ) ) === [] );
check( '...a field that is not an array', \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [ [ 'name' => 'ok' ], 'text' ] ] ) === [ [ 'field' => 1, 'code' => 'field' ] ] );
check( '...a recipient that is no address', \Nino\Form::problems( [ 'to' => 'a@b' ] + $oneField( [ 'name' => 'more' ] ) ) === [ [ 'field' => null, 'code' => 'to' ] ] );
check( '...a template path that is none, each of the two', \Nino\Form::problems( [ 'ownerTemplate' => '/templates/../x' ] + $oneField( [ 'name' => 'more' ] ) ) === [ [ 'field' => null, 'code' => 'ownerTemplate' ] ]
	&& \Nino\Form::problems( [ 'userTemplate' => 'templates/mail-user' ] + $oneField( [ 'name' => 'more' ] ) ) === [ [ 'field' => null, 'code' => 'userTemplate' ] ] );
check( '...a key that is none', \Nino\Form::problems( [ 'key' => 'Not A Key', 'fields' => [ [ 'name' => 'ok' ] ] ] ) === [ [ 'field' => null, 'code' => 'key' ] ]
	&& \Nino\Form::normalize( [ 'key' => 'Not A Key', 'fields' => [ [ 'name' => 'ok' ] ] ] ) === null );
check( '...also one that is an array, which is a problem and not a warning', \Nino\Form::problems( [ 'key' => [ 'x' ], 'fields' => [ [ 'name' => 'ok' ] ] ] ) === [ [ 'field' => null, 'code' => 'key' ] ]
	&& \Nino\Form::normalize( [ 'key' => [ 'x' ], 'fields' => [ [ 'name' => 'ok' ] ] ] ) === null );
check( '...and a definition with no field left', \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [] ] ) === [ [ 'field' => null, 'code' => 'fields' ] ]
	&& \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [ [ 'name' => 'Straße' ] ] ] ) === [ [ 'field' => 0, 'code' => 'name' ], [ 'field' => null, 'code' => 'fields' ] ] );
check( 'a field that is posted at another place keeps its own index', \Nino\Form::problems( [ 'key' => 'probe', 'fields' => [ 3 => [ 'name' => 'Straße' ], 7 => [ 'name' => 'ok' ] ] ] ) === [ [ 'field' => 3, 'code' => 'name' ] ] );

check( 'the shipped contact form has nothing to report', \Nino\Form::problems( \Nino\Form::DEFAULT_FORM ) === [] );
check( '...nor a clean form of the new types, with an address and templates', \Nino\Form::problems( [
	'key' => 'quote', 'name' => 'Quote', 'to' => 'sales@example.com', 'ownerTemplate' => '/templates/mail-owner', 'userTemplate' => '/templates/mail-user',
	'fields' => [
		[ 'name' => 'email', 'label' => 'Mail', 'type' => 'email', 'required' => true ],
		[ 'name' => 'since', 'label' => 'Since', 'type' => 'date' ],
		[ 'name' => 'plan', 'label' => 'Plan', 'type' => 'radio', 'options' => [ 'small', 'large' ] ],
		[ 'name' => 'consent', 'label' => 'Consent', 'type' => 'checkbox', 'required' => true ],
	],
] ) === [] );

// One routine, so the two cannot drift: nothing to report exactly when
// normalize() keeps every field, the address and both templates
$entries = [
	\Nino\Form::DEFAULT_FORM,
	[ 'key' => 'a', 'fields' => [ [ 'name' => 'x', 'type' => 'radio', 'options' => [ 'one' ] ], [ 'name' => 'y', 'type' => 'date' ] ] ],
	[ 'key' => 'b', 'fields' => [ [ 'name' => 'x', 'type' => 'radio' ] ] ],
	[ 'key' => 'c', 'fields' => [ [ 'name' => 'x' ], [ 'name' => 'x' ] ] ],
	[ 'key' => 'd', 'fields' => [ [ 'name' => 'x', 'type' => 'nope' ] ] ],
	[ 'key' => 'e', 'to' => 'a@b', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'f', 'to' => 'a@b.de', 'ownerTemplate' => '/templates/x', 'userTemplate' => '/templates/y', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'g', 'ownerTemplate' => '/templates/../x', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'h', 'userTemplate' => 'mail-user', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'i', 'fields' => [ [ 'name' => 'Straße' ], [ 'name' => 'date' ], [ 'name' => 'ok' ] ] ],
	[ 'key' => 'Not A Key', 'fields' => [ [ 'name' => 'x' ] ] ],
	[ 'key' => 'j', 'fields' => [] ],
	[ 'key' => 'k', 'fields' => [ 'text', [ 'name' => 'x' ] ] ],
];
$agreeing = true;
foreach( $entries as $entry ) {
	$normalized = \Nino\Form::normalize( $entry );
	$declared = array_map( static fn( mixed $f ): array => is_array( $f ) ? [ $f['name'] ?? '', $f['type'] ?? 'text' ] : [ '', '' ], (array) $entry['fields'] );
	$kept = $normalized !== null
		&& array_map( static fn( array $f ): array => [ $f['name'], $f['type'] ], $normalized['fields'] ) === $declared
		&& $normalized['to'] === ( $entry['to'] ?? '' )
		&& $normalized['ownerTemplate'] === ( $entry['ownerTemplate'] ?? '/templates/mail-owner' )
		&& $normalized['userTemplate'] === ( $entry['userTemplate'] ?? '/templates/mail-user' );
	if( ( \Nino\Form::problems( $entry ) === [] ) !== $kept )
		$agreeing = false;
}
check( 'problems() is empty exactly when normalize() keeps every field, the address and both templates', $agreeing === true );

// normalize() cut at bytes: an ascii letter ahead of 120 two-byte letters puts
// every bound (200, 100) in the middle of one, which left invalid utf-8
$wide = 'a'. str_repeat( 'ä', 120 );
$cut = \Nino\Form::normalize( [ 'key' => 'wide', 'name' => $wide, 'subject' => $wide, 'fields' => [ [ 'name' => 'x', 'label' => $wide, 'type' => 'select', 'options' => [ $wide ] ] ] ] );
check( 'a label, an option, a name and a subject cut at their bound stay valid utf-8', $cut !== null
	&& mb_check_encoding( $cut['fields'][0]['label'], 'UTF-8' ) === true && mb_check_encoding( $cut['fields'][0]['options'][0], 'UTF-8' ) === true
	&& mb_check_encoding( $cut['name'], 'UTF-8' ) === true && mb_check_encoding( $cut['subject'], 'UTF-8' ) === true );
check( '...within the bytes they were bound to', strlen( $cut['fields'][0]['label'] ) <= 200 && strlen( $cut['fields'][0]['options'][0] ) <= 200
	&& strlen( $cut['name'] ) <= 100 && strlen( $cut['subject'] ) <= 200 );

// What a submission of each is held to, through the endpoint
$appData[ \Nino\Form::FORMS ] = [ [
	'key' => 'typed', 'name' => 'Typed', 'to' => 'owner@example.com', 'confirm' => false,
	'fields' => [
		[ 'name' => 'consent',	'label' => 'Consent',	'type' => 'checkbox',	'required' => true ],
		[ 'name' => 'plan',			'label' => 'Plan',		'type' => 'radio',		'options' => [ 'small', 'large' ] ],
		[ 'name' => 'since',		'label' => 'Since',		'type' => 'date' ],
	],
] ];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ): void { $mail['sent'] = true; } );
$typedPost = static function( array $post ) use ( &$appData ): int {
	\Nino\Filesystem::putFileContent( $appData, '/data/ratelimit.php', [] );
	$_POST = array_merge( [ 'form' => 'typed', 'consent' => 'on', 'plan' => '', 'since' => '', 'location' => '' ], $post );
	$request = [ '/nino/http/response' => [ 'statusCode' => 200 ] ];
	\Nino\Modules\Form::callbackResponse( $appData, $request );
	return $request['/nino/http/response']['statusCode'];
};

check( 'a required checkbox that is ticked is accepted', $typedPost( [] ) === 200 );
check( '...and one that is not ticked is a 400', $typedPost( [ 'consent' => '' ] ) === 400 );
check( 'a radio takes one of its options, and an empty answer where it is not required', $typedPost( [ 'plan' => 'large' ] ) === 200 && $typedPost( [ 'plan' => '' ] ) === 200 );
check( '...and a value outside them is a 400', $typedPost( [ 'plan' => 'huge' ] ) === 400 );
check( 'a date is Y-m-d', $typedPost( [ 'since' => '2026-02-28' ] ) === 200 );
check( '...a day the month does not have is a 400', $typedPost( [ 'since' => '2026-02-30' ] ) === 400 );
check( '...and so is the other way round, and a date with no leading zeros', $typedPost( [ 'since' => '28.02.2026' ] ) === 400 && $typedPost( [ 'since' => '2026-2-3' ] ) === 400 );

// [[fields]] is in the mails a project starts from: the answers to the
// fields nobody knew in advance reach the owner, and the visitor
$shipped = [ 'mail-owner', 'mail-user' ];
$shippedFields = true;
foreach( $shipped as $name )
	$shippedFields = $shippedFields && str_contains( (string) file_get_contents( __DIR__. '/../_nino/Nino/Modules/Form/install/templates/'. $name. '.tpl' ), '[[fields]]' );
check( 'both mail templates the form unit ships carry [[fields]]', $shippedFields === true );

$ownerShipped = (string) file_get_contents( __DIR__. '/../_nino/Nino/Modules/Form/install/templates/mail-owner.tpl' );
\Nino\Filesystem::putFileContent( $appData, '/templates/mail-shipped.tpl', str_replace( [ "[template /templates/mail-header]\n", "\n[template /templates/mail-footer]" ], '', $ownerShipped ) );
\Nino\Modules\Template::init( $appData );
$shippedMail = \Nino\Form::render( $appData, \Nino\Form::form( $appData, 'typed' ), [ 'consent' => 'on', 'plan' => 'large', 'since' => '2026-02-28' ], '/templates/mail-shipped' );
check( 'a submission of fields of its own reaches the owner mail through them', str_contains( $shippedMail, '<th>Consent</th><td>on</td>' )
	&& str_contains( $shippedMail, '<th>Plan</th><td>large</td>' ) && str_contains( $shippedMail, '<th>Since</th><td>2026-02-28</td>' ) );
@unlink( \Nino\Filesystem::path( $appData, '/templates/mail-shipped.tpl' ) );

unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/html/shortcodes']['template'], $appData['./nino/callbacks']['/nino/html/shortcode/template'], $appData[ \Nino\Form::FORMS ] );
$_POST = [];

echo "\n";


// --- Mail::send - the per-ip send cap --------------------------------------

echo "Mail::send - the per-ip send cap: five a window, counted by Feature::count() under 'ratelimit'\n";

$ratelimitPath = '/data/ratelimit.php';

// A transport takes the mails, so that the cap is the only thing that answers
$capMails = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$capMails ): void {
	$capMails[] = $mail['to'];
	$mail['sent'] = true;
} );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, [] );

for( $i = 1; $i <= 5; $i++ )
	check( "send $i of 5 stays within budget and is not flagged as refused", \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === true && \Nino\Mail::rateLimited( $appData ) === false );

check( 'the 6th send within the same window is refused, and rateLimited() says it was the cap', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === false && \Nino\Mail::rateLimited( $appData ) === true );
check( '...before any transport was asked', count( $capMails ) === 5 );
check( 'a 7th send stays refused - the counter keeps growing, not stuck at the cap', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === false );

$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
check( 'the file holds this client under the hash of its address, every send counted past the cap', array_keys( $rateState ) === [ $clientKey ] && ( $rateState[$clientKey]['tries'] ?? 0 ) === 7 );
check( '...and the address itself is nowhere in it', str_contains( (string) file_get_contents( \Nino\Filesystem::path( $appData, $ratelimitPath ) ), '127.0.0.1' ) === false );

// Another client, and with it the next call: the flag the refusal left behind
// belongs to that call, so a call within budget reads it as false
$_SERVER['REMOTE_ADDR'] = '203.0.113.2';
check( 'a different client has its own independent budget, and the call after a refusal reads as not refused', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === true && \Nino\Mail::rateLimited( $appData ) === false );
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

$capBatch = [ [ 'to' => 'owner@example.org', 'subject' => 'a', 'body' => 'x', 'replyTo' => '' ] ];
check( 'sendAll() is refused over the same budget and flags it the same way', \Nino\Mail::sendAll( $appData, $capBatch ) === false && \Nino\Mail::rateLimited( $appData ) === true );
check( '...and an empty batch is no action: it answers true and clears the flag', \Nino\Mail::sendAll( $appData, [] ) === true && \Nino\Mail::rateLimited( $appData ) === false );

// Force this client's window to already have elapsed, same as real wall-clock
// expiry - the next send must treat it as a fresh window, not carry the old count
$rateState[$clientKey]['reset'] = time() - 1;
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

check( 'a send after the window elapsed starts a fresh budget', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === true && \Nino\Mail::rateLimited( $appData ) === false );
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
check( 'and the stale entry was dropped rather than accumulating forever', $rateState[$clientKey]['tries'] === 1 );

unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );

echo "\n";


// --- Mail::send - the transport callback -----------------------------------

echo "Mail::send - '/nino/mail/send' takes a mail before mail() does\n";

check( 'the callback name is a constant', \Nino\Mail::TRANSPORT === '/nino/mail/send' );

// A fresh budget for this test's client ip, whatever ran before
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState[$clientKey] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

// The envelope sender is a global textfill, like the owner address it
// falls back to - one place, the Text panel, for both
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'noreply@example.org' ], '*' );

$taken = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$taken ): void {
	$taken[] = $mail;
	$mail['sent'] = true;
} );

check( 'a transport that sets sent = true makes send() answer true without mail()', \Nino\Mail::send( $appData, 'to@example.org', "Grüße\r\nBcc: x@example.org", '<p>Hallo</p>', 'reply@example.org' ) === true && count( $taken ) === 1 );
check( 'it gets the mail as an array with every key', array_keys( $taken[0] ) === [ 'to', 'subject', 'body', 'replyTo', 'sender', 'headers', 'sent' ] );
check( 'the subject is raw utf-8, not mime-encoded - but cleaned of anything that starts a header line', $taken[0]['subject'] === 'GrüßeBcc: x@example.org' );
check( 'to, body, replyTo and the sender arrive as given', $taken[0]['to'] === 'to@example.org' && $taken[0]['body'] === '<p>Hallo</p>' && $taken[0]['replyTo'] === 'reply@example.org' && $taken[0]['sender'] === 'noreply@example.org' );
check( 'the headers are what mail() would get', str_contains( $taken[0]['headers'], 'Content-Type: text/html; charset=UTF-8' ) && str_contains( $taken[0]['headers'], "\r\nFrom: noreply@example.org" ) && str_contains( $taken[0]['headers'], "\r\nReply-To: reply@example.org" ) );
check( 'a display-name address is reduced to the address before the transport sees it', \Nino\Mail::send( $appData, 'Max Mustermann <max@example.org>', 'x', 'y', '' ) === true && $taken[1]['to'] === 'max@example.org' && $taken[1]['replyTo'] === '' );
check( 'an invalid address is refused before any transport', \Nino\Mail::send( $appData, 'not an address', 'x', 'y', '' ) === false && count( $taken ) === 2 );

// mail() refuses a nul byte in any of its arguments with a ValueError -
// which nothing caught, so a message with one (a form field can carry it)
// was a 500 rather than the false send() promises. Dropped with the CR/LF,
// before any transport sees the mail
check( 'a nul byte is dropped from the body and every header value, never thrown at', \Nino\Mail::send( $appData, 'to@example.org', "Sub\0ject", "Hello\0world", "re\0ply@example.org" ) === true
	&& $taken[2]['body'] === 'Helloworld' && $taken[2]['subject'] === 'Subject' && $taken[2]['replyTo'] === 'reply@example.org' );

/*	The reply address was the one thing on a header line here that nothing
	checked. $to is validated and refuses the mail; _getSender() validates
	From and drops it rather than "passing something unchecked to sendmail".
	The reply address had neither, and it comes from where those two do: an
	admin-editable textfill read through renderHtml(). A fill the project
	never installed renders as its own literal, so '[[/project/mail/address/owner]]'
	went out as the Reply-To header verbatim. That fill belongs to the Form
	module's install unit and the wizard offers that module rather than
	installing it always, so a project running the Newsletter feature without
	the contact form sent every confirmation mail with a header naming a fill.

	It is dropped, not refused: the recipient and the body are fine, and a
	confirmation nobody receives is worse than one nobody can reply to. The
	harness silences the warnings trigger_error() raises, so the line it
	records is read back after the calls	*/
// Six more mails than this block budgeted for - a fresh window, the same way
// the block above opened one
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState[$clientKey] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

ninoWarnings();

$replySent = [];
foreach( [ '[[/project/mail/address/owner]]', 'ask us anything', 'a@example.org, b@example.org', '<script>alert(1)</script>' ] as $notAnAddress )
	$replySent[$notAnAddress] = \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', $notAnAddress );

// Six sends, and the per-ip cap is five an hour - a fresh window between the
// two groups, or the last of them never reaches a transport at all
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState[$clientKey] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

$replyKept = [];
foreach( [ 'reply@example.org', 'Max Mustermann <max@example.org>' ] as $isAnAddress )
	$replyKept[$isAnAddress] = \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', $isAnAddress );

$replyWarnings = ninoWarnings();

$replyHeaders = array_map( static fn( array $mail ): string => (string) $mail['replyTo'], array_slice( $taken, 3 ) );

check( 'an unresolved textfill does not go out as the Reply-To header', ( $replyHeaders[0] ?? null ) === '' );
check( '...nor does anything else that is not an address', ( $replyHeaders[1] ?? null ) === '' && ( $replyHeaders[2] ?? null ) === '' && ( $replyHeaders[3] ?? null ) === '' );
check( '...and the mail still goes out - the recipient and the body were never the problem', array_values( $replySent ) === [ true, true, true, true ] );
check( '...with no Reply-To line on it at all', str_contains( $taken[3]['headers'] ?? '', 'Reply-To:' ) === false );
check( '...and one recorded line per mail, naming the value, or nothing tells the operator why replies stopped',
	count( array_filter( $replyWarnings, static fn( string $w ): bool => str_contains( $w, 'is no reply address' ) === true ) ) === 4
	&& count( array_filter( $replyWarnings, static fn( string $w ): bool => str_contains( $w, '[[/project/mail/address/owner]]' ) === true ) ) === 1 );

// A real address is untouched, and so is the display-name form - valid for
// this header, unlike mail()'s own $to, and what a site owner types
/*	Where the From header and the envelope sender come from, which is one
	textfill for every mail this framework sends. It is shipped as
	'[[/project/company/contact/email]]' rather than as an address, so the normal case is the
	mailbox the project already named - one answer, in one place - and an
	operator who needs another one overwrites the key without touching the
	company address. A chained fill, and this is the check that it resolves:
	a value nobody resolves is a From header reading '[[/project/company/contact/email]]'	*/
\Nino\Html::addFills( $appData, [ '[[/project/company/contact/email]]' => 'hallo@example.com', '[[/project/mail/address/owner]]' => '[[/project/company/contact/email]]', '[[/project/mail/address/envelope]]' => '' ], '*' );

$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState[$clientKey] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

$chained = [];
$chainedTransport = static function( array &$appData, array &$mail ) use ( &$chained ): void { $chained[] = $mail; $mail['sent'] = true; };
$transportBefore = $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] ?? null;
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, $chainedTransport );

\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
check( 'the sender is the company address the owner fill points at', ( $chained[0]['sender'] ?? null ) === 'hallo@example.com' );
check( '...and reaches the From header as an address, not as the fill it was written as', str_contains( $chained[0]['headers'] ?? '', "\r\nFrom: hallo@example.com" ) === true );

// An operator who needs a different mailbox overwrites the one key
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/owner]]' => 'kontakt@example.org' ], '*' );
$chained = [];
\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
check( 'overwriting that one key changes the sender and nothing else', ( $chained[0]['sender'] ?? null ) === 'kontakt@example.org'
	&& \Nino\Html::renderHtml( $appData, '[[/project/company/contact/email]]' ) === 'hallo@example.com' );

/*	...and '[[/project/mail/address/envelope]]' wins over both where it is set, because the
	envelope sender has to be an address the sending host may send for
	(spf/dmarc), which is not necessarily the mailbox replies should reach.
	A textfill like the owner address, not a config.php key: the operator
	sets both in the Text panel, and an empty one means "the same"	*/
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'no-reply@example.net' ], '*' );
$chained = [];
\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
check( 'the sender fill wins over the owner fill', ( $chained[0]['sender'] ?? null ) === 'no-reply@example.net'
	&& str_contains( $chained[0]['headers'] ?? '', "\r\nFrom: no-reply@example.net" ) === true );

// ...and one that is not an address falls back to the owner rather than
// costing every mail its From - a typo in the Text panel is one log line
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => 'not an address' ], '*' );
$chained = [];
ninoWarnings();
\Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' );
$senderWarnings = ninoWarnings();
check( 'a sender fill that is no address falls back to the owner, and says so', ( $chained[0]['sender'] ?? null ) === 'kontakt@example.org'
	&& count( array_filter( $senderWarnings, static fn( string $w ): bool => str_contains( $w, 'no sender address' ) ) ) === 1 );

\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => '' ], '*' );
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
if( $transportBefore !== null )
	$appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] = $transportBefore;
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState[$clientKey] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

check( 'a plain reply address is untouched', ( $replyHeaders[4] ?? null ) === 'reply@example.org' && ( $replyKept['reply@example.org'] ?? false ) === true );
check( '...and a display-name address keeps its name, because only the address part has to hold up',
	( $replyHeaders[5] ?? null ) === 'Max Mustermann <max@example.org>'
	&& str_contains( $taken[8]['headers'] ?? '', "\r\nReply-To: Max Mustermann <max@example.org>" ) === true );

// ...and back to the budget the rest of this block was written against
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState[$clientKey] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

// A first transport that leaves sent alone passes the mail on; the one
// after it decides
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
$seen = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$seen ): void {
	$seen[] = 'looked';
}, 1 );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$seen ): void {
	$seen[] = 'refused';
	$mail['sent'] = false;
}, 2 );
check( 'a transport that leaves sent at null passes the mail on; sent = false is a refusal', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === false && $seen === [ 'looked', 'refused' ] );

// The cap comes first: over budget, no transport is asked
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
$rateState[$clientKey] = [ 'tries' => 5, 'reset' => time() + 3600 ];
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );
$seen = [];
check( 'the per-ip cap applies before any transport', \Nino\Mail::send( $appData, 'to@example.org', 'x', 'y', '' ) === false && $seen === [] && \Nino\Mail::rateLimited( $appData ) === true );

// One action of one visitor, several envelopes: a contact form's owner
// notification and the confirmation that answers it. Charging each mail
// separately made a cap of five allow two submissions, and the third was
// answered "sent" while nothing left the server
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
$rateState = \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] );
unset( $rateState[$clientKey] );
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, $rateState );

$batched = [];
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$batched ): void {
	$batched[] = $mail['to'];
	$mail['sent'] = true;
} );

$pair = [
	[ 'to' => 'owner@example.org',   'subject' => 'a', 'body' => 'x', 'replyTo' => 'jo@example.org' ],
	[ 'to' => 'jo@example.org',      'subject' => 'b', 'body' => 'y', 'replyTo' => 'owner@example.org' ],
];
check( 'sendAll delivers every mail of one action', \Nino\Mail::sendAll( $appData, $pair ) === true && $batched === [ 'owner@example.org', 'jo@example.org' ] );
check( 'and charges the cap once for the pair, not once per envelope', ( \Nino\Filesystem::getFileContent( $appData, $ratelimitPath, [] )[$clientKey]['tries'] ?? 0 ) === 1 );

$batched = [];
for( $i = 0; $i < 4; $i++ )
	\Nino\Mail::sendAll( $appData, $pair );
check( 'so five submissions fit the window of five, and the sixth is refused whole', count( $batched ) === 8
	&& \Nino\Mail::sendAll( $appData, $pair ) === false && count( $batched ) === 8 && \Nino\Mail::rateLimited( $appData ) === true );
check( 'an empty batch is not an action and costs nothing', \Nino\Mail::sendAll( $appData, [] ) === true );

unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Html::addFills( $appData, [ '[[/project/mail/address/envelope]]' => '' ], '*' );

echo "\n";


// --- Mail - the record of mail that did not go out --------------------------

echo "Mail::failure - a call that failed leaves a record, one that delivered everything clears it\n";

/*	The workbench's dashboard says "mail has been failing since ...", and
	what it says rests on this: /data/mail-status.php, written once per call
	of send() or sendAll() rather than once per mail. A contact form sends the
	owner's mail and the visitor's confirmation as one call, and a
	confirmation that went out must not wipe the failure of the mail the owner
	is waiting for	*/
$statusPath = '/data/mail-status.php';
$refused 		= [ 'refused@example.org' ];
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ] );
\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array &$mail ) use ( &$refused ): void {
	$mail['sent'] = in_array( $mail['to'], $refused, true ) === false;
} );
$freshWindow = static function() use ( &$appData, $ratelimitPath ): void {
	\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, [] );
};

@unlink( \Nino\Filesystem::path( $appData, $statusPath ) );
$freshWindow();
check( 'with nothing ever refused there is no record', \Nino\Mail::failure( $appData ) === null );

check( 'a delivered mail with no record writes none', \Nino\Mail::send( $appData, 'ok@example.org', 'a', 'b', '' ) === true
	&& is_file( \Nino\Filesystem::path( $appData, $statusPath ) ) === false && ( $appData['./nino/mail/results'] ?? null ) === [ true ] );

$freshWindow();
check( 'a refused send answers false and is on record: since, last, count 1', \Nino\Mail::send( $appData, 'refused@example.org', 'a', 'b', '' ) === false
	&& ( $appData['./nino/mail/results'] ?? null ) === [ false ] );
$record = \Nino\Mail::failure( $appData );
check( '...the two dates are minutes, equal for the first failure', $record !== null && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $record['since'] ) === 1
	&& $record['since'] === $record['last'] && $record['count'] === 1 );
check( '...and holds no address, no subject and nothing a transport said', str_contains( (string) file_get_contents( \Nino\Filesystem::path( $appData, $statusPath ) ), 'example.org' ) === false );

// since stays where the failing began, last moves, count counts calls
\Nino\Filesystem::putFileContent( $appData, $statusPath, [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => 1 ] );
$freshWindow();
$minuteBefore = date( 'Y-m-d H:i' );
\Nino\Mail::send( $appData, 'refused@example.org', 'a', 'b', '' );
$record = \Nino\Mail::failure( $appData );
check( 'a second failure keeps the first date and adds one to the count', $record !== null && $record['since'] === '2026-01-02 03:04' && $record['count'] === 2 && in_array( $record['last'], [ $minuteBefore, date( 'Y-m-d H:i' ) ], true ) );

// A call that sent nothing neither raises nor clears: the cap refused it, or
// there was nothing to send
\Nino\Filesystem::putFileContent( $appData, $ratelimitPath, [ $clientKey => [ 'tries' => 5, 'reset' => time() + 3600 ] ] );
$appData['./nino/mail/results'] = [ true, true ];
check( 'a send the cap refused answers false and leaves the record as it was', \Nino\Mail::send( $appData, 'ok@example.org', 'a', 'b', '' ) === false
	&& ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 2 );
check( '...with no results left over from the call before', ( $appData['./nino/mail/results'] ?? null ) === [] );
$appData['./nino/mail/results'] = [ true ];
check( 'a batch the cap refused leaves it as it was too', \Nino\Mail::sendAll( $appData, [ [ 'to' => 'ok@example.org', 'subject' => 'a', 'body' => 'b', 'replyTo' => '' ] ] ) === false
	&& ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 2 && ( $appData['./nino/mail/results'] ?? null ) === [] );
$freshWindow();
$appData['./nino/mail/results'] = [ true ];
check( 'an empty batch is not an action: nothing cleared, results reset', \Nino\Mail::sendAll( $appData, [] ) === true
	&& ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 2 && ( $appData['./nino/mail/results'] ?? null ) === [] );

// The regression the per-call write exists for: the owner's mail refused and
// the confirmation behind it delivered is one failed call, not a failure
// wiped by the mail that went out
$pair = [
	[ 'to' => 'refused@example.org', 'subject' => 'a', 'body' => 'x', 'replyTo' => 'jo@example.org' ],
	[ 'to' => 'jo@example.org',      'subject' => 'b', 'body' => 'y', 'replyTo' => 'refused@example.org' ],
];
$freshWindow();
check( 'a batch whose first mail is refused and second delivered is false, one result per mail, in order', \Nino\Mail::sendAll( $appData, $pair ) === false
	&& ( $appData['./nino/mail/results'] ?? null ) === [ false, true ] );
check( '...and keeps the record, the count moved on', ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 3 );

$freshWindow();
check( 'a batch whose second mail is refused is the same failure', \Nino\Mail::sendAll( $appData, array_reverse( $pair ) ) === false
	&& ( $appData['./nino/mail/results'] ?? null ) === [ true, false ] && ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 4 );

$freshWindow();
check( 'a batch in which every mail is delivered clears the record', \Nino\Mail::sendAll( $appData, [ $pair[1], $pair[1] ] ) === true
	&& ( $appData['./nino/mail/results'] ?? null ) === [ true, true ] && \Nino\Mail::failure( $appData ) === null );

\Nino\Filesystem::putFileContent( $appData, $statusPath, [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => 1 ] );
$freshWindow();
check( 'so does one delivered mail', \Nino\Mail::send( $appData, 'ok@example.org', 'a', 'b', '' ) === true && \Nino\Mail::failure( $appData ) === null );

// A file that is not what this class writes is no record - and the next
// failure writes a new one over it
foreach( [
	'a string'				=> 'broken',
	'no dates'				=> [ 'count' => 2 ],
	'a date that is not one'	=> [ 'since' => 'yesterday', 'last' => '2026-01-02 03:04', 'count' => 1 ],
	'a count of nought'		=> [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => 0 ],
	'a count that is text'	=> [ 'since' => '2026-01-02 03:04', 'last' => '2026-01-02 03:04', 'count' => '3' ],
] as $label => $content ) {
	\Nino\Filesystem::putFileContent( $appData, $statusPath, $content );
	check( 'a status file holding '. $label. ' is no record', \Nino\Mail::failure( $appData ) === null );
}
\Nino\Filesystem::putFileContent( $appData, $statusPath, [ 'since' => 'yesterday', 'count' => 'x' ] );
$freshWindow();
$minuteBefore = date( 'Y-m-d H:i' );
\Nino\Mail::send( $appData, 'refused@example.org', 'a', 'b', '' );
check( '...and the next failure starts a clean one', ( \Nino\Mail::failure( $appData )['count'] ?? 0 ) === 1 && in_array( \Nino\Mail::failure( $appData )['since'] ?? '', [ $minuteBefore, date( 'Y-m-d H:i' ) ], true ) );

// A transport that takes no mail and leaves 'sent' alone falls through to
// mail(): not exercised here, the record rests on _deliver()'s answer alone
unset( $appData['./nino/callbacks'][ \Nino\Mail::TRANSPORT ], $appData['./nino/mail/results'] );
\Nino\Filesystem::putFileContent( $appData, $statusPath, [] );
$freshWindow();

echo "\n";


// --- RotatingLog::prune ----------------------------------------------------

echo "RotatingLog::prune - shared dated-file sweep (Runtime, Form, Admin\\Logs, Admin\\Backup)\n";

$rotDir = $sandbox. '/rotatinglog';
mkdir( $rotDir, 0777, true );

$oldMonthly 		= $rotDir. '/logs.2020-01.php';
$freshMonthly 	= $rotDir. '/logs.'. date( 'Y-m' ). '.php';
$oldDaily 			= $rotDir. '/2020-01-01.php';
$freshDaily 		= $rotDir. '/'. date( 'Y-m-d' ). '.php';
$unparseable 		= $rotDir. '/pre-restore-1234567890.php';

foreach( [ $oldMonthly, $freshMonthly, $oldDaily, $freshDaily, $unparseable ] as $f )
	file_put_contents( $f, '<?php return [];' );

$monthlyCutoff = ( new \DateTime( 'first day of -3 months' ) )->setTime( 0, 0 );
\Nino\RotatingLog::prune( $rotDir, 'logs.', 'Y-m', '.php', $monthlyCutoff );

check( 'an old monthly bucket past the cutoff is deleted', is_file( $oldMonthly ) === false );
check( 'a fresh monthly bucket is kept', is_file( $freshMonthly ) === true );
check( 'a filename this sweep does not own (no "logs." prefix) is untouched', is_file( $unparseable ) === true );

// Regression: an empty $suffix used to hide every file from this sweep -
// substr()'s own -strlen('') is -0, and PHP has no negative zero, so that
// collapsed to a length of 0 ("take zero characters") instead of "to the
// end of the string". The date portion was always extracted as '', which
// never parses, so every file silently survived regardless of its age.
$noSuffixOld 		= $rotDir. '/nosuffix.2020-01';
$noSuffixFresh	= $rotDir. '/nosuffix.'. date( 'Y-m' );
file_put_contents( $noSuffixOld, 'x' );
file_put_contents( $noSuffixFresh, 'x' );

\Nino\RotatingLog::prune( $rotDir, 'nosuffix.', 'Y-m', '', $monthlyCutoff );

check( 'an empty suffix still deletes an old file past the cutoff', is_file( $noSuffixOld ) === false );
check( 'an empty suffix still keeps a fresh file', is_file( $noSuffixFresh ) === true );

$dailyCutoff = ( new \DateTime( '-14 days' ) )->setTime( 0, 0 );
\Nino\RotatingLog::prune( $rotDir, '', 'Y-m-d', '.php', $dailyCutoff );

check( 'an old daily file past the cutoff is deleted', is_file( $oldDaily ) === false );
check( 'a fresh daily file is kept', is_file( $freshDaily ) === true );
check( 'a same-directory file whose name does not parse as a date is left alone, not deleted', is_file( $unparseable ) === true );

/*	Regression: the sweep globbed '<dir>/<prefix>*<suffix>', and a directory is
	a path rather than a pattern. One project installed below a directory
	called "site[2]" - or any name carrying '[', ']', '*' or '?' - had the
	brackets read as a character class, so the pattern described a path that
	does not exist, glob() answered nothing and the sweep pruned nothing at
	all, for the life of that installation and without a word about it	*/
$bracketDir 	= $rotDir. '/site[2]';
mkdir( $bracketDir, 0777, true );
$bracketOld 	= $bracketDir. '/logs.2020-01.php';
$bracketFresh	= $bracketDir. '/logs.'. date( 'Y-m' ). '.php';
file_put_contents( $bracketOld, '<?php return [];' );
file_put_contents( $bracketFresh, '<?php return [];' );

\Nino\RotatingLog::prune( $bracketDir, 'logs.', 'Y-m', '.php', $monthlyCutoff );

check( 'a directory whose name carries glob metacharacters is swept like any other', is_file( $bracketOld ) === false );
check( '...and the fresh bucket in it is still kept', is_file( $bracketFresh ) === true );

/*	And a $dateFormat the sweep was never taught. 'Y-m' and 'Y-m-d' were the
	only two it understood - the date was always parsed as a full 'Y-m-d' - so
	every other format a caller might name parsed as nothing, matched nothing
	and deleted nothing, in the same silence	*/
$compactOld 	= $rotDir. '/report.20200101.txt';
$compactFresh	= $rotDir. '/report.'. date( 'Ymd' ). '.txt';
file_put_contents( $compactOld, 'x' );
file_put_contents( $compactFresh, 'x' );

\Nino\RotatingLog::prune( $rotDir, 'report.', 'Ymd', '.txt', $dailyCutoff );

check( 'a date format the kernel does not use itself deletes what it names', is_file( $compactOld ) === false );
check( '...and keeps what is inside the cutoff', is_file( $compactFresh ) === true );

// The anchoring the two kernel formats depend on, pinned where today's date
// cannot reach it: a bucket named for a month is the first of that month,
// never today's day-of-month in it - which would drift the boundary from day
// to day and roll a bucket into the next month entirely (parsing "...-02" on
// the 31st)
$anchorInside		= $rotDir. '/anchor.2020-01.php';
$anchorOutside	= $rotDir. '/anchor.2020-02.php';
file_put_contents( $anchorInside, 'x' );
file_put_contents( $anchorOutside, 'x' );

\Nino\RotatingLog::prune( $rotDir, 'anchor.', 'Y-m', '.php', new \DateTime( '2020-01-15 00:00:00' ) );

check( 'a monthly bucket is judged by the first of its month', is_file( $anchorInside ) === false );
check( '...and one whose month starts after the cutoff is kept', is_file( $anchorOutside ) === true );

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
