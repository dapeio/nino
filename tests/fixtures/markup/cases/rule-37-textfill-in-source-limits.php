<?php
declare(strict_types=1);

// A source takes whole textfills only: a [[ that is not closed with a key makes the mark text
return [
	'containers'	=> [],
	'tree'			=> [
		[ 't' => '[a /x[' ],
		[ 'n' => 'y', 's' => '', 'a' => [], 'c' => null, 'o' => 6 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '/x[[y]]z', 'a' => [], 'c' => null, 'o' => 10 ],
		[ 't' => ' [a /x[' ],
		[ 'n' => 'y', 's' => '', 'a' => [ 'z' => true ], 'c' => null, 'o' => 29 ],
		[ 't' => '] ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'x' => true, '/[[k]]' => true ], 'c' => null, 'o' => 36 ],
	],
	'notes'			=> [],
];
