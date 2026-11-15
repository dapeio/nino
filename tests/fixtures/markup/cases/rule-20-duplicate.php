<?php
declare(strict_types=1);

// A duplicate attribute: the last one counts, at the place of the first, and a note
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [ 'x' => '3', 'y' => '2' ], 'c' => null, 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 's' => true ], 'c' => null, 'o' => 22 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'v' => '1' ], 'c' => null, 'o' => 30 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'w' => true ], 'c' => null, 'o' => 42 ],
	],
	'notes'			=> [
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 0 ],
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 22 ],
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 30 ],
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 42 ],
	],
];
