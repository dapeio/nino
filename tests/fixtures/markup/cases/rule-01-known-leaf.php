<?php
declare(strict_types=1);

// A name that is known as a leaf has no end: [/show] is a stray end, and the text around it one node
return [
	'containers'	=> [ 'box' => true, 'show' => false ],
	'tree'			=> [
		[ 'n' => 'box', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'a' ],
			[ 'n' => 'show', 's' => '', 'a' => [], 'c' => null, 'o' => 6 ],
			[ 't' => 'bc' ],
		], 'o' => 0 ],
	],
	'notes'			=> [
		[ 'code' => 'stray-end', 'name' => 'show', 'offset' => 13 ],
	],
];
