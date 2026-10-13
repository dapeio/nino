<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	http-smoke.php			Dependency-free smoke test for \Nino\Http: the headers a response is
 *											composed with, the csp and the locale redirects, the shipped
 *											.htaccess and router.php, Modules\Navigation, and Modules\Cache -
 *											the full-page cache for an anonymous GET. Runs against an isolated
 *											sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/http-smoke.php
 */

require __DIR__. '/harness.php';

// Build an isolated sandbox appData, bypassing Nino\init()'s session/error-handler bootstrap
$appData = ninoSandbox( 'http' );
$sandbox = ninoSandboxDir( $appData );

$appData['/nino/dir'] = '';

echo "Sandbox: $sandbox\n\n";


// --- Http header composition / locale redirects ---------------------------

echo "Http::request/response - header composition, csp, locale redirects\n";

$appData['/nino/http/routes'] = [
	'GET://' 						=> [ 'uri' => '/home', 'body' => '' ],
	'GET://rechtliches'	=> [ 'uri' => '/legal', 'body' => '', 'locale' => 'de_DE' ],
	// statusCode mirrors a route like GET://_admin declaring its own status -
	// exactly the shape that used to swallow the locale-switch redirect below
	'GET://legal'				=> [ 'uri' => '/legal', 'body' => '', 'locale' => 'en_US', 'statusCode' => 201 ],
	'GET://robots.txt'	=> [ 'uri' => '/robots.txt', 'body' => '', 'header' => [ 'Content-Type' => 'text/plain; charset=utf-8' ] ],
];

// Mirrors what \Nino\Locales::init() registers in the real request flow -
// this test builds appData by hand and never calls \Nino\init() itself
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response', [ \Nino\Locales::class, 'callbackResponse' ] );

// Locales::init() resolves the default locale for a visitor who has not
// picked one. AppData::prepare() can only seed a hardcoded placeholder
// there (config.php isn't read yet at that point), and leaving it at that
// meant a project rendered in that hardcoded locale rather than its own
// configured native one - and, for a project that doesn't install the
// hardcoded one at all, out of a text file that does not exist: every
// per-locale [[key]] on the page unresolved
// Its own uid, so its session bucket (keyed by exactly that, see
// Runtime::getSessionValue()) cannot be one an earlier check already wrote
$nativeAppData = [ './nino/uid' => $sandbox. '-native' ];
\Nino\AppData::prepare( $nativeAppData );
$nativeAppData['./nino/filesystem/path'] = $sandbox;
$nativeAppData['/nino/locales/native'] 		= 'en_US';
$nativeAppData['/nino/locales/available'] = [ 'en_US' ];
\Nino\Locales::init( $nativeAppData );
check( 'Locales::init defaults to the configured native locale, not the seeded placeholder', \Nino\Locales::getCurrentLocale( $nativeAppData ) === 'en_US' );
check( '...which is therefore always one this project actually has text for', \Nino\Locales::verifyLocale( $nativeAppData, \Nino\Locales::getCurrentLocale( $nativeAppData ) ) === true );

// A native locale outside the available list is a broken config (_admin's
// raw Config editor can produce one) - still better answered with a locale
// the project has than with one it has no text file for
$brokenNativeAppData = [ './nino/uid' => $sandbox. '-broken-native' ];
\Nino\AppData::prepare( $brokenNativeAppData );
$brokenNativeAppData['./nino/filesystem/path'] = $sandbox;
$brokenNativeAppData['/nino/locales/native'] 		= 'fr_FR';
$brokenNativeAppData['/nino/locales/available'] = [ 'en_US', 'de_DE' ];
\Nino\Locales::init( $brokenNativeAppData );
check( 'a native locale that is not available falls back to the first available one', \Nino\Locales::getCurrentLocale( $brokenNativeAppData ) === 'en_US' );

$malformedSessionAppData = [ './nino/uid' => $sandbox. '-malformed-locale-session' ];
\Nino\AppData::prepare( $malformedSessionAppData );
$malformedSessionAppData['./nino/filesystem/path'] = $sandbox;
$malformedSessionAppData['/nino/locales/native'] = 'en_US';
$malformedSessionAppData['/nino/locales/available'] = [ 'en_US', 'de_DE' ];
\Nino\Runtime::setSessionValue( $malformedSessionAppData, './nino/locales/current', [ 'de_DE' ] );
\Nino\Locales::init( $malformedSessionAppData );
check( 'Locales::init ignores a malformed non-string locale stored in the session', \Nino\Locales::getCurrentLocale( $malformedSessionAppData ) === 'en_US' );

// The default must not be written into the session - it is nobody's choice,
// and stored there it would outlive a later change of the native locale
check( 'resolving the default does not persist it as a visitor locale', \Nino\Runtime::getSessionValue( $nativeAppData, './nino/locales/current' ) === null );

/*	...and neither must the fallback a stale session locale lands on. A
	visitor who once picked a locale the project has since dropped had
	setCurrentLocale() called with it: useLocale() inside it answered the
	project's default, and setCurrentLocale() then wrote that default into
	their session - the one thing the comment above it forbids, done by
	init() itself. From then on init() read it back and let it win, so a
	project that changed its native locale never reached that visitor again	*/
$staleSessionAppData = [ './nino/uid' => $sandbox. '-stale-locale-session' ];
\Nino\AppData::prepare( $staleSessionAppData );
$staleSessionAppData['./nino/filesystem/path']	= $sandbox;
$staleSessionAppData['/nino/locales/native']			= 'en_US';
$staleSessionAppData['/nino/locales/available']	= [ 'en_US', 'de_DE' ];
\Nino\Runtime::setSessionValue( $staleSessionAppData, './nino/locales/current', 'fr_FR' );
\Nino\Locales::init( $staleSessionAppData );

check( 'a session locale the project no longer has falls back for this request', \Nino\Locales::getCurrentLocale( $staleSessionAppData ) === 'en_US' );
// Left as it was rather than corrected: it is not honoured while the
// project does not have that locale, and it is the visitor's own again if
// it comes back. What must not be in there is a default nobody chose
check( '...without the fallback being written into the visitor\'s session', \Nino\Runtime::getSessionValue( $staleSessionAppData, './nino/locales/current' ) === 'fr_FR' );

// And the ordinary case is untouched: a stored locale the project still has
// is what the request runs in, and stays where it is
$chosenSessionAppData = [ './nino/uid' => $sandbox. '-chosen-locale-session' ];
\Nino\AppData::prepare( $chosenSessionAppData );
$chosenSessionAppData['./nino/filesystem/path']		= $sandbox;
$chosenSessionAppData['/nino/locales/native']			= 'en_US';
$chosenSessionAppData['/nino/locales/available']	= [ 'en_US', 'de_DE' ];
\Nino\Runtime::setSessionValue( $chosenSessionAppData, './nino/locales/current', 'de_DE' );
\Nino\Locales::init( $chosenSessionAppData );

check( 'a session locale the project does have still wins over the native default', \Nino\Locales::getCurrentLocale( $chosenSessionAppData ) === 'de_DE'
	&& \Nino\Runtime::getSessionValue( $chosenSessionAppData, './nino/locales/current' ) === 'de_DE' );

/*	Runtime's session, which is started on demand rather than on every
	request: an anonymous GET of a page that renders no form and signs
	nobody in used to leave a session file and a PHPSESSID cookie behind
	for a session that never held one value. Measured with php -S against
	this kernel: a plain page answers with neither, the same page with a
	[csrf] in it answers with both, and a visitor who has one keeps the one
	they have. The suites build their appData without Runtime::init() (see
	harness.php), so there is nothing to start here and $_SESSION stays the
	ordinary array it is on the cli - which is what these assert	*/
check( 'startSession reports no session where init() never ran', \Nino\Runtime::startSession( $appData ) === false );

\Nino\Runtime::setSessionValue( $appData, './nino/test/value', 'stored' );
check( '...and a session value is still written and read back', \Nino\Runtime::getSessionValue( $appData, './nino/test/value' ) === 'stored' );

\Nino\Runtime::unsetSessionValue( $appData, './nino/test/value' );
check( '...and unset again', \Nino\Runtime::getSessionValue( $appData, './nino/test/value', 'gone' ) === 'gone' );

// Reading never starts one either: a value nobody wrote is the default,
// not an empty session brought into being by asking for it
check( 'reading an unwritten key answers the default', \Nino\Runtime::getSessionValue( $appData, './nino/test/never-written', 'default' ) === 'default'
	&& session_status() !== PHP_SESSION_ACTIVE );

/**
 *	The uri request() resolves for one server array, or the class name of
 *	whatever it threw instead. Every case below used to throw, and a suite
 *	that dies on the throw reports no failed check at all - it reports
 *	nothing
 */
function resolvedUri( array &$appData, array $server ): string {
	try {
		$request = $server;
		\Nino\Http::request( $appData, $request );
		return (string) ( $request['/nino/http/request']['uri'] ?? '' );
	} catch( \Throwable $e ) {
		return get_class( $e );
	}
}

$homeRequest = ninoVisit( $appData, '/' );
$seededCsp = $homeRequest['/nino/http/response']['header']['Content-Security-Policy'] ?? '';
check( 'the response header is seeded with the default csp', str_contains( $seededCsp, "default-src 'self'" ) === true );

/*	Whether a response may be stored is Nino's answer now. It used to be
	php's: the session was started on every request, and its cache limiter
	put Expires/Pragma/Cache-Control on every response as a side effect.
	A request that writes no session value starts none any more, so the
	same answer has to come from here or it would depend on whether the
	page happened to render a form	*/
check( 'the response header carries an explicit no-store', ( $homeRequest['/nino/http/response']['header']['Cache-Control'] ?? '' ) === 'no-store' );

// img-src '*' covers network schemes only, so a data: uri needs spelling out.
/*	Every custom property Nino.css reads without a fallback is one it declares
	itself. The base unit's theme.css declares the same names again for a
	project, but a page without that theme - or with an older copy of it -
	gets the browser's initial value where a name is missing, which for a
	font-family is whatever the parent had; --fontfamily-subtitle was that
	name, read by two rules and declared by theme.css alone	*/
$ninoCss = (string) @file_get_contents( __DIR__. '/../_nino/Nino.css' );
preg_match_all( '/var\(\s*(--[a-zA-Z0-9-]+)\s*\)/', $ninoCss, $consumedProperties );
preg_match_all( '/(--[a-zA-Z0-9-]+)\s*:/', $ninoCss, $declaredProperties );
$undeclaredProperties = array_values( array_diff( array_unique( $consumedProperties[1] ), $declaredProperties[1] ) );
check( 'every custom property Nino.css reads without a fallback is declared in Nino.css itself'. ( $undeclaredProperties === [] ? '' : ' - missing: '. implode( ', ', $undeclaredProperties ) ), $undeclaredProperties === [] );

/*	A page that opens with a hero loses main's top padding - also when the
	Builder's div.nino-wrap puts the hero one level down	*/
$heroSelectors = [];
foreach( [ '.nino-cover.nino-hero', '.nino-parallex.nino-hero', '.nino-hero--fullscreen' ] as $hero )
	foreach( [ 'main:has(> ', 'main:has(> .nino-wrap > ' ] as $head )
		$heroSelectors[] = $head. $hero. ':first-child)';
check( 'Nino.css takes main\'s top padding away for a hero, directly below main and inside the Builder\'s wrap', preg_match( '/((?:main:has\([^)]*\)\)?,\s*){5}main:has\([^)]*\)\)?)\s*\{\s*padding-top:\s*0;/', $ninoCss, $heroRule ) === 1
	&& array_filter( $heroSelectors, static fn( string $selector ): bool => str_contains( $heroRule[1], $selector ) === false ) === [] );

// Nino.css uses one for .nino-hero-arrowdown, ie. the framework's own default
// policy used to block the framework's own icon
check( 'the default csp allows data: images', str_contains( $seededCsp, 'img-src * data:' ) === true );
check( '...which is what Nino.css\'s own data: uri needs', str_contains(
	(string) @file_get_contents( __DIR__. '/../_nino/Nino.css' ), 'url("data:image/svg+xml'
) === false || str_contains( $seededCsp, 'data:' ) === true );

/*	A request that names neither a method nor a uri, and a uri that is
	nothing but the markers a path is cut at. Neither REQUEST_METHOD nor
	REQUEST_URI is guaranteed by the cgi environment - php-cgi under IIS
	composes no REQUEST_URI at all - and reading a key that is not there is
	an undefined-key warning, which is fatal in Nino. What followed was
	worse: cleanUri() cut the path with strtok(), which answers false for a
	string holding nothing but delimiters, so '' and '#' were a TypeError
	thrown inside request() before anything could answer. Measured against
	this kernel: all three ended in a TypeError, and '#frag' answered with
	'frag' - the fragment standing in for the path, because strtok() skips
	leading delimiters too	*/
check( 'a request naming no method and no uri is answered rather than thrown on', resolvedUri( $appData, [] ) === '/' );
$nameless = [];
try { \Nino\Http::request( $appData, $nameless ); } catch( \Throwable $e ) {}
check( '...with no method, which is what matches no route', ( $nameless['/nino/http/request']['method'] ?? 'unset' ) === '' );

check( 'an empty REQUEST_URI is the home uri', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '' ] ) === '/' );
check( '...and so is one that is only a fragment marker', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '#' ] ) === '/' );
check( 'a uri starting with a fragment marker does not route to the fragment', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '#frag' ] ) === '/' );
check( '...nor one starting with a query marker to the query', resolvedUri( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '?a=b' ] ) === '/' );

// The cut itself is unchanged: the path is what precedes the first '?' or '#'
check( 'the path is still everything before the query and the fragment', ninoVisit( $appData, '/a/b?x=1#f' )['/nino/http/request']['uri'] === '/a/b' );
check( '...whichever of the two comes first', ninoVisit( $appData, '/a/b#f?x=1' )['/nino/http/request']['uri'] === '/a/b' );
check( '...and the query is still read off the raw uri', ( ninoVisit( $appData, '/a/b?x=1' )['/nino/http/request']['query']['x'] ?? '' ) === '1' );
check( '...and a trailing slash is still dropped', ninoVisit( $appData, '/a/b/' )['/nino/http/request']['uri'] === '/a/b' );

/*	Http::getClientIp() - who the visitor is behind a reverse proxy.

	REMOTE_ADDR is the proxy's own address for every single visitor there, so
	every per-ip rule in the site (Mail's send cap, the login cooldown - a
	session is not pinned to an address) counts the internet as one client. X-Forwarded-For
	carries the visitor - and is a request header anyone can write, so it is
	read only where the peer is a proxy this site was told about	*/
$proxyServer = [ $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null ];

$_SERVER['REMOTE_ADDR'] 					= '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] 	= '198.51.100.7';

$noProxyAppData = $appData;
$noProxyAppData['/nino/http/proxies'] = [];
check( 'with no proxy configured a forwarded address is ignored', \Nino\Http::getClientIp( $noProxyAppData ) === '203.0.113.9' );
check( '...and for a caller that passes no appData at all', \Nino\Http::getClientIp() === '203.0.113.9' );

$proxyAppData = $appData;
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.9' ];
check( 'a forwarded address is read where the peer is a configured proxy', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// What the rest of the request sees - the ip field is where every consumer
// reads it from
$proxiedRequest = ninoVisit( $proxyAppData, '/' );
check( 'the resolved address is what the request carries', ( $proxiedRequest['/nino/http/request']['ip'] ?? '' ) === '198.51.100.7' );

// Only the rightmost hop was written by our own proxy. Everything left of it
// is whatever the client sent, because a forwarding proxy appends rather than
// verifies - a client that writes its own chain cannot pick its address
$_SERVER['HTTP_X_FORWARDED_FOR'] = '10.9.9.9, 198.51.100.7';
check( 'the rightmost hop wins over the ones a client put in front of it', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// Several of our own proxies in a row: the walk stops at the first address
// none of them is
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.9', '10.0.0.0/8' ];
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.5, 10.0.0.6';
check( 'a chain of trusted proxies is walked back to the client', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// A cidr range is its first bits, not its text: the neighbouring network is
// not in 10.0.0.0/8 however similar it reads
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 11.0.0.5';
check( 'an address outside the configured range is the client itself', \Nino\Http::getClientIp( $proxyAppData ) === '11.0.0.5' );

// ...and a prefix that ends inside a byte is honoured to the bit
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.0/26' ];
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
check( 'a peer inside a part-byte cidr range is trusted', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.70';
check( '...and one past its end is not', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.70' );

// A v6 range never matches a v4 address, and the other way round: the packed
// forms differ in length before a single bit is compared
$proxyAppData['/nino/http/proxies'] = [ '2001:db8::/32' ];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
check( 'a v4 peer is not trusted by a v6 range', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.9' );

// The same v6 address written two ways is one address, so the list is
// compared packed rather than as text
$proxyAppData['/nino/http/proxies'] = [ '2001:0db8:0000:0000:0000:0000:0000:0009' ];
$_SERVER['REMOTE_ADDR'] = '2001:db8::9';
check( 'a v6 peer is recognized whatever notation the list uses', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

// A port some proxies append, and the spacing the header is written with
$proxyAppData['/nino/http/proxies'] = [ '203.0.113.9', '10.0.0.0/8' ];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '  198.51.100.7:53238 ,10.0.0.5';
check( 'a port on a forwarded address is dropped', \Nino\Http::getClientIp( $proxyAppData ) === '198.51.100.7' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = '[2001:db8::7]:53238, 10.0.0.5';
check( '...including the bracketed v6 form a port needs', \Nino\Http::getClientIp( $proxyAppData ) === '2001:db8::7' );

// Anything that is not an address is dropped rather than returned: a hop
// cannot smuggle in a host name, a range, or a rate-limit key of its choosing
$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip, 10.0.0.5';
check( 'a hop that is not an ip address is skipped', \Nino\Http::getClientIp( $proxyAppData ) === '10.0.0.5' );

$_SERVER['HTTP_X_FORWARDED_FOR'] = '';
check( 'an empty header leaves the peer as the client', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.9' );

// The list is what decides, not the header: an unlisted peer is the client
// even where the header looks exactly as a proxy would have written it
$_SERVER['REMOTE_ADDR'] = '192.0.2.50';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
check( 'a peer that is no listed proxy is the client, header or not', \Nino\Http::getClientIp( $proxyAppData ) === '192.0.2.50' );

// A typo in the list matches nothing rather than everything - the failure
// mode of trusting a header is worse than the one of ignoring it
$proxyAppData['/nino/http/proxies'] = [ 'cloudflare', '', '10.0.0.0/999', 42 ];
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
check( 'an unusable list entry trusts nothing', \Nino\Http::getClientIp( $proxyAppData ) === '203.0.113.9' );

[ $_SERVER['REMOTE_ADDR'], $forwardedBefore ] = $proxyServer;
if( $forwardedBefore === null )
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
else
	$_SERVER['HTTP_X_FORWARDED_FOR'] = $forwardedBefore;

$basicHeader = 'Basic '. base64_encode( 'editor@example.com:secret:with-colons' );
$basicRequest = ninoVisit( $appData, '/.nino/auth/login', 'POST', [ 'HTTP_AUTHORIZATION' => $basicHeader ] );
check( 'Http::request keeps a CGI/FastCGI Authorization header', ( $basicRequest['/nino/http/request']['header']['Authorization'] ?? '' ) === $basicHeader );
check( 'Http::request decodes Basic credentials when PHP_AUTH_* is absent',
	( $basicRequest['/nino/http/request']['user'] ?? '' ) === 'editor@example.com'
	&& ( $basicRequest['/nino/http/request']['pw'] ?? '' ) === 'secret:with-colons' );

$nativeBasicRequest = ninoVisit( $appData, '/.nino/auth/login', 'POST', [
	'PHP_AUTH_USER' 			=> 'native@example.com',
	'PHP_AUTH_PW' 				=> 'native-secret',
	'HTTP_AUTHORIZATION'	=> $basicHeader,
] );
check( 'PHP_AUTH_* stays authoritative when the SAPI already parsed Basic auth',
	( $nativeBasicRequest['/nino/http/request']['user'] ?? '' ) === 'native@example.com'
	&& ( $nativeBasicRequest['/nino/http/request']['pw'] ?? '' ) === 'native-secret' );

$malformedBasicRequest = ninoVisit( $appData, '/.nino/auth/login', 'POST', [ 'HTTP_AUTHORIZATION' => 'Basic not!base64' ] );
check( 'a malformed Basic header produces no partial credentials',
	( $malformedBasicRequest['/nino/http/request']['user'] ?? '' ) === ''
	&& ( $malformedBasicRequest['/nino/http/request']['pw'] ?? '' ) === '' );

/*	Where Apache hands a cgi/cgi-fcgi script no Authorization header at all -
	an Apache older than CGIPassAuth (2.4.13), or a php-cgi wrapper the
	directive does not cover - the .htaccess copies it into an environment
	variable instead, and Apache prefixes a variable set during an internal
	redirect with REDIRECT_, once per redirect. The login used to see nothing
	on exactly the hosts the documented fallback was written for */
$redirectedBasicRequest = ninoVisit( $appData, '/.nino/auth/login', 'POST', [ 'REDIRECT_HTTP_AUTHORIZATION' => $basicHeader ] );
check( 'Basic credentials survive the REDIRECT_ prefix Apache\'s rewrite fallback produces',
	( $redirectedBasicRequest['/nino/http/request']['user'] ?? '' ) === 'editor@example.com'
	&& ( $redirectedBasicRequest['/nino/http/request']['pw'] ?? '' ) === 'secret:with-colons' );

$nestedBasicRequest = ninoVisit( $appData, '/.nino/auth/login', 'POST', [ 'REDIRECT_REDIRECT_HTTP_AUTHORIZATION' => $basicHeader ] );
check( '...and the second prefix a nested redirect adds',
	( $nestedBasicRequest['/nino/http/request']['user'] ?? '' ) === 'editor@example.com' );

// A request header lands in $_SERVER as HTTP_<NAME>, so the one name a client
// could try reads as HTTP_REDIRECT_HTTP_AUTHORIZATION and matches nothing
$forgedBasicRequest = ninoVisit( $appData, '/.nino/auth/login', 'POST', [ 'HTTP_REDIRECT_HTTP_AUTHORIZATION' => $basicHeader ] );
check( 'a client cannot reach that path with a header of its own',
	( $forgedBasicRequest['/nino/http/request']['user'] ?? '' ) === ''
	&& ( $forgedBasicRequest['/nino/http/request']['pw'] ?? '' ) === '' );

$emptyRedirectRequest = ninoVisit( $appData, '/.nino/auth/login', 'POST', [ 'REDIRECT_HTTP_AUTHORIZATION' => '' ] );
check( 'an empty variant is skipped rather than read as a credential',
	( $emptyRedirectRequest['/nino/http/request']['user'] ?? '' ) === '' );

check( 'the shipped .htaccess carries both halves of the Apache workaround, and says whether it is applied',
	str_contains( $htaccess = (string) @file_get_contents( __DIR__. '/../.htaccess' ), 'CGIPassAuth On' ) === true
	&& str_contains( $htaccess, 'E=HTTP_AUTHORIZATION:%{HTTP:Authorization}' ) === true
	&& str_contains( $htaccess, 'SetEnv NINO_HTACCESS 1' ) === true );

/*	The two rules that make a fresh install answer at all on Apache. Without
	the first, "/" finds no index file on a host whose php configuration does
	not add one to Apache's list, falls through to the directory listing and is
	refused by the Options line - a homepage that 403s while /index.php answers
	with the project's own 404 page. Without the second, every other address is
	the server's 404 rather than a route. Both are the line router.php already
	draws for the development server */
check( 'the shipped .htaccess names the index file that answers "/"',
	str_contains( $htaccess, 'DirectoryIndex index.php' ) === true );

/*	\Nino\Images writes a webp wherever gd can, so the file has to say what
	that is: every mime.types of the last decade carries the type and the line
	then changes nothing, but an older host without it hands the image back as
	a download. Php's own development server needs no help here - it answers
	image/webp by itself, which is why router.php carries no counterpart	*/
check( '...and declares the type of the images the kernel writes',
	str_contains( $htaccess, 'AddType image/webp .webp' ) === true );
check( '...and forwards everything that is neither file nor directory to it',
	str_contains( $htaccess, 'RewriteCond %{REQUEST_FILENAME} !-f' ) === true
	&& str_contains( $htaccess, 'RewriteCond %{REQUEST_FILENAME} !-d' ) === true
	&& str_contains( $htaccess, 'RewriteRule . /index.php [L]' ) === true );

/*	...to an absolute url path, which is the whole of it. The relative form
	reads like the portable one - Apache resolves it against the directory the
	file sits in - and on some hosts the per-directory prefix it strips is not
	the one it puts back: the substitution resolves to nothing, Apache retries,
	and every address that needs forwarding 500s at ten internal redirects.
	Measured on an IONOS host, where this one character was the difference
	between a site and a stack of 500s. A subdirectory install edits the line
	(/shop/index.php); there is no form that is both absolute and
	location-independent, so the file takes the one that works everywhere	*/
check( '...as an absolute url path, not a relative one',
	str_contains( $htaccess, 'RewriteRule . index.php' ) === false );

/*	Four directories a checkout ships hold nothing a browser ever asks for:
	the project's classes, the installed features, the wizard's library and
	the suites. Each is denied twice - its own .htaccess for Apache, a rule
	in router.php for php's development server, which applies no .htaccess
	at all. tests/ had neither: over http, a suite booted the kernel
	against a sandbox and ran every check for whoever asked, seconds of cpu
	and a temp directory per request, the sandbox path printed back. Held as
	a pair: a directory denied on one server and open on the other is the
	shape the next omission takes	*/
$undenied = [];
$router 	= (string) file_get_contents( __DIR__. '/../router.php' );
foreach( [ 'app', 'features', 'tests', '_admin/install/library' ] as $shippedSource ) {
	if( str_contains( (string) @file_get_contents( __DIR__. '/../'. $shippedSource. '/.htaccess' ), 'Require all denied' ) === false )
		$undenied[] = $shippedSource. '/.htaccess';
	if( str_contains( $router, "'#^/". $shippedSource. "(?:/|$)#'" ) === false )
		$undenied[] = $shippedSource. ' in router.php';
}
check( 'every shipped directory a browser never needs is denied for Apache and for the development server alike'. ( $undenied === [] ? '' : ' - '. implode( ', ', $undenied ) ), $undenied === [] );

/*	...and the stop in front of it. mod_rewrite runs per-directory before the
	url is mapped, and where %{REQUEST_FILENAME} is not the mapped path there,
	!-f stays true for index.php itself: the catch-all rewrites its own result
	until Apache gives up at ten internal redirects with a 500. The symptom is
	that "/" and "/_admin/" answer (mod_dir resolves those without a rewrite)
	and every other address 500s. The guard has to come first to be one */
$guard		= strpos( $htaccess, 'RewriteRule ^index\.php$ - [L]' );
$catchAll	= strpos( $htaccess, 'RewriteRule . /index.php [L]' );

check( 'the front controller cannot rewrite its own result into a loop', $guard !== false );
check( '...because that stop stands ahead of the catch-all', $guard !== false && $catchAll !== false && $guard < $catchAll );

/*	A dot uri is a route here, not a file: /.nino/auth/login is the workbench
	login, /.form the contact form, /.newsletter and /.protected belong to
	features. All three deployments say the same sentence - deny a dot path
	only where it resolves to something on disk - and only one of them said
	it in a way Apache reads: the shipped .htaccess denied the pattern
	outright, and Apache stops its directory walk at the first component that
	does not exist and tests <FilesMatch> against that one. '/.nino/auth/login'
	therefore matched as '.nino'. Measured against Apache 2.4.58: every one of
	those routes answered 403 before php saw the request, while
	/gibt-es-nicht-12345 reached index.php - which is what made it look like a
	host problem rather than this file	*/
$denyStart = strpos( $htaccess, '<FilesMatch "^\\.">' );
$existsIf  = strpos( $htaccess, '<If "-f %{REQUEST_FILENAME} || -d %{REQUEST_FILENAME}">' );

check( 'the .htaccess denies a dotfile only where one exists, so a dot route still reaches the front controller',
	$existsIf !== false && $denyStart !== false && $existsIf < $denyStart
	&& strpos( $htaccess, '</If>', $denyStart ) !== false );

// The other half of the same sentence, so a change to one stands out
// against the other
$router = (string) @file_get_contents( __DIR__. '/../router.php' );

check( '...and the development server draws the same line', str_contains( $router, 'is_file( __DIR__. $uri ) === true' ) === true );

$appData['./nino/jstext/nonce'] = base64_encode( random_bytes( 16 ) );
\Nino\Modules\Jstext::callbackResponse( $appData, $homeRequest );
$jstextCsp = $homeRequest['/nino/http/response']['header']['Content-Security-Policy'];
check( 'Jstext appends its script-src to the csp', str_contains( $jstextCsp, "script-src 'self' 'nonce-" ) === true );
check( '...once, and only where the policy has none of its own', substr_count( $jstextCsp, 'script-src' ) === 1 );

/*	A route may declare header fields of its own - that is what a route's
	'header' is for, and Http::response() merges them into the seeded policy
	before these callbacks run. A route that declares a script-src used to get
	a second one appended, and a repeated directive is not a merge: the first
	occurrence is the one a browser enforces and every later one is ignored.
	So the nonce sat in a directive nothing read, the inline jstext block was
	refused as an unlisted inline script, and Nino.content.getText() answered
	'' for every key on that page - silently, because the page renders and the
	policy is honoured, only the words are missing	*/
$ownPolicyRequest = [ '/nino/http/response' => [ 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'self' https://cdn.example" ] ] ];
\Nino\Modules\Jstext::callbackResponse( $appData, $ownPolicyRequest );
$ownPolicy = $ownPolicyRequest['/nino/http/response']['header']['Content-Security-Policy'];
check( 'a route with a script-src of its own gets the nonce in that one', substr_count( $ownPolicy, 'script-src' ) === 1
	&& str_contains( $ownPolicy, "script-src 'self' https://cdn.example 'nonce-" ) === true );
check( '...and keeps the rest of what it declared', str_starts_with( $ownPolicy, "default-src 'self'; " ) === true );

// 'none' is the one value that means the project decided against inline
// scripts. A nonce beside it would not merge with that decision but overturn
// it - 'none' is ignored the moment anything stands next to it
$noneRequest = [ '/nino/http/response' => [ 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'none'" ] ] ];
\Nino\Modules\Jstext::callbackResponse( $appData, $noneRequest );
check( "a script-src of 'none' is left as it is", $noneRequest['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'; script-src 'none'" );

// ...and an empty policy is still the one case that must not start from ''
$emptyRequest = [ '/nino/http/response' => [ 'header' => [ 'Content-Security-Policy' => '' ] ] ];
\Nino\Modules\Jstext::callbackResponse( $appData, $emptyRequest );
check( 'an empty policy gets the directive and no stray separator', $emptyRequest['/nino/http/response']['header']['Content-Security-Policy'] === "script-src 'self' 'nonce-". $appData['./nino/jstext/nonce']. "'" );

/*	The nonce is hex: a base64-value token, as a nonce must be, written with
	no character json_encode() or html escapes - so it reads the same wherever
	a module writes it, and Modules\Cache::_stamp(), which swaps it by its raw
	string, finds every copy	*/
$freshNonceData = $appData;
unset( $freshNonceData['./nino/jstext/nonce'] );
\Nino\Modules\Jstext::init( $freshNonceData );
$freshNonce = (string) ( $freshNonceData['./nino/jstext/nonce'] ?? '' );
check( 'the jstext nonce is 16 bytes of hex', preg_match( '/^[0-9a-f]{32}$/', $freshNonce ) === 1 );
check( '...so json_encode leaves it exactly as the page carries it', json_encode( $freshNonce ) === '"'. $freshNonce. '"' );
check( 'Jstext keeps the default-src while doing so', str_contains( $jstextCsp, "default-src 'self'" ) === true );
check( 'the composed csp does not start with a stray separator', str_starts_with( $jstextCsp, ';' ) === false );

// The header is composed before anything can end the request: Modules\
// Maintenance answers from priority 1 and ends the request there, so a
// maintenance page - which renders the site's own footer, and with it
// [jstext] - used to ship an inline script the policy then refused, because
// the policy naming its nonce was added at priority 5 and never ran
$jstextProbe = [];
\Nino\Modules\Jstext::init( $jstextProbe );
$jstextPrios = [];
foreach( ( $jstextProbe['./nino/callbacks']['/nino/http/response'] ?? [] ) as $prio => $callbacks )
	foreach( $callbacks as $callback )
		if( is_array( $callback ) === true && str_ends_with( (string) ( $callback[0] ?? '' ), 'Modules\\Jstext' ) === true )
			$jstextPrios[] = $prio;
check( 'the csp is composed ahead of everything that can end a request', $jstextPrios !== [] && max( $jstextPrios ) < 1 );

// What the inline block carries. It used to be every fill the site has -
// including '/project/mail/address/owner', the mailbox a contact form delivers to, and
// every address and legal line a project keeps in its text files - on every
// public page, while the scripts reading it only ever ask for two groups
\Nino\Html::addFills( $appData, [
	'[[/module/form/info/success]]'	=> 'Danke!',
	'[[/project/mail/address/owner]]'		=> 'post@example.com',
	'[[/project/company/contact/street]]' 		=> 'Musterweg 1',
	'[[/template/common/slider/prev]]'	=> 'Zurück',
	'[[/feature/newsletter/info/success]]'	=> 'Eingetragen',
	'[[/template/common/label/phone]]'	=> 'Telefon',
], '*' );
/** The block's own table, read back the way the browser reads it */
function jstextTable( array &$appData ): array {
	$block = \Nino\Modules\Jstext::doShortcode( $appData, [] );
	preg_match( '/NinoJstext=(.*);<\/script>/', $block, $found );
	return json_decode( $found[1] ?? '[]', true ) ?? [];
}

$jstextTable = jstextTable( $appData );
check( 'the inline block carries the words the shipped scripts ask for', ( $jstextTable['/module/form/info/success'] ?? null ) === 'Danke!' );
check( '...and not the mailbox a form delivers to, nor the rest of the site\'s text', isset( $jstextTable['/project/mail/address/owner'] ) === false
	&& isset( $jstextTable['/project/company/contact/street'] ) === false );
// What the module publishes when nobody asked for more is its private DEFAULT_KEYS,
// and the public script's words are read against exactly those
$jstextGroups = ( new ReflectionClassConstant( '\Nino\Modules\Jstext', 'DEFAULT_KEYS' ) )->getValue();
check( 'the three groups are the form\'s messages, the newsletter\'s and the slider\'s words - and a label like the phone\'s is none of them', ( $jstextTable['/template/common/slider/prev'] ?? null ) === 'Zurück'
	&& ( $jstextTable['/feature/newsletter/info/success'] ?? null ) === 'Eingetragen' && isset( $jstextTable['/template/common/label/phone'] ) === false
	&& $jstextGroups === [ '/module/form/info/', '/feature/newsletter/info/', '/template/common/slider/' ] );
check( 'the public script asks for exactly those groups', preg_match_all( '/getText\(\s*\'(\/[^\']*)\'/', (string) file_get_contents( __DIR__. '/../_nino/Nino.ui.js' ), $jstextAsked ) > 0
	&& array_filter( $jstextAsked[1], static fn( string $key ): bool => array_filter( $jstextGroups, static fn( string $group ): bool => str_starts_with( $key, $group ) ) === [] ) === [] );

// The nonce belongs in the script tag and the policy, nowhere else: no script
// reads it from the table, and every copy of it is one more place a stored
// page has to be stamped again
$jstextBlock = \Nino\Modules\Jstext::doShortcode( $appData, [] );
check( 'the table carries no nonce - the script tag is the one place in the block that does', isset( $jstextTable['/nino/jstext/nonce'] ) === false
	&& substr_count( $jstextBlock, (string) $appData['./nino/jstext/nonce'] ) === 1
	&& str_starts_with( $jstextBlock, '<script nonce="'. $appData['./nino/jstext/nonce']. '">' ) === true );

// A project or a feature whose own script reads a fill says so
$appData[ \Nino\Modules\Jstext::KEYS ] = [ '/project/company/' ];
$jstextConfigured = jstextTable( $appData );
check( 'a project may publish a group of its own', ( $jstextConfigured['/project/company/contact/street'] ?? null ) === 'Musterweg 1'
	&& isset( $jstextConfigured['/project/mail/address/owner'] ) === false );
unset( $appData[ \Nino\Modules\Jstext::KEYS ] );

\Nino\Modules\Jstext::publish( $appData, [ '/project/mail/address/' ] );
check( '...and a module registers one for the request it is serving', isset( jstextTable( $appData )['/project/mail/address/owner'] ) === true );
unset( $appData['./nino/jstext/keys'] );

// The last-resort 404 fallback, ie. a project without its own /404 route.
// Written as '.uri' it merged a stray key in and left the response uri on the
// unmatched request path, so every [[/_nino/webpage[[/nino/http/response/uri]]/...]]
// fill on that page resolved against a webpage that does not exist
$noFallbackAppData = $appData;
unset( $noFallbackAppData['/nino/http/routes']['GET://404'] );
$unmatchedRequest = ninoVisit( $noFallbackAppData, '/no-such-page-xyz' );
\Nino\Http::response( $noFallbackAppData, $unmatchedRequest );
check( 'a project without a /404 route still answers 404', $unmatchedRequest['/nino/http/response']['statusCode'] === 404 );
check( '...and points the response uri at /404 rather than the unmatched path', ( $unmatchedRequest['/nino/http/response']['uri'] ?? '' ) === '/404' );
check( '...leaving no stray \'.uri\' key behind', isset( $unmatchedRequest['/nino/http/response']['.uri'] ) === false );

// A route entry without a 'uri' is a hand-edited config.php away, and an
// "Undefined array key" here is an engine-raised level, ie. fatal per
// Runtime::handleError() - one config typo used to 500 every locale switch
// Prepended, so the loop has to walk past it before reaching the match it
// is looking for. The return value alone proves nothing here: these tests
// call the kernel directly and never install Runtime's error handler, so the
// "Undefined array key" this used to raise is a warning the run survives -
// while in a real request that same level is fatal. The raised level is
// therefore what gets asserted.
$uriLessAppData = $appData;
$uriLessAppData['/nino/http/routes'] = [ 'GET://broken-entry' => [ 'body' => 'no uri key here' ] ] + $appData['/nino/http/routes'];

ninoWarnings();
$foundGerman  = \Nino\Http::findRouteUri( $uriLessAppData, '/legal', 'de_DE' );
$foundNothing = \Nino\Http::findRouteUri( $uriLessAppData, '/nowhere', 'de_DE' );
$routeWarnings = ninoWarnings();

check( 'findRouteUri() raises nothing on a route entry without a uri', $routeWarnings === [] );
check( '...walks past it to the route that does match', $foundGerman === 'GET://rechtliches' );
check( '...and still returns null for a response uri no route renders', $foundNothing === null );

$robotsRequest = ninoVisit( $appData, '/robots.txt' );
\Nino\Http::response( $appData, $robotsRequest );
check( 'a route-level header extends the response header', ( $robotsRequest['/nino/http/response']['header']['Content-Type'] ?? '' ) === 'text/plain; charset=utf-8' );
check( 'a route-level header does not wipe the seeded security headers', isset( $robotsRequest['/nino/http/response']['header']['X-Content-Type-Options'] ) === true );

$localesRedirect = ninoVisit( $appData, '/legal?/_nino/locales/current=de_DE' );
\Nino\Http::response( $appData, $localesRedirect );
check( 'a locale switch redirects with a 302 status code even on a route with its own statusCode', $localesRedirect['/nino/http/response']['statusCode'] === 302 );
check( 'the Location points at the locale variant of the page, without the route-key method prefix', ( $localesRedirect['/nino/http/response']['header']['Location'] ?? '' ) === '/rechtliches' );

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$pickerRedirect = ninoVisit( $appData, '/legal?/_nino/localepicker/current=de_DE' );
\Nino\Http::response( $appData, $pickerRedirect );
\Nino\Modules\Localepicker::callbackResponse( $appData, $pickerRedirect );
check( 'the localepicker redirects with a 302 status code too', $pickerRedirect['/nino/http/response']['statusCode'] === 302 );
check( 'and its Location points at the locale variant of the page', ( $pickerRedirect['/nino/http/response']['header']['Location'] ?? '' ) === '/rechtliches' );

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$arrayLocaleRequest = ninoVisit( $appData, '/legal?/_nino/locales/current[]=de_DE' );
\Nino\Http::response( $appData, $arrayLocaleRequest );
check( 'an array-shaped locale query is ignored instead of causing a TypeError', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US' && $arrayLocaleRequest['/nino/http/response']['statusCode'] === 201 );

$arrayPickerRequest = ninoVisit( $appData, '/legal?/_nino/localepicker/current[]=de_DE' );
\Nino\Http::response( $appData, $arrayPickerRequest );
\Nino\Modules\Localepicker::callbackResponse( $appData, $arrayPickerRequest );
check( 'the localepicker also ignores an array-shaped locale query', \Nino\Locales::getCurrentLocale( $appData ) === 'en_US' && $arrayPickerRequest['/nino/http/response']['statusCode'] === 201 );

// A language is named by the fill after its code, /_nino/locale/<code>/name, which the
// module's install unit delivers for the two languages the wizard offers and the
// Language panel for one it adds
\Nino\Html::addFills( $appData, [ '[[/_nino/locale/de_DE/name]]' => 'Deutsch', '[[/_nino/locale/en_US/name]]' => 'English (US)' ], '*' );
$pickerHtml = \Nino\Html::renderHtml( $appData, \Nino\Modules\Localepicker::doShortcode( $appData, [] ) );
check( 'the localepicker names each language from /_nino/locale/<code>/name', str_contains( $pickerHtml, '>Deutsch</a>' ) === true && str_contains( $pickerHtml, '>English (US)</a>' ) === true
	&& str_contains( $pickerHtml, '/_nino/locale/' ) === false );

/*	The two switches are one method under two keys now. Modules\Localepicker's
	callback used to be a verbatim copy of the kernel's - every line and every
	comment, differing in the query key alone - which is two places to fix
	whenever one of them turns out to be wrong	*/
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$ownKeyRequest = ninoVisit( $appData, '/legal?/_project/language=de_DE' );
\Nino\Http::response( $appData, $ownKeyRequest );
\Nino\Locales::switchFromQuery( $appData, $ownKeyRequest, '/_project/language' );
check( 'the locale switch is one method taking the query key, so a project can answer under its own', $ownKeyRequest['/nino/http/response']['statusCode'] === 302
	&& ( $ownKeyRequest['/nino/http/response']['header']['Location'] ?? '' ) === '/rechtliches'
	&& \Nino\Locales::getCurrentLocale( $appData ) === 'de_DE' );
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );

$queryParser = new ReflectionMethod( '\Nino\Http', '_getRequestQueryVarsPart' );
$queryParser->setAccessible( true );
check( 'a URL parse failure produces an empty query array', $queryParser->invoke( null, 'http://[' ) === [] );
check( 'requestRoute rejects an empty URI without entering its parent walk', \Nino\Http::requestRoute( $appData, '', 'GET' ) === null );
check( 'requestRoute rejects a relative URI without entering its parent walk', \Nino\Http::requestRoute( $appData, 'relative/path', 'GET' ) === null );

/*	The signed-in account's mail address, the one fill \Nino\request()
	registers that carries something a person typed. It is built here through
	that call rather than by hand, and read back through the markup the
	workbench's rail actually uses	*/
$hostileMail = '"<script>alert(1)</script>[x]"@example.com';
check( 'php calls an address carrying a script element and a bracket valid, so the FILTER_VALIDATE_EMAIL every account panel runs stores one', filter_var( $hostileMail, FILTER_VALIDATE_EMAIL ) !== false );

ninoUser( $appData, $hostileMail, [] );
\Nino\request( $appData, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1' ] );

// The fills a request registers are the ones the kernel names as its runtime
// fills, and the names are what the Text panel's scan reads - so a fill
// added to one and not the other cannot happen: both are the same two lists
$registeredFills = array_keys( $appData['./nino/html/fills']['*'] ?? [] );
$namedFills = method_exists( '\\Nino\\Html', 'runtimeFillKeys' ) === true ? array_map( fn( string $key ): string => '[['. $key. ']]', \Nino\Html::runtimeFillKeys( $appData ) ) : [];
check( 'every fill the kernel names as a runtime fill is registered by a request, the clean uri included', $namedFills !== [] && in_array( '[[/nino/http/response/uri/clean]]', $namedFills, true ) === true && array_diff( $namedFills, $registeredFills ) === [] );
check( '...and the year is one of them, under /nino/ like the rest: a runtime fill is never a text key', in_array( '[[/nino/date/year]]', $namedFills, true ) === true
	&& array_filter( $namedFills, static fn( string $fill ): bool => str_starts_with( $fill, '[[/nino/' ) === false ) === [] );

$railSpan = \Nino\Html::renderHtml( $appData, '<span id="admin-user-email">[[/nino/auth/user]]</span>' );
check( 'the address reaches the page as the text it is, never as markup', str_contains( $railSpan, '<script' ) === false && str_contains( $railSpan, '&lt;script&gt;' ) === true );
check( '...brackets neutralized as well, so the shortcode pass over the finished document cannot read one as syntax', str_contains( $railSpan, '[x]' ) === false && str_contains( $railSpan, '&#91;x&#93;' ) === true );
check( 'and the shell draws the address through exactly that fill', str_contains( (string) @file_get_contents( dirname( __DIR__ ). '/_admin/templates/page-index.tpl' ), '<span id="admin-user-email">[[/nino/auth/user]]</span>' ) === true );

\Nino\Auth::logoutUser( $appData );
\Nino\Auth::deleteUser( $appData, $hostileMail );

echo "\n";


// --- Http::pages, matchesPath and answersHtml --------------------------------

echo "Http::pages, matchesPath and answersHtml - the routes as a feature reads them\n";

$pageTable = [
	'GET://'						=> [ 'uri' => '/home', 'body' => '' ],
	'GET://about'				=> [ 'uri' => '/about-us', 'body' => '', 'locale' => 'en_US' ],
	'GET://blog/*'			=> [ 'uri' => '/blog', 'body' => '' ],
	'GET://404'					=> [ 'uri' => '/404', 'body' => '', 'statusCode' => 404 ],
	'GET://_admin'			=> [ 'uri' => '/_admin', 'body' => '' ],
	'GET://_admin/*'		=> [ 'uri' => '/_admin', 'body' => '' ],
	'GET://.form'				=> [ 'uri' => '/.form', 'body' => '' ],
	'POST://contact'		=> [ 'uri' => '/contact', 'body' => '' ],
	'GET://robots.txt'	=> [ 'uri' => '/robots.txt', 'body' => '', 'header' => [ 'Content-Type' => 'text/plain; charset=utf-8' ] ],
	'GET://*'						=> [ 'uri' => '/everything', 'body' => '' ],
	'GET://two words'		=> [ 'uri' => '/two-words', 'body' => '' ],
	'GET://a*b'					=> [ 'uri' => '/a-b', 'body' => '' ],
	'GET:///evil.example/x'	=> [ 'uri' => '/evil', 'body' => '' ],
	'GET:///*'					=> [ 'uri' => '/evil-tree', 'body' => '' ],
	'GET://\\evil.example'	=> [ 'uri' => '/evil-too', 'body' => '' ],
	'GET://bare'				=> [ 'body' => '' ],
	'GET://scalar'			=> 'a route that is no array',
	7										=> [ 'uri' => '/seven', 'body' => '' ],
];

check( 'pages() answers a row for every page of a route table, in its order, with the fields a feature lists or matches by - a GET route that is a public address, and none for another method, the workbench, a module endpoint, a path with a space or a star in it, a path a browser reads as another host (//host, /\host), a route that is no array, a key that is no string', \Nino\Http::pages( $pageTable ) === [
	[ 'path' => '/', 'wildcard' => false, 'uri' => '/home', 'locale' => '', 'status' => 200, 'html' => true ],
	[ 'path' => '/about', 'wildcard' => false, 'uri' => '/about-us', 'locale' => 'en_US', 'status' => 200, 'html' => true ],
	[ 'path' => '/blog', 'wildcard' => true, 'uri' => '/blog', 'locale' => '', 'status' => 200, 'html' => true ],
	[ 'path' => '/404', 'wildcard' => false, 'uri' => '/404', 'locale' => '', 'status' => 404, 'html' => true ],
	[ 'path' => '/robots.txt', 'wildcard' => false, 'uri' => '/robots.txt', 'locale' => '', 'status' => 200, 'html' => false ],
	[ 'path' => '/', 'wildcard' => true, 'uri' => '/everything', 'locale' => '', 'status' => 200, 'html' => true ],
	[ 'path' => '/bare', 'wildcard' => false, 'uri' => '/bare', 'locale' => '', 'status' => 200, 'html' => true ],
] );
check( '...a list, and none for a table that holds no page', array_is_list( \Nino\Http::pages( $pageTable ) ) === true && \Nino\Http::pages( [] ) === [] && \Nino\Http::pages( [ 'POST://x' => [ 'uri' => '/x' ] ] ) === [] );

// Cache's blacklist, a sitemap's exclusions and the paths of a protected area are one match
$matchList = [ '/contact', '/blog/*' ];
foreach( [ '/contact' => true, '/blog' => true, '/blog/post-1' => true, '/blog/2026/one' => true, '/contacts' => false, '/blogging' => false, '/home' => false ] as $matchUri => $expectMatch )
	check( 'matchesPath: '. $matchUri. ' '. ( $expectMatch === true ? 'matches' : 'does not match' ). ' [ \'/contact\', \'/blog/*\' ]', \Nino\Http::matchesPath( $matchUri, $matchList ) === $expectMatch );
check( 'a pattern is editable text: one that looks like a regular expression is matched literally, never evaluated', \Nino\Http::matchesPath( '/home', [ '/.*' ] ) === false && \Nino\Http::matchesPath( '/.*', [ '/.*' ] ) === true );
check( 'a pattern that is no string or is empty matches nothing, and neither does an empty list', \Nino\Http::matchesPath( '/home', [ '', null, 7, [ '/home' ] ] ) === false && \Nino\Http::matchesPath( '/home', [] ) === false && \Nino\Http::matchesPath( '', [ '' ] ) === false );

// answersHtml(): the first Content-Type decides, none is html
check( 'a route that names no header answers html', \Nino\Http::answersHtml( [] ) === true && \Nino\Http::answersHtml( [ 'uri' => '/x' ] ) === true && \Nino\Http::answersHtml( [ 'header' => [] ] ) === true && \Nino\Http::answersHtml( [ 'header' => [ 'X-Other' => 'text/plain' ] ] ) === true );
check( '...so does text/html, with a charset or without, in any case of letters and with space around', \Nino\Http::answersHtml( [ 'header' => [ 'Content-Type' => 'text/html' ] ] ) === true
	&& \Nino\Http::answersHtml( [ 'header' => [ 'Content-Type' => 'Text/HTML; charset=utf-8' ] ] ) === true && \Nino\Http::answersHtml( [ 'header' => [ 'Content-Type' => ' text/html ' ] ] ) === true );
check( '...and the header is found in any case of letters too', \Nino\Http::answersHtml( [ 'header' => [ 'content-type' => 'text/plain' ] ] ) === false && \Nino\Http::answersHtml( [ 'header' => [ 'CONTENT-TYPE' => 'text/html' ] ] ) === true );
check( '...text/plain, application/json and the like do not', \Nino\Http::answersHtml( [ 'header' => [ 'Content-Type' => 'text/plain; charset=utf-8' ] ] ) === false && \Nino\Http::answersHtml( [ 'header' => [ 'Content-Type' => 'application/json' ] ] ) === false
	&& \Nino\Http::answersHtml( [ 'header' => [ 'Content-Type' => 'application/xhtml+xml' ] ] ) === false );
check( '...the first Content-Type named decides, and a value that is no string is no html', \Nino\Http::answersHtml( [ 'header' => [ 'content-type' => 'text/plain', 'Content-Type' => 'text/html' ] ] ) === false
	&& \Nino\Http::answersHtml( [ 'header' => [ 'Content-Type' => [ 'text/html' ] ] ] ) === false && \Nino\Http::answersHtml( [ 'header' => 'text/plain' ] ) === true );
check( 'a response answers it the same way, its header being the same map', \Nino\Http::answersHtml( $robotsRequest['/nino/http/response'] ) === false && \Nino\Http::answersHtml( $homeRequest['/nino/http/response'] ) === true );

echo "\n";


// --- Http::redirect ----------------------------------------------------------

echo "Http::redirect - the status, the Location, no-store and an empty body in one call\n";

$shop = $appData;
$shop['/nino/dir'] = '/shop';
$redirected = static fn(): array => [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [ 'X-Kept' => '1', 'Cache-Control' => 'max-age=60' ], 'body' => '<p>rendered for nothing</p>' ] ];

$redirectRequest = $redirected();
\Nino\Http::redirect( $shop, $redirectRequest, '/thanks?ok=1#top' );
check( 'a path of the site is sent under the directory the project is served from: 303, the Location, no-store and an empty body', $redirectRequest['/nino/http/response']['statusCode'] === 303
	&& $redirectRequest['/nino/http/response']['header']['Location'] === '/shop/thanks?ok=1#top' && $redirectRequest['/nino/http/response']['header']['Cache-Control'] === 'no-store' && $redirectRequest['/nino/http/response']['body'] === '' );
check( '...and the headers it does not set stay as they were', $redirectRequest['/nino/http/response']['header']['X-Kept'] === '1' );

$redirectRequest = $redirected();
\Nino\Http::redirect( $appData, $redirectRequest, '/thanks' );
$slashRoot = $appData;
$slashRoot['/nino/dir'] = '/';
$slashRequest = $redirected();
\Nino\Http::redirect( $slashRoot, $slashRequest, '/thanks' );
check( 'a project at the root sends the path as it is, whether its directory is written \'\' or \'/\'', $redirectRequest['/nino/http/response']['header']['Location'] === '/thanks' && $slashRequest['/nino/http/response']['header']['Location'] === '/thanks' );

$redirectRequest = $redirected();
\Nino\Http::redirect( $shop, $redirectRequest, 'https://example.org/elsewhere?x=1', 301 );
$plainRequest = $redirected();
\Nino\Http::redirect( $shop, $plainRequest, 'http://example.org/old' );
check( 'an absolute https url is sent as it is, under the status asked for - an http one too: a redirect rule sends a visitor to another site', $redirectRequest['/nino/http/response']['statusCode'] === 301
	&& $redirectRequest['/nino/http/response']['header']['Location'] === 'https://example.org/elsewhere?x=1' && $plainRequest['/nino/http/response']['header']['Location'] === 'http://example.org/old' );

$redirectRefused = [];
foreach( [ '//evil.example.com/x', '/\\evil.example.com/x', 'javascript:alert(1)', 'relative/path', '', 'ftp://example.org/', 'https://', 'http://', 'data:text/html,x', '/a:b', "/x\r\nSet-Cookie: a=b", '/x y', "/x\ty", 'https://exa mple.org/', 'https://user:pw@example.org/', 'mailto:me@example.org' ] as $redirectTarget ) {
	$redirectRequest = $redirected();
	$redirectErrors = ninoErrors( function() use ( &$shop, &$redirectRequest, $redirectTarget ) { \Nino\Http::redirect( $shop, $redirectRequest, $redirectTarget ); } );
	if( count( $redirectErrors ) !== 1 || $redirectErrors[0][0] !== E_USER_WARNING || $redirectRequest !== $redirected() )
		$redirectRefused[] = $redirectTarget;
}
check( 'anything but a path of the site or an http(s) url is a warning and changes nothing: another host written as a path, a scheme that runs, a relative path, a header split', $redirectRefused === [] );

$redirectStatus = [];
foreach( [ 301, 302, 303, 307, 308 ] as $status ) {
	$redirectRequest = $redirected();
	\Nino\Http::redirect( $shop, $redirectRequest, '/thanks', $status );
	if( $redirectRequest['/nino/http/response']['statusCode'] !== $status )
		$redirectStatus[] = $status;
}
check( 'the five statuses of a redirect are sent as given', $redirectStatus === [] );
$redirectStatus = [];
foreach( [ 200, 304, 404, 999, 0, -1 ] as $status ) {
	$redirectRequest = $redirected();
	$redirectErrors = ninoErrors( function() use ( &$shop, &$redirectRequest, $status ) { \Nino\Http::redirect( $shop, $redirectRequest, '/thanks', $status ); } );
	if( count( $redirectErrors ) !== 1 || $redirectErrors[0][0] !== E_USER_WARNING || $redirectRequest['/nino/http/response']['statusCode'] !== 303 || $redirectRequest['/nino/http/response']['header']['Location'] !== '/shop/thanks' )
		$redirectStatus[] = $status;
}
check( '...any other is a warning and the 303 a form post is sent on with - the redirect itself still happens', $redirectStatus === [] );

// The locale switch is the kernel's own redirect: it answers 302 as before, and the
// page the route would have rendered is not what the redirect carries
$appData['/nino/http/routes']['GET://legal']['body'] = '<p>the page</p>';
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
$switchRequest = ninoVisit( $appData, '/legal?/_nino/locales/current=de_DE' );
\Nino\Http::response( $appData, $switchRequest );
check( 'the locale switch is a redirect of this kind: 302, the Location of the other variant, no-store, and no page in the body', $switchRequest['/nino/http/response']['statusCode'] === 302
	&& $switchRequest['/nino/http/response']['header']['Location'] === '/rechtliches' && $switchRequest['/nino/http/response']['header']['Cache-Control'] === 'no-store' && $switchRequest['/nino/http/response']['body'] === '' );
$appData['/nino/http/routes']['GET://legal']['body'] = '';
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );

echo "\n";


// --- Http::allowSource -------------------------------------------------------

echo "Http::allowSource - widening the Content-Security-Policy of one response\n";

$cspPolicy	= static fn( array $request ): string => (string) ( $request['/nino/http/response']['header']['Content-Security-Policy'] ?? '' );
$cspWith		= static fn( mixed $policy ): array => [ '/nino/http/response' => [ 'header' => [ 'Content-Security-Policy' => $policy ] ] ];
$cspRefuses = static function( string $policy, string $directive, string $source ) use ( $cspPolicy, $cspWith ): bool {
	$request = $cspWith( $policy );
	$result = null;
	$errors = ninoErrors( function() use ( &$request, &$result, $directive, $source ) { $result = \Nino\Http::allowSource( $request, $directive, $source ); } );
	return $result === false && count( $errors ) === 1 && $errors[0][0] === E_USER_WARNING && $cspPolicy( $request ) === $policy;
};

$cspVisit = ninoVisit( $appData, '/' );
$cspKernel = $cspPolicy( $cspVisit );
check( 'the policy a request starts from is the kernel\'s, with no frame-src of its own', str_contains( $cspKernel, "default-src 'self'" ) === true && str_contains( $cspKernel, 'frame-src' ) === false );
check( 'allowSource() makes a directive the policy lacks from the one a browser would read instead, and adds the source to it', \Nino\Http::allowSource( $cspVisit, 'frame-src', 'https://player.example.com' ) === true
	&& $cspPolicy( $cspVisit ) === $cspKernel. "; frame-src 'self' https://player.example.com" );
check( '...appends to a directive the policy has, and leaves the others as they were', \Nino\Http::allowSource( $cspVisit, 'img-src', 'https://pictures.example.com' ) === true
	&& $cspPolicy( $cspVisit ) === str_replace( 'img-src * data:', 'img-src * data: https://pictures.example.com', $cspKernel ). "; frame-src 'self' https://player.example.com" );
check( '...and a source it lists already is not added again, in whatever case of letters', \Nino\Http::allowSource( $cspVisit, 'frame-src', 'HTTPS://Player.example.com' ) === false && substr_count( strtolower( $cspPolicy( $cspVisit ) ), 'https://player.example.com' ) === 1 );

$cspScripts = $cspWith( $cspKernel );
check( 'a directive built from its fallback is kept only if the source was new to it: script-src-elem after script-src changes nothing', \Nino\Http::allowSource( $cspScripts, 'script-src', 'https://cdn.example.com' ) === true
	&& \Nino\Http::allowSource( $cspScripts, 'script-src-elem', 'https://cdn.example.com' ) === false && str_contains( $cspPolicy( $cspScripts ), 'script-src-elem' ) === false );
check( '...and one the fallback did not name widens by a copy of the fallback\'s list', \Nino\Http::allowSource( $cspScripts, 'script-src-elem', 'https://other.example.com' ) === true
	&& str_ends_with( $cspPolicy( $cspScripts ), "; script-src-elem 'self' https://cdn.example.com https://other.example.com" ) === true );

$cspChild = $cspWith( "default-src 'self'; child-src 'self' https://kids.example.com" );
check( 'the fallback is the nearest one the policy has: frame-src is read from child-src before default-src', \Nino\Http::allowSource( $cspChild, 'frame-src', 'https://player.example.com' ) === true
	&& $cspPolicy( $cspChild ) === "default-src 'self'; child-src 'self' https://kids.example.com; frame-src 'self' https://kids.example.com https://player.example.com" );
$cspTwice = $cspWith( "frame-src 'self'; frame-src https://later.example.com" );
check( 'a directive named twice is widened where a browser reads it, at its first occurrence', \Nino\Http::allowSource( $cspTwice, 'frame-src', 'https://player.example.com' ) === true
	&& $cspPolicy( $cspTwice ) === "frame-src 'self' https://player.example.com; frame-src https://later.example.com" );
check( 'one that is not a fetch directive is appended to where the policy has it, and not made where it has not: an absent one restricts nothing', ( static function() use ( $cspWith, $cspPolicy ): bool {
	$request = $cspWith( "default-src 'self'; form-action 'self'" );
	return \Nino\Http::allowSource( $request, 'form-action', 'https://forms.example.com' ) === true && $cspPolicy( $request ) === "default-src 'self'; form-action 'self' https://forms.example.com"
		&& \Nino\Http::allowSource( $request, 'base-uri', 'https://base.example.com' ) === false && str_contains( $cspPolicy( $request ), 'base-uri' ) === false;
} )() );

// The table of CSP level 3: the directives a browser reads the sources of a directive from, nearest first.
// A policy that has the chain from one step on is widened from that step's list, whichever step it is
$cspChains = [
	'child-src'				=> [ 'default-src' ],
	'frame-src'				=> [ 'child-src', 'default-src' ],
	'worker-src'			=> [ 'child-src', 'script-src', 'default-src' ],
	'script-src-elem'	=> [ 'script-src', 'default-src' ],
	'script-src-attr'	=> [ 'script-src', 'default-src' ],
	'style-src-elem'	=> [ 'style-src', 'default-src' ],
	'style-src-attr'	=> [ 'style-src', 'default-src' ],
];
foreach( [ 'connect-src', 'font-src', 'img-src', 'manifest-src', 'media-src', 'object-src', 'prefetch-src', 'script-src', 'style-src' ] as $cspFetch )
	$cspChains[$cspFetch] = [ 'default-src' ];

$cspWrongChain = [];
foreach( $cspChains as $cspDirective => $cspChain )
	for( $step = 0; $step < count( $cspChain ); $step++ ) {

		$cspStart = implode( '; ', array_map( static fn( string $name ): string => $name. " 'self' https://". $name. '.example.com', array_slice( $cspChain, $step ) ) );
		$cspStepped = $cspWith( $cspStart );

		if( \Nino\Http::allowSource( $cspStepped, $cspDirective, 'https://new.example.com' ) !== true
			|| $cspPolicy( $cspStepped ) !== $cspStart. '; '. $cspDirective. " 'self' https://". $cspChain[$step]. '.example.com https://new.example.com' )
			$cspWrongChain[] = $cspDirective. ' from '. $cspChain[$step];
	}
check( 'a directive the policy lacks is made from the nearest of the directives CSP level 3 reads its sources from - for every fetch directive, from whichever step of its chain the policy has', $cspWrongChain === [] );
check( '...and the directives that are no fetch directives are not made: an absent one restricts nothing', array_filter( [ 'base-uri', 'form-action', 'frame-ancestors', 'sandbox', 'report-uri', 'report-to', 'upgrade-insecure-requests', 'require-trusted-types-for', 'trusted-types' ], static function( string $directive ) use ( $cspWith, $cspPolicy ): bool {
	$request = $cspWith( "default-src 'self'" );
	return \Nino\Http::allowSource( $request, $directive, 'https://new.example.com' ) !== false || $cspPolicy( $request ) !== "default-src 'self'";
} ) === [] );

$cspClosed = [ "default-src 'none'", "default-src 'self'; child-src 'none'", "default-src 'self'; frame-src 'none'", "default-src 'self'; frame-src", "default-src 'self'; frame-src 'NONE'" ];
check( 'a directive that says \'none\', or lists nothing, is the project having decided - and so is a fallback that does', array_filter( $cspClosed, static function( string $policy ) use ( $cspWith, $cspPolicy ): bool {
	$request = $cspWith( $policy );
	return \Nino\Http::allowSource( $request, 'frame-src', 'https://player.example.com' ) !== false || $cspPolicy( $request ) !== $policy;
} ) === [] );
check( 'a policy with neither the directive nor a fallback for it restricts nothing there, and is left alone', ( static function() use ( $cspWith, $cspPolicy ): bool {
	$request = $cspWith( "img-src 'self'" );
	return \Nino\Http::allowSource( $request, 'frame-src', 'https://player.example.com' ) === false && $cspPolicy( $request ) === "img-src 'self'";
} )() );
check( 'an empty policy, one that is no string and a response without one are left as they are', ( static function() use ( $cspWith, $cspPolicy ): bool {
	$empty = $cspWith( '' );
	$array = $cspWith( [ "default-src 'self'" ] );
	$none = [ '/nino/http/response' => [] ];
	return \Nino\Http::allowSource( $empty, 'frame-src', 'https://player.example.com' ) === false && $cspPolicy( $empty ) === ''
		&& \Nino\Http::allowSource( $array, 'frame-src', 'https://player.example.com' ) === false && $array === $cspWith( [ "default-src 'self'" ] )
		&& \Nino\Http::allowSource( $none, 'frame-src', 'https://player.example.com' ) === false && $none === [ '/nino/http/response' => [] ];
} )() );
check( 'a feature may widen a policy, never open it: \'*\' alone, the unsafe keywords in quotes and a source that would end the directive are a warning and no change',
	array_filter( [ '*', "'unsafe-inline'", "'UNSAFE-EVAL'", '', 'a b', "a\tb", 'a;b', 'a,b', "a\nb" ], static fn( string $source ): bool => $cspRefuses( $cspKernel, 'frame-src', $source ) === false ) === [] );
check( '...and so is a directive that is no name of one', array_filter( [ 'Frame-Src', 'frame_src', 'frame-src;', '', '-x', 'frame src' ], static fn( string $directive ): bool => $cspRefuses( $cspKernel, $directive, 'https://player.example.com' ) === false ) === [] );
check( '...while a source with a scheme only, like data:, is one a feature may add', ( static function() use ( $cspWith, $cspPolicy ): bool {
	$request = $cspWith( "default-src 'self'; img-src 'self'" );
	return \Nino\Http::allowSource( $request, 'img-src', 'data:' ) === true && $cspPolicy( $request ) === "default-src 'self'; img-src 'self' data:";
} )() );
check( '...but on a directive that runs code a scheme alone, a wildcard host and any unsafe keyword open the policy and are refused - a wildcard host on frame-src is a source like any other',
	array_filter( [ [ 'script-src', 'https:' ], [ 'script-src-elem', 'data:' ], [ 'script-src-attr', 'blob:' ], [ 'object-src', 'https://*.example.com' ], [ 'worker-src', 'https://*' ], [ 'script-src', "'unsafe-hashes'" ], [ 'frame-src', "'Unsafe-Hashes'" ] ],
		static fn( array $case ): bool => $cspRefuses( $cspKernel, $case[0], $case[1] ) === false ) === []
	&& ( static function() use ( $cspWith, $cspPolicy ): bool {
		$request = $cspWith( "default-src 'self'" );
		return \Nino\Http::allowSource( $request, 'frame-src', 'https://*.example.com' ) === true && str_contains( $cspPolicy( $request ), 'https://*.example.com' ) === true
			&& \Nino\Http::allowSource( $request, 'script-src', 'https://cdn.example.com' ) === true && str_contains( $cspPolicy( $request ), "script-src 'self' https://cdn.example.com" ) === true;
	} )() );


echo "\n";


// --- Modules\Navigation ----------------------------------------------------

echo "Modules\\Navigation: menus built from the routes\n";

// A menu is not stored anywhere - it is computed per request from the routes
// that list themselves under its key. That is what lets a route added to
// config.php by hand be a menu entry with no tool involved, and what keeps a
// menu from ever going stale against the routes it describes.
$routesBeforeNav = $appData['/nino/http/routes'];

$appData['/nino/http/routes'] = [
	'GET://top' 				=> [ 'uri' => '/top', 			'body' => '', 'navs' => [ 'main' => 1 ] ],
	'GET://' 						=> [ 'uri' => '/home', 			'body' => '', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	'GET://kontakt' 		=> [ 'uri' => '/contact', 	'body' => '', 'navs' => [ 'main' => 5 ] ],
	'GET://impressum' 	=> [ 'uri' => '/legal', 		'body' => '', 'navs' => [ 'footer' => 5 ] ],
	'GET://intern' 			=> [ 'uri' => '/intern', 		'body' => '' ],
	'GET://namenlos' 		=> [ 'uri' => '/namenlos', 	'body' => '', 'navs' => [ 'main' => 5 ] ],
	'GET://rechtliches' => [ 'uri' => '/legal-de', 	'body' => '', 'navs' => [ 'footer' => 5 ], 'locale' => 'de_DE' ],
	'POST://.form' 			=> [ 'uri' => '/.form', 		'body' => '', 'navs' => [ 'main' => 1 ] ],
];

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
\Nino\Html::addFills( $appData, [
	'/_nino/webpage/home/name' 			=> 'Home',
	'/_nino/webpage/contact/name' 	=> 'Contact',
	'/_nino/webpage/legal/name' 		=> 'Legal',
	'/_nino/webpage/top/name' 			=> 'Top',
], 'en_US' );
\Nino\Html::addFills( $appData, [
	'/_nino/webpage/legal-de/name' 	=> 'Rechtliches',
	'/_nino/webpage/home/name' 			=> 'Start',
], 'de_DE' );

$mainLines = \Nino\Modules\Navigation::routeLines( $appData, 'main' );

check( 'a menu collects every route that lists itself under its key', $mainLines === [ '/top:Top', '/:Home', '/kontakt:Contact' ] );
check( 'a lower priority sorts first, equal priorities keep the routes\' own order', $mainLines[0] === '/top:Top' );
check( 'a route with no membership stays out', in_array( '/intern:', $mainLines, true ) === false && str_contains( implode( '', $mainLines ), 'intern' ) === false );
check( 'a route nobody named stays out rather than rendering an empty link', str_contains( implode( '', $mainLines ), 'namenlos' ) === false );
check( 'a POST route is never a menu entry', str_contains( implode( '', $mainLines ), '.form' ) === false );

$footerLines = \Nino\Modules\Navigation::routeLines( $appData, 'footer' );
check( 'a second menu is an independent selection of the same routes', $footerLines === [ '/:Home', '/impressum:Legal' ] );

// A locale-gated route only exists for its own locale (same rule
// Http::findRouteUri() applies), so it only belongs in that locale's menu
check( 'a locale-gated route stays out of another locale\'s menu', str_contains( implode( '', $footerLines ), 'rechtliches' ) === false );

\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
$footerDe = \Nino\Modules\Navigation::routeLines( $appData, 'footer' );
check( '...and appears in its own', in_array( '/rechtliches:Rechtliches', $footerDe, true ) === true );
check( 'every title comes from the current locale', in_array( '/:Start', $footerDe, true ) === true );

\Nino\Locales::setCurrentLocale( $appData, 'en_US' );

$navHtml = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'the shortcode renders one <li> per entry', substr_count( $navHtml, '<li>' ) === 3 );
check( '...in menu order', strpos( $navHtml, 'Top' ) < strpos( $navHtml, 'Home' ) );

$navMixed = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main', 'content' => '/extra:Extra' ] );
check( 'a hand-written line is appended after the generated ones', substr_count( $navMixed, '<li>' ) === 4 && strpos( $navMixed, 'Extra' ) > strpos( $navMixed, 'Contact' ) );

$navManual = \Nino\Modules\Navigation::doShortcode( $appData, [ 'content' => "/a:A\n/b:B" ] );
check( 'a menu written entirely by hand still works, with no nav argument at all', substr_count( $navManual, '<li>' ) === 2 );
check( 'an empty shortcode renders nothing', \Nino\Modules\Navigation::doShortcode( $appData, [] ) === '' );
check( 'an unknown menu key renders nothing', \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'nope' ] ) === '' );

// A page's name is editor content, and a line is '<uri>:<title>'. Splitting
// on every ':' made the second colon of a perfectly ordinary name ("Angebot:
// Sommer") end the title and open the third field, which is written into the
// <a> tag as attributes - a name typed in the Text panel deciding what the
// markup says
\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => 'Angebot: Sommer" onmouseover="alert(1)' ], 'en_US' );
$navColon = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'a colon in a page name stays part of the name', str_contains( $navColon, 'Angebot: Sommer' ) === true );
check( '...and none of it reaches the tag', str_contains( $navColon, '" onmouseover="' ) === false
	&& str_contains( $navColon, '&quot; onmouseover=&quot;' ) === true );

// ...and the name itself is text, not markup - the same rule every other
// place an editor's words reach a page follows
\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => '<script>alert(1)</script>' ], 'en_US' );
$navMarkup = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'markup in a page name is drawn as text', str_contains( $navMarkup, '&lt;script&gt;' ) === true
	&& str_contains( $navMarkup, '<script>alert(1)' ) === false );

// ...and a shortcode's result is rendered again, fills and shortcodes
// included, so a '[' in a name has to be an entity by the time it leaves
// here - the pass that follows would otherwise fill it
\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => 'Angebot [[/_nino/webpage/home/name]] [navigation nav="main"]' ], 'en_US' );
$navBracket = \Nino\Modules\Navigation::doShortcode( $appData, [ 'nav' => 'main' ] );
check( 'a bracket in a page name is an entity, so the render pass after the shortcode cannot fill it',
	str_contains( $navBracket, 'Angebot &#91;&#91;/_nino/webpage/home/name&#93;&#93; &#91;navigation nav=&quot;main&quot;&#93;' ) === true
	&& str_contains( $navBracket, '[[/_nino/webpage/home/name]]' ) === false );

// A hand-written line is the page author's own - three fields, the third of
// them attributes for the tag, and written as typed. That is what it has
// always been, and what a template that uses it keeps
$navAttributes = \Nino\Modules\Navigation::doShortcode( $appData, [ 'content' => '/a:A: target="_blank"' ] );
check( 'a hand-written line still carries its attributes in the third field', str_contains( $navAttributes, '>A<' ) === true
	&& str_contains( $navAttributes, 'target="_blank"' ) === true );
check( '...and its markup is still the author\'s own', str_contains( \Nino\Modules\Navigation::doShortcode( $appData, [ 'content' => '/a:<b>A</b>' ] ), '<b>A</b>' ) === true );

\Nino\Html::addFills( $appData, [ '/_nino/webpage/top/name' => 'Top' ], 'en_US' );

// A route that exists only at runtime (a feature's /blog) has no entry in
// config.php to carry 'navs'. Its memberships are '/nino/html/navroutes', by the
// page's Element-URI, read for a live route and merged with the route's own, which
// wins for the same menu
\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => 'Blog' ], 'en_US' );
$appData['/nino/http/routes']['GET://blog'] = [ 'uri' => '/blog', 'body' => '' ];
$appData['/nino/html/navroutes'] = [
	'/blog' 		=> [ 'main' => 3 ],
	'/top' 			=> [ 'main' => 9, 'footer' => 1 ],
];
check( 'a runtime route stands in the menu at its priority, in order with the persisted members', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/top:Top', '/blog:Blog', '/:Home', '/kontakt:Contact' ] );
check( 'the route\'s own membership wins for the same menu, the key adds the others', \Nino\Modules\Navigation::routeLines( $appData, 'footer' ) === [ '/top:Top', '/:Home', '/impressum:Legal' ] );
check( 'a menu nobody is in through the key renders as before', \Nino\Modules\Navigation::routeLines( $appData, 'nope' ) === [] );

$appData['/nino/html/navroutes']['/gone'] = [ 'main' => 1 ];
\Nino\Html::addFills( $appData, [ '/_nino/webpage/gone/name' => 'Gone' ], 'en_US' );
check( 'a membership of a route that is not live is skipped, though its name exists - the feature is off', in_array( '/gone:Gone', \Nino\Modules\Navigation::routeLines( $appData, 'main' ), true ) === false );

\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => '' ], 'en_US' );
check( 'a runtime route nobody named stays out, like any other', str_contains( implode( '', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) ), 'blog' ) === false );
\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => 'Blog' ], 'en_US' );

// Whatever else is written there is not a priority
$appData['/nino/html/navroutes'] = [
	'/blog' 			=> [ 'main' => 3 ],
	'/contact' 		=> [ 'footer' => '2', 'side' => 2.5, 'x' => [ 1 ], 'y' => null ],
	'/legal' 			=> 'main',
	'/intern' 		=> 7,
	'/.form' 			=> [ 'main' => 1 ],
];
check( 'malformed entries are skipped: no menu is changed by them, and none of them throws',
	\Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/top:Top', '/blog:Blog', '/:Home', '/kontakt:Contact' ]
	&& \Nino\Modules\Navigation::routeLines( $appData, 'footer' ) === [ '/:Home', '/impressum:Legal' ]
	&& \Nino\Modules\Navigation::routeLines( $appData, 'side' ) === [] );
check( '...and a POST route is no menu entry through the key either', str_contains( implode( '', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) ), '.form' ) === false );

unset( $appData['/nino/html/navroutes'] );
check( 'with no key at all nothing changes', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/top:Top', '/:Home', '/kontakt:Contact' ] );

// A page with a route per language - the imprint of Modules\Legal - is one page:
// the key is its Element-URI, every GET route with that uri is a member, the one
// of the current language is the entry, and a wildcard is never one
$appData['/nino/http/routes'] = [
	'GET://home' 				=> [ 'uri' => '/home', 'body' => '', 'navs' => [ 'legal' => 3 ] ],
	'GET://impressum' 	=> [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '', 'maintenance' => false ],
	'GET://imprint' 		=> [ 'uri' => '/legal/imprint', 'locale' => 'en_US', 'body' => '', 'maintenance' => false ],
	'GET://datenschutz' => [ 'uri' => '/legal/privacy', 'locale' => 'de_DE', 'body' => '' ],
	'GET://privacy' 		=> [ 'uri' => '/legal/privacy', 'locale' => 'en_US', 'body' => '' ],
	'GET://wildcard/*' 	=> [ 'uri' => '/legal/imprint', 'body' => '' ],
	'GET://shared' 			=> [ 'uri' => '/legal/shared', 'body' => '' ],
	'GET://fr-fr/shared' => [ 'uri' => '/legal/shared', 'locale' => 'fr_FR', 'body' => '' ],
];
$appData['/nino/html/navroutes'] = [
	'/legal/imprint' 	=> [ 'legal' => 1 ],
	'/legal/privacy' 	=> [ 'legal' => 2 ],
	'/legal/shared' 	=> [ 'legal' => 4, 'main' => 1 ],
	'/home' 					=> [ 'legal' => 9 ],
];
\Nino\Html::addFills( $appData, [ '/_nino/webpage/legal/imprint/name' => 'Imprint', '/_nino/webpage/legal/privacy/name' => 'Privacy', '/_nino/webpage/legal/shared/name' => 'Shared', '/_nino/webpage/home/name' => 'Home' ], 'en_US' );
\Nino\Html::addFills( $appData, [ '/_nino/webpage/legal/imprint/name' => 'Impressum', '/_nino/webpage/legal/privacy/name' => 'Datenschutz', '/_nino/webpage/legal/shared/name' => 'Geteilt', '/_nino/webpage/home/name' => 'Start' ], 'de_DE' );
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
check( 'every route of an Element-URI is a member, the one of the current language is the entry, in the order of the priorities',
	\Nino\Modules\Navigation::routeLines( $appData, 'legal' ) === [ '/imprint:Imprint', '/privacy:Privacy', '/home:Home', '/shared:Shared' ] );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( '...in each language its own path and name', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ) === [ '/impressum:Impressum', '/datenschutz:Datenschutz', '/home:Start', '/shared:Geteilt' ] );
check( 'a route without a language is the entry where no route of the current language has the Element-URI', in_array( '/shared:Geteilt', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), true ) === true
	&& in_array( '/fr-fr/shared:Geteilt', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), true ) === false );
check( '...and a route on a wildcard never is a member through the key', str_contains( implode( '', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ) ), 'wildcard' ) === false );
check( 'a persisted route\'s own membership goes before the one its Element-URI has', \Nino\Modules\Navigation::routeLines( $appData, 'legal' )[2] === '/home:Start' );
check( 'an Element-URI is in a menu once', count( array_filter( \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), static fn( string $line ): bool => str_ends_with( $line, ':Impressum' ) ) ) === 1 );
$appData['/nino/html/navroutes']['/legal/privacy'] = [ 'legal' => '2' ];
$appData['/nino/html/navroutes']['legal/imprint'] = [ 'legal' => 1 ];
check( 'an entry that is no whole number is no membership, and a key that is no Element-URI matches no route', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ) === [ '/impressum:Impressum', '/home:Start', '/shared:Geteilt' ] );
\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
unset( $appData['/nino/html/navroutes'] );

$appData['/nino/http/routes'] = $routesBeforeNav;

// Http::output() itself exit()s, so the header-finalizing part it delegates
// to is exercised directly via Reflection instead. invokeArgs() with an
// array of references - plain invoke() can't pass $request by reference,
// which would silently run against a copy and never show this test its own
// mutations
$finalizeResponse = new ReflectionMethod( '\Nino\Http', '_finalizeResponse' );
$finalizeResponse->setAccessible( true );

$cookieRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [ 'Set-Cookie' => 'sid=abc123; HttpOnly' ], 'body' => '' ] ];
$finalizeResponse->invokeArgs( null, [ &$cookieRequest ] );
check( 'a response header outside the request-side whitelist (Set-Cookie) is not dropped', ( $cookieRequest['/nino/http/response']['header']['Set-Cookie'] ?? null ) === 'sid=abc123; HttpOnly' );

$downloadRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [ 'Content-Disposition' => 'attachment; filename="export.json"' ], 'body' => '' ] ];
$finalizeResponse->invokeArgs( null, [ &$downloadRequest ] );
check( 'another whitelist-only-on-the-request-side header (Content-Disposition) survives too', ( $downloadRequest['/nino/http/response']['header']['Content-Disposition'] ?? null ) === 'attachment; filename="export.json"' );

/*	A body json_encode() refuses. false went into the body and echoed as the
	empty string: an empty 200 carrying a json content-type, which every
	_apiCall in _admin reads as a success with nothing in it - a blank panel
	and no message anywhere. The reachable cause is a single malformed utf-8
	byte, which the activity log's own test in admin-elements-smoke.php drives
	end to end; that one is substituted now rather than refused	*/
$badUtf8Request = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => [ 'files' => [ "Gru\xdfe.jpg" ] ] ] ];
$finalizeResponse->invokeArgs( null, [ &$badUtf8Request ] );
$badUtf8Body = json_decode( (string) $badUtf8Request['/nino/http/response']['body'], true );
check( 'a malformed utf-8 byte no longer costs the whole response', is_array( $badUtf8Body ) === true );
check( '...the byte is substituted and the rest of the value arrives', ( $badUtf8Body['files'][0] ?? '' ) === "Gru\u{FFFD}e.jpg" );
check( '...and the status stays the one the handler set', $badUtf8Request['/nino/http/response']['statusCode'] === 200 );

// What cannot be substituted - Inf/NaN, a resource, a recursion. The harness
// silences the warnings trigger_error() raises, so the one worth reading is
// read back after the calls
ninoWarnings();

$unencodableRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => [ 'n' => INF ] ] ];
$finalizeResponse->invokeArgs( null, [ &$unencodableRequest ] );

// A handler that already failed named its own status: a 403 is a 403 whatever
// became of its body
$failedBodyRequest = [ '/nino/http/response' => [ 'statusCode' => 403, 'header' => [], 'body' => [ 'n' => NAN ] ] ];
$finalizeResponse->invokeArgs( null, [ &$failedBodyRequest ] );

$recordedWarnings = ninoWarnings();

$unencodableBody = json_decode( (string) $unencodableRequest['/nino/http/response']['body'], true );
check( 'a body that cannot be encoded at all is not answered as a 200', $unencodableRequest['/nino/http/response']['statusCode'] === 500 );
check( '...it says why, where the panel that asked will print it', str_contains( $unencodableBody['error'] ?? '', 'could not be encoded' ) === true );
check( '...and the kernel recorded it as well', count( array_filter( $recordedWarnings, static fn( string $w ): bool => str_contains( $w, 'could not be json-encoded' ) === true ) ) === 2 );
check( 'an unencodable body behind a 4xx keeps that status', $failedBodyRequest['/nino/http/response']['statusCode'] === 403 );

// A string body is not json and is never touched
$htmlRequest = [ '/nino/http/response' => [ 'statusCode' => 200, 'header' => [], 'body' => '<html>page</html>' ] ];
$finalizeResponse->invokeArgs( null, [ &$htmlRequest ] );
check( 'a string body passes through unencoded and untyped', $htmlRequest['/nino/http/response']['body'] === '<html>page</html>'
	&& isset( $htmlRequest['/nino/http/response']['header']['Content-Type'] ) === false );

/*	The request header allowlist names request headers. Location, the
	Content-Security-Policy and the other security headers are what Nino
	sends, never what it reads - one a client sends is dropped like any other
	name Nino does not know, by the public filter and through Http::request()
	alike	*/
$responseOnly = [ 'Location' => '/rechtliches', 'Content-Security-Policy' => 'default-src *', 'X-Frame-Options' => 'DENY', 'X-Content-Type-Options' => 'nosniff', 'Strict-Transport-Security' => 'max-age=1', 'Referrer-Policy' => 'no-referrer', 'Feature-Policy' => 'camera none', 'Permissions-Policy' => 'camera=()' ];
check( 'a response-only header name is dropped by filterHeaderFields()', \Nino\Http::filterHeaderFields( $responseOnly ) === [] );
$headerRequest = [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_LOCATION' => '/elsewhere', 'HTTP_CONTENT_SECURITY_POLICY' => 'default-src *', 'HTTP_HOST' => 'example.test', 'HTTP_REFERER' => 'https://example.test/', 'HTTP_ACCEPT_LANGUAGE' => 'de' ];
\Nino\Http::request( $appData, $headerRequest );
check( '...and a request that sends one arrives without it, with the request headers beside it under their own names', $headerRequest['/nino/http/request']['header'] === [ 'Host' => 'example.test', 'Referer' => 'https://example.test/', 'Accept-Language' => 'de' ] );

check( 'a bare [assets] shortcode without argument renders nothing instead of erroring', \Nino\Modules\Assets::doShortcode( $appData, [] ) === '' );

/*	The bundle keeps one address on disk and changes the one in the page: a
	regenerated bundle used to be served at the url a browser was already
	holding a copy of, so the page asked for /public/.cache/style.css and got
	the stylesheet from before the change. The way out was a hard reload nobody
	knows to do	*/
\Nino\Filesystem::putFileContent( $appData, '/assets/probe.custom.css', 'body{color:red}' );
\Nino\Html::addAsset( $appData, '/.cache/probe.css', '/assets/probe.custom.css' );

$bundleTag		= \Nino\Modules\Assets::doShortcode( $appData, [ 0 => '/.cache/probe.css' ] );
$bundleFirst	= ( preg_match( '/href="([^"]+)"/', $bundleTag, $bundleMatch ) === 1 ) ? $bundleMatch[1] : '';

check( 'the bundle is linked under its own url with a version on it', str_starts_with( $bundleFirst, \Nino\Filesystem::url( $appData, '/.cache/probe.css' ). '?v=' ) === true );
check( '...and the file on disk keeps the one name, so nothing piles up beside it', is_file( \Nino\Filesystem::path( $appData, '/.cache/probe.css' ) ) === true );

// Rendering it again changes nothing, because nothing changed
check( 'an unchanged bundle keeps its url, so the copy a browser holds stays good', \Nino\Modules\Assets::doShortcode( $appData, [ 0 => '/.cache/probe.css' ] ) === $bundleTag );

// ...and a changed source moves it, which is the whole point
sleep( 1 );
\Nino\Filesystem::putFileContent( $appData, '/assets/probe.custom.css', 'body{color:blue}' );
unset( $appData['./nino/filesystem/cache']['/assets/probe.custom.css'] );

$bundleSecond = ( preg_match( '/href="([^"]+)"/', \Nino\Modules\Assets::doShortcode( $appData, [ 0 => '/.cache/probe.css' ] ), $bundleMatch ) === 1 ) ? $bundleMatch[1] : '';
check( 'a rebuilt bundle is linked under a different url', $bundleSecond !== '' && $bundleSecond !== $bundleFirst );
check( '...at the same path, differing in the version alone', explode( '?', $bundleSecond )[0] === explode( '?', $bundleFirst )[0] );

echo "\n";


// --- The private root is never reachable over http -----------------------

echo "router.php - the private root is never served\n";

// Moving the templates out of the public root only helps if nothing hands
// them back at their new path. The dev server applies no .htaccess at all,
// so router.php has to refuse /private itself - a plain request for
// /private/templates/page-home.tpl returned the full template source until
// it did. Production has private/.htaccess (and NINO_PRIVATE_DIR for a
// setup that cannot rely on it), see docs/deployment.md
$routerSource = file_get_contents( __DIR__. '/../router.php' );

check( 'router.php refuses the private root', str_contains( $routerSource, '#^/private(?:/|$)#' ) === true );
check( '...before it ever looks for a static file', strpos( $routerSource, '/private' ) < strpos( $routerSource, 'is_file( __DIR__. $uri )' ) );

// The library used to have one deliberately public file, the theme picker's
// preview image; since 1.2 it has none, and the router says so without an
// exception to get wrong
check( 'the installer library is refused whole, with nothing carved out of it',
	str_contains( $routerSource, '#^/_admin/install/library(?:/|$)#' ) === true
	&& str_contains( $routerSource, '/_admin/install/library/themes' ) === false
	&& str_contains( (string) file_get_contents( __DIR__. '/../_admin/install/library/.htaccess' ), 'FilesMatch' ) === false );
check( '...and refuses its source before static-file delivery', strpos( $routerSource, '#^/_admin/install/library(?:/|$)#' ) < strpos( $routerSource, 'is_file( __DIR__. $uri )' ) );

/*	A checkout ships no private directory at all - it is one installation's
	own state, not repository content. The wizard creates it and brings the
	deny rule that protects it (see _admin/install/library/base), so what has to
	hold here is that the rule exists to be brought.	*/
check( 'a checkout ships no private directory - the wizard creates it', is_dir( __DIR__. '/../private' ) === false );
check( '...and the deny rule that protects it travels with the installer', str_contains(
	(string) @file_get_contents( __DIR__. '/../_admin/install/library/base/private/.htaccess' ), 'Require all denied'
) === true );
check( 'the installer library ships the matching Apache protection', str_contains(
	(string) @file_get_contents( __DIR__. '/../_admin/install/library/.htaccess' ), 'Require all denied'
) === true );

// Nothing private may sit in the public root of a fresh checkout
foreach( \Nino\Filesystem::PRIVATE_DIRS as $private )
	check( "a checkout ships no $private in the public root", file_exists( __DIR__. '/..'. $private ) === false );

check( 'and no config.php either - the wizard writes the first one', is_file( __DIR__. '/../private/config.php' ) === false );

echo "\n";


// --- Modules\Cache ---------------------------------------------------------

echo "Modules\\Cache - full-page cache for anonymous GET\n";

// A request as Http::request() leaves it, plus the response fields the two
// callbacks read. Http::output() exits, so the hit itself is driven through
// _prepare(), the decide-and-shape half callbackResponse() calls before it -
// the same split Modules\Maintenance uses for the same reason
function cacheRequest( string $uri, string $method = 'GET', array $response = [] ): array {
	return [
		'/nino/http/request' => [
			'method' 		=> $method === 'HEAD' ? 'GET' : $method,
			'rawMethod'	=> $method,
			'uri' 			=> $uri,
			'query' 		=> [],
		],
		'/nino/http/response' => array_merge( [
			'uri' 				=> $uri,
			'locale' 			=> 'de_DE',
			'statusCode'	=> 200,
			'header' 			=> [],
			'body' 				=> '<html>cached</html>',
		], $response ),
	];
}

function cacheEntryCount( array &$appData ): int {
	return count( glob( \Nino\Filesystem::path( $appData, '/data/cache' ). '/*.php' ) ?: [] );
}

$appData['/nino/cache/status'] = true;
$appData['/nino/cache/ttl'] = 60;
$appData['/nino/cache/blacklist'] = [];
\Nino\Modules\Cache::_invalidate( $appData );

$store = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $store );
check( 'an anonymous GET 200 is stored', cacheEntryCount( $appData ) === 1 );

// --- what must never be stored, whatever the configuration says -----------

foreach( [
	'a POST' 												=> [ cacheRequest( '/home', 'POST' ), [] ],
	'a HEAD, which carries no body' => [ cacheRequest( '/home', 'HEAD' ), [] ],
	'a 404' 												=> [ cacheRequest( '/nope', 'GET', [ 'statusCode' => 404 ] ), [] ],
	'a redirect' 										=> [ cacheRequest( '/home', 'GET', [ 'header' => [ 'Location' => '/other' ] ] ), [] ],
	'a json body' 									=> [ cacheRequest( '/home', 'GET', [ 'body' => [ 'ok' => true ] ] ), [] ],
	'an empty body' 								=> [ cacheRequest( '/home', 'GET', [ 'body' => '' ] ), [] ],
	'a tool uri' 										=> [ cacheRequest( '/_admin' ), [] ],
	'a module endpoint' 						=> [ cacheRequest( '/.newsletter' ), [] ],
] as $label => $case ) {
	\Nino\Modules\Cache::_invalidate( $appData );
	$req = $case[0];
	\Nino\Modules\Cache::callbackOutput( $appData, $req );
	check( "$label is never stored", cacheEntryCount( $appData ) === 0 );
}

// A query var is what makes a page not the same page for everyone - the
// locale switch is exactly one
\Nino\Modules\Cache::_invalidate( $appData );
$withQuery = cacheRequest( '/home' );
$withQuery['/nino/http/request']['query'] = [ '/_nino/localepicker/current' => 'de_DE' ];
\Nino\Modules\Cache::callbackOutput( $appData, $withQuery );
check( 'a request carrying query vars is never stored', cacheEntryCount( $appData ) === 0 );

// A signed-in visitor's page may carry their name or an editing affordance
\Nino\Modules\Cache::_invalidate( $appData );
$appData['./nino/auth/current'] = [ 'mail' => 'someone@example.com', 'perms' => [] ];
$signedIn = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $signedIn );
check( 'a signed-in visitor\'s page is never stored', cacheEntryCount( $appData ) === 0 );
unset( $appData['./nino/auth/current'] );

// Off means off
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/status'] = false;
$offRequest = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $offRequest );
check( 'nothing is stored while the cache is switched off', cacheEntryCount( $appData ) === 0 );
$appData['/nino/cache/status'] = true;

// --- the two per-request values ------------------------------------------
//
// A stored page must not carry the csrf token or the jstext nonce of whoever
// it was rendered for: serving one visitor's token to everybody leaks it and
// 403s every other form submission, and a nonce that survives in the cache is
// readable by anyone who fetches the page - which is the one property a csp
// nonce may not have.

\Nino\Modules\Cache::_invalidate( $appData );
$token = \Nino\Csrf::getToken( $appData );
$appData['./nino/jstext/nonce'] = 'nonce-of-this-request';

$tokenRequest = cacheRequest( '/home', 'GET', [
	'body' => '<input name="_csrf" value="'. $token. '"><script nonce="nonce-of-this-request">x</script>',
] );
\Nino\Modules\Cache::callbackOutput( $appData, $tokenRequest );

$storedFile	= glob( \Nino\Filesystem::path( $appData, '/data/cache' ). '/*.php' )[0] ?? '';
$storedBody = ( include $storedFile )['body'] ?? '';

check( 'the stored page carries no csrf token', str_contains( $storedBody, $token ) === false );
check( 'the stored page carries no jstext nonce', str_contains( $storedBody, 'nonce-of-this-request' ) === false );
check( '...both are held as markers instead', substr_count( $storedBody, '@@nino-cache-' ) === 2 );

// --- blacklist ------------------------------------------------------------

// What matches is Http::matchesPath(), with its cases in the Http section above;
// here the cache keeps a page that matches out of the store, and one that does not in
$appData['/nino/cache/blacklist'] = [ '/contact', '/blog/*' ];
\Nino\Modules\Cache::_invalidate( $appData );
$listedRequest = cacheRequest( '/blog/post-1' );
\Nino\Modules\Cache::callbackOutput( $appData, $listedRequest );
$unlistedRequest = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $unlistedRequest );
check( 'a page on the blacklist is not stored and one that is not on it is', cacheEntryCount( $appData ) === 1 && is_file( \Nino\Filesystem::path( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php' ) ) === true );
$appData['/nino/cache/blacklist'] = [];

// --- expiry ---------------------------------------------------------------

\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/ttl'] = 60;
$fresh = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $fresh );
$entryPath	= glob( \Nino\Filesystem::path( $appData, '/data/cache' ). '/*.php' )[0];
$entry 			= include $entryPath;
check( 'a stored entry carries an expiry from the configured ttl', ( $entry['expires'] - time() ) > 50 && ( $entry['expires'] - time() ) <= 60 );
check( '...and the locale it rendered in, which serving has to re-apply', ( $entry['locale'] ?? '' ) === 'de_DE' );

// --- a write through a tool drops everything ------------------------------

foreach( [ '/_admin' ] as $tool ) {
	\Nino\Modules\Cache::_invalidate( $appData );
	$seed = cacheRequest( '/home' );
	\Nino\Modules\Cache::callbackOutput( $appData, $seed );
	$toolWrite = cacheRequest( $tool, 'POST' );
	\Nino\Modules\Cache::callbackOutput( $appData, $toolWrite );
	check( "a successful POST to $tool drops the cache", cacheEntryCount( $appData ) === 0 );
}

// A rejected write changed nothing, so it must not cost the cache either
\Nino\Modules\Cache::_invalidate( $appData );
$seed = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $seed );
$rejected = cacheRequest( '/_admin', 'POST', [ 'statusCode' => 403 ] );
\Nino\Modules\Cache::callbackOutput( $appData, $rejected );
check( 'a rejected tool write leaves the cache alone', cacheEntryCount( $appData ) === 1 );

// Reading in a tool is not a write either
$toolRead = cacheRequest( '/_admin', 'GET' );
\Nino\Modules\Cache::callbackOutput( $appData, $toolRead );
check( 'merely opening a tool leaves the cache alone', cacheEntryCount( $appData ) === 1 );

// The frontend's own endpoints are not tool writes - a contact form
// submission changes no page
$formPost = cacheRequest( '/.form', 'POST' );
\Nino\Modules\Cache::callbackOutput( $appData, $formPost );
check( 'a form submission does not drop the cache', cacheEntryCount( $appData ) === 1 );

// --- keying ---------------------------------------------------------------

\Nino\Modules\Cache::_invalidate( $appData );
$de = cacheRequest( '/home' );
$en = cacheRequest( '/home', 'GET', [ 'locale' => 'en_US' ] );
\Nino\Modules\Cache::callbackOutput( $appData, $de );
\Nino\Modules\Cache::callbackOutput( $appData, $en );
check( 'the same uri in two locales is two entries', cacheEntryCount( $appData ) === 2 );

// Two routes can resolve to the same page while the page still differs by the
// uri it was asked for - html-header.tpl's canonical link is exactly that
\Nino\Modules\Cache::_invalidate( $appData );
$slash = cacheRequest( '/' );
$slash['/nino/http/response']['uri'] = '/home';
$named = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $slash );
\Nino\Modules\Cache::callbackOutput( $appData, $named );
check( 'two request uris resolving to one page stay two entries', cacheEntryCount( $appData ) === 2 );

// --- what a wildcard route and a route handler mean for the store ---------

// A wildcard route ('GET://blog/*') answers an unbounded set of uris, and
// the key is the uri as asked for - so every made-up address under it used
// to become an entry of its own, and a lock file beside it. An anonymous
// client could grow private/data/ as fast as it could send requests
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/http/routes']['GET://blog/*'] = [ 'uri' => '/blog', 'body' => '[template /templates/page-blog]' ];
$wildcard = cacheRequest( '/blog/anything-at-all' );
$wildcard['/nino/http/response']['uri'] = '/blog';
\Nino\Modules\Cache::callbackOutput( $appData, $wildcard );
check( 'a page answered through a wildcard route is never stored - its uris are the client\'s to invent', cacheEntryCount( $appData ) === 0 );
check( '...and it is not told it was a miss either', isset( $wildcard['/nino/http/response']['header']['X-Nino-Cache'] ) === false );

$exact = cacheRequest( '/blog' );
\Nino\Modules\Cache::callbackOutput( $appData, $exact );
check( 'the wildcard\'s own address, which is a route of its own, still is', cacheEntryCount( $appData ) === 1 );
unset( $appData['/nino/http/routes']['GET://blog/*'] );

// A route with a handler of its own (the shape Modules\Form and the Posts
// feature use) is never answered from the cache - and so must never be
// stored: those entries could only ever be written, never read
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/http/routes']['GET://post'] = [ 'uri' => '/post' ];
\Nino\Callbacks::registerCallback( $appData, '/nino/http/response/GET://post', static function( array &$appData, array &$request ): void {} );
$handled = cacheRequest( '/post' );
\Nino\Modules\Cache::callbackOutput( $appData, $handled );
check( 'a page whose route has a handler of its own is not stored - it could never be served', cacheEntryCount( $appData ) === 0 );
check( '...and is not marked as a miss', isset( $handled['/nino/http/response']['header']['X-Nino-Cache'] ) === false );
unset( $appData['./nino/callbacks']['/nino/http/response/GET://post'], $appData['/nino/http/routes']['GET://post'] );

// --- the hit ---------------------------------------------------------------

\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/http/routes']['GET://home'] = [ 'uri' => '/home' ];
$appData['./nino/jstext/nonce'] = 'a-render-time-nonce';
$rendered = cacheRequest( '/home', 'GET', [ 'body' => '<html data-csrf="'. \Nino\Csrf::getToken( $appData ). '" data-nonce="a-render-time-nonce">cached</html>' ] );
\Nino\Modules\Cache::callbackOutput( $appData, $rendered );
$storedEntry = \Nino\Filesystem::getFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', [] );
check( 'the two per-request values are stored as markers, not as this request\'s own', str_contains( $storedEntry['body'] ?? '', \Nino\Csrf::getToken( $appData ) ) === false
	&& str_contains( $storedEntry['body'] ?? '', 'a-render-time-nonce' ) === false );

// The next visitor: another session, so another token and another nonce
\Nino\Csrf::rotateToken( $appData );
$appData['./nino/jstext/nonce'] = 'the-next-requests-nonce';
$appData['./nino/locales/current'] = 'en_US';
$hit = cacheRequest( '/home', 'GET', [ 'body' => '' ] );
check( 'a stored page is served', \Nino\Modules\Cache::_prepare( $appData, $hit ) === true );
check( '...carrying this request\'s token and nonce, with no marker left in it', str_contains( $hit['/nino/http/response']['body'], 'data-csrf="'. \Nino\Csrf::getToken( $appData ). '"' ) === true
	&& str_contains( $hit['/nino/http/response']['body'], 'data-nonce="the-next-requests-nonce"' ) === true
	&& str_contains( $hit['/nino/http/response']['body'], '@@nino-cache' ) === false );
check( '...and saying so in the header', ( $hit['/nino/http/response']['header']['X-Nino-Cache'] ?? '' ) === 'hit' );
check( '...with the locale the page was rendered in applied to the session', \Nino\Locales::getCurrentLocale( $appData ) === 'de_DE' );

// --- the policy a feature widened -------------------------------------------
//
// A hit never renders, so the policy a module widened for what the page uses
// (an embed's frame-src) has to come out of the entry. The output callback
// runs at priority 9 so that it stores what the features' own priority 5
// callbacks made of it, and the nonce in it is the request's, not the stored one.

\Nino\Modules\Cache::_invalidate( $appData );
$outputCallbacks = $appData['./nino/callbacks']['/nino/http/output'] ?? null;
$appData['./nino/jstext/nonce'] = 'policy-render-nonce';
$widened = cacheRequest( '/home', 'GET', [ 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-policy-render-nonce'" ] ] );
$freshData = [];
\Nino\Modules\Cache::init( $freshData );
check( 'the module registers its output callback at priority 9, after the features\' own', in_array( [ \Nino\Modules\Cache::class, 'callbackOutput' ], $freshData['./nino/callbacks']['/nino/http/output'][9] ?? [], true ) === true );
\Nino\Callbacks::registerCallback( $appData, '/nino/http/output', [ \Nino\Modules\Cache::class, 'callbackOutput' ], 9 );
\Nino\Callbacks::registerCallback( $appData, '/nino/http/output', static function( array &$appData, array &$request ): void {
	$request['/nino/http/response']['header']['Content-Security-Policy'] .= "; frame-src 'self' https://www.youtube-nocookie.com";
} );
\Nino\Callbacks::doCallbacks( $appData, '/nino/http/output', $widened );
$policyEntry = \Nino\Filesystem::getFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', [] );
check( 'the stored entry keeps the policy a feature widened in the output phase, though Cache\'s callback registered first', str_contains( $policyEntry['csp'] ?? '', "frame-src 'self' https://www.youtube-nocookie.com" ) === true );
check( '...with the nonce as a marker, not the one of the request that rendered it', str_contains( $policyEntry['csp'] ?? '', 'policy-render-nonce' ) === false
	&& str_contains( $policyEntry['csp'] ?? '', "'nonce-@@nino-cache-nonce-2f8a@@'" ) === true );

$appData['./nino/jstext/nonce'] = 'policy-hit-nonce';
$policyHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'nonce-policy-hit-nonce'" ] ] );
check( 'a hit on that entry is served', \Nino\Modules\Cache::_prepare( $appData, $policyHit ) === true );
check( '...under the widened policy', str_contains( $policyHit['/nino/http/response']['header']['Content-Security-Policy'], "frame-src 'self' https://www.youtube-nocookie.com" ) === true );
check( '...carrying this request\'s nonce and no marker', str_contains( $policyHit['/nino/http/response']['header']['Content-Security-Policy'], "'nonce-policy-hit-nonce'" ) === true
	&& str_contains( $policyHit['/nino/http/response']['header']['Content-Security-Policy'], '@@nino-cache' ) === false );

// An entry without a policy (stored by an earlier kernel, or a page sent
// without one) keeps the header the response callbacks composed
unset( $policyEntry['csp'] );
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $policyEntry );
$bareHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( 'an entry without a stored policy is still served', \Nino\Modules\Cache::_prepare( $appData, $bareHit ) === true );
check( '...and leaves the composed header as it was', $bareHit['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'" );

// ...and so does one stored empty, as a page sent without a policy leaves it
$policyEntry['csp'] = '';
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $policyEntry );
$emptyHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( 'an entry stored with an empty policy is served and leaves the composed header as it was', \Nino\Modules\Cache::_prepare( $appData, $emptyHit ) === true
	&& $emptyHit['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'" );

// A policy stored with a nonce, read by a request that has none (Jstext off
// since): the marker cannot be completed and must not be sent as a source -
// the composed header stands
$policyEntry['csp'] = "default-src 'self'; script-src 'self' 'nonce-@@nino-cache-nonce-2f8a@@'";
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $policyEntry );
$appData['./nino/jstext/nonce'] = '';
$noNonceHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( 'a stored policy with a nonce marker, read by a request without a nonce, is served', \Nino\Modules\Cache::_prepare( $appData, $noNonceHit ) === true );
check( '...and sends the composed header, not a policy with "nonce-@@...@@" in it', $noNonceHit['/nino/http/response']['header']['Content-Security-Policy'] === "default-src 'self'"
	&& str_contains( json_encode( $noNonceHit['/nino/http/response']['header'] ), '@@nino-cache' ) === false );
$appData['./nino/jstext/nonce'] = 'policy-hit-nonce';
$withNonceHit = cacheRequest( '/home', 'GET', [ 'body' => '', 'header' => [ 'Content-Security-Policy' => "default-src 'self'" ] ] );
check( '...while the same entry still gets the nonce of a request that has one', \Nino\Modules\Cache::_prepare( $appData, $withNonceHit ) === true
	&& str_contains( $withNonceHit['/nino/http/response']['header']['Content-Security-Policy'], "'nonce-policy-hit-nonce'" ) === true );

if( $outputCallbacks === null )
	unset( $appData['./nino/callbacks']['/nino/http/output'] );
else
	$appData['./nino/callbacks']['/nino/http/output'] = $outputCallbacks;
$appData['./nino/jstext/nonce'] = 'the-next-requests-nonce';

// An entry past its lifetime is not served - and goes, rather than sitting
// there being read and rejected on every request until the next invalidation
$expiredPath = \Nino\Filesystem::path( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php' );
$expiredEntry = \Nino\Filesystem::getFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', [] );
$expiredEntry['expires'] = time() - 1;
\Nino\Filesystem::putFileContent( $appData, '/data/cache/'. sha1( '/home|de_DE' ). '.php', $expiredEntry );
$stale = cacheRequest( '/home', 'GET', [ 'body' => '' ] );
check( 'an entry past its lifetime is not served', \Nino\Modules\Cache::_prepare( $appData, $stale ) === false );
check( '...and is dropped rather than read again on every request', is_file( $expiredPath ) === false );

// The lock side-car every write creates (see Filesystem::lockFile) belongs
// to the entry, so dropping the cache drops it too - otherwise /data/.locks
// kept one empty file per page ever cached, for good
\Nino\Modules\Cache::_invalidate( $appData );
$locked = cacheRequest( '/home' );
\Nino\Modules\Cache::callbackOutput( $appData, $locked );
$lockPath = \Nino\Filesystem::path( $appData, '/data' ). '/.locks/'. sha1( '/data/cache/'. sha1( '/home|de_DE' ). '.php' ). '.lock';
check( 'a stored page has a lock side-car', is_file( $lockPath ) === true );
\Nino\Modules\Cache::_invalidate( $appData );
check( '...and dropping the cache takes it with it', is_file( $lockPath ) === false );

unset( $appData['/nino/http/routes']['GET://home'], $appData['./nino/jstext/nonce'] );
\Nino\Modules\Cache::_invalidate( $appData );
$appData['/nino/cache/status'] = false;

echo "\n";

// --- Cleanup ------------------------------------------------------------

ninoDone( $appData );
