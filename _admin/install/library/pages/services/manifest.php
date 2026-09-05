<?php return [
	'label' 		=> 'Services',
	'routes' 		=> [
		'GET://services' => [ 'uri' => '/services', 'body' => '[template /templates/page-services]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-services.tpl' ],
];
