<?php return [
	'label' 		=> 'Blank',
	'routes' 		=> [
		'GET://new-webpage' => [ 'uri' => '/blank', 'body' => '[template /templates/page-blank]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-blank.tpl' ],
];
