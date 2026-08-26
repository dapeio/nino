<?php return [
	'label' 			=> 'Signal',
	'description' => 'A brand strip across the top and a full-width band through the page in the opposite colour - two brand colours, condensed display type, for shops, events and campaigns.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.signal.css',
	'header' 			=> 'v5',
	'footer' 			=> 'v6',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#e02329',
		'secondary' 	=> '',
		'harmony' 		=> 4,
		'temperature' => 3,
		'saturation' 	=> 3,
		'contrast' 		=> 2,
		'depth' 			=> 2,
		'scale' 			=> 2,
		'volume' 			=> 3,
		'spacing' 		=> 2,
		'shaping' 		=> 2,
		'measure' 		=> 2,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
