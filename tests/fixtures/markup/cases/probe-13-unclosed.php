<?php
declare(strict_types=1);

// A start with no end
return [
	'containers'	=> [ 'box' => true, 'show' => false, 'title' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'box', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'a ' ],
			[ 'n' => 'show', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null, 'o' => 7 ],
			[ 't' => ' b' ],
		], 'o' => 0 ],
	],
	'notes'			=> [
		[ 'code' => 'unclosed', 'name' => 'box', 'offset' => 0 ],
	],
];
