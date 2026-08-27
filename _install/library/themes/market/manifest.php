<?php return [
	'label' 			=> 'Market',
	'description' => 'A brand strip across the top and a full-width band through the page in the opposite colour - two loud colours, condensed display type, rounded buttons. For shops, events, campaigns and launches.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.market.css',
	'header' 			=> 'v5',
	'footer' 			=> 'v6',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#e11d48',
		'secondary'		=> '',
		'harmony'		=> 4,
		'temperature'	=> 3,
		'saturation'	=> 3,
		'contrast'		=> 2,
		'depth'			=> 2,
		'scale'			=> 2,
		'volume'		=> 3,
		'spacing'		=> 2,
		'shaping'		=> 3,
		'measure'		=> 2,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
