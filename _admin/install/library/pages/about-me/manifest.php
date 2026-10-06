<?php return [
	'label' 		=> 'About us',
	'routes' 		=> [
		'GET://about-me' => [ 'uri' => '/about-me', 'body' => '[template /templates/page-about-me]', 'navs' => [ 'main' => 5, 'footer' => 5 ] ],
	],
	'templates' => [ 'page-about-me.tpl' ],
	'suggest' => [
		'uri' 				=> [ 'en_US' => '/about-me', 'de_DE' => '/about' ],
		'name' 				=> [ 'en_US' => 'About us', 'de_DE' => 'Über mich' ],
		'title' 			=> [ 'en_US' => 'About us', 'de_DE' => 'Über mich' ],
		'description' => [ 'en_US' => 'Tell visitors a bit about yourself or your company here.', 'de_DE' => 'Erzähle hier etwas über Dich oder Dein Unternehmen.' ],
	],
];
