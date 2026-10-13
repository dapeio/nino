<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	maintenance-smoke.php	Dependency-free smoke test for \Nino\Modules\Maintenance: the one
 *											switch that answers every page with a 503, what stays reachable, the
 *											page in the visitor's language or the project's own, and the banner
 *											a signed-in account sees. Runs against an isolated sandbox
 *											directory, never touches the real project data.
 *
 *	Usage: php tests/maintenance-smoke.php
 */

require __DIR__. '/harness.php';

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'maintenance' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";


// --- Modules\Maintenance ---------------------------------------------------

echo "Modules\\Maintenance - one switch answers every page with 503\n";

// A request as Http::request()/response() would leave it before this
// module's global callback runs - same shape as cacheRequest() in http-smoke.php.
function maintenanceRequest( string $uri, string $method = 'GET', array $route = [] ): array {
	return [
		'/nino/http/request' => [
			'method' 		=> $method,
			'rawMethod'	=> $method,
			'uri' 			=> $uri,
			'query' 		=> [],
		],
		// The route's own fields are mixed into the answer by Http::response()
		// before this module's callback runs
		'/nino/http/response' => array_merge( [
			'uri' 				=> $uri,
			'locale' 			=> 'de_DE',
			'statusCode'	=> 200,
			'header' 			=> [],
			'body' 				=> '<html>page</html>',
		], $route ),
	];
}

unset( $appData['./nino/auth/current'] );
$appData['/nino/maintenance/status'] = false;
unset( $appData['/nino/maintenance/retry'] );

$off = maintenanceRequest( '/' );
check( 'status off changes nothing', \Nino\Modules\Maintenance::_prepare( $appData, $off ) === false );
check( '...the response is left exactly as it was', $off['/nino/http/response']['statusCode'] === 200 && $off['/nino/http/response']['body'] === '<html>page</html>' );

// --- status on ---

$appData['/nino/maintenance/status'] = true;
$appData['/nino/maintenance/retry'] = 120;

$home = maintenanceRequest( '/' );
check( 'status on: an anonymous GET of / is answered here', \Nino\Modules\Maintenance::_prepare( $appData, $home ) === true );
check( '...with statusCode 503', $home['/nino/http/response']['statusCode'] === 503 );
check( '...Retry-After from the configured seconds', $home['/nino/http/response']['header']['Retry-After'] === '120' );
check( '...Cache-Control: no-store', $home['/nino/http/response']['header']['Cache-Control'] === 'no-store' );
check( '...the built-in fallback body, no /templates/page-maintenance.tpl on this sandbox',
	str_contains( $home['/nino/http/response']['body'], 'Under maintenance' ) === true
	&& str_contains( $home['/nino/http/response']['body'], 'We will be back shortly.' ) === true );

// /_admin and everything below it keeps working, so an operator can still
// log in and switch this back off
check( '/_admin is untouched', \Nino\Modules\Maintenance::_prepare( $appData, maintenanceRequest( '/_admin' ) ) === false );
check( '...and a screen below it too', \Nino\Modules\Maintenance::_prepare( $appData, maintenanceRequest( '/_admin/config' ) ) === false );

// A module endpoint is answered too - the site is down for a form
// submission exactly as much as for the page it sits on
$formPost = maintenanceRequest( '/.form', 'POST' );
check( 'a /.form POST is answered with the maintenance page as well', \Nino\Modules\Maintenance::_prepare( $appData, $formPost ) === true );
check( '...with statusCode 503', $formPost['/nino/http/response']['statusCode'] === 503 );

// A signed-in account sees the site as it is
$appData['./nino/auth/current'] = [ 'mail' => 'operator@example.com', 'perms' => [] ];
$signedIn = maintenanceRequest( '/' );
check( 'a signed-in user gets the page, not the 503', \Nino\Modules\Maintenance::_prepare( $appData, $signedIn ) === false );
check( '...the response is left exactly as it was', $signedIn['/nino/http/response']['statusCode'] === 200 && $signedIn['/nino/http/response']['body'] === '<html>page</html>' );
unset( $appData['./nino/auth/current'] );

// init() takes the full-page cache out of the loop at runtime, without
// persisting anything - config.php's own /nino/cache/status is untouched
$appData['/nino/cache/status'] = true;
\Nino\Modules\Maintenance::init( $appData );
check( '/nino/cache/status is false at runtime while maintenance is on', $appData['/nino/cache/status'] === false );

$appData['/nino/maintenance/status'] = false;
$appData['/nino/cache/status'] = true;
\Nino\Modules\Maintenance::init( $appData );
check( '...and left alone while maintenance is off', $appData['/nino/cache/status'] === true );

// A route that says 'maintenance' => false stays reachable. The login is one: an
// operator who is not signed in has to be able to sign in, and the answer to that
// POST was the 503 page, the workbench's login form included. Auth carries the
// field on its route, and the module reads it from the answer Http::response()
// has mixed the route into - one mechanism, whatever the route is
$appData['/nino/maintenance/status'] = true;
unset( $appData['./nino/auth/current'] );
$authApp = $appData;
\Nino\Auth::init( $authApp );
$loginRoute 	= $authApp['/nino/http/routes']['POST://.nino/auth/login'] ?? [];
$logoutRoute 	= $authApp['/nino/http/routes']['POST://.nino/auth/logout'] ?? [];
check( 'Auth\'s login route carries the field, its logout route does not', ( $loginRoute['maintenance'] ?? null ) === false && isset( $logoutRoute['maintenance'] ) === false );
$loginPost = maintenanceRequest( '/.nino/auth/login', 'POST', $loginRoute );
check( 'the login POST of a visitor who is not signed in is not answered with the maintenance page', \Nino\Modules\Maintenance::_prepare( $appData, $loginPost ) === false
	&& $loginPost['/nino/http/response']['statusCode'] === 200 && $loginPost['/nino/http/response']['body'] === '<html>page</html>' );
$loginGet = maintenanceRequest( '/.nino/auth/login', 'GET' );
$logoutPost = maintenanceRequest( '/.nino/auth/logout', 'POST', $logoutRoute );
check( '...only a route that says so: anything else at the address, and the logout, is still the maintenance page', \Nino\Modules\Maintenance::_prepare( $appData, $loginGet ) === true
	&& \Nino\Modules\Maintenance::_prepare( $appData, $logoutPost ) === true );
$legalPage = maintenanceRequest( '/impressum', 'GET', [ 'uri' => '/legal/imprint', 'maintenance' => false ] );
check( 'any route with the field is reachable, a page of the Legal module among them', \Nino\Modules\Maintenance::_prepare( $appData, $legalPage ) === false && $legalPage['/nino/http/response']['statusCode'] === 200 );
$notQuite = [ maintenanceRequest( '/x', 'GET', [ 'maintenance' => true ] ), maintenanceRequest( '/x', 'GET', [ 'maintenance' => 'false' ] ), maintenanceRequest( '/x', 'GET', [ 'maintenance' => 0 ] ), maintenanceRequest( '/x', 'GET', [ 'maintenance' => null ] ) ];
check( '...and only the boolean false counts: true, a string, 0 and null are the maintenance page', array_unique( array_map( static function( array $request ) use ( &$appData ): bool { return \Nino\Modules\Maintenance::_prepare( $appData, $request ); }, $notQuite ) ) === [ true ] );
$appData['/nino/maintenance/status'] = false;

/*	The page the module brings. It is its own template per language, read
	from the module's directory - the sandbox has no _nino/, so these point
	the file system at the checkout, as the workbench's own render test does,
	while the text, the templates and the config stay in the sandbox. The
	site's native language is the page's, whatever language the route asked
	for: the visitor has chosen nothing, and this page is not the site's	*/
$moduleApp = $appData;
$moduleApp['./nino/filesystem/path'] = dirname( __DIR__ );
// A path is remembered per file name, and the earlier checks have asked for
// these names with the sandbox as the root
unset( $moduleApp['./nino/filesystem/cache'] );
$moduleApp['/nino/maintenance/status'] = true;
$moduleApp['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
unset( $moduleApp['./nino/auth/current'] );
\Nino\Runtime::unsetSessionValue( $moduleApp, './nino/locales/current' );

$german = maintenanceRequest( '/' );
$german['/nino/http/response']['locale'] = 'en_US';
\Nino\Locales::useLocale( $moduleApp, 'en_US' );
check( 'native de_DE: the module\'s German page, though the route asked for en_US', \Nino\Modules\Maintenance::_prepare( $moduleApp, $german ) === true
	&& str_contains( $german['/nino/http/response']['body'], '<html lang="de">' ) === true );
check( '...with the module\'s German defaults for the two texts', str_contains( $german['/nino/http/response']['body'], '<h1>Wartungsarbeiten</h1>' ) === true
	&& str_contains( $german['/nino/http/response']['body'], 'Wir sind in Kürze wieder für Dich da.' ) === true && str_contains( $german['/nino/http/response']['body'], '[[' ) === false );
check( '...answered as the 503 it is, in the native locale', $german['/nino/http/response']['statusCode'] === 503 && $german['/nino/http/response']['locale'] === 'de_DE' && \Nino\Locales::getCurrentLocale( $moduleApp ) === 'de_DE' );
check( '...and nothing was written into the visitor\'s session', \Nino\Runtime::getSessionValue( $moduleApp, './nino/locales/current', 'unwritten' ) === 'unwritten' );

$moduleApp['/nino/locales/native'] = 'en_US';
$english = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $moduleApp, $english );
check( 'native en_US: the English page', str_contains( $english['/nino/http/response']['body'], '<html lang="en">' ) === true
	&& str_contains( $english['/nino/http/response']['body'], '<h1>Under maintenance</h1>' ) === true && str_contains( $english['/nino/http/response']['body'], 'We will be back shortly.' ) === true );

$moduleApp['/nino/locales/native'] = 'fr_FR';
$french = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $moduleApp, $french );
check( 'a native language the module has no page for gets the English one, in that locale', str_contains( $french['/nino/http/response']['body'], '<html lang="en">' ) === true
	&& str_contains( $french['/nino/http/response']['body'], '<h1>Under maintenance</h1>' ) === true && $french['/nino/http/response']['locale'] === 'fr_FR' );

// What becomes a path is checked first
$moduleApp['/nino/locales/native'] = '../../x';
\Nino\Locales::useLocale( $moduleApp, 'de_DE' );
$odd = maintenanceRequest( '/' );
check( 'a native locale that is no locale id is not made part of a path: the page of the current one is shown', \Nino\Modules\Maintenance::_prepare( $moduleApp, $odd ) === true
	&& str_contains( $odd['/nino/http/response']['body'], '<html lang="de">' ) === true );
$moduleApp['/nino/locales/native'] = 'de_DE';

// A project's own text wins over the module's default, and is escaped - the
// page is output and a title is something a person typed
\Nino\Html::addFills( $moduleApp, [ '/module/maintenance/page/title' => 'Back <b>soon</b> & "soon"', '/module/maintenance/page/text' => "Line one\nLine <two>" ], '*' );
$filledPage = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $moduleApp, $filledPage );
check( 'a project\'s /module/maintenance/page/title and /module/maintenance/page/text win over the module\'s defaults - on the module\'s page', str_contains( $filledPage['/nino/http/response']['body'], '<h1>Back &lt;b&gt;soon&lt;/b&gt; &amp; &quot;soon&quot;</h1>' ) === true
	&& str_contains( $filledPage['/nino/http/response']['body'], '<p>Line one<br />' ) === true && str_contains( $filledPage['/nino/http/response']['body'], 'Line &lt;two&gt;' ) === true );
check( '...and no markup of theirs reaches the page', str_contains( $filledPage['/nino/http/response']['body'], '<b>' ) === false && str_contains( $filledPage['/nino/http/response']['body'], '<two>' ) === false );

// A project's own template keeps the language of the route: it wears the
// site's design, and the site is in the visitor's language
$moduleApp['/nino/locales/native'] = 'de_DE';
\Nino\Filesystem::putFileContent( $moduleApp, '/templates/page-maintenance.tpl', '<main><h1>own</h1></main>' );
\Nino\Modules\Template::init( $moduleApp );
$ownRequest = maintenanceRequest( '/' );
$ownRequest['/nino/http/response']['locale'] = 'en_US';
\Nino\Modules\Maintenance::_prepare( $moduleApp, $ownRequest );
check( 'a project\'s page-maintenance.tpl wins, and is rendered in the language of the route, not forced to the native one', str_contains( $ownRequest['/nino/http/response']['body'], '<h1>own</h1>' ) === true
	&& $ownRequest['/nino/http/response']['locale'] === 'en_US' && \Nino\Locales::getCurrentLocale( $moduleApp ) === 'en_US' );
@unlink( \Nino\Filesystem::path( $moduleApp, '/templates/page-maintenance.tpl' ) );

// The banner a signed-in account gets while the site shows everybody else the
// maintenance page. /nino/http/output runs on the finished page, after every
// fill is replaced, so the link is built from the directory
$bannerApp = $moduleApp;
\Nino\Locales::useLocale( $bannerApp, 'de_DE' );
$bannerApp['/nino/dir'] = '/shop';
$bannerApp['./nino/auth/current'] = [ 'mail' => 'operator@example.com', 'perms' => [ '/_admin/maintenance/manage' ] ];
$pageHtml = '<!doctype html><html><head><title>x</title></head><body class="home"><main>page</main></body></html>';
$outputRequest = static function( string $uri = '/', mixed $body = null, array $header = [] ) use ( $pageHtml ): array {
	return [
		'/nino/http/request'	=> [ 'method' => 'GET', 'uri' => $uri ],
		'/nino/http/response'	=> [ 'statusCode' => 200, 'header' => $header, 'body' => $body ?? $pageHtml ],
	];
};

$bannered = $outputRequest();
\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $bannered );
$bannerBody = $bannered['/nino/http/response']['body'];
check( 'a signed-in account gets the banner once, right after the opening <body> - in the flow, not fixed', substr_count( $bannerBody, 'role="status"' ) === 1
	&& str_contains( $bannerBody, '<body class="home"><div role="status"' ) === true && str_contains( $bannerBody, 'position:fixed' ) === false && str_contains( $bannerBody, 'position:relative' ) === true && preg_match( '/z-index:\d+/', $bannerBody ) === 1 && str_contains( $bannerBody, '<main>page</main>' ) === true );
check( '...in the current language, with the words of the module\'s own text file', str_contains( $bannerBody, 'Die Wartung ist aktiv – Besucher sehen die Wartungsseite.' ) === true );
check( '...and with the account\'s permission, a link built from the project directory', str_contains( $bannerBody, '<a href="/shop/_admin#maintenance"' ) === true && str_contains( $bannerBody, '>Zur Wartung</a>' ) === true && str_contains( $bannerBody, '[[' ) === false );

\Nino\Locales::useLocale( $bannerApp, 'fr_FR' );
$frenchBanner = $outputRequest();
\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $frenchBanner );
check( 'a language without words of its own gets the English ones', str_contains( $frenchBanner['/nino/http/response']['body'], 'Maintenance is on – visitors see the maintenance page.' ) === true );
\Nino\Locales::useLocale( $bannerApp, 'de_DE' );

$noLink = $bannerApp;
$noLink['./nino/auth/current'] = [ 'mail' => 'editor@example.com', 'perms' => [ '/_admin/text/manage' ] ];
$editorBanner = $outputRequest();
\Nino\Modules\Maintenance::callbackOutput( $noLink, $editorBanner );
check( 'an account without the maintenance permission gets the banner and no link', str_contains( $editorBanner['/nino/http/response']['body'], 'Die Wartung ist aktiv' ) === true
	&& str_contains( $editorBanner['/nino/http/response']['body'], '<a ' ) === false );

$untouched = [];
$untouched['an anonymous visitor']				= [ $moduleApp, $outputRequest() ];
$untouched['/_admin']											= [ $bannerApp, $outputRequest( '/_admin' ) ];
$untouched['a screen below /_admin']			= [ $bannerApp, $outputRequest( '/_admin/config' ) ];
$untouched['an array body (a json answer)']	= [ $bannerApp, $outputRequest( '/', [ 'status' => 'ok' ] ) ];
$untouched['application/xml']							= [ $bannerApp, $outputRequest( '/sitemap.xml', $pageHtml, [ 'Content-Type' => 'application/xml' ] ) ];
$untouched['a fragment without </body>']	= [ $bannerApp, $outputRequest( '/', '<div>a fragment</div>' ) ];
$statusOff = $bannerApp;
$statusOff['/nino/maintenance/status'] = false;
$untouched['maintenance switched off']		= [ $statusOff, $outputRequest() ];
foreach( $untouched as $label => [ $app, $req ] ) {
	$before = $req['/nino/http/response']['body'];
	\Nino\Modules\Maintenance::callbackOutput( $app, $req );
	check( 'no banner for '. $label, $req['/nino/http/response']['body'] === $before );
}

// A '>' inside a quoted value of the <body> tag is no end of it
foreach( [ '<body data-x="a>b" class=\'c>d\'>' => '<body data-x="a>b" class=\'c>d\'><div role="status"', '<body class="home">' => '<body class="home"><div role="status"' ] as $bodyTag => $expectedStart ) {
	$quoted = $outputRequest( '/', '<!doctype html><html><head><title>x</title></head>'. $bodyTag. '<main>page</main></body></html>' );
	\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $quoted );
	check( 'the banner goes after the whole opening tag '. $bodyTag, str_contains( $quoted['/nino/http/response']['body'], $expectedStart ) === true && substr_count( $quoted['/nino/http/response']['body'], 'role="status"' ) === 1 );
}

$typed = $outputRequest( '/', $pageHtml, [ 'Content-Type' => 'text/html; charset=utf-8' ] );
\Nino\Modules\Maintenance::callbackOutput( $bannerApp, $typed );
check( 'text/html with a charset is a page', str_contains( $typed['/nino/http/response']['body'], 'role="status"' ) === true );

$registered = [];
$hookApp = $appData;
$hookApp['./nino/callbacks'] = [];
\Nino\Modules\Maintenance::init( $hookApp );
foreach( $hookApp['./nino/callbacks']['/nino/http/output'][9] ?? [] as $hook )
	$registered[] = $hook;
check( 'init() registers the banner on /nino/http/output beside Cache\'s own callback (priority 9)', $registered === [ [ \Nino\Modules\Maintenance::class, 'callbackOutput' ] ] );

// The fallback page renders both fills - a project's own text (however it
// got there) always wins over the hardcoded default
$appData['/nino/maintenance/status'] = true;
\Nino\Html::addFills( $appData, [
	'/module/maintenance/page/title'	=> 'Back soon',
	'/module/maintenance/page/text'		=> 'Custom maintenance notice',
], '*' );

$filled = maintenanceRequest( '/' );
\Nino\Modules\Maintenance::_prepare( $appData, $filled );
check( 'the fallback page renders both fills', str_contains( $filled['/nino/http/response']['body'], 'Back soon' ) === true
	&& str_contains( $filled['/nino/http/response']['body'], 'Custom maintenance notice' ) === true );

// A project's own template wears the site's header, and that header names
// the request fills \Nino\request() only adds after this callback round -
// so the module adds them itself before it renders
\Nino\Filesystem::putFileContent( $appData, '/templates/page-maintenance.tpl', '<main data-uri="[[/nino/http/request/uri]]" data-locale="[[/nino/http/response/locale]]"><h1>[[/module/maintenance/page/title]]</h1></main>' );
\Nino\Modules\Template::init( $appData );
$templated = maintenanceRequest( '/kontakt' );
\Nino\Modules\Maintenance::_prepare( $appData, $templated );
check( 'a project\'s page-maintenance.tpl renders with the request fills the kernel would have added', str_contains( $templated['/nino/http/response']['body'], 'data-uri="/kontakt"' ) === true
	&& str_contains( $templated['/nino/http/response']['body'], '<h1>Back soon</h1>' ) === true && str_contains( $templated['/nino/http/response']['body'], '[[/nino' ) === false );
@unlink( \Nino\Filesystem::path( $appData, '/templates/page-maintenance.tpl' ) );

$appData['/nino/maintenance/status'] = false;
$appData['/nino/cache/status'] = false;
unset( $appData['/nino/maintenance/retry'] );

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
