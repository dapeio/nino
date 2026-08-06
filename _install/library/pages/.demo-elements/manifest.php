<?php return [
	'label' 				=> 'Demo: Elements',
	'routes' 				=> [
		'GET://.demo-elements' => [ 'uri' => '/.demo-elements', 'body' => '[template /templates/.demo-elements]' ],
	],
	'templates' 		=> [ '.demo-elements.tpl' ],
	'elementTypes' 	=> [ 'demo-services.php' ],
];
