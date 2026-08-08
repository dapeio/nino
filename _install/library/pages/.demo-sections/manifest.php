<?php return [
	'label' 		=> 'Demo: Sections',
	'requiresModules' => [ 'democontent' ],
	'routes' 		=> [
		'GET://.demo-sections' => [ 'uri' => '/.demo-sections', 'body' => '[template /templates/.demo-sections]' ],
	],
	'templates' => [ '.demo-sections.tpl' ],
];
