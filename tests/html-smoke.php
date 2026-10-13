<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	html-smoke.php			Dependency-free smoke test for \Nino\Html: resolveTextfill(), the
 *											formats of a field - sanitizeHtml() with its profiles,
 *											detectFormat(), fieldFormat(), fieldValue() - as the element
 *											shortcodes apply them, and the [json] shortcode. Runs against an
 *											isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/html-smoke.php
 */

require __DIR__. '/harness.php';

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'html' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";


// --- Html::resolveTextfill ---------------------------------------------------

echo "Html::resolveTextfill - what [[key]] would put into a page, nested fills resolved\n";

\Nino\Html::addFills( $appData, [
	'/seed/resolve/plain' 	=> 'Hello',
	'/seed/resolve/nested' 	=> 'Dear [[/seed/resolve/plain]], see [[/seed/resolve/deeper]]',
	'/seed/resolve/deeper' 	=> '[[/seed/resolve/plain]] again',
	'/seed/resolve/empty' 	=> '',
	'/seed/resolve/loop' 		=> '[[/seed/resolve/loop]]',
	'/seed/resolve/code' 		=> 'a [json /seed/resolve/plain] b',
], 'de_DE' );
\Nino\Html::addFills( $appData, [ '/seed/resolve/runtime' => 'runtime value' ], '*' );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( 'a value comes as it is', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/plain' ) === 'Hello' );
check( '...with the fills inside it resolved, the ones inside those too', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/nested' ) === 'Dear Hello, see Hello again' );
check( '...a runtime fill as well', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/runtime' ) === 'runtime value' );
check( 'a key with no value is null, a key with an empty one is the empty string', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/nope' ) === null && \Nino\Html::resolveTextfill( $appData, '/seed/resolve/empty' ) === '' );
check( 'a value that names itself ends after ten passes', str_contains( (string) \Nino\Html::resolveTextfill( $appData, '/seed/resolve/loop' ), '[[/seed/resolve/loop]]' ) === true );
check( 'no shortcode is run, and nothing is escaped', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/code' ) === 'a [json /seed/resolve/plain] b' );
check( 'the language is the current one', \Nino\Html::resolveTextfill( $appData, '/seed/resolve/plain' ) === 'Hello' && ( \Nino\Locales::setCurrentLocale( $appData, 'en_US' ) === 'en_US' ) && \Nino\Html::resolveTextfill( $appData, '/seed/resolve/plain' ) === null );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( '[json] gives the strings it always gave, from the same rule', \Nino\Html::doJsonShortcode( $appData, [ '/seed/resolve/nested' ] ) === '"Dear Hello, see Hello again"'
	&& \Nino\Html::doJsonShortcode( $appData, [ '/seed/resolve/nope' ] ) === '""' && \Nino\Html::doJsonShortcode( $appData, [] ) === '""' );

// Text lives in /text, whatever '/nino/locales/textfiles' says. The key stays
// in AppData::DEFAULTS for catalogue features that read it, and a config.php
// may hold another value - the site must not render words the Text panel
// does not edit
$textDirApp = $appData;
$textDirApp['./nino/filesystem/path']				= $sandbox. '/textdir';
$textDirApp['./nino/filesystem/contentpath']	= $sandbox. '/textdir/private';
$textDirApp['./nino/filesystem/cache']			= [];
$textDirApp['/nino/locales/textfiles']			= '/text2';
\Nino\Filesystem::putFileContent( $textDirApp, '/text/de_DE.php', [ '[[/seed/textdir/probe]]' => 'from /text' ] );
\Nino\Filesystem::putFileContent( $textDirApp, '/text2/de_DE.php', [ '[[/seed/textdir/probe]]' => 'from /text2' ] );
check( 'a page reads its words from /text, whatever /nino/locales/textfiles says', ( \Nino\Html::getFills( $textDirApp )['[[/seed/textdir/probe]]'] ?? null ) === 'from /text' );

$textDirReaders = [];
foreach( [ '/../_nino', '/../_admin' ] as $textDirRoot )
	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( __DIR__. $textDirRoot, \FilesystemIterator::SKIP_DOTS ) ) as $textDirFile )
		if( $textDirFile->getExtension() === 'php' && str_contains( (string) file_get_contents( $textDirFile->getPathname() ), "'/nino/locales/textfiles'" ) === true )
			$textDirReaders[] = basename( $textDirFile->getPathname() );
check( '...and nothing in the kernel or the workbench reads the key but its own default', $textDirReaders === [ 'AppData.php' ] );

echo "\n";

// --- Html formats: line breaks, paragraphs and lists ---------------------
//
// sanitizeHtml() has three profiles. 'inline' is what it always was - the
// five tags - plus one space where an unwrapped block kept two runs apart;
// 'lines' adds <br>, 'blocks' paragraphs and lists. A field names which it
// keeps (fieldFormat()), a text key has it stored or read from its value
// (detectFormat()), and fieldValue() is the one rule every renderer applies.

echo "Html - formats, fieldValue() and the element shortcodes\n";

// [element], [elements] and [elementvalues] are the Elements module's own,
// registered by its init() - which the sandbox never runs
\Nino\Modules\Elements::init( $appData );

check( 'Html::FORMATS lists the four formats, narrowest first', \Nino\Html::FORMATS === [ 'plain', 'inline', 'lines', 'blocks' ] );

// What inline already did, byte for byte - the vectors are the ones the Text,
// Elements and Translations tests pin on their own
foreach( [
	'<strong><em>Bold Italic</em></strong> <code>const x = 1;</code> <script>alert(1)</script> <a href="javascript:alert(2)">bad</a> <a href="/ok">good</a> <img src=x onerror=alert(3)>',
	'pasted <strong>one</strong></div> and the rest',
	'a</div>b <em>c</em>',
	'before <div>inside</div> after',
	'<strong>Fett</strong> und [template /templates/mail-owner]',
	'plain text',
] as $vector )
	check( 'inline keeps '. substr( $vector, 0, 40 ). ' as it was', \Nino\Html::sanitizeHtml( $vector, 'inline' ) === \Nino\Html::sanitizeHtml( $vector ) );

check( 'a format that is not one is read as inline, and plain is not a html format', \Nino\Html::sanitizeHtml( 'a<br>b', 'nonsense' ) === 'a b' && \Nino\Html::sanitizeHtml( 'a<br>b', 'plain' ) === 'a b' );

// The glue: an unwrapped block used to join its neighbours ('Grill.Zweiter')
check( 'inline leaves a space where a paragraph ended', \Nino\Html::sanitizeHtml( '<p>Grill.</p><p>Zweiter Absatz</p>' ) === 'Grill. Zweiter Absatz' );
check( '...a break, a list and a div the same', \Nino\Html::sanitizeHtml( 'Danke.<br>Gruss' ) === 'Danke. Gruss'
	&& \Nino\Html::sanitizeHtml( '<ul><li>a</li><li>b</li></ul>' ) === 'a b'
	&& \Nino\Html::sanitizeHtml( '<div>x</div><div>y</div>' ) === 'x y' );
check( '...and none where a space is there already, or at an end', \Nino\Html::sanitizeHtml( 'a <p>b</p>' ) === 'a b' && \Nino\Html::sanitizeHtml( '<p>a</p> b' ) === 'a b' && \Nino\Html::sanitizeHtml( '<p>a</p>' ) === 'a' );
check( '...Posts\' pinned heading vector keeps its words apart', \Nino\Html::sanitizeHtml( '<h2>Not a heading</h2>Second.' ) === 'Not a heading Second.' );

// lines
check( 'lines: a newline of the text is a <br>', \Nino\Html::sanitizeHtml( "Street 1\n12345 City", 'lines' ) === 'Street 1<br>12345 City' );
check( 'lines: a newline straight after a <br> is source formatting, not a second break', \Nino\Html::sanitizeHtml( "a<br>\nb", 'lines' ) === 'a<br>b' );
check( 'lines: a block that ends is a break, a break at either end is not a line', \Nino\Html::sanitizeHtml( '<p>x</p><p>y</p>', 'lines' ) === 'x<br>y' && \Nino\Html::sanitizeHtml( '<br>a<br>', 'lines' ) === 'a' );
check( 'lines: the inline tags stay, with a break inside one, and everything else goes', \Nino\Html::sanitizeHtml( '<strong>a<br>b</strong><script>x</script><u>c</u>', 'lines' ) === '<strong>a<br>b</strong>c' );
check( 'lines: blank lines the text had are kept', \Nino\Html::sanitizeHtml( "a\n\nb", 'lines' ) === 'a<br><br>b' );

// blocks
$blocks = [
	'<p>Grill.</p><p>Zweiter Absatz</p><ul><li>a</li><li>b</li></ul>'	=> '<p>Grill.</p><p>Zweiter Absatz</p><ul><li>a</li><li>b</li></ul>',
	"Grill.\n\nZweiter Absatz\nmit Umbruch"															=> '<p>Grill.</p><p>Zweiter Absatz<br>mit Umbruch</p>',
	'loose <strong>x</strong><p>para</p>tail'														=> '<p>loose <strong>x</strong></p><p>para</p><p>tail</p>',
	'<li>stray</li><ul>junk<li>ok</li><p>bad</p></ul>'									=> '<p>stray</p><ul><li>junk</li><li>ok</li><li>bad</li></ul>',
	'<p><ul><li>nested</li></ul></p>'																		=> '<ul><li>nested</li></ul>',
	'<h2>Title</h2><div>Box</div><blockquote>Said</blockquote>'					=> '<p>Title</p><p>Box</p><p>Said</p>',
	'<strong><p>in strong</p></strong>'																	=> '<p><strong>in strong</strong></p>',
	'<p><strong><em>deep</em></strong> <a href="javascript:x">j</a> <a href="/ok" onclick="x()">k</a></p>'	=> '<p><strong>deep</strong> j <a href="/ok">k</a></p>',
	"<p>a</p>\n<p>b</p>"																								=> '<p>a</p><p>b</p>',
	'<p><br></p><p>x</p><p></p><ul></ul><ol><li><br></li></ol>'				=> '<p>x</p>',
	'<ol><li><br></li><li>one<br>two</li></ol>'													=> '<ol><li>one<br>two</li></ol>',
	'<p>[template /templates/mail-owner]</p><script>alert(1)</script>'	=> '<p>[template /templates/mail-owner]</p>',
	'<table><tr><td>cell</td></tr></table>'															=> '<p>cell</p>',
	"<strong>a\n\nb</strong>"																			=> '<p><strong>a<br><br>b</strong></p>',
	"<a href=\"/x\">a\n\nb</a>"																		=> '<p><a href="/x">a<br><br>b</a></p>',
	'<li>a</li><li>b</li>'																							=> '<p>a</p><p>b</p>',
	'text<li>item</li>'																									=> '<p>text</p><p>item</p>',
	'a<section>b</section>c'																						=> '<p>a<br>b<br>c</p>',
	"<a href=\"/x\ny\">z</a>"																			=> '<p><a href="/xy">z</a></p>',
];
foreach( $blocks as $in => $out )
	check( 'blocks: '. substr( str_replace( "\n", '\n', $in ), 0, 60 ), \Nino\Html::sanitizeHtml( $in, 'blocks' ) === $out );

// What comes out of a profile is stable: sanitizing it again changes nothing
$unstable = [];
foreach( array_merge( array_keys( $blocks ), [ "Street 1\n12345 City", 'Thank you.<br>Kind regards,', '<p>Grill.</p><p>Zweiter Absatz</p>', 'a</div>b <em>c</em>', '<strong>a<br>b</strong>' ] ) as $vector )
	foreach( [ 'inline', 'lines', 'blocks' ] as $format ) {
		$once = \Nino\Html::sanitizeHtml( $vector, $format );
		if( \Nino\Html::sanitizeHtml( $once, $format ) !== $once )
			$unstable[] = $format. ': '. $vector;
	}
check( 'every profile is idempotent'. ( $unstable === [] ? '' : ' - '. implode( ' | ', $unstable ) ), $unstable === [] );
check( 'a bracket survives the sanitizer - the callers swap it - and nothing becomes a tag', \Nino\Html::sanitizeHtml( '<p>[[/a/key]] [x]</p>', 'blocks' ) === '<p>[[/a/key]] [x]</p>' );

// detectFormat(): the widest thing a value holds
check( 'detectFormat reads plain text, the inline tags, a break and paragraphs or lists',
	\Nino\Html::detectFormat( 'just words' ) === 'plain'
	&& \Nino\Html::detectFormat( 'a <strong>b</strong>' ) === 'inline'
	&& \Nino\Html::detectFormat( 'a<br>b' ) === 'lines'
	&& \Nino\Html::detectFormat( 'a<br />b' ) === 'lines'
	&& \Nino\Html::detectFormat( '<p>a</p>' ) === 'blocks'
	&& \Nino\Html::detectFormat( '<ul><li>a</li></ul>' ) === 'blocks' );
check( '...and the widest wins', \Nino\Html::detectFormat( '<p><strong>a</strong><br>b</p>' ) === 'blocks' && \Nino\Html::detectFormat( '<strong>a</strong><br>b' ) === 'lines' );
check( 'containsHtml still means the inline tags only', \Nino\Html::containsHtml( 'a<br>b' ) === false && \Nino\Html::containsHtml( '<p>a</p>' ) === false && \Nino\Html::containsHtml( '<em>a</em>' ) === true );

// fieldFormat(): what a model says
check( 'fieldFormat reads blocks, inline, breaks and plain from a field',
	\Nino\Html::fieldFormat( [ 'type' => 'string', 'html' => true, 'blocks' => true ] ) === 'blocks'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string', 'html' => true ] ) === 'inline'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string', 'breaks' => true ] ) === 'breaks'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string' ] ) === 'plain'
	&& \Nino\Html::fieldFormat( [] ) === 'plain' );
check( '...and ignores a flag on a field it does not fit - a hand-written model is not trusted',
	\Nino\Html::fieldFormat( [ 'type' => 'string', 'blocks' => true ] ) === 'plain'
	&& \Nino\Html::fieldFormat( [ 'type' => 'string', 'html' => true, 'breaks' => true ] ) === 'inline'
	&& \Nino\Html::fieldFormat( [ 'type' => 'integer', 'breaks' => true ] ) === 'plain' );

// fieldValue(): the rule every renderer of a field applies
check( 'fieldValue keeps paragraphs and lists of a blocks field and swaps a bracket', \Nino\Html::fieldValue( '<p>One [x]</p><ul><li>a</li></ul><script>1</script>', [ 'type' => 'string', 'html' => true, 'blocks' => true ] ) === '<p>One &#91;x]</p><ul><li>a</li></ul>' );
check( 'fieldValue keeps only the inline tags of an html field', \Nino\Html::fieldValue( '<p>One</p><strong>two</strong>', [ 'type' => 'string', 'html' => true ] ) === 'One <strong>two</strong>' );
check( 'fieldValue turns the newlines of a breaks field into <br>, after escaping, and swaps a bracket', \Nino\Html::fieldValue( "a\r\nb <i>\"c\"</i> [[x]]", [ 'type' => 'string', 'breaks' => true ] ) === "a<br>\r\nb &lt;i&gt;&quot;c&quot;&lt;/i&gt; &#91;&#91;x]]" );
check( 'fieldValue escapes everything else, whatever its type', \Nino\Html::fieldValue( '<b>[x]</b>', [] ) === '&lt;b&gt;&#91;x]&lt;/b&gt;' && \Nino\Html::fieldValue( 12, [ 'type' => 'integer' ] ) === '12' );

// breaksToNewlines(): what strip_tags() alone would glue together
check( 'breaksToNewlines makes a line of every break and every end of a block',
	\Nino\Html::breaksToNewlines( 'Amtsgericht<br>Musterstadt' ) === "Amtsgericht\nMusterstadt"
	&& \Nino\Html::breaksToNewlines( '<p>a</p><p>b</p>' ) === "<p>a\n<p>b"
	&& \Nino\Html::breaksToNewlines( '<ul><li>a</li><li>b</li></ul>' ) === "<ul><li>a\n<li>b"
	&& \Nino\Html::breaksToNewlines( 'one<br />two<BR>three' ) === "one\ntwo\nthree"
	&& \Nino\Html::breaksToNewlines( "kept\n" ) === "kept\n" );

// [element] and [elements] hand each field to fieldValue(); [elementvalues] lists values, not fields
\Nino\Filesystem::putFileContent( $appData, '/elements/formats.php', [
	'title'	=> 'Formats',
	'model'	=> [
		'body'	=> [ 'type' => 'string', 'html' => true, 'blocks' => true ],
		'rich'	=> [ 'type' => 'string', 'html' => true ],
		'note'	=> [ 'type' => 'string', 'breaks' => true ],
		'plain'	=> [ 'type' => 'string' ],
	],
	'*'			=> [ 'one' => [
		'body'	=> '<p>One [x]</p><ul><li>a</li></ul><script>1</script>',
		'rich'	=> '<p>One</p><strong>two</strong>',
		'note'	=> "Line 1\nLine <2> [y]",
		'plain'	=> "<p>raw</p>\nnext",
	] ],
] );
$formatsOne = \Nino\Html::renderHtml( $appData, '[element /formats/one][[body]]|[[rich]]|[[note]]|[[plain]][/element]' );
check( '[element] renders a blocks field with its paragraphs and list, a rich one inline',
	str_starts_with( $formatsOne, '<p>One &#91;x]</p><ul><li>a</li></ul>|One <strong>two</strong>|' ) === true );
check( '...a breaks field with <br> and escaped text, a plain field escaped whole',
	str_ends_with( $formatsOne, "|Line 1<br>\nLine &lt;2&gt; &#91;y]|&lt;p&gt;raw&lt;/p&gt;\nnext" ) === true );
check( '[elements] does the same', \Nino\Html::renderHtml( $appData, '[elements /formats][[body]]|[[note]][/elements]' ) === "<p>One &#91;x]</p><ul><li>a</li></ul>|Line 1<br>\nLine &lt;2&gt; &#91;y]" );
check( '[elementvalues] lists plain values: a breaks field\'s are escaped, not turned into <br>', \Nino\Html::renderHtml( $appData, '[elementvalues /formats key="note"][[.value]];[/elementvalues]' ) === "Line 1\nLine &lt;2&gt; &#91;y];" );

echo "\n";


// --- [json] textfills into a hand-written json document -----------------
//
// html-header.tpl's schema.org block writes json by hand and used to drop
// raw fill values into it. '/project/company/contact/address' is multi-line by design, and a
// raw newline in a json string is not valid json, so that block failed to
// parse on every page of every install.

\Nino\Html::init( $appData );
\Nino\Html::addFills( $appData, [
	'/test/json/multiline'	=> "Musterstraße 1\n10115 Berlin",
	'/test/json/quotes'			=> 'We say "hello" & build 5 < 10 sites',
	'/test/json/nested'			=> 'Contact [[/test/json/quotes]]',
], '*' );

check( '[json] wraps a value in its own quotes', \Nino\Html::renderHtml( $appData, '[json /test/json/multiline]' ) === '"Musterstraße 1\n10115 Berlin"' );
check( '[json] renders an unknown key as an empty string literal', \Nino\Html::renderHtml( $appData, '[json /test/json/nope]' ) === '""' );
check( '[json] resolves a nested fill before encoding', str_contains( \Nino\Html::renderHtml( $appData, '[json /test/json/nested]' ), 'hello' ) === true );

$jsonDocument = \Nino\Html::renderHtml( $appData, '{ "streetAddress": [json /test/json/multiline], "description": [json /test/json/quotes] }' );
$jsonDecoded  = json_decode( $jsonDocument, true );
check( 'a document built this way parses as json', is_array( $jsonDecoded ) === true );
check( '...with the multi-line value intact', ( $jsonDecoded['streetAddress'] ?? '' ) === "Musterstraße 1\n10115 Berlin" );
check( '...and the quotes/ampersand round-tripped', ( $jsonDecoded['description'] ?? '' ) === 'We say "hello" & build 5 < 10 sites' );
check( 'nothing that could close the surrounding <script> survives encoding', str_contains( $jsonDocument, '<' ) === false && str_contains( $jsonDocument, '&' ) === false );

// The same document with the old raw-fill approach, as a regression guard
check( 'the raw-fill form this replaces really is invalid json', json_decode( \Nino\Html::renderHtml( $appData, '{ "streetAddress": "[[/test/json/multiline]]" }' ), true ) === null );

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
