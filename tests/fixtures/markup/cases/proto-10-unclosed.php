<?php
declare(strict_types=1);

// A call that is never closed is closed at the end of the document
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'card', 's' => '', 'a' => [], 'c' => [
			[ 'n' => 'text', 's' => '', 'a' => [ 'text' => 'a' ], 'c' => null, 'o' => 6 ],
		], 'o' => 0 ],
	],
	'notes'			=> [
		[ 'code' => 'unclosed', 'name' => 'card', 'offset' => 0 ],
	],
];
