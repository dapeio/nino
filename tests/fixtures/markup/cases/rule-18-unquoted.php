<?php
declare(strict_types=1);

// A value with no quotes: the mark stays, the attribute falls away, one note for each call that has any
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null, 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 27 ],
	],
	'notes'			=> [
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 0 ],
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 27 ],
	],
];
