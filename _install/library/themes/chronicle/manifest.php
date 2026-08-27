<?php return [
	'label' 			=> 'Chronicle',
	'description' => 'Serif body copy on a warm page, a sans headline above it and a tinted band for what is quoted rather than said. Narrow measure, hard corners, no shadows. For magazines, journals, long-form writing and documentation that is read rather than searched.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.chronicle.css',
	'header' 			=> 'v2',
	'footer' 			=> 'v3',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary'		=> '#35566e',
		'secondary'		=> '',
		'harmony'		=> 4,
		'temperature'	=> 4,
		'saturation'	=> 1,
		'contrast'		=> 3,
		'depth'			=> 1,
		'scale'			=> 2,
		'volume'		=> 3,
		'spacing'		=> 2,
		'shaping'		=> 1,
		'measure'		=> 1,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
