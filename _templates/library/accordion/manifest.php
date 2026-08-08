<?php return [
	'category' 	=> 'Interactive',
	'tag' 			=> 'wrap',
	'name' 			=> 'Accordion Item',
	'match' 		=> [
		'tag' 			=> 'details',
		'classes' 	=> [ 'ui-accordion' ],
	],
	'children' 	=> [ '*' ],
	'use' 			=> [ 'spacing' ],
	'settings' 	=> [
		'name' => [ 'label' => 'Group name', 'type' => 'attr', 'attr' => 'name' ],
		'open' => [ 'label' => 'Open by default', 'type' => 'attrtoggle', 'attr' => 'open' ],
	],
];
