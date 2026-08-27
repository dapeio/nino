<?php return [
	'label' 			=> 'Bureau',
	'description' => 'A company page that reads as one: a plain bar, cool greys, square corners and a second brand colour one step from the first. For agencies, firms, associations and anything that has to look settled rather than new.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.bureau.css',
	'header' 			=> 'v1',
	'footer' 			=> 'v3',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#1e40af',
		'secondary'		=> '',
		'harmony'		=> 2,
		'temperature'	=> 2,
		'saturation'	=> 2,
		'contrast'		=> 2,
		'depth'			=> 2,
		'scale'			=> 2,
		'volume'		=> 2,
		'spacing'		=> 2,
		'shaping'		=> 1,
		'measure'		=> 2,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
