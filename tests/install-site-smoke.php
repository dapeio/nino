<?php
declare(strict_types=1);

/**
 *	Nino
 *	install-site-smoke.php	Test of the site the wizard writes: a fresh project set up in ONE language - German once, English once - through the
 *												steps (Setup::apiApply with a list of one language, StarterPages::apply) and then visited with real requests. The starter
 *												site is plain html, drawn by the kernel alone: every page answers 200 with one h1 and one main part in the language of the
 *												site, the menu marks the page that is shown, no template calls a component of Basis, Basis is off, the log has nothing to
 *												say, the legal check finds nothing missing, and the style bundle is the reset, the form and the site's own stylesheet.
 *												A site that an application sets up with its own start page is drawn without header and footer, with nothing in the log.
 *
 *	Usage: php tests/install-site-smoke.php
 */

define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-site-features-'. getmypid() );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	@rmdir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

$ignored = ninoWarnings();

/**
 *	A project the wizard set up in one language - the Setup step and the starter pages of the last one, and the words of the company filled in
 *
 *	@param		string		$locale				The one language
 *	@param		bool			$own					Whether an application provides the start page: no header, no footer, no starter page but the 404
 *	@param		string		$name					The name of the company, which may be empty
 *
 *	@return 	string										The directory of the project
 */
function siteProject( string $locale, bool $own, string $name = 'Muster & Söhne GmbH' ): string {

	$appData = ninoSandbox( 'install-site' );
	$sandbox = ninoSandboxDir( $appData );
	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

	foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $directory )
		mkdir( $sandbox. $directory, 0777, true );

	$appData['/nino/html/navs'] = \Nino\AppData::DEFAULTS['/nino/html/navs'];
	$appData['/nino/locales/available'] = [ $locale ];
	$appData['/nino/locales/native'] = $locale;
	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/locales/native' => $locale, '/nino/locales/available' => [ $locale ], '/nino/modules' => [], '/nino/html/assets' => [], '/nino/http/routes' => [], '/nino/error/log' => false,
	] );

	[ $status, $body ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ $locale ], 'native' => $locale, 'modules' => [] ] );

	if( $status !== 200 )
		throw new \RuntimeException( 'Setup::apiApply: '. $status. ' '. json_encode( $body ) );

	$applied = \Nino\Install\StarterPages::apply( $appData, $own );

	if( $applied !== true )
		throw new \RuntimeException( 'StarterPages::apply: '. $applied );

	foreach( [ '[[/project/company/general/name]]' => $name, '[[/project/company/contact/email]]' => 'kontakt@example.com', '[[/project/website/general/url]]' => 'example.com' ] as $key => $value )
		\Nino\Features::mergeText( $appData, '/text/global.php', [ $key => \Nino\Text::sanitizeValue( $value, 'plain' ) ], true );

	return $sandbox;
}

/**
 *	A request's own app data on the project, booted as \Nino\init() boots it
 *
 *	@param		string		$sandbox
 *
 *	@return 	array
 */
function siteBoot( string $sandbox ): array {

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
	$appData['/nino/dir'] = '';
	\Nino\Locales::init( $appData );
	\Nino\Csrf::init( $appData );
	\Nino\Html::init( $appData );
	\Nino\Auth::init( $appData );
	\Nino\Features::registerAssets( $appData );
	\Nino\Features::registerComponents( $appData );
	\Nino\Modules::callModules( $appData, 'init' );
	\Nino\Features::registerParts( $appData );

	return $appData;
}

/**
 *	One page of the project as a visitor gets it
 *
 *	@param		string		$sandbox
 *	@param		string		$uri
 *
 *	@return 	array										[ status, body ]
 */
function siteVisit( string $sandbox, string $uri ): array {

	$appData = siteBoot( $sandbox );
	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );

	return [ (int) ( $request['/nino/http/response']['statusCode'] ?? 0 ), (string) ( $request['/nino/http/response']['body'] ?? '' ) ];
}

$basisComponents = array_map( 'basename', glob( dirname( __DIR__ ). '/_nino/Nino/Modules/Basis/components/*', GLOB_ONLYDIR ) ?: [] );

foreach( [ 'de_DE' => [ 'de', [ '/', '/contact', '/impressum', '/datenschutz' ], '/imprint' ], 'en_US' => [ 'en', [ '/', '/contact', '/imprint', '/privacy' ], '/impressum' ] ] as $locale => [ $tag, $uris, $otherLanguage ] ) {

	echo $locale. ": a fresh project in one language\n";

	$site = siteProject( $locale, false );
	$config = include $site. '/private/config.php';
	ninoWarnings();

	check( 'the project has the one language, native, and the starter pages Start, Contact and 404 as the only pages', $config['/nino/locales/available'] === [ $locale ] && $config['/nino/locales/native'] === $locale
		&& array_keys( array_filter( $config['/nino/http/routes'], static fn( mixed $route, string $key ): bool => str_starts_with( $key, 'GET://' ) === true && str_contains( (string) ( $route['body'] ?? '' ), '[template /templates/page-' ) === true && str_contains( (string) $route['body'], 'page-legal' ) === false, ARRAY_FILTER_USE_BOTH ) ) === [ 'GET://', 'GET://contact', 'GET://404' ] );

	$visits = [];
	foreach( $uris as $uri )
		$visits[$uri] = siteVisit( $site, $uri );
	$missing = siteVisit( $site, '/nowhere-'. $tag );
	$other = siteVisit( $site, $otherLanguage );

	check( 'every page answers 200 - Start, Contact, the imprint and the privacy policy - and an address that is not there answers 404, with a page of its own', array_column( $visits, 0 ) === [ 200, 200, 200, 200 ] && $missing[0] === 404 && str_contains( $missing[1], '<main' ) === true );
	check( '...the language the project does not have has no page: the address of the imprint in it is not there', $other[0] === 404 );

	$problems = [];
	foreach( $visits + [ '404' => $missing ] as $label => [ , $body ] ) {

		if( substr_count( $body, '<h1' ) !== 1 )
			$problems[] = $label. ': h1 x'. substr_count( $body, '<h1' );

		if( substr_count( $body, '<main' ) !== 1 )
			$problems[] = $label. ': main x'. substr_count( $body, '<main' );

		if( str_contains( $body, '<html lang="'. $tag. '">' ) === false )
			$problems[] = $label. ': lang';

		if( substr_count( $body, '<header' ) !== 1 || substr_count( $body, '<footer' ) !== 1 )
			$problems[] = $label. ': header or footer';

		if( preg_match( '/\[\[|\[(?:template|link|imagevalues|else|csrf)[ \]]/', (string) preg_replace( '/<!--.*?-->/s', '', $body ) ) === 1 )
			$problems[] = $label. ': a call or a fill left over';
	}
	check( 'each page has exactly one h1, one main part, one header and one footer, says the language of the project in <html lang>, and has no call or fill left over'. ( $problems === [] ? '' : ' - '. implode( '; ', $problems ) ), $problems === [] );

	$marked = [];
	foreach( $visits + [ '404' => $missing ] as $label => [ , $body ] ) {

		preg_match( '#<nav class="site-nav".*?</nav>#s', $body, $headerNav );
		preg_match_all( '#<a href="([^"]*)" aria-current="page"#', $headerNav[0] ?? '', $found );
		$marked[$label] = $found[1];
	}
	check( 'the header marks the page that is shown: Start on /, Contact on /contact, and none on the pages that are in no menu', $marked['/'] === [ '/' ] && $marked['/contact'] === [ '/contact' ]
		&& array_filter( $marked, static fn( array $links, string $label ): bool => in_array( $label, [ '/', '/contact' ], true ) === false && $links !== [], ARRAY_FILTER_USE_BOTH ) === [] );

	$footer = [];
	preg_match_all( '#<a href="([^"]*)"#', (string) ( preg_match( '#<footer class="site-footer">.*</footer>#s', $visits['/'][1], $footerHtml ) === 1 ? $footerHtml[0] : '' ), $footer );
	check( 'the footer links the start page, the contact page, the imprint and the privacy policy by the addresses the project has them at', $footer[1] === [ '/', '/contact', $uris[2], $uris[3] ] );

	$templates = [];
	foreach( glob( $site. '/private/templates/*.tpl' ) ?: [] as $file )
		if( preg_match_all( '/(?<!\[)\[([a-z][a-z0-9-]*)\b/', (string) file_get_contents( $file ), $calls ) > 0 && array_intersect( $calls[1], $basisComponents ) !== [] )
			$templates[] = basename( $file );
	check( 'no template of the project calls a component of Basis, and Basis is off: not in the module list, not in the record of the features, no word of it in the texts', $templates === []
		&& in_array( '\\Nino\\Modules\\Basis', $config['/nino/modules'], true ) === false && isset( $config['/nino/features'] ) === false
		&& array_filter( array_keys( include $site. '/private/text/'. $locale. '.php' ), static fn( string $key ): bool => str_starts_with( $key, '[[/module/basis/' ) === true ) === [] );

	$appData = siteBoot( $site );
	\Nino\Locales::useLocale( $appData, $locale );
	check( 'the legal check finds nothing to tell on a fresh site: no page of the imprint or the privacy policy is without a way in, no route is in the way', \Nino\Modules\Legal::check( $appData ) === [] );

	// Built as a request builds it, with the files of the checkout where the root of the site is: the reset is the kernel's own
	$built = siteBoot( $site );
	$built['./nino/filesystem/path'] = dirname( __DIR__ );
	\Nino\Modules\Assets::doShortcode( $built, [ '/.cache/style.css' ] );
	$bundle = (string) @file_get_contents( \Nino\Filesystem::path( $built, '/.cache/style.css' ) );
	check( 'the style bundle of the fresh site is the reset, the look of the form and the stylesheet of the site, and at most 22 KB', strlen( $bundle ) > 15000 && strlen( $bundle ) <= 22 * 1024 && str_contains( $bundle, '.site-header' ) === true && str_contains( $bundle, '.header-wrap' ) === false );

	$noticed = ninoWarnings();
	check( 'the log has no notice and no warning from setting the project up and drawing any of its pages'. ( $noticed === [] ? '' : ' - '. substr( implode( ' | ', array_unique( $noticed ) ), 0, 300 ) ), $noticed === [] );
	echo "\n";
}

echo "The same project with Basis switched on in the Features panel\n";

$withBasis = siteProject( 'de_DE', false );
$switching = siteBoot( $withBasis );
$switched = \Nino\Features::activate( $switching, 'basis' );
$basisVisit = siteVisit( $withBasis, '/' );
$built = siteBoot( $withBasis );
$built['./nino/filesystem/path'] = dirname( __DIR__ );
\Nino\Modules\Assets::doShortcode( $built, [ '/.cache/style.css' ] );
$basisBundle = (string) @file_get_contents( \Nino\Filesystem::path( $built, '/.cache/style.css' ) );
check( 'with Basis switched on the pages of the html site are drawn as they were - one header, one footer, one h1 - and the bundle holds the standard parts of Basis as well: the site\'s own stylesheet is still in it, behind them',
	$switched === true && $basisVisit[0] === 200 && substr_count( $basisVisit[1], '<header' ) === 1 && substr_count( $basisVisit[1], '<footer' ) === 1 && substr_count( $basisVisit[1], '<h1' ) === 1
	&& str_contains( $basisBundle, '.header-wrap' ) === true && strlen( $basisBundle ) > 40000 && strpos( $basisBundle, '.header-wrap' ) < strpos( $basisBundle, '.site-header' ) );
ninoWarnings();
echo "\n";

echo "A project whose company has no name\n";

$nameless = siteProject( 'de_DE', false, '' );
ninoWarnings();
$namelessVisits = [ '/' => siteVisit( $nameless, '/' ), '/contact' => siteVisit( $nameless, '/contact' ), '/impressum' => siteVisit( $nameless, '/impressum' ) ];
$namelessLog = ninoWarnings();
$startLinks = array_map( static fn( array $visit ): int => preg_match( '#<header class="site-header">.*?<a href="/"[^>]*class="brand"[^>]*>.*?</a>#s', $visit[1] ), $namelessVisits );
check( 'with no name of the company every page is drawn, 200, with its one header and a link to the start page that the brand leaves in the header, and the log has nothing to say of three requests',
	array_column( $namelessVisits, 0 ) === [ 200, 200, 200 ] && array_values( $startLinks ) === [ 1, 1, 1 ] && $namelessLog === []
	&& array_filter( $namelessVisits, static fn( array $visit ): bool => substr_count( $visit[1], '<header' ) !== 1 ) === [] );
echo "\n";

echo "A menu the starter pages are not in\n";

$menuSite = siteProject( 'de_DE', false );
ninoWarnings();
$menuApp = siteBoot( $menuSite );
$menuDrawn = \Nino\Html::renderHtml( $menuApp, '<div>[navigation nav="main"][/navigation]</div>' );
$menuConfig = include $menuSite. '/private/config.php';
check( 'the menu main, which no starter page is in, is empty where a template of the project outputs it - no error, no list, nothing in the log - and the menu legal has its two pages',
	$menuDrawn === '<div></div>' && ninoWarnings() === [] && in_array( 'main', $menuApp['/nino/html/navs'], true ) === true && array_keys( array_filter( $menuConfig['/nino/html/navroutes'] ?? [], static fn( array $menus ): bool => isset( $menus['main'] ) === true ) ) === []
	&& array_keys( array_filter( $menuConfig['/nino/html/navroutes'] ?? [], static fn( array $menus ): bool => isset( $menus['legal'] ) === true ) ) === [ '/legal/imprint', '/legal/privacy' ] );
echo "\n";

echo "A project an application gives its own start page\n";

$own = siteProject( 'de_DE', true );
$ownVisits = [ '/nowhere' => siteVisit( $own, '/nowhere' ), '/impressum' => siteVisit( $own, '/impressum' ) ];
$ownLog = ninoWarnings();
check( 'with an application that provides the start page the project has no header and no footer file, and the 404 page and the imprint are drawn all the same, with one main part, no header or footer element and nothing in the log',
	is_file( $own. '/private/templates/frame-header.tpl' ) === false && is_file( $own. '/private/templates/frame-footer.tpl' ) === false
	&& array_column( $ownVisits, 0 ) === [ 404, 200 ] && substr_count( $ownVisits['/nowhere'][1], '<main' ) === 1 && substr_count( $ownVisits['/impressum'][1], '<main' ) === 1
	&& str_contains( implode( '', array_column( $ownVisits, 1 ) ), '<header' ) === false && str_contains( implode( '', array_column( $ownVisits, 1 ) ), '<footer' ) === false && $ownLog === [] );

$empty = [];
ninoDone( $empty );
