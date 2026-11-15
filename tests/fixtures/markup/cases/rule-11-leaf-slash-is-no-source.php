<?php
declare(strict_types=1);

// The / of " /]" is never a source
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'x' => true ], 'c' => null, 'o' => 6 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '~x', 'a' => [], 'c' => null, 'o' => 14 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '/x', 'a' => [], 'c' => null, 'o' => 23 ],
		[ 't' => ' [a .] [a ~]' ],
	],
	'notes'			=> [],
];
