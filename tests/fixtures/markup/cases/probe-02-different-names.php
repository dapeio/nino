<?php
declare(strict_types=1);

// Different names nested
return [
	'containers'	=> [ 'box' => true, 'show' => false, 'title' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'box', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'a' ],
			[ 'n' => 'show', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null, 'o' => 6 ],
			[ 't' => 'c' ],
		], 'o' => 0 ],
	],
	'notes'			=> [],
];
