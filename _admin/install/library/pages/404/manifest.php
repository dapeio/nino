<?php return [
	// Part of the starter site the wizard creates when it ends, at this
	// position in the list - and only where the project has no page route
	// yet. From then on the page is the project's, to edit or remove in the
	// Routes panel: not a default underneath config.php, which could never be
	// removed at all
	'preset' 		=> 3,
	'label' 		=> '404',
	'routes' 		=> [
		'GET://404' => [ 'uri' => '/404', 'body' => '[template /templates/page-404]', 'statusCode' => 404 ],
	],
	'templates' => [ 'page-404.tpl' ],
	'suggest' => [
		'uri' 				=> '/404',
		'name' 				=> [ 'en_US' => 'Page not found', 'de_DE' => 'Seite nicht gefunden' ],
		'title' 			=> [ 'en_US' => 'Page not found', 'de_DE' => 'Seite nicht gefunden' ],
		'description' => [ 'en_US' => 'This page could not be found.', 'de_DE' => 'Diese Seite wurde nicht gefunden.' ],
	],
];
