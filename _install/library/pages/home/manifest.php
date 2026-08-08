<?php return [
	'label' 		=> 'Home',
	'requiresModules' => [ 'democontent' ],
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]' ],
	],
	'templates' => [ 'page-home.tpl' ],
];
