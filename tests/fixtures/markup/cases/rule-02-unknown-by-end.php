<?php
declare(strict_types=1);

// A name that is not known is a container exactly if its end stands somewhere
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'x', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'a' ],
		], 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'y', 's' => '', 'a' => [], 'c' => null, 'o' => 9 ],
		[ 't' => 'b' ],
	],
	'notes'			=> [],
];
