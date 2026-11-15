<?php
declare(strict_types=1);

// A bare word is true; what no, false, 0 and off mean is for the register
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [ 'fill' => true ], 'c' => null, 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'fill' => 'no' ], 'c' => null, 'o' => 9 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'fill' => '0' ], 'c' => null, 'o' => 23 ],
	],
	'notes'			=> [],
];
