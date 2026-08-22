<?php return [
	'label' 			=> 'Nocturne',
	'description' => 'Dark in every light - the page sits on the deepest surface rather than following the visitor\'s system setting. For galleries, product shots and anything shown in a dim room.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.nocturne.css',
	'header' 			=> 'v5',
	'footer' 			=> 'v2',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 	=> '#b6a6ff',
		'secondary' => '',
		'contrast' 	=> 'high',
		'colors' 		=> 'vibrant',
		'volume' 		=> 'default',
		'spacing' 	=> 'default',
		'shaping' 	=> 'round',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
