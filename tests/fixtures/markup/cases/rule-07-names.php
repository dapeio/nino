<?php
declare(strict_types=1);

// A name is [a-z][a-z0-9-]*: anything else is text
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a-b1', 's' => '', 'a' => [ 'c' => true ], 'c' => null, 'o' => 0 ],
		[ 't' => ' [1a] [A] [a_b] [a.b] [-a]' ],
	],
	'notes'			=> [],
];
