<?php
declare(strict_types=1);

// An end closes the open calls inside it; the notes are in the order of the document
return [
	'containers'	=> [ 'a' => true, 'b' => true, 'c' => true ],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => [
			[ 'n' => 'b', 's' => '', 'a' => [], 'c' => [
				[ 'n' => 'c', 's' => '', 'a' => [], 'c' => [
					[ 't' => 'x' ],
				], 'o' => 6 ],
			], 'o' => 3 ],
		], 'o' => 0 ],
		[ 't' => 'y' ],
	],
	'notes'			=> [
		[ 'code' => 'unclosed', 'name' => 'b', 'offset' => 3 ],
		[ 'code' => 'unclosed', 'name' => 'c', 'offset' => 6 ],
	],
];
