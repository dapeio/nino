<?php
declare(strict_types=1);

// A / that ends a word is part of the word; a / that stands by itself behind a blank, with a blank behind it or not, ends the mark as a leaf
return [
	'containers'	=> [ 'a' => true ],
	'tree'			=> [
		[ 'n' => 'a', 's' => '/x/', 'a' => [], 'c' => [
			[ 't' => 'x' ],
		], 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'y' ],
		], 'o' => 13 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 26 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [], 'c' => null, 'o' => 33 ],
	],
	'notes'			=> [
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 13 ],
	],
];
