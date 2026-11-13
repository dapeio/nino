<?php return [
	'label' 		=> 'Blank',
	'routes' 		=> [
		'GET://new-webpage' => [ 'uri' => '/blank', 'body' => '[template /templates/page-blank]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-blank.tpl' ],
	'suggest' => [
		'uri' 				=> '/new-webpage',
		'name' 				=> [ 'en_US' => 'New webpage', 'de_DE' => 'Neue Webseite' ],
		'title' 			=> [ 'en_US' => 'New webpage', 'de_DE' => 'Neue Webseite' ],
		'description' => [ 'en_US' => 'This is a new webpage.', 'de_DE' => 'Dies ist eine neue Webseite.' ],
	],
	// A page made from this unit is meant to get its own copy of the template,
	// named after the page (a /home page becomes templates/page-home.tpl,
	// rendered by a body of '[template /templates/page-home]'): shared, every
	// blank page would rewrite all the others when one is edited, which is the
	// opposite of what an empty starting point is for. A unit whose template
	// is a finished page (home, contact, 404, ...) is a one-off and
	// deliberately does not declare this: there, sharing is the point. Nothing
	// reads it at the moment - the wizard creates the 'preset' units and puts
	// this unit's template in place, never a route of it, and the Routes panel
	// wires pages to templates the project has
	'templatePerRoute' => true,
];
