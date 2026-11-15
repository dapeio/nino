<?php
declare(strict_types=1);

// A textfill is [[ and the characters of a key and ]]: anything else between the brackets is text
return [
	'containers'	=> [],
	'tree'			=> [
		[ 't' => 'a ' ],
		[ 'f' => '/project/company/general/name' ],
		[ 't' => ' b ' ],
		[ 'f' => '.id' ],
		[ 't' => ' ' ],
		[ 'f' => 'title' ],
		[ 't' => ' ' ],
		[ 'f' => 'imageAlt' ],
		[ 't' => ' ' ],
		[ 'f' => 'content:card-image' ],
		[ 't' => ' [[a|b]] [[ x ]] [[]]' ],
	],
	'notes'			=> [],
];
