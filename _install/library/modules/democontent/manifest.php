<?php return [
	'label' 			=> 'Demo Content',
	'requiresModules' => ['forms','localepicker','navigation','newsletter'],
	'files' => [
		'images',
		'favicon',
		'templates',
	],
	'routes' 		=> [
		'GET://' => [ 'uri' => '/home', 'body' => '[template /templates/.demo/page-home]' ],
	],
	'active' => true,
	'elementTypes' 	=> [ 'elements/faq.php', 'elements/portfolio.php', 'elements/services.php', 'elements/team.php', 'elements/testimonials.php' ],
];
