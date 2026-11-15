<?php
declare(strict_types=1);

// Of three ends for two calls of a name the third is the stray one
return [
	'containers'	=> [ 'a' => true ],
	'tree'			=> [
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => [
			[ 't' => '1' ],
			[ 'n' => 'a', 's' => '', 'a' => [], 'c' => [
				[ 't' => '2' ],
			], 'o' => 4 ],
			[ 't' => '3' ],
		], 'o' => 0 ],
		[ 't' => '4' ],
	],
	'notes'			=> [
		[ 'code' => 'stray-end', 'name' => 'a', 'offset' => 18 ],
	],
];
