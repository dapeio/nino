<?php return [
	'category' 	=> 'Interactive',
	'tag' 			=> 'wrap',
	'name' 			=> 'Tab Panel',
	'match' 		=> [
		'tag' 			=> 'div',
		'classes' 	=> [ 'js-tabs-panel' ],
	],
	'children' 	=> [ '*' ],
	'palette' 	=> false,
	'settings' 	=> [
		'id' 		=> [ 'label' => 'Panel ID', 'type' => 'attr', 'attr' => 'id' ],
		'active' => [ 'label' => 'Initially active', 'type' => 'classtoggle', 'class' => 'active' ],
	],
];
