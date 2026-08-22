<?php return [
	'label' 			=> 'Soft',
	'description' => 'Rounded, airy and unhurried - a floating header, panelled footer and generous white space. For practices, studios and local services.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.soft.css',
	'header' 			=> 'v3',
	'footer' 			=> 'v5',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 	=> '#3f7d62',
		'secondary' => '',
		'contrast' 	=> 'default',
		'colors' 		=> 'clean',
		'volume' 		=> 'default',
		'spacing' 	=> 'airy',
		'shaping' 	=> 'round',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
