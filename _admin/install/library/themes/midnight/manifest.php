<?php return [
	'label' 			=> 'Midnight',
	'description' => 'Dark in every light: the page sits on the deepest surface rather than following the visitor&rsquo;s system setting, with a third brand colour for what has to stand off it. Rounded and raised. For galleries, product shots, music and anything shown in a dim room.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.midnight.css',
	'header' 			=> 'v5',
	'footer' 			=> 'v2',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#8b5cf6',
		'secondary'		=> '',
		'harmony'		=> 3,
		'temperature'	=> 3,
		'saturation'	=> 3,
		'contrast'		=> 3,
		'depth'			=> 3,
		'scale'			=> 2,
		'volume'		=> 2,
		'spacing'		=> 2,
		'shaping'		=> 3,
		'measure'		=> 2,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
