<?php
declare(strict_types=1);

// Same name nested
return [
	'containers'	=> [ 'box' => true, 'show' => false, 'title' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'box', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'a' ],
			[ 'n' => 'box', 's' => '', 'a' => [], 'c' => [
				[ 't' => 'b' ],
			], 'o' => 6 ],
			[ 't' => 'c' ],
		], 'o' => 0 ],
	],
	'notes'			=> [],
];
