<?php
declare(strict_types=1);

// A key may carry one textfill of its own, as it stands: one node, the inner one is for the drawing
return [
	'containers'	=> [],
	'tree'			=> [
		[ 'f' => '/_nino/webpage[[/nino/http/response/uri]]/title' ],
		[ 't' => ' ' ],
		[ 'f' => 'a[[b]]c' ],
		[ 't' => ' ' ],
		[ 'f' => '[[x]]y' ],
		[ 't' => ' ' ],
		[ 'f' => '/p[[/q]]' ],
	],
	'notes'			=> [],
];
