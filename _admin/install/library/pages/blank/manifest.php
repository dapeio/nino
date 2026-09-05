<?php return [
	'label' 		=> 'Blank',
	'routes' 		=> [
		'GET://new-webpage' => [ 'uri' => '/blank', 'body' => '[template /templates/page-blank]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-blank.tpl' ],
	// Every route picking this unit gets its own copy of the template,
	// named after the route (a /home route becomes templates/page-home.tpl,
	// rendered by a body of '[template /templates/page-home]'). Without it
	// every blank route shares the single page-blank.tpl, so editing one
	// silently rewrites all of them - which is the opposite of what an
	// empty starting point is for. A unit whose template is a finished page
	// (home, contact, legal, ...) is a one-off and deliberately does not
	// declare this: there, sharing is the point. See Webpages::_applyWebpage()
	'templatePerRoute' => true,
];
