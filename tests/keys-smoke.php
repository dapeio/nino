<?php
declare(strict_types=1);
/**
 *	Nino
 *	keys-smoke.php	The grammar of a text key, /<namespace>/<category>/<part>/<name>,
 *									and everything Nino ships to it:
 *
 *										A. the text fragments of the units - the base unit, the page
 *										   units and the modules' install units - follow the grammar,
 *										   sit in the unit that may deliver them, and read the same in
 *										   both languages
 *										B. every key a shipped template reads exists: a runtime fill, a
 *										   key of the system, or a key one of the units delivers - and a
 *										   template reads only keys of its own category or of "common"
 *										C. the key literals in the kernel's and the workbench's code name a
 *										   namespace of the grammar, the system or a runtime fill
 *										D. no old key family is left anywhere, in code, templates,
 *										   texts or documents
 *										E. \Nino\Modules\Template::category()
 *										F. \Nino\Text::isGrammarKey()
 *
 *									The legal page keeps its names until the Legal module
 *									replaces it; where that is an exception, it is named.
 *
 *	Usage: php tests/keys-smoke.php
 */

require __DIR__. '/harness.php';

$root 		= dirname( __DIR__ );
$appData 	= ninoSandbox( 'keys' );

// The two fills the kernel names first, which need a directory to name
$appData['/nino/dir'] 	= '';
$appData['/nino/public'] = '/public';

// Keys the system writes itself: a page's details after its Element-URI, and
// a language's name after its code
const KEYS_SYSTEM_WEBPAGE 	= '#^/_nino/webpage(/.+)/(name|title|description|uri)$#';
const KEYS_SYSTEM_LOCALE 		= '#^/_nino/locale/[a-z]{2}_[A-Z]{2}/name$#';

// The one key of a feature a page unit delivers: the demo catalogue's
// newsletter specimen shows the label the Newsletter feature ships for
// markup any template writes
const KEYS_NAMED_EXCEPTION = [ 'page:.demo-catalogue' => [ '/feature/newsletter/label/submit' ] ];

/**
 *	@param		string		$dir
 *	@param		string		$extension	Without the dot
 *
 *	@return 	array										Absolute paths of what Nino ships, recursively, sorted
 */
function keysFiles( string $dir, string $extension ): array {

	if( is_dir( $dir ) === false )
		return [];

	// A checkout holds the project's own data beside what Nino ships: the wizard
	// writes private/ and public/, the catalogue features/, the cache _admin/.cache/.
	// None of it is shipped, and the wizard's legal link writes an old-looking key
	// into it
	$project = [ '/private/', '/public/', '/features/', '/app/', '/_admin/.cache/' ];

	$found = [];
	foreach( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {

		if( $file->isFile() !== true || $file->getExtension() !== $extension )
			continue;

		foreach( $project as $prefix )
			if( str_starts_with( $file->getPathname(), dirname( __DIR__ ). $prefix ) === true )
				continue 2;

		$found[] = $file->getPathname();
	}

	sort( $found );

	return $found;
}

/**
 *	@param		string		$path
 *	@param		string		$root
 *
 *	@return 	string									The path below the root
 */
function keysRelative( string $path, string $root ): string {
	return ltrim( substr( $path, strlen( $root ) ), '/' );
}

/**
 *	The keys of one text fragment, without their brackets
 *
 *	@param		string		$file
 *
 *	@return 	array										key => value
 */
function keysOfFragment( string $file ): array {

	$keys = [];
	foreach( is_file( $file ) === true ? (array) include $file : [] as $bracketKey => $value )
		$keys[ trim( (string) $bracketKey, '[]' ) ] = $value;

	return $keys;
}

// --- the units -----------------------------------------------------------------

$libraryDir = $root. '/_admin/install/library';
$unitDirs = [ 'base' => $libraryDir. '/base' ];

foreach( scandir( $libraryDir. '/pages' ) ?: [] as $page )
	if( $page !== '.' && $page !== '..' && is_dir( $libraryDir. '/pages/'. $page ) === true )
		$unitDirs['page:'. $page] = $libraryDir. '/pages/'. $page;

foreach( glob( $root. '/_nino/Nino/Modules/*/install', GLOB_ONLYDIR ) ?: [] as $installDir )
	$unitDirs['module:'. strtolower( basename( dirname( $installDir ) ) )] = $installDir;

$units = [];
foreach( $unitDirs as $id => $dir ) {

	$manifest = \Nino\Features::readUnitManifest( $dir ) ?? [];
	$text 		= [ 'global' => keysOfFragment( $dir. '/text/global.php' ) ];
	foreach( [ 'de_DE', 'en_US' ] as $locale )
		$text[$locale] = keysOfFragment( $dir. '/text/'. $locale. '.php' );

	// What it copies into a project: what the manifest lists, else every file of
	// its templates/ - the Maintenance unit has no manifest and is copied by hand
	$templates = [];
	foreach( $manifest['templates'] ?? [] as $file )
		$templates[] = (string) $file;
	if( $manifest === [] )
		foreach( glob( $dir. '/templates/*.tpl' ) ?: [] as $file )
			$templates[] = basename( $file );

	$units[$id] = [
		'id' 				=> $id,
		'dir' 			=> $dir,
		'manifest' 	=> $manifest,
		'text' 			=> $text,
		'keys' 			=> array_merge( array_keys( $text['global'] ), array_keys( $text['de_DE'] ), array_keys( $text['en_US'] ) ),
		'templates' => array_values( array_unique( $templates ) ),
		// The unit key a page unit names a module by (requiresModules)
		'unitKey' 	=> str_starts_with( $id, 'module:' ) === true ? (string) ( $manifest['key'] ?? substr( $id, strlen( 'module:' ) ) ) : $id,
	];
}

/** @return array	Every unit's key => unit id, for isset() */
$provided = [];
foreach( $units as $unit )
	foreach( $unit['keys'] as $key )
		$provided[$unit['id']][$key] = true;

$moduleByUnitKey = [];
foreach( $units as $unit )
	if( str_starts_with( $unit['id'], 'module:' ) === true )
		$moduleByUnitKey[$unit['unitKey']] = $unit['id'];

$runtimeFills = \Nino\Html::runtimeFillKeys( $appData );

$imageSlots = [];
foreach( $units as $unit )
	foreach( array_keys( $unit['manifest']['imageSlots'] ?? [] ) as $slot )
		$imageSlots[(string) $slot] = true;


// --- A. the fragments ------------------------------------------------------------

echo "A. Text fragments of the units\n";

check( 'the library has its base unit, the page units and the modules\' units', isset( $units['base'] ) === true && count( $units ) >= 12 && isset( $units['module:form'], $units['module:localepicker'], $units['module:maintenance'], $units['page:home'] ) === true );

$badShape = [];
foreach( $units as $unit )
	foreach( [ 'global', 'de_DE', 'en_US' ] as $file )
		foreach( $unit['text'][$file] as $key => $value ) {
			$allowed = isset( KEYS_NAMED_EXCEPTION[$unit['id']] ) === true && in_array( $key, KEYS_NAMED_EXCEPTION[$unit['id']], true ) === true;
			if( \Nino\Text::isGrammarKey( $key ) === false && $allowed === false
				&& ( $unit['id'] === 'module:localepicker' && preg_match( KEYS_SYSTEM_LOCALE, $key ) === 1 ) === false )
				$badShape[] = $unit['id']. ' '. $file. ' '. $key;
			if( is_string( $value ) === false )
				$badShape[] = $unit['id']. ' '. $file. ' '. $key. ' (no string)';
		}
$numbered = [];
foreach( $units as $unit )
	foreach( $unit['keys'] as $key )
		if( preg_match( '#/[0-9]+$#', $key ) === 1 )
			$numbered[] = $unit['id']. ' '. $key;
check( 'no key a unit delivers ends in a number: a name says what the text is, not which one it is'. ( $numbered === [] ? '' : ' - '. implode( ', ', array_slice( $numbered, 0, 6 ) ) ), $numbered === [] );

check( 'every key a unit delivers follows the grammar - or is the one system form a unit may deliver, a language\'s name from the Localepicker'. ( $badShape === [] ? '' : ' - '. implode( ', ', array_slice( $badShape, 0, 6 ) ) ), $badShape === [] );

$misplaced = [];
foreach( $units as $unit ) {

	$categories = [];
	foreach( $unit['templates'] as $template )
		if( \Nino\Modules\Template::category( $template ) !== null )
			$categories[] = \Nino\Modules\Template::category( $template );

	$module = str_starts_with( $unit['id'], 'module:' ) === true ? substr( $unit['id'], strlen( 'module:' ) ) : null;

	foreach( $unit['keys'] as $key ) {

		$allowed = isset( KEYS_NAMED_EXCEPTION[$unit['id']] ) === true && in_array( $key, KEYS_NAMED_EXCEPTION[$unit['id']], true ) === true;

		if( $allowed === true || \Nino\Text::isGrammarKey( $key ) === false )
			continue;

		[ , $namespace, $category ] = explode( '/', $key );

		$fits = match( $namespace ) {
			'project' 	=> $unit['id'] === 'base',
			'template' 	=> $category === 'common' ? $unit['id'] === 'base' : in_array( $category, $categories, true ),
			'module' 		=> $module !== null && $category === $module,
			default 		=> false,
		};

		if( $fits === false )
			$misplaced[] = $unit['id']. ' '. $key;
	}
}
check( 'a unit delivers only what it may: /project and /template/common are the base unit\'s, /template/<k> needs a template of the unit with that category, /module/<m> the unit of that module, no /feature'
	. ( $misplaced === [] ? '' : ' - '. implode( ', ', array_slice( $misplaced, 0, 6 ) ) ), $misplaced === [] );

$mismatched = [];
foreach( $units as $unit )
	if( array_keys( $unit['text']['de_DE'] ) !== array_keys( $unit['text']['en_US'] ) && array_diff( array_keys( $unit['text']['de_DE'] ), array_keys( $unit['text']['en_US'] ) ) !== []
		|| array_diff( array_keys( $unit['text']['en_US'] ), array_keys( $unit['text']['de_DE'] ) ) !== [] )
		$mismatched[] = $unit['id'];
check( 'English and German carry the same keys in every unit'. ( $mismatched === [] ? '' : ' - '. implode( ', ', $mismatched ) ), $mismatched === [] );

$doubled = [];
foreach( $units as $unit )
	foreach( array_keys( $unit['text']['global'] ) as $key )
		if( isset( $unit['text']['de_DE'][$key] ) === true || isset( $unit['text']['en_US'][$key] ) === true )
			$doubled[] = $unit['id']. ' '. $key;
check( 'no key is global and per language at once'. ( $doubled === [] ? '' : ' - '. implode( ', ', $doubled ) ), $doubled === [] );

$unresolved = [];
foreach( $units as $unit )
	foreach( [ 'global', 'de_DE', 'en_US' ] as $file )
		foreach( $unit['text'][$file] as $key => $value )
			if( is_string( $value ) === true && preg_match_all( '/\[\[([^\[\]]+)\]\]/', $value, $nested ) > 0 )
				foreach( $nested[1] as $inner )
					if( in_array( $inner, $runtimeFills, true ) === false && isset( $provided[$unit['id']][$inner] ) === false && isset( $provided['base'][$inner] ) === false )
						$unresolved[] = $unit['id']. ' '. $key. ' reads '. $inner;
check( 'every fill a value names - /project/website/general/url in a subject - is delivered by the unit, by the base unit or by the kernel'. ( $unresolved === [] ? '' : ' - '. implode( ', ', array_slice( $unresolved, 0, 6 ) ) ), $unresolved === [] );

// The blacklist of a unit follows the grammar too, and names keys the unit delivers or sets at runtime
$badBlacklist = [];
foreach( $units as $unit )
	foreach( $unit['manifest']['blacklist'] ?? [] as $entry )
		if( \Nino\Text::isGrammarKey( (string) $entry ) === false )
			$badBlacklist[] = $unit['id']. ' '. $entry;
check( 'the blacklist entries of every unit follow the grammar'. ( $badBlacklist === [] ? '' : ' - '. implode( ', ', $badBlacklist ) ), $badBlacklist === [] );

// What a page unit proposes for its page is the manifest's, and no key
$badSuggest = [];
foreach( $units as $unit ) {
	if( str_starts_with( $unit['id'], 'page:' ) === false )
		continue;
	$suggest = $unit['manifest']['suggest'] ?? [];
	foreach( [ 'uri', 'name', 'title', 'description' ] as $field )
		if( \Nino\Features::localizedValid( $suggest[$field] ?? null ) === false )
			$badSuggest[] = $unit['id']. ' '. $field;
	foreach( $unit['keys'] as $key )
		if( str_starts_with( $key, '/_nino/webpage' ) === true )
			$badSuggest[] = $unit['id']. ' delivers '. $key;
}
check( 'every page unit proposes uri, name, title and description in its manifest, and delivers no /_nino/webpage key'. ( $badSuggest === [] ? '' : ' - '. implode( ', ', $badSuggest ) ), $badSuggest === [] );

// What the base unit delivers, counted: a key added or dropped is a decision
$baseGlobal = count( $units['base']['text']['global'] );
$basePerLocale = count( $units['base']['text']['en_US'] );
check( 'the base unit delivers 40 keys: 21 global, 19 per language', $baseGlobal === 21 && $basePerLocale === 19 );
check( '...13 of them on its blacklist: the technical html values and the 11 values of the mails\' look', count( $units['base']['manifest']['blacklist'] ?? [] ) === 13
	&& array_diff( $units['base']['manifest']['blacklist'] ?? [], $units['base']['keys'] ) === [] );
check( 'the contact form unit delivers 15 keys per language, no global text and no blacklist: its mail words are templates\' and the look of the mails the base unit\'s',
	count( $units['module:form']['text']['en_US'] ) === 15 && $units['module:form']['text']['global'] === [] && isset( $units['module:form']['manifest']['blacklist'] ) === false );
check( 'the Localepicker unit names the two languages of the wizard, in global.php, after their codes', array_keys( $units['module:localepicker']['text']['global'] ) === [ '/_nino/locale/de_DE/name', '/_nino/locale/en_US/name' ] );
check( 'the Maintenance unit delivers its page\'s two words under /module/maintenance/page/', array_keys( $units['module:maintenance']['text']['en_US'] ) === [ '/module/maintenance/page/title', '/module/maintenance/page/text' ] );
echo "\n";


// --- B. the templates ------------------------------------------------------------

echo "B. Templates: every key they read exists, in their own category or common\n";

/**
 *	The keys one template reads: every [[/...]] from the inside out, [json /...],
 *	[image /...] and the strings of a section's json that name a key
 *
 *	@param		string		$source
 *
 *	@return 	array										[ [ key, composed, uri, image ], ... ] - composed: the key had a placeholder in it, replaced by "\x01" (and "/\x01" for the response uri)
 */
function keysReadBy( string $source ): array {

	$reads = [];
	$work 	= $source;

	do {
		$work = (string) preg_replace_callback( '/\[\[([^\[\]]+)\]\]/', static function( array $match ) use ( &$reads ): string {

			$key = $match[1];

			if( $key[0] !== '/' )
				return "\x01";

			$reads[] = [ 'key' => $key, 'composed' => str_contains( $key, "\x01" ), 'image' => false ];

			return $key === '/nino/http/response/uri' ? "/\x01" : "\x01";
		}, $work, -1, $replaced );
	} while( $replaced > 0 );

	if( preg_match_all( '/\[json\s+(\/[^\s\]]+)/', $source, $json ) > 0 )
		foreach( $json[1] as $key )
			$reads[] = [ 'key' => $key, 'composed' => false, 'image' => false ];

	if( preg_match_all( '/\[image\s+(\/[^\s\]]+)/', $source, $image ) > 0 )
		foreach( $image[1] as $key )
			$reads[] = [ 'key' => $key, 'composed' => false, 'image' => true ];

	// The bindings and the background of a section the Template Builder wrote
	if( preg_match_all( '/<!--\s*nino:section\s+(\{.*?\})\s*-->/s', $source, $sections ) > 0 )
		foreach( $sections[1] as $json ) {
			preg_match_all( '/"backgroundImage":"(\/[^"]+)"/', $json, $backgrounds );
			foreach( $backgrounds[1] as $key )
				$reads[] = [ 'key' => $key, 'composed' => false, 'image' => true ];
			preg_match_all( '/"(\/(?:template|project|feature|module|_nino)\/[^"]+)"/', preg_replace( '/"backgroundImage":"[^"]*"/', '', $json ), $bound );
			foreach( $bound[1] as $key )
				$reads[] = [ 'key' => $key, 'composed' => false, 'image' => false ];
		}

	return $reads;
}

/**
 *	@param		string		$check				A composed key with its placeholders replaced by "\x01"
 *
 *	@return 	bool										Whether it is one of the shapes a key may be put together in
 */
function keysComposedShape( string $key ): bool {

	// A page's details after the response uri, a language's name after the locale
	if( preg_match( '#^/_nino/webpage/\x01/(name|title|description)$#', $key ) === 1 || preg_match( '#^/_nino/locale/\x01/name$#', $key ) === 1 )
		return true;

	// Four segments, in which a placeholder is a whole segment or the identifier ending a list's part
	$segments = explode( '/', ltrim( $key, '/' ) );
	foreach( $segments as $index => $segment )
		if( str_contains( $segment, "\x01" ) === true && $segment !== "\x01" && ( $index !== 2 || preg_match( '#^[a-z0-9]+(-[a-z0-9]+)*-\x01$#', $segment ) !== 1 ) )
			return false;

	return \Nino\Text::isGrammarKey( str_replace( "\x01", 'x', $key ) );
}

// Every template there is, with what delivers it and what it may read from
$templateFiles = [];
foreach( $units as $unit )
	foreach( $unit['templates'] as $template )
		$templateFiles[] = [ 'unit' => $unit['id'], 'name' => $template, 'file' => $unit['dir']. '/templates/'. $template, 'internal' => false ];
foreach( glob( $root. '/_nino/Nino/Modules/*/templates/*.tpl' ) ?: [] as $file )
	$templateFiles[] = [ 'unit' => 'module:'. strtolower( basename( dirname( dirname( $file ) ) ) ), 'name' => basename( $file ), 'file' => $file, 'internal' => true ];

$missingFiles = [];
$byName = [];
foreach( $templateFiles as $template ) {
	if( is_file( $template['file'] ) === false )
		$missingFiles[] = $template['unit']. ' '. $template['name'];
	elseif( $template['internal'] === false )
		$byName[$template['name']][] = $template['file'];
}
check( 'every template a unit lists is in its templates/ directory'. ( $missingFiles === [] ? '' : ' - '. implode( ', ', $missingFiles ) ), $missingFiles === [] );

$notListed = [];
foreach( $units as $unit )
	if( $unit['manifest'] !== [] )
		foreach( glob( $unit['dir']. '/templates/*.tpl' ) ?: [] as $file )
			if( in_array( basename( $file ), $unit['templates'], true ) === false )
				$notListed[] = $unit['id']. ' '. basename( $file );
check( '...and every file of it is listed by the unit\'s manifest'. ( $notListed === [] ? '' : ' - '. implode( ', ', $notListed ) ), $notListed === [] );

$different = [];
foreach( $byName as $name => $files )
	if( count( $files ) > 1 && count( array_unique( array_map( 'md5_file', $files ) ) ) > 1 )
		$different[] = $name;
check( 'a template two units deliver by the same file name is one template, byte for byte'. ( $different === [] ? '' : ' - '. implode( ', ', $different ) ), $different === [] );

$tooMany = [];
foreach( $templateFiles as $template ) {
	if( $template['internal'] === false || is_file( $template['file'] ) === false )
		continue;
	foreach( keysReadBy( (string) file_get_contents( $template['file'] ) ) as $read )
		if( str_starts_with( $read['key'], '/template/' ) === true && str_starts_with( $read['key'], '/template/common/' ) === false )
			$tooMany[] = $template['unit']. ' '. $template['name']. ' '. $read['key'];
}
check( 'the module\'s internal templates carry no key of a template: they read the module\'s, the base unit\'s and the common ones'. ( $tooMany === [] ? '' : ' - '. implode( ', ', $tooMany ) ), $tooMany === [] );

$unknown = [];
$strangers = [];
$readCount = 0;
foreach( $templateFiles as $template ) {

	if( is_file( $template['file'] ) === false )
		continue;

	$unit 			= $units[$template['unit']];
	$category 	= \Nino\Modules\Template::category( $template['name'] );
	$required 	= array_map( static fn( string $key ): string => $moduleByUnitKey[$key] ?? $key, (array) ( $unit['manifest']['requiresModules'] ?? [] ) );
	$sources 		= array_merge( [ $unit['id'], 'base' ], $required );

	foreach( keysReadBy( (string) file_get_contents( $template['file'] ) ) as $read ) {

		$key = $read['key'];
		$readCount++;

		// The legal page's footer link, until the Legal module replaces it
		if( preg_match( '#^/website/legal/(uri|name)$#', $key ) === 1 && $template['name'] === 'html-footer-legal.tpl' )
			continue;

		if( $read['composed'] === true ) {
			if( keysComposedShape( $key ) === false )
				$unknown[] = $template['name']. ' reads the composed key '. str_replace( "\x01", '[[…]]', $key );
			continue;
		}

		if( in_array( ltrim( $key, '' ), $runtimeFills, true ) === true )
			continue;

		if( preg_match( KEYS_SYSTEM_WEBPAGE, $key ) === 1 || preg_match( KEYS_SYSTEM_LOCALE, $key ) === 1 )
			continue;

		if( $read['image'] === true && \Nino\Text::isGrammarKey( $key ) === false ) {
			if( isset( $imageSlots[$key] ) === false )
				$unknown[] = $template['name']. ' shows the image slot '. $key. ' that no unit declares';
			continue;
		}

		if( \Nino\Text::isGrammarKey( $key ) === false ) {
			$unknown[] = $template['name']. ' reads '. $key;
			continue;
		}

		// A key of the grammar: of its own category or common - and, unless it is an image slot, one a unit delivers
		[ , $namespace, $keyCategory ] = explode( '/', $key );
		if( $namespace === 'template' && $keyCategory !== 'common' && $keyCategory !== $category )
			$strangers[] = $template['name']. ' reads '. $key;

		if( $read['image'] === false ) {
			$found = false;
			foreach( $sources as $source )
				if( isset( $provided[$source][$key] ) === true )
					$found = true;
			if( $found === false && ( KEYS_NAMED_EXCEPTION[$template['unit']] ?? [] ) !== [] && in_array( $key, KEYS_NAMED_EXCEPTION[$template['unit']], true ) === true )
				$found = true;
			if( $found === false )
				$unknown[] = $template['name']. ' reads '. $key. ', which no unit it may use delivers';
		}
	}
}
check( 'the templates read keys ('. $readCount. ' reads): every one is a runtime fill, a key of the system, a placeholder put together in a shape that is allowed, or a key its own unit, the base unit or a module it requires delivers'
	. ( $unknown === [] ? '' : ' - '. implode( '; ', array_slice( array_unique( $unknown ), 0, 6 ) ) ), $unknown === [] && $readCount > 150 );
check( 'a template reads template keys of its own category or of /template/common only (the legal page and the demo catalogue have no category, so only common)'
	. ( $strangers === [] ? '' : ' - '. implode( '; ', array_slice( array_unique( $strangers ), 0, 6 ) ) ), $strangers === [] );

// A template without a category carries no keys of its own - what is delivered has a template with that category
check( 'the two templates without a category deliver no key of a template: .demo-catalogue.tpl and page-legal.<xx_XX>.tpl', \Nino\Modules\Template::category( '.demo-catalogue.tpl' ) === null && \Nino\Modules\Template::category( 'page-legal.de_DE.tpl' ) === null
	&& array_filter( $units['page:legal']['keys'], static fn( string $key ): bool => str_starts_with( $key, '/template/' ) ) === []
	&& array_filter( $units['page:.demo-catalogue']['keys'], static fn( string $key ): bool => str_starts_with( $key, '/template/' ) ) === [] );

// The frames carry the keys of their own category, delivered by the base unit that delivers the frames
check( 'the frames are frame-header.tpl and frame-footer.tpl, and their words are delivered with them', in_array( 'frame-header.tpl', $units['base']['templates'], true ) === true && in_array( 'frame-footer.tpl', $units['base']['templates'], true ) === true
	&& isset( $provided['base']['/template/frame-header/navigation/label'], $provided['base']['/template/frame-footer/contact/title'], $provided['base']['/template/frame-footer/label/address'] ) === true );
echo "\n";


// --- C. the code -----------------------------------------------------------------

echo "C. Key literals in the code\n";

$codeFiles = array_merge(
	keysFiles( $root. '/_nino', 'php' ), keysFiles( $root. '/_nino', 'js' ),
	keysFiles( $root. '/_admin', 'php' ), keysFiles( $root. '/_admin', 'js' )
);

$namespaces = [ 'template', 'project', 'feature', 'module', '_nino', 'nino' ];
$stray = [];
$literals = 0;
foreach( $codeFiles as $file ) {

	$relative = keysRelative( $file, $root );
	$source 	= (string) file_get_contents( $file );

	// The legal page's link, until the Legal module replaces it
	if( $relative === '_admin/install/Install.php' )
		$source = (string) preg_replace( '#/website/legal/[\w{},*-]*#', '', $source );

	$candidates = [];
	if( preg_match_all( '/\[\[(\/[^\[\]\s\'"]+)/', $source, $found ) > 0 )
		$candidates = array_merge( $candidates, $found[1] );
	if( preg_match_all( '/renderTextfill\(\s*[^,()]+,\s*\'(\/[^\']*)\'/', $source, $found ) > 0 )
		$candidates = array_merge( $candidates, $found[1] );
	if( preg_match_all( '/getText\(\s*\'(\/[^\']*)\'/', $source, $found ) > 0 )
		$candidates = array_merge( $candidates, $found[1] );
	if( preg_match( '/DEFAULT_KEYS\s*=\s*\[([^\]]*)\]/', $source, $found ) === 1 && preg_match_all( '/\'(\/[^\']*)\'/', $found[1], $prefixes ) > 0 )
		$candidates = array_merge( $candidates, $prefixes[1] );

	foreach( $candidates as $key ) {

		$segment = explode( '/', ltrim( $key, '/' ) )[0];

		// The workbench's own words are the workbench's
		if( $segment === '_admin' )
			continue;

		$literals++;

		if( in_array( $segment, $namespaces, true ) === false )
			$stray[] = $relative. ' '. $key;
	}
}
check( 'every key literal in the kernel and the workbench ('. $literals. ') names a namespace of the grammar, the system or a runtime fill'. ( $stray === [] ? '' : ' - '. implode( '; ', array_slice( array_unique( $stray ), 0, 8 ) ) ), $stray === [] && $literals > 40 );

$jstextKeys = (string) file_get_contents( $root. '/_nino/Nino/Modules/Jstext/Jstext.php' );
check( 'the inline block publishes the three groups of words the public scripts read, and each follows the grammar',
	preg_match( '/DEFAULT_KEYS\s*=\s*\[\s*\'\/module\/form\/info\/\',\s*\'\/feature\/newsletter\/info\/\',\s*\'\/template\/common\/slider\/\'\s*\]/', $jstextKeys ) === 1 );

$scripts = (string) file_get_contents( $root. '/_nino/Nino.ui.js' );
check( 'the public script asks for exactly those groups', preg_match_all( '/getText\(\s*\'(\/[^\']*)\'/', $scripts, $asked ) > 0
	&& array_filter( $asked[1], static fn( string $key ): bool => preg_match( '#^/(module/form/info|feature/newsletter/info|template/common/slider)/#', $key ) !== 1 ) === [] );
echo "\n";


// --- D. old forms -----------------------------------------------------------------

echo "D. No old key family is left\n";

$old = [
	'/page-[a-z0-9][a-z0-9-]*/', '/page-(?=[\'"`\[<])', '/webpage(?![\w-])', '/company/', '/website/', '/global/',
	'/form/(?:label|info|email|subject|required|title)', '/mail/(?:sender|style|owner|user|newsletter)',
	'/slider/label', '/newsletter/(?:label|info|page|confirm|unsubscribe)', '/maintenance/(?:title|text)', '/nino/path',
	'/nino/locales/(?:title|locale)', '/cookiebanner/', '/date/year',
];
// A family that ends in a word ends there: /slider/label is not /slider/labels. One that
// ends in a slash goes on into the key
$oldPattern = '~(?<![\w.-])(?:'. implode( '|', array_map( static fn( string $family ): string => str_ends_with( $family, '/' ) === true ? $family : $family. '(?![\w-])', $old ) ). '|theme\.(?:header|footer)(?![\w-]))~';

// The legal page and its footer link keep their names, until the Legal module
// replaces them: named here, and in the documents that name the exception
$legalExempt = static fn( string $relative ): bool => str_starts_with( $relative, '_admin/install/library/pages/legal/' ) === true
	|| $relative === '_admin/install/Install.php' || in_array( $relative, [ 'docs/development.md', 'docs/development.de.md' ], true ) === true;

$leftovers = [];
$scanned = 0;
$shipped = [];
foreach( [ 'php', 'js', 'tpl', 'css', 'md' ] as $extension )
	foreach( keysFiles( $root, $extension ) as $file )
		$shipped[] = $file;

foreach( $shipped as $file ) {

	$relative = keysRelative( $file, $root );

	if( str_starts_with( $relative, 'tests/' ) === true || str_starts_with( $relative, '.git/' ) === true || $relative === 'CHANGELOG.md' || str_contains( $relative, 'node_modules/' ) === true || str_starts_with( $relative, 'vendor/' ) === true )
		continue;

	$scanned++;

	foreach( explode( "\n", (string) file_get_contents( $file ) ) as $number => $line ) {

		if( $legalExempt( $relative ) === true )
			$line = (string) preg_replace( '#/website/legal/[\w{},*-]*#', '', $line );

		if( preg_match( $oldPattern, $line, $hit ) === 1 )
			$leftovers[] = $relative. ':'. ( $number + 1 ). ' '. trim( $hit[0] );
	}
}
check( 'no shipped file ('. $scanned. ' of them: code, templates, texts, stylesheets, documents) names a key family of an old form, a page text of the old shape, /webpage, /date/year or a theme.* frame'
	. ( $leftovers === [] ? '' : ' - '. implode( '; ', array_slice( $leftovers, 0, 10 ) ). ( count( $leftovers ) > 10 ? ' ... '. count( $leftovers ). ' in all' : '' ) ), $leftovers === [] && $scanned > 200 );

// The pattern is no mere formality: it finds the forms it is for, and leaves the new ones alone
$probeHits = [];
foreach( [ '[[/company/name]]', '/page-home/welcome/title', '[[/webpage/home/title]]', '/form/label/email', '/mail/user/title', '/date/year', 'theme.header.tpl', '[[/slider/label/prev]]', '--token: [[/nino/path]];' ] as $probe )
	if( preg_match( $oldPattern, $probe ) === 1 )
		$probeHits[] = $probe;
$probeMisses = [];
foreach( [ '[[/project/company/general/name]]', '/template/page-home/welcome/title', '[[/_nino/webpage/home/title]]', '/template/common/form/email', '/template/mail-user/intro/title', '/nino/date/year', 'frame-header.tpl', '/embed/abc', '/posts/my-first-post', '/feature/newsletter/label/submit', '/templates/page-home' ] as $probe )
	if( preg_match( $oldPattern, $probe ) === 1 )
		$probeMisses[] = $probe;
check( 'the pattern finds each of the old forms it is meant for, and none of the new ones - nor a url path such as /embed/ or a template path', count( $probeHits ) === 9 && $probeMisses === [] );
echo "\n";


// --- E. Template::category() -----------------------------------------------------

echo "E. \\Nino\\Modules\\Template::category()\n";

$categoryCases = [
	'page-home.tpl' 															=> 'page-home',
	'/templates/page-home' 												=> 'page-home',
	'page-404.tpl' 																=> 'page-404',
	'page-2026-home.tpl' 													=> 'page-2026-home',
	'html-footer.tpl' 														=> 'html-footer',
	'html-footer-nav.tpl' 												=> 'html-footer-nav',
	'frame-header.tpl' 														=> 'frame-header',
	'mail-user.tpl' 															=> 'mail-user',
	'common.tpl' 																	=> 'common',
	'page-common.tpl' 														=> 'page-common',
	'/templates/page-legal.[[/nino/http/response/locale]]' => null,
	'page-legal.de_DE.tpl' 												=> null,
	'.demo-catalogue.tpl' 												=> null,
	'theme.header.tpl' 														=> null,
	'a/x.tpl' 																		=> null,
	'page-Foo.tpl' 																=> null,
	'page-a.b.tpl' 																=> null,
	'page_home.tpl' 															=> null,
	'.tpl' 																				=> null,
	'' 																						=> null,
];
foreach( $categoryCases as $name => $expected )
	check( 'category( \''. $name. '\' ) is '. var_export( $expected, true ), \Nino\Modules\Template::category( (string) $name ) === $expected );
echo "\n";


// --- F. Text::isGrammarKey() -----------------------------------------------------

echo "F. \\Nino\\Text::isGrammarKey()\n";

foreach( [ '/project/catalog/list/title', '/template/page-404/hero/title', '/template/common/form/submit', '/feature/lightbox/controls/close', '/module/form/info/required', '/project/website/general/url', '/template/page-2026-home/intro/title' ] as $good )
	check( "$good is a key of the grammar", \Nino\Text::isGrammarKey( $good ) === true );
foreach( [ '/template/page-home/title', '/global/phone', '/Template/x/y/z', '/template/page_home/x/y', '/_nino/webpage/home/title', '/_admin/common/word/title', '/project/a/b/c/d', '/project/a/b/', 'project/a/b/c', '/project/a.b/c/d', '/project//b/c', '/project/a/b/c-', '/project/a/b/-c', "/project/a/b/c\n", '/other/a/b/c', '' ] as $bad )
	check( 'it refuses '. json_encode( $bad ), \Nino\Text::isGrammarKey( $bad ) === false );

ninoDone( $appData );
