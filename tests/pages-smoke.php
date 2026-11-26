<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	pages-smoke.php			Test of \Nino\Pages: a page made in one call - the routes of its languages, its names, its
 *											menu and, where the caller hands the source over, its template - or not at all, and the list
 *											of the pages. What the Routes panel does with the same function is pinned by
 *											admin-pages-smoke.php. Runs against an isolated sandbox directory, never touches the real
 *											project data.
 *
 *	Usage: php tests/pages-smoke.php
 */

require __DIR__. '/harness.php';

// A second process of the same project, for the pins that need two writers: php pages-smoke.php child <sandbox> <a|b> <rounds>. It
// makes and removes pages (a) or puts pages on the templates a makes (b), for a fixed number of rounds, and says what it was answered
if( ( $argv[1] ?? '' ) === 'child' ) {

	$child = [ './nino/uid' => $argv[2] ];
	\Nino\AppData::prepare( $child );
	$child['./nino/filesystem/path'] 				= $argv[2];
	$child['./nino/filesystem/configpath'] 	= $argv[2]. '/private';
	$child['./nino/filesystem/contentpath'] = $argv[2]. '/private';
	$child['./nino/filesystem/publicpath'] 	= $argv[2]. '/public';
	$child['./nino/locales/current'] 				= 'de_DE';
	$child['/nino/locales/native'] 					= 'de_DE';
	$child['/nino/locales/available'] 			= [ 'de_DE', 'en_US', 'fr_FR' ];
	$child['/nino/dir'] 										= '';
	$child['/nino/modules'] 								= [ '\\Nino\\Modules\\Navigation' ];
	$child['/nino/html/navs'] 							= [ 'main', 'footer' ];

	$rows = static fn( string $name ): array => [ 'de_DE' => [ 'name' => $name, 'title' => $name ], 'en_US' => [ 'name' => $name, 'title' => $name ], 'fr_FR' => [ 'name' => $name, 'title' => $name ] ];
	$seen = [];

	for( $i = 0; $i < (int) $argv[4]; $i++ ) {

		if( $argv[3] === 'a' ) {

			$made = \Nino\Pages::put( $child, [ 'uri' => '/site-l'. $i, 'httpUris' => [ '' => '/l'. $i ], 'template' => 'page-l'. $i, 'text' => $rows( 'L'. $i ), 'navs' => [ 'main' ], 'source' => '<p>[[/template/page-l'. $i. '/a/b]]</p>' ] );
			\Nino\Text::put( $child, [ '/template/page-l'. $i. '/a/b' => [ 'de_DE' => 'x' ] ] );
			$answer = \Nino\Pages::remove( $child, 'page-l'. $i );
			$seen[] = ( $made['error']['code'] ?? 'made' ). ' '. ( $answer['error']['code'] ?? 'removed' );

		} else if( $argv[3] === 'c' ) {

			// What the Routes and the Navigation panel do: lock, read the routes the way a request reads them, write them back
			\Nino\Filesystem::lockFile( $child, '/config.php' );
			$child['/nino/http/routes'] = \Nino\AppData::stored( $child, '/nino/http/routes', [] );
			\Nino\AppData::writeContentData( $child, [ '/nino/http/routes' ] );
			\Nino\Filesystem::unlockFile( $child, '/config.php' );
			$seen[] = 'written';

		} else {

			$answer = \Nino\Pages::put( $child, [ 'uri' => '/site-b'. $i, 'httpUris' => [ '' => '/b'. $i ], 'template' => 'page-l'. ( $i % 40 ), 'text' => $rows( 'B'. $i ), 'navs' => [ 'main' ] ] );
			$seen[] = $answer['error']['code'] ?? 'put';
		}
	}

	echo json_encode( array_count_values( $seen ) );
	exit( 0 );
}

$appData = ninoSandbox( 'pages' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

$appData['/nino/locales/available'] = [ 'de_DE', 'en_US', 'fr_FR' ];
$appData['/nino/modules'] 					= [ '\\Nino\\Modules\\Navigation' ];
$appData['/nino/html/navs'] 				= [ 'main', 'footer' ];

// A project with a language file each, a template that is there and a route that is no page's
foreach( [ 'de_DE', 'en_US', 'fr_FR' ] as $locale )
	\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', [ '[[/template/common/x]]' => $locale ] );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-there.tpl', '<h1>There</h1>' );
$appData['/nino/http/routes'] = [ 'GET://robots.txt' => [ 'uri' => '/robots.txt', 'body' => 'x' ] ];
\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

/**
 *	The project as it was before a page was made: the one route that is no page's, the texts of the languages, no template of a page
 */
$reset = static function() use ( &$appData, $sandbox ): void {

	$appData['/nino/http/routes'] = [ 'GET://robots.txt' => [ 'uri' => '/robots.txt', 'body' => 'x' ] ];
	\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

	foreach( [ 'de_DE', 'en_US', 'fr_FR' ] as $locale )
		\Nino\Filesystem::putFileContent( $appData, '/text/'. $locale. '.php', [ '[[/template/common/x]]' => $locale ] );

	// No file of global.php or the blacklist: a page that fails has to leave them as they are, which is none
	foreach( [ 'text/global.php', 'text/blacklist.php', 'templates/page-news.tpl', 'templates/page-one.tpl', 'templates/page-folded.tpl', 'templates/page-Folded.tpl' ] as $name )
		@unlink( $sandbox. '/private/'. $name );

	unset( $appData['./nino/filesystem/cache'], $appData['./nino/html/filecache'], $appData['./nino/locales/text'] );
};

/**
 *	Everything a page writes, in one hash: the route array, the text files with the blacklist, the templates
 */
$state = static function() use ( $sandbox ): string {

	$parts = [];
	$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $sandbox. '/private', \FilesystemIterator::SKIP_DOTS ) );

	foreach( $files as $file )
		if( str_contains( $file->getPathname(), '/private/data/' ) === false && $file->isFile() === true )
			$parts[$file->getPathname()] = md5_file( $file->getPathname() );

	ksort( $parts );

	return md5( serialize( $parts ) );
};

$text = static function( string $name ): array {
	return [ 'de_DE' => [ 'name' => $name, 'title' => $name. ' de' ], 'en_US' => [ 'name' => $name, 'title' => $name. ' en' ], 'fr_FR' => [ 'name' => $name, 'title' => $name. ' fr' ] ];
};

$page = [
	'uri' => '/site-news', 'httpUris' => [ 'de_DE' => '/neuigkeiten', '' => '/news' ], 'template' => 'page-news', 'text' => $text( 'News' ),
	'navs' => [ 'main', 'unregistered' ], 'source' => "<?php echo 'x'; ?>\n[template /templates/page-there]\n[[/template/common/x]]\n",
];

echo "A page in one call\n";

$put = \Nino\Pages::put( $appData, $page );
$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$fr = \Nino\Filesystem::getFileContent( $appData, '/text/fr_FR.php', [] );

check( 'the answer says the page, its routes in the order they stand in, its template and that the template file was made', $put === [ 'uri' => '/site-news', 'routes' => [ 'GET://neuigkeiten', 'GET://news' ], 'template' => 'page-news', 'created' => true ] );
check( 'a route for the language that has an address of its own and one with no language for the languages that share the other, both with the page and the menu - and a menu nobody registered is left out', ( $config['GET://neuigkeiten']['locale'] ?? null ) === 'de_DE' && isset( $config['GET://news']['locale'] ) === false
	&& $config['GET://news']['uri'] === '/site-news' && $config['GET://news']['body'] === '[template /templates/page-news]' && array_keys( $config['GET://news']['navs'] ) === [ 'main' ] && isset( $config['GET://robots.txt'] ) === true );
check( 'the name, the title and the address of each language stand in its own file, and the address that no language decides in global.php, which the blacklist hides', $de['[[/_nino/webpage/site-news/name]]'] === 'News' && $fr['[[/_nino/webpage/site-news/title]]'] === 'News fr'
	&& $de['[[/_nino/webpage/site-news/uri]]'] === '/neuigkeiten' && $fr['[[/_nino/webpage/site-news/uri]]'] === '/news'
	&& ( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/site-news/uri]]'] ?? null ) === '/news'
	&& in_array( '/_nino/webpage/site-news/uri', \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [] ), true ) === true );
check( 'the source is the file, byte for byte: a PHP tag, a call and a fill in it are no more than the text they are', file_get_contents( $sandbox. '/private/templates/page-news.tpl' ) === $page['source'] );

// A page a module routes at runtime stands in the list as well, marked, with its title where it has no name
$appData['/nino/http/routes']['GET://legal/imprint'] = [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '[template /templates/page-legal-imprint]' ];
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', $de + [ '[[/_nino/webpage/legal/imprint/title]]' => 'Impressum' ] );
$all = \Nino\Pages::all( $appData );
// ...and holds its address: a page that asks for it is refused, at the field of that address, and nothing is written
$held = md5( (string) file_get_contents( $sandbox. '/private/config.php' ) ). md5( (string) file_get_contents( $sandbox. '/private/text/de_DE.php' ) );
$taken = \Nino\Pages::put( $appData, [ 'uri' => '/taker', 'httpUris' => [ '' => '/legal/imprint' ], 'template' => 'page-news', 'text' => $text( 'Taker' ), 'navs' => [] ] );
$heldAfter = md5( (string) file_get_contents( $sandbox. '/private/config.php' ) ). md5( (string) file_get_contents( $sandbox. '/private/text/de_DE.php' ) );
unset( $appData['/nino/http/routes']['GET://legal/imprint'] );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', $de );
check( 'an address a route of the run time holds is taken: a page that asks for it is refused with 409 at that field, and nothing is written', ( $taken['error']['status'] ?? null ) === 409 && ( $taken['error']['code'] ?? null ) === 'routes_http_uri_taken' && $held === $heldAfter );
check( 'all() lists the page once, with its template, a name and an address for each language, the languages and the menus - and no route that is no page', count( $all ) === 2 && $all[0] === [
	'uri' => '/site-news', 'template' => 'page-news', 'names' => [ 'de_DE' => 'News', 'en_US' => 'News', 'fr_FR' => 'News' ],
	'addresses' => [ 'de_DE' => '/neuigkeiten', 'en_US' => '/news', 'fr_FR' => '/news' ], 'locales' => [ 'de_DE', 'en_US', 'fr_FR' ], 'navs' => [ 'main' ], 'runtime' => false,
] );
check( '...and the page of a module that is routed at runtime is in it, marked, at the address of its one language and with its title as the name', $all[1] === [
	'uri' => '/legal/imprint', 'template' => 'page-legal-imprint', 'names' => [ 'de_DE' => 'Impressum', 'en_US' => '', 'fr_FR' => '' ],
	'addresses' => [ 'de_DE' => '/legal/imprint' ], 'locales' => [ 'de_DE' ], 'navs' => [], 'runtime' => true,
] );

// A page of a template that is there, whose body somebody wrote by hand: no template is made, and none is named
$appData['/nino/http/routes']['GET://hand'] = [ 'uri' => '/hand', 'body' => '[template /templates/page-there.[[/nino/http/response/locale]]]' ];
\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );
$handed = \Nino\Pages::all( $appData );
check( 'a page whose body picks the template at runtime names none', ( $handed[1]['template'] ?? null ) === '' && ( $handed[1]['runtime'] ?? null ) === false );
unset( $appData['/nino/http/routes']['GET://hand'] );
\Nino\AppData::writeContentData( $appData, [ '/nino/http/routes' ] );

$saved = \Nino\Pages::put( $appData, [ 'originalUri' => '/site-news', 'uri' => '/site-news', 'httpUris' => [ '' => '/news' ], 'template' => 'page-there', 'text' => $text( 'News' ), 'navs' => [ 'footer' ] ] );
$config = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'];
check( 'a page saved a second time replaces its routes at the slot of its first, and a template that is there is not made', $saved['created'] === false && array_keys( $config ) === [ 'GET://robots.txt', 'GET://news' ] && array_keys( $config['GET://news']['navs'] ) === [ 'footer' ] );
// A page that is put as it stands is not written: every file of the project keeps its bytes, its mtime and its inode, and the answer is that of a page that is written. One
// text of one language is the file of that language alone
$files = static function() use ( $sandbox ): array {
	$held = [];
	clearstatcache();
	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $sandbox. '/private', \FilesystemIterator::SKIP_DOTS ) ) as $file )
		if( str_contains( $file->getPathname(), '/private/data/' ) === false && $file->isFile() === true )
			$held[$file->getPathname()] = [ md5_file( $file->getPathname() ), fileinode( $file->getPathname() ), filemtime( $file->getPathname() ) ];
	ksort( $held );
	return $held;
};
$asStored = [ 'originalUri' => '/site-news', 'uri' => '/site-news', 'httpUris' => [ '' => '/news' ], 'template' => 'page-there', 'text' => $text( 'News' ), 'navs' => [ 'footer' ] ];
foreach( array_keys( $files() ) as $aged )
	touch( $aged, 1000000000 );
$fromHere = $files();
$putTwice = \Nino\Pages::put( $appData, $asStored );
check( 'a page that is saved as it stands writes no file: not a byte, not a mtime, not an inode - and the answer is the one of a page that is written', $putTwice === $saved && $files() === $fromHere );
$changed = \Nino\Pages::put( $appData, [ 'text' => [ 'fr_FR' => [ 'name' => 'Nouvelles', 'title' => 'News fr' ] ] + $text( 'News' ) ] + $asStored );
$after = $files();
$differ = array_keys( array_filter( $after, static fn( array $one, string $path ): bool => $one !== ( $fromHere[$path] ?? null ), ARRAY_FILTER_USE_BOTH ) );
check( 'the name of one language is the file of that language alone', isset( $changed['error'] ) === false && array_map( 'basename', $differ ) === [ 'fr_FR.php' ] );
\Nino\Pages::put( $appData, $asStored );

$reset();

echo "\nA page that is refused leaves nothing\n";

// Each of these is one thing the call must say no to, with the status that fits: nothing of the file system, the routes, the
// texts or the blacklist changes. The ones the name of the template, the address and the size are about are the boundaries a
// caller's input must not cross
$one = $text( 'One' );
$ok = [ 'uri' => '/site-one', 'httpUris' => [ '' => '/one' ], 'template' => 'page-one', 'text' => $one, 'source' => '<p>one</p>' ];
$refused = [
	'an Element-URI with a jump in it' 			=> [ [ 'uri' => '/../x' ] + $ok, 400 ],
	'an address with a jump in it' 					=> [ [ 'httpUris' => [ '' => '/a/../b' ] ] + $ok, 400 ],
	'an address of the workbench' 					=> [ [ 'httpUris' => [ '' => '/_admin/x' ] ] + $ok, 409 ],
	'an address of the kernel' 							=> [ [ 'httpUris' => [ '' => '/_nino' ] ] + $ok, 409 ],
	'an address another route answers on' 	=> [ [ 'httpUris' => [ '' => '/robots.txt' ] ] + $ok, 409 ],
	'a language the site has not' 					=> [ [ 'httpUris' => [ 'xx_XX' => '/x', '' => '/one' ] ] + $ok, 400 ],
	'no address for a language' 						=> [ [ 'httpUris' => [ 'de_DE' => '/eins' ] ] + $ok, 400 ],
	'a name that is missing' 								=> [ [ 'text' => [ 'de_DE' => [ 'name' => '', 'title' => 't' ] ] + $one ] + $ok, 400 ],
	'a template that is not there' 					=> [ [ 'template' => 'page-gone', 'source' => null ] + $ok, 400 ],
	'a template of a file that is there' 		=> [ [ 'template' => 'page-there' ] + $ok, 409 ],
	'a name with a jump in it' 							=> [ [ 'template' => 'page-../../config' ] + $ok, 400 ],
	'a name with a NUL byte in it' 					=> [ [ 'template' => "page-one\0.php" ] + $ok, 400 ],
	'a name of a file that is no page' 			=> [ [ 'template' => 'html-header' ] + $ok, 400 ],
	'a name with an upper case letter' 			=> [ [ 'template' => 'page-One' ] + $ok, 400 ],
	'a source larger than a template is' 		=> [ [ 'source' => str_repeat( 'x', \Nino\Modules\Template::MAX_BYTES + 1 ) ] + $ok, 400 ],
	'a name too long to be a file' 					=> [ [ 'template' => 'page-'. str_repeat( 'a', \Nino\Pages::MAX_NAME ) ] + $ok, 400 ],
	'the name of the maintenance page' 			=> [ [ 'template' => 'page-maintenance' ] + $ok, 409 ],
	'the name of a page of the legal module' => [ [ 'template' => 'page-legal-imprint' ] + $ok, 409 ],
	'a name another template has in other case' => [ [ 'template' => 'page-folded' ] + $ok, 409 ],
];

\Nino\Filesystem::putFileContent( $appData, '/templates/page-Folded.tpl', 'x' );
$before = $state();
$ignored = ninoWarnings();

foreach( $refused as $label => [ $input, $status ] ) {

	$answer = \Nino\Pages::put( $appData, $input );
	check( 'refused with '. $status. ': '. $label, ( $answer['error']['status'] ?? 0 ) === $status && $state() === $before );
}

check( 'a source of exactly the largest size is a template', ( \Nino\Pages::put( $appData, [ 'source' => str_repeat( 'x', \Nino\Modules\Template::MAX_BYTES ) ] + $ok )['created'] ?? false ) === true );
unlink( $sandbox. '/private/templates/page-one.tpl' );
$reset();

// The refusals of a source carry the codes the workbench has words for
$codes = [];
foreach( [ 'page-x-'. str_repeat( 'a', 200 ), 'page-maintenance', 'page-there' ] as $name )
	$codes[] = \Nino\Pages::put( $appData, [ 'template' => $name ] + $ok )['error'];
$codes[] = \Nino\Pages::put( $appData, [ 'source' => str_repeat( 'x', \Nino\Modules\Template::MAX_BYTES + 1 ) ] + $ok )['error'];
check( 'a source is refused with a code and the field to blame, which the workbench has words for', array_column( $codes, 'code' ) === [ 'routes_invalid_template', 'routes_reserved_template', 'routes_template_exists', 'routes_template_too_large' ] && array_column( $codes, 'field' ) === [ 'template', 'template', 'template', 'source' ] );

// The Routes panel hands no source over: a field of that name in its form is nothing it reads
ninoUser( $appData, 'dev@example.com' );
[ $status ] = ninoCall( $appData, [ \Nino\Modules\Routes\Admin::class, 'apiSave' ], [ 'template' => 'page-there', 'source' => '<p>panel</p>' ] + $ok );
check( 'a source in the form of the panel is not made into a template: the page of a template that is there is saved and the template is as it was', $status === 200 && file_get_contents( $sandbox. '/private/templates/page-there.tpl' ) === '<h1>There</h1>' );
$reset();

echo "\nA write that fails gives back every file as it was\n";

// A directory where the sidecar lock of a file goes: lockFile() cannot open it, so the file cannot be written - what a full or
// read-only disk gives, without needing either. The last language's file and the blacklist are the first and the last of the
// writes that can fail after the template and the routes
$lockOf = static function( string $file ) use ( $sandbox ): string {
	return $sandbox. '/private/data/.locks/'. sha1( $file ). '.lock';
};

foreach( [ '/text/fr_FR.php' => 'the text of the last language', '/text/blacklist.php' => 'the blacklist' ] as $file => $label ) {

	$before = $state();
	@mkdir( dirname( $lockOf( $file ) ), 0777, true );
	@unlink( $lockOf( $file ) );
	mkdir( $lockOf( $file ) );
	unset( $appData['./nino/filesystem/locks'] );

	$answer = \Nino\Pages::put( $appData, $ok );

	rmdir( $lockOf( $file ) );
	unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
	$ignored = ninoWarnings();

	check( 'a page that could not write '. $label. ' is refused with 500 and no template, no route and no key of its text is left', ( $answer['error']['status'] ?? 0 ) === 500 && str_contains( $answer['error']['message'], $file ) === true
		&& $state() === $before && is_file( $sandbox. '/private/templates/page-one.tpl' ) === false );
}

// A page that is saved a second time and fails: the file of a language is as it was, byte for byte, with its keys where they
// stood - the lock of the blacklist is the one that fails, after the texts were written
$put = static function( array $page ) use ( &$appData ): array {
	return \Nino\Pages::put( $appData, $page );
};
$put( $ok );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ) + [ '[[/template/common/after]]' => 'after' ] );
$before = $state();
@mkdir( dirname( $lockOf( '/text/blacklist.php' ) ), 0777, true );
@unlink( $lockOf( '/text/blacklist.php' ) );
mkdir( $lockOf( '/text/blacklist.php' ) );
unset( $appData['./nino/filesystem/locks'] );
$answer = $put( [ 'originalUri' => '/site-one', 'template' => 'page-one', 'source' => null, 'text' => $text( 'Renamed' ) ] + $ok );
rmdir( $lockOf( '/text/blacklist.php' ) );
unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
$ignored = ninoWarnings();
check( 'a page that is saved a second time and fails leaves every file of the project byte for byte as it was, the order of the keys of its texts too', ( $answer['error']['status'] ?? 0 ) === 500 && $state() === $before );
$reset();

// A file of a project that is the code of a test: the include of it, which is how a file of the text is read, is the moment
// the test acts in the middle of the writes of a page. The file answers what it says everywhere else
function ninoHook( string $name ): string {
	return '<?php return ( $GLOBALS[\'ninoHook\'][\''. $name. '\'] ?? static fn(): array => [] )();';
}

$plant = static function( string $file, string $name ) use ( &$appData, $sandbox ): void {
	file_put_contents( $sandbox. '/private'. $file, ninoHook( $name ) );
	unset( $appData['./nino/filesystem/cache'][$file] );
};

// The lock of config.php is the page's from the first check to the write of the routes: another writer that asks for it between
// the writes is turned away - at the blacklist, the last write before the routes, and so at every one before it
$turnedAway = new ArrayObject();
$GLOBALS['ninoHook']['blacklist'] = static function() use ( &$appData, $turnedAway ): array {
	$other = $appData;
	unset( $other['./nino/filesystem/locks'] );
	$turnedAway[] = \Nino\Filesystem::lockFile( $other, '/config.php', false ) === false;
	return [];
};
$plant( '/text/blacklist.php', 'blacklist' );
$answer = $put( $ok );
check( 'the lock of config.php is held by the page until the routes are written: every other writer that asked in between was turned away', ( $answer['created'] ?? false ) === true && count( $turnedAway ) > 0 && in_array( false, $turnedAway->getArrayCopy(), true ) === false );
$reset();

// The write of config.php itself fails - what a read-only file gives, without needing a user that is not root: config.php is a
// directory when the routes are written. The page is refused, and the template of the source is gone
$configPath = $sandbox. '/private/config.php';
$configBytes = (string) file_get_contents( $configPath );
$armed = new ArrayObject();
$GLOBALS['ninoHook']['blacklist'] = static function() use ( $armed ): array {
	$armed[] = true;
	return [];
};
$GLOBALS['ninoHook']['config'] = static function() use ( $armed, $configPath ): void {
	if( count( $armed ) > 0 && is_file( $configPath ) === true ) {
		unlink( $configPath );
		mkdir( $configPath );
	}
};
$plant( '/text/blacklist.php', 'blacklist' );
file_put_contents( $configPath, preg_replace( '/^<\?php return /', '<?php ( $GLOBALS[\'ninoHook\'][\'config\'] ?? static fn() => null )(); return ', $configBytes, 1 ) );
unset( $appData['./nino/filesystem/cache'] );
$hooked = $configBytes === (string) file_get_contents( $configPath ) ? '' : (string) file_get_contents( $configPath );
$before = $state();
$ignored = ninoWarnings();
$answer = $put( $ok );
$ignored = ninoWarnings();
$broken = is_dir( $configPath );
@rmdir( $configPath );
file_put_contents( $configPath, $hooked );
unset( $GLOBALS['ninoHook'], $appData['./nino/filesystem/cache'] );
$listed = \Nino\Filesystem::getFileContent( $appData, '/text/blacklist.php', [ 'unread' ] );
$plant( '/text/blacklist.php', 'blacklist' );
check( 'a page whose routes cannot be written is refused with 500 - not a fatal error - and no template, no key of its texts and no entry of the blacklist is left', $broken === true && $listed === [] && ( $answer['error']['status'] ?? 0 ) === 500 && str_contains( $answer['error']['message'], 'config.php' ) === true
	&& $state() === $before && is_file( $sandbox. '/private/templates/page-one.tpl' ) === false );
file_put_contents( $configPath, $configBytes );
$reset();

// A template that somebody else makes between the check of the name and the write of the file stays theirs: the check does not
// see a link that points nowhere, and the file is made as a new one or not at all - never through the link
$target = $sandbox. '/private/foreign.target';
symlink( $target, $sandbox. '/private/templates/page-one.tpl' );
$before = $state();
$answer = $put( $ok );
$left = is_file( $target ) === false && is_link( $sandbox. '/private/templates/page-one.tpl' ) === true && $state() === $before;
unlink( $sandbox. '/private/templates/page-one.tpl' );
check( 'a template that is there when the file is made - to the check it is none - is not written to: the page is refused with 409 and nothing is made', ( $answer['error']['status'] ?? 0 ) === 409 && $left === true );
$reset();

// Another writer changes a file of the text while the page is written, and the page then fails: the keys of the page leave the
// file, the ones of the other writer stay - a new key and the new value of a key that was there before
$GLOBALS['ninoHook']['en'] = static function() use ( &$appData ): array {

	static $done = false;

	if( $done === false ) {
		$done = true;
		$other = $appData;
		unset( $other['./nino/filesystem/locks'], $other['./nino/filesystem/cache'] );
		\Nino\Filesystem::putFileContent( $other, '/text/de_DE.php', [ '[[/template/common/x]]' => 'theirs', '[[/template/common/new]]' => 'theirs' ] + \Nino\Filesystem::getFileContent( $other, '/text/de_DE.php', [] ) );
	}

	return [ '[[/template/common/x]]' => 'en_US' ];
};
$plant( '/text/en_US.php', 'en' );
@unlink( $lockOf( '/text/blacklist.php' ) );
@mkdir( dirname( $lockOf( '/text/blacklist.php' ) ), 0777, true );
mkdir( $lockOf( '/text/blacklist.php' ) );
unset( $appData['./nino/filesystem/locks'] );
$answer = $put( $ok );
rmdir( $lockOf( '/text/blacklist.php' ) );
unset( $GLOBALS['ninoHook'], $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
$ignored = ninoWarnings();
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$fr = \Nino\Filesystem::getFileContent( $appData, '/text/fr_FR.php', [] );
check( 'a failed page takes only its own keys out of a file another writer changed meanwhile: their new key and their new value stay, its name, title and address are gone', ( $answer['error']['status'] ?? 0 ) === 500
	&& $de === [ '[[/template/common/x]]' => 'theirs', '[[/template/common/new]]' => 'theirs' ] && $fr === [ '[[/template/common/x]]' => 'fr_FR' ] && is_file( $sandbox. '/private/templates/page-one.tpl' ) === false );
$reset();

echo "\nA page taken away whole, and a page copied\n";

// Everything below the sandbox but the data: the whole tree of the project the way a visitor's files and the workbench's see it - the
// templates, config.php, the text of each language with its meta file, global.php and the blacklist, and the pictures
$tree = static function() use ( $sandbox ): string {

	$parts = [];

	foreach( new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $sandbox, \FilesystemIterator::SKIP_DOTS ) ) as $file )
		if( str_contains( $file->getPathname(), '/private/data/' ) === false && $file->isFile() === true )
			$parts[$file->getPathname()] = md5_file( $file->getPathname() );

	ksort( $parts );

	return md5( serialize( $parts ) );
};

$wipe = static function() use ( &$appData, $sandbox, $reset ): void {

	$reset();

	foreach( [ 'text/meta.php', 'templates/page-copy.tpl', 'templates/page-other.tpl' ] as $name )
		@unlink( $sandbox. '/private/'. $name );

	\Nino\Filesystem::mutate( $appData, '/config.php', static fn( array $content ): array => array_diff_key( $content, [ '/nino/html/images' => true ] ) );
	\Nino\Filesystem::removeDir( $sandbox. '/public/images' );
	unset( $appData['./nino/filesystem/cache'], $appData['./nino/html/filecache'], $appData['./nino/locales/text'], $appData['/nino/html/images'], $appData['./nino/filesystem/locks'] );
};

$news = [
	'uri' => '/site-news', 'httpUris' => [ 'de_DE' => '/neuigkeiten', '' => '/news' ], 'template' => 'page-news', 'text' => $text( 'News' ), 'navs' => [ 'main' ],
	'source' => "<!-- nino:template-name Seite News -->\n<h1>[[/template/page-news/hero/title]]</h1>\n<p>[[/template/page-news/hero/text]] [[/template/common/x]] [[/template/page-news-extra/a/b]]</p>\n<p>The keys are /template/page-news/hero/title.</p><i data-key=\"/template/page-news/hero/title\"></i>\n",
];
$copy = [ 'uri' => '/site-copy', 'httpUris' => [ '' => '/copy' ], 'template' => 'page-copy', 'text' => $text( 'Copy' ), 'navs' => [ 'footer' ] ];
$photo = 'template/page-news/hero/image.10x10.webp';

/**
 *	The page of $news with its words in every language, a format in meta.php and a picture slot with its file and its alt text
 */
$stage = static function() use ( &$appData, $news, $photo ): void {

	\Nino\Pages::put( $appData, $news );
	\Nino\Text::put( $appData, [
		'/template/page-news/hero/title' => [ 'de_DE' => 'Hallo', 'en_US' => 'Hello', 'fr_FR' => 'Salut' ],
		'/template/page-news/hero/text' => [ 'de_DE' => 'Welt', 'en_US' => 'World' ],
	] );
	\Nino\Text::setMeta( $appData, '/template/page-news/hero/title', 'plain', 80 );
	\Nino\Images::putSlot( $appData, '/template/page-news/hero/image', [ 'label' => 'Hero', 'width' => 10, 'height' => 10, 'filename' => $photo ] );
	\Nino\Images::setSlotAlt( $appData, '/template/page-news/hero/image', [ 'de_DE' => 'Ein Held' ] );
	\Nino\Filesystem::putFileContent( $appData, '/images/'. $photo, 'the bytes of the picture' );
};

$draw = static function( string $template, string $locale ) use ( &$appData, $sandbox ): string {

	$appData['./nino/locales/current'] = $locale;
	unset( $appData['./nino/filesystem/cache'], $appData['./nino/html/filecache'], $appData['./nino/locales/text'] );

	return \Nino\Html::renderSource( $appData, (string) file_get_contents( $sandbox. '/private/templates/'. $template. '.tpl' ), [ 'template' => $template ] );
};

$wipe();
$before = $tree();
$stage();
$staged = $tree();
$removed = \Nino\Pages::remove( $appData, 'page-news' );
check( 'a page that is made, given words, a slot and a file and then removed leaves the project byte for byte as it was - every file of every language, config.php, the template and the pictures - and the answer says what went', $tree() === $before && $staged !== $before
	&& $removed['template'] === 'page-news' && $removed['uri'] === '/site-news' && $removed['routes'] === [ 'GET://neuigkeiten', 'GET://news' ] && $removed['texts'] === 6 && $removed['slots'] === [ '/template/page-news/hero/image' ] && $removed['files'] === [ $photo ]
	&& isset( $appData['/nino/http/routes']['GET://news'] ) === false && isset( $appData['/nino/html/images']['/template/page-news/hero/image'] ) === false );

// A picture another page reads stays - and so does a file that is no file of the slot's own name
$wipe();
$stage();
\Nino\Filesystem::putFileContent( $appData, '/templates/page-other.tpl', '<p>other</p>' );
\Nino\Pages::put( $appData, [ 'uri' => '/site-other', 'httpUris' => [ '' => '/other' ], 'template' => 'page-other', 'text' => $text( 'Other' ), 'navs' => [] ] );
\Nino\Images::putSlot( $appData, '/template/page-other/hero/image', [ 'label' => 'Other', 'width' => 10, 'height' => 10, 'filename' => $photo ] );
\Nino\Images::putSlot( $appData, '/template/page-news/hero/foreign', [ 'label' => 'Foreign', 'width' => 10, 'height' => 10, 'filename' => 'elements/demo/photo.webp' ] );
\Nino\Filesystem::putFileContent( $appData, '/images/elements/demo/photo.webp', 'an element picture' );
$removed = \Nino\Pages::remove( $appData, 'page-news' );
check( 'a picture that the slot of another page names stays, with that slot', is_file( $sandbox. '/public/images/'. $photo ) === true && ( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/template/page-other/hero/image']['filename'] ?? null ) === $photo && in_array( $photo, $removed['files'], true ) === false );
check( 'a file that is not named for its slot is left where it stands, though only the slot read it: the slot goes and nothing proves the file is the slot\'s', is_file( $sandbox. '/public/images/elements/demo/photo.webp' ) === true && $removed['files'] === [] && in_array( '/template/page-news/hero/foreign', $removed['slots'], true ) === true );

// A name in a slot that climbs out of the pictures is no file to take away - the text file of a language is what it points at
$wipe();
$stage();
$appData['/nino/html/images']['/template/page-news/hero/image']['filename'] = $photo. '/../../../../private/text/de_DE.php';
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content ) use ( $photo ): array {
	$content['/nino/html/images']['/template/page-news/hero/image']['filename'] = $photo. '/../../../../private/text/de_DE.php';
	return $content;
} );
$removed = \Nino\Pages::remove( $appData, 'page-news' );
check( 'a slot whose file name climbs out of /images/ takes no file with it: the page goes, the file it points at stays', is_file( $sandbox. '/private/text/de_DE.php' ) === true && $removed['files'] === [ $photo ] && isset( $removed['error'] ) === false );

// What the page leaves out of every step is given back whole where a step fails: each file has its bytes again, in every phase
$phases = [ 'config.php' => null, 'the text of the first language' => '/text/de_DE.php', 'the text of the last language' => '/text/fr_FR.php', 'global.php' => '/text/global.php', 'the meta file' => '/text/meta.php', 'the blacklist' => '/text/blacklist.php', 'the template' => '' ];
$gave = [];

foreach( $phases as $label => $file ) {

	$wipe();
	$stage();

	if( $file === '' ) {

		// A template that cannot be removed - a directory with something in it is no file to unlink, whoever runs this
		unlink( $sandbox. '/private/templates/page-news.tpl' );
		mkdir( $sandbox. '/private/templates/page-news.tpl' );
		file_put_contents( $sandbox. '/private/templates/page-news.tpl/inside', 'x' );
	}

	if( $file === null ) {

		// config.php is a directory when it is written: the first write of the page is the one that fails. It is read for the
		// second time, by that write, after the text of the first language was read for the first - the file that arms the hook
		$configPath = $sandbox. '/private/config.php';
		$stageDe = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
		$GLOBALS['ninoHook']['armed'] = static function() use ( $stageDe ): array {
			$GLOBALS['ninoArmed'] = true;
			return $stageDe;
		};
		$GLOBALS['ninoHook']['config'] = static function() use ( $configPath ): void {

			if( ( $GLOBALS['ninoArmed'] ?? false ) === true && is_file( $configPath ) === true ) {
				unlink( $configPath );
				mkdir( $configPath );
			}
		};
		file_put_contents( $sandbox. '/private/text/de_DE.php', ninoHook( 'armed' ) );
		file_put_contents( $configPath, preg_replace( '/^<\?php return /', '<?php ( $GLOBALS[\'ninoHook\'][\'config\'] ?? static fn() => null )(); return ', (string) file_get_contents( $configPath ), 1 ) );
		$plantedConfig = (string) file_get_contents( $configPath );
		unset( $appData['./nino/filesystem/cache'] );
	}

	$hold = $tree();

	if( is_string( $file ) === true && $file !== '' ) {

		@mkdir( dirname( $lockOf( $file ) ), 0777, true );
		@unlink( $lockOf( $file ) );
		mkdir( $lockOf( $file ) );
		unset( $appData['./nino/filesystem/locks'] );
	}

	$answer = \Nino\Pages::remove( $appData, 'page-news' );

	if( is_string( $file ) === true && $file !== '' )
		rmdir( $lockOf( $file ) );

	$ignored = ninoWarnings();
	$kept = ( $answer['error']['status'] ?? 0 ) === 500;

	if( $file === null ) {

		$kept = $kept && is_dir( $configPath );
		@rmdir( $configPath );
		file_put_contents( $configPath, $plantedConfig );
		unset( $GLOBALS['ninoHook'], $GLOBALS['ninoArmed'] );
	}

	$kept = $kept && $tree() === $hold;

	if( $file === '' ) {
		unlink( $sandbox. '/private/templates/page-news.tpl/inside' );
		rmdir( $sandbox. '/private/templates/page-news.tpl' );
	}
	$gave[$label] = $kept;
	unset( $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
}
check( 'a step that fails - config.php, each file of the text, global.php, the meta file, the blacklist, the template - is answered with 500 and every file of the project, picture and template, has the bytes it had', array_filter( $gave ) === $gave && count( $gave ) === count( $phases ) );
$wipe();

// Another writer changes a file of the text while the page is removed, and the removal then fails: the file has what it had, their
// new key stays, and what the removal took out of their file is put in again
$wipe();
$stage();
$theirs = new ArrayObject();
$deNow = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$GLOBALS['ninoHook']['fr'] = static function() use ( &$appData, $theirs ): array {

	// Asked for before the last language is written and, as the file of the page is read, once more by the write itself
	$other = $appData;
	unset( $other['./nino/filesystem/locks'], $other['./nino/filesystem/cache'] );
	$de = \Nino\Filesystem::getFileContent( $other, '/text/de_DE.php', [] );

	if( isset( $de['[[/template/common/theirs]]'] ) === false && count( $theirs ) > 0 ) {
		\Nino\Filesystem::putFileContent( $other, '/text/de_DE.php', $de + [ '[[/template/common/theirs]]' => 'theirs' ] );
		$theirs[] = 'wrote';
	}

	$theirs[] = 'asked';

	return [ '[[/template/common/x]]' => 'fr_FR', '[[/template/page-news/hero/title]]' => 'Salut', '[[/_nino/webpage/site-news/name]]' => 'News', '[[/_nino/webpage/site-news/title]]' => 'News fr', '[[/_nino/webpage/site-news/description]]' => '', '[[/_nino/webpage/site-news/uri]]' => '/news' ];
};
$plant( '/text/fr_FR.php', 'fr' );
@mkdir( dirname( $lockOf( '/text/blacklist.php' ) ), 0777, true );
@unlink( $lockOf( '/text/blacklist.php' ) );
mkdir( $lockOf( '/text/blacklist.php' ) );
unset( $appData['./nino/filesystem/locks'] );
$answer = \Nino\Pages::remove( $appData, 'page-news' );
rmdir( $lockOf( '/text/blacklist.php' ) );
unset( $GLOBALS['ninoHook'], $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
$ignored = ninoWarnings();
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'a removal that fails takes back only what it took out of a file another writer changed meanwhile: their new key stays, the keys of the page are in it again', ( $answer['error']['status'] ?? 0 ) === 500 && in_array( 'wrote', $theirs->getArrayCopy(), true ) === true
	&& ( $de['[[/template/common/theirs]]'] ?? null ) === 'theirs' && array_diff_key( $deNow, $de ) === [] );
$wipe();

// The order of the steps, seen from inside: when the blacklist is read - the last of the files of the text - the routes are gone and
// the template is still there, and the lock of config.php is held again: another writer is turned away at every step
$stage();
$seen = new ArrayObject();
$GLOBALS['ninoHook']['blacklist'] = static function() use ( &$appData, $sandbox, $seen ): array {

	$other = $appData;
	unset( $other['./nino/filesystem/locks'], $other['./nino/filesystem/cache'] );
	$held = \Nino\Filesystem::lockFile( $other, '/config.php', false ) === false;

	if( $held === false )
		\Nino\Filesystem::unlockFile( $other, '/config.php' );

	$seen[] = [ 'held' => $held, 'routes' => array_keys( \Nino\Filesystem::getFileContent( $other, '/config.php', [] )['/nino/http/routes'] ?? [] ), 'template' => is_file( $sandbox. '/private/templates/page-news.tpl' ) ];

	return [ '/_nino/webpage/site-news/uri' ];
};
$plant( '/text/blacklist.php', 'blacklist' );
$answer = \Nino\Pages::remove( $appData, 'page-news' );
unset( $GLOBALS['ninoHook'] );
check( 'the routes go first and the template last, and the lock of config.php is held from the first check to the last write: seen from the blacklist, no route of the page is left, the template is there and every other writer is turned away', isset( $answer['error'] ) === false && count( $seen ) === 1
	&& $seen[0] === [ 'held' => true, 'routes' => [ 'GET://robots.txt' ], 'template' => true ] && is_file( $sandbox. '/private/templates/page-news.tpl' ) === false );
$wipe();

// The hook: once, after the page is gone and the lock is let go, with what was removed - and it takes nothing back and changes nothing
$stage();
$heard = new ArrayObject();
\Nino\Callbacks::registerCallback( $appData, '/nino/pages/removed', static function( array &$appData, array &$event ) use ( $heard, $sandbox ): array {

	$other = $appData;
	unset( $other['./nino/filesystem/locks'] );
	$free = \Nino\Filesystem::lockFile( $other, '/config.php', false ) === true;
	\Nino\Filesystem::unlockFile( $other, '/config.php' );

	$heard[] = [ 'event' => $event, 'free' => $free, 'template' => is_file( $sandbox. '/private/templates/page-news.tpl' ) ];

	return [ 'template' => 'changed' ];
} );
$refusedFirst = \Nino\Pages::remove( $appData, 'page-nothing' );
$answer = \Nino\Pages::remove( $appData, 'page-news' );
check( 'a removal fires /nino/pages/removed once, after the lock was let go and the template was removed, with the answer - and a callback cannot change the answer; a removal that is refused fires nothing',
	count( $heard ) === 1 && $heard[0]['event'] === $answer && $heard[0]['free'] === true && $heard[0]['template'] === false && $answer['template'] === 'page-news' && isset( $refusedFirst['error'] ) === true );
unset( $appData['./nino/callbacks']['/nino/pages/removed'] );
$wipe();

// What a removal refuses, and with which code: nothing of the project changes
$stage();
\Nino\Filesystem::putFileContent( $appData, '/templates/page-home.tpl', '<p>home</p>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-gone.tpl', '<p>gone</p>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-twice.tpl', '<p>twice</p>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-includes.tpl', '<p>[template /templates/page-included]</p>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-included.tpl', '<p>included</p>' );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-routed.tpl', '<p>routed</p>' );
foreach( [ [ '/site-home', [ '' => '/' ], 'page-home' ], [ '/404', [ '' => '/404' ], 'page-gone' ], [ '/site-twice', [ '' => '/twice' ], 'page-twice' ], [ '/site-twice-b', [ '' => '/twice-b' ], 'page-twice' ],
	[ '/site-includes', [ '' => '/includes' ], 'page-includes' ], [ '/site-included', [ '' => '/included' ], 'page-included' ] ] as [ $pageUri, $pageHttp, $pageTemplate ] )
	\Nino\Pages::put( $appData, [ 'uri' => $pageUri, 'httpUris' => $pageHttp, 'template' => $pageTemplate, 'text' => $text( $pageUri ), 'navs' => [], 'statusCode' => $pageHttp === [ '' => '/404' ] ? 404 : 200 ] );
\Nino\Filesystem::putFileContent( $appData, '/templates/page-legal-imprint.tpl', '<p>imprint</p>' );
$appData['/nino/http/routes']['GET://legal/imprint'] = [ 'uri' => '/legal/imprint', 'locale' => 'de_DE', 'body' => '[template /templates/page-legal-imprint]' ];
$appData['/nino/http/routes']['GET://by-module'] = [ 'uri' => '/by-module', 'body' => '[template /templates/page-routed]' ];
$appData['/nino/http/routes']['GET://by-hand'] = [ 'uri' => '/by-hand', 'body' => '<main>[template /templates/page-news]</main>' ];
$holds = $tree();
$codes = [];

\Nino\Filesystem::putFileContent( $appData, '/templates/mail-owner.tpl', '<p>mail</p>' );
\Nino\Text::put( $appData, [ '/template/mail-owner/x/y' => [ 'de_DE' => 'mail' ] ] );
$holds = $tree();
foreach( [ 'page-home', 'page-gone', 'page-twice', 'page-included', 'page-routed', 'page-legal-imprint', 'page-maintenance', 'page-nothing', 'mail-owner', 'page-Mixed', '../config', "page-news\0", 'page-'. str_repeat( 'a', 200 ), '' ] as $name )
	$codes[$name] = \Nino\Pages::remove( $appData, $name )['error'] ?? [ 'status' => 200 ];

$codes['page-news'] = \Nino\Pages::remove( $appData, 'page-news' )['error'] ?? [ 'status' => 200 ];
unset( $appData['/nino/http/routes']['GET://by-hand'] );
check( 'a page is refused, with its own code and nothing written, where it answers "/" or the status 404, where a module or the kernel asks for it at every request, where two pages or another template draw it, where no page does - and for a name that is none',
	array_map( static fn( array $error ): string => $error['status']. ' '. ( $error['code'] ?? '' ), array_values( $codes ) ) === [ '409 routes_remove_home', '409 routes_remove_not_found', '409 routes_remove_shared', '409 routes_remove_included', '409 routes_remove_runtime',
	'409 routes_remove_runtime', '409 routes_remove_runtime', '404 routes_unknown_page', '400 routes_invalid_template', '400 routes_invalid_template', '400 routes_invalid_template', '400 routes_invalid_template', '400 routes_invalid_template', '400 routes_invalid_template', '409 routes_remove_included' ] && $tree() === $holds );
$wipe();
foreach( [ 'page-home', 'page-gone', 'page-twice', 'page-includes', 'page-included', 'page-routed', 'page-legal-imprint', 'mail-owner' ] as $name )
	@unlink( $sandbox. '/private/templates/'. $name. '.tpl' );
unset( $appData['/nino/http/routes']['GET://legal/imprint'], $appData['/nino/http/routes']['GET://by-module'] );


// A page copied: the copy draws what the original draws, in every language - its source has the keys of its own namespace and the
// keys it shares with others as they are
$wipe();
$stage();
$staged = $tree();
$dup = \Nino\Pages::duplicate( $appData, 'page-news', $copy );
$copySource = (string) file_get_contents( $sandbox. '/private/templates/page-copy.tpl' );
$same = [];

foreach( [ 'de_DE', 'en_US', 'fr_FR' ] as $locale )
	$same[$locale] = preg_replace( '/<!-- nino:template-name .*? -->/', '', $draw( 'page-news', $locale ) ) === preg_replace( '/<!-- nino:template-name .*? -->/', '', $draw( 'page-copy', $locale ) ) && str_contains( $draw( 'page-copy', $locale ), '<h1>' ) && str_contains( $draw( 'page-copy', $locale ), '[[/template/page-news-extra/a/b]]' );
check( 'a copy draws what its original draws in every language of the site, the keys of other namespaces stay what they are, and its source names none of the original\'s',
	isset( $dup['error'] ) === false && ( $dup['copied']['texts'] ?? null ) === 2 && array_filter( $same ) === $same && $draw( 'page-copy', 'de_DE' ) !== '' && str_contains( $draw( 'page-copy', 'de_DE' ), 'Hallo' )
	&& str_contains( $copySource, '[[/template/page-copy/hero/title]]' ) === true && str_contains( $copySource, '[[/template/page-news-extra/a/b]]' ) === true && str_contains( $copySource, '[[/template/page-news/' ) === false );
check( 'a copy has the keys of its own namespace where the parser reads a fill, and what is text - a sentence, an attribute of html - and the display name of its own: it is not rewritten, and the name is the new page\'s',
	str_contains( $copySource, '<p>The keys are /template/page-news/hero/title.</p><i data-key="/template/page-news/hero/title"></i>' ) === true && str_starts_with( $copySource, '<!-- nino:template-name Copy -->' ) === true );

// ...with words of its own in every language, that no change of the original reaches - and the format that was set for them
$keysOf = static function( string $namespace ) use ( &$appData ): array {

	$found = [];

	foreach( [ 'de_DE', 'en_US', 'fr_FR' ] as $locale )
		foreach( \Nino\Filesystem::getFileContent( $appData, '/text/'. $locale. '.php', [] ) as $key => $value )
			if( str_starts_with( (string) $key, '[['. $namespace ) === true )
				$found[$locale][ substr( (string) $key, strlen( $namespace ) + 2, -2 ) ] = $value;

	return $found;
};
\Nino\Text::put( $appData, [ '/template/page-news/hero/title' => [ 'de_DE' => 'Neu' ] ] );
$news_ = $keysOf( '/template/page-news/' );
$copy_ = $keysOf( '/template/page-copy/' );
$metaKeys = array_keys( \Nino\Text::meta( $appData ) );
check( 'a copy has the words of its original as words of its own in every language - and a change of the one does not reach the other - and the format and limit of a key go with it', array_keys( $copy_ ) === [ 'de_DE', 'en_US', 'fr_FR' ] && count( $copy_['de_DE'] ) === 2 && count( $copy_['en_US'] ) === 2 && count( $copy_['fr_FR'] ) === 2
	&& $copy_['de_DE']['hero/title'] === 'Hallo' && $news_['de_DE']['hero/title'] === 'Neu'
	&& in_array( '/template/page-copy/hero/title', $metaKeys, true ) === true && ( \Nino\Text::meta( $appData )['/template/page-copy/hero/title'] ?? null ) === [ 'format' => 'plain', 'maxlength' => 80 ] );

// ...and with pictures of its own: the slot with its label, size and alt text, and a file made from the original's
$images = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images'] ?? [];
$mine = $images['/template/page-copy/hero/image'] ?? [];
check( 'a copy has a slot of its own for each picture, with the label, the size and the alt text of the original and a file of its own name that holds the same bytes - the two share no file',
	( $mine['label'] ?? null ) === 'Hero' && ( $mine['alt']['de_DE'] ?? null ) === 'Ein Held' && ( $mine['width'] ?? null ) === 10 && ( $mine['filename'] ?? '' ) === 'template/page-copy/hero/image.10x10.webp' && $mine['filename'] !== $images['/template/page-news/hero/image']['filename']
	&& file_get_contents( $sandbox. '/public/images/'. $mine['filename'] ) === 'the bytes of the picture' && is_file( $sandbox. '/public/images/'. $photo ) === true );

// The original goes, the copy stays whole: its template, its words, its picture and its file - and the other way round
$drawn = $draw( 'page-copy', 'de_DE' );
\Nino\Pages::remove( $appData, 'page-news' );
$after = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] );
check( 'removing the original leaves the copy whole: its routes, its template and its words, its slot and the file of its picture', isset( $after['/nino/http/routes']['GET://copy'] ) === true && $draw( 'page-copy', 'de_DE' ) === $drawn && is_file( $sandbox. '/public/images/template/page-copy/hero/image.10x10.webp' ) === true
	&& isset( $after['/nino/html/images']['/template/page-copy/hero/image'] ) === true && is_file( $sandbox. '/public/images/'. $photo ) === false && count( $keysOf( '/template/page-copy/' )['fr_FR'] ) === 2 );
$wipe();
$stage();
$staged = $tree();
\Nino\Pages::duplicate( $appData, 'page-news', $copy );
$removed = \Nino\Pages::remove( $appData, 'page-copy' );
check( 'removing the copy leaves the original and the project as it was before the copy was made, byte for byte', $tree() === $staged && $removed['files'] === [ 'template/page-copy/hero/image.10x10.webp' ] );

// What a copy is refused for: the address of another page, a name that is none or taken, a source that is none - nothing is written
$holds = $tree();
$taken = [];
foreach( [ [ 'page-news', [ 'httpUris' => [ '' => '/news' ] ] ], [ 'page-news', [ 'httpUris' => [ '' => '/robots.txt' ] ] ], [ 'page-news', [ 'uri' => '/site-news' ] ], [ 'page-news', [ 'template' => 'page-news' ] ], [ 'page-news', [ 'template' => 'page-there' ] ],
	[ 'page-news', [ 'template' => 'page-../x' ] ], [ 'page-news', [ 'template' => 'page-copy' ], 'taken' ], [ 'page-nothing', [] ], [ '../config', [] ], [ "page-news\0", [] ], [ 'page-there', [ 'template' => 'page-maintenance' ] ] ] as $attempt ) {

	if( ( $attempt[2] ?? '' ) === 'taken' )
		\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ) + [ '[[/template/page-copy/other/x]]' => 'left over' ] );

	$answer = \Nino\Pages::duplicate( $appData, $attempt[0], $attempt[1] + $copy );
	$taken[] = ( $answer['error']['status'] ?? 200 ). ' '. ( $answer['error']['code'] ?? '' );

	if( ( $attempt[2] ?? '' ) === 'taken' ) {
		\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', array_diff_key( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] ), [ '[[/template/page-copy/other/x]]' => 1 ] ) );
		$holds = $tree();
	}
}
check( 'a copy is refused, with a code and nothing written, for an address another page or route has, an Element-URI that is taken, a template that is there, a name that is none or reserved, words under the new name already and a source that is none',
	$taken === [ '400 routes_duplicate_http_uri', '409 routes_http_uri_taken', '400 routes_duplicate_uri', '409 routes_template_exists', '409 routes_template_exists', '400 routes_invalid_template', '409 routes_namespace_taken', '404 routes_unknown_template',
	'404 routes_unknown_template', '404 routes_unknown_template', '409 routes_reserved_template' ] && $tree() === $holds );

// A write that fails after the picture files were made: the copy is none, and the pictures are not left behind
$configPath = $sandbox. '/private/config.php';
$configBytes = (string) file_get_contents( $configPath );
$armed = new ArrayObject();
$GLOBALS['ninoHook']['blacklist'] = static function() use ( $armed ): array {
	$armed[] = true;
	return [];
};
$GLOBALS['ninoHook']['config'] = static function() use ( $armed, $configPath ): void {

	if( count( $armed ) > 0 && is_file( $configPath ) === true ) {
		unlink( $configPath );
		mkdir( $configPath );
	}
};
$plant( '/text/blacklist.php', 'blacklist' );
file_put_contents( $configPath, preg_replace( '/^<\?php return /', '<?php ( $GLOBALS[\'ninoHook\'][\'config\'] ?? static fn() => null )(); return ', $configBytes, 1 ) );
unset( $appData['./nino/filesystem/cache'] );
$planted = (string) file_get_contents( $configPath );
$plantedList = (string) file_get_contents( $sandbox. '/private/text/blacklist.php' );
$before = $tree();
$ignored = ninoWarnings();
$answer = \Nino\Pages::duplicate( $appData, 'page-news', $copy );
$ignored = ninoWarnings();
$broken = is_dir( $configPath );
@rmdir( $configPath );
file_put_contents( $configPath, $planted );
file_put_contents( $sandbox. '/private/text/blacklist.php', $plantedList );
unset( $GLOBALS['ninoHook'], $appData['./nino/filesystem/cache'] );
check( 'a copy whose routes cannot be written is refused with 500, and no template, no key of its words, no meta entry, no slot and no file of a picture is left', $broken === true && ( $answer['error']['status'] ?? 0 ) === 500 && $tree() === $before && is_file( $sandbox. '/public/images/template/page-copy/hero/image.10x10.webp' ) === false
	&& is_dir( $sandbox. '/public/images/template/page-copy' ) === false );
file_put_contents( $configPath, $configBytes );
unset( $appData['./nino/filesystem/cache'] );
$wipe();

// The lock of config.php is a copy's as well, to the last write
$stage();
$turnedAway = new ArrayObject();
$GLOBALS['ninoHook']['blacklist'] = static function() use ( &$appData, $turnedAway ): array {
	$other = $appData;
	unset( $other['./nino/filesystem/locks'] );
	$turnedAway[] = \Nino\Filesystem::lockFile( $other, '/config.php', false ) === false;
	return [ '/_nino/webpage/site-news/uri' ];
};
$plant( '/text/blacklist.php', 'blacklist' );
$answer = \Nino\Pages::duplicate( $appData, 'page-news', $copy );
unset( $GLOBALS['ninoHook'] );
check( 'a copy holds the lock of config.php from the first check to the write of its routes: every other writer that asked in between was turned away', isset( $answer['error'] ) === false && count( $turnedAway ) > 0 && in_array( false, $turnedAway->getArrayCopy(), true ) === false );
$wipe();


// The words of the workbench for every refusal of a removal and a copy, in both languages of the interface
$said = [];
foreach( [ 'en_US', 'de_DE' ] as $locale )
	$said[$locale] = require __DIR__. '/../_admin/Nino/Modules/Routes/text/'. $locale. '.php';
$everyCode = [ 'routes_unknown_page', 'routes_remove_home', 'routes_remove_not_found', 'routes_remove_runtime', 'routes_remove_shared', 'routes_remove_included', 'routes_namespace_taken', 'routes_unrestored', 'routes_template_alias', 'routes_source_unreadable' ];
check( 'every refusal of a removal and of a copy that has a code of its own has words in English and in German', array_filter( $everyCode, static fn( string $code ): bool => trim( (string) ( $said['en_US']['[[/_admin/error/'. $code. ']]'] ?? '' ) ) === '' || trim( (string) ( $said['de_DE']['[[/_admin/error/'. $code. ']]'] ?? '' ) ) === '' ) === []
	&& array_intersect( array_column( $codes, 'code' ), $everyCode ) !== [] );


echo "\nWhat a removal leaves to others, what it cannot give back, and two writers\n";

// The namespace of a neighbour whose name begins like the page's: its words, its meta, its global key, its slot and its file are not the
// page's and are as they were when the page is gone
$wipe();
\Nino\Text::put( $appData, [ '/template/page-news-extra/a/b' => [ 'de_DE' => 'Nachbar', 'en_US' => 'Neighbour', 'fr_FR' => 'Voisin' ] ] );
\Nino\Text::setMeta( $appData, '/template/page-news-extra/a/b', 'plain', 40 );
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ '[[/template/page-news-extra/g/k]]' => 'global' ] );
\Nino\Images::putSlot( $appData, '/template/page-news-extra/a/image', [ 'label' => 'Near', 'width' => 5, 'height' => 5, 'filename' => 'template/page-news-extra/a/image.5x5.webp' ] );
\Nino\Filesystem::putFileContent( $appData, '/images/template/page-news-extra/a/image.5x5.webp', 'near' );
$before = $tree();
$stage();
\Nino\Pages::remove( $appData, 'page-news' );
check( 'a neighbour whose name begins like the page\'s - page-news-extra - keeps its words, meta, global key, slot and file: the namespace of a page ends with its slash', $tree() === $before );

// A text and a slot that another template reads stay, and the answer says who reads them
$wipe();
$stage();
\Nino\Filesystem::putFileContent( $appData, '/templates/page-ab.tpl', '<p>[[/template/page-news/hero/title]]</p>[image /template/page-news/hero/image alt=""]' );
$removed = \Nino\Pages::remove( $appData, 'page-news' );
$kept = array_column( $removed['kept'], 'by', 'key' );
ksort( $kept );
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'a text and a slot that another template reads are not taken: they stay with their file and their format, are named in the answer with who reads them - and the page goes all the same',
	$kept === [ '/template/page-news/hero/image' => [ 'page-ab.tpl' ], '/template/page-news/hero/title' => [ 'page-ab.tpl' ] ] && ( $de['[[/template/page-news/hero/title]]'] ?? null ) === 'Hallo' && isset( $de['[[/template/page-news/hero/text]]'] ) === false
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/html/images']['/template/page-news/hero/image'] ) === true && is_file( $sandbox. '/public/images/'. $photo ) === true && $removed['files'] === []
	&& isset( \Nino\Text::meta( $appData )['/template/page-news/hero/title'] ) === true && is_file( $sandbox. '/private/templates/page-news.tpl' ) === false && isset( \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes']['GET://news'] ) === false );
$wipe();
@unlink( $sandbox. '/private/templates/page-ab.tpl' );

// A text of the page that stays is a reader itself: what its value names of the page stays with it
$stage();
\Nino\Filesystem::putFileContent( $appData, '/templates/page-ab.tpl', '<p>[[/template/page-news/hero/title]]</p>' );
\Nino\Text::put( $appData, [ '/template/page-news/hero/title' => [ 'de_DE' => 'Hallo [[/template/page-news/hero/text]]' ] ] );
$removed = \Nino\Pages::remove( $appData, 'page-news' );
$kept = array_column( $removed['kept'], 'by', 'key' );
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'a text of the page that stays because a template reads it keeps what its own value reads of the page: the kept text never shows a key that went',
	isset( $removed['error'] ) === false && ( $kept['/template/page-news/hero/title'] ?? null ) === [ 'page-ab.tpl' ] && ( $kept['/template/page-news/hero/text'] ?? null ) === [ '/template/page-news/hero/title (de_DE)' ]
	&& ( $de['[[/template/page-news/hero/text]]'] ?? null ) === 'Welt' );
\Nino\Text::put( $appData, [ '/template/page-words/a/b' => [ 'en_US' => 'only a word' ] ] );
\Nino\Filesystem::putFileContent( $appData, '/images/template/page-file/a.webp', 'file' );
check( 'taken() says that a name has something under it - the words a removal kept - and that a name with nothing is free, so that a tool takes another name before it writes over them',
	\Nino\Pages::taken( $appData, 'page-news' ) === true && \Nino\Pages::taken( $appData, 'page-ab' ) === true && \Nino\Pages::taken( $appData, 'page-words' ) === true && \Nino\Pages::taken( $appData, 'page-file' ) === true && \Nino\Pages::taken( $appData, 'page-free' ) === false && \Nino\Pages::taken( $appData, 'Not A Name' ) === true );
\Nino\Text::put( $appData, [ '/template/page-words/a/b' => [ 'en_US' => '' ] ] );
@unlink( $sandbox. '/public/images/template/page-file/a.webp' );
@rmdir( $sandbox. '/public/images/template/page-file' );
$wipe();
@unlink( $sandbox. '/private/templates/page-ab.tpl' );

// The details of the page that another template reads, and a text whose value holds a fill of a key of the page, stay too - and what links
// to the page is told and holds nothing: the page goes, and uses() says before what remove() answers
$stage();
\Nino\Filesystem::putFileContent( $appData, '/templates/page-ab.tpl', '<a href="[[/_nino/webpage/site-news/uri]]">[[/_nino/webpage/site-news/name]]</a>' );
\Nino\Text::put( $appData, [ '/template/page-ab/x/title' => [ 'de_DE' => '[[/template/page-news/hero/text]]', 'en_US' => 'plain' ], '/template/page-ab/x/note' => [ 'de_DE' => 'See [[/_nino/webpage/site-news/title]]' ] ] );
$asked = \Nino\Pages::uses( $appData, 'page-news' );
$removed = \Nino\Pages::remove( $appData, 'page-news' );
$kept = array_column( $removed['kept'], 'by', 'key' );
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'a detail of the page that a template reads, a text of the page that the value of another text reads in a fill and a detail that a value reads stay, are named with their readers, and the page goes: no refusal',
	isset( $removed['error'] ) === false && ( $kept['/_nino/webpage/site-news/uri'] ?? null ) === [ 'page-ab.tpl' ] && ( $kept['/_nino/webpage/site-news/name'] ?? null ) === [ 'page-ab.tpl' ] && ( $kept['/template/page-news/hero/text'] ?? null ) === [ '/template/page-ab/x/title (de_DE)' ]
	&& ( $kept['/_nino/webpage/site-news/title'] ?? null ) === [ '/template/page-ab/x/note (de_DE)' ] && ( $de['[[/_nino/webpage/site-news/uri]]'] ?? null ) === '/neuigkeiten' && ( $de['[[/template/page-news/hero/text]]'] ?? null ) === 'Welt' && ( $de['[[/_nino/webpage/site-news/title]]'] ?? null ) === 'News de'
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/global.php', [] )['[[/_nino/webpage/site-news/uri]]'] ) === true );
check( 'uses() answers what remove() answers, with nothing written: the page, the routes, what it would keep and what links to it', $asked === [ 'template' => 'page-news', 'uri' => '/site-news', 'routes' => [ 'GET://neuigkeiten', 'GET://news' ], 'remnant' => false, 'kept' => $removed['kept'], 'linked' => [ [ 'by' => 'page-ab.tpl', 'key' => '' ] ], 'removable' => true ]
	&& ( \Nino\Pages::uses( $appData, 'page-nothing' )['error']['code'] ?? '' ) === 'routes_unknown_page' );
$wipe();
@unlink( $sandbox. '/private/templates/page-ab.tpl' );

// A link with page:<uri> - in the call of a template, in the body of a route, in the value of a text a call reads - is told and holds nothing;
// a comment and the text of a template link to nothing
$stage();
\Nino\Filesystem::putFileContent( $appData, '/templates/page-ab.tpl', "<!-- page:/site-news and /template/page-news/hero/title -->\n<p>page:/site-news</p>\n[button /template/page-ab/x/label href=\"/template/page-ab/x/target\"][button href=\"page:/site-news#top\"]" );
\Nino\Text::put( $appData, [ '/template/page-ab/x/target' => [ 'de_DE' => 'page:/site-news', 'en_US' => 'page:/site-news#x' ] ] );
$appData['/nino/http/routes']['GET://linker'] = [ 'uri' => '/linker', 'body' => '[button href="page:/site-news"]' ];
$holds = \Nino\Pages::uses( $appData, 'page-news' );
$removed = \Nino\Pages::remove( $appData, 'page-news' );
unset( $appData['/nino/http/routes']['GET://linker'] );
check( 'a page that is linked to with page: - in a call of a template, in a route, in the value of a text - is told in linked and goes all the same; a comment and a sentence of a template read nothing',
	isset( $removed['error'] ) === false && $removed['linked'] === $holds['linked'] && $removed['kept'] === [] && array_column( $removed['linked'], 'by' ) === [ '/template/page-ab/x/target (de_DE)', '/template/page-ab/x/target (en_US)', 'GET://linker', 'page-ab.tpl' ]
	&& array_column( $removed['linked'], 'key' ) === [ '/template/page-ab/x/target', '/template/page-ab/x/target', '', '' ] );
$wipe();
@unlink( $sandbox. '/private/templates/page-ab.tpl' );

// A file another writer took a key out of while the page was removed stays without it, when the removal is given back; the keys of the
// page are where they stood
$stage();
$deBefore = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
$GLOBALS['ninoHook']['fr'] = static function() use ( &$appData ): array {

	static $calls = 0;

	if( ++$calls === 2 ) {
		$other = $appData;
		unset( $other['./nino/filesystem/locks'], $other['./nino/filesystem/cache'] );
		\Nino\Filesystem::putFileContent( $other, '/text/de_DE.php', array_diff_key( \Nino\Filesystem::getFileContent( $other, '/text/de_DE.php', [] ), [ '[[/template/common/x]]' => true ] ) );
	}

	return \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
};
$plant( '/text/fr_FR.php', 'fr' );
unset( $appData['./nino/filesystem/cache'] );
@mkdir( dirname( $lockOf( '/text/blacklist.php' ) ), 0777, true );
@unlink( $lockOf( '/text/blacklist.php' ) );
mkdir( $lockOf( '/text/blacklist.php' ) );
unset( $appData['./nino/filesystem/locks'] );
$answer = \Nino\Pages::remove( $appData, 'page-news' );
rmdir( $lockOf( '/text/blacklist.php' ) );
unset( $GLOBALS['ninoHook'], $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
$ignored = ninoWarnings();
$de = \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] );
check( 'a removal that is given back puts in again what it took out and nothing else: a key another writer deleted meanwhile stays deleted, and the keys of the page stand in their places', ( $answer['error']['status'] ?? 0 ) === 500
	&& isset( $de['[[/template/common/x]]'] ) === false && array_keys( $de ) === array_keys( array_diff_key( $deBefore, [ '[[/template/common/x]]' => true ] ) ) );
$wipe();

// A giving back that cannot be done says so: its own code, the files that were not put back, and a line in the log
$stage();
unlink( $sandbox. '/private/templates/page-news.tpl' );
mkdir( $sandbox. '/private/templates/page-news.tpl' );
file_put_contents( $sandbox. '/private/templates/page-news.tpl/inside', 'x' );
$GLOBALS['ninoHook']['blacklist'] = static function() use ( $lockOf ): array {

	@mkdir( dirname( $lockOf( '/text/en_US.php' ) ), 0777, true );
	@unlink( $lockOf( '/text/en_US.php' ) );
	mkdir( $lockOf( '/text/en_US.php' ) );

	return [ '/_nino/webpage/site-news/uri' ];
};
$plant( '/text/blacklist.php', 'blacklist' );
unset( $appData['./nino/filesystem/locks'] );
$ignored = ninoWarnings();
$answer = \Nino\Pages::remove( $appData, 'page-news' );
$logged = ninoWarnings();
@rmdir( $lockOf( '/text/en_US.php' ) );
unlink( $sandbox. '/private/templates/page-news.tpl/inside' );
rmdir( $sandbox. '/private/templates/page-news.tpl' );
unset( $GLOBALS['ninoHook'], $appData['./nino/filesystem/locks'], $appData['./nino/filesystem/cache'] );
check( 'a removal that fails and cannot give a file back answers 500 with the code routes_unrestored and the list of the files it could not put back, and says so in the log',
	( $answer['error']['status'] ?? 0 ) === 500 && ( $answer['error']['code'] ?? '' ) === 'routes_unrestored' && ( $answer['error']['unrestored'] ?? [] ) === [ '/text/en_US.php' ] && count( $logged ) === 1 && str_contains( $logged[0], 'en_US' ) );
$wipe();

// What is left of a page whose removal was cut off - words, slots and a template with no route - is taken like a page; a name that has
// such leftovers is not a name for a new page
$stage();
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content ): array {
	unset( $content['/nino/http/routes']['GET://neuigkeiten'], $content['/nino/http/routes']['GET://news'] );
	return $content;
} );
unset( $appData['/nino/http/routes']['GET://neuigkeiten'], $appData['/nino/http/routes']['GET://news'] );
$taken = \Nino\Pages::duplicate( $appData, 'page-there', [ 'template' => 'page-news', 'uri' => '/site-again' ] + $copy );
$taken2 = \Nino\Pages::put( $appData, [ 'template' => 'page-news', 'source' => '<p>x</p>', 'uri' => '/site-again' ] + $copy );
$removed = \Nino\Pages::remove( $appData, 'page-news' );
check( 'a template with its words and slots and no route is a leftover: it is refused as the name of a new page, with a code (a copy: the namespace is taken, a page: the template is there), and remove() takes it - template, words, slot and file - and says it was one',
	( $taken['error']['code'] ?? '' ) === 'routes_namespace_taken' && ( $taken2['error']['code'] ?? '' ) === 'routes_template_exists' && ( $removed['remnant'] ?? false ) === true && $removed['uri'] === '' && is_file( $sandbox. '/private/templates/page-news.tpl' ) === false
	&& isset( \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/template/page-news/hero/title]]'] ) === false && $removed['files'] === [ $photo ] && is_file( $sandbox. '/public/images/'. $photo ) === false );
$wipe();

// A callback that throws neither makes the removal throw nor keeps the others from hearing of it
$stage();
$heard = new ArrayObject();
\Nino\Callbacks::registerCallback( $appData, '/nino/pages/removed', static function(): void {
	throw new \RuntimeException( 'a callback that fails' );
}, 1 );
\Nino\Callbacks::registerCallback( $appData, '/nino/pages/removed', static function( array &$appData, array &$event ) use ( $heard ): void {
	$heard[] = $event['template'];
}, 9 );
$ignored = ninoWarnings();
$answer = \Nino\Pages::remove( $appData, 'page-news' );
$logged = ninoWarnings();
unset( $appData['./nino/callbacks']['/nino/pages/removed'] );
check( 'a callback of /nino/pages/removed that throws is caught and logged: the removal is answered, and the callbacks after it run', isset( $answer['error'] ) === false && $heard->getArrayCopy() === [ 'page-news' ] && count( $logged ) === 1 && str_contains( $logged[0], 'a callback that fails' ) );
$wipe();

// A template that is a link to another file, or twin of one in case, is none to remove or to copy: two names of one file on a file system that folds case
$stage();
symlink( 'page-news.tpl', $sandbox. '/private/templates/page-link.tpl' );
$byLink = \Nino\Pages::remove( $appData, 'page-news' );
$link = \Nino\Pages::remove( $appData, 'page-link' );
$copied = \Nino\Pages::duplicate( $appData, 'page-link', $copy );
unlink( $sandbox. '/private/templates/page-link.tpl' );
$twin = [ 'error' => [ 'code' => 'routes_template_alias' ] ];

// ...which is tried where the file system keeps the two apart
if( file_exists( $sandbox. '/private/templates/PAGE-NEWS.tpl' ) === false ) {
	file_put_contents( $sandbox. '/private/templates/page-News.tpl', 'twin' );
	$twin = \Nino\Pages::remove( $appData, 'page-news' );
	unlink( $sandbox. '/private/templates/page-News.tpl' );
}
check( 'a template that a link leads to is named by the link and is not removed, a link and a template that has a twin in other case are refused as templates with routes_template_alias, and nothing is written',
	( $byLink['error']['code'] ?? '' ) === 'routes_remove_included' && ( $link['error']['code'] ?? '' ) === 'routes_template_alias' && ( $copied['error']['code'] ?? '' ) === 'routes_template_alias' && ( $twin['error']['code'] ?? '' ) === 'routes_template_alias'
	&& is_file( $sandbox. '/private/templates/page-news.tpl' ) === true && is_file( $sandbox. '/private/templates/page-copy.tpl' ) === false );
$wipe();

// What a feature kept of a page - a picture below the namespace that no slot names - is in the way of a page of that name
$stage();
mkdir( $sandbox. '/public/images/template/page-copy', 0777, true );
file_put_contents( $sandbox. '/public/images/template/page-copy/kept.webp', 'kept' );
$holds = $tree();
$answer = \Nino\Pages::duplicate( $appData, 'page-news', $copy );
check( 'a picture below images/template/<new name>/ that no slot names is in the way of a copy: it is refused with routes_namespace_taken and nothing is written', ( $answer['error']['code'] ?? '' ) === 'routes_namespace_taken' && $tree() === $holds );
$wipe();

// Two writers: one makes pages and removes them, the other puts pages on the templates the first makes - for a fixed number of rounds each.
// When both are done no route draws a template that is gone and no template of a page is left with no route
$wipe();
$pipes = [];
$procs = [];

foreach( [ 'a' => 40, 'b' => 400, 'c' => 400 ] as $role => $rounds )
	$procs[$role] = proc_open( [ PHP_BINARY, __FILE__, 'child', $sandbox, $role, (string) $rounds ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes[$role] );

$told = [];
$codes = [];

foreach( $procs as $role => $proc ) {
	$told[$role] = (string) stream_get_contents( $pipes[$role][1] );
	stream_get_contents( $pipes[$role][2] );
	fclose( $pipes[$role][1] );
	fclose( $pipes[$role][2] );
	$codes[$role] = proc_close( $proc );
}

unset( $appData['./nino/filesystem/cache'] );
$routes = \Nino\Filesystem::getFileContent( $appData, '/config.php', [] )['/nino/http/routes'] ?? [];
$drawn = [];
$gone = [];

foreach( $routes as $route )
	if( is_array( $route ) === true && ( $name = \Nino\Pages::templateOf( (string) ( $route['body'] ?? '' ) ) ) !== null ) {
		$drawn[$name] = true;

		if( is_file( $sandbox. '/private/templates/'. $name. '.tpl' ) === false )
			$gone[] = $name;
	}

$loose = array_filter( array_map( static fn( string $file ): string => basename( $file, '.tpl' ), glob( $sandbox. '/private/templates/page-l*.tpl' ) ?: [] ), static fn( string $name ): bool => isset( $drawn[$name] ) === false );
check( 'three writers - one puts pages and removes them, one puts pages on their templates, one locks config.php, reads the routes and writes them back as the panels of the workbench do - leave no route on a template that is gone and no template of a page with no route',
	$codes === [ 'a' => 0, 'b' => 0, 'c' => 0 ] && json_decode( $told['a'], true ) !== null && json_decode( $told['c'], true ) === [ 'written' => 400 ] && $gone === [] && $loose === [] );
$wipe();


// A copy is the new page's own where it names the page it is made from: its four details - and not a page below it - and a link with page:
// in a call and in a copied text point at the copy, and a key of the namespace read in a fill of a text is the copy's
$wipe();
\Nino\Pages::put( $appData, [ 'uri' => '/team', 'httpUris' => [ '' => '/team' ], 'template' => 'page-team', 'text' => $text( 'Team' ), 'navs' => [],
	'source' => '<a href="[[/_nino/webpage/team/anna/uri]]">[[/_nino/webpage/team/uri]]</a>[button href="page:/team#top"][button href="page:/team/anna"]' ] );
\Nino\Text::put( $appData, [ '/template/page-team/x/link' => [ 'de_DE' => 'page:/team', 'en_US' => 'Go to [[/_nino/webpage/team/name]] and [[/template/page-team/x/y]]' ] ] );
$crew = \Nino\Pages::duplicate( $appData, 'page-team', [ 'uri' => '/crew', 'httpUris' => [ '' => '/crew' ], 'template' => 'page-crew', 'text' => $text( 'Crew' ), 'navs' => [] ] );
$crewSource = (string) file_get_contents( $sandbox. '/private/templates/page-crew.tpl' );
$crewTexts = \Nino\Filesystem::getFileContent( $appData, '/text/en_US.php', [] );
check( 'a copy shows its own details and links to itself with page:, in its calls and in its copied texts, and leaves the details of a page below the original and a link to it as they are',
	isset( $crew['error'] ) === false && $crewSource === '<a href="[[/_nino/webpage/team/anna/uri]]">[[/_nino/webpage/crew/uri]]</a>[button href="page:/crew#top"][button href="page:/team/anna"]'
	&& \Nino\Filesystem::getFileContent( $appData, '/text/de_DE.php', [] )['[[/template/page-crew/x/link]]'] === 'page:/crew' && $crewTexts['[[/template/page-crew/x/link]]'] === 'Go to [[/_nino/webpage/crew/name]] and [[/template/page-crew/x/y]]'
	&& \Nino\Pages::uses( $appData, 'page-team' )['linked'] === [] );
unlink( $sandbox. '/private/templates/page-crew.tpl' );
$wipe();
@unlink( $sandbox. '/private/templates/page-team.tpl' );

// What a cut-off run leaves - the template, the words, the details of the page and a picture below its folder, and no route and no slot - is
// a leftover that remove() takes whole: the project is then as it was before the page, and the name is free
$wipe();
$stage();
$staged = $tree();
\Nino\Pages::duplicate( $appData, 'page-news', $copy );
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content ): array {
	unset( $content['/nino/http/routes']['GET://copy'], $content['/nino/html/images']['/template/page-copy/hero/image'] );
	return $content;
} );
unset( $appData['/nino/http/routes']['GET://copy'], $appData['/nino/html/images']['/template/page-copy/hero/image'] );
$asked = \Nino\Pages::uses( $appData, 'page-copy' );
$removed = \Nino\Pages::remove( $appData, 'page-copy' );
$clean = $tree() === $staged;
$asStored = \Nino\Pages::duplicate( $appData, 'page-news', $copy );
check( 'the leftovers of a copy that was cut off - template, words, details of the page, a picture file without a slot - are taken by remove() as one and the project is as it was before the copy; uses() says it is a leftover, and the name is free for a new page',
	( $asked['remnant'] ?? false ) === true && ( $removed['remnant'] ?? false ) === true && $removed['files'] === [ 'template/page-copy/hero/image.10x10.webp' ] && $clean === true && isset( $asStored['error'] ) === false );
\Nino\Pages::remove( $appData, 'page-copy' );
mkdir( $sandbox. '/public/images/template/page-ghost/deep', 0777, true );
file_put_contents( $sandbox. '/public/images/template/page-ghost/deep/x.webp', 'x' );
$ghost = \Nino\Pages::remove( $appData, 'page-ghost' );
check( 'a folder of pictures below images/template/<name>/ with no page, no template and no words is a leftover: remove() takes the files and the folders', ( $ghost['remnant'] ?? false ) === true && $ghost['files'] === [ 'template/page-ghost/deep/x.webp' ] && is_dir( $sandbox. '/public/images/template/page-ghost' ) === false );
$wipe();

// A page that is made under a name with a picture of an earlier page left - a file below its folder that no slot names - is refused with the
// code of the copy; words that are there are the words of the tool that makes the page, which writes them first
$wipe();
\Nino\Text::put( $appData, [ '/template/page-one/a/b' => [ 'de_DE' => 'words' ] ] );
mkdir( $sandbox. '/public/images/template/page-one/a', 0777, true );
file_put_contents( $sandbox. '/public/images/template/page-one/a/left.webp', 'left' );
$holds = $tree();
$answer = \Nino\Pages::put( $appData, $ok );
unlink( $sandbox. '/public/images/template/page-one/a/left.webp' );
$words = \Nino\Pages::put( $appData, $ok );
check( 'a page with a source under a name that has a picture of an earlier page below its folder is refused with routes_namespace_taken and nothing is written - and one whose words are there is made',
	( $answer['error']['code'] ?? '' ) === 'routes_namespace_taken' && $tree() !== '' && isset( $words['error'] ) === false );
$wipe();

ninoDone( $appData );
