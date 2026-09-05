<?php return [
	// Part of the starter site the Routes step opens on, at this position
	// in the list. A proposal the operator edits or removes - not a default
	// underneath config.php, which could never be removed at all
	'preset' 		=> 1,
	'label' 		=> 'Home',
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-home.tpl' ],
	'files' => [
		'images/demo.jpg',
	],
];
