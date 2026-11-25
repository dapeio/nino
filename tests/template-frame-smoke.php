<?php
declare(strict_types=1);

/**
 *	Nino
 *	template-frame-smoke.php	Contract test for the frame of a page - \Nino\Modules\Template::doFrame():
 *
 *											A. [frame /templates/frame-header[-word]] draws the scaffold of the page with the frame in [[header]]; the scaffold
 *											   called as it always was has the standard; a scaffold of the old kind is as it was
 *											B. what a page may say in the call, what a text may say, and where the fill is set
 *											C. the frames of the site, the scaffold that has a place for one, the log, the cache
 *
 *	Usage: php tests/template-frame-smoke.php
 */

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

$appData = ninoSandbox( 'template-frame' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

mkdir( $sandbox. '/private/templates', 0777, true );
mkdir( $sandbox. '/private/text', 0777, true );
mkdir( $sandbox. '/private/data', 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

\Nino\Csrf::init( $appData );
\Nino\Modules\Csrf::init( $appData );
\Nino\Html::init( $appData );
\Nino\Modules\Template::init( $appData );

// What a template that is drawn is told its words are the ones of
\Nino\Html::addShortcode( $appData, 'ctx', static fn( array &$appData, array $args ): string => (string) ( \Nino\Html::context( $appData )['template'] ?? '' ), false );

// A shortcode that stops the drawing, and one that counts how often it is drawn
\Nino\Html::addShortcode( $appData, 'boom', static function( array &$appData, array $args ): string {
	throw new RuntimeException( 'stop' );
}, false );
$ticks = new ArrayObject( [ 0 ] );
\Nino\Html::addShortcode( $appData, 'tick', static function( array &$appData, array $args ) use ( $ticks ): string {
	$ticks[0]++;
	return '';
}, false );

$tocks = new ArrayObject( [ 0 ] );
\Nino\Html::addShortcode( $appData, 'tock', static function( array &$appData, array $args ) use ( $tocks ): string {
	$tocks[0]++;
	return '';
}, false );

$write = static function( string $name, string $text ) use ( &$appData ): void {
	file_put_contents( \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' ), $text );
};

// The templates of a site: the scaffold in two halves with a place for the frame, the standard frames, named ones, and pages that no frame is to be taken for
$write( 'html-header', '<head/>[[header]]<main>' );
$write( 'html-footer', '</main>[[footer]]' );
$write( 'frame-header', '<header>std-h[tick]</header>' );
$write( 'frame-footer', '<footer>std-f</footer>' );
$write( 'frame-header-landing', '<header>landing-h [ctx]</header>' );
$write( 'frame-footer-landing', '<footer>landing-f</footer>' );
$write( 'frame-header-none', '<header>NONE-FILE</header>' );
$write( 'frame-header-default', '<header>DEFAULT-FILE</header>' );
$write( 'frame-footer-none', '<footer>NONE-FILE</footer>' );
$write( 'frame-header-'. str_repeat( 'a', 40 ), '<header>forty</header>' );
$write( 'frame-header-'. str_repeat( 'a', 41 ), '<header>FORTY-ONE</header>' );
$write( 'frame-header-Upper', '<header>UPPER-FILE</header>' );
$write( 'frame-header-two--hyphens', '<header>TWO-HYPHENS-FILE</header>' );
$write( 'frame-header-boom', '<header>[boom]</header>' );
$write( 'frame-header-selfy', '<header>selfy [frame /templates/frame-header]</header>' );
$write( 'frame-header-loop', '<header>loop [template /templates/html-header]</header>' );
$write( 'page-x', 'SECRET-PAGE-X' );
$write( 'page-secret', 'SECRET-PAGE-SECRET' );
$write( 'inner', '<i>[[header]]</i>' );
symlink( \Nino\Filesystem::path( $appData, '/templates/frame-header-landing.tpl' ), \Nino\Filesystem::path( $appData, '/templates/frame-header-linked.tpl' ) );

$inside = ( new ReflectionClassConstant( \Nino\Modules\Template::class, 'INSIDE' ) )->getValue();
$said = ( new ReflectionClassConstant( \Nino\Modules\Template::class, 'SAID' ) )->getValue();
$cache = ( new ReflectionClassConstant( \Nino\Html::class, 'CACHE' ) )->getValue();
$counter = static function() use ( &$appData ): string { return implode( '|', array_map( 'file_get_contents', glob( \Nino\Filesystem::path( $appData, '/data/.counters/template-frame-notices' ). '/*.php' ) ?: [] ) ); };

/**
 *	A request of its own: what was kept in memory and what was said in it is gone - the counter of the log, which lasts, is told by itself
 */
$fresh = static function( bool $window = true ) use ( &$appData, $inside, $said, $cache, $counter ): void {
	unset( $appData[$inside], $appData[$said], $appData[$cache] );
	clearstatcache();

	if( $window === true )
		ninoCounterSet( $appData, 'template-frame-notices', [] );
};

/**
 *	A page drawn from a source, in a request of its own, and what the log said of it
 */
$logged = [];
$page = static function( string $source, bool $window = true ) use ( &$appData, $fresh, &$logged ): string {
	$fresh( $window );
	ninoWarnings();
	$html = \Nino\Html::renderHtml( $appData, $source );
	$logged = ninoWarnings();
	return $html;
};

$head = static fn( string $word = '' ): string => '[frame /templates/frame-header'. ( $word === '' ? '' : '-'. $word ). ']';
$foot = static fn( string $word = '' ): string => '[frame /templates/frame-footer'. ( $word === '' ? '' : '-'. $word ). ']';
$std = '<head/><header>std-h</header><main>';

// --- A. The page calls the frame ---------------------------------------------------------

echo "A page chooses its frame\n";

check( 'a page that calls the scaffold as it always has has the standard frame in both halves',
	$page( '[template /templates/html-header]BODY[template /templates/html-footer]' ) === '<head/><header>std-h</header><main>BODY</main><footer>std-f</footer>' );
check( '...and so has a page that calls the standard frame, which is the same page', $page( $head(). 'BODY'. $foot() ) === '<head/><header>std-h</header><main>BODY</main><footer>std-f</footer>' );
check( 'a word draws frame-header-<word> in the scaffold - with the words of that file as the words of the template - and leaves the footer to its own call',
	$page( $head( 'landing' ). 'BODY'. $foot() ) === '<head/><header>landing-h frame-header-landing</header><main>BODY</main><footer>std-f</footer>' );
check( '...and the footer has its words as well, without the header being touched', $page( $head(). 'BODY'. $foot( 'landing' ) ) === '<head/><header>std-h</header><main>BODY</main><footer>landing-f</footer>' );
check( 'none draws the scaffold with nothing in it, on either side - and the file of that name is never read',
	$page( $head( 'none' ). 'BODY'. $foot( 'none' ) ) === '<head/><main>BODY</main>' );
check( 'default is the standard, and the file of that name is no frame', $page( $head( 'default' ) ) === $std );
check( 'a word of 40 characters is a word, and its file is drawn', $page( $head( str_repeat( 'a', 40 ) ) ) === '<head/><header>forty</header><main>' );
check( 'every call has its own frame: two in one page are two scaffolds, each with its own', $page( $head( 'landing' ). $head() ) === '<head/><header>landing-h frame-header-landing</header><main>'. $std );

$ticks[0] = 0;
$page( '[template /templates/html-header][template /templates/html-footer]' );
check( 'the standard frame is drawn once for each place the scaffold has for it', $ticks[0] === 1 );

// A scaffold of the old kind: its own [template] call of the frame, no place for a choice
$write( 'html-header', '<head/>[template /templates/frame-header]<main>' );
$write( 'html-footer', '</main>[template /templates/frame-footer]' );
$ticks[0] = 0;
$old = $page( '[template /templates/html-header]B[template /templates/html-footer]' );
check( 'a scaffold that draws its frame by a [template] call of its own is drawn exactly as it was: the same bytes, and no frame drawn that nobody asked for',
	$old === '<head/><header>std-h</header><main>B</main><footer>std-f</footer>' && $ticks[0] === 1 );
$write( 'frame-header-count', '<header>COUNT[tock]</header>' );
$ticks[0] = 0;
$tocks[0] = 0;
$out = $page( $head( 'count' ). $foot( 'landing' ) );
check( 'a scaffold with no place for a frame has none drawn for it: the frame that was chosen is not drawn at all', $tocks[0] === 0 && str_contains( $out, 'COUNT' ) === false && $out === '<head/><header>std-h</header><main></main><footer>std-f</footer>' );
check( '...and a [frame] on it draws that scaffold, and the choice has no effect', $page( $head( 'landing' ). 'B'. $foot( 'none' ) ) === $old && $page( $head( 'none' ) ) === $std );
$write( 'html-header', '<head/>[[header]]<main>' );
$write( 'html-footer', '</main>[[footer]]' );

$all = [ $page( '[template /templates/html-header]' ), $page( $head( 'none' ) ), $page( $head( 'ghost' ) ), $page( $head( 'landing' ). $foot( 'landing' ) ), $page( '[template /templates/html-footer]' ) ];
check( 'a token that nobody filled never stands on a page', preg_grep( '/\[\[(header|footer)\]\]/', $all ) === [] );

// --- B. What a page may say, what a text may say ------------------------------------------

echo "\nWhat the call may say\n";

$bad = [
	'another template'							=> '[frame /templates/page-x]',
	'a secret page'									=> '[frame /templates/page-secret]',
	'a path up'											=> '[frame /templates/frame-header/../page-x]',
	'a path up in front'						=> '[frame /templates/../templates/frame-header]',
	'another folder'								=> '[frame /other/frame-header]',
	'no folder'											=> '[frame frame-header]',
	'the file name'									=> '[frame /templates/frame-header.tpl]',
	'a side that is none'						=> '[frame /templates/frame-body]',
	'the scaffold itself'						=> '[frame /templates/html-header]',
	'a capital'											=> '[frame /templates/frame-header-Landing]',
	'a capital in the side'					=> '[frame /templates/Frame-header]',
	'a fill in it'									=> '[frame /templates/frame-header-[[/nino/http/response/locale]]]',
	'a hyphen at the end'						=> '[frame /templates/frame-header-]',
	'two hyphens'										=> '[frame /templates/frame-header--landing]',
	'41 characters of word'					=> '[frame /templates/frame-header-'. str_repeat( 'a', 41 ). ']',
	'no argument'										=> '[frame]',
	'two arguments'									=> '[frame /templates/frame-header /templates/frame-footer]',
	'an attribute'									=> '[frame /templates/frame-header x="y"]',
	'a switch'											=> '[frame /templates/frame-header landing]',
	'the file that is no frame'			=> '[frame /templates/frame-header-Upper]',
	'an underscore'									=> '[frame /templates/frame-header-lan_ding]',
	'a dot'													=> '[frame /templates/frame-header-lan.ding]',
];

foreach( $bad as $label => $source ) {

	$out = $page( $source );

	check( '[frame] with '. $label. ' draws nothing - the scaffold not either - and says so in the log', $out === '' && count( $logged ) === 1 );
}

foreach( [ 'a relative folder' => 'templates/frame-header', 'a backslash' => '\\templates\\frame-header', 'a blank' => "/templates/frame-header-lan ding", 'a line break' => "/templates/frame-header-landing\n", 'a blank in front' => ' /templates/frame-header', 'a null byte' => "/templates/frame-header\0", 'a control byte' => "/templates/frame-header-\x01", 'a number' => 5, 'an array' => [ 'x' ] ] as $label => $value ) {

	$fresh();
	ninoWarnings();
	$out = \Nino\Modules\Template::doFrame( $appData, [ $value ] );
	$lines = ninoWarnings();

	check( 'a name with '. $label. ' draws nothing, and the log shows it as printable text', $out === '' && count( $lines ) === 1 && preg_match( '/^[\x20-\x7e]+$/', $lines[0] ) === 1 );
}

$page( '' );
\Nino\Html::addFills( $appData, [ '/project/frame/pick/name' => '/templates/frame-header-landing' ], '*' );
$out = $page( '[frame [[/project/frame/pick/name]]]' );
check( 'the argument is read as it is written: a name that a fill would make is no frame, and nothing is drawn of it', str_contains( $out, '<head/>' ) === false && str_contains( $out, 'landing-h' ) === false );
$out = $page( '[frame /templates/frame-header-[[/project/frame/pick/name]]]' );
check( '...and a fill that stands in a name does not make it one', str_contains( $out, '<head/>' ) === false && str_contains( $out, 'landing-h' ) === false );

$out = $page( $head( 'ghost' ). $head( 'ghost' ) );
check( 'a word with no file draws the scaffold with the standard frame, says so once, and draws nothing of another template', $out === $std. $std && count( $logged ) === 1 );

$out = $page( $head( 'linked' ) );
check( 'a link is no frame: the word whose file is a link to another frame is a word with no file', $out === $std && count( $logged ) === 1 );

$out = $page( $head( 'selfy' ). 'AFTER' );
check( 'a [frame] in a frame draws nothing, is said, and the page goes on', $out === '<head/><header>selfy </header><main>AFTER' && count( $logged ) === 1 );

$write( 'html-header', '<head/>[frame /templates/frame-header][[header]]<main>' );
$out = $page( $head( 'landing' ) );
check( 'a [frame] in a scaffold draws nothing, is said, and the scaffold is drawn', $out === '<head/><header>landing-h frame-header-landing</header><main>' && count( $logged ) === 1 );
$write( 'html-header', '<head/>[[header]]<main>' );

$out = $page( $head( 'loop' ). 'AFTER' );
check( 'a frame that draws the scaffold ends, with the standard frame in it, and the page is drawn', $out === '<head/><header>loop <head/><header>std-h</header><main></header><main>AFTER' );

$thrown = false;

try {
	$fresh();
	\Nino\Modules\Template::doFrame( $appData, [ '/templates/frame-header-boom' ] );
}
catch( RuntimeException $stop ) {
	$thrown = true;
}

check( 'a frame that throws leaves nothing held: the next [frame] is drawn', $thrown === true && isset( $appData[$inside] ) === false && $page( $head() ) === $std );

$write( 'page-fill', '[frame [[/project/frame/pick/name]]]|[frame /templates/frame-header-[[/project/frame/pick/name]]]|[frame /templates/frame-header-landing]' );
$out = $page( '[template /templates/page-fill]' );
check( 'the argument of a page that is a file is read as it is written as well: a fill in it is no fill, and only the call that has none is drawn', substr_count( $out, 'landing-h' ) === 1 && substr_count( $out, '<head/>' ) === 1 && count( $logged ) === 1 );

$out = $page( '[template /templates/html-header]'. $head( 'landing' ). '[template /templates/html-footer]'. $foot( 'landing' ) );
check( 'a scaffold that is drawn as it always was holds nothing when it is done: a [frame] after it is drawn', $out === '<head/><header>std-h</header><main>'. '<head/><header>landing-h frame-header-landing</header><main>'. '</main><footer>std-f</footer>'. '</main><footer>landing-f</footer>' );

\Nino\Html::addFills( $appData, [ '/project/frame/pick/word' => 'landing' ], '*' );
$write( 'page-fill2', '[frame /templates/frame-header-[[/project/frame/pick/word]]]' );
$out = $page( '[template /templates/page-fill2]' );
check( '...also where the fill is a word that would make it a frame of the site: a name is what is written', $out === '' && count( $logged ) === 1 );

$write( 'frame-header-tokens', '<t>[[header]][[footer]]</t>' );
check( 'a frame has no fill of the scaffold in its text: what it writes as [[header]] stays as it is written', $page( $head( 'tokens' ) ) === '<head/><t>[[header]][[footer]]</t><main>' );

echo "\nWhat a text may say, and where the fill is set\n";

\Nino\Html::addFills( $appData, [ '/project/frame/text/tricks' => 'TEXT [[header]] [[footer]] [frame /templates/page-secret] [template /templates/page-secret]' ], '*' );
$write( 'html-header', '<head/>[[header]]<p>[[/project/frame/text/tricks]]</p><main>' );
$out = $page( $head( 'landing' ) );
check( 'a text of the site with [[header]] or a call in it is the text it is: nothing is put in, nothing is drawn, and the fill is not read again',
	$out === '<head/><header>landing-h frame-header-landing</header><p>TEXT [[header]] [[footer]] [frame /templates/page-secret] [template /templates/page-secret]</p><main>' && str_contains( $out, 'SECRET' ) === false );
$write( 'html-header', '<head/>[[header]]<main>' );

$write( 'other', '<o>[[header]][[footer]]</o>' );
check( 'the fill is set in the two scaffolds and in no other template: a page that calls another template with [[header]] in it gets nothing',
	$page( '[template /templates/other]' ) === '<o>[[header]][[footer]]</o>' && $page( $head( 'landing' ). '[template /templates/other]' ) === '<head/><header>landing-h frame-header-landing</header><main><o>[[header]][[footer]]</o>' );

$write( 'html-header-x', 'X-H[[header]]' );
$write( 'html-footer-x', 'X-F[[footer]]' );
$write( 'html-header', '<head/>[[header]]<main>' );
check( 'only the scaffold itself is filled: a template of another name that starts with its name has the token as text',
	$page( '[template /templates/html-header-x][template /templates/html-footer-x]' ) === 'X-H[[header]]X-F[[footer]]' && $page( '[template /templates/html-header][template /templates/html-footer-x]' ) === $std. 'X-F[[footer]]' );

$write( 'html-header', '<head/>[[header]][template /templates/inner]<main>' );
check( '...and not in a template the scaffold includes: what the scaffold writes in its own text is filled, what it includes is that template\'s own', $page( $head( 'landing' ) ) === '<head/><header>landing-h frame-header-landing</header><i>[[header]]</i><main>' );
$write( 'html-header', '<head/>[[header]]<main>' );

$write( 'html-footer', '</main>[[header]][[footer]]' );
check( 'a scaffold has the fill of its own side only', $page( $foot( 'landing' ) ) === '</main>[[header]]<footer>landing-f</footer>' );
$write( 'html-footer', '</main>[[footer]]' );

check( 'a [frame] where no scaffold is wanted does not leak into the next: a page that stands in a frame has none held', $page( $head( 'landing' ). '[template /templates/other]'. $head() ) === '<head/><header>landing-h frame-header-landing</header><main><o>[[header]][[footer]]</o>'. $std );

// --- C. The frames of the site, the log, the cache ----------------------------------------

echo "\nThe frames of the site\n";

$frames = \Nino\Modules\Template::frames( $appData );
check( 'frames() lists the standard of each side first and the words of its files after it, sorted: with the form of a word - not none, not default, not a capital, not 41 characters, not a double hyphen - and no link',
	array_keys( $frames ) === [ 'header', 'footer' ] && $frames['header'] === [ 'default', str_repeat( 'a', 40 ), 'boom', 'count', 'landing', 'loop', 'selfy', 'tokens' ] && $frames['footer'] === [ 'default', 'landing' ] );

$listed = [];

foreach( [ 'landing', 'linked', 'ghost', 'Upper', 'none', 'default' ] as $word ) {

	$out = $page( '[frame /templates/frame-header-'. $word. ']' );
	$drawn = str_contains( $out, 'landing-h' ) || str_contains( $out, 'UPPER-FILE' ) || str_contains( $out, 'NONE-FILE' ) || str_contains( $out, 'DEFAULT-FILE' );
	$listed[$word] = in_array( $word, $frames['header'], true ) === true;

	if( $word === 'landing' && $drawn === false )
		$listed[$word] = 'landing is not drawn';

	if( in_array( $word, [ 'linked', 'ghost', 'Upper', 'none', 'default' ], true ) === true && $drawn === true )
		$listed[$word] = 'drawn though it is no frame';
}

check( 'what a [frame] draws and what frames() lists are one list: a name that has the form and no more is a frame, any other is the standard (and default, which frames() lists for the standard itself, draws it)', $listed === [ 'landing' => true, 'linked' => false, 'ghost' => false, 'Upper' => false, 'none' => false, 'default' => true ] );

$gone = \Nino\Filesystem::path( $appData, '/templates/frame-footer.tpl' );
$kept = (string) file_get_contents( $gone );
file_put_contents( $gone, '' );
check( '...and has the standard only where its file is a file', \Nino\Modules\Template::frames( $appData )['footer'] === [ 'default', 'landing' ] );
unlink( $gone );
symlink( \Nino\Filesystem::path( $appData, '/templates/frame-footer-landing.tpl' ), $gone );
check( '...and not where it is a link', \Nino\Modules\Template::frames( $appData )['footer'] === [ 'landing' ] );
unlink( $gone );
check( '...and has none where it is not there', \Nino\Modules\Template::frames( $appData )['footer'] === [ 'landing' ] );
file_put_contents( $gone, $kept );

echo "\nThe scaffold that has a place for a frame\n";

$scaffold = static function( string $header, string $footer ) use ( $write, $fresh, &$appData ): array {
	$write( 'html-header', $header );
	$write( 'html-footer', $footer );
	$fresh();
	return \Nino\Modules\Template::framed( $appData );
};

check( 'framed() says whether the scaffold has [[header]] and [[footer]] in its text', $scaffold( '<head/>[[header]]<main>', '</main>[[footer]]' ) === [ 'header' => true, 'footer' => true ]
	&& $scaffold( '<head/>[[header]]<main>', '</main>' ) === [ 'header' => true, 'footer' => false ] );
check( '...and that one that draws its frames by a [template] call of its own does not, a fill with a key of its own is none, and neither is one in a text it is written escaped in',
	$scaffold( '<head/>[template /templates/frame-header]<main>', '</main>[template /templates/frame-footer]' ) === [ 'header' => false, 'footer' => false ]
	&& $scaffold( '[[/project/header]][[headers]]', '[[ footer ]][[Footer]]' ) === [ 'header' => false, 'footer' => false ] );
check( '...and an escaped fill and one in a comment of html are none', $scaffold( '<head/>\[[header]]<main>', '</main><!-- [[footer]] -->' ) === [ 'header' => false, 'footer' => false ]
	&& $scaffold( '<!-- x --><head/>[[header]]', '<!-- [[footer]] -->[[footer]]' ) === [ 'header' => true, 'footer' => true ]
	&& $scaffold( '<!-- a -->[[header]]<!-- b -->', '<!-- [[footer]]' ) === [ 'header' => true, 'footer' => false ] );
check( '...and one in a call that holds it is one', $scaffold( '<head/>[ctx]<main>', '</main>' ) === [ 'header' => false, 'footer' => false ] );
\Nino\Html::addShortcode( $appData, 'wrap', static fn( array &$appData, array $args ): string => '<w>'. \Nino\Html::renderSource( $appData, (string) ( $args['content'] ?? '' ), \Nino\Html::context( $appData ) ). '</w>', true );
check( '...and one that stands between the tags of a call is one: the scaffold may wrap the place of its frame', $scaffold( '<head/>[wrap][[header]][/wrap]<main>', '</main>' ) === [ 'header' => true, 'footer' => false ] );
$scaffold( '<head/>[[header]]<main>', '</main>[[footer]]' );

$appData['./nino/template/swapped']['html-header'] = '<h/>';
check( 'a draft of a preview that has no place for the frame is no scaffold that has one, though the file has', \Nino\Modules\Template::framed( $appData )['header'] === false );
unset( $appData['./nino/template/swapped'] );

$appData['./nino/template/swapped']['html-header'] = '<h/>[[header]]';
check( 'a draft of a preview in the place of the scaffold is read as the scaffold is: it has the standard frame, or the one a page chose',
	\Nino\Modules\Template::framed( $appData )['header'] === true && $page( '[template /templates/html-header]' ) === '<h/><header>std-h</header>' && $page( $head( 'landing' ) ) === '<h/><header>landing-h frame-header-landing</header>' );
unset( $appData['./nino/template/swapped'] );

$appData['./nino/template/swapped']['frame-header-draft'] = 'DRAFT';
check( 'a frame that has a draft and no file is drawn from the draft', $page( $head( 'draft' ) ) === '<head/>DRAFT<main>' );
unset( $appData['./nino/template/swapped'] );

$library = dirname( __DIR__ ). '/_admin/install/library/base/templates/';
$libraryHeader = (string) file_get_contents( $library. 'html-header.tpl' );
$libraryFooter = (string) file_get_contents( $library. 'html-footer.tpl' );
check( 'the scaffold a new site is installed with has the place for the frames, and no [template] call of a frame, and no [frame]',
	preg_match( '/\[\[header\]\]/', $libraryHeader ) === 1 && preg_match( '/\[\[footer\]\]/', $libraryFooter ) === 1
	&& preg_match( '~\[template /templates/frame-|\[frame~', $libraryHeader. $libraryFooter ) === 0 );
check( '...and the scaffold of the library says so to framed()', ( static function() use ( &$appData, $write, $fresh, $libraryHeader, $libraryFooter ): bool {
	$write( 'html-header', $libraryHeader );
	$write( 'html-footer', $libraryFooter );
	$fresh();
	return \Nino\Modules\Template::framed( $appData ) === [ 'header' => true, 'footer' => true ];
} )() );
$write( 'html-header', '<head/>[[header]]<main>' );
$write( 'html-footer', '</main>[[footer]]' );

check( 'a frame is a template of its own: its category is its file name, so its texts are /template/frame-header-<word>/...',
	\Nino\Modules\Template::category( 'frame-header-landing.tpl' ) === 'frame-header-landing' && \Nino\Modules\Template::category( '/templates/frame-footer-landing' ) === 'frame-footer-landing'
	&& \Nino\Modules\Template::category( 'frame-header-Upper.tpl' ) === null );

echo "\nThe log\n";

$page( '[frame /templates/frame-header x="y"]' );
$many = $logged;
$page( '[frame /templates/frame-header x]' );
check( 'a call with more than the name says so, and shows the name and no other value', count( $many ) === 1 && str_contains( $many[0], 'more than one argument' ) === true && str_contains( $many[0], '"y"' ) === false && str_contains( $logged[0] ?? '', 'more than one argument' ) === true );


$page( $head( 'ghost' ) );
$first = count( $logged );
$page( $head( 'ghost' ), false );
$second = count( $logged );
$page( $head( 'ghost' ), true );
check( 'a thing is said once in a request and once in an hour, however many requests meet it: the page that names a frame wrong does not write at every visit, and says it again when the hour is over',
	$first === 1 && $second === 0 && count( $logged ) === 1 );

$page( $head( 'ghost' ) );
$before = $counter();
$page( $head( 'ghost' ). $head( 'ghost' ), false );
check( 'a thing that was said in the hour is met without a write: the files of the counter are as they were', count( $logged ) === 0 && $counter() === $before && $before !== '' );

$page( $head( 'ghost' ). $foot( 'ghost' ). '[frame /templates/frame-header-other]' );
check( 'what is another thing is said of its own', count( $logged ) === 3 );

$fresh();
ninoWarnings();

for( $each = 0; $each < 60; $each++ )
	\Nino\Modules\Template::doFrame( $appData, [ 'no/'. $each ] );

check( 'a request that makes up names is told of the first twenty: the log is not the size the page decides', count( ninoWarnings() ) === 20 );

echo "\nThe page cache\n";

$appData['/nino/cache/status'] = true;
$appData['/nino/cache/ttl'] = 60;
$appData['/nino/cache/blacklist'] = [];
\Nino\Modules\Cache::_invalidate( $appData );

$pages = [ '/landing' => $head( 'landing' ). 'LANDING'. $foot( 'none' ), '/plain' => $head(). 'PLAIN'. $foot(), '/old' => '[template /templates/html-header]OLD[template /templates/html-footer]' ];
$drawn = [];

foreach( $pages as $uri => $source ) {

	$drawn[$uri] = $page( $source );
	$request = [ '/nino/http/request' => [ 'method' => 'GET', 'rawMethod' => 'GET', 'uri' => $uri, 'query' => [] ], '/nino/http/response' => [ 'uri' => $uri, 'locale' => 'de_DE', 'statusCode' => 200, 'header' => [], 'body' => $drawn[$uri] ] ];
	\Nino\Modules\Cache::callbackOutput( $appData, $request );
}

$served = [];

foreach( array_keys( $pages ) as $uri ) {

	$hit = [ '/nino/http/request' => [ 'method' => 'GET', 'rawMethod' => 'GET', 'uri' => $uri, 'query' => [] ], '/nino/http/response' => [ 'uri' => $uri, 'locale' => 'de_DE', 'statusCode' => 200, 'header' => [], 'body' => '' ] ];
	$served[$uri] = \Nino\Modules\Cache::_prepare( $appData, $hit ) === true ? $hit['/nino/http/response']['body'] : null;
}

check( 'pages that choose two frames, and a page of the old kind, are three entries of the cache, and each is served as it was drawn',
	$served === $drawn && count( array_unique( $drawn ) ) === 3 && str_contains( $served['/landing'], 'landing-h' ) === true && str_contains( $served['/landing'], 'std-f' ) === false
	&& str_contains( $served['/plain'], 'std-h' ) === true && str_contains( $served['/old'], 'std-f' ) === true );

\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/status'] = false;

echo "\n";

\Nino\Filesystem::removeDir( $sandbox );

ninoDone( $appData );
