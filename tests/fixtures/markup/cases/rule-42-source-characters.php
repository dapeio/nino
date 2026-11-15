<?php
declare(strict_types=1);

// A source may hold ~ and : behind its first character
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '/x~y:z', 'a' => [], 'c' => null, 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '~a:b~c', 'a' => [], 'c' => null, 'o' => 11 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '.f:g', 'a' => [], 'c' => null, 'o' => 22 ],
	],
	'notes'			=> [],
];
