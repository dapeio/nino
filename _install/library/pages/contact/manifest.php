<?php return [
	// Part of the starter site the Routes step opens on, at this position
	// in the list. A proposal the operator edits or removes - not a default
	// underneath config.php, which could never be removed at all
	'preset' 		=> 2,
	'label' 					=> 'Contact',
	'requiresModules' => [ 'forms' ],
	'routes' 					=> [
		'GET://contact' => [ 'uri' => '/contact', 'body' => '[template /templates/page-contact]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' 			=> [ 'page-contact.tpl' ],
];
