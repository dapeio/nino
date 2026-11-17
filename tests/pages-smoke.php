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
unset( $appData['/nino/http/routes']['GET://legal/imprint'] );
\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', $de );
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

ninoDone( $appData );
