<?php
declare(strict_types=1);

// A key keeps a character of its own beside the inner textfill; a textfill that is not closed is text
return [
	'containers'	=> [],
	'tree'			=> [
		[ 't' => '[[' ],
		[ 'f' => 'x' ],
		[ 't' => ']] [[a' ],
		[ 'f' => 'b' ],
		[ 't' => ' [[a' ],
		[ 'f' => 'b[[c]]' ],
		[ 't' => ' ' ],
		[ 'f' => 'a[[b]]' ],
	],
	'notes'			=> [],
];
