<?php return [
	'label' 					=> 'Contact',
	'requiresModules' => [ 'forms' ],
	'routes' 					=> [
		'GET://contact' => [ 'uri' => '/contact', 'body' => '[template /templates/page-contact]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' 			=> [ 'page-contact.tpl' ],
];
