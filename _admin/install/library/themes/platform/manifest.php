<?php return [
	'label' 			=> 'Platform',
	'description' => 'Surfaces that lie on each other rather than lines that separate them: a floating bar, raised cards, wide margins and a third colour marking whatever is chosen. For products, apps, SaaS and anything that should read as current.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.platform.css',
	'header' 			=> 'v3',
	'footer' 			=> 'v7',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#6366f1',
		'secondary'		=> '',
		'harmony'		=> 3,
		'temperature'	=> 2,
		'saturation'	=> 2,
		'contrast'		=> 2,
		'depth'			=> 3,
		'scale'			=> 2,
		'volume'		=> 2,
		'spacing'		=> 3,
		'shaping'		=> 3,
		'measure'		=> 1,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
