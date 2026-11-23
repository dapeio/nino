<?php
declare(strict_types=1);

/**
 *	Nino
 *	features-menus-smoke.php	Contract test for the key 'menus' of an install unit: a feature that routes a page at run time - registered in init(), with no
 *											route in config.php - offers it to a menu of the project once, when its unit is applied for the first time, and the
 *											record of the feature is what remembers it. The features of the run are written here: one with a page at run time that
 *											offers it to the main menu (and one that offers two pages, the second of them to the footer), a second after it, one
 *											that names a menu the project does not have, one that names what is no usable menu or page, a plain one, and one that
 *											is the start of a site. A project is a config.php, its text files and a menu in a page of the site that draws it; every
 *											page is a request through \Nino\request().
 *
 *												A. the first activation: the page is the last of the menu, the place after the greatest one that anything holds, and its link answers
 *												B. where nothing is offered: no such menu, no Navigation module, a page that is a member already, a unit that was left out as the start of a site,
 *												   an unusable declaration - and nothing is made, nothing is an error
 *												C. once: switched off and on, applied again, updated, a record from before the key - the page the project took out stays out
 *												D. off: nothing is drawn, the address answers 404 and the panel of the menus does not list it; on again it is where it was
 *												E. removed: the memberships it added go, nothing is left in the file, the address is free for a page of the project, and put in again it offers them again
 *												F. who may: an account that cannot change menus offers none and is told, the next activation by one that can offers them
 *												G. what the Features panel says, and what the Navigations panel does with the entry
 *
 *	Usage: php tests/features-menus-smoke.php
 */

$work = sys_get_temp_dir(). '/nino-menus-'. bin2hex( random_bytes( 6 ) );
define( 'NINO_FEATURES_DIR', $work. '/features' );

/**
 *	A feature of the run: a class that registers its pages at run time, a unit with the words that name them and the menus it offers
 *
 *	@param		string		$name					The directory and the class, eg. 'Probe'
 *	@param		string		$menus				The php of the value of 'menus' in the unit's manifest, '' for none
 *	@param		array 		$pages				Path => Element-URI of the pages the class routes
 *	@param		string		$extra				More keys of the manifest
 *	@param		string		$version
 *	@param		array 		$requires			The keys of the features it requires
 *	@param		bool			$named				Whether the unit names its pages
 *
 *	@return 	void
 */
$feature = static function( string $name, string $menus, array $pages, string $extra = '', string $version = '1.0.0', array $requires = [], bool $named = true ): void {

	$dir = NINO_FEATURES_DIR. '/'. $name;
	@mkdir( $dir. '/install/text', 0755, true );

	$routes = '';
	$words = '';
	foreach( $pages as $path => $uri ) {
		$routes .= "\$appData['/nino/http/routes']['GET://". $path. "'] = [ 'uri' => '". $uri. "', 'body' => '<p>". $name. " ". $path. "</p>' ];\n";
		$words .= $named === true ? "'[[/_nino/webpage". $uri. "/name]]' => '". ucfirst( ltrim( $uri, '/' ) ). "', " : '';
	}

	file_put_contents( $dir. '/feature.php', "<?php return [ 'key' => '". strtolower( $name ). "', 'name' => [ 'en_US' => '". $name. "', 'de_DE' => '". $name. " (de)' ], 'version' => '". $version. "', 'requires' => [ ". implode( ', ', array_map( static fn( string $required ): string => "'". $required. "'", $requires ) ). " ] ];" );
	file_put_contents( $dir. '/'. $name. '.php', "<?php\nnamespace Nino\\Modules { class ". $name. " { public static function init( array &\$appData ): void {\n". $routes. "} } }\n" );
	file_put_contents( $dir. '/install/manifest.php', "<?php return [\n". $extra. ( $menus === '' ? '' : "\t'menus' => ". $menus. ",\n" ). "];" );
	file_put_contents( $dir. '/install/text/global.php', "<?php return [ ". $words. "];" );
};

$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$feature( 'Twin', "[ 'main' => [ '/twin', '/twin-two' ], 'footer' => [ '/twin-two' ] ]", [ 'twin' => '/twin', 'twin-two' => '/twin-two' ] );
$feature( 'Second', "[ 'main' => [ '/second' ] ]", [ 'second' => '/second' ] );
$feature( 'Lonely', "[ 'sidebar' => [ '/lonely' ] ]", [ 'lonely' => '/lonely' ] );
$feature( 'Plain', '', [ 'plain' => '/plain' ] );
$feature( 'Bad', "[ 'Bad Key' => [ '/bad-one' ], 'main' => [ 'noslash', \"/nul\\0byte\", '//host', 5, '/bad-two', '/bad-two', '/' . str_repeat( 'x', 300 ) ], 'footer' => 'nope', 7 => [ '/bad-three' ] ]", [ 'bad-two' => '/bad-two' ] );
$feature( 'Start', "[ 'main' => [ '/start' ] ]", [ 'start' => '/start' ], "\t'whenNoPages' => true,\n\t'routes' => [ 'GET://home' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]', 'navs' => [ 'main' => 9 ] ] ],\n" );
$feature( 'Grab', "[ 'main' => [ '/about', '/contact', '/free' ] ]", [ 'free' => '/free' ] );
$feature( 'Many', "[ 'main' => [ '/m1', '/m2', '/m3', '/m4', '/m5', '/m6' ], 'footer' => [ '/m7', '/m8', '/m9', '/m10' ] ]", [ 'm1' => '/m1' ] );
$feature( 'Dup', "[ 'main' => [ '/dup', '/dup' ] ]", [ 'dup' => '/dup' ] );
$feature( 'Nameless', "[ 'main' => [ '/nameless' ] ]", [ 'nameless' => '/nameless' ], '', '1.0.0', [], false );
$feature( 'Child', '', [ 'child' => '/child' ], '', '1.0.0', [ 'probe' ] );
$feature( 'Alt', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$feature( 'Gone', "[ 'main' => [ '/gone' ] ]", [ 'gone' => '/gone' ] );

require __DIR__. '/harness.php';

register_shutdown_function( static fn() => \Nino\Filesystem::removeDir( $work ) );

$ignored = ninoWarnings();

/**
 *	A project: Navigation and Template on, the menus it has, two pages of its own in the main menu - About and Contact, places 1 and 2 - and a page that draws the menu
 *
 *	@param		array 		$extra				Keys of config.php that replace or add to what the project has
 *
 *	@return 	array										$appData of a session
 */
$project = static function( array $extra = [] ): array {

	$app = ninoSandbox( 'menus' );
	$dir = ninoSandboxDir( $app );
	register_shutdown_function( static fn() => \Nino\Filesystem::removeDir( $dir ) );

	foreach( [ '/private/templates', '/private/text', '/public/images' ] as $sub )
		mkdir( $dir. $sub, 0777, true );

	$words = [ '[[/_nino/webpage/about/name]]' => 'About', '[[/_nino/webpage/contact/name]]' => 'Contact', '[[/_nino/webpage/legal/name]]' => 'Legal' ];
	\Nino\Filesystem::putFileContent( $app, '/text/global.php', $words );
	file_put_contents( $dir. '/private/templates/page-home.tpl', '<p>home</p>' );

	\Nino\Filesystem::putFileContent( $app, '/config.php', $extra + [
		'/nino/locales/native'		=> 'de_DE',
		'/nino/locales/available'	=> [ 'de_DE', 'en_US' ],
		'/nino/modules'						=> [ '\\Nino\\Modules\\Navigation', '\\Nino\\Modules\\Template' ],
		'/nino/html/navs'					=> [ 'main', 'footer' ],
		'/nino/http/routes'				=> [
			'GET://menu'			=> [ 'uri' => '/menu', 'body' => '[navigation nav="main"]|[navigation nav="footer"]' ],
			'GET://about'			=> [ 'uri' => '/about', 'body' => 'about', 'navs' => [ 'main' => 1 ] ],
			'GET://contact'		=> [ 'uri' => '/contact', 'body' => 'contact', 'navs' => [ 'main' => 2 ] ],
		],
	] );

	$app['/nino/modules']		= (array) \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/modules'];
	$app['/nino/html/navs']	= (array) \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/html/navs'];

	return $app;
};

/**
 *	The project as the next request boots it
 */
$boot = static function( array &$app ): array {

	$dir = ninoSandboxDir( $app );
	$booted = [ './nino/uid' => $dir ];
	\Nino\AppData::prepare( $booted );

	foreach( [ 'path' => $dir, 'configpath' => $dir. '/private', 'contentpath' => $dir. '/private', 'publicpath' => $dir. '/public' ] as $part => $path )
		$booted['./nino/filesystem/'. $part] = $path;

	\Nino\Filesystem::init( $booted );
	\Nino\AppData::init( $booted );
	$booted['/nino/dir'] = '';
	\Nino\Locales::init( $booted );
	\Nino\Csrf::init( $booted );
	\Nino\Html::init( $booted );
	\Nino\Auth::init( $booted );
	\Nino\Features::registerComponents( $booted );
	\Nino\Modules::callModules( $booted, 'init' );
	\Nino\Features::registerParts( $booted );
	$booted['./nino/locales/current'] = 'de_DE';

	return $booted;
};

/**
 *	One request of it
 */
$get = static function( array &$app, string $uri ) use ( $boot ): array {

	$booted = $boot( $app );
	$request = \Nino\request( $booted, [ 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri, 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com', 'HTTPS' => 'on' ] );

	return [ (int) $request['/nino/http/response']['statusCode'], (string) ( $request['/nino/http/response']['body'] ?? '' ) ];
};

/**
 *	One action of the workbench, as a request that has booted gets it: signed in as the account of the project that holds this mail
 */
$act = static function( array &$app, string $mail, string $class, string $method, array $data = [] ) use ( $boot ): array {

	$booted = $boot( $app );
	$booted['/nino/admin/logs'] = true;
	\Nino\Auth::loginUser( $booted, $mail, 'correct horse battery staple' );

	return ninoCall( $booted, [ $class, $method ], $data );
};

/**
 *	What the menus of the project draw, as the page that draws them says it: the main menu, then the footer, each as the list of its links' paths
 */
$menus = static function( array &$app ) use ( $get ): array {

	[ , $body ] = $get( $app, '/menu' );
	$drawn = [];

	foreach( explode( '|', $body ) as $part ) {
		preg_match_all( '~<a href="([^"]*)"~', $part, $found );
		$drawn[] = $found[1];
	}

	return $drawn;
};

$config = static fn( array &$app ): array => \Nino\Filesystem::getFileContent( $app, '/config.php', [] );
$member = static fn( array &$app, string $uri, string $nav = 'main' ): ?int => $config( $app )['/nino/html/navroutes'][$uri][$nav] ?? null;

$project();

echo "A. The first activation\n";

$app = $project();
$report = null;
$before = $config( $app )['/nino/http/routes'];
$result = \Nino\Features::activate( $app, 'probe', [], $report );
$after = $config( $app );

check( 'a page the feature routes at run time is put at the end of the menu: the place after the last one, as a membership under its Element-URI, and nothing but that is written to the menu',
	$result === true && ( $after['/nino/html/navroutes'] ?? [] ) === [ '/probe' => [ 'main' => 3 ] ] && $after['/nino/http/routes'] === $before && $after['/nino/html/navs'] === [ 'main', 'footer' ] );
check( '...the menu draws it after the pages of the project, and its link answers', $menus( $app )[0] === [ '/about', '/contact', '/probe' ] && $get( $app, '/probe' )[0] === 200 );
check( '...the report of the unit counts what it offered, and says which', ( $report['menus'] ?? null ) === [ 'added' => 1, 'present' => 0, 'in' => [ 'main' => [ '/probe' ] ], 'denied' => false, 'refused' => [] ]
	&& $report['routes'] === [ 'added' => 0, 'present' => 0 ] );
check( '...and the record of the feature keeps what it added, beside its version and settings',
	( $after['/nino/features']['probe'] ?? null ) === [ 'version' => '1.0.0', 'settings' => [], 'menus' => [ 'offered' => [ '/probe' ], 'parked' => [] ] ] );
check( '...and the request goes on with what was written: its own copy of the memberships has the page', ( $app['/nino/html/navroutes']['/probe']['main'] ?? null ) === 3 );

// The place is the greatest one anything holds, plus one: a stored route, a membership of a page that is not there, in the order the features are switched on
$app = $project( [ '/nino/html/navroutes' => [ '/elsewhere' => [ 'main' => 41, 'footer' => 5 ] ] ] );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/http/routes']['GET://contact']['navs']['main'] = 99;
	return $content;
} );
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::activate( $app, 'twin' );
\Nino\Features::activate( $app, 'second' );
$navroutes = $config( $app )['/nino/html/navroutes'];

check( 'the place is the greatest that a stored route or a membership holds in that menu, plus one - the same menu only - and the next feature comes after the one before it; two pages of one feature in the order they are named, in two menus each at its own end',
	$navroutes['/probe']['main'] === 100 && $navroutes['/twin']['main'] === 101 && $navroutes['/twin-two']['main'] === 102 && $navroutes['/twin-two']['footer'] === 6 && $navroutes['/second']['main'] === 103
	&& $navroutes['/elsewhere'] === [ 'main' => 41, 'footer' => 5 ] );
check( '...and the menus draw them in that order, the footer with its page', $menus( $app ) === [ [ '/about', '/contact', '/probe', '/twin', '/twin-two', '/second' ], [ '/twin-two' ] ] );

echo "\nB. Where nothing is offered\n";

$app = $project();
ninoUser( $app, 'dev@example.com' );
$report = null;
$result = \Nino\Features::activate( $app, 'lonely', [], $report );
$after = $config( $app );

check( 'a menu the project does not have is not made: the feature is on, no menu, no membership, no error - and the record says that it was asked',
	$result === true && in_array( 'sidebar', $after['/nino/html/navs'], true ) === false && isset( $after['/nino/html/navroutes'] ) === false && ( $report['menus']['added'] ?? -1 ) === 0
	&& ( $after['/nino/features']['lonely']['menus'] ?? null ) === [ 'offered' => [], 'parked' => [] ] && $get( $app, '/lonely' )[0] === 200 );

// A menu the project makes later has no page of the feature in it: it was asked at the first application, and the menu was not there
[ $status ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'key' => 'sidebar', 'originalKey' => '' ] );
$report = null;
\Nino\Features::deactivate( $app, 'lonely' );
\Nino\Features::activate( $app, 'lonely', [], $report );

check( 'a menu the project makes after the first application does not get the page: it is the project\'s, and switching the feature off and on does not change that',
	$status === 200 && in_array( 'sidebar', $config( $app )['/nino/html/navs'], true ) === true && isset( $config( $app )['/nino/html/navroutes'] ) === false );

// The project that has no menu of its own is the project with the two the kernel knows: main and footer, where config.php has no list
$app = $project();
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	unset( $content['/nino/html/navs'] );
	return $content;
} );
\Nino\Features::activate( $app, 'probe' );

check( 'a config.php without a list of menus has main and footer: the page is put in main', $member( $app, '/probe' ) === 3 );

$app = $project( [ '/nino/modules' => [ '\\Nino\\Modules\\Template' ] ] );
$report = null;
$result = \Nino\Features::activate( $app, 'probe', [], $report );

check( 'without the Navigation module there is no menu: nothing is written, the feature is on, no error',
	$result === true && isset( $config( $app )['/nino/html/navroutes'] ) === false && ( $report['menus']['added'] ?? -1 ) === 0 && $get( $app, '/probe' )[0] === 200 );

// A page that is a member already: by its own membership, or by a stored route that has the same Element-URI
$app = $project( [ '/nino/html/navroutes' => [ '/probe' => [ 'main' => 7 ] ] ] );
$report = null;
\Nino\Features::activate( $app, 'probe', [], $report );

check( 'a page that is a member of the menu already stays where it stands: the place is the project\'s and the report counts it as there',
	$member( $app, '/probe' ) === 7 && ( $report['menus']['added'] ?? -1 ) === 0 && ( $report['menus']['present'] ?? -1 ) === 1 );

$app = $project();
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/http/routes']['GET://own-probe'] = [ 'uri' => '/probe', 'body' => 'mine', 'navs' => [ 'main' => 5 ] ];
	return $content;
} );
\Nino\Features::activate( $app, 'probe' );

check( 'a page that is the Element-URI of a stored route of the project is the project\'s: it is not offered, whatever its route says of the menus',
	isset( $config( $app )['/nino/html/navroutes'] ) === false && $config( $app )['/nino/http/routes']['GET://own-probe']['navs'] === [ 'main' => 5 ] );

// A unit that was left out as the start of a site offers nothing
$app = $project();
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/http/routes']['GET://page'] = [ 'uri' => '/page', 'body' => '[template /templates/page-home]' ];
	return $content;
} );
$report = null;
\Nino\Features::activate( $app, 'start', [], $report );

check( 'the unit of a start of a site that the project does not need is left out whole, and so are its menus: no membership, no record of them',
	( $report['skipped'] ?? '' ) === 'pages' && isset( $config( $app )['/nino/html/navroutes'] ) === false && isset( $config( $app )['/nino/features']['start']['menus'] ) === false && isset( $report['menus'] ) === false );

$app = $project();
$report = null;
\Nino\Features::activate( $app, 'start', [], $report );

check( '...and one that is applied, because the project has no page, offers them, after the pages its own routes put into the menu', ( $report['skipped'] ?? '' ) === '' && $member( $app, '/start' ) === 10 );

// What is not usable is left out with a warning, the rest stays
$app = $project();
$ignored = ninoWarnings();
$result = \Nino\Features::activate( $app, 'bad' );
$warnings = ninoWarnings();

check( 'a menu that is no slug or no list of pages - and a key that is no name - and a page that is no Element-URI - no slash, a control byte, a second slash, no string, too long - are left out, each with a warning; a page named twice and a good page are put in, once',
	$result === true && count( $warnings ) === 8 && $config( $app )['/nino/html/navroutes'] === [ '/bad-two' => [ 'main' => 3 ] ]
	&& $config( $app )['/nino/features']['bad']['menus'] === [ 'offered' => [ '/bad-two' ], 'parked' => [] ] );

$app = $project();
$result = \Nino\Features::activate( $app, 'plain' );

check( 'a unit without menus changes no menu and its record has no menus', $result === true && isset( $config( $app )['/nino/html/navroutes'] ) === false && array_key_exists( 'menus', $config( $app )['/nino/features']['plain'] ) === false );

echo "\nC. Once\n";

$app = $project();
\Nino\Features::activate( $app, 'probe' );
// The project takes the page out of the menu
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	unset( $content['/nino/html/navroutes'] );
	return $content;
} );

\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::activate( $app, 'probe' );
$report = null;
\Nino\Features::activate( $app, 'probe', [], $report, true );
$afterwards = $config( $app );

check( 'a page the project took out of the menu stays out: switched off and on, and the content applied again, bring nothing back - the report of an application of content has no menus to count',
	isset( $afterwards['/nino/html/navroutes'] ) === false && $afterwards['/nino/features']['probe']['menus'] === [ 'offered' => [ '/probe' ], 'parked' => [] ] && isset( $report['menus'] ) === false );

// An update is an activation of a feature that is on, at another version: the unit says the same, the record says it was done
$feature( 'Probe', "[ 'main' => [ '/probe', '/probe-new' ] ]", [ 'probe' => '/probe', 'probe-new' => '/probe-new' ], '', '1.1.0' );
unset( $app['./nino/features/all'] );
$updated = \Nino\Features::get( $app, 'probe' );
$result = \Nino\Features::activate( $app, 'probe' );

check( 'an update of the feature offers nothing, not even what the new version names', $updated['update'] === true && $result === true && isset( $config( $app )['/nino/html/navroutes'] ) === false
	&& $config( $app )['/nino/features']['probe']['version'] === '1.1.0' );

// A record from before the key: a feature that was switched on when its unit had no menus has a version and no 'menus'
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$app = $project( [ '/nino/features' => [ 'probe' => [ 'version' => '1.0.0', 'settings' => [] ] ] ] );
\Nino\Features::activate( $app, 'probe' );

check( 'a feature that was recorded before its unit named menus, and that is switched on again or updated, offers nothing: it has been applied', isset( $config( $app )['/nino/html/navroutes'] ) === false && array_key_exists( 'menus', $config( $app )['/nino/features']['probe'] ) === false );

// Settings saved do not drop what the record keeps
$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::saveSettings( $app, 'probe', [] );

check( 'the record keeps what was added when the settings of the feature are saved', $config( $app )['/nino/features']['probe']['menus'] === [ 'offered' => [ '/probe' ], 'parked' => [] ] );

echo "\nD. Off\n";

$own = static fn( array $names = [] ): array => [ 'uri' => '/probe', 'httpUris' => [ '' => '/probe' ], 'template' => 'page-home', 'text' => [ 'de_DE' => [ 'name' => 'Eigene', 'title' => 'Eigene', 'description' => '' ], 'en_US' => [ 'name' => 'Own', 'title' => 'Own', 'description' => '' ] ] ] + ( $names === [] ? [] : [ 'navs' => $names ] );

$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::deactivate( $app, 'probe' );
$off = $config( $app );

check( 'switched off, the page is not in the menu and its address answers 404, as it did before the feature; nothing of it stands in navroutes - the membership is parked in the record of the feature',
	$menus( $app )[0] === [ '/about', '/contact' ] && $get( $app, '/probe' )[0] === 404 && isset( $off['/nino/html/navroutes'] ) === false
	&& $off['/nino/features']['probe']['menus'] === [ 'offered' => [ '/probe' ], 'parked' => [ '/probe' => [ 'main' => 3 ] ] ] && isset( $app['/nino/html/navroutes'] ) === false );

ninoUser( $app, 'dev@example.com' );
[ , $body ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiList' );
$members = array_column( (array) ( $body['navs'][0]['entries'] ?? [] ), 'uri' );
$candidates = array_column( (array) ( $body['routes'] ?? [] ), 'uri' );

check( 'the panel of the menus does not list it, neither as a member nor as a page that could be one', $members === [ '/about', '/contact' ] && in_array( '/probe', $candidates, true ) === false );

\Nino\Features::activate( $app, 'probe' );

check( 'switched on again, the page is where it was - no more offered, parked no more: its link answers',
	$menus( $app )[0] === [ '/about', '/contact', '/probe' ] && $get( $app, '/probe' )[0] === 200 && $member( $app, '/probe' ) === 3
	&& $config( $app )['/nino/features']['probe']['menus'] === [ 'offered' => [ '/probe' ], 'parked' => [] ] && ( $app['/nino/html/navroutes']['/probe']['main'] ?? null ) === 3 );

// The project saves the menu while the feature is off: there is nothing of the feature in it to lose
\Nino\Features::deactivate( $app, 'probe' );
[ $status ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'key' => 'main', 'originalKey' => 'main', 'entries' => [ '/contact', '/about' ] ] );
\Nino\Features::activate( $app, 'probe' );

check( 'a save of the menu in the Navigations panel while the feature is off does not cost it its entry: it is back at the end, where its old place is free',
	$status === 200 && $menus( $app )[0] === [ '/contact', '/about', '/probe' ] && $member( $app, '/probe' ) === 3 );

// Its old place where it is free, though not the last one of the menu
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/http/routes']['GET://contact']['navs']['main'] = 9;
	return $content;
} );
\Nino\Features::activate( $app, 'probe' );

check( 'a parked page is put back at its old place where that is free, not at the end of the menu', $member( $app, '/probe' ) === 3 && $menus( $app )[0] === [ '/about', '/probe', '/contact' ] );

// A place that is taken is not taken over: the page goes to the end
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/http/routes']['GET://contact']['navs']['main'] = 3;
	return $content;
} );
\Nino\Features::activate( $app, 'probe' );

check( 'a parked page whose place is held now by another page of the menu is put at the end of it', $member( $app, '/probe' ) === 4 );

// The menu is renamed, while the feature is on and while it is off
$app = $project();
ninoUser( $app, 'dev@example.com' );
\Nino\Features::activate( $app, 'probe' );
[ $status ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'key' => 'header', 'originalKey' => 'main' ] );
\Nino\Features::deactivate( $app, 'probe' );
$parked = $config( $app )['/nino/features']['probe']['menus']['parked'];
\Nino\Features::activate( $app, 'probe' );

check( 'a menu renamed while the feature is on: the page is parked from the menu under its new key and put back into it', $status === 200 && $parked === [ '/probe' => [ 'header' => 3 ] ] && $member( $app, '/probe', 'header' ) === 3 && $member( $app, '/probe' ) === null );

\Nino\Features::deactivate( $app, 'probe' );
[ $status ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'key' => 'main', 'originalKey' => 'header' ] );
\Nino\Features::activate( $app, 'probe' );

check( 'a menu that is renamed while the feature is off has no such key when it is on: what cannot be put back is left out, without a word, and nothing is left in navroutes', $status === 200 && isset( $config( $app )['/nino/html/navroutes'] ) === false && ninoWarnings() === [] );

// A page of the project under the address: before the feature and while it is off
$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::deactivate( $app, 'probe' );
$written = \Nino\Pages::put( $app, $own() );

check( 'a page of the project can be made under the address while the feature is off - no route of the feature holds it - and it answers; it inherits nothing: it is in no menu the feature put its page in',
	isset( $written['error'] ) === false && $get( $app, '/probe' )[0] === 200 && $menus( $app )[0] === [ '/about', '/contact' ] && isset( $config( $app )['/nino/html/navroutes'] ) === false );

\Nino\Features::activate( $app, 'probe' );

check( 'a feature that is switched on while a page of the project has the Element-URI of its page puts nothing beside that page', isset( $config( $app )['/nino/html/navroutes'] ) === false );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/html/navroutes']['/probe']['main'] = 7;
	return $content;
} );
\Nino\Features::remove( $app, 'probe' );

check( 'a feature that is removed leaves a membership of an address that a page of the project has, as it leaves the page', $member( $app, '/probe' ) === 7 );
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

// ...and the page that the project made before the feature was put in, under another address and the Element-URI of the page: it is the project's, and nothing is put beside it
$app = $project();
\Nino\Pages::put( $app, [ 'httpUris' => [ '' => '/mine' ] ] + $own( [ 'main' ] ) );
$report = null;
\Nino\Features::activate( $app, 'probe', [], $report );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::remove( $app, 'probe' );

check( 'a page of the project that has the Element-URI of the feature\'s page is the project\'s: the feature offers nothing for it, parks nothing and takes nothing away - its own place in the menu is there after the feature is on, off, on, off and removed',
	( $report['menus']['refused'] ?? [] ) === [ '/probe' ] && isset( $config( $app )['/nino/html/navroutes'] ) === false && $menus( $app )[0] === [ '/about', '/contact', '/mine' ] );

$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

echo "\nE. Removed\n";

$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::activate( $app, 'second' );
\Nino\Features::deactivate( $app, 'probe' );
$result = \Nino\Features::remove( $app, 'probe' );
$after = $config( $app );

check( 'a feature that is removed takes with it the memberships it added: none of its pages is left in the file - a page of another feature is not touched - and the record is given back, with its settings and version',
	$result === true && $after['/nino/html/navroutes'] === [ '/second' => [ 'main' => 4 ] ] && $after['/nino/features']['probe'] === [ 'version' => '1.0.0', 'settings' => [], 'menus' => null ] );

\Nino\Features::deactivate( $app, 'second' );
\Nino\Features::remove( $app, 'second' );

check( '...the last of them takes the key with it: nothing is left of the menus in config.php, and the menu draws only the pages of the project', isset( $config( $app )['/nino/html/navroutes'] ) === false && $menus( $app )[0] === [ '/about', '/contact' ] );

// The project's own page under the address of the feature
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::remove( $app, 'probe' );
$written = \Nino\Pages::put( $app, [ 'uri' => '/probe', 'httpUris' => [ '' => '/probe' ], 'template' => 'page-home', 'text' => [ 'de_DE' => [ 'name' => 'Eigene', 'title' => 'Eigene', 'description' => '' ], 'en_US' => [ 'name' => 'Own', 'title' => 'Own', 'description' => '' ] ], 'navs' => [ 'main' ] ] );

check( 'once removed, the address is free: a page of the project can be made under it, and it is in the menu by its own membership - no leftover of the feature stands in its way', isset( $written['error'] ) === false && $get( $app, '/probe' )[0] === 200
	&& in_array( '/probe', $menus( $app )[0], true ) === true && $member( $app, '/probe' ) === null );

// Put in again: offered again, and a page the project had taken out of the menu is the first time again
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	unset( $content['/nino/html/navroutes'] );
	return $content;
} );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::remove( $app, 'probe' );
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ], '', '1.2.0' );
$report = null;
\Nino\Features::activate( $app, 'probe', [], $report );

check( 'removed and put in again - at the version it had or at another - the unit is applied for the first time: the page is offered again', ( $report['menus']['added'] ?? -1 ) === 1 && $member( $app, '/probe' ) === 3 );
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

// A project that moved the entry: what the feature added and that is still there goes, wherever its place is now
$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/html/navroutes']['/probe']['main'] = 1;
	$content['/nino/html/navroutes']['/probe']['footer'] = 2;
	return $content;
} );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::remove( $app, 'probe' );

check( 'a membership the project moved, and one it made itself in another menu, are the page\'s and go with the feature: nothing of the address is left in navroutes', isset( $config( $app )['/nino/html/navroutes'] ) === false
	&& $config( $app )['/nino/features']['probe']['menus'] === null );

// A record from before the key: the feature is on, the unit says menus, the record has none - removing it frees it all the same
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$app = $project( [ '/nino/features' => [ 'probe' => [ 'version' => '1.0.0', 'settings' => [] ] ] ] );
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::remove( $app, 'probe' );
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$report = null;
\Nino\Features::activate( $app, 'probe', [], $report );

check( 'removing a feature whose record is from before the key frees it: put in again, it offers its page',  ( $report['menus']['added'] ?? -1 ) === 1 && $member( $app, '/probe' ) === 3 );

echo "\nF. Who may\n";

$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

$app = $project();
ninoUser( $app, 'manager@example.com', [ '/_admin/features/manage' ] );
$report = null;
$result = \Nino\Features::activate( $app, 'probe', [], $report );
$after = $config( $app );

check( 'an account that may switch features on and not change menus offers none: the feature is on, nothing is in the menu, the report says it was refused - and the record says it was done',
	$result === true && isset( $after['/nino/html/navroutes'] ) === false && ( $report['menus']['denied'] ?? false ) === true && ( $report['menus']['added'] ?? -1 ) === 0
	&& $after['/nino/features']['probe']['menus'] === [ 'offered' => [], 'parked' => [] ] );

// Once, and that was it: no later activation, update or script puts the page into a menu that has been kept since
\Nino\Auth::logoutUser( $app );
ninoUser( $app, 'editor@example.com', [ '/_admin/features/manage', '/_admin/navs/manage' ] );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::activate( $app, 'probe' );
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ], '', '1.1.0' );
unset( $app['./nino/features/all'] );
\Nino\Features::activate( $app, 'probe' );
\Nino\Auth::logoutUser( $app );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::activate( $app, 'probe' );
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

check( 'what was refused is not made up for: not by an account that may change menus, not by an update, not by a script with nobody signed in', isset( $config( $app )['/nino/html/navroutes'] ) === false );

// Taking away is not gated: a feature that is removed takes what it added whoever removes it
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Auth::logoutUser( $app );
ninoUser( $app, 'manager2@example.com', [ '/_admin/features/manage' ] );
\Nino\Features::remove( $app, 'probe' );

check( 'whoever removes the feature takes the memberships it added away', isset( $config( $app )['/nino/html/navroutes'] ) === false );

echo "\nH. What may be offered, and how many\n";

$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

$app = $project();
$report = null;
$ignored = ninoWarnings();
\Nino\Features::activate( $app, 'grab', [], $report );
$warned = ninoWarnings();

check( 'a page that is the Element-URI of a route of the project - a page it has taken out of the menu too - is not offered, with a warning each; one that is not is',
	( $report['menus']['refused'] ?? [] ) === [ '/about', '/contact' ] && $config( $app )['/nino/html/navroutes'] === [ '/free' => [ 'main' => 3 ] ] && count( $warned ) === 2
	&& $config( $app )['/nino/http/routes']['GET://about']['navs'] === [ 'main' => 1 ] );

$app = $project();
$report = null;
$ignored = ninoWarnings();
\Nino\Features::activate( $app, 'many', [], $report );
$warned = ninoWarnings();

check( 'a unit offers at most eight pages to menus, all menus together: the ninth and the tenth are left out with a warning each',
	( $report['menus']['added'] ?? -1 ) === 8 && count( $config( $app )['/nino/html/navroutes'] ) === 8 && isset( $config( $app )['/nino/html/navroutes']['/m9'] ) === false && count( array_filter( $warned, static fn( string $line ): bool => str_contains( $line, 'more than 8' ) ) ) === 2 );

$app = $project();
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/http/routes']['GET://contact']['navs']['main'] = PHP_INT_MAX;
	return $content;
} );
$report = null;
$ignored = ninoWarnings();
\Nino\Features::activate( $app, 'probe', [], $report );
ninoWarnings();

check( 'a place that would overflow is not written, and the report does not say that the page was added',
	( $report['menus']['added'] ?? -1 ) === 0 && ( $report['menus']['refused'] ?? [] ) === [ '/probe' ] && isset( $config( $app )['/nino/html/navroutes'] ) === false );

$app = $project();
$report = null;
\Nino\Features::activate( $app, 'dup', [], $report );

check( 'a page named twice by the unit is offered once, and is not counted as there already', ( $report['menus']['added'] ?? -1 ) === 1 && ( $report['menus']['present'] ?? -1 ) === 0 );

$app = $project();
$ignored = ninoWarnings();
\Nino\Features::activate( $app, 'nameless' );
$warned = ninoWarnings();

check( 'a page that the unit offers and names nowhere - no /_nino/webpage<uri>/name - is put and said to be one that the menu does not draw', count( $warned ) === 1 && str_contains( $warned[0], '/_nino/webpage/nameless/name' ) === true && $menus( $app )[0] === [ '/about', '/contact' ] );

echo "\nI. Sites from before the key, a page two features name, a switch-off of a refused manifest, the Navigation module off\n";

$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

// A site that has the feature from before: no 'menus' in the record, and the page put into menus by hand - the pages the unit names are parked, put back and taken like any other
$app = $project( [ '/nino/features' => [ 'probe' => [ 'version' => '1.0.0', 'settings' => [] ] ], '/nino/html/navroutes' => [ '/probe' => [ 'main' => 3, 'footer' => 1 ] ] ] );
$report = null;
\Nino\Features::activate( $app, 'probe', [], $report );
$stayed = $config( $app );
\Nino\Features::deactivate( $app, 'probe' );
$off = $config( $app );
\Nino\Features::activate( $app, 'probe' );

check( 'a feature from before the key offers nothing, and its page that the project put into menus by hand is parked when it is switched off - from every menu - and put back when it is on: nothing waits in navroutes',
	isset( $report['menus'] ) === true && $report['menus']['added'] === 0 && array_key_exists( 'menus', $stayed['/nino/features']['probe'] ) === false && $stayed['/nino/html/navroutes'] === [ '/probe' => [ 'main' => 3, 'footer' => 1 ] ]
	&& isset( $off['/nino/html/navroutes'] ) === false && $off['/nino/features']['probe']['menus']['parked'] === [ '/probe' => [ 'main' => 3, 'footer' => 1 ] ]
	&& $config( $app )['/nino/html/navroutes'] === [ '/probe' => [ 'main' => 3, 'footer' => 1 ] ] );

\Nino\Features::deactivate( $app, 'probe' );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/html/navroutes']['/probe']['main'] = 9;
	return $content;
} );
\Nino\Features::remove( $app, 'probe' );
$written = \Nino\Pages::put( $app, $own() );

check( 'removing it takes what is parked and what the project put in meanwhile; a page of the project made under the address after that stands in no menu',
	isset( $config( $app )['/nino/html/navroutes'] ) === false && isset( $written['error'] ) === false && $menus( $app )[0] === [ '/about', '/contact' ] );

$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

// A page that was a member when the unit was first applied belongs to the unit all the same
$app = $project( [ '/nino/html/navroutes' => [ '/probe' => [ 'main' => 7 ] ] ] );
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::deactivate( $app, 'probe' );
$parkedAt = $config( $app )['/nino/features']['probe']['menus']['parked'] ?? null;
\Nino\Features::activate( $app, 'probe' );

check( 'a page that was in the menu already when the unit was applied is parked and put back too', $parkedAt === [ '/probe' => [ 'main' => 7 ] ] && $member( $app, '/probe' ) === 7 );

// Two features that are on name the same page: it stays while one of them is
$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::activate( $app, 'alt' );
\Nino\Features::deactivate( $app, 'probe' );
$stays = $member( $app, '/probe' ) === 3 && $menus( $app )[0] === [ '/about', '/contact', '/probe' ];
\Nino\Features::remove( $app, 'probe' );
$stays = $stays && $member( $app, '/probe' ) === 3;
\Nino\Features::deactivate( $app, 'alt' );

check( 'a page that another feature which is on names too stays in the menu when one of the two is switched off or removed, and goes when the last of them is switched off', $stays === true && isset( $config( $app )['/nino/html/navroutes'] ) === false );

// A feature whose manifest is refused is switched off by deactivateRefused(): its page leaves the menu as well
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
$app = $project();
\Nino\Features::activate( $app, 'probe' );
file_put_contents( NINO_FEATURES_DIR. '/Probe/feature.php', "<?php return [ 'key' => 'probe', 'name' => 'Probe', 'version' => 'not-a-version' ];" );
unset( $app['./nino/features/all'] );
$result = \Nino\Features::deactivateRefused( $app, 'Probe' );
$off = $config( $app );

check( 'a feature that is switched off by deactivateRefused() parks its pages as a switch-off does: nothing waits in navroutes', $result === true && isset( $off['/nino/html/navroutes'] ) === false && $off['/nino/features']['probe']['menus']['parked'] === [ '/probe' => [ 'main' => 3 ] ] );
$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );
unset( $app['./nino/features/all'] );
\Nino\Features::activate( $app, 'probe' );

check( '...and they come back when it is on', $member( $app, '/probe' ) === 3 );

// The Navigation module is off when the feature is switched on: what is parked stays parked, and comes back when the module is on
$app = $project();
\Nino\Features::activate( $app, 'probe' );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/modules'] = array_values( array_diff( $content['/nino/modules'], [ '\\Nino\\Modules\\Navigation' ] ) );
	return $content;
} );
\Nino\Features::activate( $app, 'probe' );
$waits = $config( $app )['/nino/features']['probe']['menus']['parked'] ?? null;
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	$content['/nino/modules'][] = '\\Nino\\Modules\\Navigation';
	return $content;
} );
\Nino\Features::activate( $app, 'probe' );

check( 'with the Navigation module off the parked page stays parked when the feature is switched on, and is put back by the next activation once the module is on', $waits === [ '/probe' => [ 'main' => 3 ] ] && $member( $app, '/probe' ) === 3
	&& $config( $app )['/nino/features']['probe']['menus']['parked'] === [] );

echo "\nG. The panels\n";

$feature( 'Probe', "[ 'main' => [ '/probe' ] ]", [ 'probe' => '/probe' ] );

$app = $project( [ '/nino/html/navnames' => [ 'main' => 'Hauptmenü' ] ] );
ninoUser( $app, 'dev@example.com' );
[ $status, $body ] = $act( $app, 'dev@example.com', \Nino\Modules\Features\Admin::class, 'apiActivate', [ 'key' => 'probe' ] );

check( 'the Features panel names the page and the menu by their names where it put one, in the answer of the activation',
	$status === 200 && ( $body['menus'] ?? [] ) === [ [ 'key' => 'probe', 'name' => 'Probe (de)', 'page' => 'Probe', 'menu' => 'Hauptmenü' ] ] && isset( $body['menusRefused'] ) === false );

$app['/nino/admin/logs'] = true;
$lines = array_map( static fn( mixed $line ): string => is_array( $line ) === true ? implode( ' ', array_map( 'strval', $line ) ) : (string) $line, \Nino\Modules\Logs\Admin::recentLines( $app, 20 ) );

check( '...and the log of the actions has a line for it', count( array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'The unit of feature "probe" put "/probe" into the menu "main"' ) ) ) === 1 );

$app = $project();
ninoUser( $app, 'manager@example.com', [ '/_admin/features/manage' ] );
[ $status, $body ] = $act( $app, 'manager@example.com', \Nino\Modules\Features\Admin::class, 'apiActivate', [ 'key' => 'probe' ] );

check( 'an account that may not change menus is told so, and the menu is left as it is', $status === 200 && ( $body['menusRefused'] ?? [] ) === [ [ 'key' => 'probe', 'name' => 'Probe (de)' ] ] && isset( $body['menus'] ) === false
	&& isset( $config( $app )['/nino/html/navroutes'] ) === false );

$app['/nino/admin/logs'] = true;
$lines = array_map( static fn( mixed $line ): string => is_array( $line ) === true ? implode( ' ', array_map( 'strval', $line ) ) : (string) $line, \Nino\Modules\Logs\Admin::recentLines( $app, 20 ) );

check( '...and the log of the actions says that the unit was not allowed to', count( array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'The unit of feature "probe" offered pages to menus and was not allowed to' ) ) ) === 1 );

// A feature that is switched on with the one that was asked for: its pages and its refusal are in the answer, the sentence and the log as the asked one's
$app = $project();
ninoUser( $app, 'dev@example.com' );
[ $status, $body ] = $act( $app, 'dev@example.com', \Nino\Modules\Features\Admin::class, 'apiActivate', [ 'key' => 'child' ] );
$app['/nino/admin/logs'] = true;
$lines = array_map( static fn( mixed $line ): string => is_array( $line ) === true ? implode( ' ', array_map( 'strval', $line ) ) : (string) $line, \Nino\Modules\Logs\Admin::recentLines( $app, 20 ) );

check( 'a feature that comes on because another requires it says what it put into a menu: in the answer, with its own name, and in the log',
	$status === 200 && array_column( (array) ( $body['menus'] ?? [] ), 'key' ) === [ 'probe' ] && $member( $app, '/probe' ) === 3
	&& count( array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'The unit of feature "probe" put "/probe" into the menu "main"' ) ) ) === 1 );

$app = $project();
ninoUser( $app, 'manager@example.com', [ '/_admin/features/manage' ] );
[ $status, $body ] = $act( $app, 'manager@example.com', \Nino\Modules\Features\Admin::class, 'apiActivate', [ 'key' => 'child' ] );

check( '...and so does its refusal, for an account that may not change menus', $status === 200 && ( $body['menusRefused'] ?? [] ) === [ [ 'key' => 'probe', 'name' => 'Probe (de)' ] ] && isset( $config( $app )['/nino/html/navroutes'] ) === false );

$app = $project();
ninoUser( $app, 'dev@example.com' );
[ , $body ] = $act( $app, 'dev@example.com', \Nino\Modules\Features\Admin::class, 'apiActivate', [ 'key' => 'plain' ] );

check( 'a feature that offers nothing adds nothing to the answer', isset( $body['menus'] ) === false && isset( $body['menusRefused'] ) === false );

// The Navigations panel, with the page in the menu
$app = $project();
\Nino\Features::activate( $app, 'probe' );
ninoUser( $app, 'dev@example.com' );
[ , $body ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiList' );

check( 'the Navigations panel lists the page as a member of the menu, the last one, as a page that exists at run time', array_column( $body['navs'][0]['entries'], 'uri' ) === [ '/about', '/contact', '/probe' ]
	&& array_values( array_filter( $body['routes'], static fn( array $route ): bool => $route['uri'] === '/probe' ) )[0]['runtime'] === true );

[ $status ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'key' => 'main', 'originalKey' => 'main', 'entries' => [ '/probe', '/about', '/contact' ] ] );

check( 'a save of the menu with another order keeps the page and writes the places dense', $status === 200 && $member( $app, '/probe' ) === 1 && $config( $app )['/nino/http/routes']['GET://about']['navs']['main'] === 2 );

[ $status ] = $act( $app, 'dev@example.com', \Nino\Modules\Navigation\Admin::class, 'apiSave', [ 'key' => 'main', 'originalKey' => 'main', 'entries' => [ '/about', '/contact' ] ] );
\Nino\Features::deactivate( $app, 'probe' );
\Nino\Features::activate( $app, 'probe' );

check( 'a page that the project took out of the menu with the Navigations panel is not offered again by switching the feature off and on', $status === 200 && $member( $app, '/probe' ) === null );

$app = $project();
ninoUser( $app, 'dev@example.com' );
[ , $body ] = $act( $app, 'dev@example.com', \Nino\Modules\Features\Admin::class, 'apiActivate', [ 'key' => 'nameless' ] );
ninoWarnings();

check( 'a page that has no name is not said to be in the menu: the answer lists it apart, by its address, as one that waits for its name',
	( $body['menusUnnamed'] ?? [] ) === [ [ 'key' => 'nameless', 'name' => 'Nameless (de)', 'page' => '/nameless', 'menu' => 'main' ] ] && isset( $body['menus'] ) === false );

check( 'the run raised no warning but those of the declarations that are unusable', ninoWarnings() === [] );

ninoDone( $app );
