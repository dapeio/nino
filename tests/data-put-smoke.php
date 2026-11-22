<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	data-put-smoke.php	Test of the functions that write the words and the picture slots of a page in one step:
 *											\Nino\Text::put() and values(), \Nino\Images::putSlot() and dropSlot() - what they
 *											create, set and remove, what they answer of what was there before, what they refuse
 *											before they touch a file, and that a text is cleaned as the Text panel cleans it.
 *											Runs against an isolated sandbox directory, never touches the real project data.
 *
 *	Usage: php tests/data-put-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'data-put' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

mkdir( $sandbox. '/private/text', 0777, true );

$read = static fn( string $file ): array => (array) ( include $sandbox. '/private/'. $file );
$key = '/template/page-x/hero/title';

\Nino\Filesystem::putFileContent( $appData, '/text/de_DE.php', [ '[[/template/page-x/hero/old]]' => 'Alt', '[[/template/page-y/a/b]]' => 'Y' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/en_US.php', [ '[[/template/page-x/hero/old]]' => 'Old' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/global.php', [ '[[/project/company/general/name]]' => 'ACME' ] );
\Nino\Filesystem::putFileContent( $appData, '/text/meta.php', [ '/template/page-x/hero/old' => [ 'format' => 'inline' ], '/template/page-y/a/b' => [ 'format' => 'lines' ] ] );

echo "Text::put() and values()\n";

$was = \Nino\Text::put( $appData, [ $key => [ 'de_DE' => 'Titel' ] ] );
check( 'a new key is written to the file of every language, its text in the one that was named and an empty one in the others', $was === [ $key => [ 'de_DE' => null, 'en_US' => null ] ]
	&& $read( 'text/de_DE.php' )['[['. $key. ']]'] === 'Titel' && $read( 'text/en_US.php' )['[['. $key. ']]'] === '' );

$was = \Nino\Text::put( $appData, [ $key => [ 'en_US' => 'Title' ] ] );
check( 'a key that is there is set in the language named and in no other, and the answer is what stood there', $was === [ $key => [ 'en_US' => '' ] ]
	&& $read( 'text/en_US.php' )['[['. $key. ']]'] === 'Title' && $read( 'text/de_DE.php' )['[['. $key. ']]'] === 'Titel' );

$was = \Nino\Text::put( $appData, [ '/template/page-x/hero/other' => [ 'de_DE' => 'Ein' ] ], [ '/template/page-x/hero/old', '/project/company/general/name' ] );
check( 'a key that is removed leaves every language file, global.php and meta.php with it; the others are not touched',
	$was['/template/page-x/hero/old'] === [ 'de_DE' => 'Alt', 'en_US' => 'Old' ] && $was['/project/company/general/name'] === [ '*' => 'ACME' ]
	&& array_key_exists( '[[/template/page-x/hero/old]]', $read( 'text/de_DE.php' ) ) === false && array_key_exists( '[[/template/page-x/hero/old]]', $read( 'text/en_US.php' ) ) === false
	&& $read( 'text/global.php' ) === [] && array_keys( $read( 'text/meta.php' ) ) === [ '/template/page-y/a/b' ] && $read( 'text/de_DE.php' )['[[/template/page-y/a/b]]'] === 'Y' );

// What a value is as the Text panel stores it: the format of the key, the brackets of a call an entity
$evil = '<script>alert(1)</script><b onclick="x()">fett</b> [template /templates/x] [[/a/b/c/d]] "q"';
\Nino\Text::put( $appData, [ '/template/page-x/hero/plain' => [ 'de_DE' => $evil ] ], [], [ '/template/page-x/hero/plain' => 'plain' ] );
\Nino\Text::put( $appData, [ '/template/page-x/hero/rich' => [ 'de_DE' => $evil ] ], [], [ '/template/page-x/hero/rich' => 'inline' ] );
check( 'a text is cleaned as the panel cleans it, by the format the caller names for the key', $read( 'text/de_DE.php' )['[[/template/page-x/hero/plain]]'] === \Nino\Text::sanitizeValue( $evil, 'plain' )
	&& $read( 'text/de_DE.php' )['[[/template/page-x/hero/rich]]'] === \Nino\Text::sanitizeValue( $evil, 'inline' ) && str_contains( implode( '', $read( 'text/de_DE.php' ) ), '<script' ) === false
	&& str_contains( implode( '', $read( 'text/de_DE.php' ) ), '[template' ) === false );

// Refused before a file is touched: the whole call
\Nino\Filesystem::mutate( $appData, '/text/global.php', static fn( array $content ): array => $content + [ '[[/project/company/general/tag]]' => 'Tag' ] );
$files = [ 'text/de_DE.php', 'text/en_US.php', 'text/global.php', 'text/meta.php' ];
$snapshot = static fn(): array => array_map( static fn( string $file ): string => (string) file_get_contents( $GLOBALS['sandbox']. '/private/'. $file ), $files );
$stands = $snapshot();
// Refused whole, before a file is touched: a key that is none of the form, with a path, with a capital, a language with no file, a text that is none,
// a key that is global, a key set and removed
$refused = [
	[ [ '/_nino/webpage/x/uri' => [ 'de_DE' => 'x' ], $key => [ 'de_DE' => 'y' ] ], [] ],
	[ [ '/template/../x/y/z' => [ 'de_DE' => 'x' ] ], [] ],
	[ [ $key => [ 'fr_FR' => 'x' ] ], [] ],
	[ [ $key => [ 'de_DE' => [ 'x' ] ] ], [] ],
	[ [ '/project/company/general/tag' => [ 'de_DE' => 'x' ] ], [] ],
	[ [ $key => [ 'de_DE' => 'x' ] ], [ $key ] ],
	[ [ $key => [ 'de_DE' => 'x' ] ], [ '/project/../x' ] ],
];
$answers = array_map( static fn( array $call ): ?array => \Nino\Text::put( $GLOBALS['appData'], $call[0], $call[1] ), $refused );
check( 'a call with a key or a language or a text that is none, a global key to set or a key set and removed is refused whole, and no file is touched', array_filter( $answers, static fn( ?array $answer ): bool => $answer !== null ) === [] && $snapshot() === $stands );

// The format that meta.php sets for a key is the Text Keys tab's decision and the caller does not overrule it
$plainKey = '/template/page-x/hero/alt';
\Nino\Filesystem::mutate( $appData, '/text/meta.php', static fn( array $meta ): array => $meta + [ $plainKey => [ 'format' => 'plain' ] ] );
\Nino\Text::put( $appData, [ $plainKey => [ 'de_DE' => $evil ] ], [], [ $plainKey => 'inline' ] );
check( 'a key whose format meta.php sets is cleaned by that one, whatever format the caller names for it', $read( 'text/de_DE.php' )['[['. $plainKey. ']]'] === \Nino\Text::sanitizeValue( $evil, 'plain' ) );

$keys = \Nino\Text::values( $appData, '/template/page-x' );
check( 'values() answers the keys below a namespace in every language, and not a key that only begins alike', array_keys( $keys ) === [ '/template/page-x/hero/alt', '/template/page-x/hero/other', '/template/page-x/hero/plain', '/template/page-x/hero/rich', $key ]
	&& $keys[$key] === [ 'de_DE' => 'Titel', 'en_US' => 'Title' ] && \Nino\Text::values( $appData, '/template/page' ) === [] && \Nino\Text::values( $appData, '/template/../x' ) === [] && \Nino\Text::values( $appData, '' ) === [] );

echo "\nImages::putSlot() and dropSlot()\n";

$slot = '/template/page-x/hero/image';
$config = static fn(): array => $GLOBALS['read']( 'config.php' )['/nino/html/images'] ?? [];

check( 'a slot is created in config.php with no file, and the answer says it was not there', \Nino\Images::putSlot( $appData, $slot, [ 'label' => 'Hero', 'width' => 1200, 'height' => 600 ] ) === []
	&& $config()[$slot] === [ 'label' => 'Hero', 'width' => 1200, 'height' => 600, 'filename' => null ] && $appData['/nino/html/images'][$slot]['width'] === 1200 );

// A file and an alt text saved by another request, then the slot is set again
\Nino\Filesystem::mutate( $appData, '/config.php', static function( array $content ) use ( $slot ): array {
	$content['/nino/html/images'][$slot]['filename'] = 'hero.webp';
	$content['/nino/html/images'][$slot]['alt'] = [ 'de_DE' => 'Haus' ];
	return $content;
} );
$was = \Nino\Images::putSlot( $appData, $slot, [ 'label' => 'Held', 'width' => 800, 'height' => 400 ] );
check( 'a slot that is there is set - its label and its size - and keeps its file and its alt texts; the answer is the slot as it was',
	$was['label'] === 'Hero' && $was['filename'] === 'hero.webp' && $config()[$slot] === [ 'label' => 'Held', 'width' => 800, 'height' => 400, 'filename' => 'hero.webp', 'alt' => [ 'de_DE' => 'Haus' ] ] );

$stands = (string) file_get_contents( $sandbox. '/private/config.php' );
$refused = [
	[ '/Template/x', [ 'label' => 'L', 'width' => 10, 'height' => 10 ] ],
	[ '/template/../x', [ 'label' => 'L', 'width' => 10, 'height' => 10 ] ],
	[ $slot, [ 'label' => '  ', 'width' => 10, 'height' => 10 ] ],
	[ $slot, [ 'label' => 'L', 'width' => 100000, 'height' => 100000 ] ],
	[ $slot, [ 'label' => 'L', 'width' => 10, 'height' => 10, 'filename' => '../../config.php' ] ],
	[ $slot, [ 'label' => 'L', 'width' => 10, 'height' => 10, 'filename' => "a\0.webp" ] ],
];
$refusedAll = array_filter( $refused, static fn( array $call ): bool => \Nino\Images::putSlot( $GLOBALS['appData'], $call[0], $call[1] ) !== false );
check( 'a slot with a uri that is none, a label that is none, a size no upload can fill or a file name that leaves /images/ is refused, and config.php is as it was', $refusedAll === [] && (string) file_get_contents( $sandbox. '/private/config.php' ) === $stands );

check( 'dropSlot() takes the slot out of config.php and answers it; its file is not touched', \Nino\Images::dropSlot( $appData, $slot )['filename'] === 'hero.webp' && isset( $config()[$slot] ) === false && isset( $appData['/nino/html/images'][$slot] ) === false );
check( '...and a slot that is not there is none to drop', \Nino\Images::dropSlot( $appData, $slot ) === false && \Nino\Images::dropSlot( $appData, '/x/../y' ) === false );

// A reader takes the lock, includes the file and writes what it read back: the compiled copy OPcache holds of the file has to be
// gone before the lock is, or the next writer reads the list as it was and the entry written here is lost. OPcache is off on the
// command line, so the order is read in the method: the write, the invalidation, the release, in that order, and no invalidation after
$body = ( static function(): string {
	$method = new \ReflectionMethod( \Nino\Filesystem::class, 'putFileContent' );
	return implode( '', array_slice( (array) file( (string) $method->getFileName() ), $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1 ) );
} )();
$written = strpos( $body, 'self::_writeFile(' );
$invalidated = strpos( $body, 'opcache_invalidate(' );
$released = strpos( $body, 'self::unlockFile(', (int) $written );
check( 'SAFETY: a write drops the compiled copy of the file before it releases the lock, and at no other place',
	$written !== false && $invalidated !== false && $released !== false && $written < $invalidated && $invalidated < $released && substr_count( $body, 'opcache_invalidate(' ) === 1 );

// --- Filesystem::removeDir - a link is dropped as a link and never entered ---------------

echo "\nFilesystem::removeDir\n";

$cases = $sandbox. '/removedir';
$outside = $cases. '/outside';
$makeOutside = static function( string $dir ): void {
	@mkdir( $dir. '/sub/deeper', 0777, true );
	file_put_contents( $dir. '/a.txt', 'a' );
	file_put_contents( $dir. '/sub/b.txt', 'b' );
	file_put_contents( $dir. '/sub/deeper/c.txt', 'c' );
};
$whole = static fn( string $dir ): bool => @file_get_contents( $dir. '/a.txt' ) === 'a' && @file_get_contents( $dir. '/sub/b.txt' ) === 'b' && @file_get_contents( $dir. '/sub/deeper/c.txt' ) === 'c';
$makeOutside( $outside );
$linkable = function_exists( 'symlink' ) === true;

// a link to a folder outside, as the folder's own entry
mkdir( $cases. '/one/inner', 0777, true );
file_put_contents( $cases. '/one/inner/f.txt', 'f' );
$linkable && symlink( $outside, $cases. '/one/lnk' );
\Nino\Filesystem::removeDir( $cases. '/one' );
check( 'a link to a folder outside is dropped with the folder it stands in, and what it points at is whole', is_dir( $cases. '/one' ) === false && is_link( $cases. '/one/lnk' ) === false && $whole( $outside ) === true );

// a link deep in the tree
mkdir( $cases. '/two/a/b/c', 0777, true );
$linkable && symlink( $outside, $cases. '/two/a/b/c/deep' );
$linkable && symlink( $outside. '/a.txt', $cases. '/two/a/file-link' );
\Nino\Filesystem::removeDir( $cases. '/two' );
check( 'a link deep in the tree - to a folder or to a file - is dropped, nothing of the tree stays, and the target is whole', file_exists( $cases. '/two' ) === false && is_link( $cases. '/two' ) === false && $whole( $outside ) === true );

// the path itself is a link
$linkable && symlink( $outside, $cases. '/self' );
\Nino\Filesystem::removeDir( $cases. '/self' );
check( 'a link as the path itself is dropped and not entered: the link is gone, the folder it pointed at is whole', is_link( $cases. '/self' ) === false && file_exists( $cases. '/self' ) === false && $whole( $outside ) === true );

// a link to a file as the path
$linkable && symlink( $outside. '/a.txt', $cases. '/filelink' );
\Nino\Filesystem::removeDir( $cases. '/filelink' );
check( 'a link to a file as the path is dropped and the file stays', is_link( $cases. '/filelink' ) === false && is_file( $outside. '/a.txt' ) === true );

// a broken link, in a tree and as the path
mkdir( $cases. '/three', 0777, true );
$linkable && symlink( $cases. '/nowhere', $cases. '/three/broken' );
$linkable && symlink( $cases. '/nowhere', $cases. '/broken' );
\Nino\Filesystem::removeDir( $cases. '/three' );
\Nino\Filesystem::removeDir( $cases. '/broken' );
check( 'a broken link in a tree and as the path is dropped as well', is_dir( $cases. '/three' ) === false && is_link( $cases. '/three/broken' ) === false && is_link( $cases. '/broken' ) === false );

// the path of a link ends in a slash, in /. or in both: it names what the link points at in the eyes of the file system, and the link is dropped all the same
foreach( [ '/' => 'slash', '/.' => 'dot', '//' => 'slashes', '/./' => 'dotslash' ] as $end => $name ) {
	$linkable && symlink( $outside, $cases. '/end-'. $name );
	\Nino\Filesystem::removeDir( $cases. '/end-'. $name. $end );
	check( 'a link whose path ends in "'. $end. '" is dropped, and what it points at is whole', is_link( $cases. '/end-'. $name ) === false && file_exists( $cases. '/end-'. $name ) === false && $whole( $outside ) === true );
}

// what is not there, a file and an ordinary tree: as it was
\Nino\Filesystem::removeDir( $cases. '/not-there' );
file_put_contents( $cases. '/plain.txt', 'x' );
\Nino\Filesystem::removeDir( $cases. '/plain.txt' );
$makeOutside( $cases. '/tree' );
\Nino\Filesystem::removeDir( $cases. '/tree' );
check( 'what is not there is nothing, a file is deleted, and a tree with no link in it is deleted whole', is_file( $cases. '/plain.txt' ) === false && file_exists( $cases. '/tree' ) === false && $whole( $outside ) === true );
check( ( $linkable === true ? '' : '(no symlinks here) ' ). 'the tree of the targets of the cases was never entered', $whole( $outside ) === true && count( (array) scandir( $outside ) ) === 4 );


ninoDone( $appData );
