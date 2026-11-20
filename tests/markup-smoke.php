<?php
declare(strict_types=1);

/**
 *	Nino								A compact filesystembased php framework
 *	markup-smoke.php		Dependency-free smoke test for \Nino\Markup: the notation read into
 *											a tree and a tree written back. The fourteen cases of the
 *											prototype and the thirteen inputs of the probe as trees with their
 *											notes, every rule of the grammar as a case of its own, the round
 *											trip of each of them and of the starter pages, a wall of random
 *											documents that nothing may throw at, and the documents that are
 *											too big, too deep or made of the wrong bytes. The cases are files
 *											below tests/fixtures/markup/cases: <id>.src is the document (its
 *											last line break is the file's, not the document's) and <id>.php
 *											the containers it is read with, the tree and the notes it gives.
 *											Nothing is drawn here: the class draws nothing.
 *											Nothing in this suite reads or writes a file of the project.
 *
 *	Usage: php tests/markup-smoke.php
 */

require __DIR__. '/harness.php';

$appData = [];
$fixtures = __DIR__. '/fixtures/markup';

/*	Ms of one call, for the lines that report what a big document costs	*/
$timed = static function( callable $run ): array {
	$started = hrtime( true );
	$result = $run();
	return [ $result, ( hrtime( true ) - $started ) / 1e6 ];
};

/*	A tree without the offsets: what two reads of one document have in common after a round trip	*/
$strip = static function( array $nodes ) use ( &$strip ): array {
	foreach( $nodes as &$node ) {
		unset( $node['o'] );
		if( isset( $node['c'] ) === true && is_array( $node['c'] ) === true )
			$node['c'] = $strip( $node['c'] );
	}
	return $nodes;
};

/*	The pieces a tree is written in, in the order of the document: [ 'text'|'mark', what it is written as ]. A text is a piece
	of its own, a call, its end and a textfill are one each - each written by write() alone, which is how the suite knows where
	one node ends and the next begins without a method of the class for it	*/
$pieces = static function( array $nodes, array $containers ) use ( &$pieces ): array {

	$found = [];

	foreach( $nodes as $node ) {

		if( isset( $node['t'] ) === true )
			$found[] = [ 'text', \Nino\Markup::write( [ $node ], $containers ) ];
		else if( isset( $node['f'] ) === true )
			$found[] = [ 'mark', \Nino\Markup::write( [ $node ], $containers ) ];
		else if( $node['c'] === null )
			$found[] = [ 'mark', \Nino\Markup::write( [ $node ], $containers ) ];
		else {
			$empty = \Nino\Markup::write( [ array_merge( $node, [ 'c' => [] ] ) ], $containers );
			$end = '[/'. $node['n']. ']';
			$found[] = [ 'mark', substr( $empty, 0, -strlen( $end ) ) ];
			foreach( $pieces( $node['c'], $containers ) as $piece )
				$found[] = $piece;
			$found[] = [ 'mark', $end ];
		}
	}

	return $found;
};

/*	The two promises of write(): the document it writes reads back as the tree, and writes as itself - both with the list of
	containers the tree was read with. Two forms of tree are not promised, and where a tree of that form does not come back
	the answer is null: a mark of the document written that is made of the pieces of two nodes (a text that ends in the start
	of a mark, and what follows it), or a document that ends in a \ inside calls that were never closed. A tree that does not
	come back and is of neither form is false	*/
$roundTrip = static function( array $tree, array $containers ) use ( $strip, $pieces ): ?bool {

	$written = \Nino\Markup::write( $tree, $containers );
	$second = [];
	$again = \Nino\Markup::parse( $written, $containers, $second );

	if( $strip( $again ) === $strip( $tree ) && \Nino\Markup::write( $again, $containers ) === $written )
		return true;

	if( substr( $written, -1 ) === '\\' )
		return null;

	// A leaf alone does not know that its name is a container elsewhere in the tree
	$known = $containers;
	$walk = [ $tree ];
	while( $walk !== [] )
		foreach( array_pop( $walk ) as $node )
			if( isset( $node['n'] ) === true && $node['c'] !== null ) {
				$known[ $node['n'] ] = true;
				$walk[] = $node['c'];
			}

	$parts = $pieces( $tree, $known );

	// A call or an end that has nothing but its name has a blank before its ] where a [ stands in front of it
	$starts = [];
	$joined = '';
	foreach( $parts as [ $kind, $piece ] ) {
		if( $kind === 'mark' && substr( $joined, -1 ) === '[' && preg_match( '/^\[\/?[a-z0-9-]+\]$/D', $piece ) === 1 )
			$piece = substr( $piece, 0, -1 ). ' ]';
		$starts[] = [ strlen( $joined ), strlen( $joined ) + strlen( $piece ), $kind ];
		$joined .= $piece;
	}

	// ...and where the pieces are still not the document, the suite does not know where the nodes end
	if( $joined !== $written )
		return false;

	preg_match_all( ( new \ReflectionClassConstant( \Nino\Markup::class, 'MARK' ) )->getValue(), $written, $found, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE );

	foreach( $found[0] as [ $mark, $offset ] ) {

		foreach( $starts as [ $from, $to, $kind ] )
			if( $offset >= $from && $offset < $to ) {

				$crosses = $kind === 'mark' ? $offset !== $from || $offset + strlen( $mark ) !== $to : $offset + strlen( $mark ) > $to;

				if( $crosses === true )
					return null;

				break;
			}
	}

	return false;
};

/*	The shape every tree has, whatever it was read from: the keys of a node in their order, no empty text, no text next to
	text, an offset that points at the [ of its mark, at most MAX_DEPTH containers inside each other	*/
$wellFormed = static function( array $nodes, string $source, int $depth = 0 ) use ( &$wellFormed ): bool {

	$before = null;

	foreach( $nodes as $index => $node ) {

		if( is_int( $index ) === false || is_array( $node ) === false )
			return false;

		$keys = array_keys( $node );

		if( $keys === [ 't' ] ) {
			if( is_string( $node['t'] ) === false || $node['t'] === '' || $before === 't' )
				return false;
			$before = 't';
			continue;
		}

		$before = 'x';

		if( $keys === [ 'f' ] ) {
			if( is_string( $node['f'] ) === false || preg_match( '/^[A-Za-z0-9_.\/:\[\]-]+$/D', $node['f'] ) !== 1 )
				return false;
			continue;
		}

		if( $keys !== [ 'n', 's', 'a', 'c', 'o' ] || is_string( $node['n'] ) === false || preg_match( '/^[a-z][a-z0-9-]*$/D', $node['n'] ) !== 1
			|| is_string( $node['s'] ) === false || is_array( $node['a'] ) === false || is_int( $node['o'] ) === false
			|| ( $source[ $node['o'] ] ?? '' ) !== '[' || strncmp( substr( $source, $node['o'] + 1 ), $node['n'], strlen( $node['n'] ) ) !== 0 )
			return false;

		foreach( $node['a'] as $key => $value )
			if( is_string( $key ) === false || ( $value !== true && is_string( $value ) === false ) )
				return false;

		if( $node['c'] !== null && ( is_array( $node['c'] ) === false || $depth + 1 > \Nino\Markup::MAX_DEPTH || $wellFormed( $node['c'], $source, $depth + 1 ) === false ) )
			return false;
	}

	return true;
};

/*	The notes of every read: the codes there are, the offset inside the document, the order of the offsets, the name of a call	*/
$wellNoted = static function( array $notes, string $source ): bool {

	$last = 0;

	foreach( $notes as $note ) {

		if( array_keys( $note ) !== [ 'code', 'name', 'offset' ] || in_array( $note['code'], [ 'unclosed', 'stray-end', 'depth', 'attribute', 'pattern', 'limit' ], true ) === false
			|| is_string( $note['name'] ) === false || is_int( $note['offset'] ) === false || $note['offset'] < $last || $note['offset'] > strlen( $source ) )
			return false;

		if( in_array( $note['code'], [ 'pattern', 'limit' ], true ) === false && preg_match( '/^[a-z][a-z0-9-]*$/D', $note['name'] ) !== 1 )
			return false;

		$last = $note['offset'];
	}

	return true;
};

/*	Every call of a tree, in the order of the document	*/
$calls = static function( array $nodes ) use ( &$calls ): array {
	$found = [];
	foreach( $nodes as $node )
		if( isset( $node['n'] ) === true ) {
			$found[] = $node['n'];
			foreach( $calls( $node['c'] ?? [] ) as $inner )
				$found[] = $inner;
		}
	return $found;
};

/*	How deep the containers of a tree stand inside each other	*/
$depthOf = static function( array $nodes ) use ( &$depthOf ): int {
	$deepest = 0;
	foreach( $nodes as $node )
		if( isset( $node['n'] ) === true && $node['c'] !== null )
			$deepest = max( $deepest, 1 + $depthOf( $node['c'] ) );
	return $deepest;
};


// --- The constants ---------------------------------------------------------

echo "The constants\n";

$class = new \ReflectionClass( \Nino\Markup::class );
check( 'MAX_DEPTH is sixteen, MAX_MARKS 50000, MAX_WORDS 64, MAX_NOTES 1000, and the breakpoints are m from 768 and l from 1024', $class->getReflectionConstant( 'MAX_DEPTH' )->getValue() === 16 && $class->getReflectionConstant( 'MAX_MARKS' )->getValue() === 50000 && (string) $class->getReflectionConstant( 'MAX_MARKS' )->getType() === 'int' && $class->getReflectionConstant( 'MAX_WORDS' )->getValue() === 64 && (string) $class->getReflectionConstant( 'MAX_WORDS' )->getType() === 'int'
	&& $class->getReflectionConstant( 'MAX_NOTES' )->getValue() === 1000 && (string) $class->getReflectionConstant( 'MAX_NOTES' )->getType() === 'int'
	&& $class->getReflectionConstant( 'MAX_WORDS_READ' )->getValue() === 200000 && (string) $class->getReflectionConstant( 'MAX_WORDS_READ' )->getType() === 'int'
	&& $class->getReflectionConstant( 'BREAKPOINTS' )->getValue() === [ 'm' => 768, 'l' => 1024 ] && \Nino\Markup::MAX_DEPTH === 16 && \Nino\Markup::BREAKPOINTS['l'] === 1024 );
check( 'the constants are public and typed, and parse() and write() are public and static', $class->getReflectionConstant( 'MAX_DEPTH' )->isPublic() === true
	&& (string) $class->getReflectionConstant( 'MAX_DEPTH' )->getType() === 'int' && (string) $class->getReflectionConstant( 'BREAKPOINTS' )->getType() === 'array'
	&& $class->getMethod( 'parse' )->isPublic() === true && $class->getMethod( 'parse' )->isStatic() === true
	&& $class->getMethod( 'write' )->isPublic() === true && $class->getMethod( 'write' )->isStatic() === true );
check( 'parse() takes the document, the containers and the notes by reference, write() the nodes and the list of containers - the class has no state', array_map( static fn( \ReflectionParameter $parameter ): string => $parameter->getName(). ( $parameter->isPassedByReference() === true ? '&' : '' ), $class->getMethod( 'parse' )->getParameters() ) === [ 'source', 'containers', 'notes&' ]
	&& array_map( static fn( \ReflectionParameter $parameter ): string => $parameter->getName(), $class->getMethod( 'write' )->getParameters() ) === [ 'nodes', 'containers' ] && $class->getMethod( 'write' )->getParameters()[1]->isOptional() === true && $class->getProperties() === [] );

echo "\n";


// --- The cases: the prototype, the probe and every rule of the grammar -------

echo "The cases - tree and notes of each document\n";

$cases = [];
foreach( glob( $fixtures. '/cases/*.php' ) ?: [] as $file ) {

	$id = basename( $file, '.php' );
	$source = (string) file_get_contents( $fixtures. '/cases/'. $id. '.src' );

	// The last line break of the file is the file's
	$cases[$id] = [ 'source' => substr( $source, -1 ) === "\n" ? substr( $source, 0, -1 ) : $source ] + require $file;
}

check( 'the fixtures hold the 14 cases of the prototype, the 13 inputs of the probe and a case for every rule', count( array_filter( array_keys( $cases ), static fn( string $id ): bool => str_starts_with( $id, 'proto-' ) ) ) === 14
	&& count( array_filter( array_keys( $cases ), static fn( string $id ): bool => str_starts_with( $id, 'probe-' ) ) ) === 13
	&& count( array_filter( array_keys( $cases ), static fn( string $id ): bool => str_starts_with( $id, 'rule-' ) ) ) >= 30 );

foreach( $cases as $id => $case ) {

	$notes = [ 'left over from before' ];
	$tree = \Nino\Markup::parse( $case['source'], $case['containers'], $notes );

	if( $tree !== $case['tree'] || $notes !== $case['notes'] )
		echo '        got '. json_encode( $tree, JSON_UNESCAPED_SLASHES ). ' '. json_encode( $notes ). "\n    expected ". json_encode( $case['tree'], JSON_UNESCAPED_SLASHES ). ' '. json_encode( $case['notes'] ). "\n";

	check( 'case '. $id. ': the tree and the notes', $tree === $case['tree'] && $notes === $case['notes'] );
}

$failed = array_filter( array_keys( $cases ), static fn( string $id ): bool => $roundTrip( $cases[$id]['tree'], $cases[$id]['containers'] ) !== true );
check( 'the tree of every case writes a document that reads back as the tree, and writes as itself ('. count( $cases ). ' cases)'. ( $failed === [] ? '' : ': '. implode( ', ', $failed ) ), $failed === [] );

$failed = array_filter( array_keys( $cases ), static function( string $id ) use ( $cases, $wellFormed, $wellNoted ): bool {
	$notes = [];
	return $wellFormed( \Nino\Markup::parse( $cases[$id]['source'], $cases[$id]['containers'], $notes ), $cases[$id]['source'] ) === false || $wellNoted( $notes, $cases[$id]['source'] ) === false;
} );
check( 'the tree and the notes of every case are well formed: the keys in their order, no empty text, no text next to text, offsets that point at their mark'. ( $failed === [] ? '' : ': '. implode( ', ', $failed ) ), $failed === [] );

echo "\n";


// --- The starter pages ---------------------------------------------------------

echo "The starter pages - real documents\n";

// The names of today's components and stacks, as the register will call them: what holds something is true
$today = [
	'card' => true, 'panel' => true, 'col' => true, 'element' => true, 'elements' => true,
	'stack' => true, 'repeat' => true, 'slider' => true, 'filter' => true, 'tabs' => true, 'accordion' => true, 'list' => true, 'table' => true, 'sections' => true,
	'title' => false, 'subtitle' => false, 'button' => false, 'image' => false, 'template' => false, 'csrf' => false, 'text' => false, 'line' => false,
];

$pages = [
	'home'		=> [ 'calls' => [ 'template', 'image', 'title', 'subtitle', 'button', 'template' ], 'fills' => 0 ],
	'contact'	=> [ 'calls' => [ 'template', 'title', 'subtitle', 'csrf', 'template' ], 'fills' => 14 ],
	'404'			=> [ 'calls' => [ 'template', 'title', 'subtitle', 'button', 'template' ], 'fills' => 0 ],
	'blank'		=> [ 'calls' => [ 'template', 'template' ], 'fills' => 0 ],
];

foreach( $pages as $page => $expected ) {

	$source = (string) file_get_contents( $fixtures. '/pages/page-'. $page. '.tpl' );
	$notes = [];
	$tree = \Nino\Markup::parse( $source, $today, $notes );

	$fills = 0;
	$walk = [ $tree ];
	while( $walk !== [] )
		foreach( array_pop( $walk ) as $node )
			if( isset( $node['f'] ) === true )
				$fills++;
			else if( is_array( $node['c'] ?? null ) === true )
				$walk[] = $node['c'];

	check( 'page '. $page. ': no note, its calls in the order of the document, '. $expected['fills']. ' textfills', $notes === [] && $calls( $tree ) === $expected['calls'] && $fills === $expected['fills'] && $wellFormed( $tree, $source ) === true );
	check( 'page '. $page. ': the tree writes the page again, byte for byte', \Nino\Markup::write( $tree ) === $source && $roundTrip( $tree, $today ) === true );
}

$source = (string) file_get_contents( $fixtures. '/pages/page-home.tpl' );
$tree = \Nino\Markup::parse( $source, $today );
$image = $tree[2]['c'] ?? null;
check( 'page home: the calls stand between the text of the page with their sources and attributes, and the first text is the comment that names the template',
	$tree[0] === [ 't' => "<!-- nino:template-name Home -->\n" ] && $tree[1]['n'] === 'template' && $tree[1]['s'] === '/templates/html-header' && $tree[3]['n'] === 'image' && $tree[3]['a'] === [ 'alt' => '' ]
	&& $tree[5]['n'] === 'title' && $tree[5]['s'] === '/template/page-home/hero/title' && $tree[5]['a'] === [ 'tag' => 'h2', 'style' => 'hero' ] && $tree[11]['s'] === '/templates/html-footer' && count( $tree ) === 13 );

$inner = \Nino\Markup::parse( '<div>[image /template/page-home/hero/background alt=""]</div>', $today );
check( 'an alt="" is a value of nothing, not a switch', $inner[1]['n'] === 'image' && $inner[1]['s'] === '/template/page-home/hero/background' && $inner[1]['a'] === [ 'alt' => '' ] && $inner[1]['c'] === null );

echo "\n";


// --- Containers, notes, depth -------------------------------------------------

echo "Containers and the notes\n";

$notes = [];
$tree = \Nino\Markup::parse( '', [], $notes );
check( 'an empty document is no node and no note', $tree === [] && $notes === [] );

$notes = [ 'x' ];
\Nino\Markup::parse( 'plain', [], $notes );
check( '$notes is emptied first, also by a document that has nothing to note', $notes === [] );

$tree = \Nino\Markup::parse( 'a [x] b [/y] c', [ 'x' => 'yes', 'y' => 1 ], $notes );
check( 'a value of the list that is not true is a leaf: only true is a container', $tree[1]['n'] === 'x' && $tree[1]['c'] === null && count( $notes ) === 1 && $notes[0]['code'] === 'stray-end' );

$tree = \Nino\Markup::parse( '[x]a[/x]', [ 'x' => false ], $notes );
check( 'a leaf that the list knows has no end even where one stands', $tree[0]['c'] === null && $tree[1] === [ 't' => 'a' ] && $notes === [ [ 'code' => 'stray-end', 'name' => 'x', 'offset' => 4 ] ] );

$notes = [];
$tree = \Nino\Markup::parse( '[a /] [a]x[/a]', [], $notes );
check( 'the same name is a leaf where it is written as one and a container where it has an end', $tree[0]['c'] === null && $tree[2]['c'] === [ [ 't' => 'x' ] ] && $notes === [] );

$tree = \Nino\Markup::parse( '[a]1[b]2[/a]3[/b]4', [ 'a' => true, 'b' => true ], $notes );
check( 'an end that follows the end of its parent has no call to close: it is a stray end, and the text round it one node', $tree[1] === [ 't' => '34' ] && $notes === [ [ 'code' => 'unclosed', 'name' => 'b', 'offset' => 4 ], [ 'code' => 'stray-end', 'name' => 'b', 'offset' => 13 ] ]
	&& $tree[0]['c'][1]['n'] === 'b' && $tree[0]['c'][1]['c'] === [ [ 't' => '2' ] ] );

$nest = static fn( int $levels, string $inside = 'x' ): string => str_repeat( '[box]', $levels ). $inside. str_repeat( '[/box]', $levels );

foreach( [ [ 'known', [ 'box' => true ] ], [ 'unknown', [] ] ] as [ $how, $list ] ) {

	$notes = [];
	$tree = \Nino\Markup::parse( $nest( 16 ), $list, $notes );
	check( 'depth, '. $how. ' name: sixteen containers inside each other are all opened, with no note', $notes === [] && $depthOf( $tree ) === 16 );

	$notes = [];
	$tree = \Nino\Markup::parse( $nest( 17 ), $list, $notes );
	$innermost = $tree;
	for( $level = 0; $level < 16; $level++ )
		$innermost = $innermost[0]['c'];
	check( 'depth, '. $how. ' name: the seventeenth stays a leaf at its place with its offset, one note, and what it would have held stands behind it, its end skipped with no note',
		$notes === [ [ 'code' => 'depth', 'name' => 'box', 'offset' => 80 ] ] && $depthOf( $tree ) === 16
		&& $innermost === [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => null, 'o' => 80 ], [ 't' => 'x' ] ] );

	$notes = [];
	$tree = \Nino\Markup::parse( $nest( 40 ), $list, $notes );
	check( 'depth, '. $how. ' name: forty levels end, sixteen are opened, one note for each of the other twenty-four, no stray end', $depthOf( $tree ) === 16 && count( $notes ) === 24
		&& array_unique( array_column( $notes, 'code' ) ) === [ 'depth' ] && array_column( $notes, 'offset' ) === range( 80, 195, 5 ) );
}

$notes = [];
$tree = \Nino\Markup::parse( $nest( 16, '[box /][leaf]y' ), [ 'box' => true ], $notes );
$innermost = $tree;
for( $level = 0; $level < 15; $level++ )
	$innermost = $innermost[0]['c'];
check( 'depth: a leaf inside the sixteenth container is no container and takes no level, whatever its name is', $notes === [] && $depthOf( $tree ) === 16
	&& $innermost[0]['c'] === [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => null, 'o' => 80 ], [ 'n' => 'leaf', 's' => '', 'a' => [], 'c' => null, 'o' => 87 ], [ 't' => 'y' ] ] );

$notes = [];
$tree = \Nino\Markup::parse( $nest( 16, '[box]a[/box]b[box]c[/box]d' ), [ 'box' => true ], $notes );
$innermost = $tree;
for( $level = 0; $level < 15; $level++ )
	$innermost = $innermost[0]['c'];
check( 'depth: the container that stays a leaf is a sibling of what stands behind it, each text round it is one node, and each one has its note',
	$innermost[0]['c'] === [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => null, 'o' => 80 ], [ 't' => 'ab' ], [ 'n' => 'box', 's' => '', 'a' => [], 'c' => null, 'o' => 93 ], [ 't' => 'cd' ] ]
	&& $notes === [ [ 'code' => 'depth', 'name' => 'box', 'offset' => 80 ], [ 'code' => 'depth', 'name' => 'box', 'offset' => 93 ] ] );

$notes = [];
$tree = \Nino\Markup::parse( str_repeat( '[box]', 17 ), [ 'box' => true ], $notes );
check( 'depth: a container that stayed a leaf and was never ended is noted as unclosed as well - one note for each container that has no end', array_count_values( array_column( $notes, 'code' ) ) === [ 'unclosed' => 17, 'depth' => 1 ]
	&& $depthOf( $tree ) === 16 && $notes[16] === [ 'code' => 'depth', 'name' => 'box', 'offset' => 80 ] && $notes[17] === [ 'code' => 'unclosed', 'name' => 'box', 'offset' => 80 ] );

$notes = [];
$tree = \Nino\Markup::parse( str_repeat( '[box]', 20000 ), [ 'box' => true ], $notes );
check( 'depth: 20000 containers with no end open no more than sixteen; the notes are the first MAX_NOTES - all of them depth - and the note limit, which has the offset of the first that was left out', $depthOf( $tree ) === 16 && count( $notes ) === \Nino\Markup::MAX_NOTES + 1
	&& array_count_values( array_column( $notes, 'code' ) ) === [ 'depth' => \Nino\Markup::MAX_NOTES, 'limit' => 1 ] && $notes[0] === [ 'code' => 'depth', 'name' => 'box', 'offset' => 80 ]
	&& $notes[ \Nino\Markup::MAX_NOTES - 1 ] === [ 'code' => 'depth', 'name' => 'box', 'offset' => 80 + 5 * ( \Nino\Markup::MAX_NOTES - 1 ) ] && $notes[ \Nino\Markup::MAX_NOTES ] === [ 'code' => 'limit', 'name' => '', 'offset' => 80 + 5 * \Nino\Markup::MAX_NOTES ] );

echo "\n";


// --- How many words, how many notes -----------------------------------------------------------------

echo "How many words a call has and how many notes a read keeps\n";

$switches = static fn( int $count ): string => implode( '', array_map( static fn( int $i ): string => ' w'. chr( 97 + $i % 26 ). chr( 97 + intdiv( $i, 26 ) ), range( 1, $count ) ) );
$notes = [];
$tree = \Nino\Markup::parse( '[a'. $switches( \Nino\Markup::MAX_WORDS ). ']', [], $notes );
check( 'a call of MAX_WORDS words is a call, all of them kept in their order', count( $tree ) === 1 && count( $tree[0]['a'] ) === 64 && array_key_first( $tree[0]['a'] ) === 'wba' && $notes === [] );
$source = '[a'. $switches( \Nino\Markup::MAX_WORDS + 1 ). ']';
$tree = \Nino\Markup::parse( $source, [], $notes );
check( 'a call of one word more is text, with no note - and so is what a call of that many is made of, no matter what the words are', $tree === [ [ 't' => $source ] ] && $notes === []
	&& \Nino\Markup::parse( '[a'. str_repeat( ' b="1"', 65 ). ']', [] ) === [ [ 't' => '[a'. str_repeat( ' b="1"', 65 ). ']' ] ]
	&& \Nino\Markup::parse( '[a'. str_repeat( ' b="1"', 64 ). ']', [] )[0]['n'] === 'a' && \Nino\Markup::parse( '[a'. str_repeat( ' b=5', 64 ). ']', [] )[0]['n'] === 'a'
	&& \Nino\Markup::parse( '[a'. str_repeat( ' b=5', 65 ). ']', [] ) === [ [ 't' => '[a'. str_repeat( ' b=5', 65 ). ']' ] ] );
$tree = \Nino\Markup::parse( '[a /k'. $switches( 63 ). ']', [] );
check( 'the source is one of the words: a source and 63 more is a call, a source and 64 more is text', count( $tree ) === 1 && $tree[0]['s'] === '/k' && count( \Nino\Markup::parse( '[a /k'. $switches( 64 ). ']', [] ) ) === 1
	&& \Nino\Markup::parse( '[a /k'. $switches( 64 ). ']', [] )[0]['t'] === '[a /k'. $switches( 64 ). ']' );
$many = [ 'n' => 'a', 's' => '', 'a' => array_fill_keys( array_map( static fn( int $i ): string => 'w'. chr( 97 + $i % 26 ). chr( 97 + intdiv( $i, 26 ) ), range( 1, 65 ) ), true ), 'c' => null, 'o' => 0 ];
$few = array_merge( $many, [ 'a' => array_slice( $many['a'], 0, 64 ) ] );
check( 'write() leaves out a call of more than MAX_WORDS words, the source counted, and writes one of that many', \Nino\Markup::write( [ $many ] ) === ''
	&& \Nino\Markup::write( [ array_merge( $few, [ 's' => '/k' ] ) ] ) === '' && \Nino\Markup::write( [ array_merge( $few, [ 'a' => array_slice( $few['a'], 0, 63 ), 's' => '/k' ] ) ] ) !== '' && $roundTrip( [ $few ], [] ) === true );

// The notes of one call: one
$notes = [];
\Nino\Markup::parse( '[a x=1 y=2 z=3 w=4] [a q q q] [b]', [], $notes );
check( 'a call has one note for its words, however many of them were dropped or said twice', $notes === [ [ 'code' => 'attribute', 'name' => 'a', 'offset' => 0 ], [ 'code' => 'attribute', 'name' => 'a', 'offset' => 20 ] ] );

// The notes of a read: MAX_NOTES, and one more that says the others were not kept
$ends = static fn( int $count ): string => str_repeat( '[/a]x', $count );
$notes = [];
$tree = \Nino\Markup::parse( $ends( \Nino\Markup::MAX_NOTES ), [], $notes );
check( 'MAX_NOTES notes are all kept, and none says limit', count( $notes ) === 1000 && array_unique( array_column( $notes, 'code' ) ) === [ 'stray-end' ] && $tree === [ [ 't' => str_repeat( 'x', 1000 ) ] ] );
$tree = \Nino\Markup::parse( $ends( \Nino\Markup::MAX_NOTES + 1 ), [], $notes );
check( 'one note more is not kept: the last note is limit with the offset of the one that was left out, and the tree is the whole tree', count( $notes ) === 1001 && $notes[1000] === [ 'code' => 'limit', 'name' => '', 'offset' => 5000 ]
	&& $notes[999] === [ 'code' => 'stray-end', 'name' => 'a', 'offset' => 4995 ] && $tree === [ [ 't' => str_repeat( 'x', 1001 ) ] ] );

$source = '[b]y'. str_repeat( '[/a]x', 1000 );
$tree = \Nino\Markup::parse( $source, [ 'b' => true ], $notes );
check( 'the notes that are left out are the ones found after the first MAX_NOTES - the call that is closed at the end of the document is one of them, though its offset is the least - and limit stands last, after the others, which are in the order of their offsets',
	count( $notes ) === 1001 && $notes[1000] === [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] && $notes[0] === [ 'code' => 'stray-end', 'name' => 'a', 'offset' => 4 ]
	&& $tree === [ [ 'n' => 'b', 's' => '', 'a' => [], 'c' => [ [ 't' => 'y'. str_repeat( 'x', 1000 ) ] ], 'o' => 0 ] ] );
$notes = [];
$tree = \Nino\Markup::parse( str_repeat( '[b x=1]', 1001 ), [ 'b' => true ], $notes );
$offsets = array_column( $notes, 'offset' );
$sorted = $offsets;
sort( $sorted );
check( 'the notes that are kept are in the order of the offsets, and limit comes after them: the first one left out is the attribute of the 509th call, after 16 notes of attributes and 492 pairs of attribute and depth', count( $notes ) === 1001 && array_slice( $offsets, 0, 1000 ) === array_slice( $sorted, 0, 1000 )
	&& $notes[1000] === [ 'code' => 'limit', 'name' => '', 'offset' => 508 * 7 ] && $depthOf( $tree ) === 16 );

echo "\n";


// --- What PCRE may refuse -------------------------------------------------------

echo "A pattern that gives up\n";

$limit = ini_get( 'pcre.backtrack_limit' );
$source = "text [a x=\"1\"] and [b] and \\[c]";
ini_set( 'pcre.backtrack_limit', '1' );
try {
	$notes = [ 'left over' ];
	$tree = \Nino\Markup::parse( $source, [], $notes );
	$written = \Nino\Markup::write( [ [ 't' => 'a [b] c' ], [ 't' => ' plain' ] ] );
	$plain = \Nino\Markup::write( [ [ 't' => 'plain' ] ] );
}
finally {
	ini_set( 'pcre.backtrack_limit', $limit );
}
check( 'where PCRE gives up the whole document is one text node and the one note says pattern - no throw, no empty result', $tree === [ [ 't' => $source ] ] && $notes === [ [ 'code' => 'pattern', 'name' => '', 'offset' => 0 ] ] && ninoWarnings() === [] );
check( 'write() cannot tell the marks of a text there: it writes none of that text rather than a mark, and a text with no [ in it is written as it stands', $written === '' && $plain === 'plain' && ninoWarnings() === [] );
$notes = [];
check( 'and the next read, with the limit back, is a read', \Nino\Markup::parse( $source, [], $notes )[1]['n'] === 'a' && $notes === [] );

echo "\n";


// --- The notation of what is not a mark -------------------------------------------

echo "Bytes that are not part of a mark\n";

$cases = [
	'a null byte in a value' => [ "[a v=\"x\0y\"]", [ [ 'n' => 'a', 's' => '', 'a' => [ 'v' => "x\0y" ], 'c' => null, 'o' => 0 ] ] ],
	'a null byte in text' => [ "x\0[a]\0y", [ [ 't' => "x\0" ], [ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 2 ], [ 't' => "\0y" ] ] ],
	'a null byte in a name' => [ "[a\0b]", [ [ 't' => "[a\0b]" ] ] ],
	'a null byte in an attribute name' => [ "[a b\0c]", [ [ 't' => "[a b\0c]" ] ] ],
	'a null byte between words' => [ "[a\0 b]", [ [ 't' => "[a\0 b]" ] ] ],
	'a null byte in a source' => [ "[a /x\0y]", [ [ 't' => "[a /x\0y]" ] ] ],
	'a null byte in a key' => [ "[[a\0b]]", [ [ 't' => "[[a\0b]]" ] ] ],
	'a carriage return in a value' => [ "[a v=\"x\ry\"]", [ [ 't' => "[a v=\"x\ry\"]" ] ] ],
	'a line feed in a value' => [ "[a v=\"x\ny\"]", [ [ 't' => "[a v=\"x\ny\"]" ] ] ],
	'a carriage return and a line feed between words' => [ "[a\r\nx=\"1\"\r\n]", [ [ 't' => "[a\r\nx=\"1\"\r\n]" ] ] ],
	'a carriage return before the ]' => [ "[a\r]", [ [ 't' => "[a\r]" ] ] ],
	'a backslash and a carriage return in a value' => [ "[a v=\"x\\\ry\"]", [ [ 't' => "[a v=\"x\\\ry\"]" ] ] ],
	'a backslash and a line feed in a value' => [ "[a v=\"x\\\ny\"]", [ [ 't' => "[a v=\"x\\\ny\"]" ] ] ],
	'a tab before the / of a leaf' => [ "[a\t/]", [ [ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ] ],
	'a tab between words' => [ "[a\tx=\"1\"\ty\t]", [ [ 'n' => 'a', 's' => '', 'a' => [ 'x' => '1', 'y' => true ], 'c' => null, 'o' => 0 ] ] ],
	'a vertical tab between words' => [ "[a\x0Bx]", [ [ 't' => "[a\x0Bx]" ] ] ],
	'a no-break space between words' => [ "[a\u{00A0}x]", [ [ 't' => "[a\u{00A0}x]" ] ] ],
	'bytes that are no utf-8' => [ "\xFF\xFE[a v=\"\xC3\x28\"]\xFF", [ [ 't' => "\xFF\xFE" ], [ 'n' => 'a', 's' => '', 'a' => [ 'v' => "\xC3\x28" ], 'c' => null, 'o' => 2 ], [ 't' => "\xFF" ] ] ],
	'a letter with a capital in a name' => [ '[Card]', [ [ 't' => '[Card]' ] ] ],
	'a digit first in a name' => [ '[1a]', [ [ 't' => '[1a]' ] ] ],
];

foreach( $cases as $label => [ $source, $expected ] ) {
	$notes = [];
	check( $label. ' is read as it stands, and it is no other mark than it says', \Nino\Markup::parse( $source, [], $notes ) === $expected && $notes === [] && ninoWarnings() === [] );
}

echo "\n";


// --- What a document can try --------------------------------------------------------

echo "What a value, a text or a source can try\n";

// Nothing in the notation names a file, a template, a class or a function: a document is read into data and nothing is run or opened
$attacks = [
	'a template call in a value' => '[a v="[template /templates/page-x]"]',
	'a textfill of the project in a value' => '[a v="[[/project/company/general/name]]"]',
	'an attempt to leave the attribute' => '[a v="\"><script>alert(1)</script>"]',
	'a ] in a value' => '[a v="x] [b]"]',
	'a path that goes up as a source' => '[a /../../etc/passwd]',
	'a path that goes up as a value' => '[a v="../../private/config.php"]',
];

foreach( $attacks as $label => $source ) {

	$notes = [];
	$tree = \Nino\Markup::parse( $source, [ 'template' => false, 'b' => false ], $notes );
	$names = $calls( $tree );
	check( $label. ': one call, read as data - nothing else is read from it', $notes === [] && $names === [ 'a' ] && count( $tree ) === 1 && $roundTrip( $tree, [] ) === true );
}

$tree = \Nino\Markup::parse( '[a v="[template /templates/page-x]"]', [] );
check( 'a call inside a value stays text of the value, and the value comes back whole', $tree[0]['a']['v'] === '[template /templates/page-x]' );
$tree = \Nino\Markup::parse( '[a v="\"><script>alert(1)</script>"]', [] );
check( 'a masked quote does not end the value, and what follows it is the value', $tree[0]['a']['v'] === '"><script>alert(1)</script>' );
$tree = \Nino\Markup::parse( '[a /../../etc/passwd]', [] );
check( 'a source is a string that nothing in the notation resolves', $tree[0]['s'] === '/../../etc/passwd' && $tree[0]['a'] === [] );

$tree = \Nino\Markup::parse( 'Preis \[netto] und \[template /templates/x] und [[/project/x/y/z]]', [ 'template' => false ] );
check( 'text that is masked is text: no call in it, no textfill in the masked one, and the textfill that is not masked is one', $calls( $tree ) === [] && $tree[0] === [ 't' => 'Preis [netto] und [template /templates/x] und ' ] && $tree[1] === [ 'f' => '/project/x/y/z' ] && count( $tree ) === 2 );

echo "\n";


// --- write() ------------------------------------------------------------------------

echo "write() - the one form of a document\n";

$card = [ 'n' => 'card', 's' => '~x', 'a' => [ 'fill' => true, 'size' => '100 m:50', 'label' => 'a "b" \\ c' ], 'c' => [ [ 't' => 'x' ], [ 'f' => '/a/b' ] ], 'o' => 99 ];
check( 'a container is [name source attr="value" switch]content[/name]: source first, the attributes in their order, a switch as a bare word, " and \ masked',
	\Nino\Markup::write( [ $card ] ) === '[card ~x fill size="100 m:50" label="a \"b\" \\\\ c"]x[[/a/b]][/card]' );
check( 'the offset is not written and does not matter', \Nino\Markup::write( [ array_merge( $card, [ 'o' => 0 ] ) ] ) === \Nino\Markup::write( [ array_merge( $card, [ 'o' => 12345 ] ) ] ) );

check( 'no blank is added: what stands between the nodes is the text nodes', \Nino\Markup::write( [ [ 't' => "a\n\t" ], [ 'n' => 'x', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ], [ 't' => " b\n" ] ] ) === "a\n\t[x] b\n" );
check( 'a text has no node and no mark of its own: nothing but its characters', \Nino\Markup::write( [ [ 't' => 'plain' ], [ 't' => ' text' ], [ 't' => '' ] ] ) === 'plain text' && \Nino\Markup::write( [] ) === '' );

$leaf = [ 'n' => 'a', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null, 'o' => 0 ];
check( 'a leaf is [name ...], and [name ... /] where the same name is a container in the tree - however deep the container stands',
	\Nino\Markup::write( [ $leaf ] ) === '[a x="1"]' && \Nino\Markup::write( [ $leaf, [ 'n' => 'a', 's' => '', 'a' => [], 'c' => [], 'o' => 0 ] ] ) === '[a x="1" /][a][/a]'
	&& \Nino\Markup::write( [ [ 'n' => 'b', 's' => '', 'a' => [], 'c' => [ [ 'n' => 'c', 's' => '', 'a' => [], 'c' => [ $leaf ], 'o' => 0 ], [ 'n' => 'a', 's' => '', 'a' => [], 'c' => [], 'o' => 0 ] ], 'o' => 0 ] ] ) === '[b][c][a x="1" /][/c][a][/a][/b]' );
check( 'and a leaf that was written like that reads back as one where the list knows no better', $roundTrip( \Nino\Markup::parse( '[a x="1" /][a]y[/a]', [] ), [] ) === true );
$leaves = \Nino\Markup::parse( '[a /][b /][c]', [ 'a' => true, 'b' => false ] );
check( 'a tab before the / of a leaf makes it a leaf of a name that the list calls a container as well', \Nino\Markup::parse( "[a\t/]x", [ 'a' => true ] ) === [ [ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ], [ 't' => 'x' ] ] );
check( 'a leaf of a name that the list calls a container is written [name /] where the list is given, and [name] where it is not', \Nino\Markup::write( $leaves, [ 'a' => true, 'b' => false ] ) === '[a /][b][c]'
	&& \Nino\Markup::write( $leaves ) === '[a][b][c]' && \Nino\Markup::write( $leaves, [ 'a' => 1, 'b' => 'yes' ] ) === '[a][b][c]' && $roundTrip( $leaves, [ 'a' => true, 'b' => false ] ) === true );

$bad = [
	'a name with a capital'							=> [ 'n' => 'Card', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ],
	'a name that has a blank'						=> [ 'n' => 'a b', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ],
	'a name that is no string'					=> [ 'n' => 5, 's' => '', 'a' => [], 'c' => null, 'o' => 0 ],
	'an attribute with a line feed in it'		=> [ 'n' => 'a', 's' => '', 'a' => [ 'v' => "x\ny" ], 'c' => null, 'o' => 0 ],
	'an attribute with a carriage return'		=> [ 'n' => 'a', 's' => '', 'a' => [ 'v' => "x\ry" ], 'c' => null, 'o' => 0 ],
	'an attribute name that starts with a digit'	=> [ 'n' => 'a', 's' => '', 'a' => [ '9x' => '1' ], 'c' => null, 'o' => 0 ],
	'an attribute name that is a number'		=> [ 'n' => 'a', 's' => '', 'a' => [ 5 => '1' ], 'c' => null, 'o' => 0 ],
	'an attribute that is false'					=> [ 'n' => 'a', 's' => '', 'a' => [ 'x' => false ], 'c' => null, 'o' => 0 ],
	'an attribute that is a number'				=> [ 'n' => 'a', 's' => '', 'a' => [ 'x' => 5 ], 'c' => null, 'o' => 0 ],
	'an attribute that is a list'					=> [ 'n' => 'a', 's' => '', 'a' => [ 'x' => [ '1' ] ], 'c' => null, 'o' => 0 ],
	'a source that is none'							=> [ 'n' => 'a', 's' => 'title', 'a' => [], 'c' => null, 'o' => 0 ],
	'a source of one character'					=> [ 'n' => 'a', 's' => '/', 'a' => [], 'c' => null, 'o' => 0 ],
	'a source with a blank'							=> [ 'n' => 'a', 's' => '/a b', 'a' => [], 'c' => null, 'o' => 0 ],
	'a source with a quote'							=> [ 'n' => 'a', 's' => '/a"b', 'a' => [], 'c' => null, 'o' => 0 ],
	'a source with a ]'									=> [ 'n' => 'a', 's' => '/a]b', 'a' => [], 'c' => null, 'o' => 0 ],
	'a source that is no string'					=> [ 'n' => 'a', 's' => 7, 'a' => [], 'c' => null, 'o' => 0 ],
	'no source and a first switch that is one'	=> [ 'n' => 'a', 's' => '', 'a' => [ '~x' => true ], 'c' => null, 'o' => 0 ],
	'a switch that is no word'						=> [ 'n' => 'a', 's' => '', 'a' => [ 'x y' => true ], 'c' => null, 'o' => 0 ],
	'attributes that are no list'					=> [ 'n' => 'a', 's' => '', 'a' => 'x', 'c' => null, 'o' => 0 ],
	'children that are no list'					=> [ 'n' => 'a', 's' => '', 'a' => [], 'c' => 'x', 'o' => 0 ],
	'a textfill with a blank'						=> [ 'f' => 'a b' ],
	'a textfill with a bracket'					=> [ 'f' => 'a]]b' ],
	'a textfill that is empty'						=> [ 'f' => '' ],
	'a textfill that is no string'				=> [ 'f' => 5 ],
	'a node with none of the keys'				=> [ 'x' => 1 ],
	'a node that is no array'						=> 'text',
	'a node that is null'								=> null,
	'a text that is no string'						=> [ 't' => 5 ],
];

$kept = [ 't' => 'ok' ];
foreach( $bad as $label => $node ) {
	$written = null;
	$errors = ninoErrors( static function() use ( &$written, $node, $kept ): void { $written = \Nino\Markup::write( [ $kept, $node, $kept ] ); } );
	check( 'write() leaves out '. $label. ' - the nodes round it are written, nothing is thrown or raised', $written === 'okok' && $errors === [] );
}

$written = \Nino\Markup::write( [ [ 'n' => 'box', 's' => '', 'a' => [ 'v' => "x\ny" ], 'c' => [ [ 't' => 'inside' ] ], 'o' => 0 ], [ 't' => 'after' ] ] );
check( 'a node that cannot be written takes what it holds with it', $written === 'after' );

check( 'a switch that looks like a source is written where a source stands in front of it', \Nino\Markup::write( [ [ 'n' => 'a', 's' => '/x', 'a' => [ '~y' => true, '.z' => true ], 'c' => null, 'o' => 0 ] ] ) === '[a /x ~y .z]'
	&& $roundTrip( [ [ 'n' => 'a', 's' => '/x', 'a' => [ '~y' => true, '.z' => true ], 'c' => null, 'o' => 0 ] ], [] ) === true );

// A text: a \ in front of each place that would be read as a mark
$text = '[a] [[k]] [/b] \\[a] [ x] [a x=5] [[a b]] [a /]';
$written = \Nino\Markup::write( [ [ 't' => $text ] ] );
$fill = static fn( string $key ): array => [ [ 't' => 'x' ], [ 'f' => $key ], [ 't' => 'y' ] ];
check( 'a textfill with one textfill of its own in its key is written as it stands, and one with two, or with nothing but the inner one, is left out',
	\Nino\Markup::write( $fill( '/a[[/b]]/c' ) ) === 'x[[/a[[/b]]/c]]y' && $roundTrip( $fill( '/a[[/b]]/c' ), [] ) === true
	&& \Nino\Markup::write( $fill( 'a[[b[[c]]]]' ) ) === 'xy' && \Nino\Markup::write( $fill( '[[b]]' ) ) === 'xy' && \Nino\Markup::write( $fill( 'a[[b c]]' ) ) === 'xy' );
$source = static fn( string $source ): array => [ [ 'n' => 'a', 's' => $source, 'a' => [ 'v' => '1' ], 'c' => null, 'o' => 0 ] ];
check( 'a source with textfills in it is written as it stands, and the call reads back whole - the ]] of the textfill is no end of the mark',
	\Nino\Markup::write( $source( '/t/p.[[/nino/http/response/locale]]' ) ) === '[a /t/p.[[/nino/http/response/locale]] v="1"]' && $roundTrip( $source( '/t/p.[[/nino/http/response/locale]]' ), [] ) === true
	&& $roundTrip( $source( '~[[k]][[l]]' ), [] ) === true && \Nino\Markup::write( $source( '/t/[[k l]]' ) ) === '' && \Nino\Markup::write( $source( '/t/[[k' ) ) === '' );
check( 'a text is written with a \ in front of each place that would be read as a mark, and in front of no other', $written === '\\[a] \\[[k]] \\[/b] \\\\[a] [ x] \\[a x=5] [\\[a b]] \\[a /]' );
check( 'and that reads back as the text, each time', \Nino\Markup::parse( $written, [] ) === [ [ 't' => $text ] ] );

$texts = [ '[[a b]]', '[[k]]', '[a x="[b]"]', '\\', '\\\\', '\\[', 'a\\[ b', "[a\n]", '[a v="x" w="y]', '[/a]', '[[[k]]]', '[ [a] ]' ];
$failed = array_filter( $texts, static fn( string $text ): bool => \Nino\Markup::parse( \Nino\Markup::write( [ [ 't' => $text ] ] ), [] ) !== [ [ 't' => $text ] ] );
check( 'every text of a list of the ones that look like marks, or like the ground between them, comes back as itself: '. implode( ' ', array_map( 'json_encode', $texts ) ), $failed === [] );

// The one text that cannot be said
$written = \Nino\Markup::write( [ [ 't' => 'x\\' ], [ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ], [ 't' => 'y' ] ] );
check( 'a \ that ends a text in front of a mark would mask the mark, and cannot be written: it is left out, the mark stays a mark', $written === 'x[a]y' );
$written = \Nino\Markup::write( [ [ 't' => 'x\\' ], [ 'f' => '/a/b' ] ] );
check( 'two backslashes, or more, at the end of the text go the same way: the mark stays a mark, in front of a call, a textfill and an end',
	\Nino\Markup::write( [ [ 't' => 'x\\\\' ], [ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ], [ 't' => 'y' ] ] ) === 'x[a]y'
	&& \Nino\Markup::write( [ [ 't' => 'x\\\\\\' ], [ 'f' => '/a/b' ] ] ) === 'x[[/a/b]]'
	&& \Nino\Markup::write( [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => [ [ 't' => 'x\\\\' ] ], 'o' => 0 ], [ 't' => 'y' ] ] ) === '[box]x[/box]y'
	&& \Nino\Markup::parse( \Nino\Markup::write( [ [ 't' => 'x\\\\' ], [ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ] ] ), [] )[1]['n'] === 'a' );
check( '...in front of a textfill as well', $written === 'x[[/a/b]]' );
$written = \Nino\Markup::write( [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => [ [ 't' => 'x\\' ] ], 'o' => 0 ], [ 't' => 'y' ] ] );
check( '...and in front of the end of a call that something follows', $written === '[box]x[/box]y' );
$written = \Nino\Markup::write( [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => [ [ 'n' => 'in', 's' => '', 'a' => [], 'c' => [ [ 't' => 'x\\' ] ], 'o' => 0 ] ], 'o' => 0 ] ] );
check( 'but where the \ ends the document, inside calls that nothing follows, the ends are not written: read again they are closed there, as they were', $written === '[box][in]x\\'
	&& \Nino\Markup::parse( $written, [ 'box' => true, 'in' => true ] ) === [ [ 'n' => 'box', 's' => '', 'a' => [], 'c' => [ [ 'n' => 'in', 's' => '', 'a' => [], 'c' => [ [ 't' => 'x\\' ] ], 'o' => 5 ] ], 'o' => 0 ] ] );
$tree = \Nino\Markup::parse( '[a]b[c]d\\', [ 'a' => true, 'c' => true ] );
check( 'that is what a document does that ends in a \ inside calls it never closed: it is read, written and read as the same tree', $roundTrip( $tree, [ 'a' => true, 'c' => true ] ) === true && \Nino\Markup::write( $tree, [ 'a' => true, 'c' => true ] ) === '[a]b[c]d\\' );
check( 'a text that ends in a \ at the end of the document is written whole', \Nino\Markup::write( [ [ 't' => 'x\\' ] ] ) === 'x\\' && \Nino\Markup::parse( 'x\\', [] ) === [ [ 't' => 'x\\' ] ] );

// A [ in front of a mark that has nothing but its name makes the [[name]] of a textfill
$tree = [ [ 't' => 'x[' ], [ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 2 ], [ 't' => ']y' ] ];
$written = \Nino\Markup::write( $tree );
check( 'a [ in front of a call that has nothing but its name, and a ] behind it, would be a textfill: the call gets a blank before its ]', $written === 'x[[a ]]y' && $strip( \Nino\Markup::parse( $written, [] ) ) === $strip( $tree ) );
$tree = [ [ 't' => '[' ], [ 'n' => 'box', 's' => '', 'a' => [], 'c' => [], 'o' => 1 ], [ 't' => ']' ] ];
$written = \Nino\Markup::write( $tree );
check( 'a container behind a [ likewise, and its end behind another one', $written === '[[box ][/box]]' && $strip( \Nino\Markup::parse( $written, [] ) ) === $strip( $tree )
	&& \Nino\Markup::write( [ [ 't' => '[' ], [ 'n' => 'box', 's' => '', 'a' => [ 'x' => '1' ], 'c' => [ [ 't' => '[' ] ], 'o' => 1 ], [ 't' => ']' ] ] ) === '[[box x="1"][[/box ]]' );
check( 'a document of such calls reads, writes and reads as the same tree', $roundTrip( \Nino\Markup::parse( '[[a ]] [[b ] [/c ]]', [ 'b' => true ] ), [ 'b' => true ] ) === true );

$stringKeys = [ 'n' => 'a', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null, 'o' => 0 ];
check( 'the nodes of a list with holes or keys are written in the order they stand, and a list with the wrong keys is no problem', \Nino\Markup::write( [ 5 => [ 't' => 'a' ], 'k' => $stringKeys, 2 => [ 't' => 'b' ] ] ) === 'a[a x="1"]b' );

// Nothing that can be handed over throws
mt_srand( 4242 );
$pick = static fn( array $values ): mixed => $values[ mt_rand( 0, count( $values ) - 1 ) ];
$word = static fn(): string => $pick( [ '', 'a', 'a b', 'card', 'x"y', "x\ny", "\0", '[a]', '\\', '/a/b', '~x', 'k', 'A', '-', 'a-b', '/' ] );
$junk = static function( int $depth ) use ( &$junk, $pick, $word ): mixed {

	$kind = $pick( $depth > 3 ? [ 'null', 'int', 'string', 'bool', 'text', 'fill', 'call', 'call' ] : [ 'null', 'int', 'string', 'bool', 'text', 'fill', 'call', 'call', 'call', 'list' ] );

	if( $kind === 'null' )
		return null;

	if( $kind === 'int' )
		return mt_rand( -5, 5 );

	if( $kind === 'string' )
		return $word();

	if( $kind === 'bool' )
		return mt_rand( 0, 1 ) === 1;

	if( $kind === 'text' )
		return [ 't' => mt_rand( 0, 4 ) === 0 ? mt_rand( 0, 9 ) : $word(). $word() ];

	if( $kind === 'fill' )
		return [ 'f' => mt_rand( 0, 4 ) === 0 ? null : $word() ];

	if( $kind === 'list' )
		return array_map( static fn() => $junk( $depth + 1 ), range( 1, mt_rand( 0, 3 ) ) );

	$attributes = [];
	for( $i = mt_rand( 0, 3 ); $i > 0; $i-- )
		$attributes[ mt_rand( 0, 5 ) === 0 ? mt_rand( 0, 9 ) : $word() ] = mt_rand( 0, 2 ) === 0 ? true : ( mt_rand( 0, 5 ) === 0 ? $junk( 9 ) : $word() );

	$children = null;
	if( mt_rand( 0, 2 ) !== 0 )
		$children = mt_rand( 0, 8 ) === 0 ? $junk( 9 ) : array_map( static fn() => $junk( $depth + 1 ), range( 1, mt_rand( 0, 3 ) ) );

	return [ 'n' => mt_rand( 0, 5 ) === 0 ? mt_rand( 0, 3 ) : $word(), 's' => mt_rand( 0, 3 ) === 0 ? $junk( 9 ) : $word(),
		'a' => mt_rand( 0, 6 ) === 0 ? $junk( 9 ) : $attributes, 'c' => $children, 'o' => 0 ];
};

$thrown = 0;
$raised = 0;
for( $i = 0; $i < 500; $i++ ) {
	$tree = array_map( static fn() => $junk( 0 ), range( 1, mt_rand( 0, 6 ) ) );
	try {
		$raised += count( ninoErrors( static function() use ( $tree ): void { \Nino\Markup::write( $tree ); } ) );
	}
	catch( \Throwable $exception ) {
		$thrown++;
		echo '        '. $exception->getMessage(). "\n";
	}
}
check( 'write() throws nothing and raises nothing for 500 trees made of everything a tree can hold', $thrown === 0 && $raised === 0 );

// A tree as deep as memory allows is no problem of the stack
$deep = [ 't' => 'x' ];
for( $i = 0; $i < 20000; $i++ )
	$deep = [ 'n' => 'a', 's' => '', 'a' => [], 'c' => [ $deep ], 'o' => 0 ];
$written = \Nino\Markup::write( [ $deep ] );
check( 'write() of a tree that stands 20000 containers deep ends, and writes them all', strlen( $written ) === 20000 * 3 + 1 + 20000 * 4 && substr( $written, 0, 6 ) === '[a][a]' );
unset( $deep );

echo "\n";


// --- The forms of tree that are not promised ------------------------------------------------

echo "Two forms that write() cannot give back - and what comes out of them\n";

$forms = [
	'a text that ends in an unfinished textfill, finished by the text after a skipped end'	=> [ '[[a[[b]][/z]]]', [], '[[a[[b]]]]' ],
	'a text that ends in an unfinished source'																						=> [ '[x /a[[k]][/y]]', [], '[x /a[[k]]]' ],
	'a text that ends in two unfinished textfills'																				=> [ '[[[[k]][/z]x]]', [], '[[[[k]]x]]' ],
	'a text that ends in an unfinished value, finished by a quote after a skipped end'		=> [ '[a v=1][a v="[/a]"[/a]]', [ 'a' => true ], '[a][a v="[/a]"]' ],
	'a \ that ends the document inside a call that was not closed, of a name nobody knows'	=> [ '[/a][a]x\\', [], '[a]x\\' ],
];

foreach( $forms as $label => [ $source, $list, $expected ] ) {

	$notes = [];
	$tree = \Nino\Markup::parse( $source, $list, $notes );
	$out = null;
	$errors = ninoErrors( static function() use ( &$out, $tree, $list ): void { $out = \Nino\Markup::write( $tree, $list ); } );
	$back = [];
	$again = \Nino\Markup::parse( (string) $out, $list, $back );

	check( $label. ': it is written as '. json_encode( $expected ). ', which is not the tree - and nothing is thrown or raised', $errors === [] && $out === $expected && $strip( $again ) !== $strip( $tree )
		&& $roundTrip( $tree, $list ) === null );
}

$tree = \Nino\Markup::parse( '[[a[[b]]]]', [] );
check( 'the same halves with no skipped end between are one textfill to begin with, and come back', $tree === [ [ 'f' => 'a[[b]]' ] ] && $roundTrip( $tree, [] ) === true );

// The length: twice the document, and the ends of what was never closed
$notes = [];
$tree = \Nino\Markup::parse( '[a]', [ 'a' => true ], $notes );
$written = \Nino\Markup::write( $tree, [ 'a' => true ] );
check( 'a container that is never closed is written longer than twice its document, by its end and no more: [a] is [a][/a]', $written === '[a][/a]' && strlen( $written ) > 2 * strlen( '[a]' )
	&& strlen( $written ) <= 2 * strlen( '[a]' ) + strlen( $notes[0]['name'] ) + 3 );

echo "\n";


// --- How many marks ------------------------------------------------------------------------------

echo "How many marks - and what they cost\n";

$notes = [];
$exactly = \Nino\Markup::parse( str_repeat( '[a]', \Nino\Markup::MAX_MARKS ), [], $notes );
check( 'a document with MAX_MARKS brackets is read', count( $exactly ) === \Nino\Markup::MAX_MARKS && $notes === [] );
$over = \Nino\Markup::parse( str_repeat( '[a]', \Nino\Markup::MAX_MARKS + 1 ), [], $notes );
check( 'a document with one more is one text node and the note limit', $over === [ [ 't' => str_repeat( '[a]', \Nino\Markup::MAX_MARKS + 1 ) ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] );

// The words of all calls together: 3125 calls of 64 words are MAX_WORDS_READ words. A word that is dropped is none
$words = [];

for( $i = 0; $i < \Nino\Markup::MAX_WORDS; $i++ )
	$words[] = chr( 97 + intdiv( $i, 26 ) ). chr( 97 + $i % 26 );

$full		= str_repeat( '[x '. implode( ' ', $words ). ']', intdiv( \Nino\Markup::MAX_WORDS_READ, \Nino\Markup::MAX_WORDS ) );
$read		= \Nino\Markup::parse( $full, [], $notes );
check( 'a document whose calls have MAX_WORDS_READ words together is read', count( $read ) === 3125 && count( $read[3124]['a'] ) === 64 && $notes === [] );
$read		= \Nino\Markup::parse( $full. '[x /k]', [], $notes );
check( 'with one word more - a source is a word - it is one text node and the one note limit, whatever was noted before', $read === [ [ 't' => $full. '[x /k]' ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] );
$read		= \Nino\Markup::parse( '[/z][x a=1]'. $full. '[x y]', [], $notes );
check( '...the notes of what was read so far are gone with it', $read === [ [ 't' => '[/z][x a=1]'. $full. '[x y]' ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] );
$read		= \Nino\Markup::parse( $full. '[x a=1 b=2][x][/y]', [], $notes );
check( 'a word that is dropped does not count: the document is read', count( $read ) === 3127 && $read[3125]['a'] === [] && array_column( $notes, 'code' ) === [ 'attribute', 'stray-end' ] );

gc_collect_cycles();
memory_reset_peak_usage();
$before	= memory_get_usage();
$read		= \Nino\Markup::parse( str_repeat( '[x '. implode( ' ', $words ). ']', 50000 ), [], $notes );
$peak		= ( memory_get_peak_usage() - $before ) / 1048576;
echo sprintf( "      50000 calls of 64 words (9.3 MB): peak %.1f MB\n", $peak );
check( '50000 calls of 64 words each, under the limit of marks and of words in a call, are not read: the peak stays under 64 MB', count( $read ) === 1 && $notes[0]['code'] === 'limit' && $peak < 64 );
unset( $read, $full );

check( 'a bracket is a bracket: [[k]] counts two, so does a pair that makes no mark, and so does an escaped mark',
	count( \Nino\Markup::parse( str_repeat( '[[k]]', 25000 ), [] ) ) === 25000 && \Nino\Markup::parse( str_repeat( '[[k]]', 25001 ), [], $notes ) === [ [ 't' => str_repeat( '[[k]]', 25001 ) ] ]
	&& $notes[0]['code'] === 'limit' && \Nino\Markup::parse( str_repeat( '[ ', 50001 ), [], $notes ) === [ [ 't' => str_repeat( '[ ', 50001 ) ] ] && $notes[0]['code'] === 'limit'
	&& \Nino\Markup::parse( str_repeat( '\\[a]', 50001 ), [], $notes ) === [ [ 't' => str_repeat( '\\[a]', 50001 ) ] ] && $notes[0]['code'] === 'limit' );
check( 'what was read as a limit is a text, and it is written as a text: its marks masked', \Nino\Markup::write( $over ) === str_repeat( '\\[a]', 50001 ) );

// What the marks cost, in a process that may have 128 MB: the worst documents that stay under every limit - the number of
// marks, of words in a mark, of notes - each read and written with the peak of memory counted from nothing. 349525 marks,
// 199984 notes and a mark of 200000 words were fatal errors
$code = 'require '. var_export( dirname( __DIR__ ). '/_nino/Nino.php', true ). ';'
	. '$word = static fn( int $i ): string => " w". chr( 97 + $i % 26 ). chr( 97 + intdiv( $i, 26 ) );'
	. '$words = implode( "", array_map( $word, range( 0, 63 ) ) );'
	. '$documents = ['
	. '"349525 calls" => [ str_repeat( "[a]", 349525 ), [] ],'
	. '"50000 calls" => [ str_repeat( "[a]", 50000 ), [] ],'
	. '"50000 containers with no end" => [ str_repeat( "[box]", 50000 ), [ "box" => true ] ],'
	. '"50000 containers with two unquoted values" => [ str_repeat( "[box x=1 y=1]", 50000 ), [ "box" => true ] ],'
	. '"50000 ends with no start" => [ str_repeat( "[/a]", 50000 ), [] ],'
	. '"25000 textfills" => [ str_repeat( "[[/a/b]]", 25000 ), [] ],'
	. '"25000 containers inside each other, closed" => [ str_repeat( "[a]", 25000 ). str_repeat( "[/a]", 25000 ), [] ],'
	. '"a call of 200000 switches" => [ "[a". str_repeat( " b", 200000 ). "]", [] ],'
	. '"a call of 200000 unquoted values" => [ "[a". str_repeat( " b=1", 200000 ). "]", [] ],'
	. '"50000 calls of ten switches that are one" => [ str_repeat( "[a b b b b b b b b b b]", 50000 ), [] ],'
	. '"50000 containers of seven switches" => [ str_repeat( "[box a b c d e f g]", 50000 ), [ "box" => true ] ],'
	. '"50000 containers of four more words" => [ str_repeat( "[box ab cd ef gh]", 50000 ), [ "box" => true ] ],'
	. '"50000 calls of five words" => [ str_repeat( "[a ab cd ef gh ij]", 50000 ), [] ],'
	. '"5000 calls of 64 words" => [ str_repeat( "[a". $words. "]", 5000 ), [] ],'
	. '"5000 containers of 64 words" => [ str_repeat( "[box". $words. "]", 5000 ), [ "box" => true ] ],'
	. '"50 values of 20000 characters" => [ str_repeat( "[a v=\"". str_repeat( "y", 20000 ). "\"]", 50 ), [] ],'
	. '];'
	. 'foreach( $documents as $label => [ $source, $list ] ) {'
	. 'gc_collect_cycles(); memory_reset_peak_usage(); $notes = [];'
	. '$started = hrtime( true ); $tree = \Nino\Markup::parse( $source, $list, $notes ); $ms = ( hrtime( true ) - $started ) / 1e6;'
	. '$written = \Nino\Markup::write( $tree, $list );'
	. 'printf( "%s|%d|%d|%.1f\n", $label, strlen( $source ), round( memory_get_peak_usage() / 1048576 ), $ms ); unset( $tree, $notes, $written ); }';
$process = proc_open( [ PHP_BINARY, '-d', 'memory_limit=128M', '-r', $code ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
$output = stream_get_contents( $pipes[1] );
$error = stream_get_contents( $pipes[2] );
fclose( $pipes[1] );
fclose( $pipes[2] );
$status = proc_close( $process );
$lines = array_map( static fn( string $line ): array => explode( '|', $line ), array_values( array_filter( explode( "\n", $output ) ) ) );
foreach( $lines as [ $label, $bytes, $peak, $ms ] )
	printf( "  %-46s %8d bytes  peak %3d MB  parse %7.1f ms\n", $label, $bytes, $peak, $ms );
check( 'under a memory limit of 128 MB the 16 worst documents below the limits of marks, words and notes are read and written, each with a peak of less than 100 MB counted for itself',
	$status === 0 && $error === '' && count( $lines ) === 16 && array_filter( $lines, static fn( array $line ): bool => (int) $line[2] >= 100 ) === [] );

// That the reader grows with the marks and not faster is shown by what its patterns may take, and no clock: the marks are found by patterns that give nothing back, so
// that PCRE needs a number of steps for a mark that does not depend on how many marks there are. A process with the match limit of PCRE set to 300 steps for each match -
// the limit counts steps and only counts them where the pattern is not compiled to machine code, which is why it is off - reads twelve and a half thousand marks, four
// times that and the ten thousands between them without PCRE giving up once (the note "pattern" is PCRE giving up); a pattern that backtracks takes more than that and
// the note is there. The tree has a node for each mark, so that what is read is counted by the marks as well
$growth = '$out = []; foreach( [ "[a]" => [], "[box]" => [ "box" => true ], "[/a]" => [], "[a x=1 y=\"2\" z]" => [], "[[/a/b]]" => [], "[a /]" => [] ] as $mark => $list ) {'
	. 'foreach( [ 12500, 25000, 50000 ] as $count ) { $notes = []; $tree = \Nino\Markup::parse( str_repeat( $mark, $count ), $list, $notes );'
	. '$out[] = [ $mark, $count, count( $tree ), array_values( array_unique( array_column( $notes, "code" ) ) ) ]; unset( $tree, $notes ); } } echo json_encode( $out );';
$code = '$_SERVER["REMOTE_ADDR"] = "127.0.0.1"; require '. var_export( __DIR__. '/harness.php', true ). '; '. $growth;
$process = proc_open( [ PHP_BINARY, '-d', 'pcre.jit=0', '-d', 'pcre.backtrack_limit=300', '-d', 'memory_limit=256M', '-r', $code ], [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes );
$grown = json_decode( (string) stream_get_contents( $pipes[1] ), true );
stream_get_contents( $pipes[2] );
fclose( $pipes[1] );
fclose( $pipes[2] );
proc_close( $process );
$gave = is_array( $grown ) === true ? array_filter( $grown, static fn( array $row ): bool => in_array( 'pattern', $row[3], true ) === true ) : [ 'no answer' ];
$nodes = is_array( $grown ) === true ? array_filter( $grown, static fn( array $row ): bool => $row[0] === '[a]' || $row[0] === '[a /]' ) : [];
foreach( $nodes as $row )
	printf( "  %-6s %6d marks: %6d nodes, notes %s\n", $row[0], $row[1], $row[2], json_encode( $row[3] ) );
check( 'the reader grows with the number of marks and not faster: with a match limit of 300 steps and no machine code twelve and a half thousand, twenty-five thousand and fifty thousand marks of six kinds are read, and PCRE gives up on none',
	is_array( $grown ) === true && count( $grown ) === 18 && $gave === [] );
check( '...and what is read is counted by the marks: a node for each of them, four times the marks four times the nodes', is_array( $grown ) === true && array_reduce( $nodes, static fn( bool $kept, array $row ): bool => $kept === true && $row[2] === $row[1], true ) === true );

echo "\n";


// --- Random documents --------------------------------------------------------------------

echo "Random documents - 2000 of them\n";

$tokens = [ '[a]', '[b]', '[c]', '[/a]', '[/b]', '[/c]', '[/d]', '[a /]', '[b /]', '[d]', '[c x="1"]', '[c x="a ] b"]', '[c x="a \\" b"]', '[c x="a \\\\"]', '[c x="q', '"]', '[d ~t]', '[d .f y z]', '[d /a/b/c/d x]',
	'[e x=5]', '[e x=\'5\']', '[e x="1" x="2"]', '[e y y]', '[[k]]', '[[/a/b/c/d]]', '[[.id]]', '[[', ']]', '[', ']', '\\', '\\[', '\\\\', '\\[a]', '\\[[k]]', '\\[/a]', '"', "'", '=', '/', ' /', ' ',
	"\n", "\r", "\r\n", "\t", "\0", 'x', 'text ', 'ä', "\xFF", '[a x', '[b ', '[[a b]]', '[ ]', '[a b="c"d]', '[a-b]', '[A]', '<!-- ', ' -->', '<script>', '[/a ]', '[b ]', '[x=1]',
	'="', "='", '[x v="', '[x v=', '[[a', '[[a[[b]]', '[b /x[[k]]', '[b /x[[', '[b /~k:l]', '[/z]', '[/a]"', '"[/a]', ']]]', '[[[[k]]' ];

$lists = [ [], [ 'a' => true, 'b' => false ], [ 'a' => true, 'b' => true, 'c' => true, 'd' => false ], [ 'a' => false ] ];

mt_srand( 105 );

$thrown = [];
$tooLong = [];
$notWell = [];
$notBack = [];
$notDeterministic = [];
$unpromised = 0;
$longest = 0;
$counted = [ 'notes' => 0, 'calls' => 0, 'fills' => 0, 'deepest' => 0 ];
$started = hrtime( true );

for( $i = 0; $i < 2000; $i++ ) {

	$source = '';
	for( $t = mt_rand( 1, 60 ); $t > 0; $t-- )
		$source .= $tokens[ mt_rand( 0, count( $tokens ) - 1 ) ];

	$list = $lists[ $i % count( $lists ) ];
	$notes = [];

	try {
		$tree = \Nino\Markup::parse( $source, $list, $notes );
		$written = \Nino\Markup::write( $tree, $list );
		$notesAgain = [];
		$same = \Nino\Markup::parse( $source, $list, $notesAgain );
	}
	catch( \Throwable $exception ) {
		$thrown[] = $i. ': '. $exception->getMessage();
		continue;
	}

	$counted['notes'] += count( $notes );
	$counted['calls'] += count( $calls( $tree ) );
	$counted['deepest'] = max( $counted['deepest'], $depthOf( $tree ) );
	$longest = max( $longest, strlen( $source ) );

	// Twice the document, and the ends that the calls it never closed are written with - the one thing a document can leave out
	$ends = 0;
	foreach( $notes as $note )
		if( $note['code'] === 'unclosed' )
			$ends += strlen( $note['name'] ) + 3;

	if( strlen( $written ) > 2 * strlen( $source ) + $ends )
		$tooLong[] = $i;

	if( $wellFormed( $tree, $source ) === false || $wellNoted( $notes, $source ) === false )
		$notWell[] = $i;

	$verdict = $roundTrip( $tree, $list );

	if( $verdict === false )
		$notBack[] = $i;
	else if( $verdict === null )
		$unpromised++;

	if( $same !== $tree || $notesAgain !== $notes )
		$notDeterministic[] = $i;

}

printf( "  %d documents, the longest %d bytes, %d calls and %d notes between them, %d containers deep at most, %d beyond the promise of the round trip, %.0f ms\n", 2000, $longest, $counted['calls'], $counted['notes'], $counted['deepest'], $unpromised, ( hrtime( true ) - $started ) / 1e6 );

check( 'no random document throws, and none raises a warning or a notice', $thrown === [] && ninoWarnings() === [] );
check( 'what is written for a random document is at most twice as long as the document, and the ends of the calls it never closed', $tooLong === [] );
check( 'every tree and every list of notes of a random document is well formed', $notWell === [] );
check( 'every random document is read, written and read as the same tree, and what is written is written the same again, with the list of containers it was read with - but for the two forms that are not promised: '. ( $notBack === [] ? 'all' : 'first failure: document no. '. $notBack[0] ), $notBack === [] );
check( 'a document read twice is read as the same tree and the same notes', $notDeterministic === [] );
check( 'the random documents are made of what they should be: calls, notes of every kind and containers that stand inside each other', $counted['calls'] > 10000 && $counted['notes'] > 2000 && $counted['deepest'] >= 4 );

$codes = [];
mt_srand( 105 );
for( $i = 0; $i < 2000; $i++ ) {
	$source = '';
	for( $t = mt_rand( 1, 60 ); $t > 0; $t-- )
		$source .= $tokens[ mt_rand( 0, count( $tokens ) - 1 ) ];
	$notes = [];
	\Nino\Markup::parse( $source, $lists[ $i % count( $lists ) ], $notes );
	foreach( $notes as $note )
		$codes[ $note['code'] ] = true;
}
check( 'the notes of the random documents are of the three kinds a document of this size can cause - depth has its cases above', array_keys( $codes ) !== [] && array_diff( [ 'unclosed', 'stray-end', 'attribute' ], array_keys( $codes ) ) === [] );

echo "\n";


// --- Too big, too deep, too many -----------------------------------------------------------

echo "Too big, too deep, too many - and what each costs\n";

$bigCases = [
	'a value of 1 MB' => [ '[a v="'. str_repeat( 'y', 1 << 20 ). '"]', [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && strlen( $tree[0]['a']['v'] ) === 1 << 20 && $notes === [] ],
	'a value of 1 MB of masked backslashes' => [ '[a v="'. str_repeat( '\\\\', 1 << 19 ). '"]', [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && strlen( $tree[0]['a']['v'] ) === 1 << 19 && $notes === [] ],
	'a value of 1 MB that is never closed' => [ '[a v="'. str_repeat( 'y', 1 << 20 ), [], static fn( array $tree, array $notes ): bool => $tree === [ [ 't' => '[a v="'. str_repeat( 'y', 1 << 20 ) ] ] && $notes === [] ],
	'1 MB of text with no mark' => [ str_repeat( 'abc ', 1 << 18 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && strlen( $tree[0]['t'] ) === 1 << 20 && $notes === [] ],
	'1 MB of text full of [ that make no mark' => [ str_repeat( '[ab ', 1 << 18 ), [], static fn( array $tree, array $notes, string $source ): bool => $tree === [ [ 't' => $source ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] ],
	'1 MB of text with 40000 [ that make no mark' => [ str_repeat( '[ab ', 40000 ). str_repeat( 'abcd', 224000 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && strlen( $tree[0]['t'] ) > 1 << 20 && $notes === [] ],
	'1 MB of [[' => [ str_repeat( '[[', 1 << 19 ), [], static fn( array $tree, array $notes, string $source ): bool => $tree === [ [ 't' => $source ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] ],
	'40000 [[' => [ str_repeat( '[[', 20000 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && $notes === [] ],
	'1 MB of \\' => [ str_repeat( '\\', 1 << 20 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && $notes === [] ],
	'1 MB of masked marks' => [ str_repeat( '\\[a]', 1 << 18 ), [], static fn( array $tree, array $notes, string $source ): bool => $tree === [ [ 't' => $source ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] ],
	'50000 masked marks' => [ str_repeat( '\\[a]', 50000 ), [], static fn( array $tree, array $notes ): bool => $tree === [ [ 't' => str_repeat( '[a]', 50000 ) ] ] && $notes === [] ],
	'20000 containers with no end' => [ str_repeat( '[box]', 20000 ), [ 'box' => true ], static fn( array $tree, array $notes ): bool => count( $notes ) === \Nino\Markup::MAX_NOTES + 1 && end( $notes )['code'] === 'limit' && count( $tree ) === 1 ],
	'20000 names that have no end' => [ str_repeat( '[box]', 20000 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 20000 && $notes === [] ],
	'20000 containers and one end' => [ str_repeat( '[box]', 20000 ). '[/box]', [ 'box' => true ], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && count( $notes ) === \Nino\Markup::MAX_NOTES + 1 && end( $notes )['code'] === 'limit' ],
	'20000 ends and no start' => [ str_repeat( '[/box]', 20000 ), [], static fn( array $tree, array $notes ): bool => $tree === [] && count( $notes ) === \Nino\Markup::MAX_NOTES + 1 && end( $notes ) === [ 'code' => 'limit', 'name' => '', 'offset' => 6 * \Nino\Markup::MAX_NOTES ] ],
	'17 levels' => [ str_repeat( '[box]', 17 ). str_repeat( '[/box]', 17 ), [ 'box' => true ], static fn( array $tree, array $notes ): bool => count( $notes ) === 1 && $notes[0]['code'] === 'depth' ],
	'40 levels' => [ str_repeat( '[box]', 40 ). str_repeat( '[/box]', 40 ), [ 'box' => true ], static fn( array $tree, array $notes ): bool => count( $notes ) === 24 ],
	'1 MB of unfinished nested textfills' => [ str_repeat( '[[a[[b', 1 << 17 ), [], static fn( array $tree, array $notes, string $source ): bool => $tree === [ [ 't' => $source ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] ],
	'48000 brackets of unfinished nested textfills' => [ str_repeat( '[[a[[b', 12000 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && $notes === [] ],
	'1 MB of sources that never end' => [ str_repeat( '[a /x[[y]]', 100000 ), [], static fn( array $tree, array $notes, string $source ): bool => $tree === [ [ 't' => $source ] ] && $notes === [ [ 'code' => 'limit', 'name' => '', 'offset' => 0 ] ] ],
	'16000 sources that never end' => [ str_repeat( '[a /x[[y]]', 16000 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 32000 && $notes === [] ],
	'a name of 100000 characters' => [ '['. str_repeat( 'a', 100000 ). ']', [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && strlen( $tree[0]['n'] ) === 100000 && $notes === [] ],
	'a mark with 100000 switches' => [ '[a'. str_repeat( ' b', 100000 ). ']', [], static fn( array $tree, array $notes, string $source ): bool => $tree === [ [ 't' => $source ] ] && $notes === [] ],
	'a mark with 64 switches' => [ '[a'. str_repeat( ' b', 64 ). ']', [], static fn( array $tree, array $notes ): bool => count( $tree ) === 1 && $tree[0]['a'] === [ 'b' => true ] && count( $notes ) === 1 ],
	'a mark with 100000 words that never ends' => [ '[a'. str_repeat( ' b', 100000 ), [], static fn( array $tree, array $notes ): bool => $tree === [ [ 't' => '[a'. str_repeat( ' b', 100000 ) ] ] && $notes === [] ],
	'a document of 40000 calls with attributes' => [ str_repeat( '[a x="1" ~y z /]', 40000 ), [], static fn( array $tree, array $notes ): bool => count( $tree ) === 40000 && $notes === [] ],
	'a null byte in a value, a name and a text' => [ "[a v=\"x\0\"]\0[b\0]", [], static fn( array $tree, array $notes ): bool => $tree[0]['a']['v'] === "x\0" && $tree[1] === [ 't' => "\0[b\0]" ] && $notes === [] ],
];

foreach( $bigCases as $label => [ $source, $list, $verify ] ) {

	$notes = [];
	$errors = ninoErrors( static function() use ( $source, $list, &$notes, &$tree, &$ms, $timed ): void {
		[ $tree, $ms ] = $timed( static function() use ( $source, $list, &$notes ): array { return \Nino\Markup::parse( $source, $list, $notes ); } );
	} );

	[ $written, $writeMs ] = $timed( static fn(): string => \Nino\Markup::write( $tree ) );
	$back = \Nino\Markup::parse( $written, [] );

	printf( "  %-52s %9d bytes  parse %7.1f ms  write %6.1f ms\n", $label, strlen( $source ), $ms, $writeMs );
	check( $label. ': it ends, nothing is thrown or raised, the tree and the notes are what they should be, and the tree writes and reads back', $errors === [] && $verify( $tree, $notes, $source ) === true && $wellNoted( $notes, $source ) === true
		&& ( ( $notes[0]['code'] ?? '' ) === 'limit' || $strip( $back ) === $strip( $tree ) ) );
}

echo "\n";

ninoDone( $appData );
