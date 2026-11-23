<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	markup-link-smoke.php	Test of the two calls of the kernel that a template written in plain html needs: [link], an <a> to
 *											a page of the site that says whether it is the page that is shown, and the mark [else] of
 *											[imagevalues], which draws "the picture, else the name". Every pin is the html that a real request
 *											of a site in a sandbox draws (\Nino\request(): the routes, the texts of two languages, the menu,
 *											the slots of the pictures) or that a source draws on the app data of such a request, read as
 *											anchors - the attributes of each - and not by the wording of a source:
 *
 *											A. which link is the page that is shown: the one rule of [link] and [navigation], over every form
 *												 of address and of request, a site in a directory, two languages, the address as a key of a text
 *											B. a target that is no page of the site draws no link
 *											C. the attributes that are written and the ones that are not, the escaping, the content
 *											D. where a person can write a call, and what it is there
 *											E. [else] of [imagevalues]: the picture or the name, one call inside another, in a value,
 *												 outside, and without it as it was; the line of a logo for a light ground, a dark one and the name
 *
 *											The wizard is not run and no starter page is read: the site is the suite's own.
 *
 *	Usage: php tests/markup-link-smoke.php
 */

require __DIR__. '/harness.php';

$ignored = ninoWarnings();

/**
 *	The site of the suite on disk: two languages, the pages home, contact (a route for each language, the address of each page a key of a text),
 *	a page below the contact page and a 404 page, the menu 'main', the slots /logo and /logo/dark, and the one template every route draws, which
 *	holds the calls the pins of A read
 *
 *	@param		string		$dir					The directory the project is served from, '' for the root of a domain
 *
 *	@return 	string										The directory of the site
 */
function linkSite( string $dir ): string {

	$appData = ninoSandbox( 'markup-link' );
	$sandbox = ninoSandboxDir( $appData );

	foreach( [ '/private/templates', '/private/text', '/public/images' ] as $directory )
		mkdir( $sandbox. $directory, 0777, true );

	$page = [ 'body' => '[template /templates/page-probe]' ];

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/log'					=> false,
		'/nino/error/display'			=> true,
		'/nino/dir'								=> $dir,
		'/nino/locales/native'		=> 'de_DE',
		'/nino/locales/available'	=> [ 'de_DE', 'en_US' ],
		'/nino/modules'						=> [ '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Navigation', '\\Nino\\Modules\\Elements' ],
		'/nino/html/assets'				=> [],
		'/nino/html/navs'					=> [ 'main' ],
		'/nino/html/images'				=> [
			'/logo'			=> [ 'label' => 'Logo', 'width' => 500, 'height' => 100, 'filename' => 'logo.500x100.png', 'alt' => [ 'de_DE' => 'Logo [else] "A" <B>', 'en_US' => 'Logo' ] ],
			'/logo/dark'	=> [ 'label' => 'Dark', 'width' => 500, 'height' => 100, 'filename' => 'dark.500x100.png' ],
		],
		'/nino/http/routes'				=> [
			'GET://'								=> [ 'uri' => '/home', 'navs' => [ 'main' => 1 ] ] + $page,
			'GET://kontakt'					=> [ 'uri' => '/contact', 'locale' => 'de_DE', 'navs' => [ 'main' => 2 ] ] + $page,
			'GET://contact'					=> [ 'uri' => '/contact', 'locale' => 'en_US', 'navs' => [ 'main' => 2 ] ] + $page,
			'GET://kontakt/unter'		=> [ 'uri' => '/contact/child', 'locale' => 'de_DE', 'navs' => [ 'main' => 3 ] ] + $page,
			'GET://liste'						=> [ 'uri' => '/list', 'body' => '[template /templates/page-pager]' ],
			'GET://404'							=> [ 'uri' => '/404', 'statusCode' => 404 ] + $page,
		],
	] );

	foreach( [
		'de_DE' => [ 'Start', 'Kontakt', 'Unter', '/kontakt', '/kontakt/unter' ],
		'en_US' => [ 'Home', 'Contact', 'Below', '/contact', '/contact/below' ],
	] as $locale => [ $home, $contact, $child, $contactUri, $childUri ] )
		\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', [
			'[[/_nino/webpage/home/name]]'						=> $home,
			'[[/_nino/webpage/contact/name]]'					=> $contact,
			'[[/_nino/webpage/contact/child/name]]'		=> $child,
			'[[/_nino/webpage/home/uri]]'						=> '/',
			'[[/_nino/webpage/contact/uri]]'					=> $contactUri,
			'[[/_nino/webpage/contact/child/uri]]'		=> $childUri,
			'[[/_nino/webpage/query/uri]]'						=> '/kontakt?a=1&amp;b=2',
			'[[/project/company/general/name]]'			=> 'Muster [else] &amp; Söhne',
		] );

	// The page: eight links, the menu of the routes, and the lines of a menu written by hand - the same addresses as the links but the last, each section after a comment
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-probe.tpl', implode( "\n", [
		'<!--links-->',
		'[link class="home"]x[/link]',
		'[link /kontakt]x[/link]',
		'[link /kontakt/]x[/link]',
		'[link /Kontakt]x[/link]',
		'[link /kontakt?a=1&b=2]x[/link]',
		'[link /contact]x[/link]',
		'[link /kontakt/unter]x[/link]',
		'[link /_nino/webpage/contact/uri]x[/link]',
		'<!--menu-->',
		'[navigation nav="main"][/navigation]',
		'<!--lines-->',
		'[navigation]',
		'/:a',
		'/kontakt:b',
		'/kontakt/:c',
		'/Kontakt:d',
		'/kontakt?a=1&b=2:e',
		'/contact:f',
		'/kontakt/unter:g',
		'[/navigation]',
	] ) );

	// A pager: three pages of one list and the list itself, as links and as lines of a menu
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-pager.tpl', implode( "\n", [
		'<!--links-->',
		'[link /liste?page=1]1[/link]', '[link /liste?page=2]2[/link]', '[link /liste?page=3]3[/link]', '[link /liste#a]a[/link]', '[link /liste]all[/link]',
		'<!--menu-->',
		'<!--lines-->',
		'[navigation]', '/liste?page=1:1', '/liste?page=2:2', '/liste?page=3:3', '/liste#a:a', '/liste:all', '[/navigation]',
	] ) );

	return $sandbox;
}

/**
 *	A request's own app data on the site, booted as \Nino\init() boots it
 *
 *	@param		string		$sandbox
 *
 *	@return 	array
 */
function linkBoot( string $sandbox ): array {

	$_SESSION = [];

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );

	$appData['./nino/filesystem/path']				= $sandbox;
	$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';
	$appData['./nino/test/sandbox']						= $sandbox;

	\Nino\Filesystem::init( $appData );
	\Nino\AppData::init( $appData );
	\Nino\Locales::init( $appData );
	\Nino\Csrf::init( $appData );
	\Nino\Html::init( $appData );
	\Nino\Auth::init( $appData );
	\Nino\Modules::callModules( $appData, 'init' );

	return $appData;
}

/**
 *	One request of the site, the way a server hands it over
 *
 *	@param		string		$sandbox
 *	@param		string		$uri					With the directory the site is served from
 *	@param		string		$locale				The language the visitor had before the request
 *	@param		array|null	&$appData			(reference) The app data of the request, which a source is drawn on afterwards as the page was
 *
 *	@return 	string										The body of the response
 */
function linkVisit( string $sandbox, string $uri, string $locale = 'de_DE', ?array &$appData = null ): string {

	$appData = linkBoot( $sandbox );
	$appData['./nino/locales/current'] = $locale;
	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com' ] );

	return (string) ( $request['/nino/http/response']['body'] ?? '' );
}

/**
 *	A tag of a piece of html with the attributes it has, as a browser reads them: the entities of a value are taken back
 *
 *	@param		string		$html
 *	@param		string		$tag					'a', 'img'
 *
 *	@return 	array										[ [ 'attributes' => name => value, 'inner' => what is between the tags, '' for a tag that has none ], ... ] in the order they stand
 */
function linkTags( string $html, string $tag = 'a' ): array {

	preg_match_all( '#<'. $tag. '\b([^>]*)>(?:(.*?)</'. $tag. '>)?#s', $html, $found, PREG_SET_ORDER );

	return array_map( static function( array $one ): array {

		preg_match_all( '#\s([a-z-]+)(?:="([^"]*)")?(?=\s|$)#', $one[1], $attributes, PREG_SET_ORDER );

		$list = [];

		foreach( $attributes as $attribute )
			$list[$attribute[1]] = html_entity_decode( $attribute[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return [ 'attributes' => $list, 'inner' => $one[2] ?? '' ];
	}, $found );
}

/**
 *	The anchors of a piece of html as [ href, whether aria-current says it is the page that is shown ]
 *
 *	@param		string		$html
 *
 *	@return 	array
 */
function linkShown( string $html ): array {
	return array_map( static fn( array $anchor ): array => [ $anchor['attributes']['href'] ?? '', ( $anchor['attributes']['aria-current'] ?? '' ) === 'page' ], linkTags( $html ) );
}

/**
 *	The page of the probe in its three parts
 *
 *	@param		string		$html
 *
 *	@return 	array										[ 'links' => ..., 'menu' => ..., 'lines' => ... ], each as linkShown() has it
 */
function linkParts( string $html ): array {

	$parts = preg_split( '#<!--(?:links|menu|lines)-->#', $html );

	return [ 'links' => linkShown( (string) ( $parts[1] ?? '' ) ), 'menu' => linkShown( (string) ( $parts[2] ?? '' ) ), 'lines' => linkShown( (string) ( $parts[3] ?? '' ) ) ];
}

/**
 *	The addresses the anchors of a list mark as the page that is shown, each once
 *
 *	@param		array			$shown				As linkShown() has it
 *
 *	@return 	array
 */
function linkMarked( array $shown ): array {
	return array_values( array_unique( array_column( array_filter( $shown, static fn( array $one ): bool => $one[1] === true ), 0 ) ) );
}

/**
 *	Draw a source on the app data of a request, as a page is drawn
 *
 *	@param		array 		&$appData
 *	@param		string		$source
 *
 *	@return 	string
 */
function linkDraw( array &$appData, string $source ): string {
	return \Nino\Html::renderHtml( $appData, $source );
}

/**
 *	Put a text into the file of a language of the site
 *
 *	@param		array 		&$appData
 *	@param		string		$locale
 *	@param		array			$texts				[ '[[/key]]' => value ]
 *
 *	@return 	void
 */
function linkText( array &$appData, string $locale, array $texts ): void {
	\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', static fn( array $content ): array => $texts + $content );
}

$root = linkSite( '' );
$shop = linkSite( '/shop' );

echo "Sites: $root, $shop\n";

/*	A. Which link is the page that is shown	*/

echo "\nA. The page that is shown\n";

// Every form of a request against the eight links of the probe and the seven lines of a menu written by hand: the same seven addresses, the eighth link is the contact page again by the key of its text.
$forms = [
	[ '/', 'de_DE', [ '/' ] ],
	[ '/kontakt', 'de_DE', [ '/kontakt' ] ],
	[ '/kontakt/', 'de_DE', [ '/kontakt' ] ],
	[ '/kontakt?a=1&b=2', 'de_DE', [ '/kontakt' ] ],
	[ '/kontakt#top', 'de_DE', [ '/kontakt' ] ],
	[ '/Kontakt', 'de_DE', [ '/Kontakt' ] ],
	[ '/kontakt/unter', 'de_DE', [ '/kontakt/unter' ] ],
	[ '/contact', 'en_US', [ '/contact' ] ],
	[ '/nirgends', 'de_DE', [] ],
];

foreach( $forms as [ $uri, $locale, $marked ] ) {

	$parts = linkParts( linkVisit( $root, $uri, $locale ) );

	check( 'request '. $uri. ': the same addresses are marked by [link], by the lines of [navigation] and by its menu of the routes', linkMarked( $parts['links'] ) === linkMarked( $parts['lines'] ) && linkMarked( $parts['menu'] ) === array_values( array_intersect( linkMarked( $parts['lines'] ), array_column( $parts['menu'], 0 ) ) ) );
	check( 'request '. $uri. ': the marked addresses are '. implode( ' ', $marked ). ' - the path as the routes know it', linkMarked( $parts['links'] ) === $marked );
}

// A pager: the page is /liste, and of five links to it only the one with no query and no anchor is the page that is shown - on the request /liste?page=2, as on /liste
foreach( [ '/liste?page=2', '/liste', '/liste#a' ] as $uri ) {

	$parts = linkParts( linkVisit( $root, $uri ) );
	check( 'on the request '. $uri. ' of a pager one link is the page that is shown and no number of a page is: [link] and [navigation] say the same', $parts['links'] === [ [ '/liste?page=1', false ], [ '/liste?page=2', false ], [ '/liste?page=3', false ], [ '/liste#a', false ], [ '/liste', true ] ] && $parts['lines'] === $parts['links'] );
}

$parts = linkParts( linkVisit( $root, '/kontakt', 'de_DE' ) );
check( 'the page itself and no page above or below it is the page that is shown: the contact page is, the page below it and the one above are not',
	$parts['links'] === [ [ '/', false ], [ '/kontakt', true ], [ '/kontakt/', false ], [ '/Kontakt', false ], [ '/kontakt?a=1&b=2', false ], [ '/contact', false ], [ '/kontakt/unter', false ], [ '/kontakt', true ] ] );
check( '...the seven lines of the menu say the same of the same seven addresses, the one with a query included', $parts['lines'] === array_slice( $parts['links'], 0, 7 ) );

// Two languages: the address of the page is the text of a key, and the key is the page that is shown in the language of the request
$german = linkParts( linkVisit( $root, '/kontakt', 'de_DE' ) )['links'][7];
$english = linkParts( linkVisit( $root, '/contact', 'en_US' ) )['links'][7];
check( 'the key of a text is the address of a page in each language: de /kontakt, en /contact, and each is the page that is shown on its own address', $german === [ '/kontakt', true ] && $english === [ '/contact', true ] );
check( 'the link with no target, [link class="home"], goes to the root of the site and is the page that is shown on the start page in each language and on no other', linkParts( linkVisit( $root, '/', 'de_DE' ) )['links'][0] === [ '/', true ] && linkParts( linkVisit( $root, '/', 'en_US' ) )['links'][0] === [ '/', true ]
	&& linkParts( linkVisit( $root, '/kontakt', 'de_DE' ) )['links'][0] === [ '/', false ] && linkParts( linkVisit( $root, '/contact', 'en_US' ) )['links'][0] === [ '/', false ] && linkParts( linkVisit( $root, '/nirgends', 'de_DE' ) )['links'][0] === [ '/', false ] );
check( '...and in the language that is not the page\'s it is another address that is not shown', linkParts( linkVisit( $root, '/kontakt/unter', 'de_DE' ) )['links'][7] === [ '/kontakt', false ] && linkParts( linkVisit( $root, '/', 'en_US' ) )['links'][7] === [ '/contact', false ] );

// A site in a directory: the directory in front of every address, and the page that is shown found by its path from the root of the project
foreach( [ [ '/shop/kontakt', [ '/shop/kontakt' ] ], [ '/shop/kontakt/', [ '/shop/kontakt' ] ], [ '/shop', [ '/shop/' ] ], [ '/shop/', [ '/shop/' ] ], [ '/shop/Kontakt', [ '/shop/Kontakt' ] ] ] as [ $uri, $marked ] ) {

	$parts = linkParts( linkVisit( $shop, $uri ) );
	check( 'in the directory /shop the request '. $uri. ' marks '. ( $marked === [] ? 'nothing' : $marked[0] ). ', on [link] and [navigation] alike', linkMarked( $parts['links'] ) === $marked && linkMarked( $parts['lines'] ) === $marked );
}

$parts = linkParts( linkVisit( $shop, '/shop/kontakt' ) );
check( '...every address is written under the directory, once, and by the same rule as the lines of the menu: /shop/, /shop/kontakt, /shop/kontakt/unter',
	$parts['links'][0] === [ '/shop/', false ] && $parts['links'][1] === [ '/shop/kontakt', true ] && $parts['links'][6] === [ '/shop/kontakt/unter', false ] && array_column( $parts['lines'], 0 ) === array_column( array_slice( $parts['links'], 0, 7 ), 0 ) && $parts['links'][4] === [ '/shop/kontakt?a=1&b=2', false ] && $parts['lines'][4] === [ '/shop/kontakt?a=1&b=2', false ] );
$parts = linkParts( linkVisit( $shop, '/shop' ) );
check( '...and the root of the site in the directory is /shop/, written [link class="home"]: marked on the request for /shop and /shop/', $parts['links'][0] === [ '/shop/', true ] && linkParts( linkVisit( $shop, '/shop/' ) )['links'][0] === [ '/shop/', true ] );

// The address as it is written: & in a query is & for the browser and &amp; in the attribute
linkVisit( $root, '/kontakt', 'de_DE', $site );
linkVisit( $shop, '/shop/kontakt', 'de_DE', $shopSite );
$drawn = linkDraw( $site, '[link /_nino/webpage/query/uri]x[/link]' );
check( 'the address of a page that has a query in its text is that address, the page that is shown by its path: the attribute holds &amp; and the browser reads &', str_contains( $drawn, 'href="/kontakt?a=1&amp;b=2"' ) === true && linkShown( $drawn ) === [ [ '/kontakt?a=1&b=2', false ] ] );

// A query and an anchor behind the path, written in the source
$drawn = linkDraw( $site, '[link /kontakt#formular]x[/link][link /suche?q=x&r=y]y[/link][link /?q=x]z[/link][link /anders#a?b]w[/link]' );
check( 'a path with an anchor or a query is written with it, escaped, and is never the page that is shown', $drawn === '<a href="/kontakt#formular">x</a><a href="/suche?q=x&amp;r=y">y</a><a href="/?q=x">z</a><a href="/anders#a?b">w</a>' );
check( '...in a directory the directory stands in front of the path and the query behind it', linkDraw( $shopSite, '[link /kontakt?x=1#top]x[/link]' ) === '<a href="/shop/kontakt?x=1#top">x</a>' );

check( 'a link with no target is the link to the start page, with attributes or without', linkDraw( $site, '[link]x[/link]' ) === '<a href="/">x</a>' && linkDraw( $site, '[link class="brand" title="Home"]x[/link]' ) === '<a href="/" class="brand" title="Home">x</a>' );
check( '...a bare first word - a path without its slash, a name - is a target that is none and never the start page: the words stand without a link',
	linkDraw( $site, '[link brand]x[/link]' ) === 'x' && linkDraw( $site, '[link kontakt]x[/link]' ) === 'x' && linkDraw( $site, '[link kontakt class="a"]x[/link]' ) === 'x' && linkDraw( $site, '[link class]x[/link]' ) === 'x' );
check( '...a lone slash is no word of the notation: [link /] is a leaf, with the end [/link] a stray end, and [link / class="x"] is text, as for every name', linkDraw( $site, '[link /]x[/link]' ) === 'x' && linkDraw( $site, '[link / class="x"]x[/link]' ) === '[link / class="x"]x' );
check( '...and the key of the address of the start page is the root as well', linkDraw( $site, '[link /_nino/webpage/home/uri]x[/link]' ) === '<a href="/">x</a>' );
check( '...and a key that nobody wrote is written as the address it is, as a link of a component does', linkDraw( $site, '[link /_nino/webpage/nobody/uri]x[/link]' ) === '<a href="/_nino/webpage/nobody/uri">x</a>' );

/*	B. A target that is no page of the site	*/

echo "\nB. A target that is no page of the site\n";

// What cannot be written as the source of a call - a backslash, a line break, a scheme, a quote - is the text of a key, which a text file holds as it is
$bad = [
	'a second slash'				=> '//evil.example/x',
	'a slash and a backslash'		=> '/\\evil.example',
	'a backslash'					=> '/a\\b',
	'http'							=> 'http://evil.example/',
	'https'							=> 'https://evil.example/',
	'javascript'					=> 'javascript:alert(1)',
	'data'							=> 'data:text/html;base64,PHNjcmlwdD4=',
	'mailto'						=> 'mailto:a@example.test',
	'a control character'			=> "/a\x01b",
	'a line break'					=> "/a\nb",
	'a tab'							=> "/a\tb",
	'a null byte'					=> "/a\0b",
	'a blank'						=> '/a b',
	'a quote'						=> '/a"b',
	'a bracket'						=> '/a[b',
	'an angle bracket'				=> '/a<b',
	'a path that climbs'			=> '/a/../b',
	'a path that climbs, encoded'	=> '/a/%2e%2E/b',
	'a path of one dot'				=> '/a/./b',
	'a path that begins with one'	=> '/./x',
	'a path that makes // of /./'	=> '/.//evil.example',
	'a path of one dot, encoded'	=> '/a/%2E/b',
	'a path that climbs before a query'	=> '/..?x',
	'a path that climbs before an anchor'	=> '/a/..#b',
	'a path of one dot before a query'	=> '/.?x',
	'a path that ends in a dot'		=> '/a/.',
	'a colon before the next slash'	=> '/javascript:alert(1)//',
	'a path that is none'			=> 'kontakt',
	'an anchor'						=> '#top',
	'nothing'						=> '',
	'a path that is too long'		=> '/'. str_repeat( 'a', \Nino\Html::MAX_LINK_BYTES ),
];

foreach( $bad as $what => $target ) {

	linkText( $site, 'de_DE', [ '[[/_nino/webpage/bad/uri]]' => $target ] );
	$drawn = linkDraw( $site, '<p>[link /_nino/webpage/bad/uri class="c"]Words [[/_nino/webpage/contact/name]][/link]</p>' );

	check( 'a target that is '. $what. ' draws no link, and its words stay where they were', $drawn === '<p>Words Kontakt</p>' );
}

linkText( $site, 'de_DE', [ '[[/_nino/webpage/bad/uri]]' => " /kontakt\n" ] );
check( 'the text of a key with blanks and a line break around the path is the path', linkDraw( $site, '[link /_nino/webpage/bad/uri]x[/link]' ) === '<a href="/kontakt" aria-current="page">x</a>' );
$limit = '/'. str_repeat( 'a', \Nino\Html::MAX_LINK_BYTES - 1 );
linkText( $site, 'de_DE', [ '[[/_nino/webpage/bad/uri]]' => $limit ] );
check( 'the longest address that is one is a link', ( linkTags( linkDraw( $site, '[link /_nino/webpage/bad/uri]x[/link]' ) )[0]['attributes']['href'] ?? '' ) === $limit );

// What can be written as a source and is none
foreach( [
	'a second slash'				=> '[link //evil.example/x]Words[/link]',
	'a source of a field'			=> '[link .field]Words[/link]',
	'a source of a template'		=> '[link ~word]Words[/link]',
] as $what => $source )
	check( 'written as the source, '. $what. ' draws no link, and its words stay', linkDraw( $site, $source ) === 'Words' );

check( 'a scheme is no source and the mark is no call: it is the text it is', linkDraw( $site, '[link http://evil.example/]Words[/link]' ) === '[link http://evil.example/]Words' );
check( 'a link that has no words draws nothing, whatever its target: an <a> with nothing in it has nothing to tell', linkDraw( $site, '[link /kontakt][/link][link /kontakt] [/link]x' ) === 'x' );

// A link written wrong is said once for a request in the log - with the template it stands in, and with nothing a person could make long or strange
$said = static function( array &$app, string $source, string $template = 'page-probe' ): array {

	ninoWarnings();
	$context = \Nino\Html::context( $app );
	$context['template'] = $template;
	\Nino\Html::renderSource( $app, $source, $context );

	return ninoWarnings();
};
linkVisit( $root, '/kontakt', 'de_DE', $logged );
$one = $said( $logged, '[link //evil.example/x]x[/link][link //other]y[/link]' );
check( 'a link whose target is none says so once for the request, with the template and the target, and says how the start page is written', count( $one ) === 1 && str_contains( $one[0], '"page-probe"' ) === true && str_contains( $one[0], '//evil.example/x' ) === true && str_contains( $one[0], '[link]...[/link]' ) === true );

linkVisit( $root, '/kontakt', 'de_DE', $logged );
$one = $said( $logged, '[link /]x[/link]' );
check( '[link /], which is a leaf with no words, says that nothing is drawn and that the start page is [link]...[/link]', count( $one ) === 1 && str_contains( $one[0], 'no words' ) === true && str_contains( $one[0], '[link]...[/link]' ) === true );
linkVisit( $root, '/kontakt', 'de_DE', $logged );
check( '...and so does a link whose words draw nothing', count( $said( $logged, '[link /a][imagevalues /nirgends]x[/imagevalues][/link]' ) ) === 1 );

linkVisit( $root, '/kontakt', 'de_DE', $logged );
linkText( $logged, 'de_DE', [ '[[/_nino/webpage/bad/uri]]' => "/a\x01\x1b[31m". str_repeat( "\u{202e}x", 400 ) ] );
$one = $said( $logged, '[link /a[[/_nino/webpage/bad/uri]]]x[/link]', "page\nname\x07" );
check( 'what the log holds of a target is cut and masked: no control character and no long text a person typed, the template likewise', count( $one ) === 1 && preg_match( '/[^\x20-\x7E]/', $one[0] ) !== 1 && strlen( $one[0] ) < 420 );

linkVisit( $root, '/kontakt', 'de_DE', $logged );
check( 'a link that is right says nothing: a path, the key of a text, no target, with attributes, a query, in a link', $said( $logged, '[link /kontakt]x[/link][link /_nino/webpage/contact/uri]x[/link][link]x[/link][link class="b"]x[/link][link /a?b#c]x[/link][link /a]x[link /b]y[/link][/link]' ) === [] );

/*	C. Attributes, escaping, content	*/

echo "\nC. Attributes, escaping and content\n";

$allowed = [ 'class' => 'button big', 'id' => 'to-contact', 'title' => 'The contact page', 'rel' => 'nofollow', 'target' => '_blank', 'aria-label' => 'Contact', 'hreflang' => 'de' ];

foreach( $allowed as $name => $value ) {

	$anchor = linkTags( linkDraw( $site, '[link /anders '. $name. '="'. $value. '"]x[/link]' ) )[0]['attributes'] ?? [];
	$expected = [ 'href' => '/anders', $name => $value ] + ( $name === 'target' ? [ 'rel' => 'noopener' ] : [] );

	ksort( $expected );
	ksort( $anchor );
	check( 'the attribute '. $name. ' is written, with the address and nothing else', $anchor === $expected );
}

check( 'the attributes are written in the one order, whichever the call has them in, each once', linkDraw( $site, '[link /anders hreflang="de" aria-label="a" rel="nofollow" title="t" id="i" class="c" class="d"]x[/link]' ) === '<a href="/anders" class="d" id="i" title="t" rel="nofollow" aria-label="a" hreflang="de">x</a>' );

// What is not an attribute of the link
foreach( [ 'href="/other"', 'onclick="a()"', 'onmouseover="a()"', 'onfocus="a()"', 'style="color:red"', 'aria-current="page"', 'aria-current="false"', 'src="/x"', 'data-x="y"', 'name="n"', 'download', 'Class="Capital"', 'ID="x"' ] as $word )
	check( 'the word '. $word. ' is no attribute of a link: the anchor has its address and nothing else', ( linkTags( linkDraw( $site, '[link /anders '. $word. ']x[/link]' ) )[0]['attributes'] ?? [] ) === [ 'href' => '/anders' ] );

check( 'on the page that is shown aria-current is the rule\'s alone, whatever the call says', ( linkTags( linkDraw( $site, '[link /kontakt aria-current="false" class="c"]x[/link]' ) )[0]['attributes'] ?? [] ) === [ 'href' => '/kontakt', 'aria-current' => 'page', 'class' => 'c' ] );

foreach( [
	'no rel'								=> [ 'target="_blank"', 'noopener' ],
	'a rel without it'					=> [ 'target="_blank" rel="nofollow"', 'nofollow noopener' ],
	'a rel with it'						=> [ 'target="_blank" rel="author noopener"', 'author noopener' ],
	'a rel with it in capitals'			=> [ 'target="_blank" rel="NoOpener"', 'NoOpener' ],
	'a rel with a word that has it'		=> [ 'target="_blank" rel="noopenerx"', 'noopenerx noopener' ],
	'a rel of blanks'					=> [ 'target="_blank" rel="  "', 'noopener' ],
] as $what => [ $words, $rel ] ) {

	$anchor = linkTags( linkDraw( $site, '[link /anders '. $words. ']x[/link]' ) )[0]['attributes'] ?? [];
	check( 'target _blank with '. $what. ': rel is "'. $rel. '"', ( $anchor['rel'] ?? null ) === $rel && ( $anchor['target'] ?? null ) === '_blank' );
}

check( 'rel is divided into its words at any blank, a tab and a line break too: noopener between two of them is named', ( linkTags( linkDraw( $site, "[link /anders target=\"_blank\" rel=\"a\tnoopener\"]x[/link]" ) )[0]['attributes']['rel'] ?? null ) === "a\tnoopener" );

foreach( [ '_self', '_parent', '_top' ] as $target )
	check( 'the target '. $target. ' is written and no rel is put with it', ( linkTags( linkDraw( $site, '[link /anders target="'. $target. '"]x[/link]' ) )[0]['attributes'] ?? [] ) === [ 'href' => '/anders', 'target' => $target ] );

check( 'a target that is no word of the four - a name of a window, a capital, nothing - is no target; a blank attribute is not written', linkDraw( $site, '[link /anders target="mywindow"]x[/link][link /anders target="_BLANK"]x[/link][link /anders class=""]x[/link]' ) === '<a href="/anders">x</a><a href="/anders">x</a><a href="/anders">x</a>' );

// Escaping
$tricky = [ 'class' => 'a"b<c[d]e', 'title' => 'x"onmouseover="y', 'aria-label' => "a\"><script>alert(1)</script>" ];
$written = '';

foreach( $tricky as $name => $value )
	$written .= ' '. $name. '="'. str_replace( '"', '\\"', $value ). '"';

$drawn = linkDraw( $site, '[link /anders'. $written. ']x[/link]' );
$anchor = linkTags( $drawn )[0]['attributes'] ?? [];
check( 'a quote, an angle bracket and a bracket in a value stay in the value: three attributes besides the address, each with its text, and no tag that is not the anchor', array_keys( $anchor ) === [ 'href', 'class', 'title', 'aria-label' ]
	&& $anchor['class'] === 'a"b<c[d]e' && $anchor['title'] === 'x"onmouseover="y' && $anchor['aria-label'] === "a\"><script>alert(1)</script>" && substr_count( $drawn, '<' ) === 2 && str_contains( $drawn, '"onmouseover=' ) === false );

linkText( $site, 'de_DE', [ '[[/project/company/general/tag]]' => \Nino\Text::sanitizeValue( 'Say "hi" <b>x</b> [a] [link /z]y[/link] \'q\'', 'plain' ) ] );
$drawn = linkDraw( $site, '[link /anders title="[[/project/company/general/tag]]"]x[[/project/company/general/tag]][/link]' );
check( 'the text of a key in a value is the text, as it was written: its quotes are quotes, its brackets are brackets, and it is no call - no second link is drawn', count( linkTags( $drawn ) ) === 1
	&& ( linkTags( $drawn )[0]['attributes']['title'] ?? null ) === 'Say "hi" x [a] [link /z]y[/link] \'q\'' );

$drawn = linkDraw( $site, '[link /anders title="a &amp; b &quot;c&quot; &lt;d&gt;"]x[/link]' );
check( 'the entities of a value are read, and the value is escaped once: nothing is written twice', ( linkTags( $drawn )[0]['attributes']['title'] ?? null ) === 'a & b "c" <d>' && str_contains( $drawn, 'title="a &amp; b &quot;c&quot; &lt;d&gt;"' ) === true );

// The words of a link: html+ as in any template
$drawn = linkDraw( $site, '[link /kontakt]<span class="n">[[/project/company/general/name]]</span> [[/_nino/webpage/contact/name]][/link]' );
check( 'the words of a link are html+: a tag, a fill, a text of the site', ( linkTags( $drawn )[0]['inner'] ?? null ) === '<span class="n">Muster [else] &amp; Söhne</span> Kontakt' );

// A link in a link
$drawn = linkDraw( $site, '[link /a class="outer"]one [link /b class="inner"]two[/link] three[/link] [link /c]four[/link]' );
check( 'a link inside a link draws its words and no <a>; a link after the outer one is a link again', array_column( linkTags( $drawn ), 'attributes' ) === [ [ 'href' => '/a', 'class' => 'outer' ], [ 'href' => '/c' ] ] && linkTags( $drawn )[0]['inner'] === 'one two three' );
\Nino\Filesystem::putFileContent( $site, '/templates/page-nest.tpl', '[link /inner]in[/link] [link /second class="s"]more[/link]' );
check( '...also where the inner one stands in a template that the outer one draws', linkDraw( $site, '[link /a]x [template /templates/page-nest][/link][link /b]y[/link]' ) === '<a href="/a">x in more</a><a href="/b">y</a>' );

// Round a picture
$drawn = linkDraw( $site, '[link /kontakt class="brand"][imagevalues /logo]<img src="[[src]]" alt="[[alt]]">[/imagevalues][/link]' );
check( 'a link round a picture is an anchor with the picture in it', ( linkTags( $drawn )[0]['inner'] ?? null ) === '<img src="/public/images/logo.500x100.png" alt="Logo [else] &quot;A&quot; &lt;B&gt;">' );
check( '...and round a picture that is not there it is nothing at all, no empty <a>', linkDraw( $site, '[link /kontakt][imagevalues /nirgends]<img src="[[src]]">[/imagevalues][/link]' ) === '' );

// A token of a loop in the address: a part of it, never a way to another host
$context = \Nino\Html::context( $site );
$context['fills'] = [ '[[slug]]' => 'ein-beitrag', '[[evil]]' => '/evil.example', '[[quoted]]' => 'a&quot;b', '[[amp]]' => 'Tom &amp; Jerry', '[[tag]]' => '<b>' ];
check( 'a token in the address is put in: the slug of an element is a page of the site', \Nino\Html::renderSource( $site, '[link /blog/[[slug]]]x[/link]', $context ) === '<a href="/blog/ein-beitrag">x</a>' );
check( 'the words of a link are drawn in the context of the call: the token of a loop is its text', \Nino\Html::renderSource( $site, '[link /a]<b>[[slug]]</b>[/link]', $context ) === '<a href="/a"><b>ein-beitrag</b></a>' );
check( '...one that begins with a slash is no second host: a second slash is no target; and a quote, a blank or a tag in one is no address', \Nino\Html::renderSource( $site, '[link /[[evil]]]x[/link]', $context ) === 'x'
	&& \Nino\Html::renderSource( $site, '[link /a/[[quoted]]]x[/link]', $context ) === 'x' && \Nino\Html::renderSource( $site, '[link /a/[[amp]]]x[/link]', $context ) === 'x' && \Nino\Html::renderSource( $site, '[link /a/[[tag]]]x[/link]', $context ) === 'x' );
check( '...and in the value of an attribute a token is escaped as any value is', \Nino\Html::renderSource( $site, '[link /a title="[[amp]] [[quoted]] [[tag]]"]x[/link]', $context ) === '<a href="/a" title="Tom &amp; Jerry a&quot;b &lt;b&gt;">x</a>' );

/*	D. Where a person writes a call	*/

echo "\nD. Where a person writes a call\n";

// 1. A text of the site: saved by the Text panel, or written into the file of a language
$saved = \Nino\Text::sanitizeValue( '[link /kontakt]Kontakt[/link]', 'inline' );
check( 'a text saved in the Text panel keeps no bracket of a call: each is an entity', str_contains( $saved, '[' ) === false && str_contains( $saved, ']' ) === false );
linkText( $site, 'de_DE', [ '[[/template/page-probe/edit/saved]]' => $saved, '[[/template/page-probe/edit/written]]' => '[link /kontakt]Kontakt[/link] [link //evil.example]x[/link]' ] );
$drawn = linkDraw( $site, '<p>[[/template/page-probe/edit/saved]]</p><p>[[/template/page-probe/edit/written]]</p>' );
check( 'a text of the site is put into a page as it is and read by nobody: a call in it, saved or written into the file, is the text it is and draws no link', linkTags( $drawn ) === [] && str_contains( $drawn, '[link /kontakt]Kontakt[/link] [link //evil.example]x[/link]' ) === true );

// 2. The fields of an element: a token, which is the text of the field
\Nino\Elements::insertElementType( $site, '/probe', [ 'title' => [ 'type' => 'string' ], 'body' => [ 'type' => 'string', 'html' => true, 'blocks' => true ] ] );
\Nino\Elements::insertElement( $site, '/probe/eins', [ 'title' => '[link /kontakt]T[/link]', 'body' => '<p>[link /kontakt]B[/link] <a href="/kontakt">own</a></p>' ], '*' );
$drawn = linkDraw( $site, '[elements /probe]<h2>[[title]]</h2>[[body]]<i title="[[title]]">[link /x]c[/link]</i>[/elements]' );
check( 'the field of an element is a token and is read by nobody: a call in it is the text it is; only the call of the template is a link', array_column( linkTags( $drawn ), 'attributes' ) === [ [ 'href' => '/kontakt' ], [ 'href' => '/x' ] ]
	&& str_contains( $drawn, '<a href="/kontakt">own</a>' ) === true && linkTags( $drawn )[1]['inner'] === 'c' );

// 3. A block of the Builder or of a package: the register finds it as a call that has no schema, which a block does not take
$nodes = \Nino\Markup::parse( '[link /kontakt]x[/link]', \Nino\Components::containers( $site ) );
$found = \Nino\Components::check( $site, $nodes );
check( 'a tree of components with a [link] in it has a finding of the register - a call with no schema: a block that holds it is refused', ( $found['0'][0]['code'] ?? '' ) === \Nino\Components::CODE_BARE && ( $found['0'][0]['detail'] ?? '' ) === 'link' );

// 4. Html that is cleaned - the field of a format with tags, a call that holds markup: what is left of an <a> is its address
$kept = \Nino\Html::sanitizeHtml( linkDraw( $site, '[link /kontakt class="b" target="_blank" aria-label="x" title="t"]x[/link][link //evil.example]y[/link]' ), 'blocks', true );
check( 'where html is cleaned the anchor of a link is the anchor of any html: the address, and neither aria-current nor a class, a target, a title', $kept === '<p><a href="/kontakt">x</a>y</p>' );

// 5. A template: the page of the probe itself, written in the file of a template - what a person who may write one writes is what is drawn, and it is no more than an <a href> is
check( 'in a template file, which is the author\'s and drawn as written, a call of [link] draws what it draws on every request above', count( linkParts( linkVisit( $root, '/kontakt' ) )['links'] ) === 8 );


/*	E. [else] of [imagevalues]	*/

echo "\nE. [else] of [imagevalues]\n";

linkVisit( $root, '/kontakt', 'de_DE', $site );

// Outside a call: what it always was
linkVisit( $root, '/kontakt', 'de_DE', $fresh );
$fresh['/nino/html/images']['/logo']['filename'] = null;
ninoWarnings();
$divided = linkDraw( $fresh, '[imagevalues /logo]A[else]B[/imagevalues]' );
check( 'the mark is no call of the part after it: it is not drawn and the log has nothing to say of it', $divided === 'B' && ninoWarnings() === [] );
$outside = linkDraw( $fresh, 'a [else] b' ). '|'. linkDraw( $fresh, 'c [else]d[/else] e' );
$said = ninoWarnings();
check( 'outside [imagevalues] [else] is a call nobody registered: it draws nothing, nor does what it holds, and the log says so once', $outside === 'a  b|c  e' && count( $said ) === 1 && str_contains( $said[0], '"else"' ) === true );

// The files of the slots of the picture of this request: the way an upload and a removal leave them
$withSlots = static function( array $files ) use ( &$site ): void {

	foreach( [ '/logo', '/logo/dark' ] as $slot )
		$site['/nino/html/images'][$slot]['filename'] = $files[$slot] ?? null;
};
$both = [ '/logo' => 'logo.500x100.png', '/logo/dark' => 'dark.500x100.png' ];
$line = '[imagevalues /logo]<img src="[[src]]" alt="[[alt]]" width="[[width]]" height="[[height]]">[else][[/project/company/general/name]][/imagevalues]';

$withSlots( $both );
$drawn = linkDraw( $site, $line );
check( 'with a picture the part before [else] is drawn with the values of the picture, and the part after it is not', $drawn === '<img src="/public/images/logo.500x100.png" alt="Logo [else] &quot;A&quot; &lt;B&gt;" width="500" height="100">' );
check( '...the alt text that holds [else] is a value, and not a mark: nothing is divided by it', linkTags( $drawn, 'img' )[0]['attributes']['alt'] === 'Logo [else] "A" <B>' );

$withSlots( [] );
check( 'without a picture the part after [else] is drawn, as html+ of the page: a fill is its text', linkDraw( $site, $line ) === 'Muster [else] &amp; Söhne' );
check( '...a text that holds [else] is a text: it is put in as it is, and no mark', substr_count( linkDraw( $site, $line ), '[else]' ) === 1 );
check( '...without the values of a picture: the tokens of one stay written, and a call in the part is a call', linkDraw( $site, '[imagevalues /logo]<img src="[[src]]">[else]<b class="[[src]]">[[alt]] [[url]]</b> [link /x]y[/link][/imagevalues]' ) === '<b class="[[src]]">[[alt]] [[url]]</b> <a href="/x">y</a>' );

check( 'a part that is empty is nothing: no picture and nothing after the mark, or the picture and nothing before it', linkDraw( $site, '[imagevalues /logo]X[else][/imagevalues]' ) === '' && linkDraw( $site, '[imagevalues /logo][else]Y[/imagevalues]' ) === 'Y' );
$withSlots( $both );
check( '...and the other way round', linkDraw( $site, '[imagevalues /logo]X[else][/imagevalues]' ) === 'X' && linkDraw( $site, '[imagevalues /logo][else]Y[/imagevalues]' ) === '' );

// Which [else] is the mark
$withSlots( [] );
check( 'the first [else] of the top level divides: another one is a call nobody registered, which draws nothing', linkDraw( $site, '[imagevalues /logo]A[else]B[else]C[/imagevalues]' ) === 'BC' );
check( 'a call named else with a source, with an attribute or with an end is no mark', linkDraw( $site, '[imagevalues /logo]A[else /x]B[/imagevalues]' ) === '' && linkDraw( $site, '[imagevalues /logo]A[else x="y"]B[/imagevalues]' ) === '' && linkDraw( $site, '[imagevalues /logo]A[else]B[/else]C[/imagevalues]' ) === '' );
check( 'one that is written with a backslash is the text [else]: no mark, and with no picture the call draws nothing', linkDraw( $site, '[imagevalues /logo]A\\[else]B[/imagevalues]' ) === '' );
$withSlots( $both );
check( '...and with a picture it is a part of what is drawn', linkDraw( $site, '[imagevalues /logo]A\\[else]B[/imagevalues]' ) === 'A[else]B' );

// One call inside another: a mark belongs to the call it stands in
$nested = '[imagevalues /logo]L[imagevalues /logo/dark]D[else]d[/imagevalues][else]N[imagevalues /logo/dark]D2[else]n[/imagevalues][/imagevalues]';

foreach( [
	'both pictures'			=> [ $both, 'LD' ],
	'the light one'			=> [ [ '/logo' => 'logo.500x100.png' ], 'Ld' ],
	'the dark one'			=> [ [ '/logo/dark' => 'dark.500x100.png' ], 'ND2' ],
	'no picture'				=> [ [], 'Nn' ],
] as $what => [ $files, $want ] ) {

	$withSlots( $files );
	check( 'a mark is the one of the call it stands in, an inner call has its own: with '. $what. ' the page has '. $want, linkDraw( $site, $nested ) === $want );
}

// In a value
$withSlots( [] );
check( 'a mark in the value of a call is no mark: it is in the value, or it is not drawn at all', linkDraw( $site, '[imagevalues /logo]A[link /x title="[else]"]b[/link][else]N[/imagevalues]' ) === 'N' );
$withSlots( $both );
check( '...and with a picture the value is written as it is', linkDraw( $site, '[imagevalues /logo]A[link /x title="[else]"]b[/link][else]N[/imagevalues]' ) === 'A<a href="/x" title="[else]">b</a>' );

// Without a mark: the page as it was
$sources = [
	'[imagevalues /logo]<img src="[[src]]" width="[[width]]" height="[[height]]">[/imagevalues]',
	'[imagevalues /logo]<meta content="[[url]]">[/imagevalues]',
	'[imagevalues /logo]<img alt="[[alt]]">[/imagevalues]',
	'[imagevalues /nirgends]<img src="[[src]]">[/imagevalues]',
	'[imagevalues /logo][/imagevalues]',
	'[imagevalues /logo]a [[/project/company/general/name]] [csrf][/imagevalues]',
];
$withSlots( $both );
$with = array_map( static fn( string $source ): string => linkDraw( $site, $source ), $sources );
check( 'a call with no mark draws as it always drew: with a picture, byte for byte', $with === [
	'<img src="/public/images/logo.500x100.png" width="500" height="100">',
	'<meta content="/public/images/logo.500x100.png">',
	'<img alt="Logo [else] &quot;A&quot; &lt;B&gt;">',
	'',
	'',
	'a Muster [else] &amp; Söhne ',
] );
$withSlots( [] );
check( '...and without a picture it draws nothing', array_map( static fn( string $source ): string => linkDraw( $site, $source ), $sources ) === [ '', '', '', '', '', '' ] );

// The line for a light logo, a dark one, and else the name
echo "\nThe line of a logo for a light ground, a dark one, and else the name\n";

$line = '[imagevalues /logo]<img class="light" src="[[src]]">[imagevalues /logo/dark]<img class="dark" src="[[src]]">[/imagevalues][else][imagevalues /logo/dark]<img class="dark" src="[[src]]">[else][[/project/company/general/name]][/imagevalues][/imagevalues]';

foreach( [
	'both'			=> [ $both, '<img class="light" src="/public/images/logo.500x100.png"><img class="dark" src="/public/images/dark.500x100.png">' ],
	'light only'		=> [ [ '/logo' => 'logo.500x100.png' ], '<img class="light" src="/public/images/logo.500x100.png">' ],
	'dark only'		=> [ [ '/logo/dark' => 'dark.500x100.png' ], '<img class="dark" src="/public/images/dark.500x100.png">' ],
	'none'			=> [ [], 'Muster [else] &amp; Söhne' ],
] as $what => [ $files, $want ] ) {

	$withSlots( $files );
	$drawn = linkDraw( $site, $line );
	check( 'the logo of a light ground, of a dark one, and else the name: '. $what, $drawn === $want );
	echo '    '. $what. ': '. $drawn. "\n";
}

$withSlots( [] );
check( 'in a link the name stands where there is no picture', linkDraw( $site, '[link /_nino/webpage/home/uri class="brand"]'. $line. '[/link]' ) === '<a href="/" class="brand">Muster [else] &amp; Söhne</a>' );
$withSlots( $both );
check( '...and the pictures where there are some', count( linkTags( linkDraw( $site, '[link /_nino/webpage/home/uri class="brand"]'. $line. '[/link]' ), 'img' ) ) === 2 );

echo "\nF. The parser: a lone slash, the characters of a source, and what the calls do with a source that names nothing\n";

$read = static function( string $source ): array {
	$notes = [];
	return [ \Nino\Markup::parse( $source, [ 'a' => true ], $notes ), array_column( $notes, 'code' ) ];
};
$call = static fn( string $source ): array => array_map( static fn( array $node ): array => [ $node['n'] ?? 't', $node['s'] ?? $node['t'], $node['a'] ?? null, $node['c'] ?? null ], $read( $source )[0] );

check( 'a / with a word behind it is no source: the mark is the text it is, for every name', $call( '[a / class="x"]b[/a]' )[0] === [ 't', '[a / class="x"]b', null, null ] && $call( '[a / b]' ) === [ [ 't', '[a / b]', null, null ] ] && $call( '[link / title="x"]' ) === [ [ 't', '[link / title="x"]', null, null ] ] && $call( '[a / size=5]' )[0][0] === 't' );
check( 'a / with nothing behind it is the end of a leaf, as it is for every name: [a /] has no source and the end that follows is a stray end', $call( '[a /]b[/a]' ) === [ [ 'a', '', [], null ], [ 't', 'b', null, null ] ] && $read( '[a /]b[/a]' )[1] === [ 'stray-end' ] && $call( '[a / ]' ) === [ [ 'a', '', [], null ] ] );
check( '...a / behind a word or between two is no word, and a source behind a word is a switch', $call( '[a x / y]' ) === [ [ 't', '[a x / y]', null, null ] ] && $call( '[a / /]' ) === [ [ 't', '[a / /]', null, null ] ] && $call( '[a x /y /]' ) === [ [ 'a', '', [ 'x' => true, '/y' => true ], null ] ] );

check( 'the characters of a query and an anchor are characters of a source: ? # & = % +', $call( '[a /x?y=1&z=%20+b#t]' )[0][1] === '/x?y=1&z=%20+b#t' && $call( '[a ~k?x]' )[0][1] === '~k?x' && $call( '[a /x;y]' )[0][0] === 't' && $call( '[a /x"y]' )[0][0] === 't' );
check( '...and no other: an angle bracket, an apostrophe, a bracket, a backslash, a blank, a comma end the source - the mark is text', $call( '[x /a<b]' )[0][0] === 't' && $call( '[x /a>b]' )[0][0] === 't' && $call( "[x /a'b]" )[0][0] === 't' && $call( '[x /a(b]' )[0][0] === 't' && $call( '[x /a,b]' )[0][0] === 't' && $call( '[x /a\\b]' )[0][0] === 't' );
check( 'a call with such a source is written as it was read, and read again as the same', \Nino\Markup::write( $read( '[a /x?y=1&z#t class="c"]b[/a]' )[0], [ 'a' => true ] ) === '[a /x?y=1&z#t class="c"]b[/a]' );

// What the calls of the kernel do with a source that names nothing: no template, frame, picture, loop, element, text or menu
\Nino\Components::addComponent( $site, 'link-probe-text', [ 'label' => 'T', 'in' => [ 'page' ], 'source' => 'text', 'template' => '<p>[[value]]</p>' ] );
ninoWarnings();
check( 'a source with a query or an anchor names what it would without it and nothing else: a template with a ? is no template, and the real one is drawn', linkDraw( $site, '[template /templates/page-nest?x]' ) === '' && linkDraw( $site, '[template /templates/page-nest#x]' ) === '' && linkDraw( $site, '[template /templates/page-nest]' ) !== ''
	&& array_unique( array_map( static fn( string $source ): string => linkDraw( $site, $source ), [ '[frame /templates/frame-header?x]', '[imagevalues /logo?x]Z[/imagevalues]', '[elements /probe?x]Z[/elements]', '[element /probe/eins?x]Z[/element]', '[navigation /x?y]', '[link-probe-text /x?y]' ] ) ) === [ '' ] );
ninoWarnings();

$ignored = ninoWarnings();
\Nino\Filesystem::removeDir( $shop );
\Nino\Filesystem::removeDir( $root );
ninoDone( $site );
