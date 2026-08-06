<?php return [
	'label' 		=> 'About us',
	'routes' 		=> [
		'GET://about-me' => [ 'uri' => '/about-me', 'body' => '[template /templates/page-about-me]' ],
	],
	'templates' => [ 'page-about-me.tpl' ],
];
