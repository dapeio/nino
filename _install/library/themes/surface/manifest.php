<?php return [
	'label' 			=> 'Surface',
	'description' => 'Large tonal areas, wide margins and light that falls from one side - the page is built from surfaces that lie on each other rather than from lines that separate them. For product sites, apps and anything that should read as current.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.surface.css',
	'header' 			=> 'v3',
	'footer' 			=> 'v7',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 	=> '#6750a4',
		'secondary' => '',
		'contrast' 	=> 'default',
		'colors' 		=> 'default',
		'volume' 		=> 'default',
		'spacing' 	=> 'airy',
		'shaping' 	=> 'round',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
