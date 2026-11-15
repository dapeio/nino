<?php
declare(strict_types=1);

// A bare word is true, fill="no" is the string no
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'card', 's' => '', 'a' => [ 'fill' => true ], 'c' => [], 'o' => 0 ],
		[ 'n' => 'card', 's' => '', 'a' => [ 'fill' => 'no' ], 'c' => [], 'o' => 18 ],
	],
	'notes'			=> [],
];
