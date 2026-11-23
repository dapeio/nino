<?php return [
	// Part of the starter site the wizard creates when it ends, at this
	// position in the list - and only where the project has no page route
	// yet. From then on the page is the project's, to edit or remove in the
	// Routes panel: not a default underneath config.php, which could never be
	// removed at all
	'preset' 		=> 1,
	'label' 		=> 'Home',
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/page-home]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-home.tpl' ],
	// What the starter pages start this page's name, title and description
	// from: a string, or a string per locale. They are not text fragments -
	// a [[/_nino/webpage/...]] key is the system's, written by the wizard
	// under the Element-URI the page is mounted at, not under this folder's name
	'suggest' => [
		'uri' 				=> '/',
		'name' 				=> [ 'en_US' => 'Home', 'de_DE' => 'Startseite' ],
		'title' 			=> [ 'en_US' => 'Welcome.', 'de_DE' => 'Willkommen.' ],
		'description' => [ 'en_US' => 'This is your first impression.', 'de_DE' => 'Hier steht Dein erster Eindruck.' ],
	],
	'files' => [
		'images/template/page-home/hero/background.svg',
	],
	// The hero of the page is an image slot like any other, seeded with a
	// neutral placeholder drawing - an editor replaces it in the Images panel,
	// and the file, which carries the slot's own name, goes with it
	'imageSlots' => [
		'/template/page-home/hero/background' => [
			'label' 		=> [ 'en_US' => 'Home – hero image', 'de_DE' => 'Startseite – Titelbild' ],
			'width' 		=> 1920,
			'height' 		=> 1080,
			'filename' 	=> 'template/page-home/hero/background.svg',
		],
	],
];
