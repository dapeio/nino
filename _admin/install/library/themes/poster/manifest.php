<?php return [
	'label' 			=> 'Poster',
	'description' => 'Headlines that take the whole width, heavy black bands and an overlay menu - the largest root size of the ten, set tight. One loud colour and no second. For studios, agencies, campaigns and anything meant to be read across a room.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.poster.css',
	'header' 			=> 'v4',
	'footer' 			=> 'v4',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#f24405',
		'secondary'		=> '',
		'harmony'		=> 1,
		'temperature'	=> 3,
		'saturation'	=> 3,
		'contrast'		=> 3,
		'depth'			=> 1,
		'scale'			=> 3,
		'volume'		=> 3,
		'spacing'		=> 1,
		'shaping'		=> 1,
		'measure'		=> 3,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
