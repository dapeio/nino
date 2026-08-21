<?php return [
	'label' 			=> 'Agency',
	'description' => 'Bright corporate look - a sky-blue accent on plain white, League Spartan headlines and generously rounded cards.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.agency.css',
	'header' 			=> 'v1',
	'footer' 			=> 'v1',
	// What /_theme starts this look from. The stylesheet above assigns
	// roles to the --nino-* tokens these settings generate, so the theme
	// stays intact whatever the operator picks here afterwards
	'design' 			=> [
		'primary' 	=> '#4faae8',
		'secondary' => '',
		'contrast' 	=> 'default',
		'colors' 		=> 'default',
		// What the hand-tuned sizes in this theme's stylesheet used to spell
		// out: roomy display type, generous gaps, heavily rounded cards
		'volume' 		=> 'generous',
		'spacing' 	=> 'airy',
		'shaping' 	=> 'round',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
