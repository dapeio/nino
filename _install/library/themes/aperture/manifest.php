<?php return [
	'label' 			=> 'Aperture',
	'description' => 'A gallery wall: paper-white ground, greys with no colour cast at all, square corners and a page that gets out of the way. Wide images, small tracked captions and a header that is a wordmark and nothing else. For photography, portfolios and anything where the picture is the content.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.aperture.css',
	'header' 			=> 'v4',
	'footer' 			=> 'v1',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#3f4a52',
		'secondary' 	=> '',
		'harmony' 		=> 1,
		'temperature' => 1,
		'saturation' 	=> 1,
		'contrast' 		=> 3,
		'depth' 			=> 1,
		'scale' 			=> 2,
		'volume' 			=> 3,
		'spacing' 		=> 3,
		'shaping' 		=> 1,
		'measure' 		=> 3,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
