<?php
declare(strict_types=1);

// Calls of one name nest, and an end closes the next open call of its name
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'section-col', 's' => '', 'a' => [ 'size' => '100' ], 'c' => [
			[ 't' => 'a' ],
			[ 'n' => 'section-col', 's' => '', 'a' => [ 'size' => '50' ], 'c' => [
				[ 't' => 'b' ],
			], 'o' => 25 ],
			[ 't' => 'c' ],
		], 'o' => 0 ],
	],
	'notes'			=> [],
];
