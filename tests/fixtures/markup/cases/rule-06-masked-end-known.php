<?php
declare(strict_types=1);

// An end that a \ masks does not close a container that is known
return [
	'containers'	=> [ 'x' => true ],
	'tree'			=> [
		[ 'n' => 'x', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'a[/x]' ],
		], 'o' => 0 ],
	],
	'notes'			=> [
		[ 'code' => 'unclosed', 'name' => 'x', 'offset' => 0 ],
	],
];
