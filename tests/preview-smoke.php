<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	preview-smoke.php		Test of the draft a preview shows: \Nino\Preview - the token a session is given and nobody
 *											else, its folder, its time and its number -, the template that \Nino\Modules\Template::swap()
 *											draws in the place of a file for the one request that holds a draft, and the comments that
 *											\Nino\Components::render() writes around what each node drew, for such a request only.
 *											Runs against an isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/preview-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'preview' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

foreach( [ 'private/templates', 'private/text' ] as $directory )
	mkdir( $sandbox. '/'. $directory, 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$perm = '/_admin/preview/manage';

\Nino\Csrf::init( $appData );
\Nino\Html::init( $appData );
\Nino\Modules\Template::init( $appData );

// A wrap, a title that reads a text, and a component that draws the nodes it holds
\Nino\Components::addComponent( $appData, 'box', [ 'label' => 'Box', 'in' => [ 'page' ], 'offers' => [ 'title' ], 'attributes' => [ 'id' => [ 'type' => 'id', 'default' => '' ] ], 'template' => '<div[[id]]>[[content]]</div>' ] );
\Nino\Components::addComponent( $appData, 'title', [ 'label' => 'Title', 'in' => [ 'box' ], 'source' => 'text', 'template' => '<h2>[[value]]</h2>' ] );
\Nino\Components::addComponent( $appData, 'rounds', [ 'label' => 'Rounds', 'in' => [ 'page' ], 'offers' => [ 'title' ], 'children' => 'nodes', 'template' => '' ],
	static function( array &$appData, array $args, string $content, array $context ): string {
		return \Nino\Components::render( $appData, $context['children'], $context ). \Nino\Components::render( $appData, $context['children'], $context );
	} );

\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/template/page-x/hero/title]]' => 'Hallo', '[[/template/page-x/hero/fresh]]' => 'Neu' ] );

$file = '[box id="hero"][title ~title][/box][rounds][title /template/page-x/hero/title][/rounds]';
\Nino\Filesystem::putFileContent( $appData, '/templates/page-x.tpl', $file );
$before = [ md5( $file ), filemtime( $sandbox. '/private/templates/page-x.tpl' ) ];

$draw = static function() use ( &$appData ): string {
	return \Nino\Html::renderHtml( $appData, '[template /templates/page-x]' );
};

/**
 *	A request of the site that carries a token - or none - as the signed-in account of this session
 */
$ask = static function( array &$appData, string $token ) use ( $perm ): array {

	$request = ninoVisit( $appData, '/x'. ( $token === '' ? '' : '?nino-preview='. $token ) );
	$GLOBALS['ninoHeld'] = \Nino\Preview::held( $appData, $request, 'probe', $perm );

	return $request;
};

echo "Preview - the token\n";

check( 'nobody is given a token: no account, an account without the right, a kind that is none', \Nino\Preview::issue( $appData, 'probe', $perm, [ 'a' => 1 ] ) === '' );

ninoUser( $appData, 'plain@example.com', [] );
check( '...an account without the right', \Nino\Preview::issue( $appData, 'probe', $perm, [ 'a' => 1 ] ) === '' );

ninoUser( $appData, 'owner@example.com', [ $perm ] );
check( '...a kind that is no word, a draft that is too large', \Nino\Preview::issue( $appData, '../x', $perm, [] ) === '' && \Nino\Preview::issue( $appData, 'Probe', $perm, [] ) === ''
	&& \Nino\Preview::issue( $appData, 'probe', $perm, [ str_repeat( 'x', \Nino\Preview::MAX_BYTES ) ] ) === '' );

$token = \Nino\Preview::issue( $appData, 'probe', $perm, [ 'source' => 'draft' ] );
check( 'the account that holds the right is given a token of 128 bits, and its draft is a folder of the kind below the data', preg_match( \Nino\Preview::TOKEN, $token ) === 1
	&& is_file( $sandbox. '/private/data/.preview/probe/'. $token. '/draft.json' ) === true && is_file( $sandbox. '/private/data/.preview/probe/'. $token. '/meta.json' ) === true );

$request = $ask( $appData, $token );
check( 'the request with the token has the draft, and is marked a preview that nobody stores and no engine reads', ( $GLOBALS['ninoHeld']['data'] ?? null ) === [ 'source' => 'draft' ] && \Nino\Preview::active( $appData ) === true
	&& $request['/nino/http/response']['header']['Cache-Control'] === 'no-store' && $request['/nino/http/response']['header']['X-Robots-Tag'] === 'noindex' && $request['/nino/http/response']['header']['Referrer-Policy'] === 'no-referrer' );

// The page of a preview that asks for the mark carries it on its <html> from the first byte - and a page of a request that holds no draft never does, whatever its query says
$markWith = static function( array &$appData, string $query ): string {

	$request = ninoVisit( $appData, '/x?'. $query );
	$request['/nino/http/response']['body'] = '<!DOCTYPE html><html lang="de"><head></head><body><html></body></html>';
	\Nino\Html::response( $appData, $request );

	return (string) $request['/nino/http/response']['body'];
};
$asked = \Nino\Preview::MARK. '=loose';
$marked = $markWith( $appData, 'nino-preview='. $token. '&'. $asked );
check( 'a request that holds a draft and asks for the mark has it on the first <html> of its page, once, as the attribute the stylesheets ask for', str_contains( $marked, '<html data-nino-preview="loose" lang="de">' ) === true && substr_count( $marked, 'data-nino-preview' ) === 1 );
check( '...and without the ask, or with a word that is no word, the page carries nothing', str_contains( $markWith( $appData, 'nino-preview='. $token ), 'data-nino-preview' ) === false
	&& str_contains( $markWith( $appData, 'nino-preview='. $token. '&'. \Nino\Preview::MARK. '=x%22%20onload%3D1' ), 'data-nino-preview' ) === false );
$bare = $appData;
unset( $bare['./nino/preview/held'] );
check( '...and a request that holds no draft never has it, whatever its query says: the page of the visitors is not marked', str_contains( $markWith( $bare, $asked ), 'data-nino-preview' ) === false && str_contains( $markWith( $bare, 'nino-preview='. str_repeat( 'a', 32 ). '&'. $asked ), 'data-nino-preview' ) === false );

// The shape of a token is what keeps the folder of a draft from being any other: nothing that holds a slash, a dot or a letter of another case is one
check( 'a token is 32 digits of hex and nothing else: no path, no other length, no other case',
	preg_match( \Nino\Preview::TOKEN, $token ) === 1 && array_filter( [ str_repeat( 'a', 31 ), str_repeat( 'a', 33 ), '../'. str_repeat( 'a', 29 ), str_repeat( 'a', 31 ). "\n", str_repeat( 'A', 32 ), str_repeat( 'a', 31 ). '/' ],
	static fn( string $shape ): bool => preg_match( \Nino\Preview::TOKEN, $shape ) === 1 ) === [] );

// What may not have the draft, each by itself: the page is the visitors' and nothing says why
$refusals = [
	'no token' => static fn(): string => '',
	'a token nobody was given' => static fn(): string => bin2hex( random_bytes( 16 ) ),
	'a token that is a path' => static fn(): string => '../'. $token,
	'a token with its case changed' => static fn(): string => strtoupper( $token ),
];

foreach( $refusals as $what => $make ) {

	unset( $appData['./nino/preview/held'] );
	$ask( $appData, $make() );
	check( 'refused: '. $what, $GLOBALS['ninoHeld'] === null && \Nino\Preview::active( $appData ) === false );
}

// Another session: the same token, a session that was not given it
$other = $appData;
$other['./nino/uid'] = 'another-session';
unset( $other['./nino/preview/held'] );
$ask( $other, $token );
check( 'refused: a session that was not given the token', $GLOBALS['ninoHeld'] === null && \Nino\Preview::active( $other ) === false );

// Another account in the same session
\Nino\Auth::insertUser( $appData, 'second@example.com', 'correct horse battery staple', [ $perm ] );
\Nino\Auth::loginUser( $appData, 'second@example.com', 'correct horse battery staple' );
$ask( $appData, $token );
check( 'refused: another account signed in to the session', $GLOBALS['ninoHeld'] === null );
\Nino\Auth::loginUser( $appData, 'owner@example.com', 'correct horse battery staple' );

// The right taken away
$held = $appData['./nino/auth/current']['perms'];
$appData['./nino/auth/current']['perms'] = [];
$ask( $appData, $token );
check( 'refused: an account that has lost the right', $GLOBALS['ninoHeld'] === null );
$appData['./nino/auth/current']['perms'] = $held;

// Wrong kind
$request = ninoVisit( $appData, '/x?nino-preview='. $token );
check( 'refused: a draft of another kind', \Nino\Preview::held( $appData, $request, 'other', $perm ) === null );

// Run out: by the clock of the folder and by the clock of the session, each alone
$session = \Nino\Runtime::getSessionValue( $appData, '/nino/preview/probe', [] );
\Nino\Filesystem::putFileContent( $appData, '/data/.preview/probe/'. $token. '/meta.json', [ 'user' => 'owner@example.com', 'expires' => time() - 1 ] );
$ask( $appData, $token );
check( 'refused: a token whose time is out in its folder', $GLOBALS['ninoHeld'] === null );
\Nino\Filesystem::putFileContent( $appData, '/data/.preview/probe/'. $token. '/meta.json', [ 'user' => 'owner@example.com', 'expires' => time() + 60 ] );
\Nino\Runtime::setSessionValue( $appData, '/nino/preview/probe', [ $token => time() - 1 ] );
$ask( $appData, $token );
check( 'refused: a token whose time is out in the session - and its folder goes with the request that finds it out', $GLOBALS['ninoHeld'] === null && is_dir( $sandbox. '/private/data/.preview/probe/'. $token ) === false );
\Nino\Runtime::setSessionValue( $appData, '/nino/preview/probe', $session );

echo "\nPreview - the folders\n";

// A token that is renewed is the same, and the time is a new one
$token = \Nino\Preview::issue( $appData, 'probe', $perm, [ 'n' => 1 ] );
check( 'a token that is held is renewed, with the draft of the new call', \Nino\Preview::issue( $appData, 'probe', $perm, [ 'n' => 2 ], $token ) === $token && ( $ask( $appData, $token ) !== [] ? $GLOBALS['ninoHeld']['data'] === [ 'n' => 2 ] : false ) );
check( '...and a token that is not held is not taken for one to renew', \Nino\Preview::issue( $appData, 'probe', $perm, [], str_repeat( 'a', 32 ) ) !== str_repeat( 'a', 32 ) );

// The number is the session's, the oldest go with their folders
$tokens = [];
for( $i = 0; $i < \Nino\Preview::MAX + 3; $i++ )
	$tokens[] = \Nino\Preview::issue( $appData, 'probe', $perm, [ 'i' => $i ] );

$folders = array_map( 'basename', glob( $sandbox. '/private/data/.preview/probe/*', GLOB_ONLYDIR ) ?: [] );
check( 'a session holds at most MAX tokens of a kind, the newest, and the folders of the others are gone', count( $folders ) === \Nino\Preview::MAX && in_array( $tokens[0], $folders, true ) === false && in_array( end( $tokens ), $folders, true ) === true );

// A panel that draws many drafts at once says how many the session keeps, and is held to a limit
for( $i = 0; $i < 12; $i++ )
	\Nino\Preview::issue( $appData, 'tiles', $perm, [ 'i' => $i ], '', 8 );
$few = count( glob( $sandbox. '/private/data/.preview/tiles/*', GLOB_ONLYDIR ) ?: [] );
for( $i = 0; $i < \Nino\Preview::LIMIT + 3; $i++ )
	\Nino\Preview::issue( $appData, 'tiles', $perm, [ 'i' => $i ], '', 100000 );
check( 'the number a session keeps is the caller\'s, and never more than LIMIT', $few === 8 && count( glob( $sandbox. '/private/data/.preview/tiles/*', GLOB_ONLYDIR ) ?: [] ) === \Nino\Preview::LIMIT );

// Aufräumen: a folder whose time is out goes with the next issue, one that is not a draft stays
$stale = bin2hex( random_bytes( 16 ) );
\Nino\Filesystem::putFileContent( $appData, '/data/.preview/probe/'. $stale. '/meta.json', [ 'user' => 'x', 'expires' => time() - 5 ] );
mkdir( $sandbox. '/private/data/.preview/probe/not-a-token', 0755, true );
\Nino\Preview::issue( $appData, 'probe', $perm, [] );
check( 'a draft whose time is out is removed whenever one is issued; a folder that is no token is left as it is', is_dir( $sandbox. '/private/data/.preview/probe/'. $stale ) === false && is_dir( $sandbox. '/private/data/.preview/probe/not-a-token' ) === true );

echo "\nTemplate::swap() and the trace\n";

$token = \Nino\Preview::issue( $appData, 'probe', $perm, [] );
unset( $appData['./nino/preview/held'] );
$plain = $draw();

\Nino\Modules\Template::swap( $appData, '/templates/page-x', '[title "Entwurf"]' );
check( 'swap() does nothing for a request that holds no draft: the page is the file\'s, and carries no trace', $draw() === $plain && str_contains( $plain, '<!--' ) === false && str_contains( $plain, 'Hallo' ) === true );

$ask( $appData, $token );
\Nino\Modules\Template::swap( $appData, '/templates/page-x', '[box id="hero"][title text="Entwurf"][title ~fresh][/box]' );
$swapped = $draw();
check( 'a request that holds a draft has no trace until it asks for one', str_contains( $swapped, '<!--' ) === false );
\Nino\Preview::trace( $appData );
check( 'a request that holds a draft draws the source in the place of the file, with the words of the file\'s own category', str_contains( $swapped, '>Entwurf<' ) === true && str_contains( $swapped, '>Neu<' ) === true && str_contains( $swapped, '>Hallo<' ) === false );
check( 'the file, its tree and the page of a request without a draft are as they were',
	[ md5( (string) file_get_contents( $sandbox. '/private/templates/page-x.tpl' ) ), filemtime( $sandbox. '/private/templates/page-x.tpl' ) ] === $before );

$visitor = $appData;
unset( $visitor['./nino/preview/held'], $visitor['./nino/preview/trace'], $visitor['./nino/template/swapped'] );
check( 'a visitor, next, has the page of the file', \Nino\Html::renderHtml( $visitor, '[template /templates/page-x]' ) === $plain );

\Nino\Modules\Template::swap( $appData, '/templates/../page-x', '[title "x"]' );
\Nino\Modules\Template::swap( $appData, '/templates/Page-x', '[title "x"]' );
\Nino\Modules\Template::swap( $appData, '/templates/page-x.tpl.tpl', '[title "x"]' );
check( 'a name that is no template of the project\'s own is taken for none', array_keys( $appData['./nino/template/swapped'] ) === [ 'page-x' ] );

echo "\n";

// The trace: a comment around every call, around the value of a text, nowhere else
$comments = static function( string $html ): array {
	preg_match_all( '/<!--(.*?)-->/s', $html, $found );
	return $found[1];
};

$appData['./nino/template/swapped'] = [];
\Nino\Modules\Template::swap( $appData, '/templates/page-x', '[box id="hero"][title ~title][title text="Fest"][/box][rounds][title /template/page-x/hero/title][/rounds]' );
$traced = $draw();
$labels = $comments( $traced );
check( 'a call and the value of a text are written between comments that say where they stand', in_array( 'nino:n page-x:0', $labels, true ) === true && in_array( 'nino:n page-x:0.0', $labels, true ) === true
	&& in_array( 'nino:n page-x:0.1', $labels, true ) === true && in_array( 'nino:s ~title', $labels, true ) === true && in_array( 'nino:s text', $labels, true ) === true
	&& in_array( 'nino:s /template/page-x/hero/title', $labels, true ) === true );
check( 'the comments are balanced, each one closed by its own', preg_match_all( '/<!--nino:([ns]) /', $traced ) === preg_match_all( '/<!--\/nino:[ns]-->/', $traced ) );
check( 'the children a component draws round after round have the position they have in the tree, once for each round', substr_count( $traced, '<!--nino:n page-x:1.0-->' ) === 2 );
check( 'stripped of its comments the page is the page without them', preg_replace( '/<!--\/?nino:[ns][^>]*-->/', '', $traced ) === preg_replace( '/<!--\/?nino:[ns][^>]*-->/', '', $draw() ) );

// What may stand in a comment: the digits of a path, a word, a source that is a key - never what ends a comment. The sources
// are texts that exist - a call whose source reads nothing draws nothing, and nothing is traced - and so is each template name
$hostile = [ '~a-->b', '~a--b', '/x/y->z', '~"><script>', '~a<b', '/x/y--z' ];
$fills = [];

foreach( $hostile as $source )
	$fills[$source[0] === '~' ? '/template/page-x/hero/'. substr( $source, 1 ) : $source] = 'text';

\Nino\Html::addFills( $appData, $fills, '*' );
$appData['./nino/template/swapped'] = [];
$harmed = [];
$traced = 0;

foreach( [ 'page-x', 'a-->b', 'a--b', 'A b' ] as $template )
	foreach( $hostile as $source ) {

		$tree = [ [ 'n' => 'box', 's' => '', 'a' => [ 'id' => 'hero' ], 'c' => [ [ 'n' => 'title', 's' => $source, 'a' => [], 'c' => null ] ] ] ];
		$html = \Nino\Components::render( $appData, $tree, [ 'template' => $template, 'scope' => 'hero', 'path' => '' ] );

		foreach( $comments( $html ) as $comment ) {

			$traced++;

			if( preg_match( '/^\/?nino:[ns](?: (?:[a-z0-9-]+:)?[0-9]+(?:\.[0-9]+)*| (?:text|[~\/.][a-z0-9\/._-]*))?$/D', $comment ) !== 1 || str_contains( $comment, '--' ) === true )
				$harmed[] = $comment;
		}
	}

check( 'no source and no template, however it is written, puts a character into a comment that could end it or open another - and the calls are traced all the same', $harmed === [] && $traced > 0 );

// A hand-built context cannot ask for a trace of a request that holds no draft
$tree = [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => [] ] ];
\Nino\Preview::trace( $visitor );
check( 'a context that asks for a trace is not heard where the request holds no draft, and asking for the trace makes none', str_contains( \Nino\Components::render( $visitor, $tree, [ 'trace' => true, 'path' => '' ] ), '<!--' ) === false );

// Where a comment is none: inside a script, a style, a title, a textarea, in a tag, in the value a renderer is handed; no shortcode is traced
\Nino\Components::addComponent( $appData, 'tip', [ 'label' => 'Tip', 'in' => [ 'page' ], 'source' => 'text', 'template' => '<abbr title="[[value]]">[[value]]</abbr>' ] );
\Nino\Components::addComponent( $appData, 'raw', [ 'label' => 'Raw', 'in' => [ 'page' ], 'source' => 'text', 'template' => '<p>[[value]]</p>' ],
	static function( array &$appData, array $args, string $content, array $context ): string {
		return \Nino\Components::fill( $appData, 'raw', $args, $content, [] ). '|'. ( str_contains( $args['value'], '<!--' ) === true ? 'traced' : 'clean' );
	} );
\Nino\Components::addComponent( $appData, 'rawtip', [ 'label' => 'Raw tip', 'in' => [ 'page' ], 'source' => 'text', 'template' => '<p title="[[value]]">[[value]]</p>' ],
	static fn( array &$appData, array $args, string $content, array $context ): string => \Nino\Components::fill( $appData, 'rawtip', $args, $content, [] ) );
\Nino\Html::addShortcode( $appData, 'ldjson', static fn( array &$appData, array $args ): string => '{"name": "x"}', false );
\Nino\Html::addFills( $appData, [ '/template/page-x/hero/t' => 'Hallo' ], '*' );
$source = '[ldjson]<script type="application/ld+json">[ldjson]</script><script>[title ~t]</script><style>[title ~t]</style><title>[title ~t]</title><textarea>[title ~t]</textarea>'
	. '<a href="#" title="[title /template/page-x/hero/t]">x</a><a data-x="1>2" title="[title /template/page-x/hero/t]">x</a>[box id="hero"][tip ~t][title ~t][/box][raw /template/page-x/hero/t][rawtip /template/page-x/hero/t]';
$page = \Nino\Components::render( $appData, \Nino\Html::tree( $appData, $source ), [ 'template' => 'page-x' ] );
$stripped = (string) preg_replace( '/<!--\/?nino:[ns][^>]*-->/', '', $page );
check( 'no comment stands in a script, a style, a title, a textarea or a tag, and no shortcode is traced',
	preg_match( '#<(script|style|title|textarea)\b[^>]*>(?:(?!</\1>).)*<!--#is', $page ) !== 1 && preg_match( '/<[a-z][^<>]*<!--/i', $page ) !== 1 && str_contains( $page, '{"name": "x"}' ) === true
	&& preg_match( '#<!--nino:n[^>]*-->\{"name"#', $page ) !== 1 && substr_count( $page, '<!--nino:n' ) > 0 );
check( 'the value of a source is marked where the template writes it as the content of an element and nowhere else: not in a tag, never in what a renderer is handed',
	str_contains( $page, '<h2><!--nino:s ~t-->Hallo<!--/nino:s--></h2>' ) === true && str_contains( $page, '<abbr title="Hallo">Hallo</abbr>' ) === true && str_contains( $page, '<p title="Hallo">Hallo</p>' ) === true && str_contains( $page, 'title="<!--' ) === false && str_contains( $page, 'data-x="1>2" title="<h2>Hallo</h2>"' ) === true && str_contains( $page, '|clean' ) === true && str_contains( $page, '|traced' ) === false );
check( 'a component whose renderer has its template filled by fill() has its value marked there, and the renderer is handed the value as it is',
	str_contains( $page, '<p><!--nino:s /template/page-x/hero/t-->Hallo<!--/nino:s--></p>|clean' ) === true );
check( 'a context that says no is heard where it holds one', str_contains( \Nino\Components::render( $appData, $tree, [ 'trace' => false ] ), '<!--' ) === false );

ninoDone( $appData );
