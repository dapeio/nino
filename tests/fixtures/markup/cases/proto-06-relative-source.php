<?php
declare(strict_types=1);

// A relative source on a leaf in three containers
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'section-wrap', 's' => '', 'a' => [ 'id' => 'hero' ], 'c' => [
			[ 'n' => 'section-row', 's' => '', 'a' => [], 'c' => [
				[ 'n' => 'section-col', 's' => '', 'a' => [], 'c' => [
					[ 'n' => 'section-title', 's' => '~title', 'a' => [], 'c' => null, 'o' => 50 ],
				], 'o' => 37 ],
			], 'o' => 24 ],
		], 'o' => 0 ],
	],
	'notes'			=> [],
];
