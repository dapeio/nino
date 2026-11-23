<?php
declare(strict_types=1);

/**
 *	Nino
 *	component-menus-smoke.php	Contract test for the schema key 'menu' - the component says that it draws a menu of the site, and which - and for
 *														what reads it:
 *
 *															A. \Nino\Components::validate() takes the two forms, the attribute that names the menu and the menu the component
 *																 draws, and refuses what is none of them; check() finds the call that names a menu there is none of
 *															B. \Nino\Components::menus() answers what the calls of a source draw - the menu a call names, the default where it names
 *																 none, the fixed one - for the registered components only
 *															C. \Nino\Modules\Legal::check() reads a menu component of any feature: a page of the legal texts
 *																 that stands in no menu a template outputs is a finding, and the menu a component of the test features draws is an output
 *
 *														The components are those of tests/fixtures/kit/Kit (crown-nav and foot-nav, which name their menu by an attribute) and of
 *														tests/fixtures/rider/Addon (addon-legal, which draws the menu legal). What Legal reads of Basis is in the suites of the feature Basis in the catalogue.
 *
 *	Usage: php tests/component-menus-smoke.php
 */

define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-menus-features-'. bin2hex( random_bytes( 6 ) ) );
mkdir( NINO_FEATURES_DIR, 0755, true );
register_shutdown_function( static function(): void {
	\Nino\Filesystem::removeDir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';
require __DIR__. '/../_admin/install/Install.php';

\Nino\Filesystem::copyDir( __DIR__. '/fixtures/kit/Kit', NINO_FEATURES_DIR. '/Kit' );
\Nino\Filesystem::copyDir( __DIR__. '/fixtures/rider/Addon', NINO_FEATURES_DIR. '/Addon' );

$ignored = ninoWarnings();

/**
 *	A project the wizard set up, with the test features on as a project does in the Features panel, and the texts of the legal pages filled in
 *
 *	@param		string		$name
 *
 *	@return 	array
 */
function menusProject( string $name ): array {

	$appData = ninoSandbox( $name );
	$sandbox = ninoSandboxDir( $appData );
	$appData['/nino/dir'] = '';

	foreach( [ 'private/templates', 'private/text', 'private/assets', 'public/images' ] as $directory )
		mkdir( $sandbox. '/'. $directory, 0777, true );

	\Nino\Filesystem::putFileContent( $appData, '/config.php', [ '/nino/locales/native' => 'de_DE', '/nino/locales/available' => [ 'de_DE', 'en_US' ], '/nino/modules' => [], '/nino/html/assets' => [], '/nino/http/routes' => [] ] );
	ob_start();
	ninoCall( $appData, [ \Nino\Install\Setup::class, 'apiApply' ], [ 'locales' => [ 'de_DE', 'en_US' ], 'modules' => [] ] );
	ob_end_clean();
	\Nino\Features::activate( $appData, 'addon' );
	$_POST['data'] = json_encode( [] );
	$appData['/nino/modules'] = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/modules'];

	foreach( [ 'de_DE', 'en_US' ] as $locale )
		\Nino\Filesystem::mutate( $appData, '/text/'. $locale. '.php', static function( array $texts ): array {
			foreach( [ 'general/name' => 'Muster GmbH', 'contact/address' => 'Hauptstr. 1', 'contact/phone' => '+49 1', 'contact/email' => 'a@example.org' ] as $key => $value )
				$texts['[[/project/company/'. $key. ']]'] = $value;
			$texts['[[/project/website/general/author]]'] = 'Max';
			$texts['[[/project/website/general/url]]'] = 'example.org';
			return $texts;
		}, [] );

	\Nino\Html::init( $appData );
	\Nino\Features::registerComponents( $appData );
	\Nino\Modules\Legal::init( $appData );
	\Nino\Locales::useLocale( $appData, 'de_DE' );
	ninoWarnings();

	return $appData;
}

$project = menusProject( 'menus' );

// --- A. the schema ------------------------------------------------------------------------------------------------------

echo "The schema key menu: what validate() takes and what it refuses\n";

$attributes = [
	'menu'  => [ 'type' => 'choice', 'options' => [ 'main', 'footer' ], 'default' => 'main', 'label' => 'Menu' ],
	'title' => [ 'type' => 'string', 'default' => 'footer', 'label' => 'Title' ],
	'open'  => [ 'type' => 'bool', 'default' => false, 'label' => 'Open' ],
	'blank' => [ 'type' => 'string', 'default' => '', 'label' => 'Blank' ],
	'words' => [ 'type' => 'text', 'default' => 'main', 'label' => 'Words' ],
	'fill'  => [ 'type' => 'string', 'default' => '[[x]]', 'label' => 'Fill' ],
	'upper' => [ 'type' => 'string', 'default' => 'Main', 'label' => 'Upper' ],
];
$schema = static fn( mixed $menu ): array|string => \Nino\Components::validate( 'menu-probe', [ 'label' => 'Probe', 'in' => [ 'page' ], 'attributes' => $attributes, 'menu' => $menu, 'template' => '<nav></nav>' ] );

$byAttribute = $schema( [ 'attribute' => 'menu' ] );
$fixed = $schema( [ 'name' => 'legal' ] );
$byString = $schema( [ 'attribute' => 'title' ] );
check( 'a schema may name the attribute that names the menu - a choice or a string whose default is a menu - or the menu it draws, and the clean schema keeps what it said',
	is_array( $byAttribute ) === true && $byAttribute['menu'] === [ 'attribute' => 'menu' ] && is_array( $fixed ) === true && $fixed['menu'] === [ 'name' => 'legal' ]
	&& is_array( $byString ) === true && $byString['menu'] === [ 'attribute' => 'title' ] && array_key_exists( 'menu', (array) \Nino\Components::validate( 'menu-none', [ 'label' => 'None', 'template' => '<i></i>' ] ) ) === false );

$refused = [];

foreach( [
	'a word instead of the two forms'	=> 'legal',
	'a list'							=> [ 'menu' ],
	'nothing'							=> [],
	'both forms'						=> [ 'attribute' => 'menu', 'name' => 'legal' ],
	'a key that is none'				=> [ 'menus' => 'legal' ],
	'a name that is no number'			=> [ 'name' => 7 ],
	'an empty name'						=> [ 'name' => '' ],
	'a name in capitals'				=> [ 'name' => 'Legal' ],
	'a name with a blank'				=> [ 'name' => 'a b' ],
	'a name that is a fill'				=> [ 'name' => '[[x]]' ],
	'a name with a null byte'			=> [ 'name' => "le\0gal" ],
	'an attribute that is not there'	=> [ 'attribute' => 'nothing' ],
	'an attribute that is no word'		=> [ 'attribute' => [ 'menu' ] ],
	'an attribute that is a bool'		=> [ 'attribute' => 'open' ],
	'an attribute that is a text'		=> [ 'attribute' => 'words' ],
	'an attribute with no default'		=> [ 'attribute' => 'blank' ],
	'a default that is a fill'			=> [ 'attribute' => 'fill' ],
	'a default in capitals'				=> [ 'attribute' => 'upper' ],
] as $why => $menu )
	if( is_string( $schema( $menu ) ) === false )
		$refused[] = $why;

check( 'what is no menu is refused with a sentence that says so: a word, a list, both forms, a name that is empty, in capitals, spelt with a blank, a fill or a null byte, and an attribute that is not there, is a bool or a text, or has no menu for a default',
	$refused === [] && is_array( $schema( [ 'name' => str_repeat( 'a', 80 ) ] ) ) === true );

$codes = static function( string $source ) use ( &$project ): array {
	$notes = [];
	return array_map( static fn( array $findings ): array => array_column( $findings, 'code' ), \Nino\Components::check( $project, \Nino\Markup::parse( $source, \Nino\Components::containers( $project ), $notes ) ) );
};
check( 'check() finds a call that names a menu the choice does not offer, as it finds any value that is none of what its attribute takes - and none for one it offers or for a component that draws a menu of its own',
	$codes( '[crown-wrap][crown-nav menu="nope"][/crown-wrap]' ) === [ '0.0' => [ \Nino\Components::CODE_OUTSIDE ] ] && $codes( '[crown-wrap][crown-nav menu="legal"][/crown-wrap]' ) === [] && $codes( '[band-wrap][band-row][band-col][addon-legal][/band-col][/band-row][/band-wrap]' ) === [] );

// --- B. what the calls of a source draw --------------------------------------------------------------------------------

echo "\nComponents::menus() - the menus the calls of a source draw\n";

$menus = static fn( string $source ): array => \Nino\Components::menus( $project, $source );
check( 'a call names its menu by the attribute, takes the default of the attribute where it names none, and a component with a menu of its own draws that one, whatever else the call says',
	$menus( '[crown-nav menu="legal"]' ) === [ 'legal' ] && $menus( '[crown-nav]' ) === [ 'main' ] && $menus( '[foot-nav]' ) === [ 'footer' ] && $menus( '[addon-legal menu="footer"]' ) === [ 'legal' ] );
check( 'the menus of a source are the menus of every call in it, in the order they stand, each once, the calls inside the containers as well; text that holds no call and what is not written as a menu names none',
	$menus( '[foot-wrap][foot-col][foot-nav][foot-nav menu="legal"][/foot-col][crown-nav menu="legal"][addon-legal][/foot-wrap] [crown-nav menu="Legal"] \\[crown-nav menu="x"] [crown-nav menu="[[/x]]"]' ) === [ 'footer', 'legal' ]
	&& $menus( '' ) === [] && $menus( 'crown-nav menu="legal"' ) === [] );
$off = $project;
unset( $off[\Nino\Components::REGISTRY] );
check( 'a component that is not registered draws no menu: the feature that brings it is off, and its calls are names nobody registered',
	\Nino\Components::menus( $off, '[crown-nav menu="legal"][addon-legal]' ) === [] );

// --- C. the legal check -------------------------------------------------------------------------------------------------

echo "\nLegal::check() - the menu components of any feature\n";

$nav = static fn( array $findings ): array => array_values( array_filter( $findings, static fn( array $finding ): bool => $finding['kind'] === 'nav' ) );

\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '[foot-wrap][foot-col][foot-nav][/foot-col][/foot-wrap]' );
check( 'a menu component of a feature that names no menu outputs the menu it takes, which has not the legal pages: one finding for each page',
	count( $nav( \Nino\Modules\Legal::check( $project ) ) ) === 2 );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '[foot-wrap][foot-col][foot-nav menu="legal"][/foot-col][/foot-wrap]' );
check( '...and the one that names the menu legal outputs the menu that has them', \Nino\Modules\Legal::check( $project ) === [] );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '\[foot-nav menu="legal"] \[navigation nav="legal"] [navigation nav="legal" /]' );
check( 'a call written with \\[ is text, for the shortcode that draws a menu as for the components: it outputs none - one that is written without it does',
	count( $nav( \Nino\Modules\Legal::check( $project ) ) ) === 0 );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '\[foot-nav menu="legal"] \[navigation nav="legal"]' );
check( '...and with both written so, the pages stand in no menu a template outputs: one finding for each',
	count( $nav( \Nino\Modules\Legal::check( $project ) ) ) === 2 );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '[foot-wrap][foot-col][navigation nav="legal"][/navigation][/foot-col][/foot-wrap]' );
check( 'the shortcode of a menu counts where it stands inside a component, as the components do', \Nino\Modules\Legal::check( $project ) === [] );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '[foot-wrap][foot-col][foot-nav nav="legal"][/foot-col][/foot-wrap]' );
check( 'only the shortcode that draws a menu names it by nav: the attribute of another call is no menu',
	count( $nav( \Nino\Modules\Legal::check( $project ) ) ) === 2 );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '' );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-header.tpl', '[crown-wrap][crown-nav menu="legal"][/crown-wrap]' );
check( '...in the header as well', \Nino\Modules\Legal::check( $project ) === [] );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-header.tpl', '' );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '[foot-wrap][foot-col][addon-legal][/foot-col][/foot-wrap]' );
check( 'a component that draws the menu legal itself, whatever its call says, outputs it', \Nino\Modules\Legal::check( $project ) === [] );
\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '[foot-wrap][foot-col][foot-nav menu="nope"][/foot-col][/foot-wrap]' );
check( 'a call that names a menu the project has not outputs the menu of that name, which has not the pages: one finding for each page',
	count( $nav( \Nino\Modules\Legal::check( $project ) ) ) === 2 );

\Nino\Filesystem::putFileContent( $project, '/templates/frame-footer.tpl', '[foot-wrap][foot-col][foot-nav menu="legal"][addon-legal][/foot-col][/foot-wrap]' );
$without = $project;
unset( $without[\Nino\Components::REGISTRY] );
check( 'with the feature off its components draw no menu: one finding for each page, where the same templates have none while it is on',
	array_column( $nav( \Nino\Modules\Legal::check( $without ) ), 'kind' ) === [ 'nav', 'nav' ] && \Nino\Modules\Legal::check( $project ) === [] );

ninoDone( $project );
