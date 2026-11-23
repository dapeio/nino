<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	admin-address-smoke.php	Dependency-free smoke test for the one form the workbench's addresses
 *													are written in, #panel/tab/part...: \Nino\AdminAddress writes the
 *													same address as the router of the shell does (the table is
 *													tests/fixtures/addresses.json, which admin-address-js-smoke.js holds the
 *													router to), the registry names every screen of a panel by one name that
 *													the panes carry as data-address-tab, and the links the server writes - the
 *													notices of the Dashboard - are addresses of it. Runs against an isolated
 *													sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/admin-address-smoke.php
 */

require __DIR__. '/harness.php';

/** A panel with tabs of a feature's kind: the uri of the panel in front of the name, the kernel's kind with none, and two that want a name another screen has */
class AddrFramesTab {
	public static function actions(): array { return []; }
	public static function nav(): array { return [ 'addr-frames', 'Frames', 10 ]; }
}
class AddrPlainTab {
	public static function actions(): array { return []; }
	public static function nav(): array { return [ 'plain', 'Plain', 20 ]; }
}
class AddrClashTab {
	public static function actions(): array { return []; }
	public static function nav(): array { return [ 'addr-plain', 'Clash', 30 ]; }
}
class AddrSelfTab {
	public static function actions(): array { return []; }
	public static function nav(): array { return [ 'addr-addr', 'Self', 40 ]; }
}
class AddrPanel {
	public static function actions(): array { return []; }
	public static function nav(): array { return [ 'addr', 'Addr', 58, 'content' ]; }
	public static function tabs(): array { return [ 'AddrFramesTab', 'AddrPlainTab', 'AddrClashTab', 'AddrSelfTab' ]; }
}
class AddrModule {
	public static function adminPanels( array &$appData ): array { return [ 'AddrPanel' ]; }
}

$appData = ninoSandbox( 'admin-address' );
$appData['/nino/install/completed'] = true;
ninoUser( $appData, 'dev@example.com' );

$fixture = json_decode( (string) file_get_contents( __DIR__. '/fixtures/addresses.json' ), true );


// --- the table both languages write by -----------------------------------------------------------

/**
 *	The panel a screen of the table belongs to, and the tab it is: '' for the panel's own screen
 *
 *	@param		array 		$structure		panel => [ [ screen, tab ], ... ]
 *	@param		string		$screen
 *
 *	@return 	array											[ panel, tab ]
 */
function addrWhere( array $structure, string $screen ): array {

	foreach( $structure as $panel => $screens ) {

		if( $panel === $screen )
			return [ $panel, '' ];

		foreach( $screens as $entry )
			if( $entry[0] === $screen )
				return [ $panel, $screen ];
	}

	return [ $screen, '' ];
}

check( 'every case of the table is written as the table has it - the same address the router of the browser writes', array_reduce(
	$fixture['cases'],
	static function( bool $carry, array $case ) use ( $fixture ): bool {
		[ $panel, $tab ] = addrWhere( $fixture['structure'], $case['screen'] );
		// A word of the table is { word : 'new' }, a key a string
		$parts = array_map( static fn( mixed $part ): array|string => is_array( $part ) === true ? \Nino\AdminAddress::word( (string) $part['word'] ) : $part, $case['parts'] );
		return $carry === true && \Nino\AdminAddress::of( $panel, $parts, $tab ) === $case['hash'];
	},
	true
) );

check( 'the table has what the browser needs of a key: a slash, a space, an umlaut, a percent sign, an at sign that is no word, and the word itself', ( static function() use ( $fixture ): bool {
	$hashes = implode( "\n", array_column( $fixture['cases'], 'hash' ) );
	return str_contains( $hashes, '%2F' ) && str_contains( $hashes, '%20' ) && str_contains( $hashes, '%C3%BC' ) && str_contains( $hashes, '%25' ) && str_contains( $hashes, '%40' ) && str_contains( $hashes, '/@new' ) && count( $fixture['cases'] ) >= 25;
} )() );

check( 'a word keeps its at sign and a key never does: a key that reads @new is the key %40new and the word is made by word(), a key called new is written as it is', \Nino\AdminAddress::segment( \Nino\AdminAddress::word( 'new' ) ) === '@new'
	&& \Nino\AdminAddress::segment( '@new' ) === '%40new' && \Nino\AdminAddress::segment( 'new' ) === 'new' && \Nino\AdminAddress::segment( '@New' ) === '%40New' && \Nino\AdminAddress::segment( '@' ) === '%40' && \Nino\AdminAddress::segment( '@a b' ) === '%40a%20b'
	&& \Nino\AdminAddress::segment( '@a_b' ) === '%40a_b' && \Nino\AdminAddress::segment( "@new\n" ) === '%40new%0A' && \Nino\AdminAddress::segment( '@1' ) === '%401' );
check( 'an array that says it is a word is written as one where it is a word, and as the key of the text it says where it is not - the rule of the router of the browser, by the same table', array_reduce( $fixture['wordObjects'], static fn( bool $carry, array $case ): bool => $carry === true && \Nino\AdminAddress::segment( $case['object'] ) === $case['segment'], true ) );
check( '...a name that is no lowercase word is no word, and an array that says it is one is held to it', array_reduce( [ '', 'New', '1x', 'a b', '@new', 'a/b' ], static function( bool $carry, string $name ): bool {
	try {
		\Nino\AdminAddress::word( $name );
		return false;
	} catch( \InvalidArgumentException $e ) {
		return $carry;
	}
}, true ) );

check( 'a key is one segment: a slash is %2F, whatever else it holds - and the characters the browser leaves, the server leaves', \Nino\AdminAddress::segment( '/a/b' ) === '%2Fa%2Fb' && \Nino\AdminAddress::segment( "a!'()*~._-b" ) === "a!'()*~._-b"
	&& \Nino\AdminAddress::segment( 'a,b;c:d=e@f' ) === 'a%2Cb%3Bc%3Ad%3De%40f' );

check( 'the panel as it starts is the panel alone, a tab is a name of its panel, and the own screen with parts is named again', \Nino\AdminAddress::of( 'elements' ) === '#elements'
	&& \Nino\AdminAddress::of( 'elements', [], 'types' ) === '#elements/types' && \Nino\AdminAddress::of( 'elements', [ 'blog' ] ) === '#elements/elements/blog' && \Nino\AdminAddress::of( 'builder', [ 'left' ], 'builder-frames' ) === '#builder/frames/left' );

// What an address is made of, whatever it is handed: nothing a hash cannot carry, nothing that is a second segment
$hostile = [ "a\0b", "a\nb", "a\rb", "\t", str_repeat( 'x', 100000 ), '../../etc/passwd', '..', '%00', '%2e%2e', '"><script>alert(1)</script>', "a\u{202E}b", "\u{FEFF}", 'ä', "\xC3\x28", "\xFF\xFE", 'a b', '#', '?', '@new', '@', '/', '//', '\\' ];
check( 'whatever a key holds - a null byte, a line break, a path out, markup, bytes that are no text - it is one segment of the characters of an address and nothing else', array_reduce(
	$hostile,
	static fn( bool $carry, string $key ): bool => $carry === true && preg_match( '/^#elements\/elements\/[A-Za-z0-9._~!*\'()%@-]+$/D', \Nino\AdminAddress::of( 'elements', [ $key ] ) ) === 1
		&& substr_count( \Nino\AdminAddress::of( 'elements', [ $key ] ), '/' ) === 2,
	true
) );
check( '...and what an address of the browser makes of the one a server wrote is the key that was written: the percent decoding is its own inverse', array_reduce(
	$hostile,
	static fn( bool $carry, string $key ): bool => $carry === true && rawurldecode( substr( \Nino\AdminAddress::of( 'elements', [ $key ] ), strlen( '#elements/elements/' ) ) ) === $key,
	true
) );

check( 'the class is the kernel\'s own: a public request, which never loads the workbench, finds it', ( static function(): bool {
	$php = escapeshellarg( PHP_BINARY );
	$out = (string) shell_exec( $php. ' -r '. escapeshellarg( 'require '. var_export( dirname( __DIR__ ). '/_nino/Nino.php', true ). '; echo \Nino\AdminAddress::of( "elements", [ "a b" ] ), class_exists( "\Nino\Admin\Panels", false ) ? " loaded" : " alone";' ). ' 2>&1' );
	return $out === '#elements/elements/a%20b alone';
} )() );


// --- the registry: one name for every screen ----------------------------------------------------------

$modules = $appData;
$modules['/nino/modules'] = [ '\\Nino\\Modules\\Navigation', '\\Nino\\Modules\\Form', '\\Nino\\Modules\\Maintenance' ];
$registry = \Nino\Admin\Admin::panels( $modules );

// panel => [ [ screen, tab ], ... ], as the table has it
$structure = [];
foreach( $registry as $uri => $panel ) {
	$structure[$uri] = [];
	if( $panel['tabs'] !== [] ) {
		$structure[$uri][] = [ $uri, $panel['addressTab'] ];
		foreach( $panel['tabs'] as $tab )
			$structure[$uri][] = [ $tab['uri'], $tab['addressTab'] ];
	}
}
ksort( $structure );
$tabled = $fixture['structure'];
ksort( $tabled );

check( 'the structure the table writes addresses by is the registry\'s: every panel of the workbench and its tabs, each with its name in an address', $structure === $tabled );
check( 'a panel\'s own screen is named like the panel, and a tab of the kernel by its uri', array_reduce( $registry, static fn( bool $carry, array $panel ): bool => $carry === true && $panel['addressTab'] === $panel['uri'], true )
	&& $registry['elements']['tabs']['types']['addressTab'] === 'types' && $registry['users']['tabs']['recoverypw']['addressTab'] === 'recoverypw' && $registry['images']['tabs']['favicon']['addressTab'] === 'favicon' );

check( 'the name a tab has is its uri without the panel\'s name and a hyphen before it, its uri where it does not begin with that', \Nino\AdminAddress::tab( 'builder', 'builder-frames' ) === 'frames'
	&& \Nino\AdminAddress::tab( 'themes', 'themes-settings' ) === 'settings' && \Nino\AdminAddress::tab( 'elements', 'types' ) === 'types' && \Nino\AdminAddress::tab( 'a', 'a-b-c' ) === 'b-c'
	&& \Nino\AdminAddress::tab( 'a', 'a-' ) === 'a-' && \Nino\AdminAddress::tab( 'a', 'ab-c' ) === 'ab-c' && \Nino\AdminAddress::tab( 'a', 'a' ) === 'a' );

// A panel of a module with tabs of both kinds, and two that want a name another screen has
$withAddr = $appData;
$withAddr['/nino/modules'] = [ 'AddrModule' ];
$ignored = ninoWarnings();
$addr = \Nino\Admin\Admin::panels( $withAddr )['addr'] ?? null;
$warnings = ninoWarnings();

check( 'the tabs of a feature\'s panel are named by their uri without the panel\'s, and the first claim of a name stands: the tab with the name another screen has is refused with a warning, the one with the name of the panel too',
	$addr !== null && array_map( static fn( array $tab ): string => $tab['uri']. ':'. $tab['addressTab'], $addr['tabs'] ) === [ 'addr-frames' => 'addr-frames:frames', 'plain' => 'plain:plain' ]
	&& count( $warnings ) === 2 && str_contains( $warnings[0], 'AddrClashTab' ) && str_contains( $warnings[0], '\'plain\'' ) && str_contains( $warnings[1], 'AddrSelfTab' ) && str_contains( $warnings[1], '\'addr\'' ) );

$html = \Nino\Admin\Admin::panesHtml( $withAddr );
check( 'the pane says the name of every screen: its own screen as the panel, a tab by its name, in the markup the router reads', str_contains( $html, 'id="admin-tab-addr" role="tabpanel" aria-labelledby="admin-tabbutton-addr" data-tab="addr" data-address-tab="addr" hidden' )
	&& str_contains( $html, 'data-tab="addr-frames" data-address-tab="frames"' ) && str_contains( $html, 'data-tab="plain" data-address-tab="plain"' )
	&& str_contains( $html, 'data-tab="types" data-address-tab="types"' ) && str_contains( $html, 'data-tab="roles" data-address-tab="roles"' )
	&& str_contains( $html, 'data-tab="addr-plain"' ) === false && str_contains( $html, 'data-tab="addr-addr"' ) === false );
check( 'a pane whose account holds a single screen of it names it as well: one tab is no strip, and no screen goes without its name', ( static function() use ( $withAddr ): bool {
	$one = \Nino\Admin\Panels::panesHtml( [ 'solo' => [ 'uri' => 'solo', 'label' => 'Solo', 'layout' => 'page', 'head' => true, 'own' => true, 'addressTab' => 'solo', 'tab' => 'Solo', 'template' => '', 'panes' => [ 'solo-list' ], 'manual' => [], 'manualFeature' => '', 'class' => 'AddrPanel',
		'tabs' => [ 'solo-frames' => [ 'uri' => 'solo-frames', 'addressTab' => 'frames', 'label' => 'Frames', 'tab' => 'Frames', 'template' => '', 'panes' => [ 'solo-frames-list' ] ] ] ] ] );
	return str_contains( $one, 'data-tab="solo" data-address-tab="solo"' ) && str_contains( $one, 'data-tab="solo-frames" data-address-tab="frames"' );
} )() );


// --- the links the server writes -----------------------------------------------------------------------

$notices = \Nino\Modules\Dashboard\Admin::notices( $appData, true );
check( 'a notice of the Dashboard links with an address of the form: the panel alone, for a panel as it starts', array_reduce(
	array_filter( array_column( $notices, 'link' ), static fn( string $link ): bool => $link !== '' ),
	static fn( bool $carry, string $link ): bool => $carry === true && preg_match( '/^#[a-z][a-z0-9-]*(?:\/(?:@[a-z][a-z0-9-]*|[A-Za-z0-9._~!*\'()%-]+))*$/D', $link ) === 1,
	true
) );

ninoDone( $appData );
