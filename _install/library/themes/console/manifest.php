<?php return [
	'label' 			=> 'Console',
	'description' => 'A navigation rail down the side, the smallest root size of the ten, cool greys and a brand-tinted band for notes. Dense, square and wide. For documentation, changelogs, APIs and reference material with many entries.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.console.css',
	'header' 			=> 'v6',
	'footer' 			=> 'v2',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#2563eb',
		'secondary'		=> '',
		'harmony'		=> 1,
		'temperature'	=> 2,
		'saturation'	=> 1,
		'contrast'		=> 3,
		'depth'			=> 1,
		'scale'			=> 1,
		'volume'		=> 1,
		'spacing'		=> 1,
		'shaping'		=> 1,
		'measure'		=> 3,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
