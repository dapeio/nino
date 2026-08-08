<?php return [
	'label' 				=> 'Demo: Elements',
	'requiresModules' => [ 'democontent' ],
	'routes' 				=> [
		'GET://.demo-elements' => [ 'uri' => '/.demo-elements', 'body' => '[template /templates/.demo-elements]' ],
	],
	'templates' 		=> [ '.demo-elements.tpl' ],
	'elementTypes' 	=> [ 'demo-services.php' ],
];
