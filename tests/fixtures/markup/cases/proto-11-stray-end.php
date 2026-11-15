<?php
declare(strict_types=1);

// An end with no call is skipped, and the text around it is one node
return [
	'containers'	=> [ 'section-wrap' => true, 'section-row' => true, 'section-col' => true, 'card' => true, 'section-title' => false, 'section-subtitle' => false, 'card-title' => false, 'card-text' => false, 'text' => false, 'button' => false ],
	'tree'			=> [
		[ 't' => 'ab' ],
	],
	'notes'			=> [
		[ 'code' => 'stray-end', 'name' => 'card', 'offset' => 1 ],
	],
];
