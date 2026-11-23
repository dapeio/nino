<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	form-lazy-smoke.php	Test of where the form of the module Form is loaded: the stylesheet, the script and the words of
 *											the script stand on the pages that draw a form and on no other, the engine \Nino\Form is not
 *											loaded by a page that is only drawn, and a post to the address of the form is answered as it
 *											was whichever page it comes from. The site is the one the setup wizard builds, and every
 *											request goes the whole way through \Nino\request().
 *
 *	Usage: php tests/form-lazy-smoke.php
 */

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

/**
 *	The starter site the wizard builds, in a sandbox, with the data of a company and the mailbox the form delivers to
 *
 *	@return 	string										The directory
 */
function lazySite(): string {

	$appData = ninoSandbox( 'form-lazy' );
	$sandbox = ninoSandboxDir( $appData );

	// What ninoDone() removes
	$GLOBALS['lazySandbox'] = $appData;

	$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

	$appData['/nino/html/navs'] = \Nino\AppData::DEFAULTS['/nino/html/navs'];
	$appData['/nino/modules'] = [ '\\Nino\\Modules\\Assets', '\\Nino\\Modules\\Elements', '\\Nino\\Modules\\Template', '\\Nino\\Modules\\Jstext', '\\Nino\\Modules\\Csrf' ];

	foreach( [ '/private/templates', '/public/images', '/private/text', '/private/assets' ] as $dir )
		mkdir( $sandbox. $dir, 0777, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [
		'/nino/error/log'					=> false,
		'/nino/error/display'			=> true,
		'/nino/locales/native'		=> 'de_DE',
		'/nino/locales/available'	=> [ 'de_DE', 'en_US' ],
		'/nino/modules'						=> $appData['/nino/modules'],
		'/nino/html/assets'				=> [],
		'/nino/http/routes'				=> [],
	] );

	[ $status ] = ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => array_keys( \Nino\Install\Setup::units() ), 'native' => 'de_DE' ] );

	if( $status !== 200 || \Nino\Install\StarterPages::apply( $appData ) !== true )
		throw new \RuntimeException( 'the starter site could not be built' );

	\Nino\Features::mergeText( $appData, '/text/global.php', [
		'[[/project/mail/address/owner]]'		=> 'owner@example.com',
		'[[/project/website/general/url]]'	=> 'example.com',
	], true );

	// Three pages of the cases below: a form a project wrote by hand with no token field but a call of the module, a page with a token field
	// in a form of its own and a page that has none - their templates are the project's
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-lazy.tpl', '[template /templates/html-header]<main><form action="/elsewhere" method="post">[csrf]</form>[prose text="x"]</main>[template /templates/html-footer]' );
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-lazy-call.tpl', '[template /templates/html-header]<main>[lazy-draws]</main>[template /templates/html-footer]' );
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-lazy-forged.tpl', '[template /templates/html-header]<main><!--nino-bundle-0000000000000000 /.cache/form.css-->[csrf]</main>[template /templates/html-footer]' );

	// The words come first in the page here, the form after them; and a form that is drawn by a nested render, as the catalogue draws its own
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-lazy-js.tpl', '[template /templates/html-header]<main>[jstext]<form action="/elsewhere" method="post">[csrf]</form></main>[assets /.cache/script.js]' );
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-lazy-nested.tpl', '[template /templates/html-header]<main>[lazy-nested]</main>[template /templates/html-footer]' );

	// Two blocks of the words on one page, both before the form
	\Nino\Filesystem::putFileContent( $appData, '/templates/page-lazy-twice.tpl', '[template /templates/html-header]<main>[jstext]<p>between</p>[jstext]<form action="/elsewhere" method="post">[csrf]</form></main>[assets /.cache/script.js]' );

	$routes = \Nino\AppData::stored( $appData, '/nino/http/routes', [] );
	foreach( [ 'lazy', 'lazy-call', 'lazy-forged', 'lazy-js', 'lazy-nested', 'lazy-twice' ] as $name )
		$routes['GET://'. $name] = [ 'uri' => '/'. $name, 'body' => '[template /templates/page-'. $name. ']' ];
	$appData['/nino/http/routes'] = $routes;
	\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

	// The files of the kernel the bundles are made of: the sandbox is the root of the site
	exec( 'cp -a '. escapeshellarg( dirname( __DIR__ ). '/_nino' ). ' '. escapeshellarg( $sandbox. '/_nino' ) );

	return $sandbox;
}

/**
 *	A request of the site, through \Nino\request() as index.php makes it. The mails the form sends are caught by a transport of the test
 *
 *	@param		string		$sandbox
 *	@param		string		$method
 *	@param		string		$uri
 *	@param		array			$post					The fields of a post
 *	@param		array			$config				Keys of the site that are changed for this request
 *	@param		string|null	$token			The token of the visitor's session, null for a visitor who has none
 *	@param		array			$server				More of $_SERVER: the header of the script of the page, say
 *
 *	@return 	array										The request
 */
function lazyVisit( string $sandbox, string $method, string $uri, array $post = [], array $config = [], ?string $token = null, array $server = [] ): array {

	$_SESSION = [];
	$_POST = $post;

	$appData = [ './nino/uid' => $sandbox ];
	\Nino\AppData::prepare( $appData );

	$appData['./nino/filesystem/path']				= $sandbox;
	$appData['./nino/filesystem/configpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/contentpath']	= $sandbox. '/private';
	$appData['./nino/filesystem/publicpath']	= $sandbox. '/public';
	$appData['./nino/test/sandbox']						= $sandbox;

	\Nino\Filesystem::init( $appData );
	\Nino\AppData::init( $appData );

	foreach( $config as $key => $value )
		$appData[$key] = $value;

	$appData['/nino/dir'] = '';
	\Nino\Locales::init( $appData );
	\Nino\Csrf::init( $appData );
	\Nino\Html::init( $appData );
	\Nino\Auth::init( $appData );
	\Nino\Features::registerAssets( $appData );
	\Nino\Features::registerComponents( $appData );
	\Nino\Modules::callModules( $appData, 'init' );
	\Nino\Features::registerParts( $appData );

	// A shortcode of the test that says a form is drawn, with no token field
	\Nino\Html::addShortcode( $appData, 'lazy-draws', static function( array &$appData, array $args ): string {
		\Nino\Modules\Form::draws( $appData );
		return '<form action="/.form" method="post" class="nino-form"></form>';
	}, false );

	// A form whose token field is drawn by a render of its own inside the page, as the components of the catalogue draw theirs
	\Nino\Html::addShortcode( $appData, 'lazy-nested', static function( array &$appData, array $args ): string {
		return '<form class="nino-form" action="/.form" method="post">'. \Nino\Html::renderHtml( $appData, '[csrf]' ). '</form>';
	}, false );

	// The mails are the transport's: none leaves the machine
	$GLOBALS['lazyMails'] = [];
	\Nino\Callbacks::registerCallback( $appData, \Nino\Mail::TRANSPORT, static function( array &$appData, array $mail ): array {
		$GLOBALS['lazyMails'][] = [ 'to' => $mail['to'], 'subject' => $mail['subject'] ];
		$mail['sent'] = true;
		return $mail;
	} );

	if( $token !== null )
		\Nino\Runtime::setSessionValue( $appData, './nino/csrf/token', $token );

	$appData['./nino/locales/current'] = 'de_DE';

	$GLOBALS['lazyApp'] = $appData;
	$request = \Nino\request( $appData, [ 'REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] + $server );
	$GLOBALS['lazyApp'] = $appData;

	return $request;
}

/**
 *	A page that is stored by the page cache the way the output of a request stores it, and served from it the way a request after it is
 *
 *	@param		array			$rendered			The request lazyVisit() answered, a GET of a page
 *
 *	@return 	array										The request as the cache hands the stored page on, [] where the page was not stored
 */
function lazyStored( array $rendered ): array {

	$appData = $GLOBALS['lazyApp'];
	\Nino\Modules\Cache::callbackOutput( $appData, $rendered );

	return lazyServed( (string) $rendered['/nino/http/request']['uri'] );
}

/**
 *	What the page cache answers for a page: the request as it hands the stored page on, [] where none is served
 *
 *	@param		string		$uri
 *
 *	@return 	array
 */
function lazyServed( string $uri ): array {

	$appData = $GLOBALS['lazyApp'];
	$hit = [ '/nino/http/request' => [ 'method' => 'GET', 'rawMethod' => 'GET', 'uri' => $uri, 'query' => [] ], '/nino/http/response' => [ 'uri' => $uri, 'locale' => 'de_DE', 'statusCode' => 200, 'header' => [], 'body' => '' ] ];

	return \Nino\Modules\Cache::_prepare( $appData, $hit ) === true ? $hit : [];
}

/**
 *	What a page links: the hrefs of its stylesheets in the head, the srcs of its scripts with a file, and whether the inline block of
 *	the words has any
 *
 *	@param		string		$html
 *
 *	@return 	array										[ 'css' => [ hrefs ], 'js' => [ srcs ], 'words' => int, 'mark' => bool ]
 */
function lazyLinks( string $html ): array {

	$css = $js = [];
	preg_match_all( '/<link rel="stylesheet" href="([^"?]+)/', $html, $found );
	foreach( $found[1] as $href )
		$css[] = basename( $href );
	preg_match_all( '/<script src="([^"?]+)/', $html, $found );
	foreach( $found[1] as $src )
		$js[] = basename( $src );

	$words = preg_match( '/NinoJstext=(.*?);<\/script>/s', $html, $block ) === 1 ? count( (array) json_decode( $block[1], true ) ) : -1;

	return [ 'css' => $css, 'js' => $js, 'words' => $words, 'mark' => str_contains( $html, '<!--nino-bundle' ) ];
}

$sandbox = lazySite();

// A page the engine is not drawn by: the contact form of the starter is html and a token field
echo "A page that draws no form\n";

$home = lazyVisit( $sandbox, 'GET', '/' );
$homeLinks = lazyLinks( (string) $home['/nino/http/response']['body'] );
check( 'the page without a form links the style bundle and the script bundle of the site and nothing of the form', $home['/nino/http/response']['statusCode'] === 200
	&& $homeLinks['css'] === [ 'style.css' ] && $homeLinks['js'] === [ 'script.js' ] );
check( '...carries none of the words of the script', $homeLinks['words'] === 0 );
check( '...leaves no mark of a bundle behind in the page', $homeLinks['mark'] === false );
check( '...and the engine of the form is not loaded for it', class_exists( '\\Nino\\Form', false ) === false );

$styleBundle = (string) file_get_contents( $sandbox. '/public/.cache/style.css' );
check( 'the bundles of the site hold nothing of the module: no look of the form, no script of it', str_contains( $styleBundle, '.nino-form' ) === false
	&& is_file( $sandbox. '/public/.cache/form.css' ) === false && is_file( $sandbox. '/public/.cache/form.js' ) === false );

echo "\nA page that draws a form\n";

$contact = lazyVisit( $sandbox, 'GET', '/contact' );
$contactBody = (string) $contact['/nino/http/response']['body'];
$contactLinks = lazyLinks( $contactBody );
check( 'the contact page links the bundles of the form behind the bundles of the site', $contactLinks['css'] === [ 'style.css', 'form.css' ] && $contactLinks['js'] === [ 'script.js', 'form.js' ] );
check( '...the stylesheet in the head and the script at the end of the body, where the site links its own', strpos( $contactBody, 'form.css' ) < strpos( $contactBody, '</head>' ) && strpos( $contactBody, 'form.js' ) > strpos( $contactBody, '<main' ) );
check( '...with the words of the script, and no mark behind', $contactLinks['words'] > 0 && $contactLinks['mark'] === false );
check( '...whose files are the module\'s: the look of the form and its script', str_contains( (string) @file_get_contents( $sandbox. '/public/.cache/form.css' ), '.nino-form' ) === true
	&& str_contains( (string) @file_get_contents( $sandbox. '/public/.cache/form.js' ), 'Nino.form' ) === true );
check( '...and the page is drawn without the engine as well', class_exists( '\\Nino\\Form', false ) === false );

$hand = lazyVisit( $sandbox, 'GET', '/lazy' );
$handLinks = lazyLinks( (string) $hand['/nino/http/response']['body'] );
check( 'a form of a project\'s own template that carries a token field is a page with a form', $handLinks['css'] === [ 'style.css', 'form.css' ] && $handLinks['js'] === [ 'script.js', 'form.js' ] && $handLinks['words'] > 0 );

$called = lazyVisit( $sandbox, 'GET', '/lazy-call' );
$calledLinks = lazyLinks( (string) $called['/nino/http/response']['body'] );
check( 'a page whose code says it draws a form, with no token field, is a page with a form', $calledLinks['css'] === [ 'style.css', 'form.css' ] && $calledLinks['js'] === [ 'script.js', 'form.js' ] && $calledLinks['words'] > 0 );

$early = lazyVisit( $sandbox, 'GET', '/lazy-js' );
$earlyLinks = lazyLinks( (string) $early['/nino/http/response']['body'] );
check( 'the words of the script are in the block wherever the page puts it: a block that stands before the form has them', $earlyLinks['words'] > 0 && $earlyLinks['mark'] === false
	&& str_contains( (string) $early['/nino/http/response']['body'], '<!--nino-jstext' ) === false );

$twice = lazyVisit( $sandbox, 'GET', '/lazy-twice' );
$twiceBody = (string) $twice['/nino/http/response']['body'];
preg_match_all( '/NinoJstext=(.*?);<\/script>/s', $twiceBody, $blocks );
check( 'a page with two blocks of the words has both filled, each with the words, and no mark left', count( $blocks[1] ) === 2 && $blocks[1][0] === $blocks[1][1]
	&& count( (array) json_decode( $blocks[1][0], true ) ) > 0 && str_contains( $twiceBody, '<!--nino-jstext' ) === false );

$markA = $markB = [];
$firstMark = \Nino\Modules\Jstext::doMark( $markA, [] );
$secondMark = \Nino\Modules\Jstext::doMark( $markB, [] );
check( 'the mark of the words is made anew for every request, from random bytes: no page can know it', preg_match( '/^<!--nino-jstext-[0-9a-f]{16}-->$/', $firstMark ) === 1
	&& preg_match( '/^<!--nino-jstext-[0-9a-f]{16}-->$/', $secondMark ) === 1 && $firstMark !== $secondMark && \Nino\Modules\Jstext::doMark( $markA, [] ) === $firstMark );

$nested = lazyVisit( $sandbox, 'GET', '/lazy-nested' );
$nestedBody = (string) $nested['/nino/http/response']['body'];
$nestedLinks = lazyLinks( $nestedBody );
check( 'a form whose token field is drawn by a render of its own inside the page - as the catalogue draws its forms - is a page with a form, with no mark of a bundle or of the words left', $nestedLinks['css'] === [ 'style.css', 'form.css' ]
	&& $nestedLinks['js'] === [ 'script.js', 'form.js' ] && $nestedLinks['words'] > 0 && $nestedLinks['mark'] === false && str_contains( $nestedBody, '<!--nino-' ) === false );

$forged = lazyVisit( $sandbox, 'GET', '/lazy-forged' );
$forgedBody = (string) $forged['/nino/http/response']['body'];
check( 'a mark that a page writes itself is no mark: it stays the comment it is, and the one link of the form is the page\'s own', substr_count( $forgedBody, '<!--nino-bundle-0000000000000000 /.cache/form.css-->' ) === 1
	&& substr_count( $forgedBody, 'rel="stylesheet" href="/public/.cache/form.css' ) === 1 );

$modules = array_values( array_filter( (array) ( ( include $sandbox. '/private/config.php' )['/nino/modules'] ?? [] ), static fn( string $class ): bool => $class !== '\\Nino\\Modules\\Form' ) );
$off = lazyVisit( $sandbox, 'GET', '/contact', [], [ '/nino/modules' => $modules ] );
$offLinks = lazyLinks( (string) $off['/nino/http/response']['body'] );
check( 'a site that switched the module off has no form bundles on the contact page and no mark', $offLinks['css'] === [ 'style.css' ] && $offLinks['js'] === [ 'script.js' ] && $offLinks['mark'] === false );

echo "\nThe page cache\n";

$cache = [ '/nino/cache/status' => true, '/nino/cache/ttl' => 3600, '/nino/cache/blacklist' => [] ];
$contactHit = lazyStored( lazyVisit( $sandbox, 'GET', '/contact', [], $cache ) );
$homeHit = lazyStored( lazyVisit( $sandbox, 'GET', '/', [], $cache ) );
check( 'a stored contact page is served with the bundles of the form, a stored page without a form without them', $contactHit !== [] && $homeHit !== []
	&& lazyLinks( (string) $contactHit['/nino/http/response']['body'] )['css'] === [ 'style.css', 'form.css' ] && lazyLinks( (string) $contactHit['/nino/http/response']['body'] )['js'] === [ 'script.js', 'form.js' ]
	&& lazyLinks( (string) $homeHit['/nino/http/response']['body'] )['css'] === [ 'style.css' ] && lazyLinks( (string) $homeHit['/nino/http/response']['body'] )['js'] === [ 'script.js' ] );

$entryFile = $sandbox. '/private/data/cache/'. sha1( '/contact|de_DE' ). '.php';
$entry = (array) include $entryFile;
check( 'the stored contact page carries the stamp of the bundle lists it was drawn with', is_string( $entry['assets'] ?? null ) === true && lazyServed( '/contact' ) !== [] );
$old = $entry;
unset( $old['assets'] );
file_put_contents( $entryFile, '<?php return '. var_export( $old, true ). ';' );
check( 'a page stored before the lists were told - one of an older kernel - is not served: it is drawn again, with the files of the form', lazyServed( '/contact' ) === [] );
file_put_contents( $entryFile, '<?php return '. var_export( [ 'assets' => sha1( 'other lists' ) ] + $entry, true ). ';' );
check( '...and neither is one stored with other lists, which is removed where it is found', lazyServed( '/contact' ) === [] && is_file( $entryFile ) === false );

// The stamp follows the lists themselves: the files of a bundle, and the bundles that follow one
file_put_contents( $entryFile, '<?php return '. var_export( $entry, true ). ';' );
$listsBefore = $GLOBALS['lazyApp'];
check( 'the entry as it was stored is served', lazyServed( '/contact' ) !== [] );
$GLOBALS['lazyApp']['/nino/html/assets']['/.cache/style.css'][] = '/assets/moved-here.css';
check( 'a file that joined a bundle since the page was stored makes the stored page one of other lists', lazyServed( '/contact' ) === [] );
$GLOBALS['lazyApp'] = $listsBefore;
file_put_contents( $entryFile, '<?php return '. var_export( $entry, true ). ';' );
$GLOBALS['lazyApp']['./nino/assets/followers']['/.cache/style.css'][] = '/.cache/more.css';
check( '...and so does a bundle that follows another since then', lazyServed( '/contact' ) === [] );
$GLOBALS['lazyApp'] = $listsBefore;
file_put_contents( $entryFile, '<?php return '. var_export( $entry, true ). ';' );
check( '...and with the lists as they were, the page is served as before', lazyServed( '/contact' ) !== [] );

echo "\nThe post to the address of the form\n";

$fields = [ 'name' => 'Jo Client', 'email' => 'jo@example.com', 'message' => 'Hello', 'location' => '' ];

$script = [ 'HTTP_X_NINO_FORM' => '1' ];

$noToken = lazyVisit( $sandbox, 'POST', '/.form', $fields, [], null, $script );
check( 'a post with no token is refused by the guard, with no mail and no record', $noToken['/nino/http/response']['statusCode'] === 403 && $GLOBALS['lazyMails'] === []
	&& is_file( $sandbox. '/private/data/forms.'. date( 'Y-m' ). '.php' ) === false );

$wrongToken = lazyVisit( $sandbox, 'POST', '/.form', [ '_csrf' => 'not-the-token' ] + $fields, [], str_repeat( 'a', 64 ), $script );
check( 'a post with a wrong token is refused as well', $wrongToken['/nino/http/response']['statusCode'] === 403 && $GLOBALS['lazyMails'] === [] );

$trapped = lazyVisit( $sandbox, 'POST', '/.form', [ '_csrf' => str_repeat( 'a', 64 ), 'location' => 'a bot' ] + $fields, [], str_repeat( 'a', 64 ), $script );
check( 'a post that fills the honeypot is answered 418, with no mail', $trapped['/nino/http/response']['statusCode'] === 418 && $GLOBALS['lazyMails'] === [] );

$missing = lazyVisit( $sandbox, 'POST', '/.form', [ '_csrf' => str_repeat( 'a', 64 ), 'name' => '' ] + $fields, [], str_repeat( 'a', 64 ), $script );
check( 'a post that misses a field is answered 400', $missing['/nino/http/response']['statusCode'] === 400 && $GLOBALS['lazyMails'] === [] );

$sent = lazyVisit( $sandbox, 'POST', '/.form', [ '_csrf' => str_repeat( 'a', 64 ) ] + $fields, [], str_repeat( 'a', 64 ), $script );
check( 'a valid post is answered 200, mails the owner and the visitor and is recorded', $sent['/nino/http/response']['statusCode'] === 200 && array_column( $GLOBALS['lazyMails'], 'to' ) === [ 'owner@example.com', 'jo@example.com' ]
	&& count( (array) include $sandbox. '/private/data/forms.'. date( 'Y-m' ). '.php' ) === 1 );
check( '...and the engine is loaded by the post', class_exists( '\\Nino\\Form', false ) === true );

$capped = [ '/nino/form/hour-cap' => 1 ];
$second = lazyVisit( $sandbox, 'POST', '/.form', [ '_csrf' => str_repeat( 'a', 64 ), 'email' => 'jo2@example.com' ] + $fields, $capped, str_repeat( 'a', 64 ), $script );
check( 'a site that takes one submission an hour answers the second with 429, sends nothing and records nothing', $second['/nino/http/response']['statusCode'] === 429 && $GLOBALS['lazyMails'] === []
	&& count( (array) include $sandbox. '/private/data/forms.'. date( 'Y-m' ). '.php' ) === 1 );

$_SERVER['HTTPS'] = 'on';
$browser = lazyVisit( $sandbox, 'POST', '/.form', [ '_csrf' => str_repeat( 'a', 64 ) ] + $fields, [], str_repeat( 'a', 64 ), [ 'HTTP_REFERER' => 'https://example.com/contact' ] );
unset( $_SERVER['HTTPS'] );
check( 'a browser that posts without the script is sent back to the page it came from, with the outcome in the address', $browser['/nino/http/response']['statusCode'] === 303
	&& str_starts_with( (string) ( $browser['/nino/http/response']['header']['Location'] ?? '' ), '/contact?form=' ) === true );

echo "\nA bundle that follows another\n";

$bundle = ninoSandbox( 'form-lazy-assets' );
$bundleDir = ninoSandboxDir( $bundle );
$bundle['/nino/modules'] = [ '\\Nino\\Modules\\Assets' ];
mkdir( $bundleDir. '/private/assets', 0777, true );
\Nino\Filesystem::putFileContent( $bundle, '/assets/extra.css', '.extra { color: red; }' );
\Nino\Filesystem::putFileContent( $bundle, '/assets/base.css', '.base { color: blue; }' );
\Nino\Html::addAsset( $bundle, '/.cache/style.css', '/assets/base.css' );
\Nino\Modules\Assets::init( $bundle );
\Nino\Modules\Assets::follow( $bundle, '/.cache/style.css', '/.cache/extra.css' );
\Nino\Modules\Assets::follow( $bundle, '/.cache/style.css', '/.cache/extra.css' );
$page = '[assets /.cache/style.css]<main></main>[assets /.cache/style.css]';
$links = static function( string $html ): array {
	preg_match_all( '/href="([^"?]+)/', $html, $found );
	return array_map( 'basename', $found[1] );
};
$none = \Nino\Html::renderHtml( $bundle, $page );
check( 'a bundle that follows another is not linked by a page that does not need it, and leaves no mark', array_values( $links( $none ) ) === [ 'style.css', 'style.css' ] && str_contains( $none, '<!--nino-bundle' ) === false );
\Nino\Modules\Assets::need( $bundle, '/.cache/extra.css' );
$bare = \Nino\Html::renderHtml( $bundle, $page );
check( '...nor is one that has no files, whatever the page needs', array_values( $links( $bare ) ) === [ 'style.css', 'style.css' ] && str_contains( $bare, '<!--nino-bundle' ) === false );
\Nino\Html::addAsset( $bundle, '/.cache/extra.css', '/assets/extra.css' );
$needed = \Nino\Html::renderHtml( $bundle, $page );
check( 'a page that needs it has its link once, behind the first link it follows, and the second call of the page leaves no mark', array_values( $links( $needed ) ) === [ 'style.css', 'extra.css', 'style.css' ] && str_contains( $needed, '<!--nino-bundle' ) === false
	&& str_contains( (string) @file_get_contents( $bundleDir. '/public/.cache/extra.css' ), '.extra' ) === true );

\Nino\Filesystem::putFileContent( $bundle, '/assets/main.js', 'var main = 1;' );
\Nino\Filesystem::putFileContent( $bundle, '/assets/more.js', 'var more = 1;' );
\Nino\Html::addAsset( $bundle, '/.cache/script.js', '/assets/main.js' );
\Nino\Html::addAsset( $bundle, '/.cache/more.js', '/assets/more.js' );
\Nino\Modules\Assets::follow( $bundle, '/.cache/script.js', '/.cache/more.js' );
\Nino\Modules\Assets::need( $bundle, '/.cache/more.js' );
$scripts = \Nino\Html::renderHtml( $bundle, '[assets /.cache/script.js]' );
check( 'a bundle that follows another is linked behind a script link that is drawn', str_contains( $scripts, 'more.js' ) === true );
\Nino\Callbacks::registerCallback( $bundle, '/nino/shortcodes/assets/output/js', static fn( array &$appData, string $template ): string => '' );
$nothing = \Nino\Html::renderHtml( $bundle, '[assets /.cache/script.js]' );
check( 'a link that a callback answers with nothing - a page that is a picture has no script - leaves no link of a bundle that follows it, and no mark', $nothing === '' );

// A callback that answers another tag for the link a template writes - a preview that puts its own stylesheet in its place - is asked for that link and for no other
\Nino\Callbacks::registerCallback( $bundle, '/nino/shortcodes/assets/output/css', static fn( array &$appData, string $template ): string => '<style>/* draft */</style>' );
$drafted = \Nino\Html::renderHtml( $bundle, '[assets /.cache/style.css]' );
check( 'a callback that changes the link of the bundle a page names does not change the link of the bundle that follows it', substr_count( $drafted, '<style>/* draft */</style>' ) === 1
	&& array_values( $links( $drafted ) ) === [ 'extra.css' ] );

$_SESSION = [];
\Nino\Filesystem::removeDir( $bundleDir );

$long = lazyVisit( $sandbox, 'POST', '/.form', [ '_csrf' => str_repeat( 'a', 64 ), 'message' => str_repeat( 'x', 5000 ) ] + $fields, [], str_repeat( 'a', 64 ), $script );
$records = (array) include $sandbox. '/private/data/forms.'. date( 'Y-m' ). '.php';
check( 'a message of 5000 characters is taken and recorded cut to the limit of a field, MAX_FIELD_LENGTH, which is 1000', $long['/nino/http/response']['statusCode'] === 200 && \Nino\Form::MAX_FIELD_LENGTH === 1000
	&& strlen( (string) ( end( $records )['message'] ?? '' ) ) === 1000 );

echo "\nThe question the guard's refusal is asked by\n";

$refused = [ './nino/csrf/blocked' => true ];
\Nino\Csrf::watched( true );
check( 'the callbacks of a route ask \\Nino\\Form::blocked() or \\Nino\\Csrf::blocked(), and either counts as the one question', \Nino\Csrf::watched( false ) === false && \Nino\Form::blocked( $refused ) === true && \Nino\Csrf::watched( false ) === true
	&& \Nino\Csrf::watched( true ) === false && \Nino\Csrf::blocked( $refused ) === true && \Nino\Form::watched( false ) === true && \Nino\Csrf::blocked( [] ) === false );

ninoDone( $GLOBALS['lazySandbox'] );
