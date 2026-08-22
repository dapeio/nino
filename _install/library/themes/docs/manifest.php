<?php return [
	'label' 			=> 'Docs',
	'description' => 'Dense, squared-off and narrow - visible borders, a strong code surface and a compact scale. For documentation, changelogs and technical reference.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.docs.css',
	'header' 			=> 'v1',
	'footer' 			=> 'v2',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 	=> '#2563eb',
		'secondary' => '',
		'contrast' 	=> 'high',
		'colors' 		=> 'clean',
		'volume' 		=> 'compact',
		'spacing' 	=> 'tight',
		'shaping' 	=> 'sharp',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
