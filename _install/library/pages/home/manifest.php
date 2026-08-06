<?php return [
	'label' 		=> 'Home',
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]' ],
	],
	'templates' => [ 'page-home.tpl' ],
];
