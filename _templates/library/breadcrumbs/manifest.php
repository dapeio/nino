<?php return [
	'category' 	=> 'Navigation',
	'tag' 			=> 'wrap',
	'name' 			=> 'Breadcrumbs',
	'match' 		=> [
		'tags' 			=> [ 'ul', 'ol' ],
		'classes' 	=> [ 'ui-breadcrumbs' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [],
];
