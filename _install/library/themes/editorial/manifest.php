<?php return [
	'label' 			=> 'Editorial',
	'description' => 'Serif body copy on a quiet page, sans headlines and hard corners - built for long text, footnotes and translations rather than for tiles. Sets the text in one hue and decorates in the neighbouring one.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.editorial.css',
	'header' 			=> 'v2',
	'footer' 			=> 'v3',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#215d63',
		'secondary' 	=> '',
		'harmony' 		=> 4,
		'temperature' => 3,
		'saturation' 	=> 1,
		'contrast' 		=> 3,
		'depth' 			=> 1,
		'scale' 			=> 2,
		'volume' 			=> 3,
		'spacing' 		=> 2,
		'shaping' 		=> 1,
		'measure' 		=> 1,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
