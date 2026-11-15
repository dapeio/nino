<?php
declare(strict_types=1);

// A textfill in a source holds no textfill: the mark is text, and the textfill with one inside is a textfill
return [
	'containers'	=> [],
	'tree'			=> [
		[ 't' => '[a /x' ],
		[ 'f' => 'b[[c]]d' ],
		[ 't' => ']' ],
	],
	'notes'			=> [],
];
