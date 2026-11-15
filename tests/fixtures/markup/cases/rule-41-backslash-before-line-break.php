<?php
declare(strict_types=1);

// A \ in front of a line break does not mask the line break: it ends nothing and the mark is text
return [
	'containers'	=> [],
	'tree'			=> [
		[ 't' => "[a v=\"x\\\ny\"]" ],
	],
	'notes'			=> [],
];
