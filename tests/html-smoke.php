<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	html-smoke.php			Dependency-free smoke test for \Nino\Html: resolveTextfill(), the
 *											formats of a field - sanitizeHtml() with its profiles,
 *											detectFormat(), fieldFormat(), fieldValue() - as the element
 *											shortcodes apply them, the [json] shortcode, and the fills of
 *											another language, the address of the site and a fill taken back,
 *											the fallback of the fills to another language and the helpers
 *											of \Nino\Locales it reads.
 *											Runs against an isolated sandbox directory, never touches the real
 *											project data.
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

// --- Html::getFills of a language, absoluteUrl and removeFill ----------------

echo "Html::getFills, absoluteUrl and removeFill - the fills of another language, the address of the site, a fill taken back\n";

// A sandbox of its own: the text files below are this section's, and no other check reads them
$fills = ninoSandbox( 'html-fills' );
\Nino\Filesystem::putFileContent( $fills, '/text/global.php', [ '[[/t/global]]' => 'in every language', '[[/t/both]]' => 'global text' ] );
\Nino\Filesystem::putFileContent( $fills, '/text/de_DE.php', [ '[[/t/both]]' => 'de text', '[[/t/de]]' => 'only de' ] );
\Nino\Filesystem::putFileContent( $fills, '/text/en_US.php', [ '[[/t/both]]' => 'en text', '[[/t/en]]' => 'only en' ] );
// ...and a file with a star in its name, which is no language: * is no pattern, and no file of it is read
file_put_contents( \Nino\Filesystem::path( $fills, '/text' ). '/*.php', '<?php return [ \'[[/t/starfile]]\' => \'from a file named star\' ];' );
\Nino\Html::addFills( $fills, [ '/t/both' => 'de runtime' ], 'de_DE' );
\Nino\Html::addFills( $fills, [ '/t/en' => 'en runtime', '/t/star' => 'en wants this' ], 'en_US' );
\Nino\Html::addFills( $fills, [ '/t/star' => 'star' ], '*' );
\Nino\Locales::setCurrentLocale( $fills, 'de_DE' );

$fillsOfCurrent = \Nino\Html::getFills( $fills );
check( 'getFills() is the current language\'s: the global text, the language\'s own, its runtime fills and last those of *, as it always was', $fillsOfCurrent['[[/t/global]]'] === 'in every language'
	&& $fillsOfCurrent['[[/t/both]]'] === 'de runtime' && $fillsOfCurrent['[[/t/de]]'] === 'only de' && $fillsOfCurrent['[[/t/star]]'] === 'star' );
check( '...a text it has none of is the other language\'s text, not its runtime fill', $fillsOfCurrent['[[/t/en]]'] === 'only en' );
check( '...and so it is for null and for \'\'', \Nino\Html::getFills( $fills, null ) === $fillsOfCurrent && \Nino\Html::getFills( $fills, '' ) === $fillsOfCurrent && \Nino\Html::getFills( $fills, 'de_DE' ) === $fillsOfCurrent );
$fillsOfOther = \Nino\Html::getFills( $fills, 'en_US' );
check( 'the fills of another language of the site are its own text and its own runtime fills, merged the same way, while the visitor stays in theirs', $fillsOfOther['[[/t/global]]'] === 'in every language'
	&& $fillsOfOther['[[/t/both]]'] === 'en text' && $fillsOfOther['[[/t/en]]'] === 'en runtime' && $fillsOfOther['[[/t/de]]'] === 'only de' && \Nino\Locales::getCurrentLocale( $fills ) === 'de_DE' );
check( '...and the fills that belong to no language win there too', $fillsOfOther['[[/t/star]]'] === 'star' );
$fillsOfNone = \Nino\Html::getFills( $fills, '*' );
check( 'asked for *, it is the global text and the fills that belong to no language - no language\'s own', $fillsOfNone['[[/t/global]]'] === 'in every language' && $fillsOfNone['[[/t/star]]'] === 'star'
	&& isset( $fillsOfNone['[[/t/de]]'] ) === false && isset( $fillsOfNone['[[/t/en]]'] ) === false );
check( '...and no file is read for it, not even one that has a star in its name', is_file( \Nino\Filesystem::path( $fills, '/text' ). '/*.php' ) === true && isset( $fillsOfNone['[[/t/starfile]]'] ) === false );
check( 'a language the site does not have has no fills - none from a file of that name either', \Nino\Html::getFills( $fills, 'fr_FR' ) === [] && \Nino\Html::getFills( $fills, '../private/config' ) === [] && ninoWarnings() === [] );

\Nino\Html::addFills( $fills, [ '/project/website/general/url' => 'example.com/shop/' ], '*' );
check( 'absoluteUrl() is https:// and the address of the website, the slash at the end of the value gone, and the path as it is given', \Nino\Html::absoluteUrl( $fills, '/page' ) === 'https://example.com/shop/page'
	&& \Nino\Html::absoluteUrl( $fills ) === 'https://example.com/shop' && \Nino\Html::absoluteUrl( $fills, '/' ) === 'https://example.com/shop/' && \Nino\Html::absoluteUrl( $fills, '/a?b=1#c' ) === 'https://example.com/shop/a?b=1#c' );
\Nino\Html::addFills( $fills, [ '/test/host' => 'example.org', '/project/website/general/url' => '/[[/test/host]]/shop//' ], '*' );
check( '...the fills inside the value resolved, a slash on either side of it gone', \Nino\Html::absoluteUrl( $fills, '/page' ) === 'https://example.org/shop/page' );
\Nino\Html::addFills( $fills, [ '/project/website/general/url' => '' ], '*' );
$fillsNoUrl = \Nino\Html::absoluteUrl( $fills, '/page' );
\Nino\Html::addFills( $fills, [ '/project/website/general/url' => '/' ], '*' );
$fillsSlashUrl = \Nino\Html::absoluteUrl( $fills, '/page' );
\Nino\Html::removeFill( $fills, '/project/website/general/url' );
check( 'a website without an address has no absolute one, whether its value is empty, a slash or not there at all', $fillsNoUrl === '' && $fillsSlashUrl === '' && \Nino\Html::absoluteUrl( $fills, '/page' ) === '' && \Nino\Html::absoluteUrl( $fills ) === '' );

\Nino\Html::addFills( $fills, [ '/t/rm/star' => 'star value', '/t/rm/bracketed' => 'bracketed value' ], '*' );
\Nino\Html::addFills( $fills, [ '/t/rm/de' => 'de value', '/t/rm/current' => 'current value' ], 'de_DE' );
\Nino\Html::addFills( $fills, [ '/t/rm/de' => 'en value' ], 'en_US' );
\Nino\Html::addFills( $fills, [ '/t/rm/both' => 'in every language' ], '*' );
\Nino\Html::addFills( $fills, [ '/t/rm/both' => 'de value' ], 'de_DE' );
check( 'the fills to take back render', \Nino\Html::renderHtml( $fills, '[[/t/rm/star]]|[[/t/rm/bracketed]]|[[/t/rm/de]]|[[/t/rm/current]]' ) === 'star value|bracketed value|de value|current value' );
\Nino\Html::removeFill( $fills, '/t/rm/star' );
\Nino\Html::removeFill( $fills, '[[/t/rm/bracketed]]' );
check( 'removeFill() takes a fill added for * out of the render, spelled with its brackets or without', \Nino\Html::renderHtml( $fills, '[[/t/rm/star]]|[[/t/rm/bracketed]]' ) === '[[/t/rm/star]]|[[/t/rm/bracketed]]'
	&& isset( \Nino\Html::getFills( $fills )['[[/t/rm/star]]'] ) === false );
\Nino\Html::removeFill( $fills, '/t/rm/de' );
check( '...a fill added for a language is not taken back by *, but by that language', \Nino\Html::renderHtml( $fills, '[[/t/rm/de]]' ) === 'de value' );
\Nino\Html::removeFill( $fills, '/t/rm/de', 'de_DE' );
check( '...and takes only that language\'s: the other one keeps its own', \Nino\Html::renderHtml( $fills, '[[/t/rm/de]]' ) === '[[/t/rm/de]]' && \Nino\Html::getFills( $fills, 'en_US' )['[[/t/rm/de]]'] === 'en value' );
\Nino\Html::removeFill( $fills, '/t/rm/both', 'de_DE' );
check( '...the same key under * stays when the language\'s is taken back, and the other way round', \Nino\Html::getFills( $fills, '*' )['[[/t/rm/both]]'] === 'in every language' && \Nino\Html::getFills( $fills, 'de_DE' )['[[/t/rm/both]]'] === 'in every language'
	&& isset( $fills['./nino/html/fills']['de_DE']['[[/t/rm/both]]'] ) === false );
\Nino\Html::addFills( $fills, [ '/t/rm/both' => 'de value' ], 'de_DE' );
\Nino\Html::removeFill( $fills, '/t/rm/both' );
check( '...and the one of the language stays when * is taken back', \Nino\Html::getFills( $fills, 'de_DE' )['[[/t/rm/both]]'] === 'de value' && isset( $fills['./nino/html/fills']['*']['[[/t/rm/both]]'] ) === false );
\Nino\Html::removeFill( $fills, '/t/rm/current', '' );
check( '...\'\' is the current language, as for addFills()', \Nino\Html::renderHtml( $fills, '[[/t/rm/current]]' ) === '[[/t/rm/current]]' );
$fillsBefore = $fills['./nino/html/fills'];
$fillsErrors = ninoErrors( function() use ( &$fills ) {
	\Nino\Html::removeFill( $fills, '/t/rm/never-added' );
	\Nino\Html::removeFill( $fills, '/t/rm/de', 'fr_FR' );
	\Nino\Html::removeFill( $fills, '/t/rm/de', '../private/config' );
} );
check( 'a fill that is not there, and a language the site does not have, are nothing to take back: no error, nothing created', $fillsErrors === [] && $fills['./nino/html/fills'] === $fillsBefore );
\Nino\Filesystem::removeDir( ninoSandboxDir( $fills ) );

echo "\n";


// --- Html::getFills - the fallback of the fills ----------------------------------

echo "Html::getFills and fillOrigin - a text the language has none of is the text of the first language that has one\n";

// de_DE is native and listed second; on, in this order: it_IT, de_DE, fr_FR, en_US; a file and off: es_ES and pt_PT.
// What each language says is told by the text itself, so a pin names the language that gave it
$fb = ninoSandbox( 'html-fallback' );
$fb['/nino/locales/available'] = [ 'it_IT', 'de_DE', 'fr_FR', 'en_US' ];
$fb['/nino/dir'] = '';

$fbTexts = [
	'/t/own'		=> [ 'de_DE' => 'de own', 'fr_FR' => 'fr own', 'en_US' => 'en own' ],
	'/t/mother'	=> [ 'de_DE' => 'de mother', 'it_IT' => 'it mother', 'fr_FR' => 'fr mother', 'en_US' => '' ],
	'/t/space'	=> [ 'de_DE' => 'de space', 'en_US' => " \n\t " ],
	'/t/order'	=> [ 'de_DE' => '', 'it_IT' => 'it order', 'fr_FR' => 'fr order', 'es_ES' => 'es order' ],
	'/t/rest'		=> [ 'de_DE' => '', 'it_IT' => ' ', 'fr_FR' => '', 'pt_PT' => 'pt rest', 'es_ES' => 'es rest' ],
	'/t/only'		=> [ 'pt_PT' => 'pt only' ],
	'/t/none'		=> [ 'de_DE' => '', 'fr_FR' => ' ', 'en_US' => '' ],
	'/project/website/html/lang' => [ 'de_DE' => 'de', 'en_US' => '' ],
];
$fbFiles = [];
foreach( $fbTexts as $fbKey => $fbValues )
	foreach( $fbValues as $fbLocale => $fbValue )
		$fbFiles[$fbLocale]['[['. $fbKey. ']]'] = $fbValue;
foreach( $fbFiles as $fbLocale => $fbFile )
	\Nino\Filesystem::putFileContent( $fb, '/text/'. $fbLocale. '.php', $fbFile );
\Nino\Filesystem::putFileContent( $fb, '/text/global.php', [ '[[/t/global]]' => 'in every language' ] );
// What is no language: a name that is no locale id, and a directory named like one
\Nino\Filesystem::putFileContent( $fb, '/text/notes.php', [ '[[/t/junk]]' => 'from a file that is no language' ] );
mkdir( \Nino\Filesystem::path( $fb, '/text' ). '/nl_NL.php' );
\Nino\Locales::setCurrentLocale( $fb, 'en_US' );

check( 'the text files that are languages are listed, the available ones first in the order the site lists them, then the others by their code - no file that is no locale id, no directory',
	\Nino\Locales::getTextLocales( $fb ) === [ 'it_IT', 'de_DE', 'fr_FR', 'en_US', 'es_ES', 'pt_PT' ] );

$fbFills = \Nino\Html::getFills( $fb );
check( 'a text the language has is its own, whatever the others say', $fbFills['[[/t/own]]'] === 'en own' && \Nino\Html::fillOrigin( $fb, '/t/own' ) === null );
check( 'one it has none of - empty, or only whitespace - is the native language\'s, although two others have one as well', $fbFills['[[/t/mother]]'] === 'de mother' && $fbFills['[[/t/space]]'] === 'de space'
	&& \Nino\Html::fillOrigin( $fb, '/t/mother' ) === 'de_DE' && \Nino\Html::fillOrigin( $fb, '/t/space' ) === 'de_DE' );
check( '...where the native one has none, the next available language in the order the site lists them - not the first by name', $fbFills['[[/t/order]]'] === 'it order' && \Nino\Html::fillOrigin( $fb, '/t/order' ) === 'it_IT' );
check( '...where none of those has one, a language that is only a file, by its code; a whitespace text on the way is no text', $fbFills['[[/t/rest]]'] === 'es rest' && \Nino\Html::fillOrigin( $fb, '/t/rest' ) === 'es_ES'
	&& $fbFills['[[/t/only]]'] === 'pt only' && \Nino\Html::fillOrigin( $fb, '/t/only' ) === 'pt_PT' );
check( '...and where no language has one, it stays what the language wrote, with no origin; a key nobody has is not made up', $fbFills['[[/t/none]]'] === '' && \Nino\Html::fillOrigin( $fb, '/t/none' ) === null
	&& isset( $fbFills['[[/t/nobody]]'] ) === false && \Nino\Html::fillOrigin( $fb, '/t/nobody' ) === null );
check( 'the global text is as it was, and a file that is no language is read by nobody', $fbFills['[[/t/global]]'] === 'in every language' && isset( $fbFills['[[/t/junk]]'] ) === false );
check( 'a page shows the fallback where there is one, and an empty text as nothing', \Nino\Html::renderHtml( $fb, '[[/t/mother]]|[[/t/none]]|[[/t/order]]' ) === 'de mother||it order' );

$fbNative = \Nino\Html::getFills( $fb, 'de_DE' );
check( 'the same goes for any language the site has: the native one takes from the other available languages in their order, another from the native one first', $fbNative['[[/t/order]]'] === 'it order' && $fbNative['[[/t/own]]'] === 'de own'
	&& \Nino\Html::getFills( $fb, 'fr_FR' )['[[/t/space]]'] === 'de space' && \Nino\Html::getFills( $fb, 'fr_FR' )['[[/t/own]]'] === 'fr own' );
check( '...and a language of a file only has no fills, as before', \Nino\Html::getFills( $fb, 'es_ES' ) === [] && ninoWarnings() === [] );

$fbNone = \Nino\Html::getFills( $fb, '*' );
check( 'asked for *, it has no chain: the global text, and nothing a language gave', $fbNone['[[/t/global]]'] === 'in every language' && isset( $fbNone['[[/t/mother]]'] ) === false && isset( $fbNone['[[/t/order]]'] ) === false );

\Nino\Html::addFills( $fb, [ '/t/mother' => 'runtime mother' ], 'en_US' );
\Nino\Html::addFills( $fb, [ '/t/order' => 'runtime star' ], '*' );
$fbRuntime = \Nino\Html::getFills( $fb );
check( 'a fill added at runtime wins over the text of another language - the language\'s own and the one of *', $fbRuntime['[[/t/mother]]'] === 'runtime mother' && $fbRuntime['[[/t/order]]'] === 'runtime star' );
check( '...and then nothing was taken from anywhere: it has no origin', \Nino\Html::fillOrigin( $fb, '/t/mother' ) === null && \Nino\Html::fillOrigin( $fb, '/t/order' ) === null );
\Nino\Html::removeFill( $fb, '/t/mother', 'en_US' );
\Nino\Html::removeFill( $fb, '/t/order' );
check( '...taken back, the text of the other language is what the page shows again', \Nino\Html::getFills( $fb )['[[/t/mother]]'] === 'de mother' && \Nino\Html::fillOrigin( $fb, '/t/mother' ) === 'de_DE' && \Nino\Html::getFills( $fb )['[[/t/order]]'] === 'it order' );

// The tag of a page: the one fill that never falls back, and the fill the kernel makes of it
check( 'the lang of a page never falls back: a language that has none of its own has none, whatever the native language wrote', $fbFills['[[/project/website/html/lang]]'] === '' && \Nino\Html::fillOrigin( $fb, '/project/website/html/lang' ) === null
	&& $fbNative['[[/project/website/html/lang]]'] === 'de' );
check( '[[/nino/html/lang]] is that value where the language has one, else the tag of the language - for each of them', $fbFills['[[/nino/html/lang]]'] === 'en-US' && $fbNative['[[/nino/html/lang]]'] === 'de'
	&& \Nino\Html::getFills( $fb, 'fr_FR' )['[[/nino/html/lang]]'] === 'fr-FR' && in_array( '/nino/html/lang', \Nino\Html::runtimeFillKeys( $fb ), true ) === true );
check( '...not for *, which is no language', isset( $fbNone['[[/nino/html/lang]]'] ) === false );
\Nino\Filesystem::mutate( $fb, '/text/en_US.php', static function( array $texts ): array {
	$texts['[[/project/website/html/lang]]'] = ' ';
	return $texts;
} );
$fbSpaceLang = \Nino\Html::getFills( $fb )['[[/nino/html/lang]]'];
\Nino\Filesystem::mutate( $fb, '/text/en_US.php', static function( array $texts ): array {
	$texts['[[/project/website/html/lang]]'] = 'en';
	return $texts;
} );
check( '...a value of whitespace is none, and a value the language wrote is the tag, as the site wrote it', $fbSpaceLang === 'en-US' && \Nino\Html::getFills( $fb )['[[/nino/html/lang]]'] === 'en' );

// Made once for a request, dropped by a write
$fbPrimed = \Nino\Html::getFills( $fb )['[[/t/space]]'];
file_put_contents( \Nino\Filesystem::path( $fb, '/text' ). '/de_DE.php', '<?php return [ \'[[/t/space]]\' => \'de space, written behind the kernel\' ];' );
$fbBehind = \Nino\Html::getFills( $fb )['[[/t/space]]'];
\Nino\Filesystem::putFileContent( $fb, '/data/unrelated.php', [] );
$fbElsewhere = \Nino\Html::getFills( $fb )['[[/t/space]]'];
\Nino\Filesystem::putFileContent( $fb, '/text/global.php', [ '[[/t/global]]' => 'in every language' ] );
check( 'what the text files give a language is made once per request: a file changed behind the kernel\'s back is not read again, a write elsewhere leaves that, a write under /text drops it',
	$fbPrimed === 'de space' && $fbBehind === 'de space' && $fbElsewhere === 'de space' && \Nino\Html::getFills( $fb )['[[/t/space]]'] === 'de space, written behind the kernel' );
$fbFrench = \Nino\Html::getFills( $fb, 'fr_FR' )['[[/t/space]]'];
\Nino\Filesystem::mutate( $fb, '/text/de_DE.php', static function( array $texts ): array {
	$texts['[[/t/space]]'] = 'de space, mutated';
	return $texts;
} );
check( '...also by mutate(), for the language that was written and for every other one that takes from it', $fbFrench === 'de space, written behind the kernel' && \Nino\Html::getFills( $fb )['[[/t/space]]'] === 'de space, mutated'
	&& \Nino\Html::fillOrigin( $fb, '/t/space' ) === 'de_DE' && \Nino\Html::getFills( $fb, 'fr_FR' )['[[/t/space]]'] === 'de space, mutated' );
\Nino\Filesystem::putFileContent( $fb, '/text/en_US.php', [ '[[/t/space]]' => 'en space' ] );
check( '...so a text saved in the language is the next fill of the same request, and the origin is gone with the fallback', \Nino\Html::getFills( $fb )['[[/t/space]]'] === 'en space' && \Nino\Html::fillOrigin( $fb, '/t/space' ) === null );
$fbBefore = \Nino\Locales::getTextLocales( $fb );
\Nino\Filesystem::putFileContent( $fb, '/text/ja_JP.php', [ '[[/t/japan]]' => 'ja' ] );
check( '...and the list of the text files: a language written is listed at once, among the others by its code, and the others take from it', in_array( 'ja_JP', $fbBefore, true ) === false
	&& \Nino\Locales::getTextLocales( $fb ) === [ 'it_IT', 'de_DE', 'fr_FR', 'en_US', 'es_ES', 'ja_JP', 'pt_PT' ] && \Nino\Html::getFills( $fb )['[[/t/japan]]'] === 'ja' && \Nino\Html::fillOrigin( $fb, '/t/japan' ) === 'ja_JP' );

\Nino\Filesystem::removeDir( ninoSandboxDir( $fb ) );

// A native language the site does not list - a config that was edited by hand - is no first choice: the site does not serve it, it only has a file
$nn = ninoSandbox( 'html-native' );
$nn['/nino/locales/native'] = 'fr_FR';
\Nino\Filesystem::putFileContent( $nn, '/text/de_DE.php', [] );
\Nino\Filesystem::putFileContent( $nn, '/text/en_US.php', [ '[[/t/name]]' => 'en name' ] );
\Nino\Filesystem::putFileContent( $nn, '/text/fr_FR.php', [ '[[/t/name]]' => 'fr name', '[[/t/only]]' => 'fr only' ] );
check( 'a native language the site does not list is one of the languages that only have a file: the languages of the site come first, and it is the last resort', \Nino\Html::getFills( $nn )['[[/t/name]]'] === 'en name'
	&& \Nino\Html::getFills( $nn )['[[/t/only]]'] === 'fr only' && \Nino\Html::fillOrigin( $nn, '/t/name' ) === 'en_US' );
\Nino\Filesystem::removeDir( ninoSandboxDir( $nn ) );

// What the fallback costs: a page asks for the fills of its language in every pass of its rendering, and the languages of a site may be asked for in turn.
// Each text file is read - included - once for the request, the ones of the chain too, as the single file of a language was before
$rc = ninoSandbox( 'html-reads' );
$rc['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
\Nino\Filesystem::forceDir( $rc, '/text' );
foreach( [ 'global', 'de_DE', 'en_US', 'fr_FR', 'es_ES' ] as $rcFile )
	file_put_contents( \Nino\Filesystem::path( $rc, '/text/'. $rcFile. '.php' ), '<?php $GLOBALS[\'ninoTextReads\'][\''. $rcFile. '\'] = ( $GLOBALS[\'ninoTextReads\'][\''. $rcFile. '\'] ?? 0 ) + 1; return [ \'[[/t/own]]\' => \''. $rcFile. '\', \'[[/t/\' . strtolower( $rcFile ). \']]\' => \'x\' ];' );
$GLOBALS['ninoTextReads'] = [];
foreach( [ 1, 2, 3 ] as $rcPass )
	foreach( [ 'en_US', 'de_DE', 'fr_FR' ] as $rcLocale ) {
		\Nino\Locales::useLocale( $rc, $rcLocale );
		\Nino\Html::getFills( $rc );
		\Nino\Html::fillOrigin( $rc, '/t/own' );
		\Nino\Html::renderHtml( $rc, '[[/t/own]]' );
	}
$rcReads = $GLOBALS['ninoTextReads'];
ksort( $rcReads );
check( 'a request reads each text file once - the files of the chain too - however many languages it is asked for and however often', $rcReads === [ 'de_DE' => 1, 'en_US' => 1, 'es_ES' => 1, 'fr_FR' => 1, 'global' => 1 ] );
\Nino\Filesystem::removeDir( ninoSandboxDir( $rc ) );

echo "\n";

// --- Locales::getVisitorLocale and languageTag --------------------------------------

echo "Locales::getVisitorLocale and languageTag - the language of the visitor, the tag of a language\n";

$vl = ninoSandbox( 'html-visitor' );
$vl['/nino/locales/available'] = [ 'de_DE', 'fr_FR', 'en_US' ];
$vl['./nino/locales/current'] = 'en_US';
check( 'the visitor of a browser that picked none is served the native language, whatever language this request is rendered in', \Nino\Locales::getVisitorLocale( $vl ) === 'de_DE' );
\Nino\Runtime::setSessionValue( $vl, './nino/locales/current', 'fr_FR' );
$vl['./nino/locales/current'] = 'de_DE';
check( '...the language the browser picked where the site has it - and not the request\'s: the workbench renders in its own', \Nino\Locales::getVisitorLocale( $vl ) === 'fr_FR' && \Nino\Locales::getCurrentLocale( $vl ) === 'de_DE' );
\Nino\Runtime::setSessionValue( $vl, './nino/locales/current', 'ja_JP' );
check( '...and the native one where the site does not have it any more', \Nino\Locales::getVisitorLocale( $vl ) === 'de_DE' );
\Nino\Runtime::setSessionValue( $vl, './nino/locales/current', [ 'fr_FR' ] );
check( '...a value that is no text is none', \Nino\Locales::getVisitorLocale( $vl ) === 'de_DE' );
$vl['/nino/locales/native'] = 'ja_JP';
check( '...and a native language the site does not list gives way to the first language it lists', \Nino\Locales::getVisitorLocale( $vl ) === 'de_DE' );
\Nino\Runtime::unsetSessionValue( $vl, './nino/locales/current' );
check( 'the tag of a language is its id with a hyphen, as a lang attribute is written', \Nino\Locales::languageTag( 'de_DE' ) === 'de-DE' && \Nino\Locales::languageTag( 'fr_FR' ) === 'fr-FR' && \Nino\Locales::languageTag( 'en' ) === 'en' );
\Nino\Filesystem::removeDir( ninoSandboxDir( $vl ) );

echo "\n";

// --- Html::shortcodeCalls ---------------------------------------------------

echo "Html::shortcodeCalls - the calls a piece of html+ makes, found and read as the rendering does\n";

$calls = ninoSandbox( 'html-calls' );
$seen = [];
\Nino\Html::addShortcode( $calls, 'probe', static function( array &$appData, array $args ) use ( &$seen ): string {
	$seen[] = $args;
	return '';
} );
\Nino\Html::addShortcode( $calls, 'wrap', static function( array &$appData, array $args ): string {
	return (string) ( $args['content'] ?? '' );
} );

$callsSource = '[probe /a/b name="x y" flag] text [wrap size="2"]inner [probe /c/d][/wrap] [unknown /e] [probe] [probe empty="" /f/g] [probe alt=""]';
$found = \Nino\Html::shortcodeCalls( $calls, $callsSource );
check( 'the calls are listed in the order they stand, each with its name and its arguments: a bare word a list entry, a named one under its name, the content between the tags as content',
	$found === [
		[ 'name' => 'probe', 'args' => [ '/a/b', 'name' => 'x y', 'flag' ] ],
		[ 'name' => 'wrap', 'args' => [ 'size' => '2', 'content' => 'inner [probe /c/d]' ] ],
		[ 'name' => 'probe', 'args' => [ '/c/d' ] ],
		[ 'name' => 'probe', 'args' => [] ],
		[ 'name' => 'probe', 'args' => [ 'empty' => '', '/f/g' ] ],
		[ 'name' => 'probe', 'args' => [ 'alt' => '' ] ],
	] );
check( '...the calls in the content of one follow it, and a name nobody registered is no call', array_column( $found, 'name' ) === [ 'probe', 'wrap', 'probe', 'probe', 'probe', 'probe' ] );

// What the rendering hands to the callbacks is what this reads: the same pattern, the same arguments
\Nino\Html::renderHtml( $calls, $callsSource );
check( 'the arguments are the ones the callback of a shortcode gets when the page is rendered', $seen === array_values( array_map( static fn( array $call ): array => $call['args'], array_filter( $found, static fn( array $call ): bool => $call['name'] === 'probe' ) ) ) && count( $seen ) === 5 );

check( '...nothing for a text with no bracket, an empty text and a bracket that opens no call', \Nino\Html::shortcodeCalls( $calls, '' ) === [] && \Nino\Html::shortcodeCalls( $calls, 'plain text' ) === []
	&& \Nino\Html::shortcodeCalls( $calls, '[ probe ] [probes] [/probe]' ) === [] );

\Nino\Html::addShortcode( $calls, 'late', static fn( array &$appData, array $args ): string => '' );
check( 'a shortcode registered after the first read is found by the next one', array_column( \Nino\Html::shortcodeCalls( $calls, '[late x]' ), 'name' ) === [ 'late' ] );
check( '...and a name the app data at hand did not register is no call there, whatever another one has', \Nino\Html::shortcodeCalls( $appData, '[probe /a/b]' ) === [] );

// shortcodeParts() cuts the same text by the same pattern into what stands at its top level - a component that sorts its children relies on the pieces being the text again
$parts = \Nino\Html::shortcodeParts( $calls, $callsSource );
check( 'the parts are the calls and the text between and around them, in the order they stand: each call as it is written, with the name it has, and what stands between the tags of a call is that call\'s and is not cut again',
	$parts === [
		[ 'name' => 'probe', 'text' => '[probe /a/b name="x y" flag]' ],
		[ 'name' => '', 'text' => ' text ' ],
		[ 'name' => 'wrap', 'text' => '[wrap size="2"]inner [probe /c/d][/wrap]' ],
		[ 'name' => '', 'text' => ' [unknown /e] ' ],
		[ 'name' => 'probe', 'text' => '[probe]' ],
		[ 'name' => '', 'text' => ' ' ],
		[ 'name' => 'probe', 'text' => '[probe empty="" /f/g]' ],
		[ 'name' => '', 'text' => ' ' ],
		[ 'name' => 'probe', 'text' => '[probe alt=""]' ],
	] );
check( '...put one after the other they are the text again, byte for byte, whatever it holds - a name nobody registered, a bracket that opens no call, a line break and a tab, text behind the last call',
	implode( '', array_column( $parts, 'text' ) ) === $callsSource
	&& array_filter( [ '', 'plain text', '[ probe ] [probes] [/probe]', "a\n\t[probe x]\n[wrap]\n[probe y]\n[/wrap] tail [", '[[probe]] [probe]]' ],
		static fn( string $source ): bool => implode( '', array_column( \Nino\Html::shortcodeParts( $calls, $source ), 'text' ) ) !== $source ) === [] );
check( '...nothing for an empty text, one part of no name for a text with no call, and a name that is registered later is found by the next cut',
	\Nino\Html::shortcodeParts( $calls, '' ) === [] && \Nino\Html::shortcodeParts( $calls, 'plain text' ) === [ [ 'name' => '', 'text' => 'plain text' ] ]
	&& \Nino\Html::shortcodeParts( $calls, '[ probe ] [probes] [/probe]' ) === [ [ 'name' => '', 'text' => '[ probe ] [probes] [/probe]' ] ]
	&& array_column( \Nino\Html::shortcodeParts( $calls, 'a [late x] b' ), 'name' ) === [ '', 'late', '' ]
	&& \Nino\Html::shortcodeParts( $appData, '[probe /a/b]' ) === [ [ 'name' => '', 'text' => '[probe /a/b]' ] ] );

\Nino\Filesystem::removeDir( ninoSandboxDir( $calls ) );

echo "\n";


// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
