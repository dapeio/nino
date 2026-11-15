<?php
declare(strict_types=1);

// An unquoted value never replaces a quoted one, and one in front of it is no duplicate
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null, 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'x' => '1' ], 'c' => null, 'o' => 14 ],
	],
	'notes'			=> [
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 0 ],
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 14 ],
	],
];
