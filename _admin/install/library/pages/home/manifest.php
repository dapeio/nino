<?php return [
	// Part of the starter site the Routes step opens on, at this position
	// in the list. A proposal the operator edits or removes - not a default
	// underneath config.php, which could never be removed at all
	'preset' 		=> 1,
	'label' 		=> 'Home',
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-home.tpl' ],
	'files' => [
		'images/page-home/fullscreen-image/background.svg',
	],
	// The hero of the page is an image slot like any other, seeded with a
	// neutral placeholder drawing - an editor replaces it in the Images panel,
	// and the file, which carries the slot's own name, goes with it
	'imageSlots' => [
		'/page-home/fullscreen-image/background' => [
			'label' 		=> [ 'en_US' => 'Home – hero image', 'de_DE' => 'Startseite – Titelbild' ],
			'width' 		=> 1920,
			'height' 		=> 1080,
			'filename' 	=> 'page-home/fullscreen-image/background.svg',
		],
	],
];
