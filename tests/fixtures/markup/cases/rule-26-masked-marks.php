<?php
declare(strict_types=1);

// A \ in front of a call, an end, a textfill: the mark is text, the \ is gone; in front of no mark both stay; a mark is masked whole
return [
	'containers'	=> [],
	'tree'			=> [
		[ 't' => '[name] [[/a/b]] [/end] \\[ x] a\\[b] [card x="[y]"] z\\' ],
	],
	'notes'			=> [],
];
