<?php return [
	'label' 			=> 'Surface',
	'description' => 'Large tonal areas, wide margins and light that falls from one side - the page is built from surfaces that lie on each other rather than from lines that separate them, down to a tinted header card and a third colour marking whatever is chosen. For product sites, apps and anything that should read as current.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.surface.css',
	'header' 			=> 'v3',
	'footer' 			=> 'v7',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#6750a4',
		'secondary' 	=> '',
		'harmony' 		=> 3,
		'temperature' => 3,
		'saturation' 	=> 2,
		'contrast' 		=> 2,
		'depth' 			=> 3,
		'scale' 			=> 2,
		'volume' 			=> 2,
		'spacing' 		=> 3,
		'shaping' 		=> 3,
		'measure' 		=> 1,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
