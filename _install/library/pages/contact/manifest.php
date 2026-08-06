<?php return [
	'label' 					=> 'Contact',
	'requiresModules' => [ 'forms' ],
	'routes' 					=> [
		'GET://contact' => [ 'uri' => '/contact', 'body' => '[template /templates/page-contact]' ],
	],
	'templates' 			=> [ 'page-contact.tpl' ],
];
