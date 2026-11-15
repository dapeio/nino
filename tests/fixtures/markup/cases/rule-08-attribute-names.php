<?php
declare(strict_types=1);

// An attribute name is [a-z][a-zA-Z0-9]*: a mark with another word is text
return [
	'containers'	=> [],
	'tree'			=> [
		[ 't' => '[a bC="x" d9 e-f="y"] [a g_h] ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'bC' => 'x', 'd9' => true ], 'c' => null, 'o' => 30 ],
	],
	'notes'			=> [],
];
