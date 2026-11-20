<?php
declare(strict_types=1);

/**
 *	Nino
 *	basis-smoke.php	Contract test for the feature Basis - the twenty components in six groups that come with the kernel,
 *									switched off, and the standard part of each group:
 *
 *										A. the folders: twenty schemas read and accepted, each as clean as validate() makes it, the places they
 *										   offer, the six groups, the standard parts that keep the rule of mixing; the feature is delivered, listed,
 *										   off, with no script, and nothing in the kernel names it
 *										B. what every component draws: the classes of every value of every attribute, computed from the schema;
 *										   the markup of each one, the pictures, the menus, the links that are none; the fall-back rules
 *										C. the contract of group, part, ladder and token: the seven things a group keeps, the tokens that Nino.css
 *										   sets and the parts read, the parts in the bundle and what the hook of the parts does to them
 *										D. both states: where basis is off text, button, image, spacer and html are names nobody registered, where it
 *										   is on the register draws them; [title] is such a name in both
 *										E. what must never be read: a text, a field, an alt text, a caption and the title of a menu that spell a call,
 *										   a fill, a quote, a null byte, a megabyte; an address that is none
 *										F. the fixture page - all twenty components, cards, header and footer - drawn in both languages and compared
 *										   byte for byte with tests/fixtures/basis/page.<locale>.html
 *										G. the words of the feature, the activation, the sizes, the cost of a page of seven sections
 *
 *									Runs against an isolated sandbox directory; the kernel is the checkout's own, and the feature is the one
 *									the checkout delivers (_nino/Nino/Modules/Basis).
 *
 *	Usage: php tests/basis-smoke.php
 *				 php tests/basis-smoke.php --record=DIR	writes the drawn fixture pages into DIR
 */

// A project with no features of its own: the one feature of the run is the one the kernel delivers
define( 'NINO_FEATURES_DIR', sys_get_temp_dir(). '/nino-basis-features-'. getmypid() );
mkdir( NINO_FEATURES_DIR, 0755, true );
$GLOBALS['basisSandboxes'] = [];
register_shutdown_function( static function(): void {

	foreach( $GLOBALS['basisSandboxes'] as $sandbox )
		if( is_dir( $sandbox ) === true )
			\Nino\Filesystem::removeDir( $sandbox );

	@rmdir( NINO_FEATURES_DIR );
} );

require __DIR__. '/harness.php';

$notes			= [];
$basisDir		= dirname( __DIR__ ). '/_nino/Nino/Modules/Basis';
$fixtureDir	= __DIR__. '/fixtures/basis';
$record			= getopt( '', [ 'record:' ] )['record'] ?? null;

// What the feature is made of, by the order of the table in docs/features.md
const BASIS_COMPONENTS = [
	'header'	=> [ 'header-wrap', 'header-logo', 'header-nav', 'header-actions' ],
	'footer'	=> [ 'footer-wrap', 'footer-col', 'footer-logo', 'footer-nav', 'footer-legal' ],
	'section'	=> [ 'section-wrap', 'section-row', 'section-col', 'section-title', 'section-subtitle' ],
	'button'	=> [ 'button', 'button-group' ],
	'image'		=> [ 'image' ],
	'text'		=> [ 'text', 'spacer', 'html' ],
];

/**
 *	A project with basis switched on and a site to draw in: two languages, the menus main and footer, a few pages with a
 *	name in each language, a logo and a few pictures. As \Nino\init() registers it: the components are read from the folders
 *
 *	@param		string		$locale
 *	@param		bool			$on						Whether basis is in the list of the modules
 *
 *	@return 	array										$appData
 */
function basisSite( string $locale = 'de_DE', bool $on = true ): array {

	$appData = ninoSandbox( 'basis' );
	$GLOBALS['basisSandboxes'][] = ninoSandboxDir( $appData );
	$appData['./nino/locales/current'] = $locale;

	// The root of the site is the checkout: the standard parts are files of the kernel below it (/_nino/Nino/Modules/Basis/...), which the hook of the parts is
	// told to be there. Private and public stay in the sandbox
	$appData['./nino/filesystem/path'] = dirname( __DIR__ );
	$appData['/nino/modules'] = $on === true ? [ '\\Nino\\Modules\\Basis' ] : [];
	$appData['/nino/html/navs'] = [ 'main', 'footer', 'empty' ];

	// A page is a route with the menus it is in; its name is a text of each language
	$appData['/nino/http/routes'] = [
		'GET://'					=> [ 'uri' => '/home', 'navs' => [ 'main' => 1 ] ],
		'GET://contact'		=> [ 'uri' => '/contact', 'navs' => [ 'main' => 2, 'footer' => 1 ] ],
		'GET://about'			=> [ 'uri' => '/about', 'navs' => [ 'main' => 3, 'footer' => 2 ] ],
		'GET://imprint'		=> [ 'uri' => '/imprint', 'navs' => [ 'legal' => 1 ] ],
		'GET://privacy'		=> [ 'uri' => '/privacy', 'navs' => [ 'legal' => 2 ] ],
		'POST://send'			=> [ 'uri' => '/send', 'navs' => [ 'main' => 4 ] ],
	];

	// The pictures: the logo for a light and for a dark ground, and the places of the fixture page
	$slot = static fn( string $file, int $width, int $height, array $alt = [] ): array => [ 'label' => $file, 'width' => $width, 'height' => $height, 'filename' => $file, 'alt' => $alt ];
	$appData['/nino/html/images'] = [
		'/logo'												=> $slot( 'logo.png', 160, 48 ),
		'/logo/dark'									=> $slot( 'logo-dark.png', 160, 48 ),
		'/template/page-basis/hero/background'	=> $slot( 'hero.jpg', 1600, 900 ),
		'/template/page-basis/card-1/photo'		=> $slot( 'card.jpg', 800, 450, [ 'de_DE' => 'Ein Bild im Speicher', 'en_US' => 'A stored picture' ] ),
		'/template/page-basis/story/photo'		=> $slot( 'story.jpg', 800, 600 ),
		'/template/page-basis/picture/background'	=> $slot( 'picture.jpg', 1600, 900 ),
		'/nosize'											=> $slot( 'plain.png', 0, 0 ),
		'/empty'											=> [ 'label' => 'Empty', 'width' => 100, 'height' => 100, 'filename' => '', 'alt' => [] ],
	];

	// What the site says, in both languages: the words of the project, of the page and of the feature
	$words = include __DIR__. '/fixtures/basis/text.php';

	foreach( [ 'de_DE', 'en_US' ] as $language ) {

		$fills = $words[$language];

		foreach( (array) include __DIR__. '/../_nino/Nino/Modules/Basis/install/text/'. $language. '.php' as $key => $text )
			$fills[trim( (string) $key, '[]' )] = $text;

		\Nino\Html::addFills( $appData, $fills, $language );
	}

	\Nino\Html::addFills( $appData, [ '/nino/date/year' => '2026' ], '*' );

	// As \Nino\init() does it: the register first, then the standard parts
	\Nino\Features::registerComponents( $appData );
	\Nino\Features::registerParts( $appData );

	return $appData;
}

/**
 *	A source drawn the way a page is, in the template page-basis
 *
 *	@param		array 		&$appData			(reference) A project of basisSite()
 *	@param		string		$source				html+
 *	@param		array			$context			More of the context the nodes are drawn in
 *
 *	@return 	string
 */
function basisDraw( array &$appData, string $source, array $context = [] ): string {

	$notes = [];

	return \Nino\Components::render( $appData, \Nino\Markup::parse( $source, \Nino\Components::containers( $appData ), $notes ), $context + [ 'template' => 'page-basis' ] );
}


/**
 *	One call of a component as the smallest thing that draws: a container with nothing in it, or a leaf with the word it reads
 *
 *	@param		string		$name
 *	@param		array			$schema				The clean schema
 *	@param		string		$attributes		What stands after the name, '' or ' size="m"'
 *
 *	@return 	string
 */
function basisCall( string $name, array $schema, string $attributes ): string {

	$source = $schema['source'] === 'image' ? ' /logo' : ( $schema['source'] === 'text' ? ' text="Hi"' : '' );

	return isset( $schema['offers'] ) === true ? '['. $name. $source. $attributes. ']'. ( $name === 'html' ? '<p>x</p>' : '' ). '[/'. $name. ']' : '['. $name. $source. $attributes. ' /]';
}

/**
 *	The classes a call writes, worked out of the schema: the name, then for each attribute that is a class a choice that is not
 *	its default (name--attribute-value), a bool that is on (name--attribute), and for a responsive choice the value for every
 *	width and each breakpoint that is named (name--m-attribute-value)
 *
 *	@param		string		$name
 *	@param		array			$schema
 *	@param		array			$given				attribute => the word written, for a bool true
 *
 *	@return 	string
 */
function basisClasses( string $name, array $schema, array $given ): string {

	$class = $name;

	foreach( $schema['attributes'] as $attribute => $declared ) {

		if( $declared['class'] !== true || isset( $given[$attribute] ) === false )
			continue;

		if( $declared['type'] === 'bool' )
			$class .= ' '. $name. '--'. $attribute;
		else if( ( $declared['responsive'] ?? false ) === true ) {

			foreach( explode( ' ', (string) $given[$attribute] ) as $index => $part ) {

				[ $width, $value ] = str_contains( $part, ':' ) === true ? explode( ':', $part, 2 ) : [ '', $part ];

				if( $width === '' && $value !== $declared['default'] )
					$class .= ' '. $name. '--'. $attribute. '-'. $value;
				else if( $width !== '' )
					$class .= ' '. $name. '--'. $width. '-'. $attribute. '-'. $value;
			}
		}
		else if( $given[$attribute] !== $declared['default'] )
			$class .= ' '. $name. '--'. $attribute. '-'. $given[$attribute];
	}

	return $class;
}

/**	The classes of the first element a drawing writes	*/
function basisFirstClass( string $html ): string {
	return preg_match( '/class="([^"]*)"/', $html, $found ) === 1 ? $found[1] : '';
}

/**	The table of the components docs/features.md has, worked out of the registered schemas	*/
function basisTable( array $schemas ): string {

	$rows = "| Group | Component | In | Holds | Source | Renderer | Classes next to the name | Tokens of the template |\n|---|---|---|---|---|---|---|---|\n";
	$last = '';

	foreach( BASIS_COMPONENTS as $group => $names )
		foreach( $names as $name ) {

			$schema = $schemas[$name];
			$classes = [];
			$tokens = [];

			foreach( $schema['attributes'] as $attribute => $declared ) {

				if( $declared['class'] === true && $declared['type'] === 'choice' ) {

					$responsive = ( $declared['responsive'] ?? false ) === true;
					$values = $responsive === true ? $declared['options'] : array_values( array_diff( $declared['options'], [ $declared['default'] ] ) );
					$classes[] = '`--'. ( $responsive === true ? '[m-\|l-]' : '' ). $attribute. '-{'. implode( ',', $values ). '}`';
				}
				else if( $declared['class'] === true )
					$classes[] = '`--'. $attribute. '`';
				else
					$tokens[] = '`[['. ( $declared['type'] === 'id' ? 'id' : $attribute ). ']]`';
			}

			foreach( array_diff( $schema['variants'], [ 'regular' ] ) as $variant )
				$classes[] = '`--variant-'. $variant. '`';

			$rows .= '| '. ( $group === $last ? '' : '**'. $group. '**' ). ' | `'. $name. '`'. ( $schema['once'] === true ? ' (once)' : '' ). ' | '. implode( ', ', $schema['in'] ). ' | '
				. ( isset( $schema['offers'] ) === true ? ( $schema['offers'] === [] ? 'itself' : 'itself, '. implode( ', ', $schema['offers'] ) ) : '- (a leaf)' ). ' | '
				. $schema['source']. ( isset( $schema['format'] ) === true ? '/'. $schema['format'] : '' ). ' | '. ( is_callable( [ '\\Nino\\Modules\\Basis', 'component'. str_replace( ' ', '', ucwords( str_replace( '-', ' ', $name ) ) ) ] ) === true ? 'yes' : '-' ). ' | '
				. ( implode( ' ', $classes ) ?: '-' ). ' | '. ( implode( ' ', $tokens ) ?: '-' ). " |\n";
			$last = $group;
		}

	return $rows;
}

/**
 *	The rules of a css text, the ones inside @media and @supports among them: [ 'at' => the prelude of the at-rule they stand in, '' for none,
 *	'selector' => as written with one space for each run of white space, 'body' => the declarations ]. Comments are left out
 *
 *	@param		string		$css
 *
 *	@return 	array
 */
function basisRules( string $css ): array {

	$css = (string) preg_replace( '#/\*.*?\*/#s', '', $css );
	$rules = [];
	$stack = [];
	$buffer = '';

	for( $at = 0, $length = strlen( $css ); $at < $length; $at++ ) {

		$char = $css[$at];

		if( $char === '{' ) {

			$prelude = trim( (string) preg_replace( '/\s+/', ' ', $buffer ) );
			$stack[] = [ $prelude, $at + 1 ];
			$buffer = '';
		}
		else if( $char === '}' ) {

			[ $prelude, $from ] = array_pop( $stack );
			$inner = substr( $css, $from, $at - $from );

			if( str_contains( $inner, '{' ) === false )
				$rules[] = [ 'at' => implode( ' ', array_column( $stack, 0 ) ), 'selector' => $prelude, 'body' => trim( (string) preg_replace( '/\s+/', ' ', $inner ) ) ];

			$buffer = '';
		}
		else
			$buffer .= $char;
	}

	return $rules;
}

/**	The rules of a text whose selector holds a word: the declarations of each, joined	*/
function basisBody( array $rules, string $word, string $at = '' ): string {

	$bodies = [];

	foreach( $rules as $rule )
		if( $rule['at'] === $at && str_contains( $rule['selector'], $word ) === true )
			$bodies[] = $rule['body'];

	return implode( ' ', $bodies );
}

/**	The contrast of two colours given as [ r, g, b ], 1 to 21	*/
function basisContrast( array $one, array $two ): float {

	$light = static function( array $colour ): float {

		$channels = array_map( static function( float $value ): float {

			$value /= 255;

			return $value <= 0.03928 ? $value / 12.92 : ( ( $value + 0.055 ) / 1.055 ) ** 2.4;
		}, $colour );

		return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
	};

	$a = $light( $one );
	$b = $light( $two );

	return ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
}

if( in_array( '--table', $argv, true ) === true ) {

	$appData = basisSite();
	echo basisTable( \Nino\Components::components( $appData ) );
	exit( 0 );
}


// --- A. The folders -----------------------------------------------------------------------------------

echo "A. The folders of the feature: schemas, groups, parts\n";

$read = \Nino\Features::readComponents( $basisDir, 'basis' );
check( 'the folders are read and nothing is refused: no component, no group and no standard part', is_array( $read ) === true && $read['rejected'] === [] && count( $read['groups'] ) === 6
	&& array_column( $read['groups'], 'part' ) === [ true, true, true, true, true, true ] );

$names = array_merge( ...array_values( BASIS_COMPONENTS ) );
sort( $names );
$have = array_keys( $read['components'] );
sort( $have );
check( 'twenty components in six groups: header, footer, section, button, image and text', count( $have ) === 20 && $have === $names
	&& array_keys( $read['groups'] ) !== [] && count( $read['groups'] ) === 6 );

$members = [];
foreach( $read['components'] as $name => $clean )
	$members[$clean['group']][] = $name;
$byGroup = [];
foreach( BASIS_COMPONENTS as $group => $list ) {
	sort( $list );
	$byGroup[$group] = $list;
}
ksort( $byGroup );
ksort( $members );
foreach( $members as &$list )
	sort( $list );
unset( $list );
check( 'every component names its group, and the groups hold what the table says', $members === $byGroup );

$unstable = [];
foreach( $read['components'] as $name => $clean )
	if( \Nino\Components::validate( $name, $clean ) !== $clean )
		$unstable[] = $name;
check( 'validate() accepts every clean schema as it is, unchanged'. ( $unstable === [] ? '' : ' - '. implode( ', ', $unstable ) ), $unstable === [] );

$appData = basisSite();
$schemas = \Nino\Components::components( $appData );
check( 'switched on, the register holds the twenty and the six groups', count( $schemas ) === 20 && count( \Nino\Components::groups( $appData ) ) === 6
	&& array_keys( array_diff_key( \Nino\Components::groups( $appData ), array_flip( [ 'button', 'footer', 'header', 'image', 'section', 'text' ] ) ) ) === [] );

// every choice of the twenty whose options are a list names each option in both languages, and the schema says so by itself: nothing here lists a word (a list a method answers brings its own labels)
$unlabelled = [];

foreach( $schemas as $name => $schema )
	foreach( $schema['attributes'] as $attribute => $declared )
		if( $declared['type'] === 'choice' && is_array( $declared['options'] ) === true && isset( $declared['optionLabels'] ) === false )
			foreach( $declared['options'] as $option )
				foreach( [ 'de_DE', 'en_US' ] as $language )
					if( is_array( $declared['labels'][$option] ?? null ) === false || is_string( $declared['labels'][$option][$language] ?? null ) === false || $declared['labels'][$option][$language] === '' )
						$unlabelled[] = $name. '.'. $attribute. '='. $option. ' ('. $language. ')';

check( 'every option of every choice of basis has a label in de_DE and en_US'. ( $unlabelled === [] ? '' : ' - '. implode( ', ', array_slice( $unlabelled, 0, 5 ) ) ), $unlabelled === [] );

// ...and so does every variant a component of basis has beside the regular one, as the register answers it
$unnamed = [];

foreach( array_keys( $schemas ) as $name )
	foreach( \Nino\Components::variants( $appData, $name )['variants'] as $variant => $label )
		if( $variant !== 'regular' )
			foreach( [ 'de_DE', 'en_US' ] as $language )
				if( is_array( $label ) === false || is_string( $label[$language] ?? null ) === false || $label[$language] === '' )
					$unnamed[] = $name. '.'. $variant. ' ('. $language. ')';

check( 'every variant of basis but the regular one has a label in de_DE and en_US'. ( $unnamed === [] ? '' : ' - '. implode( ', ', $unnamed ) ), $unnamed === [] && array_key_exists( 'card', \Nino\Components::variants( $appData, 'section-col' )['variants'] ) === true );

// every component of basis names an icon, and it is one the workbench draws: the table of icon() in the script is the table of the register, name for name
$unpictured = [];

foreach( $schemas as $name => $schema )
	if( is_string( $schema['icon'] ?? null ) === false || in_array( $schema['icon'], \Nino\Components::ICONS, true ) === false )
		$unpictured[] = $name;

check( 'every component of basis names an icon of the table'. ( $unpictured === [] ? '' : ' - '. implode( ', ', $unpictured ) ), $unpictured === [] );

preg_match( '/const ICONS = \{\n(.*?)\n\t\};/s', (string) file_get_contents( dirname( __DIR__ ). '/_admin/assets/Nino.admin.js' ), $table );
$drawn = array_map( static fn( string $line ): string => trim( explode( ' :', trim( $line ), 2 )[0], "'" ), explode( "\n", $table[1] ?? '' ) );
sort( $drawn );
$named = \Nino\Components::ICONS;
sort( $named );

check( 'the icons the register accepts are the icons the script of the workbench draws, name for name', $drawn !== [ '' ] && $drawn === $named );

// what a tool offers where: the nine that hold something
$holds = [
	''							=> [ 'footer-wrap', 'header-wrap', 'html', 'section-wrap' ],
	'header-wrap'		=> [ 'header-actions', 'header-logo', 'header-nav' ],
	'section-wrap'	=> [ 'section-row' ],
	'section-row'		=> [ 'section-col' ],
	'section-col'		=> [ 'button', 'button-group', 'html', 'image', 'section-subtitle', 'section-title', 'spacer', 'text' ],
	'footer-wrap'		=> [ 'footer-col', 'footer-legal' ],
	'footer-col'		=> [ 'button', 'button-group', 'footer-logo', 'footer-nav', 'html', 'image', 'spacer', 'text' ],
	'button-group'	=> [ 'button' ],
	'html'					=> [],
	'header-actions'	=> [ 'button', 'button-group', 'html', 'image', 'spacer', 'text' ],
];
$wrong = [];
foreach( $holds as $parent => $expected ) {
	$allowed = \Nino\Components::allowed( $appData, $parent );
	sort( $allowed );
	if( $allowed !== $expected )
		$wrong[] = $parent;
}
// A name is a component or a shortcode and never both, where every module of the kernel is at work and basis is on: the register would draw the one and never ask the other
$both = basisSite( 'de_DE' );
ninoWarnings();
\Nino\Html::init( $both );

foreach( (array) glob( dirname( __DIR__ ). '/_nino/Nino/Modules/*', GLOB_ONLYDIR ) as $moduleDir )
	if( method_exists( '\\Nino\\Modules\\'. basename( $moduleDir ), 'init' ) === true )
		( '\\Nino\\Modules\\'. basename( $moduleDir ) )::init( $both );

check( 'the modules of the kernel and the components of basis share no name: every shortcode is one the register does not draw, and the registrations are no warning',
	count( \Nino\Html::shortcodes( $both ) ) >= 13 && array_intersect( \Nino\Html::shortcodes( $both ), array_keys( \Nino\Components::components( $both ) ) ) === [] && ninoWarnings() === [] );

check( 'allowed() answers for the nine components that hold something, and for the page, what each may hold - no row in a column, no column in a column'. ( $wrong === [] ? '' : ' - '. implode( ', ', $wrong ) ), $wrong === [] );
$containers = array_keys( array_filter( \Nino\Components::containers( $appData ) ) );
sort( $containers );
check( 'the containers are the nine', $containers === [ 'button-group', 'footer-col', 'footer-wrap', 'header-actions', 'header-wrap', 'html', 'section-col', 'section-row', 'section-wrap' ] );
$once = array_keys( array_filter( array_map( static fn( array $schema ): bool => $schema['once'], $schemas ) ) );
sort( $once );
check( 'header-logo, header-nav and footer-legal are once, and the column has the variant card', $once === [ 'footer-legal', 'header-logo', 'header-nav' ]
	&& $schemas['section-col']['variants'] === [ 'regular', 'card' ] );

$renderers = array_keys( (array) $appData[\Nino\Components::RENDERERS] );
sort( $renderers );
check( 'ten of the twenty have a renderer, the ones that compute; the others are drawn from their template alone', $renderers === [ 'button', 'footer-legal', 'footer-logo', 'footer-nav', 'header-logo', 'header-nav', 'html', 'image', 'section-title', 'section-wrap' ] );

// the feature: delivered, listed, off, with no script, and nothing of the kernel names it
$manifestWarnings = ninoWarnings();
$all = \Nino\Features::all( $appData );
check( 'the feature is delivered with the kernel: listed with shipped true, off, its class the module of its directory, its manifest read with no warning', isset( $all['basis'] ) === true && $all['basis']['shipped'] === true
	&& $all['basis']['module'] === '\\Nino\\Modules\\Basis' && $all['basis']['dir'] === $basisDir && $all['basis']['problems'] === [] && $manifestWarnings === [] );
$fresh = ninoSandbox( 'basis-fresh' );
check( 'it is off on a fresh project, and the wizard does not list it', in_array( '\\Nino\\Modules\\Basis', \Nino\AppData::DEFAULTS['/nino/modules'] ?? [], true ) === false
	&& \Nino\Features::get( $fresh, 'basis' )['active'] === false && is_file( $basisDir. '/install/manifest.php' ) === false );
\Nino\Filesystem::removeDir( ninoSandboxDir( $fresh ) );

$scripts = [];
$mentions = [];
foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__ ). '/_nino', \FilesystemIterator::SKIP_DOTS ) ) as $file ) {

	if( str_starts_with( $file->getPathname(), $basisDir. '/' ) === true )
		$scripts[] = $file->getExtension() === 'js' ? $file->getPathname() : null;
	else if( $file->getExtension() === 'php' && preg_match( '/Modules\\\\+Basis\b/', (string) file_get_contents( $file->getPathname() ) ) === 1 )
		$mentions[] = $file->getPathname();
}
foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( __DIR__ ). '/_admin', \FilesystemIterator::SKIP_DOTS ) ) as $file )
	if( $file->getExtension() === 'php' && preg_match( '/Modules\\\\+Basis\b/', (string) file_get_contents( $file->getPathname() ) ) === 1 )
		$mentions[] = $file->getPathname();
check( 'no file of the feature is a script, and no file of the kernel or of the workbench names the class: nothing uses it'. ( $mentions === [] ? '' : ' - '. implode( ', ', $mentions ) ), array_filter( $scripts ) === [] && $mentions === [] );

// the cache of the register: a request that finds it as it was includes no component.php and no group.php - a process of its own says what it included
$warm = ninoSandbox( 'basis-warm' );
$warmDir = ninoSandboxDir( $warm );
$request = static function() use ( $warmDir ): array {

	$script = sys_get_temp_dir(). '/nino-basis-warm-'. getmypid(). '.php';
	file_put_contents( $script, '<?php define( "NINO_FEATURES_DIR", '. var_export( NINO_FEATURES_DIR, true ). ' ); require '. var_export( __DIR__. '/harness.php', true ). ';
		$a = [ "./nino/uid" => '. var_export( $warmDir, true ). ' ]; \\Nino\\AppData::prepare( $a );
		$a["./nino/filesystem/path"] = '. var_export( dirname( __DIR__ ), true ). '; $a["./nino/filesystem/configpath"] = $a["./nino/filesystem/contentpath"] = '. var_export( $warmDir. '/private', true ). '; $a["./nino/filesystem/publicpath"] = '. var_export( $warmDir. '/public', true ). ';
		$a["/nino/modules"] = [ "\\\\Nino\\\\Modules\\\\Basis" ]; $a["/nino/dir"] = ""; $a["./nino/locales/current"] = "de_DE"; $a["/nino/locales/native"] = "de_DE"; $a["/nino/locales/available"] = [ "de_DE", "en_US" ];
		\\Nino\\Features::registerComponents( $a );
		echo json_encode( [ count( \\Nino\\Components::components( $a ) ), count( \\Nino\\Components::groups( $a ) ), array_values( array_filter( get_included_files(), static fn( string $file ): bool => preg_match( "#/Modules/Basis/(components/[^/]+/component|groups/[^/]+/group)\\.php$#", $file ) === 1 ) ) ] );' );
	$out = (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $script ). ' 2>&1' );
	unlink( $script );

	return (array) json_decode( $out, true );
};
$first = $request();
$second = $request();
check( 'the first request reads the twenty component.php and the six group.php and writes the cache of the register; the second registers the same from it and includes none of them', ( $first[0] ?? 0 ) === 20 && ( $first[1] ?? 0 ) === 6 && count( $first[2] ?? [] ) === 26
	&& is_file( $warmDir. '/private/data/cache/register/basis.php' ) === true && ( $second[0] ?? 0 ) === 20 && ( $second[1] ?? 0 ) === 6 && ( $second[2] ?? [ 'x' ] ) === [] );
\Nino\Filesystem::removeDir( $warmDir );

// --- B. What every component draws ----------------------------------------------------------------

echo "\nB. What every component draws\n";

$classProblems = [];
$cases = 0;

foreach( $schemas as $name => $schema ) {

	foreach( $schema['attributes'] as $attribute => $declared ) {

		if( $declared['class'] !== true )
			continue;

		$tries = [];

		if( $declared['type'] === 'bool' )
			$tries[] = [ ' '. $attribute, true ];
		else if( ( $declared['responsive'] ?? false ) === true ) {

			foreach( $declared['options'] as $value )
				$tries[] = [ ' '. $attribute. '="'. $value. '"', $value ];

			$tries[] = [ ' '. $attribute. '="'. $declared['options'][0]. ' m:'. end( $declared['options'] ). ' l:'. $declared['options'][1]. '"', $declared['options'][0]. ' m:'. end( $declared['options'] ). ' l:'. $declared['options'][1] ];
		}
		else
			foreach( $declared['options'] as $value )
				$tries[] = [ ' '. $attribute. '="'. $value. '"', $value ];

		foreach( $tries as [ $written, $value ] ) {

			$cases++;
			$html = basisDraw( $appData, basisCall( $name, $schema, $written ) );
			$expected = basisClasses( $name, $schema, [ $attribute => $value ] );

			if( basisFirstClass( $html ) !== $expected )
				$classProblems[] = $name. $written. ' is '. basisFirstClass( $html ). ', not '. $expected;
		}
	}

	foreach( array_diff( $schema['variants'], [ 'regular' ] ) as $variant ) {

		$cases++;
		$html = basisDraw( $appData, basisCall( $name, $schema, ' variant="'. $variant. '"' ) );

		if( basisFirstClass( $html ) !== $name. ' '. $name. '--variant-'. $variant )
			$classProblems[] = $name. ' variant '. $variant;
	}

	// nothing written: the name alone
	if( basisFirstClass( basisDraw( $appData, basisCall( $name, $schema, '' ) ) ) !== $name )
		$classProblems[] = $name. ' with nothing written';
}
check( 'every component with every value of every attribute that is a class, and every variant, writes the classes the schema says ('. $cases. ' calls)'. ( $classProblems === [] ? '' : ' - '. implode( '; ', array_slice( $classProblems, 0, 5 ) ) ), $classProblems === [] && $cases > 100 );
ninoWarnings();

// the markup each one writes, by what it is for
$draw = static fn( string $source ): string => basisDraw( $appData, $source );

check( 'a section is a section with an id, a row a div, a column a div that may hold a card', $draw( '[section-wrap id="a" ground="alt"][section-row][section-col variant="card"]x[/section-col][/section-row][/section-wrap]' )
	=== '<section id="a" class="section-wrap section-wrap--ground-alt"><div class="section-row"><div class="section-col section-col--variant-card">x</div></div></section>' );
check( 'a title is the element its tag says, in a link where it has an address that is one; the subtitle is a paragraph', $draw( '[section-title text="T" tag="h3" size="s" href="page:/contact"]' ) === '<h3 class="section-title section-title--size-s"><a href="/contact">T</a></h3>'
	&& $draw( '[section-title text="T" tag="h4"]' ) === '<h4 class="section-title">T</h4>' && $draw( '[section-subtitle text="S"]' ) === '<p class="section-subtitle">S</p>' );
check( 'a title with a tag that is none of the list is the default', $draw( '[section-title text="T" tag="script"]' ) === '<h2 class="section-title">T</h2>' );
check( 'a button is a link, opens in a new window with rel where it says so, and is a span of the same classes without an address', $draw( '[button text="Go" href="/x" size="l" tone="quiet" wide]' ) === '<a class="button button--tone-quiet button--size-l button--wide" href="/x">Go</a>'
	&& $draw( '[button text="Go" href="https://example.test/" target="blank"]' ) === '<a class="button" href="https://example.test/" target="_blank" rel="noopener">Go</a>' && $draw( '[button text="Go"]' ) === '<span class="button">Go</span>' );
check( 'a text keeps its paragraphs and lists, its format is the one the call says', $draw( '[text text="<p>a</p><ul><li>b</li></ul><script>x()</script>"]' ) === '<div class="text"><p>a</p><ul><li>b</li></ul></div>'
	&& $draw( '[text text="a b" format="inline" size="l"]' ) === '<div class="text text--size-l">a b</div>' );
check( 'a spacer is empty and hidden from a screen reader; a group of buttons is a div', $draw( '[spacer size="4"]' ) === '<div class="spacer spacer--size-4" aria-hidden="true"></div>'
	&& $draw( '[button-group align="end"][button text="A" href="/a"][/button-group]' ) === '<div class="button-group button-group--align-end"><a class="button" href="/a">A</a></div>' );
check( 'html is what is left of its markup after it is cleaned: paragraphs, lists and the inline tags; nothing, where nothing is left', $draw( '[html]<p>Hi <b>x</b></p><script>alert(1)</script><iframe src="x"></iframe><ul><li onclick="x()">a</li></ul>[/html]' ) === '<div class="html"><p>Hi x</p><ul><li>a</li></ul></div>'
	&& $draw( '[html]<script>alert(1)</script>[/html]' ) === '' && $draw( '[html]<p>a \[b] and \[c]</p>[/html]' ) === '<div class="html"><p>a [b] and [c]</p></div>' );
$brackets = basisSite();
\Nino\Html::addFills( $brackets, [ '/template/page-basis/ev/br' => 'a &#91;x&#93; &#91;&#91;y&#93;&#93;' ], '*' );
check( 'the brackets of the markup stay what they were written as, and a bracket that a fill spells as an entity stays an entity: it is text and is read by nobody', basisDraw( $brackets, '[html]<p>[[/template/page-basis/ev/br]] \[z]</p>[/html]' ) === '<div class="html"><p>a &#91;x&#93; &#91;&#91;y&#93;&#93; [z]</p></div>' );

// pictures
check( 'an image is the figure of the picture of a slot: its size, lazy, the alt text of the call before the one stored for the language - and none where the call says decorative - a caption, a link', $draw( '[image /template/page-basis/card-1/photo alt="Call" caption="Cap" ratio="4-3" href="/x"]' )
	=== '<figure class="image image--ratio-4-3"><a href="/x"><img src="/public/images/card.jpg" width="800" height="450" alt="Call" loading="lazy" decoding="async"></a><figcaption>Cap</figcaption></figure>'
	&& str_contains( $draw( '[image /template/page-basis/card-1/photo]' ), ' alt="Ein Bild im Speicher" ' ) && str_contains( $draw( '[image /template/page-basis/card-1/photo decorative]' ), ' alt="" ' ) && str_contains( $draw( '[image /template/page-basis/card-1/photo decorative alt="Call"]' ), ' alt="Call" ' )
	&& $draw( '[image /logo alt="Call"]' ) === '<figure class="image"><img src="/public/images/logo.png" width="160" height="48" alt="Call" loading="lazy" decoding="async"></figure>' );
// a picture fitted or of a fixed height, a row that stands in the middle, columns of a fifth and a sixth: each is a class of its own, and a value the schema does not list is no class
check( 'an image takes fit (a bool) and a fixed height (a choice of four steps): each is a class of the figure and nothing else of the markup, and a height outside the steps is none',
	$schemas['image']['attributes']['fit']['type'] === 'bool' && $schemas['image']['attributes']['fit']['default'] === false
	&& $schemas['image']['attributes']['height']['options'] === [ 'auto', '1', '2', '3', '4' ] && $schemas['image']['attributes']['height']['default'] === 'auto'
	&& $draw( '[image /logo decorative fit height="3" ratio="16-9"]' ) === '<figure class="image image--ratio-16-9 image--fit image--height-3"><img src="/public/images/logo.png" width="160" height="48" alt="" loading="lazy" decoding="async"></figure>'
	&& $draw( '[image /logo decorative height="9" fit="no"]' ) === $draw( '[image /logo decorative]' ) && $draw( '[image /logo decorative height="auto"]' ) === '<figure class="image"><img src="/public/images/logo.png" width="160" height="48" alt="" loading="lazy" decoding="async"></figure>' );
check( 'a row takes where its columns stand when they do not fill it, and a column takes a fifth and a sixth of the row at every width; the default of each is the markup of a row and a column that say nothing',
	$schemas['section-row']['attributes']['halign']['options'] === [ 'start', 'center', 'end', 'between' ] && $schemas['section-row']['attributes']['halign']['default'] === 'start'
	&& $draw( '[section-row halign="between"][section-col size="16 m:20 l:25"][/section-col][/section-row]' ) === '<div class="section-row section-row--halign-between"><div class="section-col section-col--size-16 section-col--m-size-20 section-col--l-size-25"></div></div>'
	&& $draw( '[section-row halign="start"][section-col size="17"][/section-col][/section-row]' ) === '<div class="section-row"><div class="section-col"></div></div>' );
$noticed = ninoWarnings();
$anchored = $draw( '[image /template/page-basis/story/photo href="/x"][image /template/page-basis/story/photo href="/y" decorative][image /template/page-basis/story/photo alt="Named" href="/z"]' );
$said = ninoWarnings();
check( 'a picture with no alt text is no link: without a name the anchor would be one nobody can use, so it is left out, and the log says it once for the picture', substr_count( $anchored, '<a ' ) === 1 && str_contains( $anchored, '<a href="/z">' ) && count( $said ) === 1 && str_contains( $said[0], 'no alt text' ) && str_contains( $said[0], 'story/photo' ) );
check( 'a picture whose size is not known has no width and no height: a box of nothing is no size', $draw( '[image /nosize]' ) === '<figure class="image"><img src="/public/images/plain.png" alt="" loading="lazy" decoding="async"></figure>' );
// In a loop the fields of the round are in the words of a call: a button, an image, a title, a text and the name of a menu
$loopContext = [ 'fills' => [ '[[title]]' => 'Ärzte &amp; &quot;Co&quot; &#91;x]', '[[.slug]]' => 'aerzte', '[[.id]]' => '2' ], 'element' => [ 'uri' => '/x', 'id' => 2, 'element' => [ '.uri' => '/x/aerzte' ], 'model' => [] ] ];
check( 'in a loop the fields of the round are put into the text of a button, its link, the alt text and the caption of an image, a title, a text and the name of a menu - escaped as the text they are',
	basisDraw( $appData, '[button text="Mehr zu [[title]]" href="/leistungen/[[.slug]]"]', $loopContext ) === '<a class="button" href="/leistungen/aerzte">Mehr zu Ärzte &amp; &quot;Co&quot; [x]</a>'
	&& basisDraw( $appData, '[image /logo alt="[[title]]" caption="Nr. [[.id]]: [[title]]" href="/p/[[.slug]]"]', $loopContext ) === '<figure class="image"><a href="/p/aerzte"><img src="/public/images/logo.png" width="160" height="48" alt="Ärzte &amp; &quot;Co&quot; [x]" loading="lazy" decoding="async"></a><figcaption>Nr. 2: Ärzte &amp; &quot;Co&quot; [x]</figcaption></figure>'
	&& basisDraw( $appData, '[section-title text="[[title]]"][text text="[[title]] [[.slug]]"]', $loopContext ) === '<h2 class="section-title">Ärzte &amp; "Co" [x]</h2><div class="text"><p>Ärzte &amp; "Co" [x] aerzte</p></div>'
	&& str_contains( basisDraw( $appData, '[footer-nav label="[[title]]"]', $loopContext ), '<nav class="footer-nav" aria-label="Ärzte &amp; &quot;Co&quot; [x]">' ) === true );
$pageRound = static fn( string $v ): array => [ 'fills' => [ '[[v]]' => $v, '[[.slug]]' => 'holz' ], 'element' => [ 'uri' => '/x', 'id' => 0, 'element' => [ '.uri' => '/x/holz' ], 'model' => [] ] ];
$pageSite = $appData;
$pageSite['/nino/http/routes']['GET://leistungen/holz'] = [ 'uri' => '/leistungen/holz' ];
check( 'a field after page: is the path of a page of the site, checked as one: a routed page is the link, a page nobody routes and a way out of the site are none',
	basisDraw( $pageSite, '[button text="Holz" href="page:[[v]]"]', $pageRound( '/leistungen/holz' ) ) === '<a class="button" href="/leistungen/holz">Holz</a>'
	&& basisDraw( $pageSite, '[button text="Holz" href="page:/leistungen/[[.slug]]#top"]', $pageRound( '/' ) ) === '<a class="button" href="/leistungen/holz#top">Holz</a>'
	&& basisDraw( $pageSite, '[button text="Holz" href="page:[[v]]"]', $pageRound( '/nowhere' ) ) === '<span class="button">Holz</span>'
	&& basisDraw( $pageSite, '[button text="Holz" href="page:[[v]]"]', $pageRound( '/leistungen/../contact' ) ) === '<span class="button">Holz</span>' );
// The picture of an image field of the element of a loop: the call names the field, the model of the field names the field that holds its alt text
$photoModel = [ 'title' => [ 'type' => 'string' ], 'photo' => [ 'type' => 'image', 'alt' => 'photoAlt' ], 'photoAlt' => [ 'type' => 'string' ], 'empty' => [ 'type' => 'image' ] ];
$photoRound = static fn( string $file, string $alt = 'Holz & "Stein"' ): array => [ 'fills' => [ '[[title]]' => 'Holz' ], 'element' => [ 'uri' => '/x', 'id' => 0, 'element' => [ '.uri' => '/x/holz', 'title' => 'Holz', 'photo' => $file, 'photoAlt' => $alt ], 'model' => $photoModel ] ];
$photoCalls = '[image .photo][footer-logo .photo][section-wrap image=".photo"]x[/section-wrap][image .photo alt="[[title]]"]';
check( 'the picture of an image field of a loop is drawn by an image, a logo and the picture behind a section: the file the field holds, the alt text of the field named by its model and the one of the call before it, none behind a section',
	basisDraw( $appData, $photoCalls, $photoRound( 'elements/h/holz.jpg' ) ) === '<figure class="image"><img src="/public/images/elements/h/holz.jpg" alt="Holz &amp; &quot;Stein&quot;" loading="lazy" decoding="async"></figure>'
	. '<span class="footer-logo"><img src="/public/images/elements/h/holz.jpg" alt="Holz &amp; &quot;Stein&quot;" loading="lazy" decoding="async"></span>'
	. '<section class="section-wrap"><div class="section-wrap-image"><img class="section-wrap-picture" src="/public/images/elements/h/holz.jpg" alt="" decoding="async"></div>x</section>'
	. '<figure class="image"><img src="/public/images/elements/h/holz.jpg" alt="Holz" loading="lazy" decoding="async"></figure>'
	&& basisDraw( $appData, '[image .empty][image .title][image .nothing]', $photoRound( 'elements/h/holz.jpg' ) ) === '' && basisDraw( $appData, '[image .photo]', $photoRound( '' ) ) === '' );
$hostilePhotos = [];
ninoWarnings();

foreach( [ '../../private/config.php', '/etc/passwd', 'a/../b.jpg', '"><script>alert(1)</script>.jpg', '[template /templates/evil].jpg', "a\0b.jpg", "a\x01b.jpg" ] as $hostileFile )
	$hostilePhotos[$hostileFile] = basisDraw( $appData, $photoCalls, $photoRound( $hostileFile, '[template /x] "><b>' ) );

check( 'a file name in an image field that climbs out of the directory, is absolute, spells a tag or a call or holds a control character draws no picture of the site and nothing that runs: no path out, no tag, no call read',
	$hostilePhotos['../../private/config.php'] === '<section class="section-wrap">x</section>' && $hostilePhotos['/etc/passwd'] === '<section class="section-wrap">x</section>' && $hostilePhotos['a/../b.jpg'] === '<section class="section-wrap">x</section>'
	&& $hostilePhotos["a\0b.jpg"] === '<section class="section-wrap">x</section>' && $hostilePhotos["a\x01b.jpg"] === '<section class="section-wrap">x</section>' && ninoWarnings() === []
	&& array_filter( $hostilePhotos, static fn( string $html ): bool => str_contains( $html, '<script' ) || str_contains( $html, '<b>' ) || str_contains( $html, 'private/config' ) || str_contains( $html, '/etc/passwd' ) ) === []
	&& str_contains( $hostilePhotos['"><script>alert(1)</script>.jpg'], 'src="/public/images/&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;.jpg"' ) === true && str_contains( $hostilePhotos['[template /templates/evil].jpg'], 'src="/public/images/&#91;template /templates/evil&#93;.jpg"' ) === true );
ninoWarnings();
check( '...a link that a field makes is no link where it is none of a site: a script, another host - and outside a loop the same words are what they are written as',
	basisDraw( $appData, '[button text="Mehr" href="javascript:[[.slug]]"][button text="Mehr" href="//[[.slug]]"]', $loopContext ) === '<span class="button">Mehr</span><span class="button">Mehr</span>'
	&& basisDraw( $appData, '[button text="Mehr zu [[title]]" href="/leistungen/[[.slug]]"]' ) === '<a class="button" href="/leistungen/[[.slug]]">Mehr zu [[title]]</a>' );
check( 'a slot with no file, one that is none and a field that holds none draw no image and no logo: an empty box is no picture', $draw( '[image /empty][image /nothing][footer-logo /empty][footer-logo /nothing]' ) === ''
	&& $draw( '[section-wrap image="/empty"][/section-wrap]' ) === '<section class="section-wrap"></section>' );
check( 'a section with a picture puts it behind its content, with no alt text, since it is no content, and not lazy: it is what a visitor sees first', str_contains( $draw( '[section-wrap image="/template/page-basis/hero/background" dim][/section-wrap]' ), '<div class="section-wrap-image"><img class="section-wrap-picture" src="/public/images/hero.jpg" width="1600" height="900" alt="" decoding="async"></div></section>' ) );
$hidden = $draw( '[section-wrap image="/template/page-basis/hero/background" dim picture="off"][/section-wrap]' );
check( 'a section whose picture is off draws no picture, no veil and no reference to the file, and keeps the source in the call: on draws it', str_contains( $hidden, 'section-wrap-image' ) === false && str_contains( $hidden, '<img' ) === false && str_contains( $hidden, 'hero.jpg' ) === false
	&& str_contains( $draw( '[section-wrap image="/template/page-basis/hero/background" picture="on"][/section-wrap]' ), 'src="/public/images/hero.jpg"' ) === true );
check( 'the logo of the header is the picture for a light and the one for a dark ground and the name of the company, which is the name of the link; with no picture the name stays', $draw( '[header-logo]' )
	=== '<a class="header-logo" href="/"><img class="header-logo-light" src="/public/images/logo.png" width="160" height="48" alt=""><img class="header-logo-dark" src="/public/images/logo-dark.png" width="160" height="48" alt=""><span class="header-logo-name">Muster &amp; Söhne</span></a>'
	&& $draw( '[header-logo light="/empty" dark="/empty" href="/start"]' ) === '<a class="header-logo" href="/start"><span class="header-logo-name">Muster &amp; Söhne</span></a>' );
check( 'the logo of the footer is an image in a span, with the alt text of the call before the one stored for the picture', $draw( '[footer-logo /logo alt="L"]' ) === '<span class="footer-logo"><img src="/public/images/logo.png" width="160" height="48" alt="L" loading="lazy" decoding="async"></span>'
	&& str_contains( $draw( '[footer-logo /template/page-basis/card-1/photo alt="L"]' ), ' alt="L" ' ) && str_contains( $draw( '[footer-logo /template/page-basis/card-1/photo]' ), ' alt="Ein Bild im Speicher" ' ) && str_contains( $draw( '[footer-logo /template/page-basis/card-1/photo decorative]' ), ' alt="" ' ) );

// menus
$menuSite = basisSite();
$menu = basisDraw( $menuSite, '[header-nav]' );
check( 'the menu of the header is a details with a button that opens and closes it, no script and no popover, the entries of the pages in the menu in their order, the page that is shown marked, a route that is no page left out',
	str_contains( $menu, '<nav class="header-nav" aria-label="Hauptnavigation"><details class="header-nav-details"><summary class="header-nav-toggle" aria-label="Menü"><span aria-hidden="true"></span></summary><ul class="header-nav-list">' )
	&& str_contains( $menu, '<ul class="header-nav-list"><li><a href="/" aria-current="page">Start</a></li><li><a href="/contact">Kontakt</a></li><li><a href="/about">Über uns</a></li></ul></details></nav>' ) && str_contains( $menu, 'popover' ) === false && str_contains( $menu, 'script' ) === false && str_contains( $menu, 'id=' ) === false );
check( 'a menu that is never collapsed is a details that is open, one that collapses always or on a small screen is closed - the classes say which', str_contains( basisDraw( $menuSite, '[header-nav collapse="never"]' ), 'header-nav--collapse-never" aria-label="Hauptnavigation"><details class="header-nav-details" open>' )
	&& str_contains( basisDraw( $menuSite, '[header-nav collapse="always"]' ), 'header-nav--collapse-always" aria-label="Hauptnavigation"><details class="header-nav-details">' ) && str_contains( $menu, '<details class="header-nav-details">' ) );
check( 'a menu with no entry draws nothing - no list, no button - in the header and in the footer', $draw( '[header-nav menu="empty"][footer-nav menu="empty"]' ) === '' );
check( 'the menu of the footer is a list named by the word of the feature, or by the label the call gives it - a key, ~name or a text - so that two of them have two names', $draw( '[footer-nav menu="footer"]' ) === '<nav class="footer-nav" aria-label="Navigation im Fuß"><ul><li><a href="/contact">Kontakt</a></li><li><a href="/about">Über uns</a></li></ul></nav>'
	&& str_contains( $draw( '[footer-nav menu="main" label="Seiten &quot;x&quot;"]' ), '<nav class="footer-nav" aria-label="Seiten &quot;x&quot;">' ) && str_contains( $draw( '[footer-nav label="/template/page-basis/top/signin"]' ), 'aria-label="Anmelden"' )
	&& str_contains( $draw( '[header-nav label="Haupt"]' ), 'aria-label="Haupt"' ) && str_contains( $draw( '[header-nav]' ), 'aria-label="Hauptnavigation"' ) );
check( 'the foot of the page: the year and the company, the legal pages, the languages with the current one marked and each one in its own', $draw( '[footer-legal]' )
	=== '<div class="footer-legal"><p class="footer-legal-copyright">&copy; 2026 Muster &amp; Söhne</p><ul class="footer-legal-pages"><li><a href="/imprint">Impressum</a></li><li><a href="/privacy">Datenschutz</a></li></ul><ul class="footer-legal-locales"><li><a href="?/_nino/localepicker/current=de_DE" lang="de-DE" hreflang="de-DE" aria-current="true">Deutsch</a></li><li><a href="?/_nino/localepicker/current=en_US" lang="en-US" hreflang="en-US">English</a></li></ul></div>' );

// where the project says nothing: the right to see it
$one = basisSite();
$one['/nino/locales/available'] = [ 'de_DE' ];
$one['/nino/http/routes'] = [];
check( 'a site of one language and no legal page draws only the line of the copyright', \Nino\Components::render( $one, \Nino\Markup::parse( '[footer-legal]', \Nino\Components::containers( $one ), $notes ), [ 'template' => 'page-basis' ] ) === '<div class="footer-legal"><p class="footer-legal-copyright">&copy; 2026 Muster &amp; Söhne</p></div>' );

// the fall-back rules, each at a component of this feature
$home = basisSite( 'de_DE', false );
ninoWarnings();
$gone = basisDraw( $home, '[section-wrap][section-row][section-col]x[/section-col][/section-row][/section-wrap][section-wrap][/section-wrap]' );
$said = ninoWarnings();
check( 'F1: a component of a feature that is off is no component: it and what it holds draw nothing, and the log says so once', $gone === '' && count( $said ) === 1 && str_contains( $said[0], 'section-wrap' ) && str_contains( $said[0], 'no component that is registered' ) );
// ...and the next requests do not say it a second time within the hour: a request is a new run of the register, the counter is the file of the project's data
unset( $home[\Nino\Components::NOTICED] );
basisDraw( $home, '[section-wrap][/section-wrap]' );
$second = ninoWarnings();
unset( $home[\Nino\Components::NOTICED] );
basisDraw( $home, '[section-wrap][/section-wrap][section-row][/section-row]' );
$other = ninoWarnings();
\Nino\Filesystem::mutate( $home, '/data/component-notices.php', static fn( array $state ): array => array_map( static fn( array $entry ): array => [ 'reset' => time() - 1 ] + $entry, $state ), [] );
unset( $home[\Nino\Components::NOTICED] );
basisDraw( $home, '[section-wrap][/section-wrap]' );
$later = ninoWarnings();
check( 'F1b: the log says it once an hour for each name and not at every request - a second request is silent, another name says it, and when the hour is over it says it once more', $second === [] && count( $other ) === 1 && str_contains( $other[0], 'section-row' ) && count( $later ) === 1 && str_contains( $later[0], 'section-wrap' ) );
check( 'F2: a variant that the schema does not have is the standard one', $draw( '[section-col variant="fancy"]x[/section-col]' ) === '<div class="section-col">x</div>' );
check( 'F3: a value outside its list is the default and an attribute the schema does not have falls away', $draw( '[section-row gap="9" colour="red" width="wide"][/section-row]' ) === '<div class="section-row section-row--width-wide"></div>' );
check( 'F7: a component at a place that is not meant for it is drawn all the same', $draw( '[section-col]x[/section-col][section-row]y[/section-row]' ) === '<div class="section-col">x</div><div class="section-row">y</div>' );

$words = $basisDir. '/../../../../tests/fixtures/basis';
$lang = basisSite( 'en_US' );
\Nino\Filesystem::putFileContent( $lang, '/text/de_DE.php', [ '[[/template/page-basis/fall/back]]' => 'Nur deutsch' ] );
unset( $lang['./nino/html/filecache'] );
check( 'F8: a text that is missing in the language of the page is the text of the native language, with its lang; one that is missing everywhere draws nothing', basisDraw( $lang, '[section-title /template/page-basis/fall/back]' ) === '<h2 class="section-title" lang="de-DE">Nur deutsch</h2>'
	&& basisDraw( $lang, '[section-title /template/page-basis/fall/none][button /template/page-basis/fall/none href="/x"]' ) === '' );

// --- C. group, part, ladder and token --------------------------------------------------------------------

echo "\nC. The contract of group, part, ladder and token\n";

$nino = (string) file_get_contents( dirname( __DIR__ ). '/_nino/Nino.css' );
$problems = [];
$declaredTokens = [];

preg_match_all( '/^\s*(--(?:brand|page)-[a-z0-9-]+)\s*:/m', $nino, $tokens );
$declaredTokens = array_unique( $tokens[1] );
$bytes = 0;

foreach( $read['groups'] as $group => $declared ) {

	$css = (string) file_get_contents( $basisDir. '/groups/'. $group. '/part/part.css' );
	$bytes += strlen( $css );
	$own = $members[$group];

	if( $declared['part'] !== true || \Nino\Components::partProblems( $css, $own ) !== [] )
		$problems[] = $group. ': the part breaks the rule of mixing';

	if( is_array( $declared['label'] ) === false || isset( $declared['label']['de_DE'], $declared['label']['en_US'] ) === false )
		$problems[] = $group. ': no name in both languages';

	$per = [];

	foreach( $declared['knobs'] as $key => $knob ) {

		[ $component, $knobName ] = explode( '.', $key );
		$per[$component] = ( $per[$component] ?? 0 ) + 1;

		if( in_array( $component, $own, true ) === false || $knob['token'] !== '--'. $component. '-'. $knobName || count( $knob['ladder'] ) !== 5 )
			$problems[] = $key. ': not a knob of a component of the group, not its token or not five steps';

		// the middle step is the default, and it stands in the part, in the first :root
		$firstRoot = preg_match( '/^\s*:root\s*\{([^}]*)\}/', (string) preg_replace( '#/\*.*?\*/#s', '', $css ), $found ) === 1 ? $found[1] : '';

		if( preg_match( '/(?:^|[\s;])'. preg_quote( $knob['token'], '/' ). ':\s*'. preg_quote( $knob['ladder'][2], '/' ). '\s*;/', $firstRoot ) !== 1 )
			$problems[] = $key. ': the middle step is not what the part sets';

		if( preg_match( '/--[a-z-]+:[^;]*;/', explode( '}', $css )[0] ) !== 1 || str_starts_with( ltrim( preg_replace( '#/\*.*?\*/#s', '', $css ) ), ':root' ) === false )
			$problems[] = $group. ': the part does not begin with :root';

		foreach( $knob['ladder'] as $step )
			if( preg_match( '/var\(\s*(--[a-z0-9-]+)/', $step, $used ) === 1 && in_array( $used[1], $declaredTokens, true ) === false )
				$problems[] = $key. ': a step reads '. $used[1]. ', which Nino.css does not set';
	}

	foreach( $per as $component => $count )
		if( $count > 6 )
			$problems[] = $component. ': more than six knobs';

	// what a part reads of the brand and of the page is set in Nino.css
	preg_match_all( '/var\(\s*(--(?:brand|page)-[a-z0-9-]+)/', $css, $reads );

	foreach( array_unique( $reads[1] ) as $token )
		if( in_array( $token, $declaredTokens, true ) === false )
			$problems[] = $group. ' reads '. $token. ', which Nino.css does not set';

	if( preg_match( '/!important|@import|url\s*\(/i', $css ) === 1 )
		$problems[] = $group. ': important, import or url';
}
check( 'every group: components, a name in both languages, knobs of its components with their token and five steps, the middle step set in the part, at most six knobs for a component, a part that keeps the rule of mixing and reads only what Nino.css sets'
	. ( $problems === [] ? '' : ' - '. implode( '; ', array_slice( $problems, 0, 5 ) ) ), $problems === [] && count( $read['groups'] ) === 6 );

// Mutations of the rule, in the suite: the check has to see a foreign class, a foreign token and a sixth step
$section = (string) file_get_contents( $basisDir. '/groups/section/part/part.css' );
check( 'T3 sees a class that is no component of the group\'s, a token that is none of the brand, the page or the group\'s, and an important', \Nino\Components::partProblems( $section. "\n.card { color: red; }", $members['section'] ) !== []
	&& \Nino\Components::partProblems( $section. "\n.section-col { color: var(--foreign-ink); }", $members['section'] ) !== [] && \Nino\Components::partProblems( $section. "\n.section-col { color: red !important; }", $members['section'] ) !== []
	&& \Nino\Components::partProblems( (string) file_get_contents( $basisDir. '/groups/button/part/part.css' ), $members['section'] ) !== [] );
$group = include $basisDir. '/groups/section/group.php';
$six = $group;
$six['knobs']['section-wrap.space']['ladder'][] = '1rem';
$seven = $group;
for( $more = 0; $more < 7; $more++ )
	$seven['knobs']['section-wrap.more'. $more] = [ 'token' => '--section-wrap-more'. $more, 'label' => 'x', 'ladder' => [ '1rem', '2rem', '3rem', '4rem', '5rem' ] ];
check( 'a sixth step is refused, and so is a component with more than six knobs, and a knob of a component that is in no such group', is_string( \Nino\Components::validateGroup( 'section', $six, $members['section'] ) ) === true
	&& is_string( \Nino\Components::validateGroup( 'section', $seven, $members['section'] ) ) === true && is_string( \Nino\Components::validateGroup( 'section', $group, $members['button'] ) ) === true
	&& is_array( \Nino\Components::validateGroup( 'section', $group, $members['section'] ) ) === true );

// a helper token of a part is --<component>--<name>: a part may set it, since it is its own, and no knob can be named so - a name has no hyphen at its start
$twoHyphens = $group;
$twoHyphens['knobs']['section-row.-f'] = [ 'token' => '--section-row--f', 'label' => 'x', 'ladder' => [ '1rem', '2rem', '3rem', '4rem', '5rem' ] ];
check( 'a helper token of two hyphens (--section-row--f) is a token of its component for a part, and the group refuses a knob that would be named so',
	\Nino\Components::partProblems( ".section-row { --section-row--f: 1; gap: calc( var(--section-row-gap) * var(--section-row--f) ); }", [ 'section-row' ] ) === []
	&& is_string( \Nino\Components::validateGroup( 'section', $twoHyphens, $members['section'] ) ) === true );

// what a standard part sets itself is a knob of a group or a helper of two hyphens: a helper of one hyphen has the form of a knob and would meet one
$basisGroups = dirname( __DIR__ ). '/_nino/Nino/Modules/Basis/groups';
$knobTokens = [];
$setAside = [];

foreach( (array) glob( $basisGroups. '/*/group.php' ) as $groupFile )
	if( preg_match_all( '/--[a-z0-9-]+/', (string) file_get_contents( (string) $groupFile ), $found ) > 0 )
		$knobTokens = array_merge( $knobTokens, $found[0] );

foreach( (array) glob( $basisGroups. '/*/part/part.css' ) as $partFile )
	if( preg_match_all( '/(?<![\w-])(--[a-z0-9-]+)\s*:/', (string) preg_replace( '~/\*.*?\*/~s', '', (string) file_get_contents( (string) $partFile ) ), $found ) > 0 )
		foreach( array_unique( $found[1] ) as $token )
			if( in_array( $token, $knobTokens, true ) === false && preg_match( '/^--[a-z]+(?:-[a-z]+)*--[a-z]+(?:-[a-z]+)*$/', $token ) !== 1 )
				$setAside[] = basename( dirname( (string) $partFile, 2 ) ). ': '. $token;

check( 'a standard part sets no token but the knobs of the groups and helpers named --<component>--<name>'. ( $setAside === [] ? '' : ' - '. implode( ', ', $setAside ) ), $setAside === [] && count( $knobTokens ) >= 16 );

// the tokens of the brand and of the page are the contract: all of them in Nino.css, in the layer of the reset
$required = [ '--brand-scheme', '--brand-color-1', '--brand-color-2', '--brand-font-title', '--brand-font-text', '--brand-font-button', '--brand-ground-base', '--brand-ground-alt', '--brand-ground-tint', '--brand-ink-base',
	'--brand-ink-quiet-base', '--brand-line-base', '--brand-accent-base', '--brand-on-accent-base', '--brand-on-color-1', '--brand-on-color-2', '--brand-ground', '--brand-ink', '--brand-ink-quiet', '--brand-line', '--brand-accent',
	'--brand-on-accent', '--brand-second-base', '--brand-on-second-base', '--brand-second-from-1', '--brand-second', '--brand-on-second', '--page-size', '--page-width', '--page-radius', '--page-line', '--page-motion', '--page-leading', '--page-space-1', '--page-space-2', '--page-space-3', '--page-space-4', '--page-space-5', '--page-space-6',
	'--page-scroll-pad', '--page-weight-title', '--page-weight-text', '--page-bleed', '--page-text-1', '--page-text-2', '--page-text-3', '--page-text-4', '--page-text-5', '--page-text-6' ];
// The whole of Nino.css is the layer nino.reset
$resetLayer = $nino;
check( 'Nino.css sets every token of the brand and of the page - '. count( $required ). ' of them - in the layer nino.reset, and no part reads another', array_diff( $required, $declaredTokens ) === [] && array_filter( $required, static fn( string $token ): bool => str_contains( $resetLayer, $token. ':' ) === false ) === [] );

// the weights are tokens of the page, and what wrote a weight of a title or of the text reads them; bleed is told to the first child by the reset alone and read by the picture
$formCss = (string) file_get_contents( dirname( __DIR__ ). '/_nino/Nino/Modules/Form/assets/form.css' );
$imageCss = (string) file_get_contents( $basisDir. '/groups/image/part/part.css' );
$partOf = static fn( string $group ): string => (string) file_get_contents( $basisDir. '/groups/'. $group. '/part/part.css' );
check( 'the weight of a title and of the text are --page-weight-title (700) and --page-weight-text (400): the headings, the name of the logo, the body and the check of a form read them, and no part writes a fixed 700 or 400'
	, preg_match( '/^\s*--page-weight-title:\s*700;/m', $nino ) === 1 && preg_match( '/^\s*--page-weight-text:\s*400;/m', $nino ) === 1
	&& preg_match( '/h1, h2, h3, h4, h5, h6 \{[^}]*font-weight: var\(--page-weight-title\);/', $nino ) === 1 && str_contains( basisBody( array_filter( basisRules( $nino ), static fn( array $rule ): bool => $rule['selector'] === 'body' ), 'body', '@layer nino.reset' ), 'font-weight: var(--page-weight-text);' ) === true
	&& str_contains( basisBody( basisRules( $partOf( 'header' ) ), '.header-logo' ), 'font-weight: var(--page-weight-title);' ) === true && str_contains( $formCss, '.nino-forms-check { display: flex; align-items: center; gap: var(--page-space-2); font-weight: var(--page-weight-text); }' ) === true
	&& preg_match( '/font-weight:\s*(?:700|400|normal|bold)\b/', $nino. $partOf( 'header' ). $imageCss. $partOf( 'text' ). $partOf( 'section' ). $partOf( 'button' ). str_replace( '.footer-nav a[aria-current], .footer-legal a[aria-current] { font-weight: 700; text-decoration: none; }', '', $partOf( 'footer' ) ). $formCss ) !== 1
	&& str_contains( $partOf( 'footer' ), '.footer-legal a[aria-current] { font-weight: 700; text-decoration: none; }' ) === true
	&& str_contains( basisBody( array_filter( basisRules( $partOf( 'section' ) ), static fn( array $rule ): bool => $rule['selector'] === '.section-title' ), '.section-title' ), 'font-family: var(--brand-font-title); font-size: var(--page-text-5); font-weight: var(--page-weight-title);' ) === true );
// the header that stays lets go in the preview of the workbench: the mark that previewFrame() writes on the page, a rule after the one that makes it stay
$headerSelectors = array_column( basisRules( $partOf( 'header' ) ), 'selector' );
$looseAt = array_search( '[data-nino-preview="loose"] .header-wrap--sticky', $headerSelectors, true );
check( 'the sticky header is static under the mark the preview of the workbench sets on its page, by a rule that stands after the one that sticks it - and nothing else of the part reads the mark'
	, $looseAt !== false && $looseAt > array_search( '.header-wrap--sticky', $headerSelectors, true ) && trim( basisBody( basisRules( $partOf( 'header' ) ), '[data-nino-preview="loose"] .header-wrap--sticky' ) ) === 'position: static;'
	&& substr_count( $partOf( 'header' ), 'data-nino-preview' ) === 1 );
// the knobs of basis in the manual are the ones the group.php files hold: token and ladder, no knob left out or kept after it is gone
$manual = (string) file_get_contents( dirname( __DIR__ ). '/docs/development.md' );
$rows = [];

foreach( glob( $basisDir. '/groups/*/group.php' ) as $groupFile )
	foreach( ( include $groupFile )['knobs'] as $knobKey => $knobOf )
		$rows[] = '| `'. $knobKey. '` | `'. $knobOf['token']. '` | `'. implode( '`, `', $knobOf['ladder'] ). '` |';

preg_match_all( '/^\| `[a-z-]+\.[a-z-]+` \| `--[a-z-]+` \| .*\|$/m', $manual, $listed );
check( 'the manual lists the '. count( $rows ). ' knobs of basis with their token and ladder, as the group.php files hold them',
	count( $rows ) === 17 && array_diff( $rows, $listed[0] ) === [] && array_diff( $listed[0], $rows ) === [] );
$bleedRule = array_values( array_filter( basisRules( $nino ), static fn( array $rule ): bool => str_contains( $rule['body'], '--page-bleed' ) ) );
$bleedWhole = ':where( [class$="--bleed"], [class*="--bleed "] ) > :first-child';
check( 'bleed is a name of the page and is for the first child only: the reset sets --page-bleed to 1 there, whole word, and to 0 on everything inside it, and nothing else sets it; the figure of a picture works its corner out of it, with 0 where it is not set, and the picture reads that'
	, count( $bleedRule ) === 2 && $bleedRule[0]['selector'] === $bleedWhole. ' *' && trim( $bleedRule[0]['body'] ) === '--page-bleed: 0;' && $bleedRule[1]['selector'] === $bleedWhole && trim( $bleedRule[1]['body'] ) === '--page-bleed: 1;'
	&& substr_count( $partOf( 'section' ). $partOf( 'text' ). $partOf( 'button' ). $partOf( 'header' ). $partOf( 'footer' ), '--page-bleed:' ) === 0
	&& str_contains( basisBody( array_filter( basisRules( $imageCss ), static fn( array $rule ): bool => $rule['selector'] === '.image' ), '.image' ), '--image--corner: calc( var(--image-radius) * ( 1 - var(--page-bleed, 0) ) );' ) === true
	&& str_contains( basisBody( array_filter( basisRules( $imageCss ), static fn( array $rule ): bool => $rule['selector'] === '.image img' ), '.image img' ), 'border-radius: var(--image--corner);' ) === true );

// the contract of the grounds: what the register writes is what the reset meets - the word whole, and what each word does to the roles
$rules = basisRules( $resetLayer );
$grounds = $schemas['section-wrap']['attributes']['ground']['options'];
$meets = [];

foreach( [ 'section-wrap', 'section-col' ] as $name )
	foreach( array_diff( $schemas[$name]['attributes']['ground']['options'], [ 'none' ] ) as $ground ) {

		$written = basisFirstClass( basisDraw( $appData, basisCall( $name, $schemas[$name], ' ground="'. $ground. '"' ) ) );

		if( $ground !== $schemas[$name]['attributes']['ground']['default'] && str_contains( $written, '--ground-'. $ground ) === false )
			$meets[] = $name. ' '. $ground;
	}

$whole = static fn( string $word ): string => '[class$="--ground-'. $word. '"], [class*="--ground-'. $word. ' "]';
$ofRoles = static fn( string $selector ): string => basisBody( array_filter( $rules, static fn( array $rule ): bool => $rule['at'] === '@layer nino.reset' && $rule['selector'] === ':where( '. $selector. ' )' ), ':where', '@layer nino.reset' );

foreach( $grounds as $ground )
	if( $ofRoles( $whole( $ground ) ) === '' && in_array( $ground, [ 'base' ], true ) === false )
		$meets[] = 'no rule for '. $ground;

$palette = [ 'ground', 'ink', 'ink-quiet', 'line', 'accent', 'on-accent', 'second', 'on-second' ];
$page = $ofRoles( implode( ', ', array_map( $whole, [ 'base', 'alt', 'tint', 'dark' ] ) ) );

foreach( $palette as $role )
	if( str_contains( $page, '--brand-'. $role. ': var(--brand-'. $role. '-base);' ) === false )
		$meets[] = 'base does not set '. $role;

if( str_contains( $page, 'color-scheme: var(--brand-scheme);' ) === false )
	$meets[] = 'base does not set the mode';

$meets = array_merge( $meets,
	str_contains( $ofRoles( $whole( 'alt' ) ), '--brand-ground: var(--brand-ground-alt);' ) === true ? [] : [ 'alt does not change the ground' ],
	str_contains( $ofRoles( $whole( 'tint' ) ), '--brand-ground: var(--brand-ground-tint);' ) === true ? [] : [ 'tint does not change the ground' ],
	$ofRoles( $whole( 'dark' ) ) === 'color-scheme: dark;' ? [] : [ 'dark does not turn the mode' ],
	array_filter( [ '--brand-ground: var(--brand-color-1);', '--brand-ink: var(--brand-on-color-1);', '--brand-accent: var(--brand-on-color-1);', '--brand-on-accent: var(--brand-color-1);', '--brand-second: var(--brand-color-2,', '--brand-on-second: var(--brand-on-color-2,' ], static fn( string $line ): bool => str_contains( $ofRoles( $whole( 'primary' ) ), $line ) === false ),
	str_contains( $ofRoles( implode( ', ', array_map( $whole, [ 'base', 'alt', 'tint', 'dark', 'primary' ] ) ) ), 'background-color: var(--brand-ground); color: var(--brand-ink);' ) === true ? [] : [ 'a ground does not paint itself' ] );
$fallback = basisBody( $rules, '--ground-dark', '@layer nino.reset @supports not ( color: light-dark( #000, #fff ) )' );
check( 'the ground of a section and of a column is the class <name>--ground-<word>; Nino.css names the roles for each word, whole - base, alt, tint, primary, dark -: base the palette, alt and tint the ground, primary the colour of the brand, dark the mode, and a ground paints itself; dark is written out where light-dark() is not known'
	. ( $meets === [] ? '' : ' - '. implode( '; ', $meets ) ), $meets === [] && $grounds === [ 'base', 'alt', 'tint', 'primary', 'dark' ] && str_contains( $fallback, '--brand-ground: #15171b;' ) && str_contains( $fallback, '--brand-ink: #eceef1;' )
	&& preg_match( '/\[class\*="--ground-[a-z]+"\]/', $resetLayer ) !== 1 );
check( 'no class of the old Nino.css has --ground- in it, so the selector meets only what the register writes', preg_match( '/\.nino-[a-z0-9-]*--ground-/', $nino ) !== 1 );

// what the parts do about the mode, the grounds and the picture
$partRules = [];
foreach( array_keys( $read['groups'] ) as $partGroup )
	$partRules[$partGroup] = basisRules( (string) file_get_contents( $basisDir. '/groups/'. $partGroup. '/part/part.css' ) );
$css = [ 'header' => (string) file_get_contents( $basisDir. '/groups/header/part/part.css' ), 'section' => (string) file_get_contents( $basisDir. '/groups/section/part/part.css' ), 'footer' => (string) file_get_contents( $basisDir. '/groups/footer/part/part.css' ), 'button' => (string) file_get_contents( $basisDir. '/groups/button/part/part.css' ) ];
$bad = [];

// the mode of the device reaches a header, a footer and a section that has no ground, and does not take the dark ground its mode from it
foreach( [ 'header' => '.header-wrap { ', 'footer' => '.footer-wrap { ' ] as $partGroup => $selector ) {

	$body = basisBody( array_filter( $partRules[$partGroup], static fn( array $rule ): bool => $rule['selector'] === trim( $selector, ' {' ) ), trim( $selector, ' {' ) );

	if( str_contains( $body, 'color-scheme: var(--brand-scheme);' ) === false )
		$bad[] = $partGroup. ' has no mode of its own';
}

$wrap = array_values( array_filter( $partRules['section'], static fn( array $rule ): bool => $rule['at'] === '' && str_starts_with( $rule['selector'], '.section-wrap:not(' ) ) );
$veiled = basisBody( $partRules['section'], '.section-wrap:has( > .section-wrap-image )' );

if( count( $wrap ) !== 1 || str_contains( $wrap[0]['body'], 'color-scheme: var(--brand-scheme);' ) === false || str_contains( $wrap[0]['selector'], '[class$="--ground-dark"]' ) === false || str_contains( $wrap[0]['selector'], '[class*="--ground-dark "]' ) === false )
	$bad[] = 'a section that is not dark has no mode of its own, or it takes the one of the dark ground';

if( str_contains( $veiled, 'color-scheme: dark;' ) === false )
	$bad[] = 'a section with a picture is not dark';

// a card that is a link: the link of its title covers it and nothing else does; what is in the card is above the cover and has its own focus
$cover = basisBody( $partRules['section'], '.section-col--link > .section-title a::after' );
$above = basisBody( $partRules['section'], '.section-col--link :where( [href] ):not( .section-title * )' );
$ring = basisBody( $partRules['section'], '.section-col--link:has( > .section-title a:focus-visible )' );

if( str_contains( $cover, 'position: absolute;' ) === false || str_contains( $cover, 'inset: 0;' ) === false || str_contains( $above, 'position: relative;' ) === false || str_contains( $above, 'z-index: 1;' ) === false || str_contains( $ring, 'outline:' ) === false
	|| str_contains( $css['section'], 'first-of-type' ) === true || str_contains( $css['section'], 'outline: none' ) === true )
	$bad[] = 'the card that is a link does not cover with the link of its title alone';

// a card with bleed lets a picture reach the edge, and only a picture: the rule asks the first child for a picture, in a link or not, as the image component draws it, not whatever comes first
$bleeds = array_values( array_filter( $partRules['section'], static fn( array $rule ): bool => str_contains( $rule['selector'], '--bleed' ) ) );
$figure = (string) file_get_contents( $basisDir. '/components/image/template.tpl' );

if( count( $bleeds ) !== 1 || $bleeds[0]['selector'] !== '.section-col--bleed > :first-child:has( > img, > a > img )' || str_contains( $bleeds[0]['body'], 'margin: calc( -1 * var(--section-col--inset) ) calc( -1 * var(--section-col--inset) ) 0;' ) === false || preg_match( '#^<figure [^>]*>\\[\\[open\\]\\]\\[\\[picture\\]\\]#', $figure ) !== 1 )
	$bad[] = 'a card with bleed does not leave the padding to a first child that is no picture';

if( str_contains( basisBody( $partRules['button'], '.button:focus-visible' ), 'outline:' ) === false )
	$bad[] = 'a button has no ring of its own';

// a word longer than the box it stands in breaks where the box ends: the boxes whose width is that of their content - a button, a text, the name of a
// logo, a menu entry, a column of the foot and its line - say anywhere, since the break-word of the page leaves them as wide as the word
foreach( [ 'button' => [ '.button' ], 'text' => [ '.text' ], 'header' => [ '.header-logo', '.header-nav a' ], 'footer' => [ '.footer-col', '.footer-legal' ] ] as $wrapGroup => $wrapSelectors )
	foreach( $wrapSelectors as $wrapSelector )
		if( str_contains( basisBody( array_filter( $partRules[$wrapGroup], static fn( array $rule ): bool => $rule['selector'] === $wrapSelector ), $wrapSelector ), 'overflow-wrap: anywhere;' ) === false )
			$bad[] = $wrapSelector. ' lets a word longer than the box leave it';

// a row that is turned round turns when its columns stand one under the other as well
foreach( [ '', 'm-', 'l-' ] as $width )
	if( str_contains( basisBody( $partRules['section'], '.section-row--'. $width. 'reverse-on', $width === '' ? '' : ( $width === 'm-' ? '@media (min-width: 768px)' : '@media (min-width: 1024px)' ) ), 'flex-wrap: wrap-reverse;' ) === false )
		$bad[] = 'reverse '. $width. 'on does not turn wrapped rows';

// the picture of a section is under a veil that leaves the words of the dark page legible over a white picture, and dim is stronger
$veil = [];
foreach( [ '.section-wrap-image::after', '.section-wrap--dim > .section-wrap-image::after' ] as $veilSelector )
	$veil[] = preg_match( '/rgb\( 0 0 0 \/ (\.\d+) \)/', basisBody( $partRules['section'], $veilSelector ), $found ) === 1 ? (float) $found[1] : 0.0;

preg_match_all( '/--brand-(ink-base|ink-quiet-base|accent-base): light-dark\( [^,]+, (#[0-9a-f]{6}) \)/', $nino, $dark );
$onWhite = (int) round( 255 * ( 1 - $veil[0] ) );
$weakest = 21.0;

foreach( $dark[2] as $hex )
	$weakest = min( $weakest, basisContrast( [ hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) ], [ $onWhite, $onWhite, $onWhite ] ) );

$dimmed = (int) round( 255 * ( 1 - $veil[1] ) );
$weakestDim = 21.0;

foreach( $dark[2] as $hex )
	$weakestDim = min( $weakestDim, basisContrast( [ hexdec( substr( $hex, 1, 2 ) ), hexdec( substr( $hex, 3, 2 ) ), hexdec( substr( $hex, 5, 2 ) ) ], [ $dimmed, $dimmed, $dimmed ] ) );

if( count( $dark[2] ) !== 3 || $veil[0] <= 0 || $veil[1] <= $veil[0] || $veil[1] > 0.85 || $weakest < 4.5 || $weakest > 5.0 || $weakestDim < 4.5 )
	$bad[] = 'the veil leaves '. sprintf( '%.2f', $weakest ). ' : 1 over a white picture, and '. sprintf( '%.2f', $weakestDim ). ' : 1 with dim, which is stronger by a step and not by more than it needs';

// ...and the rows that follow the picture say that they are dark themselves, which needs no :has()
if( str_contains( basisBody( $partRules['section'], '.section-wrap-image ~ .section-row' ), 'color-scheme: dark; color: var(--brand-ink);' ) === false || str_contains( basisBody( $partRules['section'], '.section-wrap-image ~ .section-row', '@supports not ( color: light-dark( #000, #fff ) )' ), 'color: #eceef1;' ) === false )
	$bad[] = 'a row after a picture does not say it is dark without :has()';

// the header that folds its menu scrolls away when the menu is open; the button stays in the line and the list opens below it
if( str_contains( basisBody( $partRules['header'], '.header-wrap--sticky:has( .header-nav:not( .header-nav--collapse-never ) .header-nav-details[open] )' ), 'position: static;' ) === false
	|| str_contains( basisBody( $partRules['header'], '.header-nav-details', '@supports selector( ::details-content )' ), 'display: contents;' ) === false || str_contains( basisBody( $partRules['header'], '.header-nav-details::details-content', '@supports selector( ::details-content )' ), 'flex: 1 0 100%;' ) === false
	|| str_contains( basisBody( $partRules['header'], '.header-nav-toggle' ), 'margin-inline-start: auto;' ) === false || str_contains( basisBody( $partRules['header'], '.header-nav a' ), 'overflow-wrap: anywhere;' ) === false )
	$bad[] = 'the menu does not keep its button in the line or its header does not scroll away open';

if( basisBody( $partRules['section'], '.section-wrap:has( > .section-wrap-image )', '@supports not ( color: light-dark( #000, #fff ) )' ) === '' )
	$bad[] = 'a section with a picture has no colours of its own where light-dark() is not known';

// the logo for a dark ground follows the colour scheme of its element: both pictures stand in one cell, each under a mask of light-dark() that is the other's reverse; where light-dark() is not known the one for a light ground stands
$cell = basisBody( $partRules['header'], '.header-logo img' );
$lightMask = basisBody( $partRules['header'], '.header-logo-light:not( :only-of-type )' );
$darkMask = basisBody( $partRules['header'], '.header-logo-dark:not( :only-of-type )' );
$noPair = basisBody( $partRules['header'], '.header-logo-dark:not( :only-of-type )', '@supports not ( color: light-dark( #000, #fff ) )' );
$forced = basisBody( $partRules['header'], '.header-logo', '@media (forced-colors: active)' );

if( str_contains( $cell, 'grid-area: 1 / 1;' ) === false || str_contains( basisBody( $partRules['header'], '.header-logo' ), 'display: grid;' ) === false
	|| str_contains( $lightMask, 'mask-image: linear-gradient( light-dark( #000, transparent ), light-dark( #000, transparent ) );' ) === false
	|| str_contains( $darkMask, 'mask-image: linear-gradient( light-dark( transparent, #000 ), light-dark( transparent, #000 ) );' ) === false
	|| str_contains( $noPair, 'display: none;' ) === false || str_contains( $forced, 'color-scheme: light dark;' ) === false
	// a picture that stands alone is on every ground: no mask or hiding names it without :not( :only-of-type ), and the pair's own rules are the only ones to
	|| preg_match( '/\.header-logo-(?:light|dark)(?!:not\( :only-of-type \))[^{]*\{[^}]*(?:mask-image|display: none)/', $css['header'] ) === 1 || str_contains( $css['header'], 'prefers-color-scheme' ) === true || str_contains( $css['header'], '.header-logo:has( .header-logo-dark )' ) === true )
	$bad[] = 'the logo for a dark ground does not follow the colour scheme of the header it stands in';

// a fixed step is a factor on the knob of the same size: it never sets the token of the knob, so a theme that turns the knob turns the steps with it, in the same order
// ...by the factors the manual names: 0, a half, one, one and a half, two and a half for a gap and a pad, an eighth under and over for a size; each grounds rule of the component says its own 1, so that no factor is inherited by what stands in it
$steps = [ 'section' => [ 'section-row' => [ 'gap', '--section-row--f', '--section-row-gap', [ 0.0, 0.5, 1.0, 1.5, 2.5 ] ], 'section-col' => [ 'pad', '--section-col--f', '--section-col-pad', [ 0.0, 0.5, 1.0, 1.5, 2.5 ] ] ],
	'image' => [ 'image' => [ 'height', '--image--f', '--image-height', [ 0.5, 1.0, 1.5, 2.5 ] ] ],
	'text' => [ 'text' => [ 'size', '--text--f', '--text-size', [ 0.875, 1.0, 1.125 ] ] ], 'button' => [ 'button' => [ 'size', '--button--f', '--button-size', [ 0.875, 1.0, 1.125 ] ] ] ];

foreach( $steps as $stepGroup => $stepComponents )
	foreach( $stepComponents as $stepComponent => [ $stepName, $factor, $knob, $values ] ) {

		$options = array_values( array_diff( $schemas[$stepComponent]['attributes'][$stepName]['options'], [ 'auto' ] ) );
		$default = $schemas[$stepComponent]['attributes'][$stepName]['default'];
		$last = -1.0;
		$got = [];

		if( str_contains( basisBody( array_filter( $partRules[$stepGroup], static fn( array $rule ): bool => $rule['selector'] === '.'. $stepComponent ), '.'. $stepComponent ), $factor. ': 1;' ) === false )
			$bad[] = $stepComponent. ' does not say its own factor of 1, and so inherits the one of what it stands in';

		foreach( $options as $option ) {

			$body = basisBody( array_filter( $partRules[$stepGroup], static fn( array $rule ): bool => $rule['selector'] === '.'. $stepComponent. '--'. $stepName. '-'. $option ), '.'. $stepComponent. '--'. $stepName. '-'. $option );
			$value = $option === $default && $body === '' ? 1.0 : ( preg_match( '/^'. preg_quote( $factor, '/' ). ': (\d*\.?\d+);/', $body, $found ) === 1 ? (float) $found[1] : -2.0 );

			if( $value <= $last || str_contains( $body, $knob ) === true )
				$bad[] = $stepComponent. ' '. $stepName. '="'. $option. '" is no factor above the step before it';

			$last = $value;
			$got[] = $value;
		}

		if( $got !== $values )
			$bad[] = $stepComponent. ' '. $stepName. ' has the factors '. implode( ' ', $got ). ' and not '. implode( ' ', $values );
	}

if( str_contains( basisBody( $partRules['section'], '.section-row' ), '--section-row--space: calc( var(--section-row-gap) * var(--section-row--f) );' ) === false
	|| str_contains( basisBody( array_filter( $partRules['section'], static fn( array $rule ): bool => $rule['selector'] === '.section-row' ), '.section-row' ), 'gap: var(--section-row--space);' ) === false
	|| str_contains( basisBody( $partRules['section'], '.section-col', '' ), '--section-col--inset: calc( var(--section-col-pad) * var(--section-col--f) );' ) === false
	|| str_contains( basisBody( $partRules['text'], '.text' ), 'font-size: calc( var(--text-size) * var(--text--f) );' ) === false
	|| str_contains( basisBody( $partRules['button'], '.button' ), 'font-size: calc( var(--button-size) * var(--button--f) );' ) === false
	|| substr_count( $css['section'], 'var(--section-row-gap)' ) !== 1 || substr_count( $css['section'], 'var(--section-col-pad)' ) !== 1 )
	$bad[] = 'a part reads the knob of a step where it should read the knob times the factor';

// a picture is fitted by object-fit and a fixed height is a factor on the knob of its own, which the step never sets: the width follows the picture and is cut at the column, and the picture is fitted into what is left
$imageRules = $partRules['image'];

if( str_contains( basisBody( $imageRules, '.image--fit img' ), 'object-fit: contain;' ) === false
	|| str_contains( basisBody( $imageRules, '.image:where( [class*="--height-"] ) img' ), 'height: calc( var(--image-height) * var(--image--f) );' ) === false
	|| str_contains( basisBody( $imageRules, '.image:where( [class*="--height-"] ) img' ), 'width: auto;' ) === false || str_contains( basisBody( $imageRules, '.image:where( [class*="--height-"] ) img' ), 'object-fit: contain;' ) === false
	|| trim( basisBody( array_filter( $imageRules, static fn( array $rule ): bool => $rule['selector'] === '.image:where( [class*="--height-"] )' ), 'image:where' ) ) !== 'width: auto;'
	|| trim( basisBody( array_filter( $imageRules, static fn( array $rule ): bool => $rule['selector'] === '.image:where( [class*="--height-"] ) img' && str_contains( $rule['body'], 'inline-block' ) ), 'image:where' ) ) !== 'display: inline-block; vertical-align: top;'
	|| str_contains( basisBody( $imageRules, '.image--height-' ), '--image-height' ) === true )
	$bad[] = 'a fitted picture or one of a fixed height is not fitted by object-fit, or a step of the height sets the knob and not a factor';

// the columns of a fifth and a sixth stand at every width, and a row says where its columns stand with the line that wraps
foreach( [ '16' => '6', '20' => '5' ] as $size => $count )
	foreach( [ '' => [ '', '' ], 'm-' => [ 'm-', '@media (min-width: 768px)' ], 'l-' => [ 'l-', '@media (min-width: 1024px)' ] ] as $width => [ $prefix, $at ] )
		if( str_contains( basisBody( array_filter( $partRules['section'], static fn( array $rule ): bool => $rule['selector'] === '.section-col--'. $prefix. 'size-'. $size ), '.section-col--'. $prefix. 'size-'. $size, $at ), '--section-col--n: '. $count. ';' ) === false )
			$bad[] = 'a column of size '. $size. ' at '. ( $width === '' ? 'every width' : $width ). ' is not one of '. $count;

foreach( [ 'center' => 'center', 'end' => 'flex-end', 'between' => 'space-between' ] as $halign => $justify )
	if( trim( basisBody( array_filter( $partRules['section'], static fn( array $rule ): bool => $rule['selector'] === '.section-row--halign-'. $halign ), '.section-row--halign-'. $halign ) ) !== 'justify-content: '. $justify. ';' )
		$bad[] = 'halign '. $halign. ' is not justify-content: '. $justify;

// a title that is a link is underlined - no rule of the part takes the underline away from it - and thicker at the pointer; a title that is none has no rule of its own for it
$titleLink = basisBody( array_filter( $partRules['section'], static fn( array $rule ): bool => $rule['selector'] === '.section-title a' ), '.section-title a' );

if( str_contains( $titleLink, 'text-decoration' ) === true || str_contains( $titleLink, 'color: inherit;' ) === false || str_contains( basisBody( $partRules['section'], '.section-title a:hover' ), 'text-decoration-thickness: 2px;' ) === false
	|| substr_count( $css['section'], 'text-decoration: none' ) !== 0 )
	$bad[] = 'a title that is a link is not underlined, or an underline is taken from it';

// the menu is a details, with no popover, and a wide screen shows its list where the browser lets the rules reach it
$nav = basisBody( $partRules['header'], '.header-nav-details::details-content', '@media (min-width: 768px) @supports selector( ::details-content )' );

if( str_contains( $css['header'], 'popover' ) === true || str_contains( $nav, 'content-visibility: visible;' ) === false || str_contains( basisBody( $partRules['header'], '.header-nav-toggle', '@media (min-width: 768px) @supports selector( ::details-content )' ), 'display: none;' ) === false )
	$bad[] = 'the menu is no details that a wide screen shows open where it can';

// the weights of a button: four, in this order, main the default; each is drawn as its class and has a rule in the standard part; the quiet one names no colour of the brand, the others the roles of their ground
$tone = $schemas['button']['attributes']['tone'];

if( $tone['options'] !== [ 'primary', 'secondary', 'outline', 'quiet' ] || $tone['default'] !== 'primary' || array_keys( $tone['labels'] ) !== $tone['options'] )
	$bad[] = 'the weights of a button are not main, second colour, outline and quiet, main the default, each with a label';

foreach( [ 'secondary', 'outline', 'quiet' ] as $weight ) {

	if( $draw( '[button text="Go" tone="'. $weight. '"]' ) !== '<span class="button button--tone-'. $weight. '">Go</span>' )
		$bad[] = 'a button of the weight '. $weight. ' does not draw its class';

	if( array_filter( $partRules['button'], static fn( array $rule ): bool => $rule['at'] === '' && $rule['selector'] === '.button--tone-'. $weight ) === [] )
		$bad[] = 'the standard part has no rule for the weight '. $weight;
}

$quietRules = implode( ' ', array_column( array_filter( $partRules['button'], static fn( array $rule ): bool => str_contains( $rule['selector'], '.button--tone-quiet' ) ), 'body' ) );

if( preg_match( '/--brand-(?:accent|on-accent|second|on-second|color)/', $quietRules ) === 1 || str_contains( $quietRules, '--brand-ink' ) === false )
	$bad[] = 'the quiet weight names a colour of the brand, or is not the ink of its ground';

$secondRule = basisBody( array_filter( $partRules['button'], static fn( array $rule ): bool => $rule['selector'] === '.button--tone-secondary' ), '.button--tone-secondary' );

if( str_contains( $secondRule, 'background-color: var(--brand-second);' ) === false || str_contains( $secondRule, 'color: var(--brand-on-second);' ) === false || str_contains( basisBody( $partRules['button'], '.button--tone-outline' ), 'color: var(--brand-accent);' ) === false )
	$bad[] = 'second colour and outline do not read the roles of their ground';

// quiet: its fill and its ink are two helpers of the group, the ring and the words read the ink, the pointer turns the fill
$quietOwn = basisBody( array_filter( $partRules['button'], static fn( array $rule ): bool => $rule['selector'] === '.button--tone-quiet' ), '.button--tone-quiet' );

if( preg_match( '/--button--quiet-fill: color-mix\( in srgb, var\(--brand-ink\) 10%, var\(--brand-ground\) \);/', $quietOwn ) !== 1 || str_contains( $quietOwn, '--button--quiet-ink: var(--brand-ink);' ) === false
	|| str_contains( $quietOwn, 'background-color: var(--button--quiet-fill);' ) === false || str_contains( $quietOwn, 'color: var(--button--quiet-ink);' ) === false
	|| str_contains( basisBody( array_filter( $partRules['button'], static fn( array $rule ): bool => $rule['selector'] === '.button--tone-quiet:focus-visible' ), '.button--tone-quiet:focus-visible' ), 'outline-color: var(--button--quiet-ink);' ) === false
	|| preg_match( '/--button--quiet-fill: color-mix\( in srgb, var\(--brand-ink\) 14%, var\(--brand-ground\) \);/', basisBody( $partRules['button'], 'a.button--tone-quiet:hover' ) ) !== 1 )
	$bad[] = 'the quiet weight does not run on its two helpers, or its ring is not its ink, or the pointer does not turn its fill';

// the outline is filled under the pointer, and with forced colours the four weights stay apart by the line
$outlineHover = basisBody( $partRules['button'], 'a.button--tone-outline:hover' );
$forcedLines = [ '.button' => '3px solid ButtonText', '.button--tone-secondary' => 'border-style: double', '.button--tone-outline' => 'border-width: 1px', '.button--tone-quiet' => 'border-style: dashed' ];

foreach( $forcedLines as $forcedSelector => $forcedLine )
	if( str_contains( basisBody( array_filter( $partRules['button'], static fn( array $rule ): bool => $rule['selector'] === $forcedSelector ), $forcedSelector, '@media (forced-colors: active)' ), $forcedLine ) === false )
		$bad[] = 'with forced colours '. $forcedSelector. ' has not its line';

if( str_contains( $outlineHover, 'background-color: var(--brand-accent);' ) === false || str_contains( $outlineHover, 'color: var(--brand-on-accent);' ) === false )
	$bad[] = 'the outline is not filled under the pointer';

// the second colour in Nino.css: a light page has the colour of the brand, a dark page lightens it by half with white under dark writing that holds 4.5 : 1 on the default colour;
// a brand with one colour has the first colour at a share that leaves white writing 4.5 : 1 on it for every first colour (white is the worst); no light-dark() leaves the roles written out
$bareNino = (string) preg_replace( '~/\*.*?\*/~s', '', $nino );
$second = [ '--brand-second-base: light-dark( var(--brand-color-2, var(--brand-second-from-1)), color-mix( in srgb, var(--brand-color-2, var(--brand-second-from-1)) 45%, #fff ) );', '--brand-on-second-base: light-dark( var(--brand-on-color-2, #fff), #0f1b2e );' ];

foreach( $second as $secondLine )
	if( str_contains( $bareNino, $secondLine ) === false )
		$bad[] = 'the second colour of the palette is not '. $secondLine;

if( preg_match( '/--brand-second-from-1: color-mix\( in srgb, var\(--brand-color-1\) (\d+)%, #1b1d21 \);/', $bareNino, $share ) !== 1 )
	$bad[] = 'a brand with one colour has no second colour from the first';
else {
	$mixShare = (int) $share[1] / 100;
	$derived = array_map( static fn( int $i ): int => (int) round( 255 * $mixShare + [ 27, 29, 33 ][$i] * ( 1 - $mixShare ) ), [ 0, 1, 2 ] );

	if( $mixShare <= 0 || $mixShare >= 1 || basisContrast( [ 255, 255, 255 ], $derived ) < 4.5 )
		$bad[] = 'white on the second colour of a brand with one colour, made from a white first colour, is '. sprintf( '%.2f', basisContrast( [ 255, 255, 255 ], $derived ) ). ' : 1';
}

$lightened = array_map( static fn( int $value ): int => (int) round( $value * .45 + 255 * .55 ), [ 0x17, 0x23, 0x3a ] );

if( basisContrast( [ 0x0f, 0x1b, 0x2e ], $lightened ) < 4.5 || basisContrast( $lightened, [ 0x15, 0x17, 0x1b ] ) < 3.0 )
	$bad[] = 'the second colour of the dark page, lightened from the default, has not its writing at 4.5 : 1 or its ground at 3 : 1';

$secondFallback = basisBody( $rules, '--ground-dark', '@layer nino.reset @supports not ( color: light-dark( #000, #fff ) )' );

if( str_contains( $secondFallback, '--brand-second: #969ca6;' ) === false || str_contains( $secondFallback, '--brand-on-second: #0f1b2e;' ) === false || basisContrast( [ 0x96, 0x9c, 0xa6 ], [ 0x0f, 0x1b, 0x2e ] ) < 4.5 )
	$bad[] = 'without light-dark() a dark ground does not write out the second colour and its writing';

// every weight of the demo stands on a light and on a dark ground
$demoGrounds = [];
foreach( (array) preg_split( '/\[section-wrap /', (string) file_get_contents( $basisDir. '/groups/button/demo.tpl' ) ) as $demoChunk )
	if( preg_match( '/^ground="(\w+)"/', $demoChunk, $demoGround ) === 1 && preg_match_all( '/tone="(\w+)"/', $demoChunk, $demoTone ) > 0 )
		$demoGrounds[$demoGround[1]] = array_values( array_unique( array_merge( $demoGrounds[$demoGround[1]] ?? [], $demoTone[1] ) ) );

foreach( [ 'base', 'dark' ] as $demoGround )
	foreach( [ 'primary', 'secondary', 'outline', 'quiet' ] as $demoWeight )
		if( in_array( $demoWeight, $demoGrounds[$demoGround] ?? [], true ) === false )
			$bad[] = 'the demo has no '. $demoWeight. ' button on a '. $demoGround. ' ground';

// on paper: no ground, dark writing, a button with a frame
foreach( [ 'header' => '.header-wrap', 'footer' => '.footer-wrap', 'section' => '.section-wrap', 'button' => '.button' ] as $partGroup => $printed )
	if( preg_match( '/(?:^|[ ;])color: #000;/', basisBody( $partRules[$partGroup], $printed, '@media print' ) ) !== 1 )
		$bad[] = $partGroup. ' is not printed in dark writing';

check( 'the parts: the mode of the device reaches header, footer and a section; a card that is a link is covered by the link of its title alone; reverse turns wrapped rows; the picture of a section is under a veil that is legible over white ('. sprintf( '%.1f', $weakest ). ' : 1); the logo and the menu fall back; paper is dark on white'
	. ( $bad === [] ? '' : ' - '. implode( '; ', $bad ) ), $bad === [] );

// an element - a, p, li, img - is met only below a component that holds nothing: a container never styles what the page put into it
$leaves = array_keys( array_filter( $schemas, static fn( array $schema ): bool => isset( $schema['offers'] ) === false ) );
$owner = static function( string $class ) use ( $schemas ): ?string {

	$found = null;

	foreach( array_keys( $schemas ) as $component )
		if( ( $class === $component || str_starts_with( $class, $component. '-' ) ) && strlen( (string) $found ) < strlen( $component ) )
			$found = $component;

	return $found;
};
$elements = [];

foreach( $partRules as $partGroup => $groupRules )
	foreach( $groupRules as $rule ) {

		if( str_starts_with( $rule['selector'], '@' ) === true || $rule['selector'] === ':root' )
			continue;

		foreach( preg_split( '/,(?![^(]*\))/', $rule['selector'] ) ?: [] as $selector ) {

			// what is only a state or a part of a thing is not a thing: the pseudo-classes and elements and the attributes are cut away
			$plain = (string) preg_replace( [ '/::?[a-z-]+\((?:[^()]|\([^()]*\))*\)/', '/::?[a-z-]+/', '/\[[^\]]*\]/' ], '', $selector );
			$seen = [];

			foreach( preg_split( '/\s*[>+~]\s*|\s+/', trim( $plain ) ) ?: [] as $compound ) {

				preg_match_all( '/\.([a-z][a-z0-9-]*)/', $compound, $classes );
				$classes = array_map( $owner, $classes[1] );
				$typed = preg_match( '/^[a-z][a-z0-9]*(?=[.#]|$)/', $compound ) === 1;

				if( $typed === true && array_intersect( array_merge( $seen, $classes ), $leaves ) === [] )
					$elements[] = $partGroup. ': '. trim( $selector );

				$seen = array_merge( $seen, $classes );
			}
		}
	}
check( 'an element selector (a, p, li, img, ul) stands below a component that holds nothing, or beside its class - no container styles what the page put into it'. ( $elements === [] ? '' : ' - '. implode( '; ', array_slice( array_unique( $elements ), 0, 5 ) ) ), $elements === [] );

// the parts in the bundle, and the hook that asks for them
$bundle = $appData['/nino/html/assets']['/.cache/style.css'] ?? [];
sort( $bundle );
check( 'the six standard parts are in the style bundle, once each, as files of the kernel', array_map( static fn( string $path ): string => substr( $path, strlen( '/_nino/Nino/Modules/Basis/groups/' ) ), $bundle ) === [ 'button/part/part.css', 'footer/part/part.css', 'header/part/part.css', 'image/part/part.css', 'section/part/part.css', 'text/part/part.css' ]
	&& array_filter( $bundle, static fn( string $path ): bool => is_file( dirname( __DIR__ ). $path ) === false ) === [] );

$asked = new \ArrayObject();
$themed = ninoSandbox( 'basis-theme' );
$themed['./nino/filesystem/path'] = dirname( __DIR__ );
$themed['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];
\Nino\Callbacks::registerCallback( $themed, \Nino\Components::PARTS, static function( array &$appData, array $answer ) use ( $asked ): array {

	$asked[] = $answer['group'];

	// the text part is taken away, the image part is another file of the feature, the section part is answered with a file that is no feature's
	return match( $answer['group'] ) {
		'text'		=> [ 'css' => '' ] + $answer,
		'image'		=> [ 'css' => '/_nino/Nino/Modules/Basis/groups/button/part/part.css' ] + $answer,
		'section'	=> [ 'css' => '/assets/image.css' ] + $answer,
		default		=> $answer,
	};
} );
\Nino\Features::registerComponents( $themed );
\Nino\Features::registerParts( $themed );
$themedBundle = $themed['/nino/html/assets']['/.cache/style.css'];
$themedSaid = ninoWarnings();
check( 'a theme is asked for each of the six parts and may replace one (a file the bundle holds already stands once), take one away - and an answer that is no stylesheet of a feature is said once and leaves the standard part', count( $asked ) === 6 && array_diff( [ 'button', 'footer', 'header', 'image', 'section', 'text' ], $asked->getArrayCopy() ) === []
	&& count( $themedBundle ) === 4 && count( array_keys( $themedBundle, '/_nino/Nino/Modules/Basis/groups/button/part/part.css', true ) ) === 1 && in_array( '/_nino/Nino/Modules/Basis/groups/section/part/part.css', $themedBundle, true ) === true
	&& array_filter( $themedBundle, static fn( string $path ): bool => str_contains( $path, '/text/part' ) || str_contains( $path, '/image/part' ) || str_contains( $path, '/assets/' ) ) === []
	&& count( $themedSaid ) === 1 && str_contains( $themedSaid[0], 'section' ) );
\Nino\Filesystem::removeDir( ninoSandboxDir( $themed ) );

// --- D. Both states --------------------------------------------------------------------------------------

echo "\nD. Both states: the names basis has and the names it does not\n";

$sources = [
	'text'		=> '[text /template/page-basis/hero/title]',
	'button'	=> '[button /template/page-basis/hero/start href="/x"]',
	'image'		=> '[image /logo]',
	'spacer'	=> '[spacer size="2"]',
	'html'		=> '[html]<p>x</p>[/html]',
];
$off = basisSite( 'de_DE', false );
$on = basisSite();
$states = [];
$quiet = [];

foreach( $sources as $name => $source ) {

	ninoWarnings();
	$before = \Nino\Html::renderSource( $off, $source, [ 'template' => 'page-basis' ] );
	$saidOff = ninoWarnings();
	$after = \Nino\Html::renderSource( $on, $source, [ 'template' => 'page-basis' ] );
	$saidOn = ninoWarnings();
	$states[$name] = $before === '' && $after !== '' && preg_match( '/class="'. preg_quote( $name, '/' ). '[" ]/', $after ) === 1;
	$quiet[$name] = count( $saidOff ) === 1 && str_contains( $saidOff[0], '"'. $name. '"' ) && $saidOn === [];
}
check( 'where basis is off text, button, image, spacer and html are names nobody registered: nothing is drawn and the log says so once for each; where it is on the register draws each with the class of its name and the log says nothing',
	array_filter( $states ) === $states && array_filter( $quiet ) === $quiet );

// A page of the first releases: it calls [title] and the five. [title] is no name of basis, so the page is drawn without it in
// either state; the rest of the page is the register's where basis is on and is not drawn where it is off. A request says an
// unknown name once, so each state is a site of its own
$page = "<main>\n[title /template/page-basis/hero/title]\n". implode( "\n", $sources ). "\n</main>";
$pageSites = [ 'off' => basisSite( 'de_DE', false ), 'on' => basisSite() ];
ninoWarnings();
$pageOff = \Nino\Html::renderSource( $pageSites['off'], $page, [ 'template' => 'page-basis' ] );
$saidPageOff = ninoWarnings();
$pageOn = \Nino\Html::renderSource( $pageSites['on'], $page, [ 'template' => 'page-basis' ] );
$saidPageOn = ninoWarnings();
check( 'a page that calls [title] and the five: without basis it is the page with its six calls left out and one line in the log for each, with basis the five are drawn by the register and [title] alone is left out and said once',
	$pageOff === '<main>'. str_repeat( "\n", 7 ). '</main>' && count( $saidPageOff ) === 6 && str_contains( $saidPageOff[0], '"title"' )
	&& str_starts_with( $pageOn, "<main>\n\n" ) && str_ends_with( $pageOn, "\n</main>" ) && count( $saidPageOn ) === 1 && str_contains( $saidPageOn[0], '"title"' )
	&& array_filter( array_keys( $sources ), static fn( string $name ): bool => preg_match( '/class="'. preg_quote( $name, '/' ). '[" ]/', $pageOn ) !== 1 ) === [] );

// --- E. What must never be read -------------------------------------------------------------------------------

echo "\nE. What a value must never do\n";

$evil = [
	'a call'			=> '[template /templates/x] [section-title text="called"]',
	'a fill'			=> '[[/project/company/general/name]] [[content]] [[class]]',
	'a quote'			=> '"><script>alert(1)</script>\' onmouseover="x()',
	'a null byte'		=> "a\0b<scr\0ipt>x()</scr\0ipt>",
	'a megabyte'		=> str_repeat( 'x [y] ', 175000 ),
];
$element = static fn( string $value ): array => [ 'uri' => '/p', 'id' => 1, 'element' => [ 'f' => $value, 'h' => $value ], 'model' => [ 'f' => [ 'type' => 'string' ], 'h' => [ 'type' => 'string' ] ] ];
// What a page is made of is what a browser reads of it: no script, no frame, no attribute that runs, whatever the text says
$clean = static function( string $html ): bool {

	$document = new \DOMDocument();
	@$document->loadHTML( '<?xml encoding="utf-8"?><body>'. $html. '</body>', LIBXML_NOERROR | LIBXML_NOWARNING );

	foreach( $document->getElementsByTagName( '*' ) as $element ) {

		if( in_array( $element->nodeName, [ 'script', 'iframe', 'style', 'object', 'embed', 'form', 'input' ], true ) === true )
			return false;

		foreach( $element->attributes as $attribute )
			if( str_starts_with( $attribute->nodeName, 'on' ) === true || in_array( $attribute->nodeName, [ 'src', 'href', 'class', 'alt', 'id', 'width', 'height', 'loading', 'decoding', 'lang', 'hreflang', 'aria-label', 'aria-current', 'aria-hidden', 'target', 'rel', 'type', 'popover', 'popovertarget', 'popovertargetaction' ], true ) === false )
				return false;
	}

	return str_contains( $html, 'Söhne' ) === false;
};
$bad = [];

foreach( $evil as $label => $value ) {

	$context = [ 'element' => $element( $value ) ];
	ninoWarnings();

	foreach( [
		'section-title'		=> '[section-title .f]',
		'section-subtitle'	=> '[section-subtitle .f]',
		'text'				=> '[text .f]',
		'button'			=> '[button .f href="/x"]',
		'an alt text'		=> '[image /logo alt=".f"]',
		'a caption'			=> '[image /logo caption=".f"]',
		'an alt text of a logo'	=> '[footer-logo /logo alt=".f"]',
	] as $where => $source ) {

		$html = basisDraw( $appData, $source, $context );

		if( $clean( $html ) === false || strlen( $html ) > 7 * strlen( $value ) + 400 )
			$bad[] = $where. ' with '. $label;
	}

	$warned = ninoWarnings();

	if( $warned !== [] )
		$bad[] = $label. ' was said in the log';
}
check( 'a text, a field, an alt text, a caption and a label that spell a call, a fill, a quote, a null byte or fill a megabyte come out as text - nothing is read, nothing is drawn but what was asked, nothing stands as a tag'. ( $bad === [] ? '' : ' - '. implode( '; ', array_slice( $bad, 0, 5 ) ) ), $bad === [] );

// the same words stored as the Text panel stores them - entities for the angle brackets, the quotes and the square brackets - and written raw by a hand that bypassed it,
// read through the key of a text and through ~name: they are text, and what the entities spell is the text they spell
$stored = [
	'stored with entities'	=> '&lt;script&gt;alert(1)&lt;/script&gt; &quot;&gt;&lt;img src=x onerror=alert(1)&gt; &#91;template /templates/x&#93; &#91;&#91;/project/company/general/name&#93;&#93; &#91;section-title text=&quot;called&quot;&#93;',
	'stored raw'			=> '<script>alert(1)</script> "><img src=x onerror=alert(1)> [template /templates/x] [section-title text="called"]',
	'stored as control entities'	=> 'a&#1;b&#x7f;c&#0;d&#x1f;e <b>f&#x7f;g</b>',
	'stored with a control'	=> "a&#0;b\0c&#7;d<scr\0ipt>x()</scr\0ipt>",
];
$bad = [];

foreach( $stored as $label => $value ) {

	$keyed = basisSite();
	\Nino\Html::addFills( $keyed, [ '/template/page-basis/ev/t' => $value, '/template/page-basis/ev/blocks' => '<p>'. $value. '</p>' ], 'de_DE' );
	ninoWarnings();

	foreach( [
		'section-title'		=> '[section-title /template/page-basis/ev/t]',
		'section-subtitle'	=> '[section-subtitle /template/page-basis/ev/t]',
		'text'				=> '[text /template/page-basis/ev/blocks]',
		'button'			=> '[button /template/page-basis/ev/t href="/x"]',
		'an alt text'		=> '[image /logo alt="/template/page-basis/ev/t"]',
		'a caption'			=> '[image /logo caption="/template/page-basis/ev/t"]',
		'a logo'			=> '[footer-logo /logo alt="/template/page-basis/ev/t"]',
		'html'				=> '[html]<p>[[/template/page-basis/ev/t]]</p>[/html]',
		'a title in a link'	=> '[section-title /template/page-basis/ev/t href="page:/contact"]',
	] as $where => $source ) {

		$html = basisDraw( $keyed, $source );

		if( $clean( $html ) === false || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $html ) === 1 )
			$bad[] = $where. ' with '. $label;
	}

	if( ninoWarnings() !== [] )
		$bad[] = $label. ' was said in the log';
}
check( 'a text that was stored - with entities, raw, with a control character - and is read through a key or ~name comes out as text in a title, a text, a button, an alt text, a caption and a block of html'. ( $bad === [] ? '' : ' - '. implode( '; ', array_slice( $bad, 0, 5 ) ) ), $bad === [] );

// the title of a menu: as the lines of the menu say it, whatever it holds
$lines = new \ArrayObject();
\Nino\Callbacks::registerCallback( $appData, '/nino/navigation/lines', static function( array &$appData, array $args ) use ( $lines ): array {

	foreach( $lines as $line )
		$args['lines'][] = $line;

	return $args;
} );
$bad = [];

foreach( $evil as $label => $value ) {

	$lines->exchangeArray( [ '/evil:'. $value, '/second:fine', '/blank:   ', ':'. $value ] );
	unset( $appData[\Nino\Components::OPTIONS] );
	$html = basisDraw( $appData, '[footer-nav][footer-nav menu="footer"][header-nav]' );

	if( $clean( $html ) === false || str_contains( $html, "\0" ) === true || str_contains( $html, 'fine' ) === false || str_contains( $html, 'blank' ) === true )
		$bad[] = $label;
}
check( 'the title of an entry of a menu that spells a call, a fill, a quote, a null byte or fills a megabyte is text in the list; an entry with no title is none; no bracket is left to read'. ( $bad === [] ? '' : ' - '. implode( ', ', $bad ) ), $bad === [] );
$lines->exchangeArray( [ '/x:[a] [[b]]' ] );
check( 'every bracket of a title is an entity', str_contains( basisDraw( $appData, '[footer-nav]' ), '&#91;a&#93; &#91;&#91;b&#93;&#93;' ) );
$lines->exchangeArray( [] );

// what the feature writes itself - the alt text stored with a picture, the name of a language - has no control character, whatever was stored
$control = basisSite();
$control['/nino/html/images']['/logo']['alt'] = [ 'de_DE' => "L\0o\x01go<b>" ];
\Nino\Html::addFills( $control, [ '/_nino/locale/de_DE/name' => "De\0ut\x07sch" ], '*' );
$written = basisDraw( $control, '[image /logo][footer-logo /logo][footer-legal]' );
check( 'the alt text stored for a picture and the name of a language are written with no null byte and no control character, and what is left of them is text', preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $written ) !== 1 && substr_count( $written, 'alt="Logo&lt;b&gt;"' ) === 2 && str_contains( $written, '>Deutsch</a>' ) );

// an address that is none is no link: the button is a span, the title and the image have no anchor
$addresses = [ 'javascript:alert(1)', '//evil.test/x', '/\\evil.test', "java\tscript:alert(1)", "/ok\x01", "data:text/html,x", 'vbscript:x' ];
$bad = [];

foreach( $addresses as $address ) {

	$context = [ 'element' => $element( $address ) ];
	$html = basisDraw( $appData, '[button text="Go" href=".f"][section-title text="T" href=".f"][image /logo href=".f"]', $context );

	if( str_contains( $html, '<span class="button">Go</span>' ) === false || str_contains( $html, '<a ' ) === true || str_contains( $html, 'href=' ) === true )
		$bad[] = $address;
}
check( 'an address with javascript:, //host, a backslash, a scheme that is none or a control character is no link: the button is a span, the title and the picture have no anchor'. ( $bad === [] ? '' : ' - '. implode( ', ', $bad ) ), $bad === [] );
check( 'an address a browser may take is a link, with its characters escaped', basisDraw( $appData, '[button text="Go" href="https://example.test/?a=1&b=\'2\'"]' ) === '<a class="button" href="https://example.test/?a=1&amp;b=&#039;2&#039;">Go</a>' );

// --- F. The fixture page, drawn in both languages --------------------------------------------------------

echo "\nF. The fixture page, drawn in both languages\n";

$pages = [];

foreach( [ 'de_DE', 'en_US' ] as $locale ) {

	$site = basisSite( $locale );
	$pages[$locale] = basisDraw( $site, (string) file_get_contents( $fixtureDir. '/page.tpl' ) );

	$said = ninoWarnings();
	check( 'the page of '. $locale. ' is drawn with one thing said in the log: its picture that is a link and has no alt text', count( $said ) === 1 && str_contains( $said[0], 'no alt text' ) && str_contains( $said[0], 'story/photo' ) );
}

if( is_string( $record ) === true ) {

	is_dir( $record ) === true || mkdir( $record, 0755, true );

	foreach( $pages as $locale => $page )
		file_put_contents( $record. '/page.'. $locale. '.html', $page );

	echo 'recorded '. count( $pages ). " files\n";
	exit( 0 );
}

foreach( $pages as $locale => $page ) {

	$expected = (string) @file_get_contents( $fixtureDir. '/page.'. $locale. '.html' );
	check( 'the page of '. $locale. ' is drawn byte for byte as tests/fixtures/basis/page.'. $locale. '.html has it ('. strlen( $page ). ' bytes)', $expected !== '' && $page === $expected );
}

$allCalled = [];
foreach( $schemas as $name => $schema )
	if( preg_match( '/\['. preg_quote( $name, '/' ). '[\s\]\/]/', (string) file_get_contents( $fixtureDir. '/page.tpl' ) ) !== 1 )
		$allCalled[] = $name;
check( 'the fixture page calls every one of the twenty components'. ( $allCalled === [] ? '' : ' - '. implode( ', ', $allCalled ) ), $allCalled === [] );
check( 'a card is a column: one h1 on the page, three cards in one row, the whole card a link where it says link', substr_count( $pages['de_DE'], '<h1' ) === 1 && substr_count( $pages['de_DE'], 'section-col--variant-card' ) === 3 && substr_count( $pages['de_DE'], 'section-col--link' ) === 1 );

// --- H. The demos of the groups --------------------------------------------------------------------------

echo "\nH. The demos: a piece of a page for each group, read and drawn\n";

$demoSite = basisSite( 'en_US' );
$demoSource = static fn( string $group ): string => (string) \Nino\Components::groups( $demoSite )[$group]['demo'];
$demoGroups = array_keys( BASIS_COMPONENTS );

check( 'the folders hand over a demo for each of the six groups, and nothing is refused for it', $read['rejected'] === [] && array_column( $read['groups'], 'demo' ) !== [] && count( array_filter( array_column( $read['groups'], 'demo' ), static fn( string $text ): bool => $text !== '' ) ) === 6
	&& array_map( $demoSource, $demoGroups ) === array_map( static fn( string $group ): string => (string) file_get_contents( $basisDir. '/groups/'. $group. '/demo.tpl' ), $demoGroups ) );

$demoProblems = [];
$demoSeen = [];

foreach( BASIS_COMPONENTS as $group => $members ) {

	$source = $demoSource( $group );
	$notes = [];
	$tree = \Nino\Markup::parse( $source, \Nino\Components::containers( $demoSite ), $notes );

	if( $notes !== [] )
		$demoProblems[] = $group. ' is read with a note: '. $notes[0]['code'];

	if( \Nino\Components::demoProblem( $source, array_keys( $schemas ) ) !== null )
		$demoProblems[] = $group. ' breaks the rule of its file: '. \Nino\Components::demoProblem( $source, array_keys( $schemas ) );

	// Every call of the demo, in the order of the document: name => the attributes of each
	$calls = [];
	$walk = static function( array $nodes ) use ( &$walk, &$calls ): void {

		foreach( $nodes as $node )
			if( isset( $node['n'] ) === true ) {

				$calls[$node['n']][] = $node['a'];
				$walk( (array) $node['c'] );
			}
	};
	$walk( $tree );
	$demoSeen[$group] = $calls;

	// Every component of the group is in it
	foreach( $members as $name )
		if( isset( $calls[$name] ) === false )
			$demoProblems[] = $group. ' does not call '. $name;

	// Where a knob hangs on a choice of its component - section-row.gap on the gap of a row - every value of the choice is in it, a default one as a call that does not say it
	foreach( \Nino\Components::groups( $demoSite )[$group]['knobs'] as $key => $knob ) {

		[ $name, $word ] = explode( '.', $key );
		$declared = $schemas[$name]['attributes'][$word] ?? null;

		if( $declared === null || $declared['type'] !== 'choice' )
			continue;

		foreach( $declared['options'] as $value ) {

			$found = array_filter( $calls[$name] ?? [], static fn( array $attributes ): bool => ( $attributes[$word] ?? $declared['default'] ) === $value );

			if( $found === [] )
				$demoProblems[] = $group. ': '. $key. ' has no call with '. $word. '="'. $value. '"';
		}
	}

	// ...and every variant of a component of the group, the regular one as a call that names none
	foreach( $members as $name )
		foreach( $schemas[$name]['variants'] as $variant ) {

			$found = array_filter( $calls[$name] ?? [], static fn( array $attributes ): bool => ( $attributes['variant'] ?? 'regular' ) === $variant );

			if( $found === [] )
				$demoProblems[] = $group. ': '. $name. ' has no call of the variant '. $variant;
		}
}
$demoTones = array_unique( array_column( $demoSeen['button']['button'] ?? [], 'tone' ) );
sort( $demoTones );
check( 'the demo of the buttons shows every weight, and the ground dark as well as the light one', $demoTones === [ 'outline', 'primary', 'quiet', 'secondary' ] && in_array( 'dark', array_column( $demoSeen['button']['section-wrap'] ?? [], 'ground' ), true ) === true && in_array( 'base', array_column( $demoSeen['button']['section-wrap'] ?? [], 'ground' ), true ) === true );
check( 'each demo is read with no note, calls every component of its group, and shows every value of a choice a knob hangs on and every variant: the schema says which ('. count( $demoSeen ). ' demos)'
	. ( $demoProblems === [] ? '' : ' - '. implode( '; ', array_slice( $demoProblems, 0, 6 ) ) ), $demoProblems === [] );

// Drawn the way a caller draws it: renderSource() with nothing of the project's but its menus
ninoWarnings();
$drawProblems = [];

foreach( BASIS_COMPONENTS as $group => $members ) {

	$html = \Nino\Html::renderSource( $demoSite, $demoSource( $group ) );

	if( $html === '' )
		$drawProblems[] = $group. ' draws nothing';

	foreach( $members as $name )
		if( preg_match( '/class="'. preg_quote( $name, '/' ). '[ "]/', $html ) !== 1 )
			$drawProblems[] = $group. ' draws no '. $name;

	// What the project holds - the company in the copyright line apart, which is a component's own - is not in it, and a reference is not left written
	if( str_contains( $html, '/nino/placeholder/' ) === true || str_contains( $html, '[' ) === true || str_contains( $html, 'logo.png' ) === true )
		$drawProblems[] = $group. ' leaves a reference, a bracket or a picture of the project in the page';
}
$demoWarnings = ninoWarnings();
check( 'each demo is drawn by renderSource(), with every component of its group in the page, no picture of the project and nothing said in the log'. ( $drawProblems === [] ? '' : ' - '. implode( '; ', array_slice( $drawProblems, 0, 6 ) ) ), $drawProblems === [] && $demoWarnings === [] );

$landscape = \Nino\Html::renderSource( $demoSite, '[image /nino/placeholder/landscape decorative]' );
check( 'a placeholder is a picture of the kernel: a data: address with its size, in the figure, whatever slots the project has - even one of the same uri', str_contains( $landscape, '<img src="data:image/svg+xml;base64,' ) && str_contains( $landscape, ' width="800" height="600"' )
	&& ( static function() use ( $demoSite ): bool {

		$demoSite['/nino/html/images']['/nino/placeholder/landscape'] = [ 'label' => 'x', 'width' => 1, 'height' => 1, 'filename' => 'logo.png', 'alt' => [] ];

		return str_contains( \Nino\Html::renderSource( $demoSite, '[image /nino/placeholder/landscape decorative]' ), 'data:image/svg+xml' ) && str_contains( \Nino\Html::renderSource( $demoSite, '[image /nino/placeholder/landscape decorative]' ), 'logo.png' ) === false;
	} )()
	&& array_keys( \Nino\Images::PLACEHOLDERS ) === [ '/nino/placeholder/landscape', '/nino/placeholder/mark' ]
	&& array_filter( \Nino\Images::PLACEHOLDERS, static fn( array $placeholder ): bool => ( simplexml_load_string( $placeholder['svg'] ) !== false ) === false ) === [] );
ninoWarnings();

// --- G. Words, activation, sizes, cost -----------------------------------------------------------------------

echo "\nG. The words, the activation, the sizes and what a page costs\n";

$grammar = [];
foreach( [ 'de_DE', 'en_US' ] as $language ) {

	$fragment = include $basisDir. '/install/text/'. $language. '.php';

	foreach( array_keys( $fragment ) as $key )
		if( \Nino\Text::isGrammarKey( trim( $key, '[]' ) ) === false || str_starts_with( $key, '[[/module/basis/' ) === false )
			$grammar[] = $language. ' '. $key;
}
check( 'the words of the feature are keys of the grammar of the module basis, and the same in both languages'. ( $grammar === [] ? '' : ' - '. implode( ', ', $grammar ) ), $grammar === []
	&& array_keys( include $basisDir. '/install/text/de_DE.php' ) === array_keys( include $basisDir. '/install/text/en_US.php' ) );

$project = ninoSandbox( 'basis-activate' );
\Nino\AppData::writeContentData( $project, [ '/nino/modules', '/nino/locales/available', '/nino/locales/native', '/nino/http/routes' ] );
ninoWarnings();
$result = \Nino\Features::activate( $project, 'basis' );
$config = (array) \Nino\Filesystem::getFileContent( $project, '/config.php', [] );
$de = (array) \Nino\Filesystem::getFileContent( $project, '/text/de_DE.php', [] );
$en = (array) \Nino\Filesystem::getFileContent( $project, '/text/en_US.php', [] );
check( 'it is switched on in the Features panel: the class is listed, the version recorded, the three words are in the Text of both languages', $result === true && in_array( '\\Nino\\Modules\\Basis', (array) ( $config['/nino/modules'] ?? [] ), true ) === true
	&& ( $config['/nino/features']['basis']['version'] ?? '' ) === '1.0.0' && ( $de['[[/module/basis/navigation/primary]]'] ?? '' ) === 'Hauptnavigation' && ( $en['[[/module/basis/navigation/menu]]'] ?? '' ) === 'Menu' && count( $de ) === 3 && count( $en ) === 3 );
unset( $project['./nino/features/all'] );
check( 'it has nothing to remove: Remove is refused for a feature of the kernel, and switching it off lists it off again', is_string( \Nino\Features::remove( $project, 'basis' ) ) === true && \Nino\Features::deactivate( $project, 'basis' ) === true
	&& in_array( '\\Nino\\Modules\\Basis', (array) \Nino\Filesystem::getFileContent( $project, '/config.php', [] )['/nino/modules'], true ) === false );
ninoWarnings();
\Nino\Filesystem::removeDir( ninoSandboxDir( $project ) );

// sizes
$total = $bytes;
$parts = array_map( static fn( string $group ): int => (int) filesize( $basisDir. '/groups/'. $group. '/part/part.css' ), array_keys( $read['groups'] ) );
check( 'the six standard parts are 26 KB together at most ('. $total. ' bytes: '. implode( ', ', $parts ). ')', $total <= 26624 && count( $parts ) === 6 );

$listing = [];
foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $basisDir, \FilesystemIterator::SKIP_DOTS ) ) as $file )
	$listing[] = $file->getExtension();
check( 'the feature brings no script, and its parts no font or image: a file of css, php, json or a template, and nothing else', array_diff( array_unique( $listing ), [ 'php', 'tpl', 'css', 'json' ] ) === [] );

// the table of docs/features.md is worked out of the schemas
$docs = (string) file_get_contents( dirname( __DIR__ ). '/docs/features.md' );
check( 'the table of the components in docs/features.md is the one the schemas make', str_contains( $docs, basisTable( $schemas ) ) );

// the cost of a start page of seven sections: 70 calls, 28 of them texts. A clock says nothing on a machine that does other things as well, so the cost is what it is made
// of: the calls that are drawn and the elements they make grow with the sections of the page and no faster, the same tree is the same page at every draw, and a draw reads
// nothing from disk - no file is included while a tree is drawn. The time is told, and nothing hangs on it
$startPage = static function( int $sections ): array {

	$home = '';
	$fills = [];

	for( $s = 1; $s <= $sections; $s++ ) {

		$k = '/template/page-basis/s'. $s. '/';

		foreach( [ 'title' => 'Title '. $s, 'subtitle' => 'A subtitle that is a little longer', 'text' => 'A paragraph of text as a start page has it. It has two sentences.', 'more' => 'Learn more', 'side' => 'A second paragraph in the right column.', 'note' => 'And a third, short one.' ] as $n => $v )
			$fills[$k. $n] = $v;

		$home .= "[section-wrap id=\"s$s\" ground=\"alt\"]\n\t[section-row]\n\t\t[section-col size=\"100 m:50\"]\n\t\t\t[section-title ~title]\n\t\t\t[section-subtitle ~subtitle]\n\t\t\t[text ~text]\n\t\t\t[text ~more]\n\t\t[/section-col]\n\t\t[section-col size=\"100 m:50\" variant=\"card\"]\n\t\t\t[text ~side]\n\t\t\t[text ~note]\n\t\t[/section-col]\n\t[/section-row]\n[/section-wrap]\n";
	}


	return [ $home, $fills ];
};

[ $home, $fills ] = $startPage( 7 );
[ $homeBig, $fillsBig ] = $startPage( 28 );

$timed = basisSite();
\Nino\Html::addFills( $timed, $fills + $fillsBig, '*' );
$tree = \Nino\Markup::parse( $home, \Nino\Components::containers( $timed ), $notes );
$treeBig = \Nino\Markup::parse( $homeBig, \Nino\Components::containers( $timed ), $notes );
$calls = preg_match_all( '/\[(?!\/)[a-z]/', $home );
$callsBig = preg_match_all( '/\[(?!\/)[a-z]/', $homeBig );
$texts = preg_match_all( '/\[text /', $home );
$output = \Nino\Components::render( $timed, $tree, [ 'template' => 'page-basis' ] );
$outputBig = \Nino\Components::render( $timed, $treeBig, [ 'template' => 'page-basis' ] );
$included = count( get_included_files() );
$same = true;

for( $i = 0; $i < 50; $i++ )
	$same = $same === true && \Nino\Components::render( $timed, $tree, [ 'template' => 'page-basis' ] ) === $output;

$started = hrtime( true );

for( $i = 0; $i < 200; $i++ )
	\Nino\Components::render( $timed, $tree, [ 'template' => 'page-basis' ] );

echo sprintf( "  %d calls, %d of them texts, %d bytes drawn: %.3f ms a draw (told, not pinned)\n", $calls, $texts, strlen( $output ), ( hrtime( true ) - $started ) / 1e6 / 200 );
check( 'a start page of seven sections - 70 calls, 28 of them texts in the format blocks - is drawn whole: seven sections, no call left in it', $calls === 70 && $texts === 28 && substr_count( $output, '<section' ) === 7 && preg_match( '/\[[a-z]/', $output ) !== 1 );
check( '...and the same tree is the same page at every one of fifty draws, and no file is included while it is drawn', $same === true && count( get_included_files() ) === $included );
check( '...four times the sections are four times the calls, the sections and the paragraphs drawn, and the bytes follow the calls (the numbers of the titles apart): what is drawn grows with the page and no faster',
	$callsBig === 4 * $calls && substr_count( $outputBig, '<section' ) === 4 * substr_count( $output, '<section' ) && substr_count( $outputBig, '<p ' ) === 4 * substr_count( $output, '<p ' )
	&& strlen( $outputBig ) > 3.9 * strlen( $output ) && strlen( $outputBig ) < 4.2 * strlen( $output ) );

$cold = ninoSandbox( 'basis-cold' );
$cold['./nino/filesystem/path'] = dirname( __DIR__ );
$cold['/nino/modules'] = [ '\\Nino\\Modules\\Basis' ];
\Nino\Features::registerComponents( $cold );

/**	What a registration of the twenty includes, in a process of its own - so that nothing of what the suite included before is in the count: how many files of components/ and groups/ of Basis
 *	it includes (the schemas are read from them), how many files of the cache of the register, and how many components it registers. From the cache the first is 0 and the second 1: the
 *	cache is the one file it reads, and not one of the schemas is included; with the cache turned off the files are read and the cache is none	*/
$register = static function( bool $cache ) use ( $cold ): ?array {

	$script = sys_get_temp_dir(). '/nino-basis-register-'. getmypid(). '.php';
	file_put_contents( $script, '<?php define( "NINO_FEATURES_DIR", '. var_export( NINO_FEATURES_DIR, true ). ' ); require '. var_export( __DIR__. '/harness.php', true ). ';
		$cold = [ "./nino/uid" => '. var_export( ninoSandboxDir( $cold ), true ). ' ]; \\Nino\\AppData::prepare( $cold );
		$cold["./nino/filesystem/path"] = '. var_export( dirname( __DIR__ ), true ). '; $cold["./nino/filesystem/configpath"] = $cold["./nino/filesystem/contentpath"] = '. var_export( ninoSandboxDir( $cold ). '/private', true ). '; $cold["./nino/filesystem/publicpath"] = '. var_export( ninoSandboxDir( $cold ). '/public', true ). ';
		$cold["/nino/modules"] = [ "\\\\Nino\\\\Modules\\\\Basis" ]; $cold["/nino/dir"] = ""; $cold["/nino/markup/cache"] = '. ( $cache === true ? 'true' : 'false' ). ';
		\\Nino\\Features::registerComponents( $cold );
		$files = get_included_files();
		echo json_encode( [ count( array_filter( $files, static fn( string $file ): bool => preg_match( "~/Basis/(components|groups)/~", $file ) === 1 ) ), count( array_filter( $files, static fn( string $file ): bool => str_contains( $file, "/cache/register/" ) ) ), count( \\Nino\\Components::components( $cold ) ) ] );' );
	$out = trim( (string) shell_exec( escapeshellarg( PHP_BINARY ). ' '. escapeshellarg( $script ). ' 2>&1' ) );
	unlink( $script );

	return json_decode( $out, true );
};
$fromCache = $register( true );
$fromFiles = $register( false );
echo sprintf( "  registering the twenty: from the cache of the register %s, from the files %s (files of components and groups included, files of the cache included, components)\n", json_encode( $fromCache ), json_encode( $fromFiles ) );
check( 'the twenty register from the cache of the register without one of the files of components/ and groups/ being included - the cache is the one file read - and from the files where the cache is turned off',
	is_array( $fromCache ) === true && $fromCache === [ 0, 1, 20 ] && is_array( $fromFiles ) === true && $fromFiles[0] >= 20 && $fromFiles[1] === 0 && $fromFiles[2] === 20 );
\Nino\Filesystem::removeDir( ninoSandboxDir( $cold ) );

ninoDone( $appData );
