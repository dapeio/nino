<?php return [
	'label' 			=> 'Basis',
	'description' => 'The neutral starting point - a plain white page, one blue accent, and every size straight off the framework scale. The look to pick when the content should do the talking.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.basis.css',
	'header' 			=> 'v1',
	'footer' 			=> 'v1',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#4faae8',
		'secondary' 	=> '',
		'harmony' 		=> 1,
		'temperature' => 3,
		'saturation' 	=> 2,
		'contrast' 		=> 2,
		'depth' 			=> 2,
		'scale' 			=> 2,
		'volume' 			=> 2,
		'spacing' 		=> 2,
		'shaping' 		=> 2,
		'measure' 		=> 2,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
