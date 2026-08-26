<?php return [
	'label' 			=> 'Studio',
	'description' => 'Poster typography and heavy black bands - an overlay navigation, a statement footer and headlines that take the whole width.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.studio.css',
	'header' 			=> 'v4',
	'footer' 			=> 'v4',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#f24405',
		'secondary' 	=> '',
		'harmony' 		=> 1,
		'temperature' => 3,
		'saturation' 	=> 2,
		'contrast' 		=> 3,
		'depth' 			=> 1,
		'scale' 			=> 2,
		'volume' 			=> 3,
		'spacing' 		=> 1,
		'shaping' 		=> 1,
		'measure' 		=> 2,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
