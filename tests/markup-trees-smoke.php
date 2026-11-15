<?php
declare(strict_types=1);

/**
 *	Nino										A compact filesystembased php framework
 *	markup-trees-smoke.php	Dependency-free smoke test for the trees kept of the template files
 *													(\Nino\Html::fileTree(), renderFile(), dropTrees()): the second request does
 *													not open the file, a changed file, a changed register and an emptied folder
 *													give the right page, a folder that cannot be written and a file in it that is
 *													none are no reason for a warning or for a wrong page, what a template says
 *													stays inside the file as text, the switch turns it all off, two templates of
 *													one name in two folders are two trees, and a backup carries none.
 *													Runs against an isolated sandbox directory, never touches the real project
 *													data.
 *
 *	Usage: php tests/markup-trees-smoke.php
 */

require __DIR__. '/harness.php';

$appData = ninoSandbox( 'markup-trees' );
$sandbox = ninoSandboxDir( $appData );

echo "Sandbox: $sandbox\n\n";

mkdir( $sandbox. '/private/templates/a', 0777, true );
mkdir( $sandbox. '/private/templates/b', 0777, true );
mkdir( $sandbox. '/private/text', 0777, true );
mkdir( $sandbox. '/public/images', 0777, true );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

\Nino\Csrf::init( $appData );
\Nino\Modules\Csrf::init( $appData );
\Nino\Html::init( $appData );
\Nino\Modules\Template::init( $appData );

// A shortcode of the suite's own, a leaf: what it is handed, it answers in brackets
\Nino\Html::addShortcode( $appData, 'probe-leaf', static fn( array &$appData, array $args ): string => '<leaf>'. ( $args[0] ?? '' ). '</leaf>', false );

$trees = $sandbox. '/private/data/cache/trees';
$cache = ( new ReflectionClassConstant( \Nino\Html::class, 'CACHE' ) )->getValue();
$depth = ( new ReflectionClassConstant( \Nino\Html::class, 'DEPTH' ) )->getValue();
$mark = static fn( string $name ): string => $trees. '/'. sha1( $name ). '.php';

/**
 *	A request: what the one before kept in memory is gone, as it is for the next process
 */
$request = static function() use ( &$appData, $cache, $depth ): void {
	unset( $appData[$cache], $appData[$cache. '/said'], $appData[$depth. '/said'] );
	clearstatcache();
};

/**
 *	The page of a template file, by a request of its own
 */
$page = static function( string $name ) use ( &$appData, $request ): string {
	$request();
	return \Nino\Html::renderHtml( $appData, '[template '. $name. ']' );
};

/**
 *	The file a tree is kept in, as the kernel reads it - null where there is none or it is no php that answers
 */
$stored = static function( string $name ) use ( $mark ): mixed {
	return is_file( $mark( $name ) ) === true ? ( static fn( string $path ): mixed => @include $path )( $mark( $name ) ) : null;
};

/**
 *	A template file written with the time it is to have
 */
$write = static function( string $name, string $text, ?int $time = null ) use ( &$appData ): string {
	$path = \Nino\Filesystem::path( $appData, '/templates/'. $name. '.tpl' );
	file_put_contents( $path, $text );
	touch( $path, $time ?? 1767225600 );
	clearstatcache();
	return $path;
};


// --- What is kept, and where ------------------------------------------------------

echo "A tree is kept in a file of its own\n";

check( 'the folder and the switch are the ones the rest of the kernel names', ( new ReflectionClassConstant( \Nino\Html::class, 'TREES' ) )->getValue() === '/data/cache/trees'
	&& \Nino\Html::TREES_SWITCH === '/nino/markup/cache' && \Nino\AppData::DEFAULTS['/nino/markup/cache'] === true );

$write( 'one', '<p>one</p>[probe-leaf /x]' );
$out = $page( '/templates/one' );
$file = $stored( '/templates/one.tpl' );

check( 'a template is drawn as it is', $out === '<p>one</p><leaf>/x</leaf>' );
check( '...and its tree is kept below the private tree, under the sha1 of its path', is_file( $mark( '/templates/one.tpl' ) ) === true && str_starts_with( $mark( '/templates/one.tpl' ), $sandbox. '/private/' ) === true );
check( '...with the sha256 of the text, the stand of the register, the time and size of the file, the tree and the notes',
	is_array( $file ) === true && array_keys( $file ) === [ 'source', 'registry', 'mtime', 'size', 'tree', 'notes' ]
	&& $file['source'] === hash( 'sha256', '<p>one</p>[probe-leaf /x]' ) && $file['mtime'] === 1767225600 && $file['size'] === strlen( '<p>one</p>[probe-leaf /x]' )
	&& $file['tree'] === \Nino\Markup::parse( '<p>one</p>[probe-leaf /x]', [ 'probe-leaf' => false ] + \Nino\Components::containers( $appData ) ) && $file['notes'] === [] );
check( '...and the tree is the one of \\Nino\\Html::fileTree()', ( static function() use ( &$appData, $request ): bool {
	$request();
	return \Nino\Html::fileTree( $appData, '/templates/one.tpl' ) === \Nino\Markup::parse( '<p>one</p>[probe-leaf /x]', [ 'probe-leaf' => false ] + \Nino\Components::containers( $appData ) );
} )( ) );
check( 'a template that is not there has no tree and is nothing', $page( '/templates/nobody' ) === '' && is_file( $mark( '/templates/nobody.tpl' ) ) === false && \Nino\Html::fileTree( $appData, '/templates/nobody.tpl' ) === [] );
check( 'a template with no text has none either', $write( 'blank', '' ) !== '' && $page( '/templates/blank' ) === '' && is_file( $mark( '/templates/blank.tpl' ) ) === false );

$before = glob( $trees. '/*.php' );
\Nino\Html::renderSource( $appData, '<p>a text</p>[probe-leaf /y]' );
\Nino\Html::renderHtml( $appData, '[probe-leaf /z]' );
check( 'a source that comes from no file - a text, the body of a route - is kept in no file', glob( $trees. '/*.php' ) === $before );

echo "\n";


// --- The second request does not read ------------------------------------------------

echo "The second request does not read the template\n";

/*	The proof that the file is not opened is the page: the text of the file is changed to another of the same
	size and the file is given the time it had. A request that opened it would draw the new text; the one that
	draws the old text has compared the size and the time and nothing else. That is the limit of the comparison,
	and the documented one - the time has a second's resolution, so a tool that writes a file of the same size
	in the second after the last write, and does not set the time, is not seen. Every cache of the kernel that
	compares a file with a stamp has it (\Nino\Filesystem::getFileContent() does), and the folder is emptied
	where a workbench writes (\Nino\Modules\Cache::_invalidate()), which is where such a tool is not	*/
$write( 'same', '<p>AAA</p>' );
$first = $page( '/templates/same' );
$write( 'same', '<p>BBB</p>' );
$second = $page( '/templates/same' );

check( 'a template whose size and time are those of the last request is not opened: the page is the one of the tree', $first === '<p>AAA</p>' && $second === '<p>AAA</p>' );

$request();
check( '...and a file is looked at once in a request: what is written in between is not seen until the next', ( static function() use ( &$appData, $write ): bool {
	$one = \Nino\Html::fileTree( $appData, '/templates/same.tpl' );
	$write( 'same', '<p>CCCC</p>', 1767225700 );
	$two = \Nino\Html::fileTree( $appData, '/templates/same.tpl' );
	return $one === $two && $one === [ [ 't' => '<p>AAA</p>' ] ];
} )( ) );

$out = $page( '/templates/same' );
check( 'a template whose time and size are others is read, and the page is the new one', $out === '<p>CCCC</p>' );
check( '...and its tree is kept in place of the old one', ( $stored( '/templates/same.tpl' )['tree'] ?? null ) === [ [ 't' => '<p>CCCC</p>' ] ] && $stored( '/templates/same.tpl' )['mtime'] === 1767225700 );

$write( 'same', '<p>DDDD</p>', 1767225700 );
$write( 'same', '<p>EEEEE</p>', 1767225700 );
check( 'a template of another size and the same time is read', $page( '/templates/same' ) === '<p>EEEEE</p>' );
$write( 'same', '<p>FFFFF</p>', 1767225800 );
check( 'a template of the same size and another time is read', $page( '/templates/same' ) === '<p>FFFFF</p>' );

$write( 'touch', '<p>touched</p>[probe-leaf /t]', 1767225600 );
$page( '/templates/touch' );
$hash = $stored( '/templates/touch.tpl' )['source'];
$write( 'touch', '<p>touched</p>[probe-leaf /t]', 1767999999 );
$out = $page( '/templates/touch' );
check( 'a file that was touched and not changed is the same page, and the file of the tree has the new time', $out === '<p>touched</p><leaf>/t</leaf>'
	&& $stored( '/templates/touch.tpl' )['mtime'] === 1767999999 && $stored( '/templates/touch.tpl' )['source'] === $hash );

$request();
$tree = \Nino\Html::fileTree( $appData, '/templates/touch.tpl' );
\Nino\Html::dropTrees( $appData );
$write( 'touch', '<p>changed</p>', 1767999999 );
check( 'dropTrees() empties the folder and a file a request looked at is looked at again', is_dir( $trees ) === false && \Nino\Html::fileTree( $appData, '/templates/touch.tpl' ) === [ [ 't' => '<p>changed</p>' ] ] && $tree !== [] );

echo "\n";


// --- A changed register -------------------------------------------------------------

echo "A name that came or went gives another tree\n";

$write( 'reg', '[late]kid[/late] [late-leaf]' );
$out = $page( '/templates/reg' );
$kept = $stored( '/templates/reg.tpl' );

check( 'a name nobody knows is a container where its end stands in the document', is_array( $kept ) === true && ( $kept['tree'][0]['c'] ?? null ) === [ [ 't' => 'kid' ] ] );

\Nino\Html::addShortcode( $appData, 'late', static fn( array &$appData, array $args ): string => '<late/>', false );
$out = $page( '/templates/reg' );
$now = $stored( '/templates/reg.tpl' );

check( 'a shortcode registered as a leaf since is read as one: the end of it is text, and the tree kept is the new one', is_array( $now ) === true && array_key_exists( 'c', $now['tree'][0] ) === true && $now['tree'][0]['c'] === null
	&& $now['registry'] !== $kept['registry'] && str_starts_with( $out, '<late/>kid' ) === true && str_contains( $out, '[/late]' ) === false
	&& $now['tree'] === \Nino\Markup::parse( '[late]kid[/late] [late-leaf]', [ 'late' => false, 'probe-leaf' => false ] + \Nino\Components::containers( $appData ) ) );

\Nino\Components::addComponent( $appData, 'late-leaf', [ 'label' => 'Late', 'template' => '<b>late</b>' ] );
$out = $page( '/templates/reg' );
check( 'a component that came is read as one, and the stand is another again', ( $stored( '/templates/reg.tpl' )['registry'] ?? '' ) !== $now['registry'] && str_contains( $out, '<b>late</b>' ) === true );

check( 'the same register in the next request keeps the tree of the last one', ( static function() use ( $page, $stored ): bool {
	$one = $stored( '/templates/reg.tpl' );
	$page( '/templates/reg' );
	return $stored( '/templates/reg.tpl' ) === $one;
} )( ) );

echo "\n";


// --- The folder is emptied, cannot be written, holds a file that is none -----------------

echo "The folder\n";

$write( 'tidy', '<p>tidy</p>', 1767225600 );
$page( '/templates/tidy' );
check( 'the folder holds a file of every template drawn', count( glob( $trees. '/*.php' ) ) >= 2 );

\Nino\Filesystem::removeDir( $trees );
check( 'a folder that was emptied is made again by the next request, and the page is the right one', $page( '/templates/tidy' ) === '<p>tidy</p>' && is_file( $mark( '/templates/tidy.tpl' ) ) === true );

$ignored = ninoWarnings();
\Nino\Modules\Cache::_invalidate( $appData );
check( 'the page cache is dropped where the trees are: Cache::_invalidate() empties the folder', is_dir( $trees ) === false );

$write( 'tidy', '<p>tidy</p>', 1767225600 );
$request();
$one = \Nino\Html::fileTree( $appData, '/templates/tidy.tpl' );
$write( 'tidy', '<p>TIDY!</p>', 1767225700 );
\Nino\Modules\Cache::_invalidate( $appData );
check( '...and a template a request has looked at is looked at again after it, so that a page written by the workbench is drawn from what was written', $one === [ [ 't' => '<p>tidy</p>' ] ] && \Nino\Html::fileTree( $appData, '/templates/tidy.tpl' ) === [ [ 't' => '<p>TIDY!</p>' ] ] );
$write( 'tidy', '<p>tidy</p>', 1767225600 );

// A folder that cannot be written: a file stands where it should be (a mode would mean nothing to an account that may write anywhere)
\Nino\Filesystem::removeDir( $sandbox. '/private/data/cache' );
mkdir( $sandbox. '/private/data/cache', 0777, true );
file_put_contents( $trees, 'not a folder' );
$ignored = ninoWarnings();
$out = $page( '/templates/tidy' );
check( 'a folder that cannot be written is no reason for a page that is not drawn, nor for a warning', $out === '<p>tidy</p>' && ninoWarnings() === [] && is_file( $trees ) === true );
check( '...and the request after it draws the page as well', $page( '/templates/tidy' ) === '<p>tidy</p>' && ninoWarnings() === [] );
unlink( $trees );

// Files that are none
$bad = [
	'a number'								=> '<?php return 5;',
	'a string'								=> '<?php return "x";',
	'nothing'									=> '',
	'a list'									=> '<?php return [ 1, 2 ];',
	'an empty array'					=> '<?php return [];',
	'a key short'							=> '<?php return [ "source" => "x", "registry" => "x", "mtime" => 1, "size" => 1, "tree" => [] ];',
	'keys in another order'		=> '<?php return [ "registry" => "x", "source" => "x", "mtime" => 1, "size" => 1, "tree" => [], "notes" => [] ];',
	'a key too many'					=> '<?php return [ "source" => "x", "registry" => "x", "mtime" => 1, "size" => 1, "tree" => [], "notes" => [], "more" => 1 ];',
	'a time that is text'			=> '<?php return [ "source" => "x", "registry" => "x", "mtime" => "1", "size" => 1, "tree" => [], "notes" => [] ];',
	'a tree that is text'			=> '<?php return [ "source" => "x", "registry" => "x", "mtime" => 1, "size" => 1, "tree" => "<script>", "notes" => [] ];',
	'a node that is text'			=> '<?php return [ "source" => "x", "registry" => "x", "mtime" => 1, "size" => 1, "tree" => [ "EVIL" ], "notes" => [] ];',
	'a syntax error'					=> '<?php return [ "source" => ',
	'an error'								=> '<?php return 1 % 0;',
	'an exception'						=> '<?php throw new \Exception( "x" );',
	'a type error'						=> '<?php return strlen( [] );',
	'output'									=> 'EVIL OUTPUT <?php echo "MORE EVIL"; return null;',
	'html'										=> '<h1>EVIL</h1>',
	'binary'									=> "\0\1\2\377\376<?php",
];

$write( 'foreign', '<p>foreign</p>[probe-leaf /f]', 1767225600 );
$expected = '<p>foreign</p><leaf>/f</leaf>';

foreach( $bad as $what => $content ) {

	\Nino\Filesystem::forceDir( $appData, '/data/cache/trees' );
	file_put_contents( $mark( '/templates/foreign.tpl' ), $content );
	$ignored = ninoWarnings();

	ob_start();
	$out = $page( '/templates/foreign' );
	$printed = ob_get_clean();

	$kept = $stored( '/templates/foreign.tpl' );
	check( 'a file in the folder that is '. $what. ' is no tree: the page is the template\'s, nothing is printed, and the file is written over',
		$out === $expected && $printed === '' && ninoWarnings() === [] && is_array( $kept ) === true && $kept['source'] === hash( 'sha256', '<p>foreign</p>[probe-leaf /f]' ) );
}

// A file with the time, the size and the stand of the file it is for and a shape that is not one: each part, one by one
$page( '/templates/foreign' );
$good = $stored( '/templates/foreign.tpl' );
$shapes = [
	'a source that is a number'		=> [ 'source' => 5 ],
	'a stand that is a number'			=> [ 'registry' => 5 ],
	'a time that is text'					=> [ 'mtime' => (string) $good['mtime'] ],
	'a size that is a float'				=> [ 'size' => (float) $good['size'] ],
	'a tree that is text'					=> [ 'tree' => 'EVIL' ],
	'a tree that is null'					=> [ 'tree' => null ],
	'a node that is text'					=> [ 'tree' => [ 'EVIL' ] ],
	'a node that is a number'			=> [ 'tree' => [ [ 't' => 'EVIL' ], 5 ] ],
	'notes that are text'					=> [ 'notes' => 'EVIL' ],
];

foreach( $shapes as $what => $change ) {

	file_put_contents( $mark( '/templates/foreign.tpl' ), '<?php return '. var_export( array_replace( $good, $change ), true ). ';' );
	ob_start();
	$out = $page( '/templates/foreign' );
	$printed = ob_get_clean();
	check( 'a file with the right time, size and stand and '. $what. ' is no tree: the page is the template\'s, and the file is written over', $out === $expected && $printed === '' && $stored( '/templates/foreign.tpl' ) === $good );
}

// A file that is a tree for another file, the right shape and the wrong stat: not taken
file_put_contents( $mark( '/templates/foreign.tpl' ), '<?php return '. var_export( [ 'source' => 'x', 'registry' => 'x', 'mtime' => 1, 'size' => 1, 'tree' => [ [ 't' => 'EVIL' ] ], 'notes' => [] ], true ). ';' );
check( 'a tree of the right shape for a file of another time, size and register is not taken', $page( '/templates/foreign' ) === $expected );

echo "\n";


// --- The switch ----------------------------------------------------------------------

echo "The switch\n";

$write( 'sw', '<p>one</p>', 1767225600 );
\Nino\Filesystem::removeDir( $trees );
$appData['/nino/markup/cache'] = false;
$out = $page( '/templates/sw' );
check( 'with the switch off a template is drawn and nothing is written', $out === '<p>one</p>' && is_dir( $trees ) === false );

$appData['/nino/markup/cache'] = true;
$page( '/templates/sw' );
$write( 'sw', '<p>two</p>', 1767225600 );
check( 'with it on a template of the same size and time is not read again', $page( '/templates/sw' ) === '<p>one</p>' );

$appData['/nino/markup/cache'] = false;
$out = $page( '/templates/sw' );
check( 'with it off a tree that is kept is not taken: the template is read as often as it is asked for', $out === '<p>two</p>' && ( $stored( '/templates/sw.tpl' )['tree'] ?? null ) === [ [ 't' => '<p>one</p>' ] ] );
$appData['/nino/markup/cache'] = true;

echo "\n";


// --- Two templates of one name -------------------------------------------------------

echo "Two templates of one name in two folders\n";

$write( 'a/same', '<p>in a</p>' );
$write( 'b/same', '<p>in b</p>' );
$first = [ $page( '/templates/a/same' ), $page( '/templates/b/same' ) ];
$again = [ $page( '/templates/a/same' ), $page( '/templates/b/same' ) ];

check( 'each is its own page, cold and warm', $first === [ '<p>in a</p>', '<p>in b</p>' ] && $again === $first );
check( '...kept in two files', $mark( '/templates/a/same.tpl' ) !== $mark( '/templates/b/same.tpl' ) && ( $stored( '/templates/a/same.tpl' )['tree'] ?? null ) === [ [ 't' => '<p>in a</p>' ] ]
	&& ( $stored( '/templates/b/same.tpl' )['tree'] ?? null ) === [ [ 't' => '<p>in b</p>' ] ] );
check( 'a path spelled with a slash too many is the same template, and has the same file', ( static function() use ( &$appData, $request, $mark ): bool {
	$request();
	return \Nino\Html::fileTree( $appData, '/templates//a//same.tpl' ) === [ [ 't' => '<p>in a</p>' ] ] && count( glob( dirname( $mark( 'x' ) ). '/*.php' ) ) === count( array_unique( glob( dirname( $mark( 'x' ) ). '/*.php' ) ) );
} )( ) );

echo "\n";


// --- What a template says stays inside the file as text ------------------------------------

echo "What a template says\n";

$GLOBALS['ninoTreesRan'] = 0;
$evil = [
	'php open tag'			=> '<?php $GLOBALS[\'ninoTreesRan\']++; ?>',
	'short echo tag'		=> '<?= $GLOBALS[\'ninoTreesRan\']++ ?>',
	'a quote'						=> '\' . $GLOBALS[\'ninoTreesRan\']++ . \'',
	'a double quote'		=> '" . $GLOBALS[\'ninoTreesRan\']++ . "',
	'a backslash'				=> 'a\\\\b \\\' \\" \\0 \\x41 \\u{41} \\',
	'a quote and a slash'	=> '\\\' . $GLOBALS[\'ninoTreesRan\']++ . \'\\',
	'a null byte'				=> "a\0b\0",
	'a string end'			=> '\'; $GLOBALS[\'ninoTreesRan\']++; return [ \'',
	'a comment'					=> '*/ $GLOBALS[\'ninoTreesRan\']++; /*',
	'a heredoc'					=> "<<<X\n\$GLOBALS['ninoTreesRan']++;\nX;",
	'a dollar'					=> '${ $GLOBALS[\'ninoTreesRan\']++ } {$GLOBALS[\'ninoTreesRan\']++}',
	'a close tag'				=> '?> <script>x</script> <?php',
];

foreach( $evil as $what => $text ) {

	$source = '<p>'. $text. '</p>[probe-leaf '. '/x]<i title="a'. str_replace( '"', '\\"', $text ). '">[[/project/x/y/z]]</i>';
	$write( 'evil', $source, 1767225600 );
	$cold = $page( '/templates/evil' );
	$warm = $page( '/templates/evil' );
	$tokens = token_get_all( (string) file_get_contents( $mark( '/templates/evil.tpl' ) ) );
	$foreign = array_filter( $tokens, static fn( mixed $token ): bool => is_array( $token ) === true
		&& in_array( $token[0], [ T_OPEN_TAG, T_RETURN, T_ARRAY, T_DOUBLE_ARROW, T_LNUMBER, T_CONSTANT_ENCAPSED_STRING, T_WHITESPACE, T_STRING ], true ) === false );
	$strings = array_filter( $tokens, static fn( mixed $token ): bool => is_array( $token ) === true && $token[0] === T_STRING && in_array( strtolower( $token[1] ), [ 'null', 'true', 'false' ], true ) === false );

	check( 'a template with '. $what. ' is drawn as it is, cold and warm, and nothing of it runs', $cold === $warm && $GLOBALS['ninoTreesRan'] === 0
		&& str_contains( $cold, $text ) === true );
	check( '...and the file kept is data and nothing else: no variable, no call, no tag but the first', array_values( $foreign ) === [] && array_values( $strings ) === []
		&& count( array_filter( $tokens, static fn( mixed $token ): bool => is_array( $token ) === true && in_array( $token[0], [ T_VARIABLE, T_INLINE_HTML, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG ], true ) ) ) === 0
		&& $stored( '/templates/evil.tpl' )['tree'] === \Nino\Markup::parse( $source, [ 'probe-leaf' => false ] + \Nino\Components::containers( $appData ) ) );
}

check( 'a path that is none is no tree and writes nothing', ( static function() use ( &$appData, $trees ): bool {
	$before = glob( $trees. '/*.php' );
	ninoWarnings();
	$one = \Nino\Html::fileTree( $appData, '/templates/../../etc/passwd' );
	$two = \Nino\Html::fileTree( $appData, "/templates/a\0b.tpl" );
	$three = \Nino\Html::renderFile( $appData, '/templates/../x.tpl' );
	ninoWarnings();
	return $one === [] && $two === [] && $three === '' && glob( $trees. '/*.php' ) === $before;
} )( ) );

check( 'a template of more than a megabyte is not drawn and is kept in no file', ( static function() use ( $write, $page, $mark ): bool {
	$write( 'huge', str_repeat( 'x', \Nino\Modules\Template::MAX_BYTES + 1 ) );
	ninoWarnings();
	$out = $page( '/templates/huge' );
	$said = ninoWarnings();
	return $out === '' && is_file( $mark( '/templates/huge.tpl' ) ) === false && count( $said ) === 1;
} )( ) );

$big = str_repeat( 'abcdefgh', ( \Nino\Modules\Template::MAX_BYTES - 64 ) >> 3 );
$write( 'big', $big );
check( 'a template of a megabyte less a little is drawn as it is, cold and warm', $page( '/templates/big' ) === $big && $page( '/templates/big' ) === $big );

// A document the reader gives up on is said once in a request, from the notes that are kept as well
$write( 'limit', str_repeat( '[a ', \Nino\Markup::MAX_MARKS + 1 ) );
ninoWarnings();
$page( '/templates/limit' );
$cold = ninoWarnings();
$page( '/templates/limit' );
$warm = ninoWarnings();
check( 'a template the reader gives up on is said in the log on the request that reads it and on the one that finds its tree, once each', count( $cold ) === 1 && count( $warm ) === 1 );

echo "\n";


// --- A template that includes itself, and the depth ---------------------------------------

echo "A template that includes itself\n";

$write( 'self', 'x[template /templates/self]' );
ninoWarnings();
$out = $page( '/templates/self' );
$said = ninoWarnings();
check( 'it ends at the depth of sources, with one warning, from the tree as well as from the file', str_starts_with( $out, 'xxxx' ) === true && count( $said ) === 1 && $page( '/templates/self' ) === $out );

echo "\n";


// --- A backup carries none ---------------------------------------------------------

echo "A backup\n";

$page( '/templates/tidy' );
\Nino\Filesystem::putFileContent( $appData, '/data/sample.php', [ 'kept' => true ] );
$carried = \Nino\Backup::manifest( $appData );
check( 'the trees are in no backup, and the files that are content still are', array_filter( $carried, static fn( string $name ): bool => str_contains( $name, 'cache' ) === true ) === [] && count( glob( $trees. '/*.php' ) ) > 0 );

$greedy = $appData;
$greedy['./nino/features/all'] = [ 'greedy' => [
	'key' => 'greedy', 'dir' => \Nino\Features::dir(). '/Greedy', 'data' => [ '/data/', '/data/cache', '/data/cache/trees/', '/data/sample.php' ],
	'active' => false, 'problems' => [], 'version' => '1.0.0', 'installed' => null, 'module' => '',
] ];
$all = array_map( static fn( string $name ): string => (string) preg_replace( '#/+#', '/', $name ), \Nino\Backup::manifest( $greedy ) );
check( 'nor are they where a feature claims the folder of the cache, or the folder of the trees, as its own', array_filter( $all, static fn( string $name ): bool => str_contains( $name, 'cache' ) === true ) === [] && in_array( 'data/sample.php', $all, true ) === true );

\Nino\Filesystem::removeDir( $sandbox );

$empty = [];
ninoDone( $empty );
