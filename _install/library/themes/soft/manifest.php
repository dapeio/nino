<?php return [
	'label' 			=> 'Soft',
	'description' => 'Rounded, airy and unhurried - a floating header, a brand-tinted footer panel, warm greys, the largest root size of the ten and type that reads gently rather than hard. For practices, studios and local services.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.soft.css',
	'header' 			=> 'v3',
	'footer' 			=> 'v5',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#3f7d62',
		'secondary' 	=> '',
		'harmony' 		=> 3,
		'temperature' => 4,
		'saturation' 	=> 1,
		'contrast' 		=> 1,
		'depth' 			=> 3,
		'scale' 			=> 3,
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
