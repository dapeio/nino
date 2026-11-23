<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-modules-smoke.php	Test of what Basis does where a module it asks for data is not there: the menu components ask Navigation for the entries of a
 *													menu and [footer-legal] asks it for the legal pages and Localepicker for the languages, and a project whose kernel has none
 *													of the two is drawn all the same - no menu, no list of languages, the line of the law and the logo as ever, and nothing thrown.
 *													The kernel is a copy of this one without the folders of the two modules, run as a process of its own.
 *
 *													What it reads of the kernel besides is the root of its words: /module/basis/ where the kernel delivers it and /feature/basis/
 *													where it is a feature of the catalogue, which is a copy of its folder below features/ - run as a process of its own on a
 *													kernel without the folder - draws the same menu with the words of /feature/basis/.
 *
 *	Usage: php tests/basis-modules-smoke.php
 */

define( 'NINO_FEATURES_DIR', __DIR__. '/fixtures/features' );

require __DIR__. '/harness.php';

$ignored = ninoWarnings();
$appData = ninoSandbox( 'basis-modules' );
$sandbox = ninoSandboxDir( $appData );
$root = dirname( __DIR__ );

// The kernel without the two modules, and a place for the project
mkdir( $sandbox. '/kernel/tests', 0755, true );
\Nino\Filesystem::copyDir( $root. '/_nino', $sandbox. '/kernel/_nino' );
\Nino\Filesystem::copyDir( $root. '/_admin', $sandbox. '/kernel/_admin' );
copy( $root. '/tests/harness.php', $sandbox. '/kernel/tests/harness.php' );

foreach( [ 'Navigation', 'Localepicker' ] as $module )
	\Nino\Filesystem::removeDir( $sandbox. '/kernel/_nino/Nino/Modules/'. $module );

// The run: Basis on, a header with its menu and a footer with its line of the law, drawn as the kernel draws a page
file_put_contents( $sandbox. '/run.php', <<<'PHP'
<?php
define( 'NINO_FEATURES_DIR', $argv[2] );
require $argv[1]. '/tests/harness.php';

$appData = ninoSandbox( 'basis-modules-child' );
$appData['/nino/html/navs'] = [ 'main', 'footer', 'legal' ];
$appData['/nino/modules'] = [ '\Nino\Modules\Basis' ];
\Nino\Features::registerComponents( $appData );
\Nino\Html::init( $appData );

$draw = static function( string $source ) use ( &$appData ): string {
	return \Nino\Html::renderSource( $appData, $source, \Nino\Html::context( $appData ) );
};

echo json_encode( [
	'navigation'	=> class_exists( '\Nino\Modules\Navigation', false ) || is_file( $argv[1]. '/_nino/Nino/Modules/Navigation/Navigation.php' ),
	'localepicker'	=> is_file( $argv[1]. '/_nino/Nino/Modules/Localepicker/Localepicker.php' ),
	'registered'	=> isset( \Nino\Components::components( $appData )['footer-legal'], \Nino\Components::components( $appData )['header-nav'] ),
	'header'		=> $draw( '[header-wrap][header-nav][header-nav menu="footer"][/header-wrap]' ),
	'footer'		=> $draw( '[footer-wrap][footer-nav][footer-legal][/footer-wrap]' ),
] );
PHP );

$child = proc_open( [ PHP_BINARY, $sandbox. '/run.php', $sandbox. '/kernel', NINO_FEATURES_DIR ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
$out = (string) stream_get_contents( $pipes[1] );
$err = (string) stream_get_contents( $pipes[2] );
fclose( $pipes[1] );
fclose( $pipes[2] );
$code = proc_close( $child );
$drawn = json_decode( $out, true );

echo "Basis in a kernel that has no Navigation and no Localepicker\n";

check( 'the process of the copy ran to its end and the copy has neither module', $code === 0 && is_array( $drawn ) === true && $drawn['navigation'] === false && $drawn['localepicker'] === false && $drawn['registered'] === true );
check( 'a menu component draws nothing where the module of the menus is not there: no navigation, no button, and the header is the wrap that holds them',
	is_array( $drawn ) === true && str_contains( $drawn['header'], 'header-wrap' ) === true && str_contains( $drawn['header'], '<nav' ) === false && str_contains( $drawn['header'], '<details' ) === false );
check( 'the footer is drawn without a list of menu entries and without a list of languages: the line of the law stays, and nothing is said in the error output of the process',
	is_array( $drawn ) === true && str_contains( $drawn['footer'], 'footer-legal-copyright' ) === true && str_contains( $drawn['footer'], 'footer-legal-pages' ) === false
	&& str_contains( $drawn['footer'], 'footer-legal-locales' ) === false && str_contains( $drawn['footer'], '<nav' ) === false && $err === '' );

// --- The words of Basis ------------------------------------------------------------------------------------------------

echo "\nThe root of the words of Basis\n";

/**
 *	A process that draws the menu of a header of Basis in a kernel of its own, with the words of both roots in the texts of its project
 *
 *	@param		string		$kernel				The directory of the kernel the process runs in
 *	@param		string		$features			The features directory of the run
 *
 *	@return 	array|null								[ 'root' => ..., 'header' => ..., 'on' => ... ]
 */
function basisWords( string $kernel, string $features ): ?array {

	$script = $kernel. '/words.php';

	file_put_contents( $script, <<<'PHP'
<?php
define( 'NINO_FEATURES_DIR', $argv[2] );
require $argv[1]. '/tests/harness.php';

$appData = ninoSandbox( 'basis-words-child' );
$appData['/nino/html/navs'] = [ 'main' ];
$appData['/nino/modules'] = [ '\Nino\Modules\Basis' ];

foreach( [ 'de_DE', 'en_US' ] as $locale )
	\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', [
		'[[/module/basis/navigation/primary]]' => 'Module '. $locale, '[[/module/basis/navigation/menu]]' => 'Module menu '. $locale,
		'[[/feature/basis/navigation/primary]]' => 'Feature '. $locale, '[[/feature/basis/navigation/menu]]' => 'Feature menu '. $locale,
	] );

\Nino\Callbacks::registerCallback( $appData, '/nino/navigation/lines', static function( array &$appData, array $args ): array {
	return [ 'nav' => $args['nav'], 'lines' => [ '/about:About' ] ];
} );
\Nino\Features::registerComponents( $appData );
\Nino\Html::init( $appData );
\Nino\Locales::useLocale( $appData, 'en_US' );

echo json_encode( [
	'root'		=> \Nino\Features::textRoot( $appData, 'basis' ),
	'header'	=> \Nino\Html::renderSource( $appData, '[header-wrap][header-nav][/header-wrap]', \Nino\Html::context( $appData ) ),
	'on'			=> isset( \Nino\Components::components( $appData )['header-nav'] ),
] );
PHP );

	$child = proc_open( [ PHP_BINARY, $script, $kernel, $features ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
	$out = (string) stream_get_contents( $pipes[1] );
	stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	proc_close( $child );

	$read = json_decode( $out, true );

	return is_array( $read ) === true ? $read : null;
}

// The kernel as it is: the words are those of a module of the kernel
mkdir( $sandbox. '/kernel-whole/tests', 0755, true );
\Nino\Filesystem::copyDir( $root. '/_nino', $sandbox. '/kernel-whole/_nino' );
\Nino\Filesystem::copyDir( $root. '/_admin', $sandbox. '/kernel-whole/_admin' );
copy( $root. '/tests/harness.php', $sandbox. '/kernel-whole/tests/harness.php' );
mkdir( $sandbox. '/features-none', 0755, true );
$whole = basisWords( $sandbox. '/kernel-whole', $sandbox. '/features-none' );

// The kernel without the folder, and the folder below features/ as a catalogue feature has it: the path of its asset below features/ is all it needs
mkdir( $sandbox. '/kernel-less/tests', 0755, true );
\Nino\Filesystem::copyDir( $root. '/_nino', $sandbox. '/kernel-less/_nino' );
\Nino\Filesystem::copyDir( $root. '/_admin', $sandbox. '/kernel-less/_admin' );
copy( $root. '/tests/harness.php', $sandbox. '/kernel-less/tests/harness.php' );
\Nino\Filesystem::copyDir( $sandbox. '/kernel-less/_nino/Nino/Modules/Basis', $sandbox. '/features-basis/Basis' );
\Nino\Filesystem::removeDir( $sandbox. '/kernel-less/_nino/Nino/Modules/Basis' );
$manifest = (string) file_get_contents( $sandbox. '/features-basis/Basis/feature.php' );
file_put_contents( $sandbox. '/features-basis/Basis/feature.php', str_replace( [ '/_nino/Nino/Modules/Basis/assets/', "'category'" ], [ '/features/Basis/assets/', "'nino' => '^2.0', 'category'" ], $manifest ) );
$catalogue = basisWords( $sandbox. '/kernel-less', $sandbox. '/features-basis' );

check( 'the words of Basis, where the kernel delivers it, are the words of a module: /module/basis/, and the menu is drawn with them',
	is_array( $whole ) === true && $whole['root'] === '/module/basis/' && str_contains( $whole['header'], 'aria-label="Module en_US"' ) === true && str_contains( $whole['header'], 'aria-label="Module menu en_US"' ) === true );
check( 'the same folder as a feature of the catalogue draws the same menu with the words of a feature: /feature/basis/, and none of the other root is read',
	is_array( $catalogue ) === true && $catalogue['on'] === true && $catalogue['root'] === '/feature/basis/' && str_contains( $catalogue['header'], 'aria-label="Feature en_US"' ) === true
	&& str_contains( $catalogue['header'], 'aria-label="Feature menu en_US"' ) === true && str_contains( $catalogue['header'], 'Module' ) === false );

ninoDone( $appData );
