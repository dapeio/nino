<?php return [
	'label' 			=> 'Docs',
	'description' => 'Dense, squared-off and narrow - visible borders, a strong code surface, cool greys and the smallest root size of the ten. Its one band is brand-tinted rather than grey, because the only thing a reference page bands for is a note. For documentation, changelogs and technical reference.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.docs.css',
	'header' 			=> 'v1',
	'footer' 			=> 'v2',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#2563eb',
		'secondary' 	=> '',
		'harmony' 		=> 1,
		'temperature' => 2,
		'saturation' 	=> 1,
		'contrast' 		=> 3,
		'depth' 			=> 1,
		'scale' 			=> 1,
		'volume' 			=> 1,
		'spacing' 		=> 1,
		'shaping' 		=> 1,
		'measure' 		=> 1,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
