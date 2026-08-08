<?php return [
	'label' 		=> 'Home',
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]' ],
	],
	'files' => [
		'images',
	],
	'templates' => [ 'page-home.tpl' ],
];
