<?php
declare(strict_types=1);

// An unknown name is a leaf, and a container where its end stands somewhere
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 't' => 'a ' ],
		[ 'n' => 'gallery', 's' => '', 'a' => [ 'album' => 'x' ], 'c' => null, 'o' => 2 ],
		[ 't' => ' b ' ],
		[ 'n' => 'slider', 's' => '', 'a' => [], 'c' => [
			[ 't' => 'c' ],
		], 'o' => 24 ],
		[ 't' => ' d' ],
	],
	'notes'			=> [],
];
