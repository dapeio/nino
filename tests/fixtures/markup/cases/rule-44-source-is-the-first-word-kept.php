<?php
declare(strict_types=1);

// A word with an unquoted value is dropped, and a word that looks like a source behind only dropped words is the source; behind a word that is kept it is a switch
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'n' => 'a', 's' => '/k', 'a' => [], 'c' => null, 'o' => 0 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '', 'a' => [ 'y' => '1', '/k' => true ], 'c' => null, 'o' => 11 ],
		[ 't' => ' ' ],
		[ 'n' => 'a', 's' => '~t', 'a' => [], 'c' => null, 'o' => 24 ],
	],
	'notes'			=> [
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 0 ],
		[ 'code' => 'attribute', 'name' => 'a', 'offset' => 24 ],
	],
];
