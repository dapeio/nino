<?php
declare(strict_types=1);

/**
 *	Nino
 *	install-site-smoke.php	Test of the site the wizard writes: a fresh project set up in ONE language - German once, English once - through the
 *												steps (Setup::apiApply with a list of one language, StarterPages::apply) and then visited with real requests. The starter
 *												site is plain html, drawn by the kernel alone: every page answers 200 with one h1 and one main part in the language of the
 *												site, the menu marks the page that is shown, no template calls anything but what the kernel registers, no feature is on, the log has nothing to
 *												say, the legal check finds nothing missing, and the style bundle is the reset, the form and the site's own stylesheet.
 *												A site that an application sets up with its own start page is drawn without header and footer, with nothing in the log.
 *
 *	Usage: php tests/install-site-smoke.php
 */

// The one feature the run has is the test feature of the kernel, which the last project switches on
define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/kit' );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

$ignored = ninoWarnings();

/**
 *	A project the wizard set up in one language - the Setup step and the starter pages of the last one, and the words of the company filled in
 *
 *	@param		string		$locale				The one language
 *	@param		bool			$own					Whether an application provides the start page: no header, no footer, no starter page but the 404
 *	@param		string		$name					The name of the company, which may be empty
 *	@param		string		$stylesheet		What the operator wrote into assets/style.css between the Setup step and the last one, '' for nothing
 *
 *	@return 	string										The directory of the project
 */
function siteProject( string $locale, bool $own, string $name = 'Muster & Söhne GmbH', string $stylesheet = '' ): string {

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

	if( $stylesheet !== '' )
		file_put_contents( $sandbox. '/private/assets/style.css', $stylesheet );

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

	// No feature is on, so no component is registered: a template that called one would leave the call in the page as it is written, so that the pages say it
	$booted = siteBoot( $site );
	$left = [];
	foreach( $visits + [ '404' => $missing ] as $label => [ , $body ] )
		if( preg_match( '/(?<!\[)\[\/?[a-z][a-z0-9-]*[ \]]/', (string) preg_replace( '#<!--.*?-->|<script\b.*?</script>|<style\b.*?</style>#s', '', $body ) ) === 1 )
			$left[] = $label;
	check( 'no page of the project has a call left in it - the templates call no component, and no feature is on: not in the module list, not in the record of the features, no word of a feature in the texts', $left === []
		&& \Nino\Components::components( $booted ) === []
		&& array_filter( \Nino\Features::all( $booted ), static fn( array $feature ): bool => $feature['active'] === true ) === [] && isset( $config['/nino/features'] ) === false
		&& array_filter( array_keys( include $site. '/private/text/'. $locale. '.php' ), static fn( string $key ): bool => str_starts_with( $key, '[[/feature/' ) === true ) === [] );

	$appData = siteBoot( $site );
	\Nino\Locales::useLocale( $appData, $locale );
	check( 'the legal check finds nothing to tell on a fresh site: no page of the imprint or the privacy policy is without a way in, no route is in the way', \Nino\Modules\Legal::check( $appData ) === [] );

	// Built as a request builds it, with the files of the checkout where the root of the site is: the reset is the kernel's own
	$built = siteBoot( $site );
	$built['./nino/filesystem/path'] = dirname( __DIR__ );
	\Nino\Modules\Assets::doShortcode( $built, [ '/.cache/style.css' ] );
	$bundle = (string) @file_get_contents( \Nino\Filesystem::path( $built, '/.cache/style.css' ) );
	check( 'the style bundle of the fresh site is the reset, the look of the form and the stylesheet of the site, and at most 22 KB', strlen( $bundle ) > 15000 && strlen( $bundle ) <= 22 * 1024 && str_contains( $bundle, '.site-header' ) === true && str_contains( $bundle, '.header-wrap' ) === false );

	check( 'the stylesheet of the project is the one of the frame unit - the last step writes it with the header and the footer - and is in the bundle, whole', md5_file( $site. '/private/assets/style.css' ) === md5_file( dirname( __DIR__ ). '/_admin/install/library/frame/assets/style.css' )
		&& str_contains( $bundle, (string) file_get_contents( dirname( __DIR__ ). '/_admin/install/library/frame/assets/style.css' ) ) === true );

	$noticed = ninoWarnings();
	check( 'the log has no notice and no warning from setting the project up and drawing any of its pages'. ( $noticed === [] ? '' : ' - '. substr( implode( ' | ', array_unique( $noticed ) ), 0, 300 ) ), $noticed === [] );
	echo "\n";
}

echo "The same project with the test feature switched on in the Features panel\n";

$withKit = siteProject( 'de_DE', false );
$switching = siteBoot( $withKit );
$switched = \Nino\Features::activate( $switching, 'kit' );
$kitVisit = siteVisit( $withKit, '/' );
$built = siteBoot( $withKit );
$built['./nino/filesystem/path'] = dirname( __DIR__ );
\Nino\Modules\Assets::doShortcode( $built, [ '/.cache/style.css' ] );
$kitBundle = (string) @file_get_contents( \Nino\Filesystem::path( $built, '/.cache/style.css' ) );
check( 'with a feature switched on the pages of the html site are drawn as they were - one header, one footer, one h1 - and the bundle holds the standard parts of the feature as well: the site\'s own stylesheet is still in it, behind them',
	$switched === true && $kitVisit[0] === 200 && substr_count( $kitVisit[1], '<header' ) === 1 && substr_count( $kitVisit[1], '<footer' ) === 1 && substr_count( $kitVisit[1], '<h1' ) === 1
	&& str_contains( $kitBundle, '.band-wrap' ) === true && strpos( $kitBundle, '.band-wrap' ) < strpos( $kitBundle, '.site-header' ) );
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

// The stylesheet of the html site belongs to its header and footer: where an application provides the start page the project keeps the empty one the Setup step wrote
$baseCss = (string) file_get_contents( dirname( __DIR__ ). '/_admin/install/library/base/assets/style.css' );
$ownBuilt = siteBoot( $own );
$ownBuilt['./nino/filesystem/path'] = dirname( __DIR__ );
\Nino\Modules\Assets::doShortcode( $ownBuilt, [ '/.cache/style.css' ] );
$ownBundle = (string) @file_get_contents( \Nino\Filesystem::path( $ownBuilt, '/.cache/style.css' ) );
$ownConfig = \Nino\Filesystem::getFileContent( $ownBuilt, '/config.php', [] );
check( 'with an application that provides the start page the stylesheet of the project is the empty one of the base unit - a comment and no rule - and the bundle holds no rule of the html site: no header, no menu, no title area, no footer',
	(string) file_get_contents( $own. '/private/assets/style.css' ) === $baseCss && preg_match( '/^\/\*[^*]*(?:\*(?!\/)[^*]*)*\*\/\s*$/', $baseCss ) === 1
	&& ( $ownConfig['/nino/html/assets']['/.cache/style.css'] ?? [] ) === [ '/_nino/Nino.css', '/assets/style.css' ]
	&& array_filter( [ '.site-header', '.site-footer', '.footer-cols', '.site-nav', '.hero', '.brand' ], static fn( string $selector ): bool => str_contains( $ownBundle, $selector ) === true ) === [] && strlen( $ownBundle ) > 10000 );

// What the operator wrote there between the two steps: with an application that provides the start page it stays; with the starter pages the library wins, as the frames do
$mine = "/* mine */\n.mine { color: red; }\n";
$keptOwn = siteProject( 'de_DE', true, 'Muster & Söhne GmbH', $mine );
$keptStarter = siteProject( 'de_DE', false, 'Muster & Söhne GmbH', $mine );
check( 'a stylesheet the operator changed between the steps stays where an application provides the start page, and is replaced by the stylesheet of the html site where the starter pages are written',
	(string) file_get_contents( $keptOwn. '/private/assets/style.css' ) === $mine && md5_file( $keptStarter. '/private/assets/style.css' ) === md5_file( dirname( __DIR__ ). '/_admin/install/library/frame/assets/style.css' ) );

$empty = [];
ninoDone( $empty );
