<?php return [
	// Part of the starter site the Routes step opens on, at this position
	// in the list. A proposal the operator edits or removes - not a default
	// underneath config.php, which could never be removed at all
	'preset' 		=> 4,
	'label' 		=> 'Legal',
	// One route, its body picking the locale-specific template file at
	// render time via the standard [[/nino/http/response/locale]] fill -
	// \Nino\Http::requestRoute() matches routes by 'METHOD://'.$uri as a
	// literal array key (see Install\Webpages::_routeKeys()'s docblock),
	// so an entry mounted at one uri can only ever be one route; two
	// locale-gated routes sharing that uri (this bundle's previous shape)
	// would only ever be reachable at whichever request path equals their
	// own key, never at the uri a Webpages entry actually assigns
	'routes' 		=> [
		'GET://' => [ 'uri' => '/legal', 'body' => '[template /templates/page-legal.[[/nino/http/response/locale]]]', 'navs' => [ 'footer' => 5 ] ],
	],
	'templates' => [
		 'de_DE' => 'page-legal.de_DE.tpl',
		 'en_US' => 'page-legal.en_US.tpl',
		 'html-footer-legal.tpl',
	],
];
