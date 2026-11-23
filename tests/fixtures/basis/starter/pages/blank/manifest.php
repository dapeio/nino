<?php return [
	'label' 		=> 'Blank',
	'routes' 		=> [
		'GET://new-webpage' => [ 'uri' => '/blank', 'body' => '[template /templates/page-blank]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-blank.tpl' ],
	'suggest' => [
		'uri' 				=> '/new-webpage',
		'name' 				=> [ 'en_US' => 'New webpage', 'de_DE' => 'Neue Webseite' ],
		'title' 			=> [ 'en_US' => 'New webpage', 'de_DE' => 'Neue Webseite' ],
		'description' => [ 'en_US' => 'This is a new webpage.', 'de_DE' => 'Dies ist eine neue Webseite.' ],
	],
];
