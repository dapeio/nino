<?php return [
	'label' 			=> 'Rail',
	'description' => 'A vertical navigation rail instead of a top bar, with a compact single-line footer - the layout for portfolios and reference sites with many entries.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.rail.css',
	'header' 			=> 'v6',
	'footer' 			=> 'v2',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 	=> '#4338ca',
		'secondary' => '',
		'contrast' 	=> 'default',
		'colors' 		=> 'default',
		'volume' 		=> 'default',
		'spacing' 	=> 'default',
		'shaping' 	=> 'sharp',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
