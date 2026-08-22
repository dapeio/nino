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
		'primary' 	=> '#4faae8',
		'secondary' => '',
		'contrast' 	=> 'default',
		'colors' 		=> 'default',
		'volume' 		=> 'default',
		'spacing' 	=> 'default',
		'shaping' 	=> 'default',
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
