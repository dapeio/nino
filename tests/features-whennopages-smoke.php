<?php
declare(strict_types=1);

/**
 *	Nino
 *	features-whennopages-smoke.php	Contract test for the key 'whenNoPages' of an install unit: a unit that is the start of a site is applied as a whole where the
 *												project has no page, and not at all where it has one. The unit of the run brings everything a unit can bring - a route that is a page and one
 *												that is none, a template, a file with a picture place, words in two languages and a global one, a blacklist key, a default of the
 *												configuration, an element type with elements - and is placed by activating the feature, as the Features panel does.
 *
 *													A. a project with a page of its own, under a key the unit does not use: the activation goes through and the unit places nothing - the files
 *													   of the project and its config.php are what they were, but for the list of the modules and the record of the feature - and its report says so
 *													B. a project without a page - robots.txt, a route that is no page, words and templates of its own do not make one: the unit is placed
 *													   as a whole, and its own routes are no reason to leave it out
 *													C. the decision is the project's state before the unit, each time: switched on again, off and on, applied again from the panel, a page of the
 *													   unit that was deleted is not brought back - the project has pages
 *													D. the setup wizard applies a unit with overwrite, and the key does nothing there; a unit without the key is applied as it always was, where
 *													   the project has a page as where it has none
 *													   a page that answers with an error status - a 404 page - is none; the decision reads the routes that are stored, not those a module registers at run time
 *													E. what the panel says after Apply content, after switching on and after an update: the answer and the log name why nothing was placed, in a sentence of the interface language
 *
 *	Usage: php tests/features-whennopages-smoke.php
 */

// The features of this run: a start unit and a plain one, the same unit under two keys
$work = sys_get_temp_dir(). '/nino-whennopages-'. bin2hex( random_bytes( 6 ) );
define( 'NINO_FEATURES_DIR', $work. '/features' );

$unit = static function( string $key, string $name, bool $when ): void {

	$dir = NINO_FEATURES_DIR. '/'. $name;
	mkdir( $dir. '/install/templates', 0755, true );
	mkdir( $dir. '/install/text', 0755, true );
	mkdir( $dir. '/install/images', 0755, true );
	mkdir( $dir. '/install/elements', 0755, true );

	file_put_contents( $dir. '/feature.php', "<?php return [ 'key' => '". $key. "', 'name' => [ 'en_US' => '". $name. "', 'de_DE' => '". $name. "' ], 'version' => '1.0.0' ];" );
	file_put_contents( $dir. '/'. $name. '.php', "<?php\nnamespace Nino\\Modules { class ". $name. " {} }\n" );
	file_put_contents( $dir. '/install/manifest.php', "<?php return [\n". ( $when === true ? "\t'whenNoPages' => true,\n" : '' ). "\t'routes' => [\n"
		. "\t\t'GET://". $key. "' => [ 'uri' => '/". $key. "', 'body' => '[template /templates/page-". $key. "]', 'navs' => [ 'main' => 1 ] ],\n"
		. "\t\t'GET://". $key. "-feed' => [ 'uri' => '/". $key. "-feed', 'body' => '[template /templates/". $key. "-feed]' ],\n\t],\n"
		. "\t'templates' => [ 'page-". $key. ".tpl', '". $key. "-feed.tpl' ],\n\t'files' => [ 'images/". $key. ".svg' ],\n"
		. "\t'imageSlots' => [ '/". $key. "/banner' => [ 'label' => 'Banner', 'width' => 100, 'height' => 50, 'filename' => '". $key. ".svg' ] ],\n"
		. "\t'blacklist' => [ '/_nino/webpage/". $key. "/uri' ],\n\t'config' => [ '/". $key. "/setting' => 'unit-default' ],\n"
		. "\t'elementTypes' => [ '". $key. "-things.php' ],\n\t'elements' => [ '". $key. "-things' => 'elements/things.php' ],\n];" );
	file_put_contents( $dir. '/install/templates/page-'. $key. '.tpl', '<p>The page of '. $key. '</p>' );
	file_put_contents( $dir. '/install/templates/'. $key. '-feed.tpl', '<p>The feed of '. $key. '</p>' );
	file_put_contents( $dir. '/install/images/'. $key. '.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="50"></svg>' );
	file_put_contents( $dir. '/install/text/en_US.php', "<?php return [ '[[/_nino/webpage/". $key. "/name]]' => 'The ". $key. "' ];" );
	file_put_contents( $dir. '/install/text/de_DE.php', "<?php return [ '[[/_nino/webpage/". $key. "/name]]' => 'Das ". $key. "' ];" );
	file_put_contents( $dir. '/install/text/global.php', "<?php return [ '[[/_nino/webpage/". $key. "/uri]]' => '/". $key. "' ];" );
	file_put_contents( $dir. '/install/'. $key. '-things.php', "<?php return [ 'title' => 'Things', 'model' => [ 'title' => [ 'type' => 'string', 'locale' => true ] ], '*' => [], 'de_DE' => [] ];" );
	file_put_contents( $dir. '/install/elements/things.php', "<?php return [ '*' => [ 'one' => [] ], 'de_DE' => [ 'one' => [ 'title' => 'One' ] ] ];" );
};

$unit( 'starter', 'Starter', true );
$unit( 'plain', 'Plain', false );

require __DIR__. '/harness.php';

register_shutdown_function( static fn() => \Nino\Filesystem::removeDir( $work ) );

/**
 *	A project of the run: two languages, the routes it has - stored in config.php, as the Routes panel stores them - and a template or two of its own
 *
 *	@param		array 		$routes				The routes the project has
 *
 *	@return 	array										$appData
 */
$project = static function( array $routes ): array {

	$app = ninoSandbox( 'whennopages' );
	$dir = ninoSandboxDir( $app );

	foreach( [ '/private/templates', '/private/text', '/private/assets', '/public/images' ] as $sub )
		mkdir( $dir. $sub, 0777, true );

	file_put_contents( $dir. '/private/templates/page-about.tpl', '<p>About</p>' );
	\Nino\Filesystem::putFileContent( $app, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [], '/nino/http/routes' => $routes ] );
	$app['/nino/http/routes'] = $routes;

	return $app;
};

/**
 *	What a project is made of, apart from what an activation records of itself: every file below it but the kept trees, and config.php without the list of modules and the record of the features
 *
 *	@param		array 		&$app
 *
 *	@return 	array
 */
$state = static function( array &$app ): array {

	$dir = ninoSandboxDir( $app );
	$files = [];

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
		if( $file->isFile() === true && str_contains( $file->getPathname(), '/private/data/' ) === false && basename( $file->getPathname() ) !== 'config.php' )
			$files[substr( $file->getPathname(), strlen( $dir ) )] = md5_file( $file->getPathname() );

	ksort( $files );
	$config = \Nino\Filesystem::getFileContent( $app, '/config.php', [] );
	unset( $config['/nino/modules'], $config['/nino/features'] );

	return [ $files, $config ];
};

$about = [ 'GET://about' => [ 'uri' => '/about', 'body' => '[template /templates/page-about]', 'navs' => [ 'main' => 1 ] ] ];
$robots = [ 'GET://robots.txt' => [ 'uri' => '/robots.txt', 'body' => '[template /templates/robots]' ], 'GET://feed' => [ 'uri' => '/feed', 'body' => '<rss/>' ] ];

/**
 *	Whether the whole unit of a feature is in the project: its routes, its templates, its file and picture place, its words, its blacklist key, its default and its type with its element
 *
 *	@param		array 		&$app
 *	@param		string		$key
 *
 *	@return 	bool
 */
$placed = static function( array &$app, string $key ): bool {

	$dir = ninoSandboxDir( $app );
	$config = \Nino\Filesystem::getFileContent( $app, '/config.php', [] );
	$types = \Nino\Filesystem::getFileContent( $app, '/elements/'. $key. '-things.php', [] );

	return isset( $config['/nino/http/routes']['GET://'. $key], $config['/nino/http/routes']['GET://'. $key. '-feed'], $config['/nino/html/images']['/'. $key. '/banner'] ) === true && ( $config['/'. $key. '/setting'] ?? '' ) === 'unit-default'
		&& is_file( $dir. '/private/templates/page-'. $key. '.tpl' ) === true && is_file( $dir. '/private/templates/'. $key. '-feed.tpl' ) === true && is_file( $dir. '/public/images/'. $key. '.svg' ) === true
		&& isset( \Nino\Filesystem::getFileContent( $app, '/text/en_US.php', [] )['[[/_nino/webpage/'. $key. '/name]]'], \Nino\Filesystem::getFileContent( $app, '/text/de_DE.php', [] )['[[/_nino/webpage/'. $key. '/name]]'] ) === true
		&& ( \Nino\Filesystem::getFileContent( $app, '/text/global.php', [] )['[[/_nino/webpage/'. $key. '/uri]]'] ?? '' ) === '/'. $key
		&& in_array( '/_nino/webpage/'. $key. '/uri', (array) \Nino\Filesystem::getFileContent( $app, '/text/blacklist.php', [] ), true ) === true
		&& isset( $types['*']['one'] ) === true;
};

// --- A. a project with a page --------------------------------------------------------------------------------------------

echo "A project with a page\n";

$app = $project( $about );
$before = $state( $app );
$report = null;
$result = \Nino\Features::activate( $app, 'starter', [], $report );
$after = $state( $app );
$modules = (array) ( \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/modules'] ?? [] );

check( 'a unit that is the start of a site is left out where the project has a page: the feature is switched on and recorded, with no error', $result === true && in_array( '\\Nino\\Modules\\Starter', $modules, true ) === true
	&& ( \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/features']['starter']['version'] ?? '' ) === '1.0.0' );
check( '...and places nothing: no file, no template, no picture, no word, no type, no route, no menu, no blacklist key, no default - the project is what it was', $before === $after && $placed( $app, 'starter' ) === false );
check( '...and the report says why: skipped for the pages, and not one thing placed or found', ( $report['skipped'] ?? '' ) === 'pages' && array_sum( array_merge( ...array_values( array_map( 'array_values', array_filter( (array) $report, 'is_array' ) ) ) ) ) === 0 );

// --- B. a project without a page ------------------------------------------------------------------------------------------

echo "\nA project without a page\n";

$app = $project( $robots );
$report = null;
$result = \Nino\Features::activate( $app, 'starter', [], $report );

check( 'a project whose routes are no page - robots.txt, a route with another body - gets the whole unit, and its own routes are no reason to leave it out', $result === true && $placed( $app, 'starter' ) === true && ( $report['skipped'] ?? '' ) === ''
	&& $report['routes']['added'] === 2 && $report['templates']['added'] === 2 );

$app = $project( [] );
$result = \Nino\Features::activate( $app, 'starter' );

check( 'a project with no route at all gets the whole unit', $result === true && $placed( $app, 'starter' ) === true );

// A site that has only its 404 page has not begun, and the unit is placed whole - its own 404 route stays the project's
$app = $project( [ 'GET://404' => [ 'uri' => '/404', 'body' => '[template /templates/page-about]', 'statusCode' => 404 ], 'GET://gone' => [ 'uri' => '/gone', 'body' => '[template /templates/page-about]', 'statusCode' => 410 ] ] );
$report = null;
$result = \Nino\Features::activate( $app, 'starter', [], $report );
$stored = \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/http/routes'];

check( 'a project whose only pages answer with an error status - the 404 page, a page that is gone - gets the whole unit, and the 404 route it has is not replaced', $result === true && $placed( $app, 'starter' ) === true && ( $report['skipped'] ?? '' ) === ''
	&& ( $stored['GET://404']['uri'] ?? '' ) === '/404' && ( $stored['GET://404']['statusCode'] ?? 0 ) === 404 );

// The routes a module registers at run time - the legal pages, the list of the posts - are in the live array and not in config.php: the decision reads what is stored
$app = $project( [] );
$app['/nino/http/routes'] = $about;
$report = null;
$result = \Nino\Features::activate( $app, 'starter', [], $report );

check( 'a page that stands in the routes of this request only, as a module registers it, is none: the unit is placed whole where config.php holds no page', $result === true && $placed( $app, 'starter' ) === true && ( $report['skipped'] ?? '' ) === '' );

// --- C. the state before the unit, each time ----------------------------------------------------------------------------------

echo "\nEach time\n";

$app = $project( [] );
\Nino\Features::activate( $app, 'starter' );
$once = $state( $app );
$report = null;
\Nino\Features::activate( $app, 'starter', [], $report, true );
check( 'switched on again, the project has the pages of the unit and the unit places nothing more: the project is what it was, and the report says so', $once === $state( $app ) && ( $report['skipped'] ?? '' ) === 'pages' );

// A page of the unit that was deleted is the project's decision, and the project has pages - here its own as well
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ) use ( $about ): array {
	unset( $content['/nino/http/routes']['GET://starter'] );
	$content['/nino/http/routes'] += $about;
	return $content;
} );
$app['/nino/http/routes'] = \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/http/routes'];
\Nino\Features::deactivate( $app, 'starter' );
\Nino\Features::activate( $app, 'starter' );
\Nino\Features::activate( $app, 'starter', [], $report, true );
$routes = \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/http/routes'];

check( 'switched off and on, and applied again, a page of the unit that the project deleted stays deleted where the project has pages', isset( $routes['GET://starter'] ) === false && isset( $routes['GET://about'] ) === true && ( $report['skipped'] ?? '' ) === 'pages' );

// Without a page left the project has begun nothing: the unit comes whole
\Nino\Filesystem::mutate( $app, '/config.php', static function( array $content ): array {
	unset( $content['/nino/http/routes']['GET://about'] );
	return $content;
} );
$app['/nino/http/routes'] = \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/http/routes'];
\Nino\Features::activate( $app, 'starter', [], $report, true );

check( 'a project that has no page left gets the unit whole once more: the decision is made at every activation, from what the project has', ( $report['skipped'] ?? '' ) === '' && isset( \Nino\Filesystem::getFileContent( $app, '/config.php', [] )['/nino/http/routes']['GET://starter'] ) === true );

// --- D. overwrite, and a unit without the key ---------------------------------------------------------------------------

echo "\nOverwrite, and a unit without the key\n";

$app = $project( $about );
$routes = $about;
$blacklist = [];
$config = [];
$report = null;
$applied = \Nino\Features::applyUnit( $app, NINO_FEATURES_DIR. '/Starter/install', [ 'de_DE', 'en_US' ], $routes, $blacklist, $config, true, $report );

check( 'applied with overwrite - what the setup wizard does - the unit is placed where the project has a page, and the key does nothing', $applied === true && isset( $routes['GET://starter'] ) === true && ( $report['skipped'] ?? '' ) === ''
	&& is_file( ninoSandboxDir( $app ). '/private/templates/page-starter.tpl' ) === true );

$app = $project( $about );
$report = null;
$result = \Nino\Features::activate( $app, 'plain', [], $report );

check( 'a unit without the key is applied as it always was, where the project has a page: the whole unit, add-only', $result === true && $placed( $app, 'plain' ) === true && ( $report['skipped'] ?? '' ) === '' );

$app = $project( [] );
\Nino\Features::activate( $app, 'plain', [], $report );

check( '...and where it has none, the same', $placed( $app, 'plain' ) === true && ( $report['skipped'] ?? '' ) === '' );

// --- E. what the panel says ------------------------------------------------------------------------------------------------

echo "\nThe panel\n";

$app = $project( $about );
ninoUser( $app, 'dev@example.com' );
\Nino\Features::activate( $app, 'starter' );
unset( $app['./nino/features/all'] );
[ $status, $body ] = array_slice( ninoCall( $app, [ \Nino\Modules\Features\Admin::class, 'apiReapply' ], [ 'key' => 'starter' ] ), 0, 2 );

check( 'Apply content on a feature that was left out says so in the answer: skipped for the pages, every count 0', $status === 200 && ( $body['applied']['skipped'] ?? '' ) === 'pages' && ( $body['applied']['routes']['added'] ?? -1 ) === 0 );

// ...and after switching on, and after an update, which is the same activation: the answer names the feature, and the log holds it
$app = $project( $about );
$app['/nino/admin/logs'] = true;
ninoUser( $app, 'dev@example.com' );
[ $status, $body ] = array_slice( ninoCall( $app, [ \Nino\Modules\Features\Admin::class, 'apiActivate' ], [ 'key' => 'starter' ] ), 0, 2 );
$lines = array_map( static fn( mixed $line ): string => is_array( $line ) === true ? implode( ' ', array_map( 'strval', $line ) ) : (string) $line, \Nino\Modules\Logs\Admin::recentLines( $app, 20 ) );

check( 'switching a feature on that was left out says so in the answer, with its name, and the log holds that the unit was left out because the project has pages',
	$status === 200 && ( $body['skipped'] ?? null ) === [ [ 'key' => 'starter', 'name' => 'Starter' ] ] && count( array_filter( $lines, static fn( string $line ): bool => str_contains( $line, 'The unit of feature "starter" was left out' ) ) ) === 1 );

$app = $project( [] );
ninoUser( $app, 'dev@example.com' );
[ , $body ] = array_slice( ninoCall( $app, [ \Nino\Modules\Features\Admin::class, 'apiActivate' ], [ 'key' => 'starter' ] ), 0, 2 );

check( '...and a feature whose unit was placed says nothing of it', ( $body['skipped'] ?? null ) === [] );

$words = [ 'en_US' => include __DIR__. '/../_admin/Nino/Modules/Features/text/en_US.php', 'de_DE' => include __DIR__. '/../_admin/Nino/Modules/Features/text/de_DE.php' ];
check( 'the sentence is in both languages of the panel, and names the feature', str_contains( (string) ( $words['en_US']['[[/_admin/features/msg/unit-skipped]]'] ?? '' ), '%s' ) === true && str_contains( (string) ( $words['de_DE']['[[/_admin/features/msg/unit-skipped]]'] ?? '' ), '%s' ) === true );

$empty = [];
ninoDone( $empty );
