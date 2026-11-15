<?php
declare(strict_types=1);

// A ] and masked quotes and backslashes in a value
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'button', 's' => '', 'a' => [ 'text' => 'a ] b "c" \\ d', 'href' => '/x?a=1&b=2' ], 'c' => null, 'o' => 0 ],
	],
	'notes'			=> [],
];
