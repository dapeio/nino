<?php return [
	'label' 		=> 'Home',
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-home.tpl' ],
	'files' => [
		'images/demo.jpg',
	],
];
