<?php return [
	'label' 		=> 'Services',
	'routes' 		=> [
		'GET://services' => [ 'uri' => '/services', 'body' => '[template /templates/page-services]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-services.tpl' ],
	'suggest' => [
		'uri' 				=> '/services',
		'name' 				=> [ 'en_US' => 'Services', 'de_DE' => 'Leistungen' ],
		'title' 			=> [ 'en_US' => 'Services', 'de_DE' => 'Leistungen' ],
		'description' => [ 'en_US' => 'A short overview of what we offer.', 'de_DE' => 'Ein kurzer Überblick über das, was wir anbieten.' ],
	],
];
