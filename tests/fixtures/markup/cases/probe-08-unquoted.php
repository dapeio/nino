<?php
declare(strict_types=1);

// A value with no quotes: the mark stays, the attribute falls away
return [
	'containers'	=> [ 'box' => true, 'show' => false, 'title' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'show', 's' => '', 'a' => [], 'c' => null, 'o' => 0 ],
	],
	'notes'			=> [
		[ 'code' => 'attribute', 'name' => 'show', 'offset' => 0 ],
	],
];
