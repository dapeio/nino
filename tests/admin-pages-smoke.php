<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	admin-pages-smoke.php	Dependency-free smoke test for the page panels of the workbench:
 *											PageEditor - create, edit and delete a page route, the pages a feature
 *											routes at runtime - and Modules\Navigation\Admin, which menus exist and
 *											what stands in them. Runs against an isolated sandbox directory, never
 *											touches the real project data.
 *
 *	Usage: php tests/admin-pages-smoke.php
 */

require __DIR__. '/harness.php';

/**
 *	Dispatch one action directly against a Dev\* module class
 *
 *	A copy of the helper in admin-system-smoke.php, which the other admin suites
 *	carry as well: it is no helper the harness should hold
 *
 *	@param		array 		&$appData
 *	@param		string		$class				eg. "\Nino\Modules\Config\Admin"
 *	@param		string		$method				eg. "apiList"
 *	@param		array 		$data					Post data
 *
 *	@return		array										[ statusCode, body, request ]
 */
function callDev( array &$appData, string $class, string $method, array $data = [] ): array {
	return ninoCall( $appData, [ $class, $method ], $data );
}

$appData = ninoSandbox( 'admin-pages' );
$sandbox = ninoSandboxDir( $appData );

// A set-up project with one developer account, logged in - the shell serves
// the workbench, every panel action runs against this session
ninoUser( $appData, 'dev@example.com' );

echo "Sandbox: $sandbox\n\n";


// --- Dev\PageEditor - create/edit/delete page routes -------------------------

echo "PageEditor - create/edit/delete page routes\n";

// The three languages the checks below expect
callDev( $appData, \Nino\Modules\Language\Admin::class, 'apiSave', [ 'fields' => [
	'/nino/locales/available' => [ 'de_DE', 'en_US', 'fr_FR' ],
	'/nino/locales/native' 		=> 'de_DE',
] ] );

// A project has a templates/ directory for its page templates
mkdir( $sandbox. '/private/templates', 0777, true );

file_put_contents( $sandbox. '/private/templates/page-about.tpl', '<h1>About</h1>' );
file_put_contents( $sandbox. '/private/templates/page-contact.tpl', '<h1>Contact</h1>' );
file_put_contents( $sandbox. '/private/templates/not-a-page.tpl', '<p>ignored - not a page-*.tpl file</p>' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'starts with an empty page list', $body['pages'] === [] );
check( 'lists only templates/page-*.tpl files, sorted, extension stripped', $body['templates'] === [ 'page-about', 'page-contact' ] );
check( 'no navigations are offered - Navigation was never picked', $body['navs'] === [] );
check( 'a project with only finished pages is proposed no template - the form asks for a choice', $body['defaultTemplate'] === '' );
check( 'the list names the locale the workbench is on', $body['selectedLocale'] === \Nino\Admin\Admin::sessionLocale( $appData ) && in_array( $body['selectedLocale'], $body['locales'], true ) );

// The proposal is the blank page and nothing else - not the template that sorts
// first, which is the 404 here, and not any other finished page
file_put_contents( $sandbox. '/private/templates/page-404.tpl', '<h1>Not found</h1>' );
file_put_contents( $sandbox. '/private/templates/page-blank.tpl', '' );
[ , $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( 'page-blank is the proposed template when the project has it', $body['templates'][0] === 'page-404' && $body['defaultTemplate'] === 'page-blank' );
unlink( $sandbox. '/private/templates/page-blank.tpl' );
[ , $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( '...and never page-404, even when that one sorts first and is all there is besides', $body['templates'][0] === 'page-404' && $body['defaultTemplate'] === '' );
unlink( $sandbox. '/private/templates/page-404.tpl' );

// What the save checks of a new route are written against: a name and a title
// in every active language
$pageText = static function( string $name ) use ( &$appData ): array {
	$text = [];
	foreach( \Nino\Locales::getAvailableLocales( $appData ) as $locale )
		$text[$locale] = [ 'name' => $name, 'title' => $name. ' - '. $locale ];
	return $text;
};
check( 'the fixture project has more than two active languages, so "every language" is more than a pair', count( $pageText('x') ) >= 3 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '../etc/passwd', 'httpUri' => '/about', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an unsafe Element-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/about', 'httpUri' => '../etc/passwd', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an unsafe Http-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/admin-shadow', 'httpUri' => '/_admin', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects a page mounted on a runtime-owned tool uri', $status === 409 );

\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://owned'] = [ 'uri' => '/owned', 'body' => 'hand-written route' ];
	return $config;
} );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/owned-page', 'httpUri' => '/owned', 'template' => 'page-about', 'text' => [],
] );
check( 'rejects an Http-URI already owned by a non-page route', $status === 409 );
check( 'keeps the colliding hand-written route unchanged', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://owned']['body'] ?? null ) === 'hand-written route' );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/about', 'httpUri' => '/about', 'template' => 'not-a-page', 'text' => [],
] );
check( 'rejects a template outside the page-*.tpl whitelist with 400', $status === 400 );

// The actual, first real save: Element-URI deliberately differs from
// Http-URI, to prove the class uses the entry's own uri, not the request
// path, for the route's own data field/meta namespace
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-about', 'httpUri' => '/about', 'template' => 'page-about', 'statusCode' => 200, 'text' => [
		'de_DE' => [ 'name' => 'Über uns', 'title' => 'Über uns', 'description' => 'Über unser Unternehmen.' ],
		'en_US' => [ 'name' => 'About us', 'title' => 'About us', 'description' => '' ],
		'fr_FR' => [ 'name' => 'A propos', 'title' => 'A propos' ],
	],
] );
check( 'apiSave succeeds', $status === 200 );
check( 'response echoes the persisted list', count( $body['pages'] ) === 1 );

$configAfterSave = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'registers a route at "/about" (its Http-URI)', isset( $configAfterSave['/nino/http/routes']['GET://about'] ) === true );
check( 'the route\'s own "uri" data field is the Element-URI, not the Http-URI', $configAfterSave['/nino/http/routes']['GET://about']['uri'] === '/site-about' );
check( 'the route body references the picked template, extension stripped', $configAfterSave['/nino/http/routes']['GET://about']['body'] === '[template /templates/page-about]' );
check( 'a 200 status code is not written out (the implicit default)', isset( $configAfterSave['/nino/http/routes']['GET://about']['statusCode'] ) === false );
check( 'the route is the whole persistence - no second list is written alongside it', isset( $configAfterSave['/nino/install/webpages'] ) === false && count( array_filter( $configAfterSave['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Modules\Routes\Admin::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === 1 );

$deAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'writes the page\'s own de_DE meta, keyed by its Element-URI', $deAfterSave['[[/_nino/webpage/site-about/name]]'] === 'Über uns' && $deAfterSave['[[/_nino/webpage/site-about/title]]'] === 'Über uns' );

$enAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'a blank description is stored as it is, empty - there is no filler text any more', $enAfterSave['[[/_nino/webpage/site-about/description]]'] === '' && $enAfterSave['[[/_nino/webpage/site-about/name]]'] === 'About us' );
check( '...and no "Page" or "Page Title" placeholder is written anywhere', in_array( 'Page', $enAfterSave, true ) === false && in_array( 'Page Title', $deAfterSave + $enAfterSave, true ) === false );

$globalAfterSave = \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] );
check( 'writes the page\'s reachable Http-URI as one global fill a template can link to by name', ( $globalAfterSave['[[/_nino/webpage/site-about/uri]]'] ?? null ) === '/about'
	&& isset( $deAfterSave['[[/_nino/webpage/site-about/uri]]'], $enAfterSave['[[/_nino/webpage/site-about/uri]]'] ) === false );
check( 'that uri is blacklisted as a technical value, like every other route key', in_array( '/_nino/webpage/site-about/uri', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) );

// A name and a title are required in every active language, and a refusal
// writes nothing: not the route, not one of the texts
$configBeforeRefusal = file_get_contents( $sandbox. '/private/config.php' );
$textBeforeRefusal 	= [ file_get_contents( $sandbox. '/private/text/de_DE.php' ), file_get_contents( $sandbox. '/private/text/en_US.php' ), file_get_contents( $sandbox. '/private/text/global.php' ) ];
$complete = $pageText('Contact');
$without 	= static fn( string $locale, string $field ): array => array_replace( $complete, [ $locale => array_diff_key( $complete[$locale], [ $field => 1 ] ) ] );
$refusals = [
	'a name missing in one language' 	=> [ array_diff_key( $complete, [ 'fr_FR' => 1 ] ), 'routes_missing_name', 'fr_FR' ],
	'a blank name' 										=> [ array_replace( $complete, [ 'de_DE' => [ 'name' => '   ', 'title' => 'T' ] ] ), 'routes_missing_name', 'de_DE' ],
	'a name that is not text' 				=> [ array_replace( $complete, [ 'en_US' => [ 'name' => [ 'x' ], 'title' => 'T' ] ] ), 'routes_missing_name', 'en_US' ],
	'a title missing in one language' => [ $without( 'en_US', 'title' ), 'routes_missing_title', 'en_US' ],
	'a blank title' 									=> [ array_replace( $complete, [ 'de_DE' => [ 'name' => 'Kontakt', 'title' => "\t" ] ] ), 'routes_missing_title', 'de_DE' ],
	'no text at all' 									=> [ [], 'routes_missing_name', 'de_DE' ],
];
foreach( $refusals as $label => [ $refusedText, $refusedCode, $refusedLocale ] ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
		'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'text' => $refusedText,
	] );
	check( $label. ' is refused with 400, a code and the language', $status === 400 && ( $body['code'] ?? '' ) === $refusedCode && ( $body['params'] ?? [] ) === [ $refusedLocale ] );
}
check( '...and every one of them left config.php and the text files exactly as they were', file_get_contents( $sandbox. '/private/config.php' ) === $configBeforeRefusal
	&& $textBeforeRefusal === [ file_get_contents( $sandbox. '/private/text/de_DE.php' ), file_get_contents( $sandbox. '/private/text/en_US.php' ), file_get_contents( $sandbox. '/private/text/global.php' ) ] );

// Duplicate checks: a second entry may not reuse either uri
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-about', 'httpUri' => '/contact', 'template' => 'page-contact', 'text' => [],
] );
check( 'rejects a duplicate Element-URI with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/about', 'template' => 'page-contact', 'text' => [],
] );
check( 'rejects a duplicate Http-URI with 400', $status === 400 );

// A real second entry, with a non-200 status code and explicit menu membership
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'statusCode' => 404, 'navs' => [ 'main' ], 'text' => $pageText('Kontakt'),
] );
check( 'apiSave succeeds for the second entry too', $status === 200 );

$configAfterSecond = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'a non-200, in-range status code is written out as posted', $configAfterSecond['/nino/http/routes']['GET://contact']['statusCode'] === 404 );
check( 'both page routes are now persisted, in order', array_keys( array_filter( $configAfterSecond['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Modules\Routes\Admin::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === [ 'GET://about', 'GET://contact' ] );

// Navigation: only regenerated while the module is active
$appData['/nino/modules'][] = '\\Nino\\Modules\\Navigation';
// The registry is what a menu name is checked against, and it is explicit -
// there is no implied default menu to fall back on
$appData['/nino/html/navs'] = [ 'main' ];

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/contact', 'uri' => '/site-contact', 'httpUri' => '/contact', 'template' => 'page-contact', 'navs' => [ 'main' ], 'text' => $pageText('Kontakt'),
] );
check( 'resaving an existing entry unchanged (identified by originalHttpUri) succeeds', $status === 200 );

// Menu membership lives on the route this entry owns, not in a generated
// textfill - see \Nino\Modules\Navigation::routeLines()
$routesAfterNav = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'a page explicitly assigned to main joins that menu on its own route', ( $routesAfterNav['GET://contact']['navs'] ?? null ) === [ 'main' => 1 ] );
check( 'a page in no menu carries no membership at all', isset( $routesAfterNav['GET://about']['navs'] ) === false );

// Reordering: assign both entries to main so the generated menu actually
// shows a reorder, not just the list itself
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/about', 'template' => 'page-about', 'navs' => [ 'main' ], 'text' => $pageText('About'),
] );
check( 'assigning the first entry to main too succeeds', $status === 200 );

// A membership a save newly adds goes behind everything already in that menu
check( 'the second page to join the menu lands behind the first, not on top of it', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://about']['navs'] ?? null ) === [ 'main' => 2 ] );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'up' ] );
check( 'moving the first entry up (already at the top) 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/contact', 'direction' => 'down' ] );
check( 'moving the last entry down (already at the bottom) 400s', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'sideways' ] );
check( 'rejects an invalid direction with 400', $status === 400 );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/does-not-exist', 'direction' => 'up' ] );
check( 'apiMove 404s for an unknown httpUri', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'down' ] );
check( 'moving the first entry down succeeds', $status === 200 );
check( 'the two entries actually swapped places', array_column( $body['pages'], 'httpUri' ) === [ '/contact', '/about' ] );

$configAfterMove = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the swapped order is persisted too', array_slice( array_keys( $configAfterMove['/nino/http/routes'] ), -2 ) === [ 'GET://contact', 'GET://about' ] );
check( 'the hand-written route the swap stepped over kept its own slot', array_keys( $configAfterMove['/nino/http/routes'] )[0] === 'GET://owned' );

// Move it back for the rename/delete checks below, which assume the
// original order ("about" first)
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'up' ] );
check( 'moving it back up succeeds', $status === 200 );

// Renaming an entry's Http-URI: the old route key must disappear, the new
// one appear, and the list must stay at 2 entries (replaced in place)
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/about', 'uri' => '/site-about', 'httpUri' => '/ueber-uns', 'template' => 'page-about', 'text' => $pageText('Über uns'),
] );
check( 'renaming an entry\'s Http-URI succeeds', $status === 200 );

$configAfterRename = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the old route key is gone after a rename', isset( $configAfterRename['/nino/http/routes']['GET://about'] ) === false );
check( 'the new route key exists', isset( $configAfterRename['/nino/http/routes']['GET://ueber-uns'] ) === true );
check( 'still exactly two page routes - rename replaced in place, did not append a third', count( array_filter( $configAfterRename['/nino/http/routes'], fn( array $r, string $k ): bool => \Nino\Modules\Routes\Admin::isPageRoute( $k, $r ), ARRAY_FILTER_USE_BOTH ) ) === 2 );
check( 'the renamed route keeps the slot the old key stood in, rather than dropping to the bottom', array_slice( array_keys( $configAfterRename['/nino/http/routes'] ), -2 ) === [ 'GET://ueber-uns', 'GET://contact' ] );

// Delete
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/ueber-uns' ] );
check( 'apiDelete succeeds', $status === 200 );
check( 'exactly one page remains', count( $body['pages'] ) === 1 );

$configAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'the deleted entry\'s route is gone', isset( $configAfterDelete['/nino/http/routes']['GET://ueber-uns'] ) === false );
check( 'the surviving entry\'s route is untouched', isset( $configAfterDelete['/nino/http/routes']['GET://contact'] ) === true );

$deAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'the deleted entry\'s own text meta is left in place - additive-only, never auto-deleted', isset( $deAfterDelete['[[/_nino/webpage/site-about/name]]'] ) === true );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/does-not-exist' ] );
check( 'apiDelete 404s for an unknown httpUri', $status === 404 );

// What a route carries that the form does not edit stays. Somebody put a
// 'maintenance' => false, a 'locale' and a 'header' on it in config.php - the
// first keeps a page up while the site is down - and a save used to build the
// route anew from the form's four fields and drop the rest
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-kept', 'httpUri' => '/kept', 'template' => 'page-about', 'navs' => [ 'main' ], 'text' => $pageText('Kept'),
] );
check( 'a page for the fields-kept check is created', $status === 200 );
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://kept'] = array_merge( $config['/nino/http/routes']['GET://kept'], [ 'maintenance' => false, 'locale' => 'de_DE', 'header' => [ 'X-Kept' => '1' ], 'statusCode' => 201 ] );
	return $config;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/kept', 'uri' => '/site-kept', 'httpUri' => '/kept-on', 'template' => 'page-contact', 'statusCode' => 200, 'navs' => [], 'text' => $pageText('Kept'),
] );
$keptRoutes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'a save keeps the fields of the route it does not edit - it moved to its new path with them', $status === 200 && isset( $keptRoutes['GET://kept'] ) === false
	&& ( $keptRoutes['GET://kept-on']['maintenance'] ?? null ) === false && ( $keptRoutes['GET://kept-on']['locale'] ?? null ) === 'de_DE' && ( $keptRoutes['GET://kept-on']['header'] ?? null ) === [ 'X-Kept' => '1' ] );
check( '...and sets the four it does edit: the body, the status code - 200 is no field -, the menus', $keptRoutes['GET://kept-on']['body'] === '[template /templates/page-contact]'
	&& isset( $keptRoutes['GET://kept-on']['statusCode'] ) === false && isset( $keptRoutes['GET://kept-on']['navs'] ) === false && $keptRoutes['GET://kept-on']['uri'] === '/site-kept' );
callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/kept-on' ] );

// The key of the page's address goes on the blacklist, and a blacklist that
// cannot be written is an answer of its own: a directory where the sidecar lock
// of text/blacklist.php goes, so lockFile() cannot open it
$blacklistLock = $sandbox. '/private/data/.locks/'. sha1( '/text/blacklist.php' ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $blacklistLock );
@mkdir( $blacklistLock );
check( 'Text::setBlacklisted() says false when the list cannot be written', \Nino\Text::setBlacklisted( $appData, '/_nino/webpage/site-hidden/uri', true ) === false );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '', 'uri' => '/site-hidden', 'httpUri' => '/hidden', 'template' => 'page-about', 'text' => $pageText('Hidden'),
] );
check( 'routes/save that could not write the blacklist says so rather than reporting success', $status === 500 && str_contains( (string) ( $body['error'] ?? '' ), '/text/blacklist.php' ) === true && isset( $body['pages'] ) === false );
@rmdir( $blacklistLock );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( '...and true when it can, and when the key is already as asked', \Nino\Text::setBlacklisted( $appData, '/_nino/webpage/site-hidden/uri', true ) === true && \Nino\Text::setBlacklisted( $appData, '/_nino/webpage/site-hidden/uri', true ) === true
	&& \Nino\Text::setBlacklisted( $appData, '/_nino/webpage/site-hidden/uri', false ) === true && \Nino\Text::setBlacklisted( $appData, '/_nino/webpage/site-hidden/uri', false ) === true );
callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiDelete', [ 'httpUri' => '/hidden' ] );

// The imprint and the privacy policy are the Legal module's
$configBeforeReserved = file_get_contents( $sandbox. '/private/config.php' );
foreach( [ '/legal/imprint', '/legal/privacy' ] as $reservedUri ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
		'originalHttpUri' => '', 'uri' => $reservedUri, 'httpUri' => '/mine', 'template' => 'page-about', 'text' => $pageText('Mine'),
	] );
	check( 'a page with the Element-URI '. $reservedUri. ' is refused with 409, a code and the uri', $status === 409 && ( $body['code'] ?? '' ) === 'routes_reserved_uri' && ( $body['params'] ?? [] ) === [ $reservedUri ] );
}
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSave', [
	'originalHttpUri' => '/contact', 'uri' => '/legal/imprint', 'httpUri' => '/contact', 'template' => 'page-contact', 'text' => $pageText('Kontakt'),
] );
check( '...and so is an existing page that is moved onto one, and nothing was written', $status === 409 && file_get_contents( $sandbox. '/private/config.php' ) === $configBeforeReserved );

// --- the pages a feature routes at runtime ---------------------------------
//
// Posts' /blog, the Newsletter's /.newsletter, Hello's /hello are in no
// config.php. Their name, title and description are keys of the system the
// Text Keys tab will not create, so this panel lists them apart and writes
// those three keys - and nothing else
$configBeforeRuntime = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
// The text of a post for every language the sandbox has: German and English as given, any other as the English
$runtimeLocales = \Nino\Locales::getAvailableLocales( $appData );
$runtimeText = static fn( array $de, array $en ): array => array_combine( $runtimeLocales, array_map( static fn( string $locale ): array => $locale === 'de_DE' ? $de : $en, $runtimeLocales ) );
$appData['/nino/http/routes']['GET://blog/*'] = [ 'uri' => '/blog/post', 'body' => '[template /templates/page-post]' ];
$appData['/nino/http/routes']['GET://.newsletter'] = [ 'uri' => '/.newsletter', 'body' => '[template /templates/page-newsletter]' ];
$appData['/nino/http/routes']['GET://.search'] = [ 'uri' => '/.search' ];
$appData['/nino/http/routes']['GET://hello'] = [ 'uri' => '/hello', 'body' => '[template /templates/page-hello]' ];
$appData['/nino/http/routes']['POST://.newsletter'] = [ 'uri' => '/.newsletter' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
$runtimeByUri = array_column( $body['runtime'] ?? [], null, 'uri' );
check( 'routes/list lists the pages features route under "runtime"', $status === 200 && array_keys( $runtimeByUri ) === [ '/blog/post', '/.newsletter', '/hello' ] );
check( '...with the path as the feature routes it, placeholder and all', array_column( $body['runtime'], 'httpUri', 'uri' ) === [ '/blog/post' => '/blog/*', '/.newsletter' => '/.newsletter', '/hello' => '/hello' ] );
check( '...a route with no page template (/.search) and a persisted one are not among them', isset( $runtimeByUri['/.search'] ) === false && in_array( '/contact', array_column( $body['runtime'], 'httpUri' ), true ) === false );
check( '...and the persisted pages still come as before, without the runtime ones', array_column( $body['pages'], 'httpUri' ) === [ '/contact' ] );
check( '...each with its name, title and description per language, empty while nobody wrote them', array_keys( $runtimeByUri['/hello']['text'] ) === $runtimeLocales && $runtimeByUri['/hello']['text']['de_DE'] === [ 'name' => '', 'title' => '', 'description' => '' ] );
check( '...every page with its paths: one for a page of its own', $runtimeByUri['/hello']['httpUris'] === [ '/hello' ] );

// A page with a route per language is one page: the imprint of Modules\Legal
$appData['/nino/http/routes']['GET://impressum'] = [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '[template /templates/page-legal-imprint]', 'maintenance' => false ];
$appData['/nino/http/routes']['GET://imprint'] = [ 'uri' => '/legal/imprint', 'locale' => 'en_US', 'body' => '[template /templates/page-legal-imprint]', 'maintenance' => false ];
[ , $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
$languageByUri = array_column( $body['runtime'], null, 'uri' );
check( 'the routes of one Element-URI are one page, with all their paths', array_keys( $languageByUri ) === [ '/blog/post', '/.newsletter', '/hello', '/legal/imprint' ]
	&& $languageByUri['/legal/imprint']['httpUri'] === '/impressum' && $languageByUri['/legal/imprint']['httpUris'] === [ '/impressum', '/imprint' ] );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/legal/imprint', 'text' => $runtimeText( [ 'name' => 'Impressum', 'title' => 'Impressum' ], [ 'name' => 'Imprint', 'title' => 'Imprint' ] ) ] );
check( '...whose details are written once, under the Element-URI they share', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/legal/imprint/name]]'] ?? null ) === 'Impressum' );
unset( $appData['/nino/http/routes']['GET://impressum'], $appData['/nino/http/routes']['GET://imprint'] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => ' Newsletter ', 'title' => 'Newsletter abonnieren', 'description' => 'Alle drei Monate.' ],
	[ 'name' => 'Newsletter', 'title' => 'Subscribe', 'description' => '' ]
) ] );
$deRuntime = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$enRuntime = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'routes/savetexts writes name, title and description per language, trimmed', $status === 200 && ( $deRuntime['[[/_nino/webpage/.newsletter/name]]'] ?? null ) === 'Newsletter' && ( $deRuntime['[[/_nino/webpage/.newsletter/title]]'] ?? null ) === 'Newsletter abonnieren'
	&& ( $deRuntime['[[/_nino/webpage/.newsletter/description]]'] ?? null ) === 'Alle drei Monate.' && ( $enRuntime['[[/_nino/webpage/.newsletter/title]]'] ?? null ) === 'Subscribe' && ( $enRuntime['[[/_nino/webpage/.newsletter/description]]'] ?? null ) === '' );
check( '...writes no uri key, so the feature\'s path is not copied and cannot go stale, and nothing goes on the blacklist', isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/.newsletter/uri]]'] ) === false
	&& array_filter( \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), static fn( string $key ): bool => str_starts_with( $key, '/_nino/webpage/.newsletter' ) ) === [] );
check( '...and no route into config.php', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] === $configBeforeRuntime['/nino/http/routes'] );
check( '...and answers with the list as it stands now', ( array_column( $body['runtime'], null, 'uri' )['/.newsletter']['text']['de_DE']['name'] ?? null ) === 'Newsletter' );
check( 'the key is the one the menu and the page header read, so it renders', \Nino\Html::renderTextfill( $appData, '/_nino/webpage/.newsletter/name' ) === 'Newsletter' );

// A directory where the language file's sidecar lock goes: lockFile() cannot open it, so the
// write is refused - what a read-only or full disk gives, without needing either
$runtimeLock = $sandbox. '/private/data/.locks/'. sha1( '/text/de_DE.php' ). '.lock';
unset( $appData['./nino/filesystem/locks'] );
@unlink( $runtimeLock );
@mkdir( $runtimeLock );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => 'Neu', 'title' => 'Neu', 'description' => '' ],
	[ 'name' => 'New', 'title' => 'New', 'description' => '' ]
) ] );
check( 'routes/savetexts that could not write the language file says so rather than reporting success', $status === 500 && str_contains( (string) ( $body['error'] ?? '' ), '/text/de_DE.php' ) === true && isset( $body['runtime'] ) === false );
@rmdir( $runtimeLock );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( '...and the text it had is still there', ( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/.newsletter/name]]'] ?? null ) === 'Newsletter' );

// The title and the description land in an attribute of the page head, through a blind str_replace
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => '<b>Newsletter</b>', 'title' => 'x" onmouseover="y', 'description' => '<script>z</script>Alle drei Monate.' ],
	[ 'name' => 'Newsletter', 'title' => 'Subscribe', 'description' => '' ]
) ] );
$deRuntime = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'routes/savetexts stores the words as plain text, as the Text panel does: no markup, no quote that closes an attribute', $status === 200 && $deRuntime['[[/_nino/webpage/.newsletter/name]]'] === 'Newsletter'
	&& $deRuntime['[[/_nino/webpage/.newsletter/title]]'] === 'x&quot; onmouseover=&quot;y' && str_contains( $deRuntime['[[/_nino/webpage/.newsletter/description]]'], '<' ) === false );
callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.newsletter', 'text' => $runtimeText(
	[ 'name' => 'Newsletter', 'title' => 'Newsletter abonnieren', 'description' => 'Alle drei Monate.' ],
	[ 'name' => 'Newsletter', 'title' => 'Subscribe', 'description' => '' ]
) ] );

[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/nowhere', 'text' => $runtimeText( [ 'name' => 'x', 'title' => 'y' ], [ 'name' => 'x', 'title' => 'y' ] ) ] );
check( 'routes/savetexts is a 404 for an Element-URI no feature route carries', $status === 404 && isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/nowhere/name]]'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/contact', 'text' => $runtimeText( [ 'name' => 'x', 'title' => 'y' ], [ 'name' => 'x', 'title' => 'y' ] ) ] );
check( '...and for the Element-URI of a persisted page, which is saved the way it always was', $status === 404 );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/.search', 'text' => $runtimeText( [ 'name' => 'x', 'title' => 'y' ], [ 'name' => 'x', 'title' => 'y' ] ) ] );
check( '...and for a route that is no page', $status === 404 );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/hello', 'text' => $runtimeText( [ 'name' => 'Hallo', 'title' => 'Hallo Welt' ], [ 'name' => 'Hello', 'title' => '' ] ) ] );
check( 'a title missing in one language is refused with the code and the field, and nothing is written', $status === 400 && ( $body['code'] ?? '' ) === 'routes_missing_title' && ( $body['params'] ?? [] ) === [ 'en_US' ] && ( $body['field'] ?? '' ) === 'title'
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/_nino/webpage/hello/name]]'] ) === false );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/hello', 'text' => $runtimeText( [ 'name' => '', 'title' => 'y' ], [ 'name' => 'Hello', 'title' => 'x' ] ) ] );
check( '...and so is a missing name', $status === 400 && ( $body['code'] ?? '' ) === 'routes_missing_name' );
check( 'the log has a line for it, with the Element-URI', \Nino\Modules\Routes\Admin::log( 'routes/savetexts', [ 'uri' => '/.newsletter' ] ) === 'Edit Feature Route /.newsletter' );
\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiSaveTexts', [ 'uri' => '/hello', 'text' => [] ] );
check( '...and routes/savetexts needs the panel\'s session and permission like every action of it', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );
foreach( [ 'name', 'title', 'description' ] as $runtimeField )
	foreach( [ '/text/de_DE.php', '/text/en_US.php' ] as $runtimeFile )
		\Nino\Filesystem::mutate( $appData, $runtimeFile, function( array $content ) use ( $runtimeField ): array {
			unset( $content['[[/_nino/webpage/.newsletter/'. $runtimeField. ']]'] );
			return $content;
		} );
unset( $appData['/nino/http/routes']['GET://blog/*'], $appData['/nino/http/routes']['GET://.newsletter'], $appData['/nino/http/routes']['GET://.search'], $appData['/nino/http/routes']['GET://hello'], $appData['/nino/http/routes']['POST://.newsletter'] );

array_pop( $appData['/nino/modules'] ); // drop the simulated Navigation module again

unlink( $sandbox. '/private/templates/page-about.tpl' );
unlink( $sandbox. '/private/templates/page-contact.tpl' );
unlink( $sandbox. '/private/templates/not-a-page.tpl' );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiList' );
check( 'PageEditor actions require an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

// A config.php that holds the routes as null - a hand edit - is a project without routes, as the readers took it
// before they read through AppData::stored(): the tile counts none and a move finds no page, where a TypeError is a 500
$routesStored = \Nino\AppData::stored( $appData, '/nino/http/routes' );
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $config ): array { $config['/nino/http/routes'] = null; return $config; } );
[ $nullStatus ] = callDev( $appData, \Nino\Modules\Routes\Admin::class, 'apiMove', [ 'httpUri' => '/about', 'direction' => 'up' ] );
check( 'a config.php that holds the routes as null is a project without routes: the dashboard tile counts none and a move finds no page', \Nino\Modules\Routes\Admin::count( $appData ) === 0 && $nullStatus === 404 );
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $config ) use ( $routesStored ): array { $config['/nino/http/routes'] = $routesStored; return $config; } );

echo "\n";


// --- Modules\Navigation\Admin - which menus exist, and what stands in them -------

echo "Modules\\Navigation\\Admin - menus and their running order\n";

// Same two config keys the Routes module writes, from the other side: this
// one opens one menu and sets its whole running order. Route bodies are
// deliberately mixed - a page route, and one no page editor manages
// (robots.txt) - to prove a menu is only ever "a path with a name"
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/html/navs'] = [ 'main', 'footer' ];
	$config['/nino/http/routes'] = [
		'GET://' 						=> [ 'uri' => '/home', 	'body' => '[template /templates/page-home]', 'navs' => [ 'main' => 1 ] ],
		'GET://robots.txt' 	=> [ 'uri' => '/robots.txt', 'body' => '[template /templates/robots]' ],
		'GET://contact' 		=> [ 'uri' => '/contact', 'body' => '[template /templates/page-contact]', 'navs' => [ 'main' => 2 ] ],
		'GET://legal' 			=> [ 'uri' => '/legal', 'body' => '[template /templates/page-legal]' ],
	];
	return $config;
} );
$appData['/nino/html/navs'] = [ 'main', 'footer' ];
// The live routes are the persisted ones here: nothing registers a route of its own yet
$appData['/nino/http/routes'] = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ),
	[ '[[/_nino/webpage/home/name]]' => 'Start', '[[/_nino/webpage/contact/name]]' => 'Kontakt', '[[/_nino/webpage/legal/name]]' => 'Impressum' ]
) );

$navKeys = fn( array $body ): array => array_column( $body['navs'], 'key' );
$entriesOf = fn( array $body, string $key ): array => array_column(
	$body['navs'][ array_search( $key, array_column( $body['navs'], 'key' ), true ) ]['entries'], 'httpUri'
);

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
check( 'apiList succeeds', $status === 200 );
check( 'lists every registered menu, in registry order', $navKeys( $body ) === [ 'main', 'footer' ] );
check( 'a menu reports its members in running order', $entriesOf( $body, 'main' ) === [ '/', '/contact' ] );
check( 'a registered menu nobody is in is still listed, empty', $entriesOf( $body, 'footer' ) === [] );
check( 'offers every GET route as a possible entry, not just the page ones', array_column( $body['routes'], 'httpUri' ) === [ '/', '/robots.txt', '/contact', '/legal' ] );
check( 'labels a route by the /_nino/webpage<uri>/name key the menu would render', $body['routes'][0]['label'] === 'Start' );
check( '...and falls back to the path for one nobody named', $body['routes'][1]['label'] === '/robots.txt' );
check( 'a route with no name is reported as such - routeLines() would skip it', $body['routes'][1]['named'] === false && $body['routes'][0]['named'] === true );
check( 'a route that is in config.php is not marked as a runtime one', array_column( $body['routes'], 'runtime' ) === [ false, false, false, false ] );

// A menu is saved as a whole: Save posts the complete running order, there is no
// action that adds, moves or removes one entry
check( 'the three per-entry actions are gone', array_keys( \Nino\Modules\Navigation\Admin::actions() ) === [ 'navs/list', 'navs/save', 'navs/delete' ] );

$configNow = static fn(): string => (string) file_get_contents( $sandbox. '/private/config.php' );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/legal', '/', '/contact' ] ] );
check( 'saving a menu with its entries succeeds', $status === 200 );
check( 'the entries are the menu\'s running order, in the order they were posted', $entriesOf( $body, 'main' ) === [ '/legal', '/', '/contact' ] );

$routesAfterOrder = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'membership is written onto the route itself, densely numbered 1..n', [
	$routesAfterOrder['GET://legal']['navs'], $routesAfterOrder['GET://']['navs'], $routesAfterOrder['GET://contact']['navs'],
] === [ [ 'main' => 1 ], [ 'main' => 2 ], [ 'main' => 3 ] ] );
check( 'the route array itself is not reordered by a menu save', array_keys( $routesAfterOrder ) === [ 'GET://', 'GET://robots.txt', 'GET://contact', 'GET://legal' ] );
check( 'no runtime membership key is written while there are none', array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );

// A member that is left out loses its membership - and a route left in no menu
// carries no "navs" at all rather than an empty one
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/contact', '/legal' ] ] );
check( 'a save with fewer entries succeeds', $status === 200 && $entriesOf( $body, 'main' ) === [ '/contact', '/legal' ] );

$routesAfterLeaveOut = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'the entries that stay are renumbered from 1', [ $routesAfterLeaveOut['GET://contact']['navs'], $routesAfterLeaveOut['GET://legal']['navs'] ] === [ [ 'main' => 1 ], [ 'main' => 2 ] ] );
check( 'a route that was left out carries no "navs" key rather than an empty one, and survives', isset( $routesAfterLeaveOut['GET://'] ) === true && isset( $routesAfterLeaveOut['GET://']['navs'] ) === false );

// Refusals write nothing
$configBeforeRefusals = $configNow();
foreach( [
	'a route listed twice' 					=> [ [ '/contact', '/legal', '/contact' ], 400, 'navs_duplicate_entry' ],
	'the same route in two spellings' => [ [ '/contact', 'contact/' ], 400, 'navs_duplicate_entry' ],
	'a route that does not exist' 	=> [ [ '/contact', '/does-not-exist' ], 404, 'navs_unknown_route' ],
	'a workbench route' 							=> [ [ '/_admin' ], 404, 'navs_unknown_route' ],
	'entries that are no list' 			=> [ 'contact', 400, 'navs_invalid_entries' ],
	'an entry that is no uri' 			=> [ [ '/contact', 7 ], 400, 'navs_invalid_entries' ],
] as $label => [ $refused, $refusedStatus, $refusedCode ] ) {
	[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => $refused ] );
	check( $label. ' is refused with '. $refusedStatus. ' and its code', $status === $refusedStatus && ( $body['code'] ?? '' ) === $refusedCode );
}
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'nope', 'key' => 'nope', 'entries' => [ '/contact' ] ] );
check( 'entries for an unknown menu 404 as well', $status === 404 );
check( '...and none of the refusals wrote anything', $configNow() === $configBeforeRefusals );

// Entries stay optional: a save without them only creates or renames
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main' ] );
check( 'a save without entries leaves the running order alone', $status === 200 && $entriesOf( $body, 'main' ) === [ '/contact', '/legal' ] );

// Put the menu back the way the checks below were written against
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/contact' ] ] );
$routesAfterRestore = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'a route that was left out comes back into the menu at the place it was given', [ $routesAfterRestore['GET://']['navs'], $routesAfterRestore['GET://contact']['navs'] ] === [ [ 'main' => 1 ], [ 'main' => 2 ] ] );
check( '...and the one that was dropped is in no menu, and still a route', isset( $routesAfterRestore['GET://legal']['navs'] ) === false && isset( $routesAfterRestore['GET://legal'] ) === true );

// Create
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'meta' ] );
check( 'creating a menu succeeds', $status === 200 );
check( 'it is registered, at the end', $navKeys( $body ) === [ 'main', 'footer', 'meta' ] );
check( '...and starts empty', $entriesOf( $body, 'meta' ) === [] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'meta' ] );
check( 'creating one that already exists 409s', $status === 409 );

foreach( [ 'Main Menu', 'main/sub', '', '2fast', 'MAIN' ] as $badKey ) {
	[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => $badKey ] );
	check( 'rejects "'. $badKey. '" as a menu id', $status === 400 );
}

// Rename: follows the key into the registry and onto every member route
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'primary' ] );
check( 'renaming succeeds', $status === 200 );
check( 'the renamed menu keeps its place in the registry', $navKeys( $body ) === [ 'primary', 'footer', 'meta' ] );
check( '...and keeps its members, in order', $entriesOf( $body, 'primary' ) === [ '/', '/contact' ] );

$routesAfterRename = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'every member route carries the new key, at the priority it had', [ $routesAfterRename['GET://']['navs'], $routesAfterRename['GET://contact']['navs'] ] === [ [ 'primary' => 1 ], [ 'primary' => 2 ] ] );
check( 'the old key is gone from the routes', isset( $routesAfterRename['GET://']['navs']['main'] ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'primary', 'key' => 'footer' ] );
check( 'renaming onto a registered id 409s', $status === 409 );

// ...and onto one only a route carries: without this the rename would merge
// two memberships into one and silently drop a priority
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://']['navs']['handwritten'] = 4;
	return $config;
} );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'primary', 'key' => 'handwritten' ] );
check( 'renaming onto an id only a hand-written route carries 409s too', $status === 409 );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'nope', 'key' => 'whatever' ] );
check( 'renaming an unknown menu 404s', $status === 404 );

// Rename and entries in one call, one lock, one write
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'meta', 'key' => 'metax', 'entries' => [ '/legal' ] ] );
check( 'a rename and its entries go through in one save', $status === 200 && $navKeys( $body ) === [ 'primary', 'footer', 'metax' ] && $entriesOf( $body, 'metax' ) === [ '/legal' ] );
check( '...and the route carries the new key only', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://legal']['navs'] === [ 'metax' => 1 ] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'metax', 'key' => 'meta', 'entries' => [] ] );
check( 'an empty list empties the menu, and the route it held carries no "navs" afterwards', $status === 200 && $entriesOf( $body, 'meta' ) === []
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://legal']['navs'] ) === false );

// Delete: out of the registry, and off every route
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting succeeds', $status === 200 );
check( 'it is out of the registry', $navKeys( $body ) === [ 'footer', 'meta' ] );

$routesAfterDelete = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'every member route lost the membership too', isset( $routesAfterDelete['GET://contact']['navs'] ) === false );
check( 'a route that was in a second menu keeps that one', $routesAfterDelete['GET://']['navs'] === [ 'handwritten' => 4 ] );
check( 'the routes themselves survive', array_keys( $routesAfterDelete ) === [ 'GET://', 'GET://robots.txt', 'GET://contact', 'GET://legal' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting an unknown menu 404s', $status === 404 );

// Deleting the last one really does leave none.
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'footer' ] );
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'meta' ] );
check( 'the last menu can be deleted, and stays deleted', $navKeys( $body ) === [] );

/*	The registry a save or a delete starts from is the one config.php holds
	under the lock, not the copy this request loaded when it began: another
	request may have changed the file since, and writing the old copy back
	would lose its menus (or give back one it deleted). The copy is stale
	here on purpose - it knows 'main' and a 'ghost' that is gone, the file
	knows 'footer' and 'side'.	*/
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/html/navs'] = [ 'main', 'footer', 'side' ];
	return $config;
} );
$registryInFile = static fn(): array => \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navs'];

$appData['/nino/html/navs'] = [ 'main', 'ghost' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'top' ] );
check( 'a create keeps the menus that are only in config.php', $status === 200 && $registryInFile() === [ 'main', 'footer', 'side', 'top' ] && $navKeys( $body ) === [ 'main', 'footer', 'side', 'top' ] );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'side' ] );
check( '...and an id that only config.php knows is taken', $status === 409 );

$appData['/nino/html/navs'] = [ 'main', 'ghost' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'side', 'key' => 'flank' ] );
check( 'a rename finds a menu that is only in config.php, and keeps its place', $status === 200 && $registryInFile() === [ 'main', 'footer', 'flank', 'top' ] );

$appData['/nino/html/navs'] = [ 'main', 'ghost' ];
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'footer' ] );
check( 'a delete removes one menu and keeps the others of config.php', $status === 200 && $registryInFile() === [ 'main', 'flank', 'top' ] && $navKeys( $body ) === [ 'main', 'flank', 'top' ] );

\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/html/navs'] = [];
	return $config;
} );

unset( $appData['/nino/html/navs'] );
check( 'a missing navigation registry exposes no implicit menu', \Nino\Modules\Navigation\Admin::registry( $appData ) === [] );
$appData['/nino/html/navs'] = [];
check( 'an empty navigation registry stays empty', \Nino\Modules\Navigation\Admin::registry( $appData ) === [] );

/*	What "named" has to mean: the menu renders the name through the fill
	engine, and that merges the locale-independent global.php under the file
	of the locale the visitor is on. A name written once in global.php, and a
	name written only in a language that is not the native one, are both names
	the menu puts on the page - and the panel read the native locale's file
	alone, so it called them missing and refused to offer the route.	*/
\Nino\Filesystem::mutate( $appData, '/config.php', function( array $config ): array {
	$config['/nino/http/routes']['GET://shared']	= [ 'uri' => '/shared', 'body' => '[template /templates/page-shared]' ];
	$config['/nino/http/routes']['GET://jobs'] 		= [ 'uri' => '/jobs', 'body' => '[template /templates/page-jobs]' ];
	$config['/nino/http/routes']['GET://press'] 	= [ 'uri' => '/press', 'body' => '[template /templates/page-press]' ];
	return $config;
} );
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] ),
	[ '[[/_nino/webpage/shared/name]]' => 'Shared', '[[/_nino/webpage/press/name]]' => 'Press' ]
) );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] ),
	[ '[[/_nino/webpage/jobs/name]]' => 'Jobs' ]
) );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', array_merge(
	\Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ),
	[ '[[/_nino/webpage/press/name]]' => 'Presse' ]
) );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
$byUri = array_column( $body['routes'], null, 'httpUri' );

check( 'apiList succeeds after the three unnamed-looking routes joined', $status === 200 );
check( 'a name written once in global.php for every language is a name', ( $byUri['/shared']['named'] ?? null ) === true
	&& ( $byUri['/shared']['label'] ?? null ) === 'Shared' );
check( 'a name only a non-native locale carries is a name too - that menu renders it', ( $byUri['/jobs']['named'] ?? null ) === true
	&& ( $byUri['/jobs']['label'] ?? null ) === 'Jobs' );
check( 'the locale file still beats global.php for the label, as the fill engine does', ( $byUri['/press']['label'] ?? null ) === 'Presse' );
check( '...and a route no language named at all is still reported unnamed', ( $byUri['/robots.txt']['named'] ?? null ) === false
	&& ( $byUri['/robots.txt']['label'] ?? null ) === '/robots.txt' );

/*	Routes that exist only at runtime. A feature registers its routes in its
	init(), so /blog is in the live route array and nowhere in config.php - it
	has no route of its own to carry a "navs". The panel offers it all the same,
	and what it joins lives in '/nino/html/navroutes', which the shortcode reads
	for a live route (see http-smoke.php). Config.php never gets a stub route:	*/
$persistedRoutes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
$runtimeRoutes = [
	'GET://blog' 								=> [ 'uri' => '/blog', 'body' => '' ],
	'GET://blog/*' 							=> [ 'uri' => '/blog/*', 'body' => '' ],
	'GET://.search' 						=> [ 'uri' => '/.search', 'body' => '' ],
	'GET://_admin' 							=> [ 'uri' => '/_admin', 'body' => '' ],
	'GET://_admin/recovery.php' => [ 'uri' => '/_admin/recovery.php', 'body' => '' ],
	'POST://.newsletter' 				=> [ 'uri' => '/.newsletter', 'body' => '' ],
];
$appData['/nino/http/routes'] = $persistedRoutes + $runtimeRoutes;
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'main' ] );
check( 'a menu for the runtime routes to join is created', $status === 200 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
$offered = array_column( $body['routes'], null, 'httpUri' );
check( 'a route registered only at runtime is offered, marked as one', ( $offered['/blog']['runtime'] ?? null ) === true && ( $offered['/blog']['named'] ?? null ) === false );
check( '...a technical route that has no entry in config.php too: the menu\'s owner decides what a visitor should see', isset( $offered['/.search'] ) === true );
check( '...but no wildcard route, no route of the workbench - the recovery page included - and no POST endpoint', isset( $offered['/blog/*'], $offered['/_admin'], $offered['/_admin/recovery.php'], $offered['/.newsletter'] ) === false );
check( '...while the persisted technical route stays offered, and is not marked as a runtime one', ( $offered['/robots.txt']['runtime'] ?? null ) === false );

$configBeforeRuntime = $configNow();
[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/blog', '/contact' ] ] );
check( 'a runtime route can be put into the running order', $status === 200 && $entriesOf( $body, 'main' ) === [ '/', '/blog', '/contact' ] );

$configAfterRuntime = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'its membership is written under /nino/html/navroutes, by the page\'s Element-URI, at its position in the order', ( $configAfterRuntime['/nino/html/navroutes'] ?? null ) === [ '/blog' => [ 'main' => 2 ] ] );
check( '...the routes that are in config.php take the numbers around it', [ $configAfterRuntime['/nino/http/routes']['GET://']['navs']['main'], $configAfterRuntime['/nino/http/routes']['GET://contact']['navs']['main'] ] === [ 1, 3 ] );
check( '...and no stub route for it is written into config.php - nor any other runtime route', array_keys( $configAfterRuntime['/nino/http/routes'] ) === array_keys( $persistedRoutes ) );
check( 'the live route array still holds the runtime routes after the save', isset( $appData['/nino/http/routes']['GET://blog'], $appData['/nino/http/routes']['GET://_admin/recovery.php'] ) === true
	&& array_keys( array_diff_key( $appData['/nino/http/routes'], $persistedRoutes ) ) === array_keys( $runtimeRoutes ) );

// The shortcode's side of it, from the same config: the menu renders the
// runtime route in its place once it is named
$appData['/nino/html/navroutes'] = $configAfterRuntime['/nino/html/navroutes'];
\Nino\Html::addFills( $appData, [ '/_nino/webpage/blog/name' => 'Blog', '/_nino/webpage/home/name' => 'Start', '/_nino/webpage/contact/name' => 'Kontakt' ], 'de_DE' );
\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
check( 'the Navigation shortcode draws the runtime route between the two pages', \Nino\Modules\Navigation::routeLines( $appData, 'main' ) === [ '/:Start', '/blog:Blog', '/contact:Kontakt' ] );

// A runtime route that is gone - its feature switched off - is not offered, is
// not rendered, and drops out with the next save of that menu
$appData['/nino/http/routes'] = $persistedRoutes;
[ , $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
check( 'a runtime route that is not live any more is neither offered nor listed in its menu', isset( array_column( $body['routes'], null, 'httpUri' )['/blog'] ) === false && $entriesOf( $body, 'main' ) === [ '/', '/contact' ] );
unset( $appData['/nino/html/navroutes'] );
check( '...nor rendered', in_array( '/blog:Blog', \Nino\Modules\Navigation::routeLines( $appData, 'main' ), true ) === false );

[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/blog' ] ] );
check( 'it cannot be saved into a menu while it is gone', $status === 404 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/', '/contact' ] ] );
check( 'the next save of that menu drops what was stored for it', $status === 200 && array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );

// Rename and delete follow a runtime membership too
$appData['/nino/http/routes'] = $persistedRoutes + $runtimeRoutes;
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'main', 'entries' => [ '/blog', '/', '/.search' ] ] );
check( 'two runtime routes in one menu, one of them first', ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes'] ?? null ) === [ '/blog' => [ 'main' => 1 ], '/.search' => [ 'main' => 3 ] ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'main', 'key' => 'primary' ] );
check( 'a rename follows the key onto a runtime membership', $status === 200 && $entriesOf( $body, 'primary' ) === [ '/blog', '/', '/.search' ]
	&& ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes']['/blog'] ?? null ) === [ 'primary' => 1 ] );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'primary' ] );
check( 'a menu id a runtime membership carries is taken', $status === 409 );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'primary' ] );
check( 'deleting a menu takes its runtime memberships away too, and the key with them when none is left', $status === 200
	&& array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false );
check( '...and the runtime routes are still live afterwards', isset( $appData['/nino/http/routes']['GET://blog'] ) === true );
$appData['/nino/http/routes'] = $persistedRoutes;
unset( $appData['/nino/html/navroutes'] );

/*	A page with a route per language - the imprint of Modules\Legal - is one
	entry: its routes share an Element-URI, the menu keeps the page by that
	uri, and a path of any of its routes names it. Nothing is written for it
	into config.php's routes	*/
$languageRoutes = [
	'GET://imprint' 		=> [ 'uri' => '/legal/imprint', 'locale' => 'en_US', 'body' => '', 'maintenance' => false ],
	'GET://impressum' 	=> [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '', 'maintenance' => false ],
	'GET://shared-page' => [ 'uri' => '/legal/shared', 'body' => '' ],
];
\Nino\Filesystem::mutate( $appData, '/text/de_DE.php', function( array $content ): array { $content['[[/_nino/webpage/legal/imprint/name]]'] = 'Impressum'; return $content; } );
\Nino\Filesystem::mutate( $appData, '/text/en_US.php', function( array $content ): array { $content['[[/_nino/webpage/legal/imprint/name]]'] = 'Imprint'; return $content; } );
$appData['/nino/http/routes'] = $persistedRoutes + $languageRoutes;
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'legal' ] );
[ , $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
$languageEntries = array_values( array_filter( $body['routes'], static fn( array $route ): bool => $route['uri'] === '/legal/imprint' ) );
check( 'the routes of one Element-URI are one entry, named in the native language, with every path', count( $languageEntries ) === 1
	&& $languageEntries[0]['label'] === 'Impressum' && $languageEntries[0]['runtime'] === true && $languageEntries[0]['named'] === true );
check( '...the path of the native language names it, all of them are listed', $languageEntries[0]['httpUri'] === '/impressum' && $languageEntries[0]['paths'] === [ '/impressum', '/imprint' ] );
check( 'a route on its own has its one path', array_column( $body['routes'], 'paths', 'httpUri' )['/contact'] === [ '/contact' ] );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'legal', 'key' => 'legal', 'entries' => [ '/contact', '/imprint' ] ] );
check( 'any path of the page puts it into the running order, once', $status === 200 && $entriesOf( $body, 'legal' ) === [ '/contact', '/impressum' ] );
$configAfterLanguage = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( '...under its Element-URI in /nino/html/navroutes, and nowhere as a route of config.php', ( $configAfterLanguage['/nino/html/navroutes'] ?? null ) === [ '/legal/imprint' => [ 'legal' => 2 ] ]
	&& isset( $configAfterLanguage['/nino/http/routes']['GET://imprint'] ) === false && isset( $configAfterLanguage['/nino/http/routes']['GET://impressum'] ) === false );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'legal', 'key' => 'legal', 'entries' => [ '/imprint', '/impressum' ] ] );
check( 'two paths of one page are listed twice', $status === 400 );
check( 'the menu reads it the way the shortcode does, in each language', ( function() use ( &$appData ): bool {
	\Nino\Locales::setCurrentLocale( $appData, 'en_US' );
	$english = \Nino\Modules\Navigation::routeLines( $appData, 'legal' );
	\Nino\Locales::setCurrentLocale( $appData, 'de_DE' );
	return in_array( '/imprint:Imprint', $english, true ) === true && in_array( '/impressum:Impressum', \Nino\Modules\Navigation::routeLines( $appData, 'legal' ), true ) === true;
} )() );

[ $status, $body ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => 'legal', 'key' => 'rechtliches' ] );
check( 'a rename follows the page\'s Element-URI', $status === 200 && ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/navroutes']['/legal/imprint'] ?? null ) === [ 'rechtliches' => 2 ] );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'originalKey' => '', 'key' => 'rechtliches' ] );
check( 'a menu id a page is in is taken', $status === 409 );
callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiDelete', [ 'key' => 'rechtliches' ] );
check( 'deleting the menu takes the page out of it', array_key_exists( '/nino/html/navroutes', \Nino\Filesystem::getFileContent( $appData, '/config.php', [] ) ) === false
	&& isset( $appData['/nino/http/routes']['GET://imprint'] ) === true );
$appData['/nino/http/routes'] = $persistedRoutes;
unset( $appData['/nino/html/navroutes'] );

\Nino\Auth::logoutUser( $appData );
[ $status ] = callDev( $appData, \Nino\Modules\Navigation\Admin::class, 'apiList' );
check( 'Navigations actions require an authed _admin session too', $status === 401 );
\Nino\Auth::loginUser( $appData, 'dev@example.com', 'correct horse battery staple' );

echo "\n";

ninoDone( $appData );
