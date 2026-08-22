<?php return [
	'label' 			=> 'Signal',
	'description' => 'A brand strip across the top and a full-width accent band through the page - condensed display type for shops, events and campaigns.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.signal.css',
	'header' 			=> 'v5',
	'footer' 			=> 'v6',
	// What /_theme starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 	=> '#e02329',
		'secondary' => '',
		'contrast' 	=> 'default',
		'colors' 		=> 'vibrant',
		'volume' 		=> 'generous',
		'spacing' 	=> 'default',
		'shaping' 	=> 'default',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
