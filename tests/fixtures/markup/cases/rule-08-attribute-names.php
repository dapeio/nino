<?php
declare(strict_types=1);

// A word is [A-Za-z][A-Za-z0-9_-]*, the form of the name of a field: a mark with a word that starts otherwise is text
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [ 'bC' => 'x', 'd9' => true, 'e-f' => 'y', 'g_h' => true, 'Up' => true ], 'c' => null, 'o' => 0 ],
		[ 't' => ' [a 9x] [a -x] ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'bC' => 'x' ], 'c' => null, 'o' => 43 ],
	],
	'notes'			=> [],
];
