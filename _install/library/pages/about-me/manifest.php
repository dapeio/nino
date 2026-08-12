<?php return [
	'label' 		=> 'About us',
	'routes' 		=> [
		'GET://about-me' => [ 'uri' => '/about-me', 'body' => '[template /templates/page-about-me]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-about-me.tpl' ],
];
