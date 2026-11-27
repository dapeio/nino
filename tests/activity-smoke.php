<?php
declare(strict_types=1);

/**
 *	Nino									A compact filesystembased php framework
 *	activity-smoke.php		The activity log's one writer, \Nino\Activity: what a line is
 *												whoever writes it (one line, the three fields kept, cut, never a
 *												forged actor, nothing for a value that is not text), the call of a
 *												feature (its actor, the key checked against the features that are on,
 *												the log switched off, the Logs module not delivered), a process that
 *												has only the kernel loaded, two processes appending at once, the
 *												bytes of an ordinary line, and \Nino\Http::string(). The split of a
 *												line is read by the real script of the Log panel, in node.
 *
 *	Usage: php tests/activity-smoke.php
 */

const ACTIVITY_PASSWORD = 'correct horse battery staple';

/**
 *	A project directory booted as a request would boot it: the paths, then config.php read
 *
 *	@param		string		$sandbox
 *
 *	@return		array
 */
function activityBoot( string $sandbox ): array {

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );
	$appData['./nino/filesystem/path']				= $sandbox;
	$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';
	\Nino\AppData::init( $appData );

	return $appData;
}

// Worker modes - this same file once more, as a process of its own, before the harness is loaded: the kernel alone, as index.php loads it for a
// surface outside the workbench. Arguments: mode, the tree of the kernel, the sandbox, then the mode's own
if( in_array( $argv[1] ?? '', [ 'outside', 'append' ], true ) === true ) {

	define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );
	require $argv[2]. '/_nino/Nino.php';

	$raised = [];
	// What the code silenced with @ is not an error anyone sees; the manifests of the fixtures that do not validate are said once, by the kernel's
	// own reading of the features
	set_error_handler( static function( int $level, string $message ) use ( &$raised ): bool {
		if( ( error_reporting() & $level ) !== 0 && preg_match( '/^Feature (manifest|in) /', $message ) !== 1 )
			$raised[] = $level. ': '. $message;
		return true;
	} );

	$appData = activityBoot( $argv[3] );

	// One line from a process with no workbench: [ feature, message, signed in ]
	if( $argv[1] === 'outside' ) {

		$before = class_exists( '\Nino\Admin\Admin', false );

		if( ( $argv[5] ?? '0' ) === '1' )
			\Nino\Auth::loginUser( $appData, 'adm@example.com', ACTIVITY_PASSWORD );

		// '@write' is the workbench's own writer with an actor it names
		$answer = $argv[4] === '@write' ? \Nino\Activity::write( $appData, 'adm@example.com', 'Sent the document 42 to the client 7' ) : \Nino\Activity::record( $appData, $argv[4], 'Sent the document 42 to the client 7' );

		echo json_encode( [ 'before' => $before, 'answer' => $answer, 'after' => class_exists( '\Nino\Admin\Admin', false ), 'raised' => $raised ] );
		exit( 0 );
	}

	// Rounds of lines under one tag
	for( $round = 1; $round <= (int) $argv[5]; $round++ ) {
		$appData = activityBoot( $argv[3] );
		\Nino\Activity::record( $appData, 'sample', 'Worker '. $argv[4]. ' line '. $round );
	}

	exit( 0 );
}

// The features of the fixtures: sample and helper are switched on below, old is there and off
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'activity' );
$sandbox = ninoSandboxDir( $appData );
$root		 = dirname( __DIR__ );

$appData['/nino/modules']			= [ '\\Nino\\Modules\\Sample', '\\Nino\\Modules\\Helper' ];
$appData['/nino/admin/logs']	= true;
\Nino\AppData::writeContentData( $appData, [ '/nino/modules', '/nino/admin/logs' ] );
ninoUser( $appData, 'adm@example.com' );
ninoWarnings();

$logDir  = $sandbox. '/private/.logs';
$logFile = $logDir. '/'. date( 'Y-m-d' ). '.php';

/** The lines of today's file, as a reader decodes them */
$linesOf = static function( ?string $file = null ) use ( $logFile ): array {

	$raw = is_file( $file ?? $logFile ) === true ? (string) file_get_contents( $file ?? $logFile ) : '';

	if( str_starts_with( $raw, \Nino\Backup\Seal::STUB_PREFIX ) === false || str_ends_with( $raw, \Nino\Backup\Seal::STUB_SUFFIX ) === false )
		return [];

	$decoded = (string) base64_decode( substr( $raw, strlen( \Nino\Backup\Seal::STUB_PREFIX ), -strlen( \Nino\Backup\Seal::STUB_SUFFIX ) ) );

	return $decoded === '' ? [] : explode( "\n", $decoded );
};

/** Today's file taken away, as a site that has not written yet */
$clear = static function() use ( $logFile ): void {
	if( is_file( $logFile ) === true )
		unlink( $logFile );
};

/** The real script of the Log panel splits each line, in node: [ actor, text ] for each, null for a line that does not parse */
$split = static function( array $lines ) use ( $root ): array {

	$script = 'const h0 = require( process.argv[1]+ "/tests/harness.js" ); const h = h0.adminPage(); h.show("logs");'
		. 'h0.load( "_admin/Nino/Modules/Logs/assets/admin.js", h.context );'
		. 'console.log( JSON.stringify( JSON.parse( process.argv[2] ).map( line => { const row = h.admin.logs._parse( line, "k" ); return row.when === "" ? null : [ row.actor, row.text ]; } ) ) );';
	$answer = json_decode( (string) shell_exec( 'cd '. escapeshellarg( $root ). ' && node -e '. escapeshellarg( $script ). ' '. escapeshellarg( $root ). ' '. escapeshellarg( (string) json_encode( $lines ) ). ' 2>&1' ), true );

	return is_array( $answer ) === true ? $answer : [];
};

$forbidden = '/[\x{0085}\x{2028}\x{2029}\x{061C}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{2066}-\x{2069}\x{FEFF}]/u';
$shape		 = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}  (\S(?:\S| (?=\S))*)  (\S(?:\S| (?=\S))*)$/u';

// --- the value of a line ------------------------------------------------------

echo "A line, whoever writes it: every hostile value as the actor and as the message\n";

// [ label, value, what the line holds of it ]
$hostile = [
	[ 'plain',								'Edit Element /news/first',												'Edit Element /news/first' ],
	[ 'LF and a forged line',	"one\n2026-01-01 00:00  root@example.com  Delete User x",	'one 2026-01-01 00:00 root@example.com Delete User x' ],
	[ 'CR',										"one\rtwo",																				'one two' ],
	[ 'CRLF',									"one\r\ntwo",																			'one two' ],
	[ 'NUL',									"one\0two",																				'one two' ],
	[ 'TAB',									"one\ttwo",																				'one two' ],
	[ 'ESC',									"one\e[31mred",																		'one [31mred' ],
	[ 'DEL',									"one\x7ftwo",																			'one two' ],
	[ 'C1 U+0080',						"one\u{0080}two",																	'one two' ],
	[ 'NEL U+0085',						"one\u{0085}two",																	'one two' ],
	[ 'C1 U+009F',						"one\u{009F}two",																	'one two' ],
	[ 'LS U+2028',						"one\u{2028}two",																	'one two' ],
	[ 'PS U+2029',						"one\u{2029}two",																	'one two' ],
	[ 'ALM U+061C',						"one\u{061C}two",																	'one two' ],
	[ 'LRM U+200E',						"one\u{200E}two",																	'one two' ],
	[ 'RLM U+200F',						"one\u{200F}two",																	'one two' ],
	[ 'LRE U+202A',						"one\u{202A}two",																	'one two' ],
	[ 'RLE U+202B',						"one\u{202B}two",																	'one two' ],
	[ 'PDF U+202C',						"one\u{202C}two",																	'one two' ],
	[ 'LRO U+202D',						"one\u{202D}two",																	'one two' ],
	[ 'RLO U+202E',						"one\u{202E}owt",																	'one owt' ],
	[ 'LRI U+2066',						"one\u{2066}two",																	'one two' ],
	[ 'RLI U+2067',						"one\u{2067}two",																	'one two' ],
	[ 'FSI U+2068',						"one\u{2068}two",																	'one two' ],
	[ 'PDI U+2069',						"one\u{2069}two",																	'one two' ],
	[ 'BOM U+FEFF',						"one\u{FEFF}two",																	'one two' ],
	[ 'ZWSP U+200B',					"one\u{200B}two",																	'one two' ],
	[ 'ZWNJ U+200C',					"one\u{200C}two",																	'one two' ],
	[ 'ZWJ U+200D',						"one\u{200D}two",																	'one two' ],
	[ 'WJ U+2060',						"one\u{2060}two",																	'one two' ],
	[ 'no-break space',				"one\u{00A0}two",																	'one two' ],
	[ 'em space',							"one\u{2003}two",																	'one two' ],
	[ 'narrow no-break space',"one\u{202F}two",																	'one two' ],
	[ 'ideographic space',		"one\u{3000}two",																	'one two' ],
	[ 'soft hyphen U+00AD',		"one\u{00AD}two",																	'one two' ],
	[ 'Mongolian vowel sep. U+180E',	"one\u{180E}two",													'one two' ],
	[ 'function application U+2061',	"one\u{2061}two",													'one two' ],
	[ 'invisible times U+2062',	"one\u{2062}two",																'one two' ],
	[ 'invisible separator U+2063',	"one\u{2063}two",														'one two' ],
	[ 'invisible plus U+2064',	"one\u{2064}two",																'one two' ],
	[ 'inhibit swapping U+206A',	"one\u{206A}two",															'one two' ],
	[ 'nominal digit shapes U+206F',	"one\u{206F}two",													'one two' ],
	[ 'interlinear anchor U+FFF9',	"one\u{FFF9}two",														'one two' ],
	[ 'interlinear terminator U+FFFB',	"one\u{FFFB}two",												'one two' ],
	[ 'language tag U+E0001',	"one\u{E0001}two",																'one two' ],
	[ 'tag letter U+E0061',		"one\u{E0061}\u{E007F}two",													'one two' ],
	[ 'grapheme joiner U+034F',	"one\u{034F}two",																'one two' ],
	[ 'Hangul filler U+115F',	"one\u{115F}two",																'one two' ],
	[ 'Hangul jungseong filler U+1160',	"one\u{1160}two",												'one two' ],
	[ 'Khmer inherent vowel U+17B4',	"one\u{17B4}two",													'one two' ],
	[ 'Khmer inherent vowel U+17B5',	"one\u{17B5}two",													'one two' ],
	[ 'Hangul filler U+3164',	"one\u{3164}two",																'one two' ],
	[ 'halfwidth Hangul filler U+FFA0',	"one\u{FFA0}two",												'one two' ],
	[ 'Mongolian selector U+180B',	"one\u{180B}two",													'one two' ],
	[ 'Mongolian selector U+180C',	"one\u{180C}two",													'one two' ],
	[ 'Mongolian selector U+180D',	"one\u{180D}two",													'one two' ],
	[ 'Mongolian selector U+180F',	"one\u{180F}two",													'one two' ],
	[ 'Braille blank U+2800',	"one\u{2800}two",																'one two' ],
	[ 'umlauts, an emoji with a variation selector, a euro sign',	"Gr\u{00FC}\u{00DF}e \u{2764}\u{FE0F} \u{20AC}5 \u{00E4}\u{00F6}",	"Gr\u{00FC}\u{00DF}e \u{2764}\u{FE0F} \u{20AC}5 \u{00E4}\u{00F6}" ],
	[ 'two blanks',						'one  two  three',																'one two three' ],
	[ 'a run of mixed blanks',"one \t \u{00A0}\u{200B} two",										'one two' ],
	[ 'blanks around',				"  one two \n",																		'one two' ],
	[ 'markup',								'<img src=x onerror=alert(1)><b>bold</b>',				'<img src=x onerror=alert(1)><b>bold</b>' ],
	[ 'a character of four bytes',"one \u{1F600} two",													"one \u{1F600} two" ],
];

$cleanedMessage = [];
$cleanedActor		= [];

foreach( $hostile as [ $label, $value, $meant ] ) {

	$clear();

	// As the message, through both ways: the workbench's call with an actor of its own, and the call of a feature (signed in as adm)
	\Nino\Admin\Admin::record( $appData, 'adm@example.com', $value );
	$answer = \Nino\Activity::record( $appData, 'sample', $value );
	// As the actor, through the workbench's call
	\Nino\Admin\Admin::record( $appData, $value, 'Login' );
	$lines = $linesOf();
	$parts = $split( $lines );

	check( $label. ': three calls leave exactly three lines in the file, and the call of a feature answers true', count( $lines ) === 3 && $answer === true );
	check( '...the panel reads the actor that was meant and the sentence that was meant, for the message written by the workbench and by a feature',
		( $parts[0] ?? null ) === [ 'adm@example.com', $meant ] && ( $parts[1] ?? null ) === [ 'adm@example.com', $meant ] );
	check( '...and for the value as the actor: nothing of the message is read as the actor, and the message is the one that was written', ( $parts[2] ?? null ) === [ $meant, 'Login' ] );

	// countSince() reads the actor the same way: the line of the actor with its Login is the visit it is told of, and is not counted
	$past = time() - 3600;
	check( '...countSince() sees the three lines, and skips the sign-in of the actor that was meant', \Nino\Modules\Logs\Admin::countSince( $appData, $past ) === 3
		&& \Nino\Modules\Logs\Admin::countSince( $appData, $past, $meant, time() - 120 ) === 2 );

	$cleanedMessage[$label] = $lines;
}

$all = [];
foreach( $cleanedMessage as $lines )
	$all = array_merge( $all, $lines );

check( 'no line of all of them holds two blanks outside its two separators, none begins or ends a field with a blank', count( array_filter( $all, static fn( string $line ): bool => preg_match( $shape, $line ) !== 1 ) ) === 0 );
check( '...and none holds U+0085, U+2028, U+2029, a direction control, a zero-width character or the byte order mark', count( array_filter( $all, static fn( string $line ): bool => preg_match( $forbidden, $line ) === 1 ) ) === 0 );

echo "\nA field of a certain length, and one that is no text\n";

$clear();
\Nino\Admin\Admin::record( $appData, 'adm@example.com', str_repeat( 'x', 100000 ) );
\Nino\Admin\Admin::record( $appData, str_repeat( 'y', 100000 ), 'Login' );
\Nino\Admin\Admin::record( $appData, str_repeat( "\u{00E9}", 300 ), 'Login' );
\Nino\Activity::record( $appData, 'sample', str_repeat( "\u{1F600}", 1000 ) );
$lines = $linesOf();
check( '100 000 characters of a message land as 300, and of an actor as 254', count( $lines ) === 4 && $lines[0] === date( 'Y-m-d H:i' ). '  adm@example.com  '. str_repeat( 'x', 300 )
	&& $lines[1] === date( 'Y-m-d H:i' ). '  '. str_repeat( 'y', 254 ). '  Login' );
check( '...counted in characters, not in bytes: 300 characters of two bytes are 254, 1000 of four bytes are 300', $lines[2] === date( 'Y-m-d H:i' ). '  '. str_repeat( "\u{00E9}", 254 ). '  Login'
	&& $lines[3] === date( 'Y-m-d H:i' ). '  adm@example.com  '. str_repeat( "\u{1F600}", 300 ) );
check( '...and the day\'s file is a few kilobytes, not 200', filesize( $logFile ) < 4096 );

// The cut is never inside a character
$clear();
\Nino\Admin\Admin::record( $appData, 'adm@example.com', str_repeat( 'a', 299 ). "\u{1F600}more" );
\Nino\Admin\Admin::record( $appData, 'adm@example.com', str_repeat( 'a', 300 ). 'b' );
\Nino\Admin\Admin::record( $appData, 'adm@example.com', str_repeat( 'a', 299 ). ' bbb' );
\Nino\Admin\Admin::record( $appData, 'adm@example.com', str_repeat( 'a', 300 ) );
$lines = $linesOf();
$last	 = static fn( string $line ): string => substr( $line, strlen( date( 'Y-m-d H:i' ). '  adm@example.com  ' ) );
check( 'a message of 299 ascii characters and one of four bytes is kept whole, and what follows it is cut off', $last( $lines[0] ) === str_repeat( 'a', 299 ). "\u{1F600}" && preg_match( '//u', $lines[0] ) === 1 );
check( '...the 301st character is cut off', $last( $lines[1] ) === str_repeat( 'a', 300 ) );
check( '...a cut that ends on a blank leaves no blank at the end', $last( $lines[2] ) === str_repeat( 'a', 299 ) );
check( '...a message of exactly 300 is kept', $last( $lines[3] ) === str_repeat( 'a', 300 ) );

// The cut is of the cleaned text: 300 visible characters behind separators stay 300
$clear();
\Nino\Admin\Admin::record( $appData, 'adm@example.com', str_repeat( "abcdef\u{200B}\u{200B}\u{200B}", 400 ) );
$lines = $linesOf();
check( 'a message of letters and separators is cut after it was cleaned: 300 characters remain, not what 300 raw ones leave', mb_strlen( $last( $lines[0] ) ) === 300 && $last( $lines[0] ) === mb_substr( str_repeat( 'abcdef ', 400 ), 0, 300 ) );

// Two accounts whose mails are alike for their first 120 characters stay two actors
$clear();
$mailA = 'x'. str_repeat( 'a', 59 ). '@'. str_repeat( 'b', 63 ). '.'. str_repeat( 'c', 63 ). '.example.org';
$mailB = 'x'. str_repeat( 'a', 59 ). '@'. str_repeat( 'b', 63 ). '.'. str_repeat( 'c', 63 ). '.example.com';
\Nino\Admin\Admin::record( $appData, $mailA, 'Login' );
\Nino\Admin\Admin::record( $appData, $mailB, 'Login' );
$lines = $linesOf();
$parts = $split( $lines );
check( 'two valid mails of more than 120 characters that differ only at the end are two actors for the panel', filter_var( $mailA, FILTER_VALIDATE_EMAIL ) !== false && filter_var( $mailB, FILTER_VALIDATE_EMAIL ) !== false && strlen( $mailA ) > 190
	&& ( $parts[0] ?? null ) === [ $mailA, 'Login' ] && ( $parts[1] ?? null ) === [ $mailB, 'Login' ] );
check( '...and for countSince(): the sign-in of one does not hide the sign-in of the other', \Nino\Modules\Logs\Admin::countSince( $appData, time() - 3600 ) === 2 && \Nino\Modules\Logs\Admin::countSince( $appData, time() - 3600, $mailA, time() - 120 ) === 1 );

// The file keeps the mode it had
$clear();
\Nino\Admin\Admin::record( $appData, 'adm@example.com', 'First' );
$modes = [];
foreach( [ 0640, 0600 ] as $mode ) {
	chmod( $logFile, $mode );
	clearstatcache();
	\Nino\Admin\Admin::record( $appData, 'adm@example.com', 'Next' );
	clearstatcache( true, $logFile );
	$modes[] = fileperms( $logFile ) & 0777;
}
check( 'the day\'s file keeps its mode across a write, whatever it is (0640, 0600)', $modes === [ 0640, 0600 ] && count( $linesOf() ) === 3 );

// Not text, and nothing: no line, false, a notice as for a write that failed
$clear();
$refused = [ 'bytes ff and fe' => "one\xff\xfetwo", 'a cut multibyte character' => "one\xE2\x82", 'an overlong form' => "one\xC0\xAFtwo", 'a lone continuation byte' => "\x80", 'empty' => '', 'blanks only' => "   \n\t ", 'controls and separators only' => "\0\u{200B}\u{2028}\u{FEFF}", 'invisible format characters only' => "\u{00AD}\u{180E}\u{2061}\u{FFF9}\u{E0001}", 'Mongolian selectors only' => "\u{180B}\u{180C}\u{180D}\u{180F}", 'fillers only' => "\u{034F}\u{115F}\u{1160}\u{17B4}\u{17B5}\u{3164}\u{FFA0}\u{2800}" ];
$bad		 = [];

foreach( $refused as $label => $value ) {

	foreach( [ 'message through the workbench', 'actor through the workbench', 'message of a feature' ] as $way ) {

		$answer = null;
		$errors = ninoErrors( function() use ( &$appData, $value, $way, &$answer ): void {
			match( $way ) {
				'message through the workbench'	=> \Nino\Admin\Admin::record( $appData, 'adm@example.com', $value ),
				'actor through the workbench'		=> \Nino\Admin\Admin::record( $appData, $value, 'Login' ),
				default													=> $answer = \Nino\Activity::record( $appData, 'sample', $value ),
			};
		} );

		if( $linesOf() !== [] || ( $way === 'message of a feature' && $answer !== false ) || count( $errors ) !== 1 || $errors[0][0] !== E_USER_NOTICE || str_starts_with( $errors[0][1], 'Activity log write failed: ' ) === false )
			$bad[] = $label. ' / '. $way;
	}
}

check( 'a value that is not valid UTF-8, or is empty once cleaned, writes no line through any way, the call of a feature answers false, and each is one notice', $bad === [] );
check( '...the file is not even made for it', is_file( $logFile ) === false );

$answer = \Nino\Activity::write( $appData, 'adm@example.com', 'A line' );
check( 'write() answers true for a line that is in the file, and the line is the one', $answer === true && $linesOf() === [ date( 'Y-m-d H:i' ). '  adm@example.com  A line' ] );

echo "\nThe bytes of an ordinary line\n";

$clear();
$expected = [];
foreach( [ [ 'adm@example.com', 'Login' ], [ 'adm@example.com', 'Save Text /template/page-home/main-hero/title' ], [ 'recovery.php', 'Reset Password a.b@example.com' ], [ 'adm@example.com', "Saved \"x\" - \u{00E4}\u{00F6}\u{00FC} \u{20AC} <b>" ], [ 'adm@example.com', 'Activate feature "themes" (not activated: PHP 8.4 needed)' ], [ 'adm@example.com', "Gr\u{00FC}\u{00DF}e \u{2764}\u{FE0F} \u{20AC}5 \u{1F600}" ] ] as [ $who, $what ] ) {
	\Nino\Admin\Admin::record( $appData, $who, $what );
	$expected[] = date( 'Y-m-d H:i' ). '  '. $who. '  '. $what;
}
check( 'the file holds the stub, the lines joined by a line break in base64, and the stub\'s end - byte for byte what an ordinary line has always been',
	file_get_contents( $logFile ) === "<?php http_response_code(403); exit; return '". base64_encode( implode( "\n", $expected ) ). "';\n" );
check( '...through Logs\\Admin::record() as well', ( static function() use ( &$appData, $logFile, $clear, $linesOf ): bool {
	$clear();
	\Nino\Modules\Logs\Admin::record( $appData, 'adm@example.com', 'Login' );
	return $linesOf() === [ date( 'Y-m-d H:i' ). '  adm@example.com  Login' ];
} )() );

echo "\nThe call of a feature\n";

$visitor = $appData;
unset( $visitor['./nino/auth/current'] );
// The manifests are read once for a request, and what the fixtures have that does not validate is said then
\Nino\Features::all( $visitor );
ninoWarnings();

$clear();
\Nino\Activity::record( $appData, 'sample', 'Signed the document 42' );
\Nino\Activity::record( $appData, 'helper', 'Signed the document 43' );
check( 'with an account signed in the actor is its mail, whatever the key of the feature says', $linesOf() === [ date( 'Y-m-d H:i' ). '  adm@example.com  Signed the document 42', date( 'Y-m-d H:i' ). '  adm@example.com  Signed the document 43' ] );

$clear();
$answers = [ \Nino\Activity::record( $visitor, 'sample', 'Signed the document 44' ), \Nino\Activity::record( $visitor, 'helper', 'Signed the document 45' ) ];
check( 'with nobody signed in it is the fixed feature:<key>, which the kernel checked, and the call answers true', $answers === [ true, true ] && $linesOf() === [ date( 'Y-m-d H:i' ). '  feature:sample  Signed the document 44', date( 'Y-m-d H:i' ). '  feature:helper  Signed the document 45' ] );

$clear();
$bad = [];

foreach( [ 'old', 'nope', '', 'sam/ple', '../sample', 'sam ple', 'sample ', "sample\n", 'Sample', 'SAMPLE', 'sample.php', "sample\0", 'feature:sample', 'adm@example.com' ] as $key ) {

	$answer = null;
	$errors = ninoErrors( function() use ( &$visitor, $key, &$answer ): void { $answer = \Nino\Activity::record( $visitor, $key, 'Signed the document 46' ); } );

	if( $answer !== false || count( $errors ) !== 1 || $errors[0][0] !== E_USER_WARNING )
		$bad[] = json_encode( $key );
}

check( 'a feature that is off, an unknown key, an empty one, one with a slash, dots, a blank, a line break or a NUL, or in another case: no line, false, one warning for the programmer', $bad === [] && $linesOf() === [] );

$visitor['/nino/modules'] = [ '\\Nino\\Modules\\Helper' ];
unset( $visitor['./nino/features/all'] );
$answer = \Nino\Activity::record( $visitor, 'sample', 'Signed the document 47' );
ninoWarnings();
check( 'a feature that is on is asked for each time: switched off, it writes no line', $answer === false && $linesOf() === [] );

$off = $appData;
$off['/nino/admin/logs'] = false;
$clear();
if( is_dir( $logDir ) === true )
	\Nino\Filesystem::removeDir( $logDir );
$answers = [ \Nino\Activity::record( $off, 'sample', 'Signed the document 48' ), \Nino\Activity::write( $off, 'adm@example.com', 'Login' ) ];
\Nino\Admin\Admin::record( $off, 'adm@example.com', 'Login' );
\Nino\Modules\Logs\Admin::record( $off, 'adm@example.com', 'Login' );
check( 'with the log switched off (/nino/admin/logs) every way answers false and writes no file, not even its directory', $answers === [ false, false ] && is_dir( $logDir ) === false );

echo "\nOutside the workbench\n";

$run = static function( string $tree, string $mode, string ...$arguments ) use ( $sandbox ): array {
	$answer = json_decode( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( __FILE__ ). ' '. $mode. ' '. escapeshellarg( $tree ). ' '. escapeshellarg( $sandbox ). ' '. implode( ' ', array_map( 'escapeshellarg', $arguments ) ). ' 2>/dev/null' ), true );
	return is_array( $answer ) === true ? $answer : [ 'answer' => null ];
};

$clear();
$outside = $run( $root, 'outside', 'sample', '0' );
check( 'a process that loads _nino/Nino.php only has no workbench before the call and none after it: the call writes its line', ( $outside['before'] ?? null ) === false && ( $outside['after'] ?? null ) === false && ( $outside['answer'] ?? null ) === true && ( $outside['raised'] ?? [ 'x' ] ) === [] );
check( '...with the fixed actor of a request nobody signed in', $linesOf() === [ date( 'Y-m-d H:i' ). '  feature:sample  Sent the document 42 to the client 7' ] );

$outside = $run( $root, 'outside', 'sample', '1' );
check( '...and with the mail of the account that is signed in there', ( $outside['answer'] ?? null ) === true && ( $outside['after'] ?? null ) === false && ( $linesOf()[1] ?? '' ) === date( 'Y-m-d H:i' ). '  adm@example.com  Sent the document 42 to the client 7' );

$outside = $run( $root, 'outside', 'old', '0' );
check( '...a feature that is off writes nothing there either', ( $outside['answer'] ?? null ) === false && count( $linesOf() ) === 2 );

[ $status, $body ] = ninoCall( $appData, [ \Nino\Admin\Admin::class, 'handlePost' ], [], 'logs/list' );
$listed = (array) ( $body['lines'] ?? [] );
$byFeature = array_search( date( 'Y-m-d H:i' ). '  feature:sample  Sent the document 42 to the client 7', $listed, true );
$byAccount = array_search( date( 'Y-m-d H:i' ). '  adm@example.com  Sent the document 42 to the client 7', $listed, true );
check( 'the workbench\'s logs/list, in a process of its own, reads the lines the other wrote: both, the one written last first, each with its key',
	$status === 200 && $byFeature !== false && $byAccount !== false && $byAccount < $byFeature && count( (array) ( $body['keys'] ?? [] ) ) === count( $listed ) && preg_match( '/^\d{4}-\d{2}-\d{2}\.\d+$/', (string) ( $body['keys'][$byFeature] ?? '' ) ) === 1 );

// A tree whose workbench is delivered without the Logs module: the kernel, and the directory of the modules with nothing in it
$bare = $sandbox. '/bare';
mkdir( $bare. '/_admin/Nino/Modules', 0755, true );
exec( 'cp -a '. escapeshellarg( $root. '/_nino' ). ' '. escapeshellarg( $bare. '/_nino' ) );
$clear();
if( is_dir( $logDir ) === true )
	\Nino\Filesystem::removeDir( $logDir );
$outside = $run( $bare, 'outside', 'sample', '0' );
$written = $run( $bare, 'outside', '@write', '0' );
check( 'where the Logs module is not delivered no line is written, the call answers false and nothing is raised that ends a request', ( $outside['answer'] ?? null ) === false && is_dir( $logDir ) === false
	&& ( $outside['raised'] ?? [ 'x' ] ) === [] );
check( '...write(), the writer of the workbench\'s own lines, takes none there either: false, no file, no directory, nothing raised', ( $written['answer'] ?? null ) === false && is_dir( $logDir ) === false && ( $written['raised'] ?? [ 'x' ] ) === [] );

// A tree with no _admin at all
$bare2 = $sandbox. '/noadmin';
mkdir( $bare2, 0755, true );
exec( 'cp -a '. escapeshellarg( $root. '/_nino' ). ' '. escapeshellarg( $bare2. '/_nino' ) );
$outside = $run( $bare2, 'outside', 'sample', '0' );
$written = $run( $bare2, 'outside', '@write', '0' );
check( 'in a tree with no _admin directory both calls answer false, write no file and no directory, and raise nothing', ( $outside['answer'] ?? null ) === false && ( $written['answer'] ?? null ) === false && is_dir( $logDir ) === false
	&& ( $outside['raised'] ?? [ 'x' ] ) === [] && ( $written['raised'] ?? [ 'x' ] ) === [] );
$written = $run( $root, 'outside', '@write', '0' );
check( '...and in the full tree write() writes its line', ( $written['answer'] ?? null ) === true && count( $linesOf() ) === 1 );

echo "\nTwo processes at once\n";

$clear();
$workers = [];

foreach( [ 'a', 'b' ] as $tag )
	$workers[$tag] = proc_open( [ PHP_BINARY, __FILE__, 'append', $root, $sandbox, $tag, '30' ], [ 1 => [ 'file', '/dev/null', 'w' ], 2 => [ 'file', '/dev/null', 'w' ] ], $pipes );

foreach( $workers as $worker )
	if( is_resource( $worker ) === true )
		proc_close( $worker );

$lines = $linesOf();
$have	 = [];
foreach( $lines as $line )
	if( preg_match( '/^\S+ \S+  feature:sample  Worker (\w) line (\d+)$/', $line, $match ) === 1 )
		$have[$match[1]][] = (int) $match[2];

sort( $have['a'] );
sort( $have['b'] );
check( 'two processes that append 30 lines each lose none and write none twice', count( $lines ) === 60 && $have['a'] === range( 1, 30 ) && $have['b'] === range( 1, 30 ) );
check( '...and each process\'s lines are in the order it wrote them', array_values( array_filter( array_map( static fn( string $line ): ?int => preg_match( '/Worker a line (\d+)$/', $line, $m ) === 1 ? (int) $m[1] : null, $lines ), static fn( ?int $n ): bool => $n !== null ) ) === range( 1, 30 ) );

echo "\n\\Nino\\Http::string() and Admin::postString()\n";

$posted = [ 'text' => 'Hello', 'padded' => '  Hello  ', 'empty' => '', 'int' => 42, 'float' => 1.5, 'zero' => 0, 'array' => [ 'a' ], 'yes' => true, 'no' => false, 'null' => null ];
$same		= true;

foreach( array_merge( array_keys( $posted ), [ 'absent' ] ) as $key )
	foreach( [ '', 'dflt' ] as $default ) {
		$errors = ninoErrors( function() use ( $posted, $key, $default, &$same ): void {
			if( \Nino\Http::string( $posted, $key, $default ) !== \Nino\Admin\Admin::postString( $posted, $key, $default ) )
				$same = false;
		} );
		if( $errors !== [] )
			$same = false;
	}

check( 'string() answers what postString() answers for a string, a missing key, an array, an int, a float, null and a bool, with and without a default, and raises nothing', $same === true );
check( '...a string as it is - not trimmed -, a whole or a decimal number as its text, anything else the default', \Nino\Http::string( $posted, 'padded' ) === '  Hello  ' && \Nino\Http::string( $posted, 'int' ) === '42' && \Nino\Http::string( $posted, 'float' ) === '1.5' && \Nino\Http::string( $posted, 'zero' ) === '0'
	&& \Nino\Http::string( $posted, 'array', 'dflt' ) === 'dflt' && \Nino\Http::string( $posted, 'yes', 'dflt' ) === 'dflt' && \Nino\Http::string( $posted, 'null', 'dflt' ) === 'dflt' && \Nino\Http::string( $posted, 'absent' ) === '' );

ninoDone( $appData );
