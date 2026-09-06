<?php return [
	'label' 			=> 'Practice',
	'description' => 'Rounded, warm and unhurried: the largest root size, gentle contrast, a brand-tinted footer panel and a second colour one step from the first. For practices, studios, local services and anyone whose visitors are looking for reassurance rather than novelty.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.practice.css',
	'header' 			=> 'v3',
	'footer' 			=> 'v5',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#4d7c5f',
		'secondary'		=> '',
		'harmony'		=> 2,
		'temperature'	=> 4,
		'saturation'	=> 1,
		'contrast'		=> 1,
		'depth'			=> 3,
		'scale'			=> 3,
		'volume'		=> 1,
		'spacing'		=> 3,
		'shaping'		=> 3,
		'measure'		=> 1,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
