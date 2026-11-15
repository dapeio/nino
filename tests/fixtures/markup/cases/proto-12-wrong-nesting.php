<?php
declare(strict_types=1);

// An end closes the calls inside it as well
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 'n' => 'card', 's' => '', 'a' => [], 'c' => [
			[ 'n' => 'section-col', 's' => '', 'a' => [], 'c' => [
				[ 't' => 'x' ],
			], 'o' => 6 ],
		], 'o' => 0 ],
		[ 't' => 'y' ],
	],
	'notes'			=> [
		[ 'code' => 'unclosed', 'name' => 'section-col', 'offset' => 6 ],
	],
];
