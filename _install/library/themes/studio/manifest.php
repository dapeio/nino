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
		'primary' 	=> '#f24405',
		'secondary' => '',
		'contrast' 	=> 'high',
		'colors' 		=> 'default',
		'volume' 		=> 'generous',
		'spacing' 	=> 'tight',
		'shaping' 	=> 'sharp',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
