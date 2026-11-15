<?php
declare(strict_types=1);

// What is open at the end of the document is closed, each with a note
return [
	'containers'	=> [ 'a' => true, 'b' => true ],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'x' ],
			[ 'n' => 'b', 's' => '', 'a' => [], 'c' => [
				[ 't' => 'y' ],
			], 'o' => 4 ],
		], 'o' => 0 ],
	],
	'notes'			=> [
		[ 'code' => 'unclosed', 'name' => 'a', 'offset' => 0 ],
		[ 'code' => 'unclosed', 'name' => 'b', 'offset' => 4 ],
	],
];
