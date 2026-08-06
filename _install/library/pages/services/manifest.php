<?php return [
	'label' 		=> 'Services',
	'routes' 		=> [
		'GET://services' => [ 'uri' => '/services', 'body' => '[template /templates/page-services]' ],
	],
	'templates' => [ 'page-services.tpl' ],
];
