<?php return [
	'label' 			=> 'Rail',
	'description' => 'A vertical navigation rail instead of a top bar, with a compact single-line footer and greys that carry no colour of their own - the layout for portfolios and reference sites with many entries, where the brand belongs where it is put rather than everywhere.',
	'preview' 		=> 'preview.svg',
	'stylesheet' 	=> '/assets/style.theme.rail.css',
	'header' 			=> 'v6',
	'footer' 			=> 'v2',
	// What /_design starts this look from. The stylesheet assigns roles to the
	// --nino-* tokens these settings generate, so the theme survives whatever
	// the operator picks here afterwards - including a different brand colour
	'design' 			=> [
		'primary' 		=> '#4338ca',
		'secondary' 	=> '',
		'harmony' 		=> 2,
		'temperature' => 1,
		'saturation' 	=> 2,
		'contrast' 		=> 2,
		'depth' 			=> 2,
		'scale' 			=> 2,
		'volume' 			=> 2,
		'spacing' 		=> 2,
		'shaping' 		=> 1,
		'measure' 		=> 1,
	],
	'files' 			=> [
		'assets',
		'fonts',
	],
];
