<?php
declare(strict_types=1);

// [name /] is a leaf where the same name is a container elsewhere
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'x', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ],
		[ 'n' => 'x', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'a' ],
		], 'o' => 5 ],
	],
	'notes'			=> [],
];
