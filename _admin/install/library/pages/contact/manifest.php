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
	'suggest' => [
		'uri' 				=> '/contact',
		'name' 				=> [ 'en_US' => 'Contact', 'de_DE' => 'Kontakt' ],
		'title' 			=> [ 'en_US' => 'Contact us', 'de_DE' => 'Kontakt' ],
		'description' => [ 'en_US' => 'Get in touch - we usually reply within one business day.', 'de_DE' => 'Nimm Kontakt auf - wir antworten in der Regel innerhalb eines Werktages.' ],
	],
];
